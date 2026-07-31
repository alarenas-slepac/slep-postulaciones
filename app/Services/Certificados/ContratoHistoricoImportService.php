<?php

namespace App\Services\Certificados;

use App\Models\CertificadoImportacion;
use App\Support\MaeChunkReadFilter;
use App\Support\RutChile;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use XMLReader;
use ZipArchive;

class ContratoHistoricoImportService
{
    public function encolar(UploadedFile $file, int $userId): CertificadoImportacion
    {
        if (! $file->isValid()) {
            throw ValidationException::withMessages([
                'excel' => 'No se pudo subir el archivo histórico. Intenta nuevamente.',
            ]);
        }

        $extension = Str::lower((string) $file->getClientOriginalExtension());
        if (! in_array($extension, ['xlsx', 'xls'], true)) {
            throw ValidationException::withMessages([
                'excel' => 'El archivo histórico debe estar en formato Excel (.xlsx o .xls).',
            ]);
        }

        $hash = hash_file('sha256', $file->getRealPath());
        if ($hash === false) {
            throw ValidationException::withMessages([
                'excel' => 'No fue posible verificar la integridad del archivo histórico.',
            ]);
        }
        if (CertificadoImportacion::query()->where('hash_archivo', $hash)->exists()) {
            throw ValidationException::withMessages([
                'excel' => 'Este mismo archivo ya fue cargado. Revisa el historial de importaciones.',
            ]);
        }

        $directorio = 'imports/certificados/contratos-historicos/'.now()->format('Y/m');
        Storage::disk('local')->makeDirectory($directorio);
        $nombreSeguro = preg_replace(
            '/[^A-Za-z0-9._-]+/',
            '-',
            $file->getClientOriginalName()
        ) ?: 'historico-contratos.'.$extension;
        $ruta = $file->storeAs(
            $directorio,
            now()->format('Ymd_His').'_'.Str::lower(Str::random(8))
                .'_'.$nombreSeguro,
            'local'
        );

        if (! $ruta || ! Storage::disk('local')->exists($ruta)) {
            throw ValidationException::withMessages([
                'excel' => 'No fue posible guardar el archivo para iniciar la importación.',
            ]);
        }

        return CertificadoImportacion::query()->create([
            'nombre_archivo' => $file->getClientOriginalName(),
            'ruta_archivo' => $ruta,
            'hash_archivo' => $hash,
            'estado' => 'pendiente',
            'es_vigente' => false,
            'subido_por' => $userId,
        ]);
    }

