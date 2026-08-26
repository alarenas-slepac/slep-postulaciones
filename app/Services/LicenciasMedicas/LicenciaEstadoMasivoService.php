<?php

namespace App\Services\LicenciasMedicas;

use App\Models\LicenciaMedica;
use App\Models\LicenciaMedicaHistorial;
use App\Models\LicenciaMedicaImportacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class LicenciaEstadoMasivoService
{
    public const TIPO_IMPORTACION = 'actualizacion_estados';

    public const ORIGEN_CAMBIO = 'actualizacion_masiva';

    public const ORIGEN_REVERSION = 'reversion_importacion';

    private const MAX_FILAS = 20000;

    private const MAX_MUESTRAS = 100;

    public function __construct(private readonly LicenciaEstadoService $estados)
    {
    }

    public function prevalidar(LicenciaMedicaImportacion $importacion, string $dimension): LicenciaMedicaImportacion
    {
        $this->validarImportacion($importacion);
        $this->validarDimension($dimension);

        try {
            $resultado = $this->analizar($importacion, $dimension);
            $resumen = $resultado['resumen'];

            $importacion->update([
                'dimension_estado' => $dimension,
                'total_filas' => $resumen['filas'],
                'total_importadas' => 0,
                'total_actualizadas' => 0,
                'total_omitidas' => $resumen['inconsistencias'],
                'total_duplicadas' => $resumen['sin_cambios'] + $resumen['duplicadas_archivo'],
                'total_inconsistencias' => $resumen['inconsistencias'],
                'resumen_json' => $resumen,
                'huella_prevalidacion' => $resultado['huella'],
                'estado' => 'prevalidado',
                'prevalidado_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $importacion->update([
                'estado' => 'fallido',
                'resumen_json' => ['error' => 'No fue posible prevalidar el archivo.'],
            ]);

            throw $e;
        }

        return $importacion->fresh();
    }

    public function confirmar(LicenciaMedicaImportacion $importacion, int $userId, string $observacion): LicenciaMedicaImportacion
    {
        $this->validarImportacion($importacion);
        if ($importacion->estado !== 'prevalidado') {
            throw ValidationException::withMessages(['archivo_estados' => 'La carga no está disponible para confirmación.']);
        }

        $observacion = trim($observacion);
        if (mb_strlen($observacion) < 5) {
            throw ValidationException::withMessages(['observacion' => 'Debe indicar un fundamento de al menos 5 caracteres.']);
        }

        $resultado = $this->analizar($importacion, (string) $importacion->dimension_estado);
        if (! hash_equals((string) $importacion->huella_prevalidacion, $resultado['huella'])) {
            throw ValidationException::withMessages([
                'archivo_estados' => 'Los estados cambiaron después de la prevalidación. Cargue nuevamente el archivo para revisar los conflictos.',
            ]);
        }

        if ($resultado['candidatos'] === []) {
            throw ValidationException::withMessages(['archivo_estados' => 'La carga no contiene cambios aplicables.']);
        }

        DB::transaction(function () use ($importacion, $userId, $observacion, $resultado) {
            $carga = LicenciaMedicaImportacion::query()->lockForUpdate()->findOrFail($importacion->id);
            if ($carga->estado !== 'prevalidado') {
                throw ValidationException::withMessages(['archivo_estados' => 'La carga ya fue procesada o cambió de estado.']);
            }

            foreach ($resultado['candidatos'] as $candidato) {
                $licencia = LicenciaMedica::query()->lockForUpdate()->findOrFail($candidato['licencia_id']);
                $columna = $this->estados->columna($candidato['dimension']);

                if ($licencia->{$columna} !== $candidato['estado_anterior']) {
                    throw ValidationException::withMessages([
                        'archivo_estados' => "La licencia de la fila {$candidato['fila']} cambió durante la confirmación. No se aplicó ningún cambio.",
                    ]);
                }

                $fundamento = mb_strlen((string) $candidato['observacion']) >= 5
                    ? mb_substr((string) $candidato['observacion'], 0, 1000)
                    : mb_substr($observacion, 0, 1000);

                $this->estados->cambiar(
                    $licencia,
                    $candidato['dimension'],
                    $candidato['estado_nuevo'],
                    $userId,
                    $fundamento,
                    self::ORIGEN_CAMBIO,
                    $carga->id
                );
            }

            $resumen = $resultado['resumen'];
            $resumen['confirmacion'] = [
                'actualizadas' => count($resultado['candidatos']),
                'confirmado_at' => now()->toIso8601String(),
            ];

            $carga->update([
                'total_actualizadas' => count($resultado['candidatos']),
                'resumen_json' => $resumen,
                'estado' => 'procesado',
                'confirmado_at' => now(),
            ]);
        });

        return $importacion->fresh();
    }

    public function revertir(LicenciaMedicaImportacion $importacion, int $userId, string $observacion): LicenciaMedicaImportacion
    {
        $this->validarImportacion($importacion);
        if ($importacion->estado !== 'procesado') {
            throw ValidationException::withMessages(['reversion' => 'La carga no está disponible para reversa.']);
        }

        $observacion = trim($observacion);
        if (mb_strlen($observacion) < 5) {
            throw ValidationException::withMessages(['observacion_reversion' => 'Debe indicar el motivo de la reversa.']);
        }

        DB::transaction(function () use ($importacion, $userId, $observacion) {
            $carga = LicenciaMedicaImportacion::query()->lockForUpdate()->findOrFail($importacion->id);
            if ($carga->estado !== 'procesado') {
                throw ValidationException::withMessages(['reversion' => 'La carga ya fue revertida o cambió de estado.']);
            }

            $cambios = LicenciaMedicaHistorial::query()
                ->where('importacion_id', $carga->id)
                ->where('accion', 'cambio_estado')
                ->where('origen', self::ORIGEN_CAMBIO)
                ->orderByDesc('id')
                ->get();

            if ($cambios->isEmpty()) {
                throw ValidationException::withMessages(['reversion' => 'La carga no tiene cambios de estado reversibles.']);
            }

            foreach ($cambios as $historial) {
                $existeCambioPosterior = LicenciaMedicaHistorial::query()
                    ->where('licencia_medica_id', $historial->licencia_medica_id)
                    ->where('estado_dimension', $historial->estado_dimension)
                    ->where('id', '>', $historial->id)
                    ->exists();

                if ($existeCambioPosterior) {
                    throw ValidationException::withMessages([
                        'reversion' => 'No se puede revertir la carga porque una de las licencias tuvo cambios posteriores.',
                    ]);
                }

                $licencia = LicenciaMedica::query()->lockForUpdate()->findOrFail($historial->licencia_medica_id);
                $columna = $this->estados->columna((string) $historial->estado_dimension);
                if ($licencia->{$columna} !== $historial->estado_nuevo) {
                    throw ValidationException::withMessages([
                        'reversion' => 'No se puede revertir la carga porque el estado actual ya no coincide con el aplicado.',
                    ]);
                }
            }

            foreach ($cambios as $historial) {
                $licencia = LicenciaMedica::query()->lockForUpdate()->findOrFail($historial->licencia_medica_id);
                $dimension = (string) $historial->estado_dimension;
                $columna = $this->estados->columna($dimension);
                $estadoActual = $licencia->{$columna};

                $licencia->update($this->estados->atributosParaCambio(
                    $dimension,
                    $historial->estado_anterior,
                    $userId
                ));

                LicenciaMedicaHistorial::create([
                    'licencia_medica_id' => $licencia->id,
                    'accion' => 'reversion_estado_masiva',
                    'descripcion' => mb_substr($observacion, 0, 1000),
                    'datos_anteriores' => [$columna => $estadoActual],
                    'datos_nuevos' => [$columna => $historial->estado_anterior],
                    'estado_dimension' => $dimension,
                    'estado_anterior' => $estadoActual,
                    'estado_nuevo' => $historial->estado_anterior,
                    'origen' => self::ORIGEN_REVERSION,
                    'importacion_id' => $carga->id,
                    'user_id' => $userId,
                    'created_at' => now(),
                ]);
            }

            $resumen = (array) $carga->resumen_json;
            $resumen['reversion'] = [
                'revertidas' => $cambios->count(),
                'revertido_at' => now()->toIso8601String(),
            ];

            $carga->update([
                'estado' => 'revertido',
                'resumen_json' => $resumen,
                'revertido_at' => now(),
                'revertido_por' => $userId,
            ]);
        });

        return $importacion->fresh();
    }

    private function analizar(LicenciaMedicaImportacion $importacion, string $dimension): array
    {
        $this->validarDimension($dimension);
        if (! $importacion->archivo_path || ! Storage::disk('local')->exists($importacion->archivo_path)) {
            throw ValidationException::withMessages(['archivo_estados' => 'No se encontró el archivo original de la carga.']);
        }

        $path = Storage::disk('local')->path($importacion->archivo_path);
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);

        try {
            $sheet = $spreadsheet->getActiveSheet();
            $highestRow = (int) $sheet->getHighestDataRow();
            [$headerRow, $map] = $this->detectarCabecera($sheet);
            if (($highestRow - $headerRow) > self::MAX_FILAS) {
                throw ValidationException::withMessages([
                    'archivo_estados' => 'La planilla supera el máximo de '.number_format(self::MAX_FILAS, 0, ',', '.').' filas por carga.',
                ]);
            }
            $entradas = [];
            $muestras = [];
            $inconsistencias = 0;
            $filas = 0;

            for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
                $folioRaw = $this->valorCelda($sheet, $row, $map['folio']);
                $rutRaw = $this->valorCelda($sheet, $row, $map['rut']);
                $estadoRaw = $this->valorCelda($sheet, $row, $map['estado']);
                $tipoRaw = $this->valorCelda($sheet, $row, $map['tipo']);
                $cuerpoRaw = $this->valorCelda($sheet, $row, $map['cuerpo']);
                $dvRaw = $this->valorCelda($sheet, $row, $map['dv']);

                if ($this->filaVacia([$folioRaw, $rutRaw, $estadoRaw, $tipoRaw, $cuerpoRaw, $dvRaw])) {
                    continue;
                }
                $filas++;

                $folio = $this->normalizarFolio($folioRaw, $tipoRaw, $cuerpoRaw, $dvRaw);
                if (! $folio) {
                    $inconsistencias++;
                    $this->agregarMuestra($muestras, $row, 'Folio de licencia inválido o incompleto.');
                    continue;
                }

                $rut = RutNormalizer::normalize((string) $rutRaw);
                if (! $rut['valido']) {
                    $inconsistencias++;
                    $this->agregarMuestra($muestras, $row, 'RUT inválido o con DV incorrecto.');
                    continue;
                }

                $estado = $this->estados->normalizarEstricto($dimension, (string) $estadoRaw);
                if (! $estado) {
                    $inconsistencias++;
                    $this->agregarMuestra($muestras, $row, 'Estado no reconocido para la dimensión seleccionada.');
                    continue;
                }

                $entradas[] = [
                    'fila' => $row,
                    'folio' => $folio,
                    'rut_normalizado' => $rut['normalizado'],
                    'estado_nuevo' => $estado,
                    'observacion' => trim((string) $this->valorCelda($sheet, $row, $map['observacion'])),
                ];
            }

            $licencias = collect($entradas)
                ->pluck('folio')
                ->unique()
                ->chunk(500)
                ->flatMap(fn ($folios) => LicenciaMedica::query()->whereIn('folio_licencia', $folios)->get())
                ->keyBy('folio_licencia');

            $porLicencia = [];
            foreach ($entradas as $entrada) {
                $licencia = $licencias->get($entrada['folio']);
                if (! $licencia) {
                    $inconsistencias++;
                    $this->agregarMuestra($muestras, $entrada['fila'], 'No existe una licencia registrada con ese folio.');
                    continue;
                }
                if ($licencia->rut_normalizado !== $entrada['rut_normalizado']) {
                    $inconsistencias++;
                    $this->agregarMuestra($muestras, $entrada['fila'], 'El RUT no coincide con el titular de la licencia.');
                    continue;
                }

                $entrada['licencia_id'] = $licencia->id;
                $porLicencia[$licencia->id][] = $entrada;
            }

            $candidatos = [];
            $sinCambios = 0;
            $duplicadasArchivo = 0;
            $columna = $this->estados->columna($dimension);

            foreach ($porLicencia as $grupo) {
                $estadosNuevos = collect($grupo)->pluck('estado_nuevo')->unique()->values();
                if ($estadosNuevos->count() > 1) {
                    $inconsistencias += count($grupo);
                    foreach ($grupo as $entrada) {
                        $this->agregarMuestra($muestras, $entrada['fila'], 'El archivo contiene estados contradictorios para la misma licencia.');
                    }
                    continue;
                }

                $entrada = $grupo[0];
                $duplicadasArchivo += max(0, count($grupo) - 1);
                $licencia = $licencias->get($entrada['folio']);
                if ($licencia->{$columna} === $entrada['estado_nuevo']) {
                    $sinCambios++;
                    continue;
                }

                $candidatos[] = [
                    'fila' => $entrada['fila'],
                    'licencia_id' => $licencia->id,
                    'dimension' => $dimension,
                    'estado_anterior' => $licencia->{$columna},
                    'estado_nuevo' => $entrada['estado_nuevo'],
                    'observacion' => $entrada['observacion'],
                ];
            }

            usort($candidatos, fn (array $a, array $b) => [$a['licencia_id'], $a['fila']] <=> [$b['licencia_id'], $b['fila']]);
            $huella = hash('sha256', json_encode(array_map(fn (array $item) => [
                $item['licencia_id'],
                $item['fila'],
                $item['dimension'],
                $item['estado_anterior'],
                $item['estado_nuevo'],
                $item['observacion'],
            ], $candidatos), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            return [
                'candidatos' => $candidatos,
                'huella' => $huella,
                'resumen' => [
                    'dimension' => $dimension,
                    'fila_cabecera' => $headerRow,
                    'filas' => $filas,
                    'actualizables' => count($candidatos),
                    'sin_cambios' => $sinCambios,
                    'duplicadas_archivo' => $duplicadasArchivo,
                    'inconsistencias' => $inconsistencias,
                    'muestras_inconsistencias' => $muestras,
                ],
            ];
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    private function detectarCabecera($sheet): array
    {
        $highestColumn = $sheet->getHighestDataColumn();
        $max = min((int) $sheet->getHighestDataRow(), 10);

        for ($row = 1; $row <= $max; $row++) {
            $headers = $sheet->rangeToArray("A{$row}:{$highestColumn}{$row}", null, true, false)[0] ?? [];
            $map = $this->mapearCabeceras($headers);
            $tieneFolio = $map['folio'] !== null || ($map['tipo'] !== null && $map['cuerpo'] !== null && $map['dv'] !== null);
            if ($tieneFolio && $map['rut'] !== null && $map['estado'] !== null) {
                return [$row, $map];
            }
        }

        throw ValidationException::withMessages([
            'archivo_estados' => 'No se encontró una cabecera válida. Use FOLIO_LICENCIA, RUT, ESTADO y opcionalmente OBSERVACION.',
        ]);
    }

    private function mapearCabeceras(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $index => $header) {
            $normalized[$this->normalizarTexto((string) $header)] = $index;
        }

        return [
            'folio' => $this->buscarColumna($normalized, ['FOLIOLICENCIA', 'FOLIO', 'NLICENCIA', 'NDELICENCIA']),
            'rut' => $this->buscarColumna($normalized, ['RUT', 'RUN', 'RUTFUNCIONARIO']),
            'estado' => $this->buscarColumna($normalized, ['ESTADO', 'ESTADOCOMPIN', 'RESOLUCIONCOMPIN', 'PRIMERESTADO']),
            'observacion' => $this->buscarColumna($normalized, ['OBSERVACION', 'OBSERVACIONES', 'FUNDAMENTO']),
            'tipo' => $this->buscarColumna($normalized, ['TIPOINGRESO', 'TIPOINGRESOLICENCIA']),
            'cuerpo' => $this->buscarColumna($normalized, ['CUERPOLICENCIA', 'CUERPO']),
            'dv' => $this->buscarColumna($normalized, ['DVLICENCIA', 'DV']),
        ];
    }

    private function normalizarFolio($folioRaw, $tipoRaw, $cuerpoRaw, $dvRaw): ?string
    {
        $partes = LicenciaFolio::split((string) $folioRaw);
        if ($partes['folio_licencia']) {
            return $partes['folio_licencia'];
        }

        return LicenciaFolio::build($tipoRaw, $cuerpoRaw, $dvRaw);
    }

    private function valorCelda($sheet, int $row, ?int $column): mixed
    {
        return $column === null
            ? null
            : $sheet->getCell(Coordinate::stringFromColumnIndex($column + 1).$row)->getCalculatedValue();
    }

    private function buscarColumna(array $headers, array $candidates): ?int
    {
        foreach ($candidates as $candidate) {
            if (array_key_exists($candidate, $headers)) {
                return $headers[$candidate];
            }
        }

        return null;
    }

    private function normalizarTexto(string $value): string
    {
        return preg_replace('/[^A-Z0-9]/', '', (string) Str::of($value)->ascii()->upper()) ?: '';
    }

    private function filaVacia(array $values): bool
    {
        return collect($values)->every(fn ($value) => trim((string) $value) === '');
    }

    private function agregarMuestra(array &$muestras, int $fila, string $motivo): void
    {
        if (count($muestras) >= self::MAX_MUESTRAS) {
            return;
        }

        $muestras[] = ['fila' => $fila, 'motivo' => $motivo];
    }

    private function validarImportacion(LicenciaMedicaImportacion $importacion): void
    {
        if ($importacion->tipo !== self::TIPO_IMPORTACION) {
            throw ValidationException::withMessages(['archivo_estados' => 'La carga no corresponde a una actualización masiva de estados.']);
        }
    }

    private function validarDimension(string $dimension): void
    {
        if (! in_array($dimension, (array) config('licencias_medicas.dimensiones_masivas_habilitadas', []), true)) {
            throw ValidationException::withMessages(['dimension' => 'La dimensión seleccionada no está habilitada para actualización masiva.']);
        }
    }
}
