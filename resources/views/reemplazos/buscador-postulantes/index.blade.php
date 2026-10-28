@extends('layouts.app')

@push('styles')
    @vite('resources/js/slep-listados.js')
@endpush

@section('content')
@php
    $campo = function ($nombre, $defecto = '') use ($filters): string {
        $valor = old($nombre, $filters[$nombre] ?? $defecto);
        return is_scalar($valor) ? (string) $valor : (string) $defecto;
    };
    $areaActual = (int) $campo('area_desempeno_id');
    $mostrarTp = collect($areaTpIds ?? [])->contains($areaActual);
    $mostrarMencion = collect($areaDocenteIds ?? [])->contains($areaActual);
    $mencionActual = (string) $campo('mencion');
    $menciones = collect($mencionesDocentes ?? [])->when($mencionActual !== '', fn ($items) => $items->prepend($mencionActual))->unique();
@endphp
<div class="slep-listing" data-slep-listing>
    <header class="slep-listing__header">
        <div class="slep-listing__heading">
            <span class="slep-listing__icon"><i class="bi bi-person-vcard" aria-hidden="true"></i></span>
            <div>
                <p class="slep-listing__eyebrow">Reemplazos · Personas registradas</p>
                <h1>Buscador de postulantes y funcionarios</h1>
                <p class="slep-listing__muted mb-0">Consulta perfiles, documentación y antecedentes laborales para gestionar un reemplazo.</p>
            </div>
        </div>
        <a href="{{ route('reemplazos.index') }}" class="slep-listing__button slep-listing__button--secondary"><i class="bi bi-arrow-left" aria-hidden="true"></i> Volver a Reemplazos</a>
    </header>

    @if ($errors->any())
        <div class="slep-listing__notice slep-listing__notice--danger" role="alert" tabindex="-1" data-slep-errors>
            <strong><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> Revisa los filtros ingresados</strong>
            <ul class="mb-0 mt-2">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="slep-listing__panel" aria-labelledby="buscador-filtros-titulo">
        <div class="slep-listing__panel-header">
            <div><p class="slep-listing__eyebrow">Búsqueda y filtrado</p><h2 id="buscador-filtros-titulo"><i class="bi bi-funnel" aria-hidden="true"></i> Encontrar personas</h2></div>
            <span class="slep-listing__chip"><i class="bi bi-intersect" aria-hidden="true"></i> Filtros combinables</span>
        </div>
        <form method="GET" action="{{ route('reemplazos.buscador-postulantes.index') }}" class="slep-listing__filters" data-slep-filter-form novalidate autocomplete="off">
            <div class="row g-3 align-items-start">
                <div class="col-12 col-md-4 col-xl-3">
                    <label for="buscador-rut" class="form-label">RUT</label>
                    <input id="buscador-rut" type="text" name="rut" value="{{ $campo('rut') }}" maxlength="30" class="form-control @error('rut') is-invalid @enderror" placeholder="Ej.: 12.345.678-9" aria-describedby="buscador-rut-error" @error('rut') aria-invalid="true" @enderror>
                    <div id="buscador-rut-error" class="invalid-feedback">@error('rut'){{ $message }}@enderror</div>
                </div>
                <div class="col-12 col-md-8 col-xl-5">
                    <label for="buscador-nombre" class="form-label">Nombre completo</label>
                    <input id="buscador-nombre" type="text" name="nombre" value="{{ $campo('nombre') }}" maxlength="120" class="form-control @error('nombre') is-invalid @enderror" placeholder="Nombres o apellidos" aria-describedby="buscador-nombre-error" @error('nombre') aria-invalid="true" @enderror>
                    <div id="buscador-nombre-error" class="invalid-feedback">@error('nombre'){{ $message }}@enderror</div>
                </div>
                <div class="col-12 col-md-4 col-xl-4">
                    <label for="buscador-telefono" class="form-label">Teléfono</label>
                    <input id="buscador-telefono" type="tel" name="telefono" value="{{ $campo('telefono') }}" maxlength="30" class="form-control @error('telefono') is-invalid @enderror" placeholder="Número de contacto" aria-describedby="buscador-telefono-error" @error('telefono') aria-invalid="true" @enderror>
                    <div id="buscador-telefono-error" class="invalid-feedback">@error('telefono'){{ $message }}@enderror</div>
                </div>
                <div class="col-12 col-md-4">
                    <label for="buscador-comuna" class="form-label">Comuna de desempeño</label>
                    <select id="buscador-comuna" name="commune_id" class="form-select @error('commune_id') is-invalid @enderror" data-slep-select="search" aria-describedby="buscador-comuna-error" @error('commune_id') aria-invalid="true" @enderror>
                        <option value="">Todas las comunas</option>
                        @foreach ($communes as $comuna)<option value="{{ $comuna->id }}" @selected((string) $campo('commune_id') === (string) $comuna->id)>{{ $comuna->name }}</option>@endforeach
                    </select>
                    <div id="buscador-comuna-error" class="invalid-feedback">@error('commune_id'){{ $message }}@enderror</div>
                </div>
                <div class="col-12 col-md-8">
                    <label for="area_desempeno_id" class="form-label">Área de desempeño</label>
                    <select name="area_desempeno_id" id="area_desempeno_id" class="form-select @error('area_desempeno_id') is-invalid @enderror" data-slep-select="search" data-slep-area aria-describedby="buscador-area-error" @error('area_desempeno_id') aria-invalid="true" @enderror>
                        <option value="">Todas las áreas</option>
                        @foreach (['Docente' => $areasDocente, 'Asistente' => $areasAsistente] as $estamento => $areas)
                            @if ($areas->isNotEmpty())
                                <optgroup label="{{ $estamento }}">
                                    @foreach ($areas as $area)
                                        <option value="{{ $area->id }}" data-docente="{{ $estamento === 'Docente' ? '1' : '0' }}" data-tp="{{ collect($areaTpIds ?? [])->contains((int) $area->id) ? '1' : '0' }}" @selected($areaActual === (int) $area->id)>{{ $area->nombre }}</option>
                                    @endforeach
                                </optgroup>
                            @endif
                        @endforeach
                    </select>
                    <div id="buscador-area-error" class="invalid-feedback">@error('area_desempeno_id'){{ $message }}@enderror</div>
                </div>
                <div class="col-12 col-md-6" data-slep-conditional="tp" @if (! $mostrarTp) hidden @endif>
                    <label for="buscador-especialidad" class="form-label">Especialidad TP</label>
                    <select name="especialidad_tp" id="buscador-especialidad" class="form-select @error('especialidad_tp') is-invalid @enderror" data-slep-select="search" @disabled(! $mostrarTp) aria-describedby="buscador-especialidad-ayuda buscador-especialidad-error" @error('especialidad_tp') aria-invalid="true" @enderror>
                        <option value="">Todas las especialidades</option>
                        @foreach (($especialidadesTp ?? collect()) as $especialidad)<option value="{{ $especialidad }}" @selected((string) $campo('especialidad_tp') === (string) $especialidad)>{{ $especialidad }}</option>@endforeach
                    </select>
                    <p id="buscador-especialidad-ayuda" class="form-text">Corresponde al área Docente Técnico Profesional.</p>
                    <div id="buscador-especialidad-error" class="invalid-feedback">@error('especialidad_tp'){{ $message }}@enderror</div>
                </div>
                <div class="col-12 col-md-6" data-slep-conditional="docente" @if (! $mostrarMencion) hidden @endif>
                    <label for="buscador-mencion" class="form-label">Mención</label>
                    <select name="mencion" id="buscador-mencion" class="form-select @error('mencion') is-invalid @enderror" data-slep-select="tags" @disabled(! $mostrarMencion) aria-describedby="buscador-mencion-ayuda buscador-mencion-error" @error('mencion') aria-invalid="true" @enderror>
                        <option value="">Todas las menciones</option>
                        @foreach ($menciones as $mencion)<option value="{{ $mencion }}" @selected($mencionActual === (string) $mencion)>{{ $mencion }}</option>@endforeach
                    </select>
                    <p id="buscador-mencion-ayuda" class="form-text">Busca una mención o escribe el texto que deseas consultar.</p>
                    <div id="buscador-mencion-error" class="invalid-feedback">@error('mencion'){{ $message }}@enderror</div>
                </div>
                @foreach (['exp_min' => 'Experiencia mínima', 'exp_max' => 'Experiencia máxima'] as $nombreCampo => $etiqueta)
                    <div class="col-6 col-md-4">
                        <label for="buscador-{{ $nombreCampo }}" class="form-label">{{ $etiqueta }}</label>
                        <div class="slep-listing__input-unit">
                            <input id="buscador-{{ $nombreCampo }}" type="text" inputmode="numeric" name="{{ $nombreCampo }}" value="{{ $campo($nombreCampo) }}" maxlength="2" class="form-control @error($nombreCampo) is-invalid @enderror" data-slep-integer aria-describedby="buscador-{{ $nombreCampo }}-ayuda buscador-{{ $nombreCampo }}-error" @error($nombreCampo) aria-invalid="true" @enderror>
                            <span aria-hidden="true">años</span>
                        </div>
                        <p id="buscador-{{ $nombreCampo }}-ayuda" class="form-text">Entre 0 y 80 años.</p>
                        <div id="buscador-{{ $nombreCampo }}-error" class="invalid-feedback" data-slep-field-error>@error($nombreCampo){{ $message }}@enderror</div>
                    </div>
                @endforeach
                <div class="col-12 col-md-4">
                    <label for="buscador-por-pagina" class="form-label">Resultados por página</label>
                    <select name="per_page" id="buscador-por-pagina" class="form-select @error('per_page') is-invalid @enderror" data-slep-select="compact" aria-describedby="buscador-por-pagina-error" @error('per_page') aria-invalid="true" @enderror>
                        @foreach ([10, 15, 25, 50] as $cantidad)<option value="{{ $cantidad }}" @selected((int) $campo('per_page', 15) === $cantidad)>{{ $cantidad }} registros</option>@endforeach
                    </select>
                    <div id="buscador-por-pagina-error" class="invalid-feedback">@error('per_page'){{ $message }}@enderror</div>
                </div>
            </div>
            <div class="slep-listing__filter-actions">
                <p class="slep-listing__muted mb-0">Puedes combinar los filtros. Los resultados se ordenan por apellidos y nombres.</p>
                <div class="slep-listing__actions">
                    <a class="slep-listing__button slep-listing__button--secondary" href="{{ route('reemplazos.buscador-postulantes.index') }}"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Limpiar filtros</a>
                    <button class="slep-listing__button slep-listing__button--primary" type="submit" data-slep-submit><i class="bi bi-search" aria-hidden="true"></i> <span data-slep-submit-label>Buscar</span></button>
                </div>
            </div>
        </form>
    </section>

    <section class="slep-listing__panel" aria-labelledby="buscador-resultados-titulo">
        <div class="slep-listing__panel-header">
            <div><p class="slep-listing__eyebrow">Perfiles registrados</p><h2 id="buscador-resultados-titulo">Resultados de la búsqueda</h2><p class="slep-listing__muted mb-0">La vinculación laboral registrada se muestra en la tarjeta amarilla de cada persona.</p></div>
            <span class="slep-listing__chip">{{ number_format($profiles->total(), 0, ',', '.') }} resultados</span>
        </div>
        <div class="slep-listing__legend" aria-label="Estados laborales">
            <span class="slep-status-pill slep-status-pill--warning"><i class="bi bi-briefcase" aria-hidden="true"></i> Vinculación activa</span>
            <span class="slep-status-pill slep-status-pill--success"><i class="bi bi-telephone-outbound" aria-hidden="true"></i> Trabajo externo informado</span>
            <span class="slep-status-pill slep-status-pill--info"><i class="bi bi-person-check" aria-hidden="true"></i> Reemplazo vigente</span>
        </div>
        <div class="slep-listing__table-wrap">
            <table class="slep-listing__table" role="table">
                <caption class="visually-hidden">Postulantes y funcionarios con datos de contacto, perfil y estado laboral registrado.</caption>
                <thead role="rowgroup"><tr role="row"><th scope="col">Persona y documentos</th><th scope="col">Contacto y comunas</th><th scope="col">Perfil profesional</th><th scope="col">Estado laboral</th><th scope="col">Acción</th></tr></thead>
                <tbody role="rowgroup">
                    @forelse ($profiles as $perfil)
                        @php
                            $usuario = $perfil->user;
                            $nombre = $usuario?->nombre_completo ?: 'Nombre no informado';
                            $estadoDocs = $perfil->documentos_obligatorios_estado ?? [];
                            $completos = $estadoDocs['is_complete'] ?? null;
                            $faltantes = (int) ($estadoDocs['missing'] ?? 0);
                        @endphp
                        <tr id="perfil-{{ $perfil->id }}" role="row">
                            <td data-label="Persona y documentos" role="cell">
                                <strong class="slep-listing__person">{{ $nombre }}</strong>
                                <span class="slep-listing__muted d-block mb-2">RUT {{ $usuario?->rut ?: 'No informado' }}</span>
                                <div class="slep-listing__documents">
                                    <span class="slep-status-pill slep-status-pill--{{ $completos === true ? 'success' : ($completos === false ? 'danger' : 'neutral') }}">
                                        <i class="bi {{ $completos === true ? 'bi-check-circle' : ($completos === false ? 'bi-exclamation-triangle' : 'bi-file-earmark') }}" aria-hidden="true"></i>
                                        {{ $completos === true ? 'Documentos cargados' : ($completos === false ? ($faltantes === 1 ? 'Falta 1 documento' : 'Faltan '.$faltantes.' documentos') : 'Sin documentos obligatorios definidos') }}
                                    </span>
                                    <p class="slep-listing__muted small mt-2 mb-0">{{ $estadoDocs['tooltip'] ?? 'No se detectaron documentos obligatorios para este perfil.' }} @if ($completos === true)La carga no implica aprobación administrativa.@endif</p>
                                </div>
                            </td>
                            <td data-label="Contacto y comunas" role="cell">
                                <div class="slep-listing__detail"><i class="bi bi-telephone" aria-hidden="true"></i><span>{{ $perfil->telefono1 ?: ($perfil->telefono2 ?: 'Teléfono no informado') }}</span></div>
                                <div class="slep-listing__detail mt-2"><i class="bi bi-geo-alt" aria-hidden="true"></i><span>{{ $usuario?->communes?->pluck('name')->implode(', ') ?: 'Sin comunas de desempeño registradas' }}</span></div>
                            </td>
                            <td data-label="Perfil profesional" role="cell">
                                <strong>{{ $perfil->areaDesempeno?->nombre ?: ($perfil->area_desempeno_nombre ?: 'Área no informada') }}</strong>
                                @if ($perfil->especialidad_tp)<p class="slep-listing__muted mb-1 mt-2">Especialidad TP: {{ $perfil->especialidad_tp }}</p>@endif
                                @if ($perfil->mencion)<p class="slep-listing__muted mb-1 mt-2">Mención: {{ $perfil->mencion }}</p>@endif
                                <p class="slep-listing__muted mb-0 mt-2">{{ $perfil->anios_experiencia !== null ? $perfil->anios_experiencia.' años de experiencia' : 'Experiencia no informada' }}</p>
                            </td>
                            <td data-label="Estado laboral" role="cell">
                                @include('partials.ui.estado-laboral', ['contratos' => $perfil->contratosLaboralesActivos ?? collect(), 'reemplazos' => $perfil->relationLoaded('reemplazosActivosActuales') ? $perfil->getRelation('reemplazosActivosActuales') : collect(), 'externo' => $usuario?->trabaja_en_otro_lugar ?? false, 'observacion' => $usuario?->trabaja_en_otro_lugar_observacion])
                            </td>
                            <td data-label="Acción" role="cell"><a href="{{ route('reemplazos.buscador-postulantes.show', $perfil) }}" class="slep-listing__button slep-listing__button--secondary slep-listing__button--compact" aria-label="Ver ficha de {{ $nombre }}"><i class="bi bi-person-lines-fill" aria-hidden="true"></i> Ver ficha</a></td>
                        </tr>
                    @empty
                        <tr role="row"><td colspan="5" class="slep-listing__empty" role="cell"><i class="bi bi-search" aria-hidden="true"></i><h3>No encontramos personas con esos filtros</h3><p class="slep-listing__muted">Prueba con menos criterios o vuelve al listado completo.</p><a href="{{ route('reemplazos.buscador-postulantes.index') }}" class="slep-listing__button slep-listing__button--secondary">Limpiar filtros</a></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.ui.paginacion', ['paginador' => $profiles, 'etiqueta' => 'Páginas de postulantes y funcionarios'])
    </section>
</div>
@endsection
