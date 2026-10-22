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
        <div><span class="cv-eyebrow"><i class="bi bi-bar-chart" aria-hidden="true"></i> Votaciones · Administración</span><h1>Resultados y consolidación</h1><p class="mb-0">Registra varios días, consolida todos sus votos y publica una sola versión de resultados.</p></div>
        <a class="btn cv-secondary" href="{{ route('public.resultados-votacion.index', $jornada ? ['jornada' => $jornada->slug] : []) }}" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> Vista pública</a>
    </header>
    @include('votaciones.partials.admin-nav')
    @include('votaciones.partials.alertas')

    <nav class="vr-day-nav mb-4" aria-label="Pasos del escrutinio"><a class="btn cv-secondary" href="#registro">1. Registrar días</a><a class="btn cv-secondary" href="#consolidado">2. Consolidar todos los días</a><a class="btn cv-secondary" href="#publicacion-consolidado">3. Publicar consolidado</a></nav>
    <section class="cv-panel mb-4" aria-labelledby="seleccion-titulo" id="registro">
        <h2 id="seleccion-titulo">1. Registra los resultados de cada día</h2><p class="text-secondary">Selecciona una fecha, guarda sus votos y repite con las demás fechas. Cada día queda registrado por separado sin publicarse.</p>
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
            <div class="vr-section-heading mb-3"><div><span class="cv-eyebrow">{{ $jornada->nombre }}</span><h2 id="escrutinio-titulo">Escrutinio del {{ \Carbon\Carbon::parse($fecha)->format('d-m-Y') }}</h2><p class="text-secondary mb-0">Totales de todos los locales del día. Registra cero cuando una opción no tenga votos.</p></div><span class="cv-badge {{ $resultado ? '' : 'cv-badge-muted' }}">{{ $resultado ? 'Día guardado' : 'Sin registrar' }}</span></div>
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
                    <div class="vr-actions mt-3"><a class="btn cv-secondary" href="#registro">Registrar otra fecha</a><span class="text-secondary small" data-vr-dirty hidden role="status">Hay cambios sin guardar. Guarda antes de consolidar o publicar.</span><button class="btn cv-primary" type="submit"><i class="bi bi-save" aria-hidden="true"></i> Guardar escrutinio del día</button></div>
                </section>
            </form>
        </section>
    @endif

    @if($dias->isNotEmpty())
        <section class="cv-panel mb-4" aria-labelledby="dias-titulo">
            <h2 id="dias-titulo">Días registrados que se van a consolidar</h2><p class="text-secondary">Se incluyen los {{ $dias->count() }} días guardados de esta jornada, sin necesidad de publicarlos individualmente.</p>
            <div class="table-responsive"><table class="table cv-table align-middle mb-0"><caption>Totales de papeletas por día; cada columna corresponde a una votación distinta.</caption><thead><tr><th scope="col">Día</th>@foreach($resumen as $papeleta)<th scope="col">{{ $papeleta['titulo'] }}</th>@endforeach<th scope="col">Acción</th></tr></thead><tbody>@foreach($dias as $dia)<tr><th scope="row">{{ $dia->fecha->format('d-m-Y') }}</th>@foreach($resumen as $clave => $papeleta)<td>{{ isset($dia->conteos[$clave]) ? number_format(array_sum($dia->conteos[$clave]), 0, ',', '.') : '—' }}</td>@endforeach<td><a class="btn cv-secondary" href="{{ route('votaciones.admin.resultados.index', ['jornada' => $jornada->slug, 'fecha' => $dia->fecha->toDateString()]) }}">Editar día</a></td></tr>@endforeach</tbody><tfoot><tr><th scope="row">Consolidado de todos los días</th>@foreach($resumen as $papeleta)<td class="fw-bold">{{ number_format($papeleta['emitidos'], 0, ',', '.') }}</td>@endforeach<td></td></tr></tfoot></table></div>
        </section>
        <section id="consolidado" aria-labelledby="consolidado-titulo">
            <div class="cv-panel mb-4">
                <div class="vr-section-heading"><div><span class="cv-eyebrow">Revisión general</span><h2 id="consolidado-titulo">2. Consolida todos los días registrados</h2><p class="text-secondary mb-0">Las siguientes sumas incluyen todos los días de la tabla anterior. Revisa cada opción antes de preparar la publicación.</p></div><span class="cv-badge {{ $consolidadoVigente ? '' : 'cv-badge-muted' }}">{{ $consolidadoVigente ? 'Consolidado preparado · v'.$consolidado->version : 'Pendiente de consolidar' }}</span></div>
                <form class="mt-3" method="POST" action="{{ route('votaciones.admin.resultados.consolidar', $jornada) }}">@csrf<input type="hidden" name="huella" value="{{ $huella }}"><input type="hidden" name="fecha" value="{{ $fecha }}"><button type="submit" class="btn {{ $consolidadoVigente ? 'cv-secondary' : 'cv-primary' }}" data-vr-publish><i class="bi bi-calculator" aria-hidden="true"></i> Consolidar todos los días</button></form>
            </div>
            @include('votaciones.partials.resumen-papeletas', ['resumen' => $resumen, 'prefijo' => 'consolidado'])
        </section>
        <section class="cv-panel mt-4" aria-labelledby="publicacion-titulo" id="publicacion-consolidado">
            <h2 id="publicacion-titulo">3. Publica el consolidado</h2><p class="text-secondary">Una sola publicación para todos los días incluidos. Las observaciones internas permanecen privadas.</p>
            @if($consolidado && !$consolidadoVigente)<p class="cv-inline-note">Hay días nuevos o correcciones pendientes. Vuelve a consolidar todos los días para publicar una versión actualizada.</p>@endif
            @if($publicado)<p><span class="cv-badge">Versión {{ $publicado->version }} publicada</span> <span class="text-secondary small">{{ count($publicado->dias) }} días incluidos · {{ $publicado->publicado_at->timezone(config('votaciones.timezone'))->format('d-m-Y H:i') }}</span></p>@endif
            @if($publicado && !hash_equals($huella, $publicado->huella))<p class="cv-inline-note">El público sigue viendo la última versión publicada. Las nuevas cargas y correcciones aparecerán después de consolidar y publicar una nueva versión.</p>@endif
            <div class="vr-actions justify-content-start">
                @if($consolidadoVigente && (!$consolidado->publicado_at || $consolidado->retirado_at))<form method="POST" action="{{ route('votaciones.admin.resultados.consolidados.publicacion', [$jornada, $consolidado]) }}">@csrf @method('PATCH')<input type="hidden" name="publicar" value="1"><button type="submit" class="btn cv-primary" data-vr-publish @disabled($jornada->estado === 'suspendida')><i class="bi bi-globe" aria-hidden="true"></i> Publicar consolidado</button></form>@elseif(!$consolidadoVigente)<p class="text-secondary mb-0">Primero guarda todos los días y pulsa «Consolidar todos los días».</p>@endif
                @if($publicado)<form method="POST" action="{{ route('votaciones.admin.resultados.consolidados.publicacion', [$jornada, $publicado]) }}">@csrf @method('PATCH')<input type="hidden" name="publicar" value="0"><button type="submit" class="btn cv-secondary" data-vr-publish><i class="bi bi-eye-slash" aria-hidden="true"></i> Retirar consolidado publicado</button></form>@endif
            </div>
            @if($jornada->estado === 'suspendida')<p class="cv-inline-note mb-0">La jornada está suspendida. Sus resultados no se muestran públicamente.</p>@endif
            <label class="form-label mt-4" for="enlace-resultados">Enlace de resultados para compartir</label><input id="enlace-resultados" class="form-control" readonly value="{{ route('public.resultados-votacion.index', ['jornada' => $jornada->slug]) }}"><p class="form-text mb-0">El consolidado será visible después de pulsar «Publicar consolidado».</p>
        </section>
    @endif
</div>
@endsection
