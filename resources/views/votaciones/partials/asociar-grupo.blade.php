@can('votaciones.manage-grupos')
<section class="va-card mb-4" id="asociar-grupo">
    <div class="va-card__header"><div><h2><i class="bi bi-link-45deg me-1" aria-hidden="true"></i> Asociar grupo existente</h2><p>Incorpore a esta jornada un equipo ya configurado en otra jornada.</p></div></div>
    <div class="va-card__body">
        @if($gruposDisponibles->isEmpty())
            <p class="text-muted mb-0">No hay grupos de otras jornadas disponibles. Puede crear un grupo nuevo con el botón «Crear grupo».</p>
        @else
            <p class="alert alert-info">Se copiarán el nombre, responsable, integrantes y ruta. El grupo original conservará su información; los estados, visitas e historial de esta jornada serán independientes.</p>
            <form method="POST" action="{{ route('votaciones.admin.grupos.store', $jornada) }}" class="row g-3" data-disable-on-submit>
                @csrf
                <input type="hidden" name="modo" value="asociar">
                <div class="col-md-9">
                    <label class="form-label" for="grupo-origen">Grupo y jornada de origen *</label>
                    <select class="form-select @error('grupo_origen_id') is-invalid @enderror" id="grupo-origen" name="grupo_origen_id" required aria-describedby="ayuda-grupo-origen">
                        <option value="">Buscar grupo o jornada…</option>
                        @foreach($gruposDisponibles->groupBy('jornada_votacion_id') as $opciones)
                            <optgroup label="{{ $opciones->first()->jornada->nombre }} · {{ $opciones->first()->jornada->periodo_funcionamiento }}">
                                @foreach($opciones as $opcion)<option value="{{ $opcion->id }}" @selected(old('grupo_origen_id') == $opcion->id)>{{ $opcion->numero }}. {{ $opcion->nombre }} · {{ $opcion->jornada->nombre }}</option>@endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    <div class="form-text" id="ayuda-grupo-origen">Cada grupo de origen puede incorporarse una sola vez a esta jornada.</div>
                    @error('grupo_origen_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3"><label class="form-label" for="numero-asociado">Número en esta jornada *</label><input class="form-control" type="number" id="numero-asociado" name="numero" min="1" max="999" value="{{ old('modo') === 'asociar' ? old('numero') : $jornada->grupos->max('numero') + 1 }}" required></div>
                @include('votaciones.partials.periodo-grupo', ['prefijo' => 'asociar', 'grupoPeriodo' => null])
                <div class="col-12 text-end"><button class="btn btn-primary"><i class="bi bi-link-45deg me-1" aria-hidden="true"></i> Asociar a esta jornada</button></div>
            </form>
        @endif
    </div>
</section>
@endcan

@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">
    <style>
        #grupo-origen + .select2-container .select2-selection { min-height: 2.75rem; border-color: var(--slep-border, #dbe4f0); border-radius: .75rem; }
        #grupo-origen + .select2-container--focus .select2-selection { border-color: #0d6efd; box-shadow: 0 0 0 .2rem rgba(13, 110, 253, .15); }
    </style>
@endpush
@push('scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (window.jQuery?.fn?.select2) {
                window.jQuery('#grupo-origen').select2({
                    theme: 'bootstrap-5', width: '100%', placeholder: 'Buscar grupo o jornada…',
                    language: { noResults: () => 'No se encontraron grupos.' }
                });
            }
        });
    </script>
@endpush
