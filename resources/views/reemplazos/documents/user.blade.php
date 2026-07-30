@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h3 class="m-0">Documentos del usuario</h3>
                <div class="text-muted">
                    {{ $user->display_name ??
                        ($user->name ??
                            ($user->full_name ?? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: $user->email)) }}
                    <span class="ms-2">({{ $user->email }})</span>
                    <a href="{{ route('reemplazos.documents.user.profile.view', $user) }}" class="btn btn-sm btn-outline-secondary"
                        target="_blank" rel="noopener" title="Ver perfil en PDF">
                        <i class="bi bi-file-earmark-text"></i> Ver perfil (PDF)
                    </a>
                    <a href="{{ route('reemplazos.documents.user.profile.pdf', $user) }}" class="btn btn-sm btn-outline-primary"
                        title="Descargar perfil en PDF">
                        <i class="bi bi-file-earmark-arrow-down"></i> Descargar perfil (PDF)
                    </a>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2" style="min-width:220px;">
                <div class="flex-grow-1">
                    <div class="progress" role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0"
                        aria-valuemax="100">
                        <div class="progress-bar" style="width: {{ $percent }}%;">{{ $percent }}%</div>
                    </div>
                    <div class="small text-muted mt-1">{{ $uploaded }} / {{ $total }} documentos requeridos</div>
                </div>

                {{-- Botón ZIP --}}
                @if (($newCount ?? 0) > 0)
                    <span class="badge rounded-pill text-bg-info" title="Nuevos pendientes en las últimas 72h"
                        data-bs-toggle="tooltip">
                        <i class="bi bi-stars me-1"></i>{{ $newCount }} nuevos
                    </span>
                @endif
                <a href="{{ $approved ?? 0 ? route('reemplazos.documents.downloadApproved', $user) : '#' }}"
                    class="btn btn-outline-success-dark {{ $approved ?? 0 ? '' : 'disabled' }}"
                    title="{{ $approved ?? 0 ? 'Descargar aprobados (ZIP)' : 'Sin documentos aprobados' }}"
                    aria-label="Descargar aprobados (ZIP)" data-bs-toggle="tooltip">
                    <i class="bi bi-file-earmark-zip"></i>
                    <span class="visually-hidden">Descargar aprobados</span>
                </a>
            </div>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="table align-middle m-0">
                    <thead>
                        <tr>
                            <th>Documento</th>
                            <th>Estado</th>
                            <th>Revisado por</th> {{-- NUEVA --}}
                            <th>Fecha revisión</th> {{-- NUEVA --}}
                            <th style="width:160px" class="text-nowrap">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $row)
                            @php
                                $t = $row['type'];
                                $d = $row['doc']; // UserDocument|null
                            @endphp
                            <tr>
                                <td class="fw-semibold">
                                    {{ $t->label }}
                                    @unless ($row['is_required'] ?? true)
                                        <span class="badge text-bg-light ms-1">Opcional</span>
                                    @endunless
                                </td>
                                <td>
                                    @if ($d)
                                        @if ($d->status === 'approved')
                                            <span class="badge text-bg-success">Aprobado</span>
                                        @elseif ($d->status === 'rejected')
                                            <span class="badge text-bg-danger">Rechazado</span>
                                        @else
                                            <span class="badge text-bg-warning">Pendiente</span>
                                        @endif
                                        @if ($d->reviewer_comment)
                                            <div class="small text-muted mt-1">{{ $d->reviewer_comment }}</div>
                                        @endif
                                    @elseif ($row['is_required'] ?? true)
                                        <span class="badge text-bg-secondary">Faltante</span>
                                    @else
                                        <span class="badge text-bg-light">Opcional</span>
                                    @endif
                                </td>
                                @php
                                    /** @var \App\Models\UserDocument|null $d */
                                    $d = $row['doc'] ?? null;

                                    $rev = $d?->reviewer;
                                    // Preferimos 'name'; si no existe, concatenamos first_name + last_name; si tampoco, display_name o email
                                    $revName =
                                        $rev?->name ?? trim(($rev?->first_name ?? '') . ' ' . ($rev?->last_name ?? ''));
                                    if ($revName === '') {
                                        $revName = $rev?->display_name ?? ($rev?->email ?? '—');
                                    }
                                @endphp

                                <td>
                                    {{ $rev ? $revName : '—' }}
                                </td>

                                <td>
                                    {{-- Fecha/hora de revisión segura ante null --}}
                                    {{ cl_datetime($d?->reviewed_at) }}
                                </td>

                                <td class="text-nowrap">
                                    @if ($d)
                                        {{-- Ver (detalle de revisión) --}}
                                        @can('review', $d)
                                            <a href="{{ route('reemplazos.documents.show', ['document' => $d->id, 'back_to' => url()->full()]) }}"
                                                class="btn btn-sm btn-outline-primary" title="Ver"
                                                aria-label="Ver" data-bs-toggle="tooltip">
                                                <i class="bi bi-eye"></i>
                                                <span class="visually-hidden">Ver</span>
                                            </a>
                                        @endcan
                                        {{-- Descargar --}}
                                        <a href="{{ route('reemplazos.documents.download', $d) }}"
                                            class="btn btn-sm btn-outline-secondary" title="Descargar"
                                            aria-label="Descargar" data-bs-toggle="tooltip">
                                            <i class="bi bi-download"></i>
                                            <span class="visually-hidden">Descargar</span>
                                        </a>
                                        {{-- (Opcional) Preview si es PDF --}}
                                        @php
                                            $isPdf =
                                                $d->mime === 'application/pdf' ||
                                                \Illuminate\Support\Str::endsWith(strtolower($d->path), '.pdf');
                                        @endphp
                                        @if ($isPdf)
                                            <a href="{{ route('reemplazos.documents.preview', $d) }}"
                                                class="btn btn-sm btn-outline-info" title="Previsualizar"
                                                aria-label="Previsualizar" data-bs-toggle="tooltip" target="_blank">
                                                <i class="bi bi-file-earmark-richtext"></i>
                                                <span class="visually-hidden">Previsualizar</span>
                                            </a>
                                        @endif


                                        @can('review', $d)
                                            <form action="{{ route('reemplazos.documents.update', $d) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" name="status" value="approved">
                                    <button type="submit" class="btn btn-sm btn-outline-success-dark" title="Aprobar" aria-label="Aprobar" data-bs-toggle="tooltip">
                                                    <i class="bi bi-check2-circle"></i>
                                                    <span class="visually-hidden">Aprobar</span>
                                                </button>
                                            </form>

                                            <button type="button" class="btn btn-sm btn-outline-danger" title="Rechazar" aria-label="Rechazar" data-bs-toggle="modal" data-bs-target="#rejectDoc{{ $d->id }}">
                                                <i class="bi bi-x-circle"></i>
                                                <span class="visually-hidden">Rechazar</span>
                                            </button>

                                            <div class="modal fade" id="rejectDoc{{ $d->id }}" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Rechazar documento</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                                        </div>
                                                        <form action="{{ route('reemplazos.documents.update', $d) }}" method="POST">
                                                            @csrf
                                                            @method('PUT')
                                                            <div class="modal-body">
                                                                <input type="hidden" name="status" value="rejected">
                                                                <div class="mb-3">
                                                                    <label class="form-label">Motivo de rechazo <span class="text-danger">*</span></label>
                                                                    <textarea name="reviewer_comment" class="form-control" rows="3" required maxlength="5000"></textarea>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                                <button type="submit" class="btn btn-danger">Rechazar</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        @endcan

                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">No hay documentos disponibles.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-3">
            @php
                use Illuminate\Support\Str;
                $returnTo = request('return_to');
                $backUrl =
                    $returnTo && (Str::startsWith($returnTo, url('/')) || Str::startsWith($returnTo, '/'))
                        ? $returnTo
                        : route('reemplazos.buscador-postulantes.index');
            @endphp

            <a href="{{ $backUrl }}" class="btn btn-outline-secondary">Volver</a>

        </div>
    </div>
@endsection
