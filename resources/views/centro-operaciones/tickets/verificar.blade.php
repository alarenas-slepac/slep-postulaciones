@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="card border-0 shadow-sm mx-auto" style="max-width: 900px;">
        <div class="card-header bg-white border-0 px-4 pt-4">
            <div class="text-uppercase small fw-semibold text-primary mb-1">Centro de Operaciones</div>
            <h1 class="h4 mb-0">Verificación documental de ticket</h1>
        </div>
        <div class="card-body px-4 pb-4">
            @if($ticket)
                <div class="alert {{ $integridad['integro'] ? 'alert-success' : 'alert-danger' }} d-flex align-items-start gap-2">
                    <i class="bi {{ $integridad['integro'] ? 'bi-shield-check' : 'bi-shield-exclamation' }} fs-5"></i>
                    <div>
                        <strong>{{ $integridad['integro'] ? 'Registro documental válido e íntegro.' : 'No fue posible confirmar la integridad de los datos registrados.' }}</strong>
                        <div class="small">El código corresponde a un ticket registrado en la plataforma institucional.</div>
                    </div>
                </div>

                <dl class="row mb-0">
                    <dt class="col-md-4">Ticket</dt><dd class="col-md-8">{{ $ticket->numero }}</dd>
                    <dt class="col-md-4">Incidencia</dt><dd class="col-md-8">{{ $ticket->incidencia->tipo_label }}</dd>
                    <dt class="col-md-4">Establecimiento</dt><dd class="col-md-8">{{ $ticket->incidencia->establecimiento?->nombre_establecimiento ?? 'Sin establecimiento' }}</dd>
                    <dt class="col-md-4">Estado</dt><dd class="col-md-8">{{ ucfirst(str_replace('_', ' ', $ticket->estado)) }}</dd>
                    <dt class="col-md-4">Código de validación</dt><dd class="col-md-8"><code>{{ $ticket->codigo_validacion }}</code></dd>
                    <dt class="col-md-4">Fecha de emisión</dt><dd class="col-md-8">{{ $ticket->documento_emitido_en?->format('d-m-Y H:i') ?? 'Sin emisión registrada' }}</dd>
                    <dt class="col-md-4">Huella de datos SHA-256</dt><dd class="col-md-8"><code class="text-break">{{ $ticket->documento_hash }}</code></dd>
                </dl>

                <hr>
                <h2 class="h6">Firma electrónica simple</h2>
                @if($ticket->firmaResolucion)
                    <p class="mb-0">
                        <strong>{{ $ticket->firmaResolucion->nombre_firmante }}</strong><br>
                        {{ $ticket->firmaResolucion->cargo_firmante ?: $ticket->firmaResolucion->rol_firmante ?: 'Funcionario responsable' }}<br>
                        <span class="text-muted">Firmado el {{ $ticket->firmaResolucion->fecha_firma?->format('d-m-Y H:i') }} hrs.</span>
                    </p>
                @else
                    <p class="text-muted mb-0">El ticket aún no ha sido resuelto y no registra firma de cierre.</p>
                @endif
            @else
                <div class="alert alert-danger mb-0">No se encontró un ticket asociado al código de validación ingresado.</div>
            @endif
        </div>
    </div>
</div>
@endsection
