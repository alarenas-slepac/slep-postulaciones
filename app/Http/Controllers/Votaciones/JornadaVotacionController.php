<?php

namespace App\Http\Controllers\Votaciones;

use App\Http\Controllers\Controller;
use App\Http\Requests\Votaciones\GuardarJornadaRequest;
use App\Models\Establecimiento;
use App\Models\JornadaVotacion;
use App\Models\ProcesoVotacion;
use App\Models\User;
use App\Models\VisitaVotacion;
use App\Services\Votaciones\BitacoraVotacionService;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class JornadaVotacionController extends Controller
{
    public function index(): View
    {
        return view('votaciones.admin.index', ['jornadas' => JornadaVotacion::withCount('grupos')->latest('fecha')->paginate(20)]);
    }

    public function create(): View
    {
        return view('votaciones.admin.form', ['jornada' => new JornadaVotacion, 'procesos' => ProcesoVotacion::where('activo', true)->get()]);
    }

    public function store(GuardarJornadaRequest $request, BitacoraVotacionService $bitacora): RedirectResponse
    {
        $jornada = DB::transaction(function () use ($request, $bitacora) {
            $data = $request->safe()->except('procesos');
            $data += ['estado' => 'borrador', 'creada_por' => $request->user()->id, 'actualizada_por' => $request->user()->id];
            $j = JornadaVotacion::create($data);
            $j->procesos()->sync($request->validated('procesos'));
            $bitacora->registrar($j, 'jornada_creada', 'Se creó la jornada.', $request->user());

            return $j;
        });

        return redirect()->route('votaciones.admin.jornadas.show', $jornada)->with('success', 'Jornada creada.');
    }

    public function edit(JornadaVotacion $jornada): View
    {
        abort_if($jornada->estado !== 'borrador', 422, 'Solo se editan jornadas en borrador.');
        $jornada->load('procesos');

        return view('votaciones.admin.form', ['jornada' => $jornada, 'procesos' => ProcesoVotacion::where('activo', true)->get()]);
    }

    public function update(GuardarJornadaRequest $request, JornadaVotacion $jornada, BitacoraVotacionService $bitacora): RedirectResponse
    {
        if ($jornada->estado !== 'borrador') {
            throw ValidationException::withMessages(['jornada' => 'Solo se editan jornadas en borrador.']);
        }
        DB::transaction(function () use ($request, $jornada, $bitacora) {
            $jornada->update($request->safe()->except('procesos') + ['actualizada_por' => $request->user()->id]);
            $jornada->procesos()->sync($request->validated('procesos'));
            $bitacora->registrar($jornada, 'jornada_actualizada', 'Se actualizó la configuración.', $request->user());
        });

        return redirect()->route('votaciones.admin.jornadas.show', $jornada)->with('success', 'Jornada actualizada.');
    }

    public function show(JornadaVotacion $jornada): View
    {
        $jornada->load(['procesos', 'grupos.encargado', 'grupos.miembros', 'grupos.rutas.establecimiento', 'grupos.rutas.visita', 'incidencias.grupo', 'bitacora.usuario']);
        $usuarios = User::permission('votaciones.operate-group')->orderBy('nombres')->orderBy('apellido_paterno')->get(['id', 'name', 'nombres', 'apellido_paterno', 'apellido_materno', 'email']);
        $establecimientos = Establecimiento::orderBy('comuna')->orderBy('nombre_establecimiento')->get(['id', 'rbd', 'nombre_establecimiento', 'comuna', 'latitud', 'longitud']);

        return view('votaciones.admin.show', compact('jornada', 'usuarios', 'establecimientos'));
    }

    public function publicar(Request $request, JornadaVotacion $jornada, BitacoraVotacionService $bitacora): RedirectResponse
    {
        abort_unless($request->user()->can('votaciones.manage-jornadas'), 403);
        $jornada->load('grupos.rutas.establecimiento');
        $errores = [];
        if ($jornada->procesos()->count() === 0) {
            $errores[] = 'Debe seleccionar al menos un proceso.';
        }
        if ($jornada->grupos->isEmpty()) {
            $errores[] = 'Debe crear al menos un grupo.';
        }
        foreach ($jornada->grupos as $g) {
            if (! $g->encargado_id) {
                $errores[] = "{$g->nombre} no tiene responsable.";
            }
            if ($g->rutas->isEmpty()) {
                $errores[] = "{$g->nombre} no tiene ruta.";
            }
            foreach ($g->rutas as $r) {
                if ($r->establecimiento->latitud === null || $r->establecimiento->longitud === null) {
                    $errores[] = "Faltan coordenadas para RBD {$r->establecimiento->rbd}.";
                }
            }
        }
        if ($errores) {
            throw ValidationException::withMessages(['publicacion' => $errores]);
        }
        $jornada->update(['estado' => 'publicada', 'publica' => true, 'publicada_at' => now(config('votaciones.timezone')), 'actualizada_por' => $request->user()->id]);
        $bitacora->registrar($jornada, 'jornada_publicada', 'Se publicó la jornada.', $request->user());

        return back()->with('success', 'Jornada publicada.');
    }

    public function suspender(Request $request, JornadaVotacion $jornada, BitacoraVotacionService $bitacora): RedirectResponse
    {
        abort_unless($request->user()->can('votaciones.manage-jornadas'), 403);
        $data = $request->validate(['motivo' => ['required', 'string', 'max:1000']]);
        $anterior = $jornada->estado;
        $jornada->update(['estado' => JornadaVotacion::SUSPENDIDA, 'publica' => false, 'actualizada_por' => $request->user()->id]);
        $bitacora->registrar($jornada, 'jornada_suspendida', 'Se suspendió la jornada.', $request->user(), metadata: ['motivo' => $data['motivo'], 'estado_anterior' => $anterior]);

        return back()->with('success', 'Jornada suspendida.');
    }

    public function corregir(Request $request, JornadaVotacion $jornada, BitacoraVotacionService $bitacora): RedirectResponse
    {
        abort_unless($request->user()->can('votaciones.admin'), 403);
        $data = $request->validate(['campo' => ['required', 'in:estado,iniciada_at,finalizada_at'], 'valor' => ['required', 'string', 'max:80'], 'motivo' => ['required', 'string', 'max:1000']]);
        $this->validarValorCorreccion($data['campo'], $data['valor'], ['borrador', 'publicada', 'en_curso', 'finalizada', 'suspendida']);
        $anterior = $jornada->{$data['campo']};
        $old = $anterior instanceof CarbonInterface ? $anterior->toIso8601String() : $anterior;
        $actualizacion = [$data['campo'] => $data['valor'], 'actualizada_por' => $request->user()->id];
        if ($data['campo'] === 'estado') {
            $actualizacion['publica'] = in_array($data['valor'], [JornadaVotacion::PUBLICADA, JornadaVotacion::EN_CURSO, JornadaVotacion::FINALIZADA], true);
        }
        $jornada->update($actualizacion);
        $bitacora->registrar($jornada, 'correccion_administrativa', "Se corrigió {$data['campo']}.", $request->user(), metadata: ['campo' => $data['campo'], 'anterior' => $old, 'nuevo' => $data['valor'], 'motivo' => $data['motivo']]);

        return back()->with('success', 'Corrección registrada.');
    }

    public function corregirVisita(Request $request, VisitaVotacion $visita, BitacoraVotacionService $bitacora): RedirectResponse
    {
        abort_unless($request->user()->can('votaciones.admin'), 403);
        $data = $request->validate(['campo' => ['required', 'in:estado,inicio_traslado_at,inicio_votacion_at,fin_votacion_at'], 'valor' => ['required', 'string', 'max:80'], 'motivo' => ['required', 'string', 'max:1000']]);
        $this->validarValorCorreccion($data['campo'], $data['valor'], ['pendiente', 'en_traslado', 'en_votacion', 'finalizada']);
        $visita->load('ruta.grupo.jornada');
        $anterior = $visita->{$data['campo']};
        $old = $anterior instanceof CarbonInterface ? $anterior->toIso8601String() : $anterior;
        $visita->update([$data['campo'] => $data['valor']]);
        $bitacora->registrar($visita->ruta->grupo->jornada, 'correccion_visita', "Se corrigió {$data['campo']} de una visita.", $request->user(), $visita->ruta->grupo, $visita->ruta, ['campo' => $data['campo'], 'anterior' => $old, 'nuevo' => $data['valor'], 'motivo' => $data['motivo']]);

        return back()->with('success', 'Visita corregida y registrada en bitácora.');
    }

    private function validarValorCorreccion(string $campo, string $valor, array $estados): void
    {
        if ($campo === 'estado' && ! in_array($valor, $estados, true)) {
            throw ValidationException::withMessages(['valor' => 'El estado indicado no es válido.']);
        }
        if ($campo !== 'estado' && strtotime($valor) === false) {
            throw ValidationException::withMessages(['valor' => 'Debe indicar una fecha y hora válidas.']);
        }
    }
}
