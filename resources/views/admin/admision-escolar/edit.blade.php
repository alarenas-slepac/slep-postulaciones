@extends('layouts.app')

@section('content')
<div class="container-fluid py-4 admision-editor">
    <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-start gap-3 mb-4">
        <div>
            <a class="text-decoration-none small" href="{{ route('admin.admision-escolar.index') }}"><i class="bi bi-arrow-left me-1"></i>Volver al listado</a>
            <h1 class="h2 mt-2 mb-1">{{ $establecimiento->nombre_establecimiento }}</h1>
            <div class="text-muted">RBD {{ $establecimiento->rbd }}{{ $establecimiento->dv ? '-' . $establecimiento->dv : '' }} · {{ $establecimiento->comuna ?: 'Sin comuna' }}</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @if ($perfil->exists)
                <a class="btn btn-outline-primary" href="{{ route('admin.admision-escolar.preview', $establecimiento) }}" target="_blank" rel="noopener"><i class="bi bi-eye me-1"></i>Previsualizar</a>
                @if ($perfil->isPublicado())
                    <form method="POST" action="{{ route('admin.admision-escolar.unpublish', $establecimiento) }}">
                        @csrf
                        <button class="btn btn-outline-danger" type="submit" onclick="return confirm('¿Despublicar esta ficha? La información se conservará como borrador.')"><i class="bi bi-eye-slash me-1"></i>Despublicar</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('admin.admision-escolar.publish', $establecimiento) }}">
                        @csrf
                        <button class="btn btn-success" type="submit" @disabled(! $completitud['publishable'])><i class="bi bi-cloud-arrow-up me-1"></i>Publicar</button>
                    </form>
                @endif
            @endif
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success shadow-sm"><i class="bi bi-check-circle me-2"></i>{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger shadow-sm">
            <div class="fw-bold mb-1">Revisa la información ingresada:</div>
            <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-12 col-xl-8">
            <form method="POST" action="{{ route('admin.admision-escolar.update', $establecimiento) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0 pt-4 px-4">
                        <h2 class="h5 mb-1"><i class="bi bi-stars text-primary me-2"></i>Identidad educativa</h2>
                        <p class="text-muted small mb-0">Contenido principal que aparecerá en la tarjeta y ficha pública.</p>
                    </div>
                    <div class="card-body p-4">
                        <div class="mb-3">
                            <label class="form-label" for="sello_educativo">Sello educativo</label>
                            <textarea id="sello_educativo" name="sello_educativo" class="form-control" rows="5" maxlength="3000" placeholder="Describe el rasgo distintivo del Proyecto Educativo Institucional...">{{ old('sello_educativo', $perfil->sello_educativo) }}</textarea>
                            <div class="form-text">Texto público. Evita datos personales o información no validada por el establecimiento.</div>
                        </div>
                        <div>
                            <label class="form-label" for="descripcion_corta">Descripción corta</label>
                            <textarea id="descripcion_corta" name="descripcion_corta" class="form-control" rows="3" maxlength="500" placeholder="Resumen de hasta 500 caracteres para la tarjeta de la vitrina.">{{ old('descripcion_corta', $perfil->descripcion_corta) }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0 pt-4 px-4">
                        <h2 class="h5 mb-1"><i class="bi bi-person-badge text-primary me-2"></i>Dirección del establecimiento</h2>
                    </div>
                    <div class="card-body p-4">
                        <div class="mb-3">
                            <label class="form-label" for="director_nombre">Director/a</label>
                            <input id="director_nombre" name="director_nombre" class="form-control" value="{{ old('director_nombre', $perfil->director_nombre) }}" maxlength="180">
                        </div>
                        <div>
                            <label class="form-label" for="director_resena">Reseña o mensaje breve</label>
                            <textarea id="director_resena" name="director_resena" class="form-control" rows="4" maxlength="1200">{{ old('director_resena', $perfil->director_resena) }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0 pt-4 px-4">
                        <h2 class="h5 mb-1"><i class="bi bi-image text-primary me-2"></i>Identidad visual</h2>
                        <p class="text-muted small mb-0">Archivos públicos. El sistema corrige la orientación, reduce dimensiones, elimina metadatos y convierte automáticamente las imágenes a WebP.</p>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-4">
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="logo">Logo del establecimiento</label>
                                @if ($perfil->logoUrl())
                                    <div class="admision-current-media mb-3"><img src="{{ $perfil->logoUrl() }}" alt="Logo actual de {{ $establecimiento->nombre_establecimiento }}"></div>
                                @endif
                                <input id="logo" type="file" name="logo" class="form-control" accept="image/jpeg,image/png,image/webp">
                                <div class="form-text">JPG, PNG o WebP. Hasta {{ config('admision.max_imagen_mb', 100) }} MB; se optimiza automáticamente al guardar.</div>
                                @if ($perfil->logo_path)
                                    <div class="form-check mt-2">
                                        <input type="hidden" name="eliminar_logo" value="0">
                                        <input id="eliminar_logo" class="form-check-input" type="checkbox" name="eliminar_logo" value="1">
                                        <label class="form-check-label text-danger" for="eliminar_logo">Eliminar logo actual</label>
                                    </div>
                                @else
                                    <input type="hidden" name="eliminar_logo" value="0">
                                @endif
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="director_foto">Fotografía del director/a</label>
                                @if ($perfil->directorFotoUrl())
                                    <div class="admision-current-media is-person mb-3"><img src="{{ $perfil->directorFotoUrl() }}" alt="Fotografía actual de {{ $perfil->director_nombre ?: 'la dirección' }}"></div>
                                @endif
                                <input id="director_foto" type="file" name="director_foto" class="form-control" accept="image/jpeg,image/png,image/webp">
                                <div class="form-text">Preferentemente cuadrada. Hasta {{ config('admision.max_imagen_mb', 100) }} MB; se optimiza automáticamente al guardar.</div>
                                @if ($perfil->director_foto_path)
                                    <div class="form-check mt-2">
                                        <input type="hidden" name="eliminar_director_foto" value="0">
                                        <input id="eliminar_director_foto" class="form-check-input" type="checkbox" name="eliminar_director_foto" value="1">
                                        <label class="form-check-label text-danger" for="eliminar_director_foto">Eliminar fotografía actual</label>
                                    </div>
                                @else
                                    <input type="hidden" name="eliminar_director_foto" value="0">
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0 pt-4 px-4">
                        <h2 class="h5 mb-1"><i class="bi bi-link-45deg text-primary me-2"></i>Enlaces, ubicación y contacto</h2>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="sitio_web_url">Sitio web</label>
                                <input id="sitio_web_url" type="url" name="sitio_web_url" class="form-control" value="{{ old('sitio_web_url', $perfil->sitio_web_url) }}" placeholder="https://...">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="facebook_url">Facebook</label>
                                <input id="facebook_url" type="url" name="facebook_url" class="form-control" value="{{ old('facebook_url', $perfil->facebook_url) }}" placeholder="https://facebook.com/..."></div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="instagram_url">Instagram</label>
                                <input id="instagram_url" type="url" name="instagram_url" class="form-control" value="{{ old('instagram_url', $perfil->instagram_url) }}" placeholder="https://instagram.com/..."></div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="sector">Sector</label>
                                <select id="sector" name="sector" class="form-select">
                                    <option value="">Sin especificar</option>
                                    <option value="Urbano" @selected(old('sector', $perfil->sector) === 'Urbano')>Urbano</option>
                                    <option value="Rural" @selected(old('sector', $perfil->sector) === 'Rural')>Rural</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="direccion_publica">Dirección pública</label>
                                <input id="direccion_publica" name="direccion_publica" class="form-control" value="{{ old('direccion_publica', $perfil->direccion_publica) }}" maxlength="500">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="telefono_publico">Teléfono público</label>
                                <input id="telefono_publico" name="telefono_publico" class="form-control" value="{{ old('telefono_publico', $perfil->telefono_publico) }}" maxlength="80">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="email_publico">Correo público</label>
                                <input id="email_publico" type="email" name="email_publico" class="form-control" value="{{ old('email_publico', $perfil->email_publico) }}" maxlength="255">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <div class="row g-3 align-items-center">
                            <div class="col-12 col-md-4">
                                <label class="form-label" for="orden">Orden manual</label>
                                <input id="orden" type="number" min="0" max="65535" name="orden" class="form-control" value="{{ old('orden', $perfil->orden ?? 0) }}">
                            </div>
                            <div class="col-12 col-md-8">
                                <div class="form-check form-switch mt-md-4">
                                    <input type="hidden" name="destacado" value="0">
                                    <input id="destacado" class="form-check-input" type="checkbox" name="destacado" value="1" @checked(old('destacado', $perfil->destacado))>
                                    <label class="form-check-label fw-semibold" for="destacado">Destacar en la vitrina pública</label>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end mt-4">
                            <button class="btn btn-primary px-4" type="submit"><i class="bi bi-save me-1"></i>Guardar ficha</button>
                        </div>
                    </div>
                </div>
            </form>

            @include('admin.admision-escolar.partials.gallery')
        </div>

        <div class="col-12 col-xl-4">
            <div class="card border-0 shadow-sm position-sticky" style="top:92px;">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h2 class="h5 mb-0">Estado de la ficha</h2>
                        @if ($perfil->isPublicado())
                            <span class="badge text-bg-success rounded-pill">Publicado</span>
                        @elseif ($perfil->exists)
                            <span class="badge text-bg-warning rounded-pill">Borrador</span>
                        @else
                            <span class="badge text-bg-secondary rounded-pill">Sin guardar</span>
                        @endif
                    </div>
                    <div class="d-flex justify-content-between small fw-semibold mt-4 mb-1">
                        <span>{{ $completitud['label'] }}</span><span>{{ $completitud['score'] }}%</span>
                    </div>
                    <div class="progress" style="height:9px;"><div class="progress-bar bg-{{ $completitud['tone'] }}" style="width:{{ $completitud['score'] }}%"></div></div>

                    @if ($completitud['publishable'])
                        <div class="alert alert-success mt-4 mb-0"><i class="bi bi-check-circle me-1"></i>La ficha cumple los requisitos mínimos de publicación.</div>
                    @else
                        <div class="mt-4">
                            <div class="fw-semibold mb-2">Falta para publicar:</div>
                            <ul class="small text-muted ps-3 mb-0">
                                @foreach (app(\App\Services\AdmisionEscolarCompletenessService::class)->publicationMissing($establecimiento, $perfil) as $missing)
                                    <li class="mb-1">{{ $missing }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <hr class="my-4">
                    <div class="small text-muted">Slug público</div>
                    <code class="d-block text-break mt-1">{{ $perfil->slug ?: 'Se generará al guardar' }}</code>
                    <div class="small text-muted mt-3">Los datos base —nombre, RBD, comuna y niveles— se leen directamente desde la tabla <code>establecimientos</code> y no se editan desde este módulo.</div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .admision-editor .card { border-radius:18px; }
    .admision-editor .card-header { border-radius:18px 18px 0 0; }
    .admision-current-media { width:100%; height:190px; border:1px solid #dbe4f0; border-radius:16px; background:#f8fafc; display:flex; align-items:center; justify-content:center; overflow:hidden; }
    .admision-current-media img { width:100%; height:100%; object-fit:contain; padding:.75rem; }
    .admision-current-media.is-person img { object-fit:cover; padding:0; }
</style>
@endsection
