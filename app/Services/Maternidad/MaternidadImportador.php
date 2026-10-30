<?php

namespace App\Services\Maternidad;

use App\Models\MaternidadCaso;
use App\Models\MaternidadImportacion;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use ZipArchive;

final class MaternidadImportador
{
    public const COLUMNAS = ['referencia_caso', 'rut', 'rbd', 'fuente', 'nombres', 'apellido_paterno', 'apellido_materno',
        'estamento', 'calidad_juridica', 'fecha_ingreso', 'fecha_termino', 'fecha_registro', 'fecha_certificado_embarazo',
        'fpc', 'fpp', 'fnh', 'ley_dominga', 'fecha_ley_dominga', 'lactancia', 'fecha_inicio_lactancia',
        'postnatal_parental', 'fecha_inicio_postnatal', 'fecha_termino_postnatal', 'modalidad_postnatal', 'fecha_rex_postnatal', 'observacion'];
    public const COLUMNAS_HIJOS = ['rut_funcionaria', 'referencia_caso', 'nombre_completo', 'fecha_nacimiento', 'rut_hijo'];

    public function plantilla(): Spreadsheet
    {
        $libro = new Spreadsheet;
        $libro->getActiveSheet()->setTitle('Casos')->fromArray([self::COLUMNAS]);
        $libro->createSheet()->setTitle('Hijos')->fromArray([self::COLUMNAS_HIJOS]);
        $libro->createSheet()->setTitle('Instrucciones')->fromArray([
            ['Maternidad · Instrucciones'],
            ['Una fila por caso en Casos. Referencia y RUT son obligatorios; use una referencia distinta para cada embarazo.'],
            ['La carga crea casos nuevos. Una referencia ya registrada para el mismo RUT se rechaza; no sobrescribe expedientes.'],
            ['Los nombres y datos laborales vacíos se completan desde el vínculo encontrado. Revise nombres separados automáticamente.'],
            ['Si hay varios establecimientos, indique RBD. Fuente opcional: padron:123 o reemplazo:456, según la búsqueda individual.'],
            ['Estamento: docente o asistente. Beneficios: SI o NO. Modalidad postnatal: completo o compartido.'],
            ['Fechas: AAAA-MM-DD o celdas de fecha Excel. No se admiten fórmulas. Registro vacío: fecha actual.'],
            ['Hijos: una fila por hijo, enlazada por RUT de funcionaria y referencia de caso. Esta hoja puede quedar vacía.'],
            ['La carga no incluye archivos. Los certificados se adjuntan después desde Editar caso.'],
            ['Un beneficio informado sin respaldo queda pendiente y no se considera acreditado.'],
            ['Nacimiento sin certificado: el fuero continúa como estimado por FPP hasta adjuntar el certificado.'],
            ['La previsualización no crea expedientes. Revise y confirme la importación dentro de 30 minutos.'],
            ['Máximo '.config('maternidad.max_casos_importacion').' casos y '.config('maternidad.max_hijos_importacion').' hijos por archivo.'],
        ]);
        foreach ([$libro->getSheet(0), $libro->getSheet(1)] as $hoja) {
            $ultima = $hoja->getHighestColumn();
            $hoja->freezePane('C2');
            $hoja->getStyle('A1:'.$ultima.'1')->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '0B3D91']], 'alignment' => ['wrapText' => true]]);
            $hoja->getRowDimension(1)->setRowHeight(35);
            foreach (range(1, $hoja->getHighestDataColumn() === 'E' ? 5 : count(self::COLUMNAS)) as $i) {
                $hoja->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i))->setWidth(25);
            }
            $hoja->getStyle('A2:'.$ultima.'501')->getNumberFormat()->setFormatCode('@');
        }
        $libro->getSheet(2)->getColumnDimension('A')->setWidth(120);
        $libro->getSheet(2)->getStyle('A1:A13')->getAlignment()->setWrapText(true);
        return $libro;
    }

    public function previsualizar(UploadedFile $archivo, int $actor): MaternidadImportacion
    {
        $zip = new ZipArchive;
        if ($zip->open($archivo->getRealPath()) !== true) throw ValidationException::withMessages(['archivo' => 'El archivo no es un libro XLSX válido.']);
        try {
            $bytes = 0;
            if ($zip->numFiles > 2000) throw new \RuntimeException;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $bytes += (int) $stat['size'];
                if ($bytes > 50 * 1024 * 1024 || (int) $stat['size'] > 20 * 1024 * 1024) throw new \RuntimeException;
            }
        } catch (\Throwable) {
            throw ValidationException::withMessages(['archivo' => 'El contenido del libro supera los límites de importación.']);
        } finally { $zip->close(); }

        $lector = (new Xlsx)->setReadDataOnly(true);
        $info = $lector->listWorksheetInfo($archivo->getRealPath());
        foreach ($info as $hoja) {
            $limite = $hoja['worksheetName'] === 'Hijos' ? config('maternidad.max_hijos_importacion') : config('maternidad.max_casos_importacion');
            if ($hoja['totalRows'] > $limite + 1 || $hoja['totalColumns'] > 40) throw ValidationException::withMessages(['archivo' => 'El libro supera el máximo de filas o columnas admitido.']);
        }
        $libro = $lector->setLoadSheetsOnly(['Casos', 'Hijos'])->load($archivo->getRealPath());
        $errores = []; $payload = []; $hijosPorCaso = [];
        try {
            $casos = $libro->getSheetByName('Casos');
            if (! $casos) throw ValidationException::withMessages(['archivo' => 'El libro debe contener la hoja Casos. Descargue la plantilla.']);
            $this->cabecera($casos, self::COLUMNAS);
            if ($hojaHijos = $libro->getSheetByName('Hijos')) {
                $this->cabecera($hojaHijos, self::COLUMNAS_HIJOS);
                foreach ($this->filas($hojaHijos, self::COLUMNAS_HIJOS) as $fila => $valores) {
                    try {
                        $rut = MaternidadRut::normalizar($valores['rut_funcionaria']);
                        if (blank($valores['referencia_caso'])) throw ValidationException::withMessages(['referencia_caso' => 'Indique la referencia del caso.']);
                        $key = $rut.'|'.trim($valores['referencia_caso']);
                        $hijosPorCaso[$key][] = ['nombre_completo' => $valores['nombre_completo'],
                            'fecha_nacimiento' => $this->fecha($valores['fecha_nacimiento']), 'rut' => $valores['rut_hijo']];
                    } catch (ValidationException $e) { $this->errores($errores, 'Hijos', $fila, $e); }
                }
            }
            $vistos = [];
            foreach ($this->filas($casos, self::COLUMNAS) as $fila => $datos) {
                try {
                    if (blank($datos['referencia_caso'])) throw ValidationException::withMessages(['referencia_caso' => 'La referencia es obligatoria en la carga masiva.']);
                    $rut = MaternidadRut::normalizar($datos['rut']);
                    $key = $rut.'|'.trim($datos['referencia_caso']);
                    if (isset($vistos[$key])) throw ValidationException::withMessages(['referencia_caso' => 'El archivo repite el mismo caso y RUT.']);
                    $vistos[$key] = true;
                    foreach (MaternidadRegistroService::FECHAS as $campo) $datos[$campo] = $this->fecha($datos[$campo]);
                    $datos['fecha_registro'] ??= today()->toDateString();
                    foreach (['ley_dominga', 'lactancia', 'postnatal_parental'] as $campo) {
                        $valor = Str::upper(Str::ascii(trim((string) $datos[$campo])));
                        if (! in_array($valor, ['', 'NO', 'SI', '0', '1'], true)) throw ValidationException::withMessages([$campo => 'Use SI o NO.']);
                        $datos[$campo] = in_array($valor, ['SI', '1'], true);
                    }
                    foreach (['estamento', 'modalidad_postnatal'] as $campo) {
                        $datos[$campo] = Str::lower(Str::ascii(trim((string) $datos[$campo])));
                        if ($campo === 'estamento' && in_array($datos[$campo], ['aaee', 'asistentes'], true)) $datos[$campo] = 'asistente';
                    }
                    foreach (['nombres', 'apellido_paterno', 'apellido_materno', 'estamento', 'calidad_juridica', 'fecha_ingreso', 'fecha_termino'] as $campo) {
                        if (blank($datos[$campo])) unset($datos[$campo]);
                    }
                    if (filled($datos['rbd'])) $datos['rbd'] = (string) $datos['rbd'];
                    $datos['hijos'] = $hijosPorCaso[$key] ?? [];
                    $preparado = app(MaternidadRegistroService::class)->preparar($datos, [], null, true);
                    $payload[] = $preparado;
                } catch (ValidationException $e) { $this->errores($errores, 'Casos', $fila, $e); }
            }
            foreach (array_keys($hijosPorCaso) as $key) if (! isset($vistos[$key])) $errores[] = 'Hijos: existe un grupo de hijos sin caso correspondiente en la hoja Casos.';
            if ($errores) throw ValidationException::withMessages(['archivo' => array_slice($errores, 0, 50)]);
            if (! $payload) throw ValidationException::withMessages(['archivo' => 'El libro no contiene casos para registrar.']);
            return MaternidadImportacion::create(['uuid' => (string) Str::uuid(), 'user_id' => $actor,
                'payload' => $payload, 'cantidad' => count($payload), 'expira_at' => now()->addMinutes(30)]);
        } finally { $libro->disconnectWorksheets(); }
    }

    public function confirmar(MaternidadImportacion $importacion, int $actor): int
    {
        return DB::transaction(function () use ($importacion, $actor) {
            $importacion = MaternidadImportacion::whereKey($importacion->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $importacion->user_id === $actor, 403);
            if ($importacion->procesada_at) return (int) $importacion->cantidad;
            if ($importacion->expira_at->isPast()) throw ValidationException::withMessages(['importacion' => 'La previsualización expiró. Cargue nuevamente el archivo.']);
            foreach ($importacion->payload as $preparado) {
                $datos = $preparado['datos'];
                if (MaternidadCaso::where('rut', $datos['rut'])->where('referencia_caso', $datos['referencia_caso'])->exists()) {
                    throw ValidationException::withMessages(['importacion' => 'Uno de los casos ya fue registrado después de la previsualización. Revise y vuelva a cargar el archivo.']);
                }
                if (! \App\Models\Establecimiento::whereKey($datos['establecimiento_id'])->exists()) throw ValidationException::withMessages(['importacion' => 'Un establecimiento dejó de estar disponible. Vuelva a preparar la carga.']);
                app(MaternidadRegistroService::class)->guardar($preparado, [], $actor, null, null, true);
            }
            $importacion->update(['procesada_at' => now(), 'payload' => null]);
            return (int) $importacion->cantidad;
        });
    }

    private function cabecera($hoja, array $esperada): void
    {
        $actual = $hoja->rangeToArray('A1:'.\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($esperada)).'1', null, false, false)[0];
        if ($actual !== $esperada) throw ValidationException::withMessages(['archivo' => 'Los encabezados de '.$hoja->getTitle().' no coinciden con la plantilla.']);
    }

    private function filas($hoja, array $columnas): \Generator
    {
        foreach (range(2, max(2, $hoja->getHighestDataRow())) as $fila) {
            $valores = []; $tieneValor = false;
            foreach ($columnas as $i => $nombre) {
                $celda = $hoja->getCell([$i + 1, $fila]);
                if ($celda->getDataType() === DataType::TYPE_FORMULA) throw ValidationException::withMessages(['archivo' => $hoja->getTitle().' fila '.$fila.': no se admiten fórmulas.']);
                $valor = $celda->getValue();
                if ($valor instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) $valor = $valor->getPlainText();
                $valores[$nombre] = is_string($valor) ? trim($valor) : $valor;
                $tieneValor = $tieneValor || filled($valor);
            }
            if ($tieneValor) yield $fila => $valores;
        }
    }

    private function fecha($valor): ?string
    {
        if (blank($valor)) return null;
        if (is_numeric($valor)) {
            if ((float) $valor < 1 || (float) $valor > 150000) throw ValidationException::withMessages(['fecha' => 'La fecha de Excel no es válida.']);
            return Date::excelToDateTimeObject((float) $valor)->format('Y-m-d');
        }
        foreach (['Y-m-d', 'd-m-Y', 'd/m/Y'] as $formato) {
            try {
                $fecha = CarbonImmutable::createFromFormat('!'.$formato, (string) $valor);
                if ($fecha && $fecha->format($formato) === (string) $valor) return $fecha->toDateString();
            } catch (\Throwable) { }
        }
        throw ValidationException::withMessages(['fecha' => 'Use una fecha válida AAAA-MM-DD o una celda de fecha Excel.']);
    }

    private function errores(array &$errores, string $hoja, int $fila, ValidationException $error): void
    {
        foreach ($error->errors() as $campo => $mensajes) foreach ($mensajes as $mensaje) $errores[] = $hoja.' · fila '.$fila.' · '.$campo.': '.$mensaje;
    }
}