    public function procesar(int $importacionId): CertificadoImportacion
    {
        $importacion = CertificadoImportacion::query()->findOrFail($importacionId);
        $rutaCompleta = Storage::disk('local')->path($importacion->ruta_archivo);

        if (! is_file($rutaCompleta)) {
            throw ValidationException::withMessages([
                'excel' => 'No se encontró el archivo histórico almacenado.',
            ]);
        }

        $importacion->update([
            'estado' => 'procesando',
            'total_filas' => 0,
            'filas_validas' => 0,
            'filas_omitidas' => 0,
            'filas_duplicadas' => 0,
            'errores' => null,
            'procesado_at' => null,
        ]);

        if (
            Str::lower((string) pathinfo($rutaCompleta, PATHINFO_EXTENSION)) === 'xlsx'
            && class_exists(ZipArchive::class)
            && class_exists(XMLReader::class)
        ) {
            return $this->procesarXlsxStreaming($importacion, $rutaCompleta);
        }

        $reader = IOFactory::createReaderForFile($rutaCompleta);
        if (method_exists($reader, 'setReadDataOnly')) {
            $reader->setReadDataOnly(true);
        }
        if (method_exists($reader, 'setReadEmptyCells')) {
            $reader->setReadEmptyCells(false);
        }

        $info = $reader->listWorksheetInfo($rutaCompleta);
        if (empty($info)) {
            throw ValidationException::withMessages([
                'excel' => 'El archivo no contiene hojas legibles.',
            ]);
        }

        $hojaNombre = (string) ($info[0]['worksheetName'] ?? '');
        $ultimaFila = (int) ($info[0]['totalRows'] ?? 0);
        $ultimaColumna = (string) ($info[0]['lastColumnLetter'] ?? 'A');
        $totalColumnas = Coordinate::columnIndexFromString($ultimaColumna);

        if ($ultimaFila < 2) {
            throw ValidationException::withMessages([
                'excel' => 'El archivo no contiene registros para importar.',
            ]);
        }

        $reader->setLoadSheetsOnly([$hojaNombre]);
        $filtro = new MaeChunkReadFilter(1, 1);
        $reader->setReadFilter($filtro);

        $libroEncabezado = $reader->load($rutaCompleta);
        $hojaEncabezado = $libroEncabezado->getActiveSheet();
        $encabezados = $hojaEncabezado
            ->rangeToArray('A1:'.$ultimaColumna.'1', null, true, false)[0] ?? [];
        $encabezados = array_pad($encabezados, $totalColumnas, null);
        $mapa = $this->mapaEncabezados($encabezados);
        $libroEncabezado->disconnectWorksheets();
        unset($hojaEncabezado, $libroEncabezado);

        $requeridos = [
            'rut',
            'nombre',
            'establecimiento',
            'comuna',
            'fecing',
            'fecfiniq',
            'calidadjuridica',
            'regimenjuridico',
        ];
        $faltantes = array_values(array_filter(
            $requeridos,
            static fn (string $campo) => ! array_key_exists($campo, $mapa)
        ));

        if ($faltantes !== []) {
            throw ValidationException::withMessages([
                'excel' => 'Faltan columnas obligatorias: '.implode(', ', $faltantes).'.',
            ]);
        }

        DB::table('certificado_contratos_historicos')
            ->where('importacion_id', $importacion->id)
            ->delete();

        $totalFilas = 0;
        $filasValidas = 0;
        $filasOmitidas = 0;
        $filasDuplicadas = 0;
        $errores = [];
        $maxErrores = max(1, (int) config('certificados.importacion.max_errores_guardados', 200));
        $chunkSize = max(100, (int) config('certificados.importacion.chunk_size', 750));

        for ($inicio = 2; $inicio <= $ultimaFila; $inicio += $chunkSize) {
            $filtro->setRows($inicio, $chunkSize);
            $libro = $reader->load($rutaCompleta);
            $hoja = $libro->getActiveSheet();
            $fin = min($ultimaFila, $inicio + $chunkSize - 1);
            $buffer = [];

            for ($fila = $inicio; $fila <= $fin; $fila++) {
                $valores = $hoja
                    ->rangeToArray(
                        'A'.$fila.':'.$ultimaColumna.$fila,
                        null,
                        true,
                        false
                    )[0] ?? [];
                $valores = array_pad($valores, $totalColumnas, null);

                if ($this->filaVacia($valores)) {
                    continue;
                }

                $totalFilas++;
                $resultado = $this->transformarFila($valores, $mapa, $fila);

                if (isset($resultado['error'])) {
                    $filasOmitidas++;
                    if (count($errores) < $maxErrores) {
                        $errores[] = [
                            'fila' => $fila,
                            'mensaje' => $resultado['error'],
                        ];
                    }

                    continue;
                }

                $datos = $resultado['data'];
                $datos['importacion_id'] = $importacion->id;
                $buffer[] = $datos;
            }

            if ($buffer !== []) {
                [$insertadas, $duplicadas] = $this->guardarBuffer(
                    $buffer,
                    (int) $importacion->id
                );
                $filasValidas += $insertadas;
                $filasDuplicadas += $duplicadas;
            }

            $libro->disconnectWorksheets();
            unset($hoja, $libro);
        }

        return $this->finalizar(
            $importacion,
            $totalFilas,
            $filasValidas,
            $filasOmitidas,
            $filasDuplicadas,
            $errores
        );
    }

