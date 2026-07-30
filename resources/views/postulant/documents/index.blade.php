@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h3 class="m-0">Mis documentos</h3>
            <a href="{{ route('postulant.profile.edit') }}" class="btn btn-outline-secondary">
                <i class="bi bi-person-badge"></i> Mi Perfil
            </a>
            <a href="{{ route('postulant.profile.pdf') }}" class="btn btn-sm btn-outline-primary"
                title="Descargar mi perfil en PDF">
                <i class="bi bi-file-earmark-arrow-down"></i> Descargar mi perfil
            </a>
        </div>

        @if (session('warning'))
            <div class="alert alert-warning">{{ session('warning') }}</div>
        @endif
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">Revisa los errores del formulario.</div>
        @endif

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Documento</th>
                        <th>Archivo</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($documentTypes as $type)
                        @php
                            $doc = $byTypeId[$type->id] ?? null;
                            $isRequired = isset($requiredTypeIds[(int) $type->id]);
                        @endphp
                        <tr>
                            <td>
                                <div class="fw-semibold">
                                    {{ $type->label }}
                                    @unless ($isRequired)
                                        <span class="badge text-bg-light ms-1">Opcional</span>
                                    @endunless
                                </div>
                                @if ($type->template_path)
                                    <a class="small" href="{{ route('postulant.documents.template', $type) }}">
                                        <i class="bi bi-download"></i> Descargar plantilla
                                    </a>
                                @endif
                                @unless ($isRequired)
                                    <div class="small text-muted mt-1">Este documento sólo aplica si registras experiencia laboral y no afecta tu completitud documental.</div>
                                @endunless
                            </td>
                            <td>
                                @if ($doc)
                                    {{-- Nombre de archivo físico (renombrado) --}}
                                    <div class="fw-semibold">
                                        {{ $doc->path ? basename($doc->path) : '—' }}
                                    </div>
                                    <div class="small text-muted">
                                        {{ cl_datetime($doc->updated_at) }}</div>
                                @else
                                    <span class="text-muted">Sin archivo</span>
                                @endif
                            </td>
                            <td>
                                @if (!$doc)
                                    @if ($isRequired)
                                        <span class="badge text-bg-secondary">Pendiente</span>
                                    @else
                                        <span class="badge text-bg-light">Opcional</span>
                                    @endif
                                @else
                                    @if ($doc->status === 'pending')
                                        <span class="badge text-bg-warning">Pendiente</span>
                                    @elseif($doc->status === 'approved')
                                        <span class="badge text-bg-success">Aprobado</span>
                                    @else
                                        <span class="badge text-bg-danger"
                                            title="{{ $doc->reviewer_comment }}">Rechazado</span>
                                        @if ($doc->reviewer_comment)
                                            <div class="small text-muted mt-1">{{ $doc->reviewer_comment }}</div>
                                        @endif
                                    @endif
                                @endif
                            </td>
                            <td style="width: 320px;">
                                <form method="POST" action="{{ route('postulant.documents.store', $type) }}"
                                    enctype="multipart/form-data" class="d-flex align-items-center gap-2">
                                    @csrf
                                    <input type="file" name="file" id="file"
                                        class="form-control @error('file') is-invalid @enderror"
                                        accept="application/pdf,.pdf" required>
                                    <div class="form-text">Solo PDF (máx 10 MB).</div>
                                    @error('file')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <button type="submit" class="btn btn-sm btn-primary">
                                        {{ $doc ? 'Reemplazar' : 'Subir' }}
                                    </button>
                                </form>
                                @error('file')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted">No hay documentos disponibles para tu perfil.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
