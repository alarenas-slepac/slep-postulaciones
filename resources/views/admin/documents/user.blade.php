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
                    <a href="{{ route('admin.documents.user.profile.view', $user) }}" class="btn btn-sm btn-outline-secondary"
                        target="_blank" rel="noopener" title="Ver perfil en PDF">
                        <i class="bi bi-file-earmark-text"></i> Ver perfil (PDF)
                    </a>
                    <a href="{{ route('admin.documents.user.profile.pdf', $user) }}" class="btn btn-sm btn-outline-primary"
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
                <a href="{{ $approved ?? 0 ? route('admin.documents.downloadApproved', $user) : '#' }}"
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
                                        {{-- Revisar --}}
                                        <a href="{{ route('admin.documents.show', ['document' => $d->id, 'back_to' => url()->full()]) }}"
                                            class="btn btn-sm btn-outline-primary" title="Revisar" aria-label="Revisar"
                                            data-bs-toggle="tooltip">
                                            <i class="bi bi-eye"></i>
                                            <span class="visually-hidden">Revisar</span>
                                        </a>
                                        {{-- Descargar --}}
                                        <a href="{{ route('admin.documents.download', $d) }}"
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
                                            <a href="{{ route('admin.documents.preview', $d) }}"
                                                class="btn btn-sm btn-outline-info" title="Previsualizar"
                                                aria-label="Previsualizar" data-bs-toggle="tooltip" target="_blank">
                                                <i class="bi bi-file-earmark-richtext"></i>
                                                <span class="visually-hidden">Previsualizar</span>
                                            </a>
                                        @endif
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
                        : route('admin.documents.index');
            @endphp

            <a href="{{ $backUrl }}" class="btn btn-outline-secondary">Volver</a>

        </div>
    </div>
@endsection
