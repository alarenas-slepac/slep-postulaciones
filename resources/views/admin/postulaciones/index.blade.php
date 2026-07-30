@extends('layouts.app')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="h4 mb-0">Postulaciones</h1>
            <div class="text-muted small">Listado administrativo de postulaciones registradas en el sistema.</div>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.postulaciones.index') }}" class="row g-3 align-items-end">
                <div class="col-12 col-lg-4">
                    <label class="form-label">Buscar</label>
                    <input type="text" name="q" value="{{ $filters['q'] }}" class="form-control"
                        placeholder="RUT, nombre o correo">
                </div>
                <div class="col-12 col-md-6 col-lg-3">
                    <label class="form-label">Área de desempeño</label>
                    <select name="area_desempeno_id" class="form-select">
                        <option value="">Todas</option>
                        @foreach ($areas as $area)
                            <option value="{{ $area->id }}" @selected((int) $filters['area_desempeno_id'] === (int) $area->id)>
                                {{ $area->nombre }} @if ($area->estamento)
                                    ({{ ucfirst($area->estamento) }})
                                @endif
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-6 col-lg-2">
                    <label class="form-label">Comuna que postula</label>
                    <select name="commune_id" class="form-select">
                        <option value="">Todas</option>
                        @foreach ($communes as $commune)
                            <option value="{{ $commune->id }}" @selected((int) $filters['commune_id'] === (int) $commune->id)>
                                {{ $commune->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-6 col-lg-2">
                    <label class="form-label">Mención</label>
                    <input type="text" name="mencion" value="{{ $filters['mencion'] }}" class="form-control"
                        list="menciones-list" placeholder="Filtrar por mención">
                    <datalist id="menciones-list">
                        @foreach ($menciones as $mencion)
                            <option value="{{ $mencion }}"></option>
                        @endforeach
                    </datalist>
                </div>
                <div class="col-6 col-md-3 col-lg-1">
                    <label class="form-label">Por pág.</label>
                    <select name="per_page" class="form-select">
                        @foreach ([10, 15, 25, 50] as $pp)
                            <option value="{{ $pp }}" @selected((int) $filters['per_page'] === $pp)>{{ $pp }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3 col-lg-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <a href="{{ route('admin.postulaciones.index') }}" class="btn btn-link text-decoration-none px-0">Limpiar filtros</a>
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>RUT normalizado</th>
                            <th>Nombre completo</th>
                            <th>Comuna que postula</th>
                            <th>Estamento</th>
                            <th>Área de desempeño</th>
                            <th>Mención</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($profiles as $profile)
                            @php
                                $user = $profile->user;
                                $rut = strtoupper(preg_replace('/[^0-9Kk]/', '', (string) ($user->rut ?? '')));
                                $rutFmt = $rut && strlen($rut) > 1 ? substr($rut, 0, -1) . '-' . substr($rut, -1) : '—';
                                $comunasPostula = $user?->communes?->pluck('name')->filter()->values() ?? collect();
                            @endphp
                            <tr>
                                <td class="text-nowrap">{{ $rutFmt }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $user?->nombre_completo ?: $user?->email }}</div>
                                    <div class="small text-muted">{{ $user?->email }}</div>
                                </td>
                                <td>
                                    @if ($comunasPostula->isNotEmpty())
                                        {{ $comunasPostula->join(', ') }}
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>{{ $profile->estamento ? ucfirst($profile->estamento) : '—' }}</td>
                                <td>{{ $profile->areaDesempeno?->nombre ?? '—' }}</td>
                                <td>{{ $profile->mencion ?: '—' }}</td>
                                <td>
                                    <div class="d-flex justify-content-end">
                                        <a href="{{ route('admin.postulaciones.show', $profile) }}" class="btn btn-sm btn-outline-primary"
                                            data-bs-toggle="tooltip" title="Ver postulación completa">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No hay postulaciones para los filtros aplicados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="text-muted small">Mostrando {{ $profiles->count() }} de {{ $profiles->total() }} postulaciones.</div>
            {{ $profiles->links() }}
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => {
                new bootstrap.Tooltip(el);
            });
        });
    </script>
@endpush
