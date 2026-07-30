@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="mb-4">
        <h1 class="h3 mb-1">Mis liquidaciones</h1>
        <p class="text-muted mb-0">Aquí aparecerán las liquidaciones de sueldo asociadas a tu RUT cuando correspondan a reemplazos o suplencias.</p>
    </div>

    @if ($rutNormalizado === '')
        <div class="alert alert-warning">Tu cuenta no tiene RUT registrado, por lo que no es posible buscar liquidaciones.</div>
    @endif

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Periodo</th>
                        <th>Dominio</th>
                        <th>Contrato detectado</th>
                        <th>RUT asociado</th>
                        <th class="text-end">Página</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr>
                            <td>{{ $item->mesNombre() }} {{ $item->anio }}</td>
                            <td>{{ $item->dominio }}</td>
                            <td>{{ $item->tipo_contrato_detectado ?: 'Reemplazo detectado' }}</td>
                            <td><code>{{ $item->rut_normalizado }}</code></td>
                            <td class="text-end">{{ $item->pagina_origen }}</td>
                            <td class="text-end">
                                <a href="{{ route('liquidaciones.mis.ver', $item) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i> Ver</a>
                                <a href="{{ route('liquidaciones.mis.descargar', $item) }}" class="btn btn-sm btn-primary"><i class="bi bi-download"></i> Descargar</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No tienes liquidaciones publicadas para reemplazos o suplencias.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $items->links() }}</div>
</div>
@endsection
