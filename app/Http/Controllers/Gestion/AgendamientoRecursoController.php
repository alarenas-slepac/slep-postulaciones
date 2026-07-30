<?php

namespace App\Http\Controllers\Gestion;

use App\Http\Controllers\Controller;
use App\Models\AgendamientoRecurso;
use App\Models\AgendamientoRecursoCatalogo;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AgendamientoRecursoController extends Controller
{
    private const ROLES_ACCESO = [
        'admin',
        'coordinador_gdp',
        'funcionario_slep',
        'funcionario_ac',
        'secretaria_direccion_ejecutiva',
        'coordinador_uatp',
        'supervisor_plani',
    ];

    private const ROLES_ADMINISTRABLES_SALA = [
        'funcionario_ac',
        'funcionario_slep',
        'coordinador_uatp',
        'coordinador_gdp',
        'supervisor_plani',
        'secretaria_direccion_ejecutiva',
        'admin',
    ];

    public function index(Request $request): View
    {
        $fechaBase = $this->fechaBase($request);
        $inicioMes = $fechaBase->copy()->startOfMonth();
        $finMes = $fechaBase->copy()->endOfMonth();
        $recursoId = $request->integer('recurso_id') ?: null;
        $estado = $request->string('estado', 'activos')->toString();

        $recursos = AgendamientoRecursoCatalogo::query()
            ->where('activo', true)
            ->orderBy('tipo')
            ->orderBy('nombre')
            ->get();

        $query = AgendamientoRecurso::query()
            ->with(['creador', 'solicitante', 'recurso', 'aprobador', 'rechazador'])
            ->whereBetween('fecha', [$inicioMes->toDateString(), $finMes->toDateString()]);

        if ($recursoId) {
            $query->where('recurso_catalogo_id', $recursoId);
        }

        if ($estado === 'activos') {
            $query->whereIn('estado', [AgendamientoRecurso::ESTADO_PENDIENTE, AgendamientoRecurso::ESTADO_VIGENTE, AgendamientoRecurso::ESTADO_APROBADO]);
        } elseif ($estado !== 'todos') {
            $query->where('estado', $estado);
        }

        $agendamientosMes = $query
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->get();

        $agendamientosPorDia = $agendamientosMes->groupBy(fn (AgendamientoRecurso $item) => $item->fecha?->toDateString());

        $proximos = AgendamientoRecurso::query()
            ->with(['recurso'])
            ->whereIn('estado', [AgendamientoRecurso::ESTADO_PENDIENTE, AgendamientoRecurso::ESTADO_VIGENTE, AgendamientoRecurso::ESTADO_APROBADO])
            ->where('fecha', '>=', now()->toDateString())
            ->when($recursoId, fn ($q) => $q->where('recurso_catalogo_id', $recursoId))
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->limit(10)
            ->get();

        $resumen = [
            'total_mes' => $agendamientosMes->count(),
            'pendientes' => $agendamientosMes->where('estado', AgendamientoRecurso::ESTADO_PENDIENTE)->count(),
            'aprobados' => $agendamientosMes->whereIn('estado', [AgendamientoRecurso::ESTADO_APROBADO, AgendamientoRecurso::ESTADO_VIGENTE])->count(),
            'salas' => $agendamientosMes->filter(fn ($item) => $item->recurso?->tipo === AgendamientoRecursoCatalogo::TIPO_SALA)->count(),
        ];

        return view('gestion.agendamientos-recursos.index', [
            'fechaBase' => $fechaBase,
            'inicioMes' => $inicioMes,
            'finMes' => $finMes,
            'agendamientosPorDia' => $agendamientosPorDia,
            'agendamientosMes' => $agendamientosMes,
            'proximos' => $proximos,
            'recursosCatalogo' => $recursos,
            'estados' => AgendamientoRecurso::estados(),
            'resumen' => $resumen,
            'filtros' => [
                'recurso_id' => $recursoId,
                'estado' => $estado,
            ],
            'puedeAdministrarRecursos' => $this->puedeAdministrarCatalogo($request->user()),
        ]);
    }

    public function create(Request $request): View
    {
        $recursos = AgendamientoRecursoCatalogo::query()
            ->where('activo', true)
            ->orderBy('tipo')
            ->orderBy('nombre')
            ->get();

        $recursoDefault = $request->integer('recurso_id') ?: optional($recursos->first())->id;

        $agendamiento = new AgendamientoRecurso([
            'fecha' => $request->date('fecha') ?: now()->toDateString(),
            'hora_inicio' => $request->input('hora_inicio', '09:00'),
            'hora_termino' => $request->input('hora_termino', '10:00'),
            'recurso_catalogo_id' => $recursoDefault,
            'estado' => AgendamientoRecurso::ESTADO_VIGENTE,
        ]);

        return view('gestion.agendamientos-recursos.create', [
            'agendamiento' => $agendamiento,
            'recursosCatalogo' => $recursos,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $recurso = AgendamientoRecursoCatalogo::findOrFail($data['recurso_catalogo_id']);
        $this->validarRecursoTieneAdministrador($recurso);
        $this->validarDisponibilidad($data, null, $recurso);

        $user = $request->user();
        $estadoInicial = $recurso->requiere_aprobacion
            ? AgendamientoRecurso::ESTADO_PENDIENTE
            : AgendamientoRecurso::ESTADO_VIGENTE;

        $data['estado'] = $estadoInicial;
        $data['tipo_recurso'] = $recurso->slug;
        $data['created_by'] = $user?->id;
        $data['updated_by'] = $user?->id;
        $data['solicitado_by'] = $user?->id;
        $data['solicitante_user_id'] = $user?->id;
        $data['solicitante_nombre'] = $data['solicitante_nombre'] ?: ($user?->nombre_completo ?? $user?->name ?? null);
        $data['solicitante_email'] = $data['solicitante_email'] ?: ($user?->email ?? null);

        if ($recurso->tipo === AgendamientoRecursoCatalogo::TIPO_PROYECTOR) {
            $data['requiere_proyector'] = true;
        }

        $agendamiento = AgendamientoRecurso::create($data);
        $agendamiento->load(['recurso', 'solicitante', 'creador']);

        if ($agendamiento->estado === AgendamientoRecurso::ESTADO_PENDIENTE) {
            $this->notificarAdministradores($agendamiento);
        }

        $message = $agendamiento->estado === AgendamientoRecurso::ESTADO_PENDIENTE
            ? 'Solicitud registrada correctamente. Quedó pendiente de aprobación por el administrador de sala.'
            : 'Agendamiento registrado correctamente.';

        return redirect()
            ->route('gestion.agendamientos-recursos.show', $agendamiento)
            ->with('success', $message);
    }

    public function show(Request $request, AgendamientoRecurso $agendamiento): View
    {
        $agendamiento->load(['creador', 'editor', 'anulador', 'solicitante', 'solicitadoPor', 'recurso.administradores', 'aprobador', 'rechazador']);

        return view('gestion.agendamientos-recursos.show', [
            'agendamiento' => $agendamiento,
            'puedeEditar' => $this->puedeEditar($request->user(), $agendamiento),
            'puedeAnular' => $this->puedeAnular($request->user(), $agendamiento),
            'puedeResolver' => $this->puedeResolver($request->user(), $agendamiento),
        ]);
    }

    public function edit(Request $request, AgendamientoRecurso $agendamiento): View|RedirectResponse
    {
        if (! $this->puedeEditar($request->user(), $agendamiento)) {
            return redirect()->route('gestion.agendamientos-recursos.show', $agendamiento)->with('warning', 'No tiene permisos para editar este agendamiento.');
        }

        if (in_array($agendamiento->estado, [AgendamientoRecurso::ESTADO_ANULADO, AgendamientoRecurso::ESTADO_RECHAZADO, AgendamientoRecurso::ESTADO_FINALIZADO], true)) {
            return redirect()->route('gestion.agendamientos-recursos.show', $agendamiento)->with('warning', 'Sólo se pueden editar agendamientos activos o pendientes.');
        }

        $recursos = AgendamientoRecursoCatalogo::query()->where('activo', true)->orderBy('tipo')->orderBy('nombre')->get();

        return view('gestion.agendamientos-recursos.edit', [
            'agendamiento' => $agendamiento,
            'recursosCatalogo' => $recursos,
        ]);
    }

    public function update(Request $request, AgendamientoRecurso $agendamiento): RedirectResponse
    {
        if (! $this->puedeEditar($request->user(), $agendamiento)) {
            return redirect()->route('gestion.agendamientos-recursos.show', $agendamiento)->with('warning', 'No tiene permisos para editar este agendamiento.');
        }

        $data = $this->validatedData($request);
        $recurso = AgendamientoRecursoCatalogo::findOrFail($data['recurso_catalogo_id']);
        $this->validarRecursoTieneAdministrador($recurso);
        $this->validarDisponibilidad($data, $agendamiento->id, $recurso);

        $datosOriginales = $this->datosOriginalesHorario($agendamiento);
        $hayCambioHorario = $this->hayCambioHorario($datosOriginales, $data);
        $recursoAnterior = $agendamiento->recurso;

        $data['tipo_recurso'] = $recurso->slug;
        $data['updated_by'] = $request->user()?->id;

        if ($recurso->requiere_aprobacion && ! $this->puedeResolver($request->user(), $agendamiento) && $agendamiento->estado !== AgendamientoRecurso::ESTADO_PENDIENTE) {
            $data['estado'] = AgendamientoRecurso::ESTADO_PENDIENTE;
            $data['aprobado_by'] = null;
            $data['aprobado_at'] = null;
            $data['rechazado_by'] = null;
            $data['rechazado_at'] = null;
            $data['motivo_rechazo'] = null;
        }

        if ($recurso->tipo === AgendamientoRecursoCatalogo::TIPO_PROYECTOR) {
            $data['requiere_proyector'] = true;
        }

        $agendamiento->update($data);
        $agendamiento->load(['recurso', 'solicitante', 'creador']);

        if ($hayCambioHorario) {
            $this->notificarAdministradoresCambio($agendamiento, 'edicion_horario', $datosOriginales, $recursoAnterior);
        } elseif ($agendamiento->estado === AgendamientoRecurso::ESTADO_PENDIENTE) {
            $this->notificarAdministradores($agendamiento);
        }

        return redirect()->route('gestion.agendamientos-recursos.show', $agendamiento)->with('success', 'Agendamiento actualizado correctamente.');
    }

    public function aprobar(Request $request, AgendamientoRecurso $agendamiento): RedirectResponse
    {
        if (! $this->puedeResolver($request->user(), $agendamiento)) {
            return back()->with('warning', 'No tiene permisos para aprobar esta solicitud.');
        }

        if (! $agendamiento->recurso?->requiere_aprobacion) {
            return back()->with('warning', 'Este recurso no tiene habilitado el flujo de aprobación.');
        }

        if ($agendamiento->estado !== AgendamientoRecurso::ESTADO_PENDIENTE) {
            return back()->with('warning', 'Sólo se pueden aprobar solicitudes pendientes.');
        }

        $this->validarDisponibilidad([
            'recurso_catalogo_id' => $agendamiento->recurso_catalogo_id,
            'fecha' => $agendamiento->fecha?->format('Y-m-d'),
            'hora_inicio' => substr((string) $agendamiento->hora_inicio, 0, 5),
            'hora_termino' => substr((string) $agendamiento->hora_termino, 0, 5),
        ], $agendamiento->id, $agendamiento->recurso);

        $agendamiento->update([
            'estado' => AgendamientoRecurso::ESTADO_APROBADO,
            'aprobado_by' => $request->user()?->id,
            'aprobado_at' => now(),
            'rechazado_by' => null,
            'rechazado_at' => null,
            'motivo_rechazo' => null,
            'updated_by' => $request->user()?->id,
        ]);

        $this->notificarSolicitanteResolucion($agendamiento->fresh(['recurso', 'solicitante', 'aprobador']), true);

        return back()->with('success', 'Solicitud aprobada correctamente.');
    }

    public function rechazar(Request $request, AgendamientoRecurso $agendamiento): RedirectResponse
    {
        if (! $this->puedeResolver($request->user(), $agendamiento)) {
            return back()->with('warning', 'No tiene permisos para rechazar esta solicitud.');
        }

        if (! $agendamiento->recurso?->requiere_aprobacion) {
            return back()->with('warning', 'Este recurso no tiene habilitado el flujo de aprobación.');
        }

        $validated = $request->validate([
            'motivo_rechazo' => ['required', 'string', 'max:2000'],
        ]);

        $agendamiento->update([
            'estado' => AgendamientoRecurso::ESTADO_RECHAZADO,
            'rechazado_by' => $request->user()?->id,
            'rechazado_at' => now(),
            'motivo_rechazo' => $validated['motivo_rechazo'],
            'updated_by' => $request->user()?->id,
        ]);

        $this->notificarSolicitanteResolucion($agendamiento->fresh(['recurso', 'solicitante', 'rechazador']), false);

        return back()->with('success', 'Solicitud rechazada correctamente.');
    }

    public function anular(Request $request, AgendamientoRecurso $agendamiento): RedirectResponse
    {
        if (! $this->puedeAnular($request->user(), $agendamiento)) {
            return back()->with('warning', 'No tiene permisos para anular este agendamiento.');
        }

        $validated = $request->validate([
            'motivo_anulacion' => ['required', 'string', 'max:1000'],
        ]);

        if ($agendamiento->estado === AgendamientoRecurso::ESTADO_ANULADO) {
            return back()->with('warning', 'El agendamiento ya se encuentra anulado.');
        }

        $datosOriginales = $this->datosOriginalesHorario($agendamiento);
        $recursoAnterior = $agendamiento->recurso;

        $agendamiento->update([
            'estado' => AgendamientoRecurso::ESTADO_ANULADO,
            'motivo_anulacion' => $validated['motivo_anulacion'],
            'anulado_by' => $request->user()?->id,
            'anulado_at' => now(),
            'updated_by' => $request->user()?->id,
        ]);

        $agendamiento->load(['recurso', 'solicitante', 'creador', 'anulador']);
        $this->notificarAdministradoresCambio($agendamiento, 'anulacion', $datosOriginales, $recursoAnterior);

        return redirect()
            ->route('gestion.agendamientos-recursos.index', ['month' => $agendamiento->fecha?->format('Y-m'), 'recurso_id' => $agendamiento->recurso_catalogo_id])
            ->with('success', 'Agendamiento anulado correctamente.');
    }

    public function recursosIndex(Request $request): View
    {
        abort_unless($this->puedeAdministrarCatalogo($request->user()), 403);

        $recursos = AgendamientoRecursoCatalogo::query()
            ->with(['administradores'])
            ->orderBy('tipo')
            ->orderBy('nombre')
            ->get();

        return view('gestion.agendamientos-recursos.recursos.index', compact('recursos'));
    }

    public function recursosCreate(Request $request): View
    {
        abort_unless($this->puedeAdministrarCatalogo($request->user()), 403);

        return view('gestion.agendamientos-recursos.recursos.create', [
            'recurso' => new AgendamientoRecursoCatalogo(['tipo' => AgendamientoRecursoCatalogo::TIPO_SALA, 'activo' => true]),
            'tipos' => AgendamientoRecursoCatalogo::tipos(),
            'usuariosAdministrables' => $this->usuariosAdministrables(),
        ]);
    }

    public function recursosStore(Request $request): RedirectResponse
    {
        abort_unless($this->puedeAdministrarCatalogo($request->user()), 403);

        $data = $this->validatedRecursoData($request);
        $administradores = $this->validatedAdministradoresData($request);
        $data['slug'] = $data['slug'] ?: Str::slug($data['nombre'], '_');
        $data['created_by'] = $request->user()?->id;
        $data['updated_by'] = $request->user()?->id;

        $recurso = AgendamientoRecursoCatalogo::create($data);
        $this->syncAdministradores($recurso, $administradores, $request->user()?->id);

        return redirect()->route('gestion.agendamientos-recursos.recursos.index')->with('success', 'Sala/recurso creado correctamente.');
    }

    public function recursosEdit(Request $request, AgendamientoRecursoCatalogo $recurso): View
    {
        abort_unless($this->puedeAdministrarCatalogo($request->user()) || $this->esAdministradorDeRecurso($request->user(), $recurso), 403);

        return view('gestion.agendamientos-recursos.recursos.edit', [
            'recurso' => $recurso->load('administradores'),
            'tipos' => AgendamientoRecursoCatalogo::tipos(),
            'usuariosAdministrables' => $this->usuariosAdministrables(),
            'soloGestionAdmin' => ! $this->puedeAdministrarCatalogo($request->user()),
        ]);
    }

    public function recursosUpdate(Request $request, AgendamientoRecursoCatalogo $recurso): RedirectResponse
    {
        abort_unless($this->puedeAdministrarCatalogo($request->user()) || $this->esAdministradorDeRecurso($request->user(), $recurso), 403);

        $soloGestionAdmin = ! $this->puedeAdministrarCatalogo($request->user());
        if ($soloGestionAdmin) {
            $data = $request->validate([
                'requiere_aprobacion' => ['nullable', 'boolean'],
            ]);
            $recurso->update([
                'requiere_aprobacion' => $request->boolean('requiere_aprobacion'),
                'updated_by' => $request->user()?->id,
            ]);
        } else {
            $data = $this->validatedRecursoData($request, $recurso);
            $administradores = $this->validatedAdministradoresData($request);
            $data['slug'] = $data['slug'] ?: Str::slug($data['nombre'], '_');
            $data['updated_by'] = $request->user()?->id;
            $recurso->update($data);
            $this->syncAdministradores($recurso, $administradores, $request->user()?->id);
        }

        return redirect()->route('gestion.agendamientos-recursos.recursos.index')->with('success', 'Sala/recurso actualizado correctamente.');
    }

    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'recurso_catalogo_id' => ['required', 'integer', 'exists:agendamiento_recursos_catalogo,id'],
            'titulo' => ['required', 'string', 'max:180'],
            'fecha' => ['required', 'date'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'hora_termino' => ['required', 'date_format:H:i', 'after:hora_inicio'],
            'solicitante_nombre' => ['nullable', 'string', 'max:180'],
            'solicitante_email' => ['nullable', 'email', 'max:180'],
            'unidad' => ['nullable', 'string', 'max:180'],
            'lugar_uso' => ['nullable', 'string', 'max:220'],
            'cantidad_participantes' => ['nullable', 'integer', 'min:1', 'max:500'],
            'motivo' => ['nullable', 'string', 'max:3000'],
            'responsable_retiro' => ['nullable', 'string', 'max:180'],
            'responsable_devolucion' => ['nullable', 'string', 'max:180'],
            'observaciones' => ['nullable', 'string', 'max:3000'],
        ]);

        $data['requiere_proyector'] = $request->boolean('requiere_proyector');
        $data['requiere_apoyo_tecnico'] = $request->boolean('requiere_apoyo_tecnico');

        return $data;
    }

    private function validatedRecursoData(Request $request, ?AgendamientoRecursoCatalogo $recurso = null): array
    {
        $id = $recurso?->id;

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:180'],
            'slug' => ['nullable', 'string', 'max:80', Rule::unique('agendamiento_recursos_catalogo', 'slug')->ignore($id)],
            'tipo' => ['required', Rule::in(array_keys(AgendamientoRecursoCatalogo::tipos()))],
            'ubicacion' => ['nullable', 'string', 'max:180'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
        ]);

        $data['activo'] = $request->boolean('activo');
        $data['requiere_aprobacion'] = $request->boolean('requiere_aprobacion');

        return $data;
    }

    private function validarRecursoTieneAdministrador(AgendamientoRecursoCatalogo $recurso): void
    {
        if (! $recurso->administradores()->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'recurso_catalogo_id' => 'La sala/recurso seleccionado no tiene administrador asignado. Debe asignarse un administrador antes de permitir solicitudes o agendamientos.',
            ]);
        }
    }

    private function validarDisponibilidad(array $data, ?int $exceptId, AgendamientoRecursoCatalogo $recurso): void
    {
        $query = AgendamientoRecurso::query()
            ->where('recurso_catalogo_id', $data['recurso_catalogo_id'])
            ->where('fecha', $data['fecha'])
            ->whereIn('estado', [AgendamientoRecurso::ESTADO_PENDIENTE, AgendamientoRecurso::ESTADO_VIGENTE, AgendamientoRecurso::ESTADO_APROBADO])
            ->where('hora_inicio', '<', $data['hora_termino'])
            ->where('hora_termino', '>', $data['hora_inicio']);

        if ($exceptId) {
            $query->whereKeyNot($exceptId);
        }

        if ($query->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'hora_inicio' => 'Ya existe una solicitud o agendamiento activo para la sala/recurso seleccionado en ese rango horario.',
            ]);
        }
    }

    private function fechaBase(Request $request): Carbon
    {
        $month = $request->string('month')->toString();

        if (preg_match('/^\d{4}-\d{2}$/', $month)) {
            return Carbon::createFromFormat('Y-m-d', $month . '-01')->startOfMonth();
        }

        return now()->startOfMonth();
    }

    private function validatedAdministradoresData(Request $request): array
    {
        $request->validate([
            'administradores' => ['required', 'array', 'min:1'],
            'administradores.*' => ['integer', 'exists:users,id'],
        ], [
            'administradores.required' => 'Debe asignar al menos un administrador responsable para la sala/recurso.',
            'administradores.min' => 'Debe asignar al menos un administrador responsable para la sala/recurso.',
            'administradores.*.exists' => 'Uno de los administradores seleccionados no existe en el sistema.',
        ]);

        return array_values(array_unique(array_filter(array_map('intval', $request->input('administradores', [])))));
    }

    private function activeRole($user): ?string
    {
        if (! $user) {
            return null;
        }

        if (method_exists($user, 'activeRoleName')) {
            return $user->activeRoleName();
        }

        return null;
    }

    private function userHasRole($user, string|array $roles): bool
    {
        if (! $user) {
            return false;
        }

        $roles = (array) $roles;
        $active = $this->activeRole($user);

        if ($active && in_array($active, $roles, true)) {
            return true;
        }

        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole($roles)) {
            return true;
        }

        if (method_exists($user, 'hasRole')) {
            foreach ($roles as $role) {
                if ($user->hasRole($role)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function puedeAdministrarCatalogo($user): bool
    {
        return $this->userHasRole($user, ['admin']);
    }

    private function esAdministradorDeRecurso($user, AgendamientoRecursoCatalogo $recurso): bool
    {
        if (! $user) {
            return false;
        }

        if ($this->userHasRole($user, ['admin'])) {
            return true;
        }

        return $recurso->administradores()->where('users.id', $user->id)->exists();
    }

    private function puedeResolver($user, AgendamientoRecurso $agendamiento): bool
    {
        if (! $agendamiento->recurso?->requiere_aprobacion) {
            return false;
        }

        return $this->esAdministradorDeRecurso($user, $agendamiento->recurso);
    }

    private function puedeEditar($user, AgendamientoRecurso $agendamiento): bool
    {
        if (! $user) {
            return false;
        }

        if ($agendamiento->recurso && $this->esAdministradorDeRecurso($user, $agendamiento->recurso)) {
            return true;
        }

        return (int) $agendamiento->created_by === (int) $user->id || (int) $agendamiento->solicitado_by === (int) $user->id;
    }

    private function puedeAnular($user, AgendamientoRecurso $agendamiento): bool
    {
        return $this->puedeEditar($user, $agendamiento);
    }

    private function syncAdministradores(AgendamientoRecursoCatalogo $recurso, array $ids, ?int $createdBy): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        $sync = [];
        foreach ($ids as $id) {
            $sync[$id] = ['created_by' => $createdBy, 'created_at' => now(), 'updated_at' => now()];
        }

        $recurso->administradores()->sync($sync);
    }

    private function usuariosAdministrables()
    {
        $query = User::query()
            ->orderBy('apellido_paterno')
            ->orderBy('apellido_materno')
            ->orderBy('nombres')
            ->limit(500);

        try {
            $query->role(self::ROLES_ADMINISTRABLES_SALA);
        } catch (\Throwable $e) {
            // Mantiene compatibilidad si el modelo User no expone scope role.
        }

        return $query->get(['id', 'nombres', 'apellido_paterno', 'apellido_materno', 'email']);
    }

    private function datosOriginalesHorario(AgendamientoRecurso $agendamiento): array
    {
        return [
            'recurso_catalogo_id' => $agendamiento->recurso_catalogo_id,
            'recurso_label' => $agendamiento->tipo_recurso_label,
            'fecha' => $agendamiento->fecha?->format('Y-m-d'),
            'hora_inicio' => substr((string) $agendamiento->hora_inicio, 0, 5),
            'hora_termino' => substr((string) $agendamiento->hora_termino, 0, 5),
        ];
    }

    private function hayCambioHorario(array $original, array $nuevo): bool
    {
        return (string) ($original['fecha'] ?? '') !== (string) ($nuevo['fecha'] ?? '')
            || (string) ($original['hora_inicio'] ?? '') !== (string) ($nuevo['hora_inicio'] ?? '')
            || (string) ($original['hora_termino'] ?? '') !== (string) ($nuevo['hora_termino'] ?? '');
    }

    private function emailsAdministradoresRecurso(?AgendamientoRecursoCatalogo $recurso): array
    {
        if (! $recurso) {
            return [];
        }

        return $recurso->administradores()
            ->whereNotNull('email')
            ->pluck('email')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function notificarAdministradoresCambio(AgendamientoRecurso $agendamiento, string $tipo, array $datosOriginales = [], ?AgendamientoRecursoCatalogo $recursoAnterior = null): void
    {
        $agendamiento->loadMissing(['recurso', 'solicitante', 'creador', 'editor', 'anulador']);

        $emails = $this->emailsAdministradoresRecurso($agendamiento->recurso);

        if ($recursoAnterior && (int) $recursoAnterior->id !== (int) $agendamiento->recurso_catalogo_id) {
            $emails = array_merge($emails, $this->emailsAdministradoresRecurso($recursoAnterior));
        }

        $emails = array_values(array_unique(array_filter($emails)));
        if (empty($emails)) {
            return;
        }

        $subject = $tipo === 'anulacion'
            ? 'Sala disponible por anulación: ' . $agendamiento->tipo_recurso_label
            : 'Cambio de fecha u horario de agendamiento: ' . $agendamiento->tipo_recurso_label;

        try {
            Mail::send('emails.agendamientos.aviso-administrador', [
                'agendamiento' => $agendamiento,
                'tipo' => $tipo,
                'datosOriginales' => $datosOriginales,
            ], function ($message) use ($emails, $subject) {
                $message->to($emails)->subject($subject);
            });
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function notificarAdministradores(AgendamientoRecurso $agendamiento): void
    {
        $agendamiento->loadMissing(['recurso', 'solicitante', 'creador']);
        $emails = $this->emailsAdministradoresRecurso($agendamiento->recurso);
        if (empty($emails)) {
            return;
        }

        try {
            Mail::send('emails.agendamientos.solicitud-pendiente', ['agendamiento' => $agendamiento], function ($message) use ($emails, $agendamiento) {
                $message->to($emails)->subject('Nueva solicitud de sala pendiente: ' . $agendamiento->tipo_recurso_label);
            });
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function notificarSolicitanteResolucion(AgendamientoRecurso $agendamiento, bool $aprobada): void
    {
        $email = $agendamiento->solicitante_email ?: $agendamiento->solicitante?->email;
        if (! $email) {
            return;
        }

        try {
            Mail::send('emails.agendamientos.solicitud-resuelta', ['agendamiento' => $agendamiento, 'aprobada' => $aprobada], function ($message) use ($email, $aprobada, $agendamiento) {
                $message->to($email)->subject(($aprobada ? 'Solicitud de sala aprobada: ' : 'Solicitud de sala rechazada: ') . $agendamiento->tipo_recurso_label);
            });
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
