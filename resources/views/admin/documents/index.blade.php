@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h3 class="m-0">Documentos — Resumen por Usuario</h3>
        </div>
        {{-- Resumen global --}}
        <div class="d-flex flex-wrap gap-2 mb-3">
            {{-- Documentos --}}
            <span class="badge rounded-pill text-bg-warning">
                Docs pendientes: {{ $globalPendingCount ?? 0 }}
            </span>
            <span class="badge rounded-pill text-bg-info">
                Docs nuevos (72h): {{ $globalNew72hCount ?? 0 }}
            </span>

            {{-- Personas --}}
            <span class="badge rounded-pill text-bg-warning">
                Personas pendientes: {{ $globalPendingPeopleCount ?? 0 }}
            </span>
            <span class="badge rounded-pill text-bg-info">
                Personas nuevas (72h): {{ $globalNew72hPeopleCount ?? 0 }}
            </span>
            <span class="badge rounded-pill text-bg-success">
                Personas revisadas: {{ $globalReviewedPeopleCount ?? 0 }}
            </span>
        </div>
        {{-- Buscador / paginación server-side --}}
        <form id="summarySearchForm" method="GET" action="{{ route('admin.documents.index') }}"
              class="d-flex justify-content-end gap-2 mb-3">
            <div class="input-group" style="max-width:420px;">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input id="summarySearch" name="q" type="search" class="form-control"
                       placeholder="Buscar por nombre, RUT o correo"
                       value="{{ request('q') }}">
            </div>

            <select id="perPageSelect" name="per_page" class="form-select" style="max-width:140px;">
                @php $pp = (int) request('per_page', 25); @endphp
                @foreach([10,25,50,100] as $n)
                    <option value="{{ $n }}" {{ $pp === $n ? 'selected' : '' }}>{{ $n }} / pág.</option>
                @endforeach
            </select>

            <button class="btn btn-outline-secondary" type="submit">
                Buscar
            </button>

            @if(request('q'))
                <a class="btn btn-outline-light" href="{{ route('admin.documents.index', ['per_page' => $pp]) }}"
                   title="Quitar filtro">
                    Limpiar
                </a>
            @endif
        </form>
        
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="small text-muted">
                Mostrando {{ $rows->firstItem() ?? 0 }}–{{ $rows->lastItem() ?? 0 }} de {{ $rows->total() }} usuarios
            </div>
            <div class="small text-muted">
                <span class="badge text-bg-info me-1">Azul</span> nuevos (72h)
                <span class="badge text-bg-warning ms-2 me-1">Amarillo</span> pendientes
            </div>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="table align-middle m-0">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th class="text-center">Archivos</th>
                            <th style="width:220px">Avance</th>
                            <th style="width:130px" class="text-nowrap">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            @php
                                $u = $row['user'];
                                $name = $u->display_name
                                    ?? ($u->name ?? ($u->full_name ?? trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) ?: $u->email));

                                $rowClass = '';
                                if (($row['new_count'] ?? 0) > 0) {
                                    $rowClass = 'table-info';
                                } elseif (($row['pending_count'] ?? 0) > 0) {
                                    $rowClass = 'table-warning';
                                }
                            @endphp

                            <tr class="{{ $rowClass }}">
                                <td>
                                    <div class="fw-semibold">{{ $name }}</div>
                                    <div class="small text-muted">{{ $u->email }}</div>
                                    @if(!empty($u->rut))
                                        <div class="small text-muted">RUT: {{ $u->rut }}</div>
                                    @endif
                                </td>

                                <td class="text-center">
                                    <span class="fw-semibold">{{ $row['uploaded'] }}</span> / {{ $row['total'] }}

                                    @if (($row['pending_count'] ?? 0) > 0)
                                        <span class="badge rounded-pill text-bg-warning ms-2" title="Pendientes de revisión"
                                              data-bs-toggle="tooltip">
                                            <i class="bi bi-hourglass-split me-1"></i>{{ $row['pending_count'] }}
                                        </span>
                                    @endif

                                    @if (($row['new_count'] ?? 0) > 0)
                                        <span class="badge rounded-pill text-bg-info ms-2"
                                              title="Nuevos pendientes en las últimas 72h" data-bs-toggle="tooltip">
                                            <i class="bi bi-stars me-1"></i>{{ $row['new_count'] }}
                                        </span>
                                    @endif

                                    @if (!empty($row['oldest_pending_at']))
                                        <div class="small text-muted mt-1">
                                            Más antiguo: {{ cl_datetime($row['oldest_pending_at']) }}
                                        </div>
                                    @endif
                                </td>

                                <td>
                                    <div class="progress" role="progressbar" aria-valuenow="{{ $row['percent'] }}"
                                         aria-valuemin="0" aria-valuemax="100">
                                        <div class="progress-bar" style="width: {{ $row['percent'] }}%;">
                                            {{ $row['percent'] }}%
                                        </div>
                                    </div>
                                </td>

                                <td class="text-nowrap">
                                    <a href="{{ route('admin.documents.forUser', $u) }}"
                                       class="btn btn-sm btn-outline-primary" title="Ver documentos"
                                       aria-label="Ver documentos" data-bs-toggle="tooltip">
                                        <i class="bi bi-folder2-open"></i>
                                        <span class="visually-hidden">Ver documentos</span>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">Sin usuarios.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="d-flex justify-content-center mt-3">
            {{ $rows->links() }}
        </div>
    </div>

    @push('scripts')
        <script>
            (function() {
                const form = document.getElementById('summarySearchForm');
                const input = document.getElementById('summarySearch');
                const perPage = document.getElementById('perPageSelect');
                if (!form || !input) return;

                // Auto-enviar al escribir (debounce)
                let t;
                input.addEventListener('input', () => {
                    clearTimeout(t);
                    t = setTimeout(() => form.submit(), 450);
                });

                if (perPage) {
                    perPage.addEventListener('change', () => form.submit());
                }
            })();
        </script>
    @endpush
@endsection
