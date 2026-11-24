@extends('layouts.app')

@section('content')
<x-postulant-page :title="$job->exists ? 'Editar cargo' : 'Agregar cargo'" icon="bi-briefcase" description="Indica el área y la formación que acreditan este cargo.">
    <x-slot:actions>
        <a href="{{ route('postulant.profile.cargos.index') }}" class="slep-listing__button slep-listing__button--secondary"><i class="bi bi-arrow-left" aria-hidden="true"></i> Volver a mis cargos</a>
    </x-slot:actions>
    <section class="slep-listing__panel" aria-labelledby="cargo-form-heading">
        <div class="slep-listing__panel-header"><h2 id="cargo-form-heading">Datos del cargo</h2></div>
        <form method="POST" action="{{ $job->exists ? route('postulant.profile.cargos.update', $job) : route('postulant.profile.cargos.store') }}" class="slep-listing__filters" data-slep-saving="Guardando…">
            @csrf
            @if ($job->exists)@method('PUT')@endif
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label for="job-area" class="form-label">Área de desempeño</label>
                    <select id="job-area" name="area_desempeno_id" class="form-select @error('area_desempeno_id') is-invalid @enderror" data-slep-select="search" @error('area_desempeno_id') aria-describedby="job-area-error" @enderror required>
                        <option value="">Selecciona un área</option>
                        @foreach ($areas as $estamento => $choices)
                            <optgroup label="{{ $estamento === 'docente' ? 'Docente' : 'Asistente de la Educación' }}">
                                @foreach ($choices as $area)
                                    <option value="{{ $area->id }}" @selected((string) old('area_desempeno_id', $job->area_desempeno_id) === (string) $area->id)>{{ $area->nombre }}{{ $area->activo ? '' : ' (inactiva)' }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    @error('area_desempeno_id')<div id="job-area-error" class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-6">
                    <label for="job-nivel" class="form-label">Nivel de estudios</label>
                    <select id="job-nivel" name="nivel_estudios" class="form-select @error('nivel_estudios') is-invalid @enderror" @error('nivel_estudios') aria-describedby="job-nivel-error" @enderror required>
                        <option value="">Selecciona un nivel</option>
                        @foreach (\App\Models\PostulantJobProfile::EDUCATION_LEVELS as $level)
                            <option @selected(old('nivel_estudios', $job->nivel_estudios) === $level)>{{ $level }}</option>
                        @endforeach
                    </select>
                    @error('nivel_estudios')<div id="job-nivel-error" class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                @foreach (['titulo' => ['Título profesional o técnico', 200, 'Obligatorio para nivel técnico superior o universitario.'], 'mencion' => ['Mención', 255, 'Obligatoria para Educación Diferencial.'], 'especialidad_tp' => ['Especialidad técnico-profesional', 150, 'Obligatoria para Docente Técnico Profesional.']] as $field => [$label, $max, $hint])
                    <div class="col-12 col-md-6">
                        <label for="job-{{ $field }}" class="form-label">{{ $label }}</label>
                        <input id="job-{{ $field }}" name="{{ $field }}" maxlength="{{ $max }}" value="{{ old($field, $job->{$field}) }}" class="form-control @error($field) is-invalid @enderror" aria-describedby="job-{{ $field }}-help @error($field) job-{{ $field }}-error @enderror">
                        <div class="form-text" id="job-{{ $field }}-help">{{ $hint }}</div>
                        @error($field)<div id="job-{{ $field }}-error" class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                @endforeach
                <div class="col-12 col-md-6">
                    <label for="job-experience" class="form-label">Años de experiencia en el cargo</label>
                    <input id="job-experience" type="number" min="0" max="80" name="anios_experiencia" value="{{ old('anios_experiencia', $job->anios_experiencia) }}" class="form-control @error('anios_experiencia') is-invalid @enderror" @error('anios_experiencia') aria-describedby="job-experience-error" @enderror required>
                    @error('anios_experiencia')<div id="job-experience-error" class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="form-check form-switch mt-4">
                <input type="hidden" name="activo" value="0">
                <input type="checkbox" id="job-active" class="form-check-input" role="switch" name="activo" value="1" @checked(old('activo', $job->activo)) aria-describedby="job-active-help">
                <label for="job-active" class="form-check-label">Cargo activo para nuevas postulaciones</label>
                <div class="form-text" id="job-active-help">Desactivar el cargo conserva las postulaciones ya enviadas.</div>
            </div>
            <div class="slep-listing__filter-actions">
                <button class="slep-listing__button slep-listing__button--primary" type="submit"><i class="bi bi-check-lg" aria-hidden="true"></i> <span data-slep-save-label>Guardar cargo</span></button>
            </div>
        </form>
    </section>
</x-postulant-page>
@endsection
