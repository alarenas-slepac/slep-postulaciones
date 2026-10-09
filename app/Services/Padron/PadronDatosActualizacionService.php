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
                    $porRut = [];
                    $repetidos = [];
                    $omitidos = [];
                    foreach ($filas as $fila) {
                        if ($fila['rut'] === null) {
                            $omitidos[] = [$fila['fila'], $fila['rut_excel'], $fila['motivo']];
                        } else {
                            if (isset($porRut[$fila['rut']])) { $repetidos[$fila['rut']] = true; }
                            $porRut[$fila['rut']] = $fila;
                        }
                    }
                    $encontrados = [];
                    $actualizados = 0;
                    $rutsActualizados = [];
                    $sinCambios = 0;
                    // Una consulta por lote, nunca una búsqueda SQL por cada fila del Excel.
                    $rutSql = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(UPPER(TRIM(rut)), '.', ''), '-', ''), ' ', ''), CHAR(9), ''), CHAR(10), ''), CHAR(13), '')";
                    foreach (array_chunk(array_map('strval', array_keys($porRut)), 500) as $ruts) {
                        DB::table('reemplazos_personal')->where('anio', intdiv($periodo, 100))->where('mes', $periodo % 100)
                            ->whereIn(DB::raw($rutSql), $ruts)->lockForUpdate()
                            ->chunkById(300, function ($rows) use ($porRut, $repetidos, $campos, $usuario, $periodo, &$encontrados, &$actualizados, &$rutsActualizados, &$sinCambios) {
                                $propuestas = [];
                                foreach ($rows as $row) {
                                    $rut = \App\Support\Rut::normalize($row->rut);
                                    $fila = $porRut[$rut];
                                    if (isset($repetidos[$rut])) {
                                        $this->fallar('Fila '.$fila['fila'].': este RUT se repite en el Excel. Mantenga una sola fila por persona.');
                                    }
                                    if (! empty($fila['errores'])) { $this->fallar(implode(' ', $fila['errores'])); }
                                    $encontrados[$rut] = true;
                                    $datos = array_intersect_key($fila['datos'], array_flip($campos));
                                    $cambios = array_filter($datos, fn ($valor, $campo) => (string) $row->{$campo} !== (string) $valor, ARRAY_FILTER_USE_BOTH);
                                    if (! $cambios) { $sinCambios++; continue; }
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
                                        'controles' => json_encode(['campos' => array_keys($cambios), 'periodo' => $periodo, 'fila_excel' => $fila['fila']], JSON_THROW_ON_ERROR),
                                        'created_at' => now(),
                                    ]);
                                    $actualizados++;
                                    $rutsActualizados[$rut] = true;
                                }
                            });
                    }
                    foreach ($filas as $fila) {
                        if ($fila['rut'] !== null && ! isset($encontrados[$fila['rut']])) {
                            $omitidos[] = [$fila['fila'], $fila['rut_excel'], 'RUT sin registros en el último mes cargado ('.sprintf('%02d/%d', $periodo % 100, intdiv($periodo, 100)).').'];
                        }
                    }
                    usort($omitidos, fn ($a, $b) => $a[0] <=> $b[0]);
                    $reporte = null;
                    if ($omitidos) {
                        $reporte = (string) Str::uuid();
                        $reportePath = $this->reportePath((int) $usuario->id, $reporte);
                        $json = json_encode(['usuario_id' => $usuario->id, 'periodo' => $periodo, 'omitidos' => $omitidos], JSON_THROW_ON_ERROR);
                        // El informe debe quedar disponible antes de confirmar los cambios.
                        if (! Storage::disk('local')->put($reportePath, $json) || ! Storage::disk('local')->exists($reportePath)) {
                            $this->fallar('No fue posible guardar el informe de registros omitidos. No se aplicaron cambios.');
                        }
                    }
                    return ['periodo' => $periodo, 'filas' => count($filas), 'ruts_actualizados' => count($rutsActualizados),
                        'registros_actualizados' => $actualizados, 'sin_cambios' => $sinCambios,
                        'omitidos' => count($omitidos), 'reporte' => $reporte, 'campos' => $campos];
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

    private function fallar(string $mensaje): never
    {
        throw ValidationException::withMessages(['excel_actualizacion' => $mensaje]);
    }
}
