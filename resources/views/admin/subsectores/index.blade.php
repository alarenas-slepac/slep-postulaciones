@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h3 class="m-0">Subsectores</h3>
            <a href="{{ route('admin.subsectores.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg"></i> Nuevo subsector
            </a>
        </div>

        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if ($errors->has('general'))
            <div class="alert alert-danger">{{ $errors->first('general') }}</div>
        @endif

        <form method="GET" class="row g-2 align-items-end mb-3">
            <div class="col-md-6">
                <label class="form-label">Buscar</label>
                <input type="text" name="q" value="{{ $q }}" class="form-control"
                    placeholder="Nombre de subsector">
            </div>
            <div class="col-md-3">
                <button class="btn btn-outline-secondary"><i class="bi bi-search"></i> Filtrar</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead>
                    <tr>
                        <th>Subsector</th>
                        <th style="width:120px">Menciones</th> {{-- 👈 NUEVO --}}
                        <th style="width:140px">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $s)
                        <tr>
                            <td>{{ $s->subsector }}</td>
                            <td>
                                <a href="{{ route('admin.menciones.index', ['subsector_id' => $s->id]) }}"
                                    class="badge text-bg-secondary">
                                    {{ $s->menciones_count }}
                                </a>
                            </td>
                            <td>
                                <a href="{{ route('admin.subsectores.edit', $s) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal"
                                    data-bs-target="#confirmDeleteSubsectorModal"
                                    data-delete-url="{{ route('admin.subsectores.destroy', $s) }}"
                                    data-name="{{ $s->subsector }}">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center text-muted py-4">Sin subsectores.</td>
                            {{-- 👈 colspan=3 --}}
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $items->links() }}
    </div>

    {{-- Modal eliminar --}}
    <div class="modal fade" id="confirmDeleteSubsectorModal" tabindex="-1" aria-labelledby="confirmDeleteSubsectorLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" class="modal-content" id="deleteSubsectorForm">
                @csrf
                @method('DELETE')
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmDeleteSubsectorLabel">Eliminar subsector</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    ¿Seguro que deseas eliminar el subsector <strong id="deleteSubsectorName">este subsector</strong>?
                    <div class="text-danger small mt-2">Si tiene menciones asociadas, no podrá eliminarse.</div>
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
            const modalEl = document.getElementById('confirmDeleteSubsectorModal');
            const form = document.getElementById('deleteSubsectorForm');
            const nameEl = document.getElementById('deleteSubsectorName');

            modalEl.addEventListener('show.bs.modal', function(evt) {
                const btn = evt.relatedTarget;
                const url = btn.getAttribute('data-delete-url');
                const name = btn.getAttribute('data-name') || 'este subsector';
                form.setAttribute('action', url);
                nameEl.textContent = name;
            });
        });
    </script>
@endpush
