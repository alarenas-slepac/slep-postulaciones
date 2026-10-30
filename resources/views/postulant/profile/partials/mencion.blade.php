@php
    $mencionSel = (string) old('mencion', $profile->mencion ?? '');
    $mencionEnCatalogo = $menciones->flatten(1)->contains(fn ($mencion) => $mencion->etiqueta === $mencionSel);
@endphp
<div class="mb-3 d-none slep-listing" id="wrap-mencion" data-profile-mencion>
    <label class="form-label" for="mencion" id="mencion-label">
        Mención <span id="mencionAsterisk" class="d-none" aria-hidden="true">*</span>
    </label>
    <select class="form-select @error('mencion') is-invalid @enderror" id="mencion" name="mencion"
        data-slep-select="search" aria-describedby="mencion-ayuda mencion-error"
        @error('mencion') aria-invalid="true" @enderror
        data-required-message="{{ $requiredMessage ?? 'Este campo es obligatorio.' }}">
        <option value="">Seleccione…</option>
        @if ($mencionSel !== '' && ! $mencionEnCatalogo)
            <option value="{{ $mencionSel }}" selected>{{ $mencionSel }}</option>
        @endif
        @foreach ($menciones as $subsector => $opciones)
            <optgroup label="{{ $subsector }}">
                @foreach ($opciones as $mencion)
                    <option value="{{ $mencion->etiqueta }}" @selected($mencionSel === $mencion->etiqueta)>{{ $mencion->etiqueta }}</option>
                @endforeach
            </optgroup>
        @endforeach
    </select>
    <div id="mencion-error" class="invalid-feedback">@error('mencion'){{ $message }}@enderror</div>
    <p id="mencion-ayuda" class="form-text">Todas las menciones están disponibles. Abre el selector y busca por nombre, universidad o año.</p>
    @if ($menciones->isEmpty())
        <p class="form-text" role="status">No hay menciones registradas en el catálogo.</p>
    @endif
</div>
