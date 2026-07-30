@extends('layouts.app')

@section('content')
    <div class="container">
        @php
            // Permite reutilizar esta vista desde Admin y desde Reemplazos
            $routeNs = $routeNs ?? 'admin';
            $backUrl = $backUrl ?? route('admin.documents.index');
        @endphp
        <div class="row g-3">
            <div class="col-md-6">
                <h3 class="mb-3">Revisión de documento</h3>
            </div>
            <div class="col-md-6">
                <div class="d-flex justify-content-end gap-2 flex-wrap">
                    @if (!empty($prevDocument))
                        <a href="{{ route($routeNs . '.documents.show', ['document' => $prevDocument->id, 'back_to' => $backUrl]) }}" class="btn btn-outline-secondary">
                            <i class="bi bi-chevron-left"></i> Documento anterior
                        </a>
                    @endif

                    @if (!empty($nextDocument))
                        <a href="{{ route($routeNs . '.documents.show', ['document' => $nextDocument->id, 'back_to' => $backUrl]) }}" class="btn btn-primary">
                            Siguiente documento <i class="bi bi-chevron-right"></i>
                        </a>
                    @endif

                    <a href="{{ $backUrl }}" class="btn btn-link">Volver</a>
                </div>
            </div>
        </div>

        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">Revisa los errores.</div>
        @endif

        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-12">
                        <div class="mb-2">
                            <strong>Postulante:</strong>
                            {{ $document->user?->display_name ??
                                ($document->user?->name ??
                                    ($document->user?->full_name ??
                                    trim(($document->user?->first_name ?? '') . ' ' . ($document->user?->last_name ?? '')) ?:
                                        $document->user?->email)) }}
                            <span class="text-muted">({{ $document->user?->email }})</span>
                        </div>
                        <div class="mb-2"><strong>Documento:</strong> {{ $document->type?->label }}</div>
                        <div class="mb-2">
                            <strong>Archivo:</strong>
                            {{ $document->path ? basename($document->path) : '—' }}

                        </div>
                        <div class="mb-2"><strong>Subido:</strong> {{ cl_datetime($document->updated_at) }}</div>
                        <div class="mb-2"><strong>Estado actual:</strong>
                            @if ($document->status === 'pending')
                                <span class="badge text-bg-warning">Pendiente</span>
                            @elseif($document->status === 'approved')
                                <span class="badge text-bg-success">Aprobado</span>
                            @else
                                <span class="badge text-bg-danger">Rechazado</span>
                            @endif
                        </div>
                        @php
                            use Illuminate\Support\Str;
                            $mime = $document->mime ?? ($document->mime_type ?? '');
                            $name = $document->original_name ?? '';
                            $isPdf =
                                Str::of($mime)->lower()->contains('pdf') || Str::of($name)->lower()->endsWith('.pdf');
                        @endphp

                        @if ($isPdf)
                            <div class="mb-3">
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Previsualización (PDF)</label>
                                    </div>
                                    <div class="col-md-8">
                                        <a href="{{ route($routeNs . '.documents.download', $document) }}"
                                            class="btn btn-outline-secondary rounded float-end">
                                            <i class="bi bi-download"></i> Descargar archivo
                                        </a>
                                    </div>
                                </div>

                                <div class="ratio ratio-4x3 border rounded overflow-hidden">
                                    <iframe
                                        src="{{ route($routeNs . '.documents.preview', $document) }}#toolbar=1&navpanes=0&scrollbar=1"
                                        title="Vista previa PDF" style="width:100%; height:100%;"></iframe>
                                </div>
                                <div class="form-text">
                                    Si no ves el PDF, <a href="{{ route($routeNs . '.documents.preview', $document) }}"
                                        target="_blank">ábrelo en una nueva pestaña</a>.
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route($routeNs . '.documents.update', $document) }}">
            @csrf
            @method('PUT')

            <div class="mb-3">
                <label class="form-label required">Estado</label>
                <select name="status" class="form-select" required>
                    <option value="approved" @selected(old('status', $document->status) === 'approved')>Aprobar</option>
                    <option value="rejected" @selected(old('status', $document->status) === 'rejected')>Rechazar</option>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Comentario (opcional)</label>
                <textarea name="reviewer_comment" class="form-control" rows="3">{{ old('reviewer_comment', $document->reviewer_comment) }}</textarea>
                <div class="form-text">Explica el motivo si rechazas.</div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">Guardar</button>
                <a href="{{ $backUrl }}" class="btn btn-outline-secondary">Volver</a>
            </div>
        </form>
    </div>
@endsection
