@extends('layouts.app')

@push('styles')
    @vite('resources/js/slep-listados.js')
@endpush

@section('content')
    @php($ruta = 'gestion.solicitudes-reemplazo.firma-ot.')
    <div class="slep-listing" data-slep-listing>
        <header class="slep-listing__header">
            <div class="slep-listing__heading">
                <span class="slep-listing__icon"><i class="bi bi-pen" aria-hidden="true"></i></span>
                <div>
                    <p class="slep-listing__eyebrow">Reemplazos y selección</p>
                    <h1>Firma de órdenes de trabajo</h1>
                    <p class="mb-0 slep-listing__muted">Define quién firma las nuevas órdenes de Reemplazos y Prórrogas.</p>
                </div>
            </div>
            <a class="slep-listing__button slep-listing__button--secondary" href="{{ route('gestion.solicitudes-reemplazo.index') }}">Volver a Gestión reemplazos</a>
        </header>

        @if (session('status'))
            <div class="alert alert-success" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="slep-listing__notice slep-listing__notice--danger" data-slep-errors tabindex="-1" role="alert">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route($ruta.'update') }}" enctype="multipart/form-data">
            @csrf
            <section class="slep-listing__panel" aria-labelledby="firma-vigente">
                <div class="slep-listing__panel-header">
                    <h2 id="firma-vigente">Firma vigente</h2>
                    <span class="slep-status-pill slep-status-pill--success">{{ $perfiles[$configuracion['vigente']] }}</span>
                </div>
                <fieldset class="slep-listing__filters mb-0" @error('vigente') aria-describedby="error-vigente" @enderror>
                    <legend class="form-label">Quién firmará las nuevas órdenes</legend>
                    <div class="d-flex flex-wrap gap-3">
                        @foreach ($perfiles as $clave => $etiqueta)
                            <div class="form-check">
                                <input id="vigente-{{ $clave }}" class="form-check-input" type="radio" name="vigente" value="{{ $clave }}"
                                    @checked(old('vigente', $configuracion['vigente']) === $clave) required>
                                <label for="vigente-{{ $clave }}" class="form-check-label">{{ $etiqueta }}</label>
                            </div>
                        @endforeach
                    </div>
                    @error('vigente')<div class="invalid-feedback" id="error-vigente">{{ $message }}</div>@enderror
                </fieldset>
            </section>

            <div class="row g-3 mb-3 align-items-stretch">
                @foreach ($perfiles as $clave => $etiqueta)
                    @php($perfil = $configuracion[$clave])
                    <div class="col-lg-6">
                        <section class="slep-listing__panel h-100 mb-0" aria-labelledby="perfil-{{ $clave }}">
                            <div class="slep-listing__panel-header">
                                <h2 id="perfil-{{ $clave }}">{{ $etiqueta }}</h2>
                            </div>
                            <div class="slep-listing__filters">
                                <div class="mb-3">
                                    <label for="{{ $clave }}-nombre" class="form-label">Nombre completo</label>
                                    <input type="text" id="{{ $clave }}-nombre" name="{{ $clave }}[nombre]"
                                        class="form-control @error($clave.'.nombre') is-invalid @enderror"
                                        value="{{ old($clave.'.nombre', $perfil['nombre']) }}" maxlength="140" @required($clave === 'titular')>
                                    @error($clave.'.nombre')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="mb-3">
                                    <label for="{{ $clave }}-cargo" class="form-label">Cargo</label>
                                    <input type="text" id="{{ $clave }}-cargo" name="{{ $clave }}[cargo]"
                                        class="form-control @error($clave.'.cargo') is-invalid @enderror"
                                        value="{{ old($clave.'.cargo', $perfil['cargo']) }}" maxlength="160" @required($clave === 'titular')>
                                    @error($clave.'.cargo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div>
                                    <label for="{{ $clave }}-firma" class="form-label">Imagen de firma</label>
                                    <input type="file" id="{{ $clave }}-firma" name="{{ $clave }}[firma]"
                                        class="form-control @error($clave.'.firma') is-invalid @enderror" accept="image/png,image/jpeg"
                                        aria-describedby="{{ $clave }}-ayuda">
                                    @error($clave.'.firma')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    <div id="{{ $clave }}-ayuda" class="form-text">
                                        PNG o JPG, hasta 2 MB y 1600 × 1200 píxeles.
                                        @if ($perfil['archivo'])
                                            Firma guardada. Selecciona otra imagen para reemplazarla.
                                        @elseif ($clave === 'titular')
                                            Firma institucional registrada. Carga una nueva imagen si cambia la persona titular.
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>
                @endforeach
            </div>

            <div class="slep-listing__actions">
                <button type="submit" class="slep-listing__button slep-listing__button--primary">
                    <i class="bi bi-check2" aria-hidden="true"></i> Guardar configuración
                </button>
                <button type="submit" class="slep-listing__button slep-listing__button--secondary"
                    formaction="{{ route($ruta.'preview') }}" formtarget="_blank">
                    <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> Previsualizar PDF
                </button>
            </div>
            <p class="form-text mb-0 mt-2">La vista previa abre una orden de ejemplo en otra pestaña con los datos ingresados en el formulario.</p>
        </form>
    </div>
@endsection
