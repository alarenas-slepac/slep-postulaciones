@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h3 class="m-0">Menciones</h3>
            <a href="{{ route('admin.menciones.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg"></i> Nueva mención
            </a>
        </div>

        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        {{-- Filtros --}}
        <form method="GET" class="row g-2 align-items-end mb-3">
            <div class="col-md-4">
                <label class="form-label">Buscar</label>
                <input type="text" name="q" value="{{ $q }}" class="form-control"
                    placeholder="Nombre, universidad o año">
            </div>
            <div class="col-md-4">
                <label class="form-label">Subsector</label>
                <select name="subsector_id" class="form-select">
                    <option value="">Todos</option>
                    @foreach ($subsectores as $s)
                        <option value="{{ $s->id }}" @selected($subsector == $s->id)>{{ $s->subsector }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <button class="btn btn-outline-secondary"><i class="bi bi-search"></i> Filtrar</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Universidad</th>
                        <th style="width:120px">Año</th>
                        <th>Subsector</th>
                        <th style="width:140px">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $m)
                        <tr>
                            <td>{{ $m->nombre }}</td>
                            <td>{{ $m->universidad ?? '—' }}</td>
                            <td>{{ $m->anio ?? '—' }}</td>
                            <td>{{ $m->subsector?->subsector ?? '—' }}</td>
                            <td>
                                <a href="{{ route('admin.menciones.edit', $m) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal"
                                    data-bs-target="#confirmDeleteMencionModal"
                                    data-delete-url="{{ route('admin.menciones.destroy', $m) }}"
                                    data-name="{{ $m->nombre }}">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">Sin menciones.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $items->links() }}
    </div>

    {{-- Modal eliminar --}}
    <div class="modal fade" id="confirmDeleteMencionModal" tabindex="-1" aria-labelledby="confirmDeleteMencionLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" class="modal-content" id="deleteMencionForm">
                @csrf
                @method('DELETE')
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmDeleteMencionLabel">Eliminar mención</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    ¿Seguro que deseas eliminar la mención <strong id="deleteMencionName">esta mención</strong>?
                    <div class="text-danger small mt-2">Esta acción no se puede deshacer.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Eliminar</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const modalEl = document.getElementById('confirmDeleteMencionModal');
            const form = document.getElementById('deleteMencionForm');
            const nameEl = document.getElementById('deleteMencionName');

            modalEl.addEventListener('show.bs.modal', function(evt) {
                const btn = evt.relatedTarget;
                const url = btn.getAttribute('data-delete-url');
                const name = btn.getAttribute('data-name') || 'esta mención';
                form.setAttribute('action', url);
                nameEl.textContent = name;
            });
        });
    </script>
@endpush
