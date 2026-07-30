<div class="card border-0 shadow-sm mb-4" id="galeria">
    <div class="card-header bg-white border-0 pt-4 px-4 d-flex flex-column flex-md-row justify-content-between gap-2">
        <div>
            <h2 class="h5 mb-1"><i class="bi bi-images text-primary me-2"></i>Galería de imágenes</h2>
            <p class="text-muted small mb-0">La portada se utiliza en las tarjetas y encabezado de la ficha pública.</p>
        </div>
        @if ($perfil->exists)
            <span class="badge text-bg-light border align-self-start">{{ $perfil->imagenes->count() }} / {{ config('admision.max_imagenes_por_establecimiento', 20) }}</span>
        @endif
    </div>
    <div class="card-body p-4">
        @if (! $perfil->exists)
            <div class="alert alert-info mb-0"><i class="bi bi-info-circle me-1"></i>Guarda primero la ficha para habilitar la galería.</div>
        @else
            <form method="POST" action="{{ route('admin.admision-escolar.gallery.store', $establecimiento) }}" enctype="multipart/form-data" class="border rounded-4 p-3 p-lg-4 bg-light mb-4">
                @csrf
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-lg-6">
                        <label class="form-label" for="imagenes">Agregar imágenes</label>
                        <input id="imagenes" type="file" name="imagenes[]" class="form-control" accept="image/jpeg,image/png,image/webp" multiple required>
                        <div class="form-text">Hasta {{ config('admision.max_imagenes_por_carga', 10) }} por carga; {{ config('admision.max_imagen_mb', 100) }} MB por imagen y {{ config('admision.max_carga_total_mb', 200) }} MB en total. Se reducen y convierten automáticamente a WebP.</div>
                    </div>
                    <div class="col-12 col-lg-4">
                        <label class="form-label" for="texto_alternativo_base">Texto alternativo base</label>
                        <input id="texto_alternativo_base" name="texto_alternativo_base" class="form-control" maxlength="180" placeholder="Ej.: Patio principal del establecimiento">
                    </div>
                    <div class="col-12 col-lg-2">
                        <input type="hidden" name="marcar_primera_como_portada" value="0">
                        <div class="form-check mb-2">
                            <input id="marcar_primera_como_portada" class="form-check-input" type="checkbox" name="marcar_primera_como_portada" value="1">
                            <label class="form-check-label small" for="marcar_primera_como_portada">Primera como portada</label>
                        </div>
                        <button class="btn btn-primary w-100" type="submit"><i class="bi bi-upload me-1"></i>Subir</button>
                    </div>
                </div>
            </form>

            @if ($perfil->imagenes->isEmpty())
                <div class="text-center text-muted py-5 border rounded-4 border-dashed">
                    <i class="bi bi-images fs-1 d-block mb-2"></i>
                    Aún no hay imágenes en la galería.
                </div>
            @else
                <div class="row g-3">
                    @foreach ($perfil->imagenes as $imagen)
                        <div class="col-12 col-lg-6" id="imagen-{{ $imagen->id }}">
                            <div class="border rounded-4 overflow-hidden h-100 bg-white">
                                <div class="position-relative admision-gallery-thumb">
                                    <img src="{{ $imagen->url() }}" alt="{{ $imagen->texto_alternativo }}">
                                    @if ($imagen->es_portada)
                                        <span class="badge text-bg-primary position-absolute top-0 start-0 m-2"><i class="bi bi-star-fill me-1"></i>Portada</span>
                                    @endif
                                </div>
                                <div class="p-3">
                                    <form method="POST" action="{{ route('admin.admision-escolar.gallery.update', [$establecimiento, $imagen]) }}">
                                        @csrf
                                        @method('PATCH')
                                        <div class="mb-2">
                                            <label class="form-label small" for="alt-{{ $imagen->id }}">Texto alternativo</label>
                                            <input id="alt-{{ $imagen->id }}" name="texto_alternativo" class="form-control form-control-sm" value="{{ $imagen->texto_alternativo }}" maxlength="255" required>
                                        </div>
                                        <div class="row g-2">
                                            <div class="col-8">
                                                <label class="form-label small" for="titulo-{{ $imagen->id }}">Título</label>
                                                <input id="titulo-{{ $imagen->id }}" name="titulo" class="form-control form-control-sm" value="{{ $imagen->titulo }}" maxlength="255">
                                            </div>
                                            <div class="col-4">
                                                <label class="form-label small" for="orden-{{ $imagen->id }}">Orden</label>
                                                <input id="orden-{{ $imagen->id }}" type="number" name="orden" class="form-control form-control-sm" value="{{ $imagen->orden }}" min="0" max="65535" required>
                                            </div>
                                        </div>
                                        <div class="mt-2">
                                            <label class="form-label small" for="descripcion-{{ $imagen->id }}">Pie de foto</label>
                                            <textarea id="descripcion-{{ $imagen->id }}" name="descripcion" class="form-control form-control-sm" rows="2" maxlength="1000">{{ $imagen->descripcion }}</textarea>
                                        </div>
                                        <button class="btn btn-sm btn-outline-primary mt-3" type="submit"><i class="bi bi-save me-1"></i>Guardar datos</button>
                                    </form>

                                    <div class="d-flex gap-2 mt-3 pt-3 border-top">
                                        @unless ($imagen->es_portada)
                                            <form method="POST" action="{{ route('admin.admision-escolar.gallery.cover', [$establecimiento, $imagen]) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="bi bi-star me-1"></i>Usar como portada</button>
                                            </form>
                                        @endunless
                                        <form method="POST" action="{{ route('admin.admision-escolar.gallery.destroy', [$establecimiento, $imagen]) }}" class="ms-auto">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger" type="submit" onclick="return confirm('¿Eliminar esta imagen de la galería?')"><i class="bi bi-trash"></i><span class="visually-hidden">Eliminar</span></button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        @endif
    </div>
</div>

<style>
    .admision-gallery-thumb { height:240px; background:#eef2f7; }
    .admision-gallery-thumb img { width:100%; height:100%; object-fit:cover; }
    .border-dashed { border-style:dashed !important; }
</style>
