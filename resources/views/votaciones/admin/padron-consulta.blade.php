@extends('layouts.app')

@push('styles')
    @vite(['resources/css/votaciones-admin.css', 'resources/css/consulta-votacion.css'])
@endpush

@section('content')
<div class="cv-shell">
    <header class="cv-panel cv-page-header mb-4">
        <div><span class="cv-eyebrow"><i class="bi bi-person-vcard" aria-hidden="true"></i> Votaciones · Administración</span><h1>Padrón y locales de votación</h1><p class="mb-0">Carga las asignaciones y publica la consulta sin inicio de sesión.</p></div>
        <a class="btn cv-secondary" href="{{ route('votaciones.admin.jornadas.create') }}"><i class="bi bi-plus-circle" aria-hidden="true"></i> Nueva jornada</a>
    </header>
    @include('votaciones.partials.admin-nav')
    @include('votaciones.partials.alertas')

    <section class="cv-panel mb-4" aria-labelledby="jornadas-titulo">
        <h2 id="jornadas-titulo">1. Selecciona una jornada</h2>
        <p class="text-secondary">Una jornada puede incluir ambos procesos con el mismo padrón. Para padrones distintos, crea jornadas separadas.</p>
        <div class="table-responsive"><table class="table cv-table align-middle mb-0"><thead><tr><th scope="col">Jornada y procesos</th><th scope="col">Registros</th><th scope="col">Consulta pública</th><th scope="col">Acción</th></tr></thead><tbody>
            @forelse($jornadas as $j)
                @php($visible = $j->consulta_publica && $j->estado !== 'suspendida' && $j->procesos->contains('activo', true))
                <tr @class(['cv-selected-row' => $jornada?->id === $j->id])><td><strong>{{ $j->nombre }}</strong><small class="d-block text-secondary">{{ $j->procesos->pluck('nombre')->join(' · ') }}</small></td><td>{{ number_format($j->padron_consulta_count, 0, ',', '.') }}</td><td><span class="cv-badge {{ $visible ? '' : 'cv-badge-muted' }}">{{ $visible ? 'Publicada' : 'Sin publicar' }}</span></td><td><a class="btn cv-secondary" href="{{ route('votaciones.admin.padrones.index', ['jornada' => $j->slug, 'page' => $jornadas->currentPage()]) }}">{{ $jornada?->id === $j->id ? 'Seleccionada' : 'Configurar' }}</a></td></tr>
            @empty
                <tr><td colspan="4" class="text-center py-4">Crea una jornada para cargar su padrón y locales.</td></tr>
            @endforelse
        </tbody></table></div>
        <div class="mt-3">{{ $jornadas->links() }}</div>
    </section>

    @if($jornada)
        @php($procesosActivos = $jornada->procesos->contains('activo', true))
        <div class="cv-admin-grid">
            <section class="cv-panel" aria-labelledby="carga-titulo">
                <span class="cv-eyebrow">{{ $jornada->nombre }}</span><h2 id="carga-titulo">2. Carga el padrón y los locales</h2>
                <p class="text-secondary">Un Excel .xlsx con las hojas <strong>PADRON VOTACIÓN</strong> y <strong>LOCALES VOTACION</strong>. El día y la hora se obtienen de la combinación de RBD de origen y RBD del local.</p>
                <form method="POST" enctype="multipart/form-data" action="{{ route('votaciones.admin.padrones.importar', $jornada) }}">
                    @csrf
                    <div class="mb-3"><label class="form-label" for="archivo">Archivo de padrón y locales</label><input class="form-control" type="file" id="archivo" name="archivo" accept=".xlsx" required aria-describedby="archivo-ayuda"><div class="form-text" id="archivo-ayuda">Hasta 10 MB y 20.000 filas de padrón. Las fórmulas deben convertirse a valores.</div></div>
                    @if($jornada->padron_consulta_count)
                        <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="confirmar" name="confirmar" value="1" required><label class="form-check-label" for="confirmar">Confirmo reemplazar el padrón y los locales de esta jornada. La consulta quedará sin publicar hasta que la revise.</label></div>
                    @endif
                    <button class="btn cv-primary" type="submit"><i class="bi bi-upload" aria-hidden="true"></i> Validar e importar Excel</button>
                </form>
                <details class="mt-4"><summary class="fw-bold">Columnas requeridas</summary><div class="cv-column-help mt-3"><strong>PADRON VOTACIÓN</strong><p>{{ implode(', ', \App\Services\Votaciones\PadronConsultaExcel::PADRON) }}</p><strong>LOCALES VOTACION</strong><p class="mb-0">{{ implode(', ', \App\Services\Votaciones\PadronConsultaExcel::LOCALES) }}</p></div></details>
            </section>
            <section class="cv-panel" aria-labelledby="publicacion-titulo">
                <h2 id="publicacion-titulo">3. Publica la consulta</h2><p class="text-secondary">Revisa los datos antes de habilitar la búsqueda por RUT.</p>
                <dl class="cv-admin-summary"><div><dt>Registros de padrón</dt><dd>{{ number_format($jornada->padron_consulta_count, 0, ',', '.') }}</dd></div><div><dt>Asignaciones de local y horario</dt><dd>{{ $jornada->locales_consulta_count }}</dd></div><div><dt>Estado de la consulta</dt><dd>{{ $jornada->consulta_publica && $jornada->estado !== 'suspendida' && $procesosActivos ? 'Publicada' : 'Sin publicar' }}</dd></div></dl>
                @if($jornada->estado === 'suspendida')<p class="cv-inline-note">Esta jornada está suspendida y su consulta no se muestra públicamente.</p>@endif
                @if(!$procesosActivos)<p class="cv-inline-note">La jornada necesita al menos un proceso activo para mostrarse en la consulta.</p>@endif
                <form method="POST" action="{{ route('votaciones.admin.padrones.publicacion', $jornada) }}" class="mb-3">@csrf @method('PATCH')<input type="hidden" name="publicar" value="{{ $jornada->consulta_publica ? '0' : '1' }}"><button class="btn {{ $jornada->consulta_publica ? 'cv-secondary' : 'cv-primary' }}" @disabled(!$jornada->consulta_publica && (!$jornada->padron_consulta_count || $jornada->estado === 'suspendida' || !$procesosActivos))>{{ $jornada->consulta_publica ? 'Retirar consulta pública' : 'Publicar consulta por RUT' }}</button></form>
                <label class="form-label" for="enlace-publico">Enlace para compartir</label><input class="form-control mb-3" id="enlace-publico" readonly value="{{ route('public.consulta-votacion.index', ['jornada' => $jornada->slug]) }}">
                <a class="btn cv-secondary" href="{{ route('public.consulta-votacion.index', ['jornada' => $jornada->slug]) }}" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> Abrir consulta pública</a>
            </section>
        </div>
        @if($diagnostico = session('diagnostico_padron'))
            <section class="cv-panel mt-4" aria-labelledby="diagnostico-titulo" role="status">
                <h2 id="diagnostico-titulo">Resultado de la validación</h2>
                <p>{{ $diagnostico['resumen']['filas_padron'] }} filas de padrón · {{ $diagnostico['resumen']['filas_locales'] }} filas de locales · <strong>{{ $diagnostico['resumen']['errores'] }} observaciones</strong>.</p>
                @if($diagnostico['resumen']['errores'])<div class="alert cv-alert-error">No se modificó el padrón existente. Corrige las filas indicadas y vuelve a cargar el archivo.</div><ul class="cv-diagnostic-list">@foreach($diagnostico['errores'] as $observacion)<li>{{ $observacion }}</li>@endforeach</ul>@if($diagnostico['resumen']['errores'] > 100)<p>Se muestran las primeras 100 observaciones.</p>@endif @endif
                @if($diagnostico['resumen']['sin_grupo'])<p class="cv-inline-note">{{ $diagnostico['resumen']['sin_grupo'] }} registros válidos no tienen grupo; se mostrará «Por confirmar».</p>@endif
                @if($diagnostico['resumen']['duplicados_padron'] || $diagnostico['resumen']['duplicados_locales'])<p>Duplicados omitidos: {{ $diagnostico['resumen']['duplicados_padron'] }} filas idénticas de padrón y {{ $diagnostico['resumen']['duplicados_locales'] }} filas de locales con el mismo RBD de origen, destino, día y hora.</p>@endif
                @if($diagnostico['resumen']['fechas_mes_primero'])<p class="mb-0 text-secondary">Se detectaron fechas MM/DD/AAAA. Se convirtieron al formato de presentación chileno. Usa AAAA-MM-DD para evitar ambigüedad.</p>@endif
            </section>
        @endif
        <section class="cv-panel mt-4" aria-labelledby="locales-titulo"><h2 id="locales-titulo">Locales y horarios cargados</h2><p class="text-secondary">Comprueba el destino asignado a cada establecimiento de origen.</p><div class="table-responsive"><table class="table cv-table align-middle mb-0"><thead><tr><th scope="col">Origen</th><th scope="col">Local de votación</th><th scope="col">Día</th><th scope="col">Inicio</th></tr></thead><tbody>@forelse($locales as $local)<tr><td><strong>{{ $local->establecimiento }}</strong><small class="d-block text-secondary">RBD {{ $local->rbd_origen }}</small></td><td><strong>{{ $local->lugar_votacion }}</strong><small class="d-block text-secondary">RBD {{ $local->rbd_local }}</small></td><td class="text-nowrap">{{ $local->dia->format('d-m-Y') }}</td><td>{{ substr($local->hora_inicio, 0, 5) }} hrs.</td></tr>@empty<tr><td colspan="4" class="text-center py-4">Aún no hay locales cargados para esta jornada.</td></tr>@endforelse</tbody></table></div></section>
    @endif
</div>
@endsection
