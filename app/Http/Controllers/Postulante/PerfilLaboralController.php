<?php

namespace App\Http\Controllers\Postulante;

use App\Http\Controllers\Controller;
use App\Models\AreaDesempeno;
use App\Models\PostulantJobProfile;
use App\Models\PostulantProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PerfilLaboralController extends Controller
{
    public function index(Request $request)
    {
        $profile = $request->user()->postulantProfile;
        $profile?->loadMissing(['areaDesempeno', 'jobProfiles.areaDesempeno']);

        return view('postulant.profile.cargos.index', compact('profile'));
    }

    public function create(Request $request)
    {
        $this->profile($request);

        return $this->form(new PostulantJobProfile(['activo' => true]));
    }

    public function edit(Request $request, int $cargo)
    {
        return $this->form($this->profile($request)->jobProfiles()->findOrFail($cargo));
    }

    public function store(Request $request)
    {
        return $this->save($request);
    }

    public function update(Request $request, int $cargo)
    {
        return $this->save($request, $cargo);
    }

    private function profile(Request $request): PostulantProfile
    {
        $profile = $request->user()->postulantProfile;
        abort_unless($profile && filled($profile->estamento), 409, 'Guarda tu perfil principal antes de agregar cargos.');

        return $profile;
    }

    private function form(PostulantJobProfile $job)
    {
        $areas = AreaDesempeno::query()->where(fn ($query) => $query->where('activo', true)
            ->when($job->exists, fn ($query) => $query->orWhereKey($job->area_desempeno_id)))
            ->orderBy('nombre')->get()->groupBy('estamento');

        return view('postulant.profile.cargos.form', compact('job', 'areas'));
    }

    private function save(Request $request, ?int $id = null)
    {
        $profile = $this->profile($request);
        $existing = $id ? $profile->jobProfiles()->findOrFail($id) : null;
        $area = AreaDesempeno::find($request->integer('area_desempeno_id'));
        $slug = $area ? ($area->slug ?: Str::slug($area->nombre, '_')) : '';
        $differential = in_array($slug, ['educadora_diferencial', 'educador_a_diferencial', 'educador_diferencial'], true);
        $technical = in_array($slug, ['docente_tecnico_profesional', 'docente_técnico_profesional'], true);
        $religion = in_array($slug, ['religion_catolica', 'religion_evangelica'], true);
        $levels = match (true) {
            $area?->estamento !== 'docente', $religion => PostulantJobProfile::EDUCATION_LEVELS,
            $technical => array_slice(PostulantJobProfile::EDUCATION_LEVELS, 2),
            default => ['Universitaria'],
        };

        $data = $request->validate([
            'area_desempeno_id' => ['required', 'integer', function ($attribute, $value, $fail) use ($area, $existing, $request) {
                if (! $area || (! $area->activo && ((int) $existing?->area_desempeno_id !== (int) $value || $request->boolean('activo')))) {
                    $fail('Selecciona un área de desempeño activa.');
                }
            }],
            'nivel_estudios' => ['required', Rule::in($levels)],
            'titulo' => ['nullable', 'string', 'max:200', Rule::requiredIf(in_array($request->input('nivel_estudios'), ['Técnico Nivel Superior', 'Universitaria'], true))],
            'mencion' => ['nullable', 'string', 'max:255', Rule::requiredIf($differential)],
            'especialidad_tp' => ['nullable', 'string', 'max:150', Rule::requiredIf($technical)],
            'anios_experiencia' => ['required', 'integer', 'min:0', 'max:80'],
            'activo' => ['required', 'boolean'],
        ]);
        $data['estamento'] = $area->estamento;
        $data['cargos_funcion'] = $area->estamento === 'asistente' ? $area->nombre : null;

        $profile->getConnection()->transaction(function () use ($profile, $id, $data) {
            User::on($profile->getConnectionName())->whereKey($profile->user_id)->lockForUpdate()->firstOrFail();
            $jobs = $profile->jobProfiles();
            $same = $jobs->get()->contains(function (PostulantJobProfile $job) use ($data, $id) {
                return $job->id !== $id && $job->activo && (bool) $data['activo']
                    && (int) $job->area_desempeno_id === (int) $data['area_desempeno_id']
                    && mb_strtolower($job->titulo ?? '') === mb_strtolower($data['titulo'] ?? '')
                    && mb_strtolower($job->mencion ?? '') === mb_strtolower($data['mencion'] ?? '')
                    && mb_strtolower($job->especialidad_tp ?? '') === mb_strtolower($data['especialidad_tp'] ?? '');
            });
            if ($same) {
                throw ValidationException::withMessages(['area_desempeno_id' => 'Ya tienes un cargo activo con esa área y acreditación.']);
            }
            $job = $id ? $jobs->findOrFail($id) : $jobs->make();
            $job->fill($data)->save();
        }, 3);

        return redirect()->route('postulant.profile.cargos.index')->with('status', 'Cargo guardado.');
    }
}
