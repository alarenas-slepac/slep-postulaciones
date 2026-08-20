@extends('layouts.app')

@push('styles')
    @vite('resources/css/centro-operaciones.css')
@endpush

@section('content')
<div class="co-shell co-tickets-shell">
    <header class="co-hero">
        <div class="co-module-identity">
            <div class="co-module-icon co-module-icon--tickets">
                <i class="bi bi-ticket-detailed" aria-hidden="true"></i>
            </div>
            <div>
                <div class="co-eyebrow">Centro de Operaciones</div>
                <h1>Tickets de incidencias</h1>
                <p>Seguimiento y resolución de incidencias según su rol, unidad o establecimiento.</p>
            </div>
        </div>
        <div class="co-hero-actions">
            <span class="co-hero-counter">
                <i class="bi bi-inbox" aria-hidden="true"></i>
                <span><strong>{{ number_format($tickets->total(), 0, ',', '.') }}</strong> visibles</span>
            </span>
            @if(auth()->user()->hasAnyRole(['admin', 'gabinete_slep']))
                <a class="btn btn-outline-primary" href="{{ route('centro-operaciones.configuraciones.index') }}">
                    <i class="bi bi-sliders"></i> Mantenedor
                </a>
            @endif
        </div>
    </header>

    @if(session('success'))
        <div class="alert alert-success co-flash-message">
            <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <section class="co-card">
        <div class="co-card-head">
            <div>
                <span class="co-eyebrow">Bandeja de seguimiento</span>
                <h2>Tickets asignados</h2>
            </div>
            <span class="co-date-chip">
                <i class="bi bi-calendar3" aria-hidden="true"></i>
                {{ now(config('centro_operaciones.timezone'))->translatedFormat('d M Y') }}
            </span>
        </div>

        <div class="table-responsive">
            <table class="table co-table co-ticket-table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Ticket</th>
                        <th>Incidencia</th>
                        <th>Prioridad</th>
                        <th>Establecimiento</th>
                        <th>Responsable / unidad</th>
                        <th>Vencimiento</th>
                        <th>Estado</th>
                        <th><span class="visually-hidden">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($tickets as $ticket)
                    @php
                        $estadoTicket = strtolower($ticket->estado);
                        $estadoLabel = match ($estadoTicket) {
                            'pendiente_asignacion' => 'Pendiente de asignación',
                            'asignado' => 'Asignado',
                            'vencido' => 'Vencido',
                            'escalado' => 'Escalado',
                            'resuelto' => 'Resuelto',
                            default => ucfirst(str_replace('_', ' ', $estadoTicket)),
                        };
                    @endphp
                    <tr>
                        <td>
                            <a class="co-ticket-number" href="{{ route('centro-operaciones.tickets.show', $ticket) }}">
                                <i class="bi bi-ticket-perforated" aria-hidden="true"></i>
                                {{ $ticket->numero }}
                            </a>
                        </td>
                        <td>
                            <div class="co-table-primary">{{ $ticket->incidencia->tipo_label }}</div>
                            <small class="co-table-secondary">{{ ucfirst($ticket->incidencia->severidad ?? 'alerta') }}</small>
                            @if($ticket->incidencia->familia)<small class="co-table-secondary">{{ config("centro_operaciones.familias_incidencia.{$ticket->incidencia->familia}", $ticket->incidencia->familia) }}</small>@endif
                        </td>
                        <td>
                            @if($ticket->incidencia->prioridad_nivel)
                                <span class="co-priority co-priority--{{ strtolower($ticket->incidencia->prioridad_nivel) }}">{{ $ticket->incidencia->prioridad_nivel }}</span>
                                <small class="co-table-secondary">{{ number_format($ticket->incidencia->prioridad_puntaje, 1, ',', '.') }} puntos</small>
                            @else
                                <span class="text-muted">Sin calcular</span>
                            @endif
                        </td>
                        <td>
                            <div class="co-table-primary">{{ $ticket->incidencia->establecimiento?->nombre_establecimiento ?? 'Sin establecimiento' }}</div>
                        </td>
                        <td>
                            <div class="co-table-primary">{{ $ticket->responsable?->nombre_completo ?? 'Sin responsable' }}</div>
                            <small class="co-table-secondary">{{ $ticket->unidad_departamento }}</small>
                            @if($ticket->segundoResponsable)
                                <small class="co-table-secondary">También: {{ $ticket->segundoResponsable->nombre_completo }}</small>
                            @endif
                        </td>
                        <td>
                            <div class="co-deadline {{ in_array($estadoTicket, ['vencido', 'escalado'], true) ? 'co-deadline--late' : '' }}">
                                <i class="bi bi-clock" aria-hidden="true"></i>
                                <span>
                                    <strong>{{ $ticket->vence_en?->format('d/m/Y') ?? 'Sin plazo' }}</strong>
                                    <small>{{ $ticket->vence_en ? $ticket->vence_en->format('H:i').' hrs.' : 'Pendiente de asignación' }}</small>
                                </span>
                            </div>
                        </td>
                        <td><span class="co-ticket-status co-ticket-status--{{ $estadoTicket }}"><i></i>{{ $estadoLabel }}</span></td>
                        <td class="text-end">
                            <a href="{{ route('centro-operaciones.tickets.show', $ticket) }}" class="btn btn-sm btn-outline-primary co-action-button" aria-label="Ver ticket {{ $ticket->numero }}">
                                Ver detalle <i class="bi bi-arrow-right" aria-hidden="true"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">
                            <div class="co-empty co-empty--large">
                                <i class="bi bi-inbox" aria-hidden="true"></i>
                                <div><strong>No hay tickets en este ámbito</strong><span>Los nuevos tickets asignados aparecerán en esta bandeja.</span></div>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if($tickets->hasPages())
            <div class="co-card-footer">{{ $tickets->links() }}</div>
        @endif
    </section>
</div>
@endsection
