<?php

namespace App\Services\Remuneraciones;

use App\Models\UtmValor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;

class UtmImportService
{
    public function importar(UploadedFile $archivo, int $usuarioId): int
    {
        $hoja = IOFactory::load($archivo->getRealPath())->getActiveSheet();
        $filas = $hoja->toArray(null, true, true, false);
        $encabezados = array_map(fn ($valor) => $this->normalizarEncabezado((string) $valor), array_shift($filas) ?? []);

        $columnas = [
            'anio' => $this->buscarColumna($encabezados, ['ANIO', 'ANO']),
            'mes' => $this->buscarColumna($encabezados, ['MES']),
            'valor' => $this->buscarColumna($encabezados, ['VALOR_UTM', 'UTM', 'VALOR']),
        ];

        if (in_array(null, $columnas, true)) {
            throw ValidationException::withMessages([
                'archivo' => 'La planilla debe incluir las columnas ANIO, MES y VALOR_UTM.',
            ]);
        }

        $registros = [];
        $errores = [];

        foreach ($filas as $offset => $fila) {
            $numeroFila = $offset + 2;
            if (collect($fila)->filter(fn ($valor) => $valor !== null && trim((string) $valor) !== '')->isEmpty()) {
                continue;
            }

            $anio = filter_var($fila[$columnas['anio']] ?? null, FILTER_VALIDATE_INT);
            $mes = $this->normalizarMes($fila[$columnas['mes']] ?? null);
            $valor = $this->normalizarNumero($fila[$columnas['valor']] ?? null);

            if (! $anio || $anio < 2000 || $anio > 2100 || ! $mes || $valor === null || $valor <= 0) {
                $errores[] = "Fila {$numeroFila}: periodo o valor UTM inválido.";

                continue;
            }

            $periodo = sprintf('%04d-%02d', $anio, $mes);
            if (isset($registros[$periodo])) {
                $errores[] = "Fila {$numeroFila}: el periodo {$periodo} está repetido en el archivo.";

                continue;
            }

            $registros[$periodo] = ['anio' => $anio, 'mes' => $mes, 'valor' => round($valor, 2)];
        }

        if ($registros === [] && $errores === []) {
            $errores[] = 'La planilla no contiene valores UTM para importar.';
        }

        foreach ($registros as $periodo => $registro) {
            if (UtmValor::query()->where('anio', $registro['anio'])->where('mes', $registro['mes'])->exists()) {
                $errores[] = "El periodo {$periodo} ya se encuentra registrado.";
            }
        }

        if ($errores !== []) {
            throw ValidationException::withMessages(['archivo' => $errores]);
        }

        DB::transaction(function () use ($registros, $usuarioId) {
            foreach ($registros as $registro) {
                UtmValor::create($registro + [
                    'creado_por_id' => $usuarioId,
                    'actualizado_por_id' => $usuarioId,
                ]);
            }
        });

        return count($registros);
    }

    private function buscarColumna(array $encabezados, array $alternativas): ?int
    {
        foreach ($alternativas as $alternativa) {
            $indice = array_search($alternativa, $encabezados, true);
            if ($indice !== false) {
                return $indice;
            }
        }

        return null;
    }

    private function normalizarEncabezado(string $valor): string
    {
        $valor = mb_strtoupper(trim($valor));
        $valor = strtr($valor, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N']);

        return preg_replace('/[^A-Z0-9]+/', '_', $valor) ?: '';
    }

    private function normalizarMes(mixed $valor): ?int
    {
        if (is_numeric($valor)) {
            $mes = (int) $valor;

            return $mes >= 1 && $mes <= 12 ? $mes : null;
        }

        $texto = $this->normalizarEncabezado((string) $valor);
        $meses = [
            'ENERO' => 1, 'FEBRERO' => 2, 'MARZO' => 3, 'ABRIL' => 4,
            'MAYO' => 5, 'JUNIO' => 6, 'JULIO' => 7, 'AGOSTO' => 8,
            'SEPTIEMBRE' => 9, 'SETIEMBRE' => 9, 'OCTUBRE' => 10,
            'NOVIEMBRE' => 11, 'DICIEMBRE' => 12,
        ];

        return $meses[$texto] ?? null;
    }

    private function normalizarNumero(mixed $valor): ?float
    {
        if (is_int($valor) || is_float($valor)) {
            return (float) $valor;
        }

        $texto = preg_replace('/[^0-9,.\-]/', '', trim((string) $valor));
        if ($texto === null || $texto === '') {
            return null;
        }

        if (str_contains($texto, ',') && str_contains($texto, '.')) {
            $texto = str_replace('.', '', $texto);
        }
        $texto = str_replace(',', '.', $texto);

        return is_numeric($texto) ? (float) $texto : null;
    }
}
