<?php

namespace App\Services\CentroOperaciones;

use App\Models\CentroOperacionesIncidencia;
use App\Models\CentroOperacionesReporte;
use App\Models\Establecimiento;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReporteService
{
    public function __construct(
        private readonly DatosBaseService $datosBase,
        private readonly EstadoService $estadoService,
    ) {
    }

    public function crear(Establecimiento $establecimiento, User $usuario, array $datos): CentroOperacionesReporte
    {
        $ahora = CarbonImmutable::now(config('centro_operaciones.timezone'));
        $base = $this->datosBase->paraEstablecimiento($establecimiento, $ahora->year);

        return DB::transaction(function () use ($establecimiento, $usuario, $datos, $ahora, $base) {
            $reporte = new CentroOperacionesReporte([
                'establecimiento_id' => $establecimiento->id,
                'reportado_por_id' => $usuario->id,
                'fecha_reporte' => $ahora->toDateString(),
                'reportado_en' => $ahora,
                'establecimiento_nombre' => $establecimiento->nombre_establecimiento,
                'establecimiento_rbd' => $establecimiento->rbd,
                'establecimiento_comuna' => $establecimiento->comuna,
                'matricula_total' => $base['matricula']['total'],
                'matricula_fuente' => $base['matricula']['fuente'],
                'docentes_total' => $base['dotacion']['docentes'],
                'asistentes_total' => $base['dotacion']['asistentes'],
                'padron_periodo' => $base['dotacion']['periodo'],
                'regla_version' => '1.0',
                'version' => 1,
            ]);

            $this->aplicarDatos($reporte, $datos);
            $reporte->save();
            $this->sincronizarDetalle($reporte, $usuario, $datos, $ahora);
            $this->actualizarEstado($reporte);
            $this->guardarRevision($reporte, $usuario);

            return $reporte->fresh($this->relaciones());
        });
    }

    public function actualizar(CentroOperacionesReporte $reporte, User $usuario, array $datos): CentroOperacionesReporte
    {
        $ahora = CarbonImmutable::now(config('centro_operaciones.timezone'));

        return DB::transaction(function () use ($reporte, $usuario, $datos, $ahora) {
            $reporte = CentroOperacionesReporte::query()->lockForUpdate()->findOrFail($reporte->id);
            $reporte->version++;
            $reporte->reportado_por_id = $usuario->id;
            $reporte->reportado_en = $ahora;
            $this->aplicarDatos($reporte, $datos);
            $reporte->save();
            $this->sincronizarDetalle($reporte, $usuario, $datos, $ahora);
            $this->actualizarEstado($reporte);
            $this->guardarRevision($reporte, $usuario);

            return $reporte->fresh($this->relaciones());
        });
    }

    private function aplicarDatos(CentroOperacionesReporte $reporte, array $datos): void
    {
        $estudiantesPresentes = (int) $datos['estudiantes_presentes'];
        $docentesPresentes = (int) $datos['docentes_presentes'];
        $asistentesPresentes = (int) $datos['asistentes_presentes'];

        $errores = [];
        if ($estudiantesPresentes > $reporte->matricula_total) {
            $errores['estudiantes_presentes'] = 'La asistencia de estudiantes no puede superar la matrícula total.';
        }
        if ($docentesPresentes > $reporte->docentes_total) {
            $errores['docentes_presentes'] = 'Los docentes presentes no pueden superar la dotación total.';
        }
        if ($asistentesPresentes > $reporte->asistentes_total) {
            $errores['asistentes_presentes'] = 'Los asistentes presentes no pueden superar la dotación total.';
        }
        if ($errores !== []) {
            throw ValidationException::withMessages($errores);
        }

        $reporte->fill([
            'funcionamiento' => $datos['funcionamiento'],
            'estudiantes_presentes' => $estudiantesPresentes,
            'docentes_presentes' => $docentesPresentes,
            'asistentes_presentes' => $asistentesPresentes,
            'observaciones' => Arr::get($datos, 'observaciones'),
            'necesita_apoyo' => (bool) Arr::get($datos, 'necesita_apoyo', false),
            'apoyo_detalle' => Arr::get($datos, 'necesita_apoyo', false)
                ? Arr::get($datos, 'apoyo_detalle')
                : null,
            'prioridad' => $datos['prioridad'],
        ]);
    }

    private function sincronizarDetalle(
        CentroOperacionesReporte $reporte,
        User $usuario,
        array $datos,
        CarbonImmutable $ahora
    ): void {
        $servicios = collect($datos['servicios'])->map(fn ($estado, $servicio) => [
            'servicio' => $servicio,
            'estado' => $estado,
            'observacion' => Arr::get($datos, "servicio_observaciones.{$servicio}"),
        ])->values()->all();
        $reporte->servicios()->delete();
        $reporte->servicios()->createMany($servicios);

        $afectaciones = collect(Arr::get($datos, 'afectaciones', []))->map(fn ($tipo) => [
            'tipo' => $tipo,
            'detalle' => $tipo === 'otro' ? Arr::get($datos, 'afectacion_otro') : null,
        ])->all();
        $reporte->afectaciones()->delete();
        $reporte->afectaciones()->createMany($afectaciones);

        $tiposIncidencia = collect(Arr::get($datos, 'incidencias', []))->unique()->values();
        $reporte->incidencias()
            ->where('estado', 'activa')
            ->whereNotIn('tipo', $tiposIncidencia)
            ->delete();
        foreach ($tiposIncidencia as $tipo) {
            $incidencia = $reporte->incidencias()->where('tipo', $tipo)->first();
            $atributos = [
                'severidad' => config("centro_operaciones.incidencias.{$tipo}.severity", 'alerta'),
                'descripcion' => Arr::get($datos, "incidencia_detalles.{$tipo}"),
            ];

            if ($incidencia) {
                $incidencia->update($atributos);
            } else {
                $reporte->incidencias()->create($atributos + [
                    'establecimiento_id' => $reporte->establecimiento_id,
                    'fecha_incidencia' => $reporte->fecha_reporte,
                    'estado' => 'activa',
                ]);
            }
        }

        $idsResolucion = collect(Arr::get($datos, 'incidencias_resueltas', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->all();

        CentroOperacionesIncidencia::query()
            ->whereIn('id', $idsResolucion)
            ->where('establecimiento_id', $reporte->establecimiento_id)
            ->where('estado', 'activa')
            ->update([
                'estado' => 'resuelta',
                'resuelta_en' => $ahora,
                'resuelta_por_id' => $usuario->id,
                'resuelta_en_reporte_id' => $reporte->id,
                'updated_at' => $ahora,
            ]);
    }

    private function actualizarEstado(CentroOperacionesReporte $reporte): void
    {
        $reporte->load(['servicios', 'afectaciones']);
        $activas = CentroOperacionesIncidencia::query()
            ->where('establecimiento_id', $reporte->establecimiento_id)
            ->where('estado', 'activa')
            ->get();

        $reporte->estado_general = $this->estadoService->paraReporte($reporte, $activas);
        $reporte->save();
    }

    private function guardarRevision(CentroOperacionesReporte $reporte, User $usuario): void
    {
        $reporte->load($this->relaciones());
        $reporte->revisiones()->create([
            'version' => $reporte->version,
            'editado_por_id' => $usuario->id,
            'datos' => $reporte->toArray(),
        ]);
    }

    /** @return array<int, string> */
    private function relaciones(): array
    {
        return ['establecimiento', 'reportadoPor', 'servicios', 'afectaciones', 'incidencias', 'incidenciasResueltas'];
    }
}
