<?php

namespace App\Services\CentroOperaciones;

use App\Models\CentroOperacionesIncidencia;
use App\Models\CentroOperacionesReporte;
use App\Models\CentroOperacionesTicket;
use App\Models\Establecimiento;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReporteService
{
    public function __construct(
        private readonly DatosBaseService $datosBase,
        private readonly EstadoService $estadoService,
        private readonly UnidadOperacionalService $unidades,
    ) {
    }

    public function crear(Establecimiento $establecimiento, User $usuario, array $datos): CentroOperacionesReporte
    {
        $ahora = CarbonImmutable::now(config('centro_operaciones.timezone'));
        $unidadCodigo = Arr::get($datos, 'unidad_codigo') ?: null;
        if (! $this->unidades->codigoPermitido($establecimiento, $unidadCodigo)) {
            throw ValidationException::withMessages([
                'unidad_codigo' => 'La unidad seleccionada no pertenece a este establecimiento.',
            ]);
        }
        $base = $this->datosBase->paraContexto($establecimiento, $ahora->year, $unidadCodigo);

        $reporte = DB::transaction(function () use ($establecimiento, $usuario, $datos, $ahora, $base, $unidadCodigo) {
            $reporte = new CentroOperacionesReporte([
                'establecimiento_id' => $establecimiento->id,
                'unidad_codigo' => $unidadCodigo,
                'reportado_por_id' => $usuario->id,
                'fecha_reporte' => $ahora->toDateString(),
                'reportado_en' => $ahora,
                'establecimiento_nombre' => $this->unidades->nombreReporte($establecimiento, $unidadCodigo),
                'establecimiento_rbd' => $establecimiento->rbd,
                'establecimiento_comuna' => $establecimiento->comuna,
                'matricula_total' => $base['matricula']['total'],
                'matricula_fuente' => $base['matricula']['fuente'],
                'docentes_total' => $base['dotacion']['docentes'],
                'asistentes_total' => $base['dotacion']['asistentes'],
                'padron_periodo' => $base['dotacion']['periodo'],
                'regla_version' => '1.1',
                'version' => 1,
            ]);

            $this->aplicarDatos($reporte, $datos);
            $reporte->save();
            $this->sincronizarDetalle($reporte, $usuario, $datos, $ahora);
            $this->actualizarEstado($reporte);
            $this->guardarRevision($reporte, $usuario);

            return $reporte->fresh($this->relaciones());
        });

        $this->generarTickets($reporte, $usuario);

        return $reporte;
    }

    public function actualizar(CentroOperacionesReporte $reporte, User $usuario, array $datos): CentroOperacionesReporte
    {
        $ahora = CarbonImmutable::now(config('centro_operaciones.timezone'));

        $reporte = DB::transaction(function () use ($reporte, $usuario, $datos, $ahora) {
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

        $this->generarTickets($reporte, $usuario);

        return $reporte;
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
            'fecha_control_plagas' => $this->fechaControlPlagas($reporte, $datos),
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

        $tiposIncidencia = collect(Arr::get($datos, 'incidencias', []))
            ->reject(fn ($tipo) => (bool) config("centro_operaciones.incidencias.{$tipo}.automatic", false))
            ->unique()
            ->values();
        $incidenciasRetiradas = CentroOperacionesIncidencia::query()
            ->where('reporte_id', $reporte->id)
            ->where('estado', 'activa')
            ->where('tipo', '!=', 'control_plagas_vencido')
            ->whereNotIn('tipo', $tiposIncidencia);
        $this->resolverIncidencias(
            $incidenciasRetiradas,
            $reporte,
            $usuario,
            $ahora,
            'Incidencia retirada durante la actualización del reporte diario.'
        );
        foreach ($tiposIncidencia as $tipo) {
            $incidencia = $reporte->incidencias()
                ->where('tipo', $tipo)
                ->where('estado', 'activa')
                ->first();
            $modalidad = Arr::get($datos, "incidencia_modalidades.{$tipo}");
            $atributos = [
                'tipo' => $tipo,
                'modalidad' => $modalidad,
                'severidad' => config(
                    "centro_operaciones.severidades_modalidad_incidencia.{$tipo}.{$modalidad}",
                    config("centro_operaciones.incidencias.{$tipo}.severity", 'alerta')
                ),
                'descripcion' => Arr::get($datos, "incidencia_detalles.{$tipo}"),
            ];

            if ($incidencia) {
                $incidencia->update($atributos);
            } else {
                $reporte->incidencias()->create($atributos + [
                    'establecimiento_id' => $reporte->establecimiento_id,
                    'unidad_codigo' => $reporte->unidad_codigo,
                    'fecha_incidencia' => $reporte->fecha_reporte,
                    'estado' => 'activa',
                ]);
            }
        }

        $idsResolucion = collect(Arr::get($datos, 'incidencias_resueltas', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->all();

        $incidenciasResueltas = CentroOperacionesIncidencia::query()
            ->whereIn('id', $idsResolucion)
            ->where('establecimiento_id', $reporte->establecimiento_id)
            ->where('unidad_codigo', $reporte->unidad_codigo)
            ->where('estado', 'activa');
        $this->resolverIncidencias(
            $incidenciasResueltas,
            $reporte,
            $usuario,
            $ahora,
            'Incidencia resuelta desde el reporte diario.'
        );

        $this->sincronizarControlPlagas($reporte, $usuario, $ahora);
    }

    private function actualizarEstado(CentroOperacionesReporte $reporte): void
    {
        $reporte->load(['servicios', 'afectaciones']);
        $activas = CentroOperacionesIncidencia::query()
            ->where('establecimiento_id', $reporte->establecimiento_id)
            ->where('unidad_codigo', $reporte->unidad_codigo)
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

    private function fechaControlPlagas(CentroOperacionesReporte $reporte, array $datos): ?string
    {
        $fechaIngresada = Arr::get($datos, 'fecha_control_plagas');
        if ($fechaIngresada) {
            return (string) $fechaIngresada;
        }

        if ($reporte->fecha_control_plagas) {
            return $reporte->fecha_control_plagas->toDateString();
        }

        $fechaAnterior = CentroOperacionesReporte::query()
            ->where('establecimiento_id', $reporte->establecimiento_id)
            ->where('unidad_codigo', $reporte->unidad_codigo)
            ->whereNotNull('fecha_control_plagas')
            ->latest('reportado_en')
            ->latest('id')
            ->value('fecha_control_plagas');

        return $fechaAnterior
            ? CarbonImmutable::parse((string) $fechaAnterior)->toDateString()
            : null;
    }

    private function sincronizarControlPlagas(
        CentroOperacionesReporte $reporte,
        User $usuario,
        CarbonImmutable $ahora
    ): void {
        if (! $reporte->fecha_control_plagas) {
            return;
        }

        $consulta = CentroOperacionesIncidencia::query()
            ->where('establecimiento_id', $reporte->establecimiento_id)
            ->where('unidad_codigo', $reporte->unidad_codigo)
            ->where('tipo', 'control_plagas_vencido')
            ->where('estado', 'activa');
        $fechaReporte = CarbonImmutable::parse(
            $reporte->fecha_reporte->toDateString(),
            config('centro_operaciones.timezone')
        )->startOfDay();

        if ($reporte->fecha_control_plagas->startOfDay()->isBefore($fechaReporte)) {
            if (! $consulta->exists()) {
                $reporte->incidencias()->create([
                    'establecimiento_id' => $reporte->establecimiento_id,
                    'unidad_codigo' => $reporte->unidad_codigo,
                    'fecha_incidencia' => $reporte->fecha_reporte,
                    'tipo' => 'control_plagas_vencido',
                    'severidad' => config('centro_operaciones.incidencias.control_plagas_vencido.severity', 'critico'),
                    'descripcion' => 'La vigencia informada terminó el '.$reporte->fecha_control_plagas->format('d-m-Y').'.',
                    'estado' => 'activa',
                ]);
            }

            return;
        }

        $this->resolverIncidencias(
            $consulta,
            $reporte,
            $usuario,
            $ahora,
            'Control de plagas actualizado con una fecha vigente.'
        );
    }

    private function resolverIncidencias(
        Builder $consulta,
        CentroOperacionesReporte $reporte,
        User $usuario,
        CarbonImmutable $ahora,
        string $resolucion
    ): void {
        $ids = (clone $consulta)->pluck('id');
        if ($ids->isEmpty()) {
            return;
        }

        CentroOperacionesIncidencia::query()
            ->whereIn('id', $ids)
            ->update([
                'estado' => 'resuelta',
                'resuelta_en' => $ahora,
                'resuelta_por_id' => $usuario->id,
                'resuelta_en_reporte_id' => $reporte->id,
                'updated_at' => $ahora,
            ]);

        CentroOperacionesTicket::query()
            ->whereIn('incidencia_id', $ids)
            ->where('estado', '!=', 'resuelto')
            ->update([
                'estado' => 'resuelto',
                'resuelto_en' => $ahora,
                'resuelto_por_id' => $usuario->id,
                'resolucion' => $resolucion,
                'updated_at' => $ahora,
            ]);
    }

    /** @return array<int, string> */
    private function relaciones(): array
    {
        return ['establecimiento', 'reportadoPor', 'servicios', 'afectaciones', 'incidencias', 'incidenciasResueltas'];
    }

    private function generarTickets(CentroOperacionesReporte $reporte, User $usuario): void
    {
        $tickets = app(TicketService::class);
        $reporte->incidencias()->where('estado', 'activa')->where('tipo', '!=', 'otro')
            ->whereDoesntHave('ticket')->get()
            ->each(fn (CentroOperacionesIncidencia $incidencia) => $tickets->crearParaIncidencia($incidencia, $usuario));
    }
}
