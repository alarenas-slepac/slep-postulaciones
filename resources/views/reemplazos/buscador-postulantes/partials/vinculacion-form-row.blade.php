@php
    $valorFila = fn ($campo) => is_scalar($vinculacion[$campo] ?? null) ? (string) $vinculacion[$campo] : '';
@endphp
<fieldset class="slep-profile__contract-row" data-ficha-row>
    <legend>Establecimiento <span data-ficha-row-number>{{ is_numeric($idx) ? $idx + 1 : '' }}</span></legend>
    <div class="row g-3 align-items-start">
        <div class="col-12 col-md-8">
            <label class="form-label" for="vinculo-ee-{{ $idx }}">Establecimiento <span aria-hidden="true">*</span></label>
            <select id="vinculo-ee-{{ $idx }}" name="vinculaciones[{{ $idx }}][establecimiento_id]" class="form-select @error('vinculaciones.'.$idx.'.establecimiento_id') is-invalid @enderror" data-slep-select="search" data-ficha-establecimiento required aria-describedby="vinculo-ee-{{ $idx }}-error">
                <option value="">Buscar establecimiento por nombre o RBD</option>
                @foreach (($establecimientosPorComuna ?? collect()) as $comuna => $establecimientos)
                    <optgroup label="{{ $comuna }}">
                        @foreach ($establecimientos as $establecimiento)
                            <option value="{{ $establecimiento->id }}" @selected($valorFila('establecimiento_id') === (string) $establecimiento->id)>RBD {{ $establecimiento->rbd }} · {{ $establecimiento->nombre_establecimiento }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
            <div id="vinculo-ee-{{ $idx }}-error" class="invalid-feedback">@error('vinculaciones.'.$idx.'.establecimiento_id'){{ $message }}@enderror</div>
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label" for="vinculo-horas-{{ $idx }}">Horas <span aria-hidden="true">*</span></label>
            <input id="vinculo-horas-{{ $idx }}" type="text" inputmode="numeric" maxlength="2" name="vinculaciones[{{ $idx }}][cantidad_horas]" value="{{ $valorFila('cantidad_horas') }}" class="form-control @error('vinculaciones.'.$idx.'.cantidad_horas') is-invalid @enderror" data-ficha-horas required aria-describedby="vinculo-horas-{{ $idx }}-ayuda vinculo-horas-{{ $idx }}-error">
            <p class="form-text" id="vinculo-horas-{{ $idx }}-ayuda">Entre 1 y 60 horas.</p>
            <div id="vinculo-horas-{{ $idx }}-error" class="invalid-feedback">@error('vinculaciones.'.$idx.'.cantidad_horas'){{ $message }}@enderror</div>
        </div>
    </div>
    <button type="button" class="slep-listing__button slep-profile__button-danger slep-listing__button--compact mt-3" data-ficha-remove><i class="bi bi-trash" aria-hidden="true"></i> Quitar establecimiento</button>
</fieldset>
