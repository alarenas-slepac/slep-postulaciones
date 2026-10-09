<?php

namespace App\Services\Padron;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PadronDatosActualizacionService
{
    public function periodo(): int
    {
        return app(PadronPeriodoService::class)->periodoMaximo();
    }

    public function actualizar(array $filas, array $campos, int $periodoMostrado, User $usuario): array
    {
        abort_unless($usuario->hasRole('admin') && $usuario->activeRoleName() === 'admin', 403);
        if (! $campos || array_diff($campos, array_keys(PadronDatosExcel::CAMPOS))) {
            $this->fallar('Seleccione únicamente los campos personales disponibles para actualizar.');
        }
        if (! Schema::hasTable('padron_individual_cambios') || ! Schema::hasTable('padron_aplicacion_control')) {
            $this->fallar('Instale las migraciones existentes de auditoría y control del padrón antes de actualizar datos.');
        }
        foreach ($campos as $campo) {
            if (! Schema::hasColumn('reemplazos_personal', $campo)) {
                $this->fallar('Falta la columna '.$campo.' en el padrón. Ejecute las migraciones pendientes.');
            }
        }
        $reportePath = null;
        try {
            return app(PadronEscrituraService::class)->ejecutar(function () use ($filas, $campos, $periodoMostrado, $usuario, &$reportePath) {
                return DB::transaction(function () use ($filas, $campos, $periodoMostrado, $usuario, &$reportePath) {
                    $periodo = $this->periodo();
                    if ($periodo < 200001 || $periodo !== $periodoMostrado) {
                        $this->fallar('El último mes cargado cambió o aún no hay padrón. Vuelva a abrir el formulario antes de actualizar.');
                    }
                    $porRut = $this->consolidar($filas);
                    $omitidos = [];
                    foreach ($filas as $fila) {
                        if ($fila['rut'] === null) {
                            $omitidos[] = [$fila['fila'], $fila['rut_excel'], $fila['motivo']];
                        }
                    }
                    $encontrados = [];
                    $actualizados = 0;
                    $rutsActualizados = [];
                    $sinCambios = 0;
                    $sinCambiosPorRut = [];
                    $porRbd = [];
                    // Una consulta por lote, nunca una búsqueda SQL por cada fila del Excel.
                    $rutSql = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(UPPER(TRIM(rut)), '.', ''), '-', ''), ' ', ''), CHAR(9), ''), CHAR(10), ''), CHAR(13), '')";
                    foreach (array_chunk(array_map('strval', array_keys($porRut)), 500) as $ruts) {
                        DB::table('reemplazos_personal')->where('anio', intdiv($periodo, 100))->where('mes', $periodo % 100)
                            ->whereIn(DB::raw($rutSql), $ruts)->lockForUpdate()
                            ->chunkById(300, function ($rows) use ($porRut, $campos, $usuario, $periodo, &$encontrados, &$actualizados, &$rutsActualizados, &$sinCambios, &$sinCambiosPorRut, &$porRbd) {
                                $propuestas = [];
                                foreach ($rows as $row) {
                                    $rut = \App\Support\Rut::normalize($row->rut);
                                    $fila = $porRut[$rut];
                                    if (! empty($fila['errores'])) { $this->fallar(implode(' ', $fila['errores'])); }
                                    $encontrados[$rut] = true;
                                    $datos = array_intersect_key($fila['datos'], array_flip($campos));
                                    $cambios = array_filter($datos, fn ($valor, $campo) => (string) $row->{$campo} !== (string) $valor, ARRAY_FILTER_USE_BOTH);
                                    if (! $cambios) {
                                        $sinCambios++;
                                        $this->registrarResultado($sinCambiosPorRut, $rut, $row, $fila, array_intersect_key((array) $row, array_flip($campos)));
                                        continue;
                                    }
                                    $propuestas[] = [$row, $cambios, $rut, $fila];
                                }
                                if (! $propuestas) { return; }
                                app(PadronHistorialService::class)->congelarReferencias(array_map(fn ($p) => (int) $p[0]->id, $propuestas));
                                foreach ($propuestas as [$row, $cambios, $rut, $fila]) {
                                    DB::table('reemplazos_personal')->where('id', $row->id)->update($cambios + ['updated_at' => now()]);
                                    $despues = array_replace((array) $row, $cambios, ['updated_at' => now()->format('Y-m-d H:i:s')]);
                                    DB::table('padron_individual_cambios')->insert([
                                        'personal_id' => $row->id, 'usuario_id' => $usuario->id,
                                        'accion' => 'actualizacion_excel', 'justificacion' => 'Actualización de antecedentes personales por Excel.',
                                        'antes' => json_encode($row, JSON_THROW_ON_ERROR),
                                        'despues' => json_encode($despues, JSON_THROW_ON_ERROR),
                                        'controles' => json_encode(['campos' => array_keys($cambios), 'periodo' => $periodo, 'fila_excel' => $fila['fila'], 'filas_excel' => $fila['filas_excel']], JSON_THROW_ON_ERROR),
                                        'created_at' => now(),
                                    ]);
                                    $actualizados++;
                                    $this->registrarResultado($rutsActualizados, $rut, $row, $fila, $cambios);
                                    $rbd = (string) ($row->rbd ?? 'Sin RBD');
                                    $porRbd[$rbd]['ruts'][$rut] = true;
                                    $porRbd[$rbd]['lineas'] = ($porRbd[$rbd]['lineas'] ?? 0) + 1;
                                }
                            });
                    }
                    foreach ($filas as $fila) {
                        if ($fila['rut'] !== null && ! isset($encontrados[$fila['rut']])) {
                            $omitidos[] = [$fila['fila'], $fila['rut_excel'], 'RUT sin registros en el último mes cargado ('.sprintf('%02d/%d', $periodo % 100, intdiv($periodo, 100)).').'];
                        }
                    }
                    $filasOmitidas = count($omitidos);
                    $omitidos = $this->agruparOmitidos($omitidos);
                    // Una persona con alguna línea modificada figura sólo en Modificados.
                    $sinCambiosPorRut = array_diff_key($sinCambiosPorRut, $rutsActualizados);
                    ksort($porRbd, SORT_NATURAL);
                    $resumenRbd = [];
                    foreach ($porRbd as $rbd => $datosRbd) {
                        $resumenRbd[] = [(string) $rbd, count($datosRbd['ruts']), $datosRbd['lineas']];
                    }
                    $reporte = null;
                    if ($omitidos || $rutsActualizados || $sinCambiosPorRut) {
                        $reporte = (string) Str::uuid();
                        $reportePath = $this->reportePath((int) $usuario->id, $reporte);
                        $json = json_encode(['version' => 2, 'usuario_id' => $usuario->id, 'periodo' => $periodo,
                            'campos' => $campos, 'modificados' => array_values($rutsActualizados),
                            'sin_cambios' => array_values($sinCambiosPorRut), 'omitidos' => $omitidos,
                            'por_rbd' => $resumenRbd], JSON_THROW_ON_ERROR);
                        // El informe debe quedar disponible antes de confirmar los cambios.
                        if (! Storage::disk('local')->put($reportePath, $json) || ! Storage::disk('local')->exists($reportePath)) {
                            $this->fallar('No fue posible guardar el informe de resultados. No se aplicaron cambios.');
                        }
                    }
                    return ['periodo' => $periodo, 'filas' => count($filas), 'ruts_actualizados' => count($rutsActualizados),
                        'registros_actualizados' => $actualizados, 'sin_cambios' => $sinCambios,
                        'ruts_sin_cambios' => count($sinCambiosPorRut), 'ruts_omitidos' => count($omitidos),
                        'omitidos' => $filasOmitidas, 'reporte' => $reporte, 'campos' => $campos];
                });
            });
        } catch (\Throwable $error) {
            if ($reportePath !== null) {
                try {
                    if (Storage::disk('local')->exists($reportePath) && ! Storage::disk('local')->delete($reportePath)) {
                        Log::warning('No se pudo limpiar un informe de actualización del padrón tras un fallo.');
                    }
                } catch (\Throwable) {
                    Log::warning('No se pudo limpiar un informe de actualización del padrón tras un fallo.');
                }
            }
            throw $error;
        }
    }

    public function reportePath(int $usuarioId, string $reporte): string
    {
        return 'padron-actualizaciones/'.$usuarioId.'/'.$reporte.'.json';
    }

    private function consolidar(array $filas): array
    {
        $porRut = [];
        foreach ($filas as $fila) {
            if ($fila['rut'] === null) { continue; }
            $rut = $fila['rut'];
            if (! isset($porRut[$rut])) {
                $porRut[$rut] = $fila + ['filas_excel' => [$fila['fila']], 'errores' => []];
                continue;
            }
            $grupo = &$porRut[$rut];
            $grupo['filas_excel'][] = $fila['fila'];
            $grupo['errores'] = array_merge($grupo['errores'], $fila['errores'] ?? []);
            foreach ($fila['datos'] as $campo => $valor) {
                if (! array_key_exists($campo, $grupo['datos'])) {
                    $grupo['datos'][$campo] = $valor;
                } elseif (in_array($campo, ['fecha_nacimiento', 'fecha_antiguedad'], true)) {
                    // Las fechas ya fueron validadas y normalizadas como YYYY-MM-DD.
                    if ($valor < $grupo['datos'][$campo]) { $grupo['datos'][$campo] = $valor; }
                } elseif ((string) $valor !== (string) $grupo['datos'][$campo]) {
                    $grupo['errores'][] = 'Filas '.implode(', ', $grupo['filas_excel']).': el RUT repetido tiene valores distintos para '.$campo.'. Corrija el Excel antes de actualizar.';
                }
            }
            unset($grupo);
        }
        return $porRut;
    }

    private function registrarResultado(array &$resultados, string $rut, object $row, array $fila, array $datos): void
    {
        $resultados[$rut] ??= ['rut' => substr($rut, 0, -1).'-'.substr($rut, -1),
            'filas_excel' => $fila['filas_excel'], 'rbds' => [], 'lineas' => 0, 'anteriores' => [], 'nuevos' => []];
        $resultado = &$resultados[$rut];
        $rbd = (string) ($row->rbd ?? 'Sin RBD');
        if (! in_array($rbd, $resultado['rbds'], true)) { $resultado['rbds'][] = $rbd; }
        $resultado['lineas']++;
        foreach ($datos as $campo => $valor) {
            $anterior = $row->{$campo} === null || $row->{$campo} === '' ? '(vacío)' : (string) $row->{$campo};
            if (! in_array($anterior, $resultado['anteriores'][$campo] ?? [], true)) {
                $resultado['anteriores'][$campo][] = $anterior;
            }
            $resultado['nuevos'][$campo] = $valor;
        }
    }

    private function agruparOmitidos(array $omitidos): array
    {
        usort($omitidos, fn ($a, $b) => $a[0] <=> $b[0]);
        $grupos = [];
        foreach ($omitidos as [$fila, $rut, $motivo]) {
            $key = preg_replace('/[.\-\s]+/', '', mb_strtoupper($rut));
            $grupos[$key] ??= ['filas' => [], 'rut' => $rut, 'motivo' => $motivo];
            $grupos[$key]['filas'][] = $fila;
        }
        return array_values(array_map(fn ($grupo) => [count($grupo['filas']) === 1 ? $grupo['filas'][0] : implode(', ', $grupo['filas']), $grupo['rut'], $grupo['motivo']], $grupos));
    }

    private function fallar(string $mensaje): never
    {
        throw ValidationException::withMessages(['excel_actualizacion' => $mensaje]);
    }
}
