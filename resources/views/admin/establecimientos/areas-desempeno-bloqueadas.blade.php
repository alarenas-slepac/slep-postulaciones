@extends('layouts.app')

@section('content')
    <div class="container">

        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h4 class="mb-0">Áreas de desempeño bloqueadas (Sobredotación)</h4>
                <div class="text-muted small">
                    Establecimiento: <strong>{{ $establecimiento->nombre_establecimiento ?? ($establecimiento->nombre ?? '-') }}</strong>
                    @if (!empty($establecimiento->rbd))
                        | RBD: {{ $establecimiento->rbd }}
                    @endif
                </div>
            </div>

            <div class="d-flex gap-2">
                <a href="{{ route('admin.establecimientos.show', $establecimiento) }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left"></i> Volver
                </a>
            </div>
        </div>

        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        <div class="alert alert-warning">
            <div class="fw-semibold mb-1">Importante</div>
            Al marcar un área como <strong>bloqueada</strong>, seguirá apareciendo en la solicitud de reemplazo,
            pero el sistema mostrará una advertencia de sobredotación al seleccionarla.
        </div>

        <form method="POST" action="{{ route('admin.establecimientos.areas-desempeno-bloqueadas.update', $establecimiento) }}">
            @csrf
            @method('PUT')

            <div class="card">
                <div class="card-header fw-semibold">Configuración por área</div>
                <div class="card-body">
                    @php
                        $groups = $areas->groupBy('estamento');
                        $labels = ['docente' => 'Docente', 'asistente' => 'Asistente'];
                    @endphp

                    @foreach ($labels as $key => $label)
                        @php $list = $groups[$key] ?? collect(); @endphp
                        @if ($list->isEmpty())
                            @continue
                        @endif

                        <div class="mb-4">
                            <div class="fw-semibold mb-2">{{ $label }}</div>

                            <div class="table-responsive">
                                <table class="table table-sm align-middle">
                                    <thead>
                                        <tr>
                                            <th>Área</th>
                                            <th class="text-end">Bloqueada</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($list as $a)
                                            <tr>
                                                <td>{{ $a->nombre }}</td>
                                                <td class="text-end">
                                                    <div class="form-check form-switch d-inline-flex justify-content-end">
                                                        <input class="form-check-input" type="checkbox" role="switch"
                                                            id="area_{{ $a->id }}" name="bloqueadas[]"
                                                            value="{{ $a->id }}" @checked($bloqueadas[$a->id] ?? false)>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="card-footer d-flex justify-content-end gap-2">
                    <a href="{{ route('admin.establecimientos.show', $establecimiento) }}" class="btn btn-outline-secondary">Cancelar</a>
                    <button class="btn btn-primary" type="submit">Guardar</button>
                </div>
            </div>
        </form>

    </div>
@endsection
