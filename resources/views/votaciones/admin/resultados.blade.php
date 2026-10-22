@extends('layouts.app')

@push('styles')
    @vite(['resources/css/votaciones-admin.css', 'resources/css/consulta-votacion.css', 'resources/css/votaciones-resultados.css'])
@endpush
@push('scripts')
    @vite('resources/js/votaciones-resultados.js')
@endpush

@section('content')
<div class="cv-shell vr-shell">
    <header class="cv-panel cv-page-header mb-4">
        <div><span class="cv-eyebrow"><i class="bi bi-bar-chart" aria-hidden="true"></i> Votaciones · Administración</span><h1>Resultados diarios</h1><p class="mb-0">Ingresa el escrutinio general de cada día y publícalo después de revisarlo.</p></div>
        <a class="btn cv-secondary" href="{{ route('public.resultados-votacion.index') }}" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> Vista pública</a>
    </header>
    @include('votaciones.partials.admin-nav')
    @include('votaciones.partials.alertas')

    <section class="cv-panel mb-4" aria-labelledby="seleccion-titulo">
        <h2 id="seleccion-titulo">Selecciona la jornada y el día</h2>
        <form method="GET" action="{{ route('votaciones.admin.resultados.index') }}" class="row g-3 align-items-end">
            <div class="col-lg-6"><label class="form-label" for="jornada">Jornada</label><select id="jornada" class="form-select" name="jornada" required>@foreach($jornadas as $item)<option value="{{ $item->slug }}" @selected($jornada?->id === $item->id)>{{ $item->nombre }}</option>@endforeach</select></div>
            <div class="col-lg-3 col-sm-7"><label class="form-label" for="fecha">Día de votación</label><input class="form-control" id="fecha" name="fecha" type="date" value="{{ $fecha }}" max="{{ now(config('votaciones.timezone'))->toDateString() }}" required></div>
            <div class="col-lg-3 col-sm-5"><button class="btn cv-secondary w-100" @disabled($jornadas->isEmpty())><i class="bi bi-calendar-check" aria-hidden="true"></i> Abrir día</button></div>
        </form>
    </section>

    @if(!$jornada)
        <section class="cv-panel cv-empty"><i class="bi bi-calendar-plus" aria-hidden="true"></i><h2>Aún no hay jornadas</h2><p>Crea una jornada antes de ingresar resultados.</p><a class="btn cv-primary mt-3" href="{{ route('votaciones.admin.jornadas.create') }}">Crear jornada</a></section>
    @elseif(!$papeletas)
        <section class="cv-panel cv-empty"><i class="bi bi-info-circle" aria-hidden="true"></i><h2>No hay papeletas configuradas</h2><p>Esta jornada debe incluir el proceso CCAF, Consulta de Mutualidades o ambos para registrar estos resultados.</p></section>
    @else
        <section class="mb-4" aria-labelledby="escrutinio-titulo">
            <div class="vr-section-heading mb-3"><div><span class="cv-eyebrow">{{ $jornada->nombre }}</span><h2 id="escrutinio-titulo">Escrutinio del {{ \Carbon\Carbon::parse($fecha)->format('d-m-Y') }}</h2><p class="text-secondary mb-0">Totales de todos los locales del día. Registra cero cuando una opción no tenga votos.</p></div><span class="cv-badge {{ $resultado?->publicado_at ? '' : 'cv-badge-muted' }}">{{ $resultado?->publicado_at ? 'Día publicado' : ($resultado ? 'Borrador guardado' : 'Sin registrar') }}</span></div>
            @if($resultado?->publicado_at)<p class="cv-inline-note">Al modificar y guardar este escrutinio, el día se retirará de la vista pública hasta que lo vuelvas a publicar.</p>@endif
            <form method="POST" action="{{ route('votaciones.admin.resultados.guardar', $jornada) }}" data-vr-form>
                @csrf
                <input type="hidden" name="fecha" value="{{ $fecha }}">
                <input type="hidden" name="version" value="{{ old('version', $resultado?->version ?? 0) }}">
                <div class="vr-ballots">
                    @foreach($papeletas as $clave => $papeleta)
                        <section class="cv-panel vr-ballot" aria-labelledby="papeleta-{{ $clave }}" data-vr-ballot>
                            <div class="vr-ballot-heading"><span class="cv-icon" aria-hidden="true"><i class="bi bi-ui-checks"></i></span><span class="cv-eyebrow mb-0">Papeleta {{ $loop->iteration }}</span></div>
                            <h3 id="papeleta-{{ $clave }}">{{ $papeleta['titulo'] }}</h3><p class="text-secondary vr-question">{{ $papeleta['pregunta'] }}</p>
                            @foreach($papeleta['campos'] as $opcion => $label)
                                <div class="vr-count-row {{ in_array($opcion, ['nulos', 'blancos']) ? 'vr-count-secondary' : '' }}">
                                    <label for="votos-{{ $clave }}-{{ $opcion }}">{{ $label }}</label>
                                    <div><input @class(['form-control', 'is-invalid' => $errors->has("conteos.$clave.$opcion")]) id="votos-{{ $clave }}-{{ $opcion }}" name="conteos[{{ $clave }}][{{ $opcion }}]" type="number" inputmode="numeric" min="0" max="1000000" step="1" placeholder="0" required value="{{ old("conteos.$clave.$opcion", $resultado?->conteos[$clave][$opcion] ?? '') }}" data-vr-count data-valid-vote="{{ array_key_exists($opcion, $papeleta['opciones']) ? '1' : '0' }}" @if($errors->has("conteos.$clave.$opcion")) aria-invalid="true" aria-describedby="error-{{ $clave }}-{{ $opcion }}" @endif>@error("conteos.$clave.$opcion")<div class="invalid-feedback" id="error-{{ $clave }}-{{ $opcion }}">{{ $message }}</div>@enderror</div>
                                </div>
                            @endforeach
                            <dl class="vr-totals mb-0" aria-live="polite"><div><dt>Votos válidos</dt><dd data-vr-total="validos">—</dd></div><div><dt>Total de papeletas</dt><dd data-vr-total="emitidos">—</dd></div></dl>
                        </section>
                    @endforeach
                </div>
                <section class="cv-panel mt-4" aria-labelledby="revision-titulo">
                    <h3 id="revision-titulo">Revisión y guardado</h3><p class="text-secondary">Cada papeleta tiene su propio total. Guardar no publica los resultados.</p>
                    <label class="form-label" for="observacion-interna">Observación interna <span class="fw-normal text-secondary">(opcional)</span></label><textarea class="form-control" id="observacion-interna" name="observacion_interna" rows="2" maxlength="2000" aria-describedby="observacion-ayuda">{{ old('observacion_interna', $resultado?->observacion_interna) }}</textarea><div class="form-text" id="observacion-ayuda">Sólo visible en administración. Puedes indicar antecedentes de una corrección.</div>
                    <div class="vr-actions mt-3"><span class="text-secondary small" data-vr-dirty hidden role="status">Hay cambios sin guardar. Guarda antes de publicar o retirar el día.</span><button class="btn cv-primary" type="submit"><i class="bi bi-save" aria-hidden="true"></i> Guardar escrutinio del día</button></div>
                </section>
            </form>
            @if($resultado)
                <section class="cv-panel mt-4" aria-labelledby="publicacion-titulo">
                    <h3 id="publicacion-titulo">Publicación del día</h3><p class="text-secondary">Esta acción usa los datos guardados. Revisa las cifras antes de publicarlas. La observación interna nunca se publica.</p>
                    <form method="POST" action="{{ route('votaciones.admin.resultados.publicacion', [$jornada, $resultado]) }}">@csrf @method('PATCH')<input type="hidden" name="version" value="{{ $resultado->version }}"><input type="hidden" name="publicar" value="{{ $resultado->publicado_at ? '0' : '1' }}"><button type="submit" class="btn {{ $resultado->publicado_at ? 'cv-secondary' : 'cv-primary' }}" data-vr-publish @disabled(!$resultado->publicado_at && $jornada->estado === 'suspendida')><i class="bi bi-{{ $resultado->publicado_at ? 'eye-slash' : 'globe' }}" aria-hidden="true"></i> {{ $resultado->publicado_at ? 'Retirar publicación del día' : 'Publicar día' }}</button></form>
                    @if($jornada->estado === 'suspendida')<p class="cv-inline-note mb-0">La jornada está suspendida. Sus resultados no se muestran públicamente.</p>@endif
                </section>
            @endif
        </section>
    @endif

    @if($dias->isNotEmpty())
        <section class="cv-panel mb-4" aria-labelledby="dias-titulo">
            <h2 id="dias-titulo">Días registrados</h2><p class="text-secondary">Un registro general por jornada y día. Abre un día para revisarlo o corregirlo.</p>
            <div class="table-responsive"><table class="table cv-table align-middle mb-0"><thead><tr><th scope="col">Día</th><th scope="col">Estado</th><th scope="col">Último guardado</th><th scope="col">Acción</th></tr></thead><tbody>@foreach($dias as $dia)<tr><th scope="row">{{ $dia->fecha->format('d-m-Y') }}</th><td><span class="cv-badge {{ $dia->publicado_at ? '' : 'cv-badge-muted' }}">{{ $dia->publicado_at ? 'Publicado' : 'Borrador' }}</span></td><td>{{ $dia->updated_at->timezone(config('votaciones.timezone'))->format('d-m-Y H:i') }}</td><td><a class="btn cv-secondary" href="{{ route('votaciones.admin.resultados.index', ['jornada' => $jornada->slug, 'fecha' => $dia->fecha->toDateString()]) }}">Abrir día</a></td></tr>@endforeach</tbody></table></div>
        </section>
        <section class="cv-panel" aria-labelledby="acumulado-titulo"><h2 id="acumulado-titulo">Acumulado interno</h2><p class="text-secondary">Incluye los {{ $dias->count() }} días guardados, tanto borradores como publicados. El acumulado público sólo incluye días publicados.</p><div class="vr-summary">@foreach($resumen as $papeleta)<div><span>{{ $papeleta['titulo'] }}</span><strong>{{ number_format($papeleta['emitidos'], 0, ',', '.') }}</strong><small>Total de papeletas</small></div>@endforeach</div><label class="form-label mt-4" for="enlace-resultados">Enlace de resultados para compartir</label><input id="enlace-resultados" class="form-control" readonly value="{{ route('public.resultados-votacion.index', ['jornada' => $jornada->slug]) }}"><p class="form-text mb-0">El enlace estará disponible cuando publiques al menos un día.</p></section>
    @endif
</div>
@endsection
