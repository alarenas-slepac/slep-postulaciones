@php $ultimo = $documentosActuales->get($tipoDocumento); $campoArchivo = 'documentos.'.$tipoDocumento; @endphp
<div class="col-md-6">
    <label class="form-label" for="mat-documento-{{ $tipoDocumento }}">{{ config('maternidad.documentos.'.$tipoDocumento) }}@if ($tipoDocumento === 'embarazo' && $caso?->origen_registro !== 'masivo') <span class="text-danger">*</span>@endif</label>
    <input class="form-control @error($campoArchivo) is-invalid @enderror" type="file" id="mat-documento-{{ $tipoDocumento }}" name="documentos[{{ $tipoDocumento }}]"
           accept="{{ $tipoDocumento === 'orden_trabajo' ? 'application/pdf' : 'application/pdf,image/jpeg,image/png,image/webp' }}" data-mat-documento="{{ $tipoDocumento }}"
           aria-describedby="mat-documento-{{ $tipoDocumento }}-ayuda mat-documento-{{ $tipoDocumento }}-error">
    <div class="form-text" id="mat-documento-{{ $tipoDocumento }}-ayuda">
        {{ $tipoDocumento === 'orden_trabajo' ? 'PDF opcional' : 'PDF, JPG, PNG o WEBP' }} · máximo 10 MB.
        @if ($ultimo)<a href="{{ route('tramites.maternidad.documento', [$caso, $ultimo]) }}">Descargar respaldo actual</a>. Cargar otro archivo conserva la versión anterior.@endif
    </div>
    <div class="invalid-feedback" id="mat-documento-{{ $tipoDocumento }}-error">@error($campoArchivo){{ $message }}@enderror</div>
</div>