    private function procesarXlsxStreaming(
        CertificadoImportacion $importacion,
        string $rutaCompleta
    ): CertificadoImportacion {
        $zip = new ZipArchive;
        if ($zip->open($rutaCompleta) !== true) {
            throw ValidationException::withMessages([
                'excel' => 'No fue posible abrir la estructura interna del archivo XLSX.',
            ]);
        }

        try {
            $sharedStrings = $this->xlsxSharedStrings($zip);
            $hojas = $this->xlsxSheets($zip);
            $entradaHoja = array_values($hojas)[0] ?? null;
        } finally {
            $zip->close();
        }

        if (! $entradaHoja) {
            throw ValidationException::withMessages([
                'excel' => 'El archivo XLSX no contiene una hoja legible.',
            ]);
        }

        $mapa = null;
        $requeridos = [
            'rut',
            'nombre',
            'establecimiento',
            'comuna',
            'fecing',
            'fecfiniq',
            'calidadjuridica',
            'regimenjuridico',
        ];
        $totalFilas = 0;
        $filasValidas = 0;
        $filasOmitidas = 0;
        $filasDuplicadas = 0;
        $errores = [];
        $buffer = [];
        $maxErrores = max(1, (int) config('certificados.importacion.max_errores_guardados', 200));
        $chunkSize = max(100, (int) config('certificados.importacion.chunk_size', 750));

        foreach (
            $this->xlsxRows($rutaCompleta, $entradaHoja, $sharedStrings) as $numeroFila => $valores
        ) {
            if ($mapa === null) {
                $mapa = $this->mapaEncabezados($valores);
                $faltantes = array_values(array_filter(
                    $requeridos,
                    static fn (string $campo) => ! array_key_exists($campo, $mapa)
                ));
                if ($faltantes !== []) {
                    throw ValidationException::withMessages([
                        'excel' => 'Faltan columnas obligatorias: '
                            .implode(', ', $faltantes).'.',
                    ]);
                }

                continue;
            }

            if ($this->filaVacia($valores)) {
                continue;
            }

            $totalFilas++;
            $resultado = $this->transformarFila($valores, $mapa, $numeroFila);
            if (isset($resultado['error'])) {
                $filasOmitidas++;
                if (count($errores) < $maxErrores) {
                    $errores[] = [
                        'fila' => $numeroFila,
                        'mensaje' => $resultado['error'],
                    ];
                }

                continue;
            }

            $datos = $resultado['data'];
            $datos['importacion_id'] = $importacion->id;
            $buffer[] = $datos;

            if (count($buffer) >= $chunkSize) {
                [$insertadas, $duplicadas] = $this->guardarBuffer(
                    $buffer,
                    (int) $importacion->id
                );
                $filasValidas += $insertadas;
                $filasDuplicadas += $duplicadas;
                $buffer = [];
            }
        }

        if ($mapa === null) {
            throw ValidationException::withMessages([
                'excel' => 'El archivo XLSX no contiene una fila de encabezados.',
            ]);
        }

        if ($buffer !== []) {
            [$insertadas, $duplicadas] = $this->guardarBuffer(
                $buffer,
                (int) $importacion->id
            );
            $filasValidas += $insertadas;
            $filasDuplicadas += $duplicadas;
        }

        return $this->finalizar(
            $importacion,
            $totalFilas,
            $filasValidas,
            $filasOmitidas,
            $filasDuplicadas,
            $errores
        );
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function guardarBuffer(array $buffer, int $importacionId): array
    {
        return DB::transaction(function () use ($buffer, $importacionId) {
            $unicas = [];
            $duplicadas = 0;
            foreach ($buffer as $datos) {
                if (isset($unicas[$datos['row_hash']])) {
                    $duplicadas++;

                    continue;
                }
                $unicas[$datos['row_hash']] = $datos;
            }

            $hashesExistentes = DB::table('certificado_contratos_historicos')
                ->where('importacion_id', $importacionId)
                ->whereIn('row_hash', array_keys($unicas))
                ->pluck('row_hash')
                ->flip();
            $nuevas = array_values(array_filter(
                $unicas,
                fn (array $datos) => ! $hashesExistentes->has($datos['row_hash'])
            ));
            $duplicadas += count($unicas) - count($nuevas);

            if ($nuevas !== []) {
                DB::table('certificado_contratos_historicos')->insert($nuevas);
            }

            return [count($nuevas), $duplicadas];
        });
    }

    private function finalizar(
        CertificadoImportacion $importacion,
        int $totalFilas,
        int $filasValidas,
        int $filasOmitidas,
        int $filasDuplicadas,
        array $errores
    ): CertificadoImportacion {
        if ($filasValidas === 0) {
            throw ValidationException::withMessages([
                'excel' => 'No fue posible importar contratos válidos desde el archivo.',
            ]);
        }

        $estado = ($filasOmitidas > 0 || $filasDuplicadas > 0)
            ? 'procesado_con_observaciones'
            : 'procesado';

        $importacion->update([
            'estado' => $estado,
            'total_filas' => $totalFilas,
            'filas_validas' => $filasValidas,
            'filas_omitidas' => $filasOmitidas,
            'filas_duplicadas' => $filasDuplicadas,
            'errores' => $errores !== [] ? $errores : null,
            'procesado_at' => now(),
        ]);

        return $importacion->fresh();
    }

    /**
     * @return array<string, int>
     */
    private function mapaEncabezados(array $encabezados): array
    {
        $mapa = [];
        foreach ($encabezados as $indice => $encabezado) {
            $normalizado = $this->normalizarClave((string) $encabezado);
            if ($normalizado !== '') {
                $mapa[$normalizado] = $indice;
            }
        }

        return $mapa;
    }

    private function transformarFila(array $fila, array $mapa, int $numeroFila): array
    {
        $rutCrudo = $this->valor($fila, $mapa, 'rut');
        $rut = RutChile::normalize((string) $rutCrudo);
        if (! $rut || ($rut['status'] ?? null) !== 'ok') {
            return ['error' => 'RUT inválido o con dígito verificador incorrecto.'];
        }

        $nombre = $this->texto($this->valor($fila, $mapa, 'nombre'));
        $establecimiento = $this->texto($this->valor($fila, $mapa, 'establecimiento'));
        $comuna = $this->texto($this->valor($fila, $mapa, 'comuna'));
        $calidad = $this->texto($this->valor($fila, $mapa, 'calidadjuridica'));
        $regimen = $this->texto($this->valor($fila, $mapa, 'regimenjuridico'));

        foreach ([
            'nombre' => $nombre,
            'establecimiento' => $establecimiento,
            'comuna' => $comuna,
            'calidad jurídica' => $calidad,
            'régimen jurídico' => $regimen,
        ] as $campo => $valor) {
            if ($valor === '') {
                return ['error' => "El campo {$campo} está vacío."];
            }
        }

        $fechaIngreso = $this->fecha($this->valor($fila, $mapa, 'fecing'));
        if (! $fechaIngreso) {
            return ['error' => 'La fecha de ingreso es inválida.'];
        }

        $finiquitoCrudo = $this->valor($fila, $mapa, 'fecfiniq');
        $finiquitoTexto = $this->normalizarClave((string) $finiquitoCrudo);
        $indefinido = str_contains($finiquitoTexto, 'indefinid');
        $fechaFiniquito = $indefinido ? null : $this->fecha($finiquitoCrudo);

        if (! $indefinido && ! $fechaFiniquito) {
            return ['error' => 'La fecha de finiquito no es válida ni indica indefinido.'];
        }

        if ($fechaFiniquito && $fechaFiniquito->lt($fechaIngreso)) {
            return ['error' => 'La fecha de finiquito es anterior a la fecha de ingreso.'];
        }

        $rutNormalizado = Str::upper(
            preg_replace('/[^0-9Kk]/', '', (string) $rut['rut'])
        );
        $datosHash = [
            $rutNormalizado,
            Str::upper($nombre),
            Str::upper($establecimiento),
            Str::upper($comuna),
            $fechaIngreso->format('Y-m-d'),
            $fechaFiniquito?->format('Y-m-d') ?? 'INDEFINIDO',
            Str::upper($calidad),
            Str::upper($regimen),
        ];
        $ahora = now();

        return [
            'data' => [
                'fila_origen' => $numeroFila,
                'rut_normalizado' => $rutNormalizado,
                'nombre' => $nombre,
                'establecimiento' => $establecimiento,
                'comuna' => $comuna,
                'fecha_ingreso' => $fechaIngreso->format('Y-m-d'),
                'fecha_finiquito' => $fechaFiniquito?->format('Y-m-d'),
                'termino_indefinido' => $indefinido,
                'calidad_juridica' => $calidad,
                'regimen_juridico' => $regimen,
                'row_hash' => hash('sha256', implode('|', $datosHash)),
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ],
        ];
    }

    private function valor(array $fila, array $mapa, string $campo): mixed
    {
        return $fila[$mapa[$campo]] ?? null;
    }

    private function filaVacia(array $fila): bool
    {
        foreach ($fila as $valor) {
            if (trim((string) ($valor ?? '')) !== '') {
                return false;
            }
        }

        return true;
    }

    private function texto(mixed $valor): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) ($valor ?? '')));
    }

    private function normalizarClave(string $valor): string
    {
        return Str::of($valor)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/', '')
            ->toString();
    }

    /**
     * @return array<int, string>
     */
    private function xlsxSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false || trim($xml) === '') {
            return [];
        }

        $reader = new XMLReader;
        $reader->XML($xml, null, LIBXML_NONET | LIBXML_COMPACT);
        $strings = [];
        $actual = '';
        $enString = false;

        while ($reader->read()) {
            if (
                $reader->nodeType === XMLReader::ELEMENT
                && $reader->localName === 'si'
            ) {
                $enString = true;
                $actual = '';
            } elseif (
                $enString
                && $reader->nodeType === XMLReader::ELEMENT
                && $reader->localName === 't'
            ) {
                $actual .= $reader->readString();
            } elseif (
                $reader->nodeType === XMLReader::END_ELEMENT
                && $reader->localName === 'si'
            ) {
                $strings[] = $actual;
                $enString = false;
            }
        }
        $reader->close();

        return $strings;
    }

    /**
     * @return array<string, string>
     */
    private function xlsxSheets(ZipArchive $zip): array
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        $relacionesXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbook === false || $relacionesXml === false) {
            return [];
        }

        $relaciones = [];
        $reader = new XMLReader;
        $reader->XML($relacionesXml, null, LIBXML_NONET | LIBXML_COMPACT);
        while ($reader->read()) {
            if (
                $reader->nodeType !== XMLReader::ELEMENT
                || $reader->localName !== 'Relationship'
            ) {
                continue;
            }

            $id = $reader->getAttribute('Id');
            $target = ltrim((string) $reader->getAttribute('Target'), '/');
            if ($id && $target !== '') {
                $relaciones[$id] = str_starts_with($target, 'xl/')
                    ? $target
                    : 'xl/'.$target;
            }
        }
        $reader->close();

        $hojas = [];
        $reader = new XMLReader;
        $reader->XML($workbook, null, LIBXML_NONET | LIBXML_COMPACT);
        while ($reader->read()) {
            if (
                $reader->nodeType !== XMLReader::ELEMENT
                || $reader->localName !== 'sheet'
            ) {
                continue;
            }

            $nombre = trim((string) $reader->getAttribute('name'));
            $relacionId = $reader->getAttribute('r:id')
                ?: $reader->getAttribute('id');
            if ($nombre !== '' && $relacionId && isset($relaciones[$relacionId])) {
                $hojas[$nombre] = $relaciones[$relacionId];
            }
        }
        $reader->close();

        return $hojas;
    }

    /**
     * @return \Generator<int, array<int, mixed>>
     */
    private function xlsxRows(
        string $rutaCompleta,
        string $entrada,
        array $sharedStrings
    ): \Generator {
        $uri = 'zip://'.str_replace('\\', '/', $rutaCompleta).'#'.$entrada;
        $reader = new XMLReader;
        if (! $reader->open($uri, null, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new \RuntimeException('No fue posible leer la hoja principal del XLSX.');
        }

        while ($reader->read()) {
            if (
                $reader->nodeType !== XMLReader::ELEMENT
                || $reader->localName !== 'row'
            ) {
                continue;
            }

            $numeroFila = (int) ($reader->getAttribute('r') ?: 0);
            $valores = [];
            $profundidadFila = $reader->depth;

            while ($reader->read()) {
                if (
                    $reader->nodeType === XMLReader::END_ELEMENT
                    && $reader->localName === 'row'
                    && $reader->depth === $profundidadFila
                ) {
                    break;
                }

                if (
                    $reader->nodeType !== XMLReader::ELEMENT
                    || $reader->localName !== 'c'
                ) {
                    continue;
                }

                $referencia = (string) $reader->getAttribute('r');
                $tipo = (string) $reader->getAttribute('t');
                $indice = $this->columnIndexFromCellRef($referencia)
                    ?? count($valores);
                $valores[$indice] = $this->xlsxCellValue(
                    $reader,
                    $tipo,
                    $sharedStrings
                );
            }

            yield ($numeroFila > 0 ? $numeroFila : 1) => $valores;
        }

        $reader->close();
    }

    private function xlsxCellValue(
        XMLReader $reader,
        string $tipo,
        array $sharedStrings
    ): ?string {
        $valor = null;
        $texto = '';
        $profundidad = $reader->depth;

        while ($reader->read()) {
            if (
                $reader->nodeType === XMLReader::END_ELEMENT
                && $reader->localName === 'c'
                && $reader->depth === $profundidad
            ) {
                break;
            }

            if (
                $reader->nodeType === XMLReader::ELEMENT
                && $reader->localName === 'v'
            ) {
                $valor = $reader->readString();
            } elseif (
                $reader->nodeType === XMLReader::ELEMENT
                && $reader->localName === 't'
            ) {
                $texto .= $reader->readString();
            }
        }

        if ($tipo === 's') {
            $indice = is_numeric($valor) ? (int) $valor : null;

            return $indice !== null ? ($sharedStrings[$indice] ?? null) : null;
        }

        if ($tipo === 'inlineStr') {
            return $texto !== '' ? $texto : null;
        }

        if ($tipo === 'b') {
            return $valor === '1' ? 'SI' : 'NO';
        }

        return $valor !== null ? (string) $valor : ($texto !== '' ? $texto : null);
    }

    private function columnIndexFromCellRef(string $referencia): ?int
    {
        if (! preg_match('/^([A-Z]+)/i', $referencia, $coincidencias)) {
            return null;
        }

        $letras = strtoupper($coincidencias[1]);
        $numero = 0;
        for ($indice = 0; $indice < strlen($letras); $indice++) {
            $numero = ($numero * 26) + (ord($letras[$indice]) - 64);
        }

        return $numero - 1;
    }

    private function fecha(mixed $valor): ?CarbonImmutable
    {
        if ($valor instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($valor)->startOfDay();
        }

        if (is_numeric($valor)) {
            try {
                return CarbonImmutable::instance(
                    ExcelDate::excelToDateTimeObject((float) $valor)
                )->startOfDay();
            } catch (\Throwable) {
                return null;
            }
        }

        $texto = trim((string) ($valor ?? ''));
        if ($texto === '') {
            return null;
        }

        foreach (['d-m-Y', 'd/m/Y', 'Y-m-d', 'Y/m/d', 'd.m.Y'] as $formato) {
            try {
                $fecha = CarbonImmutable::createFromFormat('!'.$formato, $texto);
                if ($fecha !== false) {
                    return $fecha->startOfDay();
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }
}
