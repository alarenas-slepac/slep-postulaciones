<?php

namespace App\Services\Remuneraciones;

use App\Support\MaeChunkReadFilter;
use App\Support\MaeColumnNormalizer;
use App\Support\RutChile;
use DateTimeImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class SnedMaeReader
{
    private const COLUMNAS = ['RUT', 'NOMBRE', 'FECHA INGRESO', 'FECHA TERMINO', 'JORNADA', 'PLANTA', 'LINCONTRATO C'];

    public function leer(string $ruta, Collection $establecimientos): array
    {
        $reader = new Xlsx;
        $reader->setReadDataOnly(true)->setReadEmptyCells(false);
        $info = collect($reader->listWorksheetInfo($ruta))->firstWhere('worksheetName', 'Hoja1');
        if (! $info || $info['totalRows'] < 2) {
            throw ValidationException::withMessages(['excel' => 'El MAE debe contener contratos y encabezados en Hoja1.']);
        }

        $reader->setLoadSheetsOnly('Hoja1')->setReadFilter(new MaeChunkReadFilter);
        $libro = $reader->load($ruta);
        $hoja = $libro->getSheet(0);
        $encabezados = $hoja->rangeToArray('A1:'.$info['lastColumnLetter'].'1', null, false, false, true)[1];
        $columnas = [];
        foreach ($encabezados as $columna => $valor) {
            $encabezado = MaeColumnNormalizer::normalizeHeader((string) $valor);
            if (in_array($encabezado, [...self::COLUMNAS, 'RBD', 'DEPARTAMENTO'], true)) {
                if (isset($columnas[$encabezado])) {
                    throw ValidationException::withMessages(['excel' => 'El MAE tiene una columna obligatoria repetida.']);
                }
                $columnas[$encabezado] = $columna;
            }
        }
        $libro->disconnectWorksheets();
        unset($hoja, $libro);
        $faltan = array_diff(self::COLUMNAS, array_keys($columnas));
        if (! isset($columnas['RBD']) && ! isset($columnas['DEPARTAMENTO'])) {
            $faltan[] = 'RBD o DEPARTAMENTO';
        }
        if ($faltan) {
            throw ValidationException::withMessages(['excel' => 'Faltan columnas en Hoja1: '.implode(', ', $faltan).'.']);
        }

        $rbdValidos = $establecimientos->keyBy('rbd');
        $nombres = $establecimientos->groupBy(fn ($ee) => MaeColumnNormalizer::normalizeHeader($ee->nombre_establecimiento));
        $contratos = [];
        $fueraDeComuna = $duplicados = 0;
        for ($desde = 2; $desde <= $info['totalRows']; $desde += 1800) {
            $hasta = min($desde + 1799, $info['totalRows']);
            $reader->setReadFilter(new class($desde, $hasta, array_values($columnas)) implements IReadFilter
            {
                public function __construct(private int $desde, private int $hasta, private array $columnas) {}

                public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
                {
                    return $row >= $this->desde && $row <= $this->hasta && in_array($columnAddress, $this->columnas, true);
                }
            });
            $libro = $reader->load($ruta);
            $hoja = $libro->getSheet(0);

            try {
                for ($fila = $desde; $fila <= $hasta; $fila++) {
                    $valores = array_map(fn ($col) => $hoja->getCell($col.$fila)->getValue(), $columnas);
                    if (! filled($valores['RUT']) && ! filled($valores['NOMBRE'])) {
                        continue;
                    }

                    $rbd = $this->rbd($valores, $nombres, $fila);
                    if (! $rbdValidos->has($rbd)) {
                        $fueraDeComuna++;
                        continue;
                    }
                    $contrato = $this->contrato($valores, $fila);
                    $llave = $contrato['rut'].'|'.$contrato['linea'];
                    if (isset($contratos[$rbd][$llave])) {
                        if ($contrato !== array_diff_key($contratos[$rbd][$llave], ['fila' => true])) {
                            $this->errorFila($fila, 'la línea de contrato se repite con datos distintos');
                        }
                        $duplicados++;
                        continue;
                    }
                    $contratos[$rbd][$llave] = $contrato + ['fila' => $fila];
                }
            } finally {
                $libro->disconnectWorksheets();
                unset($libro, $hoja);
            }
        }

        if (! $contratos) {
            throw ValidationException::withMessages(['excel' => 'El MAE no contiene contratos de los RBD de la comuna seleccionada.']);
        }

        return [
            'contratos' => array_map('array_values', $contratos),
            'total' => array_sum(array_map('count', $contratos)),
            'fuera_comuna' => $fueraDeComuna,
            'duplicados' => $duplicados,
        ];
    }

    private function rbd(array $valores, Collection $nombres, int $fila): int
    {
        $departamento = trim((string) ($valores['DEPARTAMENTO'] ?? ''));
        $rbd = trim((string) ($valores['RBD'] ?? ''));
        if ($rbd !== '') {
            if (! preg_match('/^(\d{1,8})(?:-[0-9Kk])?$/', $rbd, $partes)) {
                $this->errorFila($fila, 'el RBD es inválido');
            }

            return (int) $partes[1];
        }
        if (preg_match('/^(\d{1,8})(?:-[0-9Kk])?(?:\s|$)/', $departamento, $partes)) {
            return (int) $partes[1];
        }

        $coincidencias = $nombres->get(MaeColumnNormalizer::normalizeHeader($departamento));
        if ($coincidencias && $coincidencias->count() > 1) {
            $this->errorFila($fila, 'el nombre del establecimiento corresponde a más de un RBD');
        }

        return (int) ($coincidencias?->first()?->rbd ?? 0);
    }

    private function contrato(array $valores, int $fila): array
    {
        $rutOriginal = strtoupper(str_replace(['.', ' '], '', trim((string) $valores['RUT'])));
        $rut = RutChile::normalize($rutOriginal);
        if (! preg_match('/^\d{5,8}-?[0-9K]$/', $rutOriginal) || ($rut['status'] ?? '') !== 'ok') {
            $this->errorFila($fila, 'el RUT o su dígito verificador es inválido');
        }
        $nombre = trim((string) $valores['NOMBRE']);
        $linea = trim((string) $valores['LINCONTRATO C']);
        if ($nombre === '' || ! preg_match('/^\d{1,16}$/', $linea)) {
            $this->errorFila($fila, 'faltan el nombre o la línea de contrato');
        }
        $planta = MaeColumnNormalizer::normalizeHeader((string) $valores['PLANTA']);
        $estamento = match (true) {
            $planta === 'DOCENTE' => 'docentes',
            $planta === 'AAEE', str_starts_with($planta, 'ASIST '), str_starts_with($planta, 'ASISTENTE ') => 'aaee',
            default => $this->errorFila($fila, 'la planta no corresponde a Docente o Asistente de la Educación'),
        };
        $inicio = $this->fecha($valores['FECHA INGRESO'], $fila);
        $termino = $this->fecha($valores['FECHA TERMINO'], $fila);
        if ($inicio === null || ($termino !== null && $termino < $inicio)) {
            $this->errorFila($fila, 'las fechas del contrato son inconsistentes');
        }
        $jornada = str_replace(',', '.', trim((string) $valores['JORNADA']));
        if (! is_numeric($jornada) || (float) $jornada < 0 || (float) $jornada > 168) {
            $this->errorFila($fila, 'la jornada semanal es inválida');
        }

        return [
            'rut' => $rut['rut'],
            'nombre' => $nombre,
            'linea' => ltrim($linea, '0') ?: '0',
            'estamento' => $estamento,
            'inicio' => $inicio,
            'termino' => $termino,
            'minutos' => (int) round((float) $jornada * 60),
        ];
    }

    private function fecha(mixed $valor, int $fila): ?string
    {
        $texto = trim((string) $valor);
        if ($texto === '') {
            return null;
        }
        if (is_numeric($valor) && (float) $valor > 0 && (float) $valor < 100000) {
            return Date::excelToDateTimeObject($valor)->format('Y-m-d');
        }
        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d'] as $formato) {
            $fecha = DateTimeImmutable::createFromFormat('!'.$formato, $texto);
            if ($fecha && $fecha->format($formato) === $texto) {
                return $fecha->format('Y-m-d');
            }
        }

        $this->errorFila($fila, 'la fecha no tiene un formato válido');
    }

    private function errorFila(int $fila, string $razon): never
    {
        throw ValidationException::withMessages(['excel' => "Hoja1, fila {$fila}: {$razon}."]);
    }
}
