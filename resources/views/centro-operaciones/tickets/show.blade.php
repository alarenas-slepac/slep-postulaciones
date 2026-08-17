@extends('layouts.app')

@push('styles')
    @vite('resources/css/centro-operaciones.css')
@endpush

@section('content')
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
<div class="co-shell co-ticket-detail-shell">
    <header class="co-hero">
        <div class="co-module-identity">
            <div class="co-module-icon co-module-icon--{{ in_array($estadoTicket, ['vencido', 'escalado'], true) ? 'urgent' : 'tickets' }}">
                <i class="bi {{ $estadoTicket === 'resuelto' ? 'bi-check2-circle' : 'bi-ticket-detailed' }}" aria-hidden="true"></i>
            </div>
            <div>
                <div class="co-eyebrow">Ticket de incidencia</div>
                <h1>{{ $ticket->numero }}</h1>
                <p>{{ $ticket->incidencia->tipo_label }} · {{ $ticket->incidencia->establecimiento?->nombre_establecimiento ?? 'Sin establecimiento' }}</p>
            </div>
        </div>
        <div class="co-hero-actions">
            <span class="co-ticket-status co-ticket-status--{{ $estadoTicket }} co-ticket-status--large"><i></i>{{ $estadoLabel }}</span>
            <a class="btn btn-primary" href="{{ route('centro-operaciones.tickets.pdf', $ticket) }}" target="_blank" rel="noopener">
                <i class="bi bi-file-earmark-pdf"></i> Ver informe PDF
            </a>
            <a class="btn btn-outline-secondary" href="{{ route('centro-operaciones.tickets.index') }}">
                <i class="bi bi-arrow-left"></i> Volver a tickets
            </a>
        </div>
    </header>

    @if(session('success'))
        <div class="alert alert-success co-flash-message">
            <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger co-flash-message align-items-start">
            <i class="bi bi-exclamation-octagon-fill" aria-hidden="true"></i>
            <div><strong>Revisa la información ingresada.</strong><ul class="mb-0 mt-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        </div>
    @endif

    <div class="co-detail-meta co-ticket-meta">
        <div><span>Creado</span><strong>{{ $ticket->created_at->format('d/m/Y H:i') }} hrs.</strong></div>
        <div><span>Vencimiento</span><strong class="{{ in_array($estadoTicket, ['vencido', 'escalado'], true) ? 'text-danger' : '' }}">{{ $ticket->vence_en ? $ticket->vence_en->format('d/m/Y H:i').' hrs.' : 'Se definirá al asignar' }}</strong></div>
        <div><span>Responsable</span><strong>{{ $ticket->responsable?->nombre_completo ?? 'Sin responsable' }}</strong></div>
        <div><span>Estado</span><strong>{{ $estadoLabel }}</strong></div>
    </div>

    <div class="co-grid co-grid--detail">
        <section class="co-card">
            <div class="co-card-head">
                <div><span class="co-eyebrow">Antecedentes</span><h2>Detalle de la incidencia</h2></div>
                <span class="co-badge co-badge--{{ $ticket->incidencia->severidad ?? 'alerta' }}">{{ ucfirst($ticket->incidencia->severidad ?? 'alerta') }}</span>
            </div>
            <div class="co-info-list">
                <div><span><i class="bi bi-exclamation-triangle"></i> Incidencia</span><strong>{{ $ticket->incidencia->tipo_label }}</strong></div>
                <div><span><i class="bi bi-card-text"></i> Detalle</span><p>{{ $ticket->incidencia->descripcion ?: 'Sin detalle informado.' }}</p></div>
                <div><span><i class="bi bi-building"></i> Establecimiento</span><strong>{{ $ticket->incidencia->establecimiento?->nombre_establecimiento ?? 'Sin establecimiento' }}</strong></div>
                <div><span><i class="bi bi-person-check"></i> Reportado por</span><strong>{{ $ticket->incidencia->reporte?->reportado_por_nombre_visible ?? 'Usuario registrado sin nombre disponible' }}</strong></div>
            </div>
        </section>

        <section class="co-card">
            <div class="co-card-head">
                <div><span class="co-eyebrow">Asignación</span><h2>Responsabilidad institucional</h2></div>
                <i class="bi bi-diagram-3 co-card-head-icon" aria-hidden="true"></i>
            </div>
            <div class="co-info-list">
                <div><span><i class="bi bi-person-badge"></i> Responsable</span><strong>{{ $ticket->responsable?->nombre_completo ?? 'Sin responsable' }}</strong></div>
                <div><span><i class="bi bi-people"></i> Unidad</span><strong>{{ $ticket->unidad_departamento ?: 'Sin unidad registrada' }}</strong></div>
                <div><span><i class="bi bi-building-gear"></i> Subdirección</span><strong>{{ $ticket->subdireccion_dependencia ?: 'Sin subdirección registrada' }}</strong></div>
                <div><span><i class="bi bi-hourglass-split"></i> Plazo límite</span><strong>{{ $ticket->vence_en ? $ticket->vence_en->translatedFormat('d \d\e F \d\e Y, H:i').' hrs.' : 'Pendiente de asignación' }}</strong></div>
                <div><span><i class="bi bi-person-plus"></i> Segundo responsable</span><strong>{{ $ticket->segundoResponsable?->nombre_completo ?? 'No asignado' }}</strong></div>
                <div><span><i class="bi bi-building-gear"></i> Segunda subdirección</span><strong>{{ $ticket->segunda_subdireccion_responsable ?: 'No asignada' }}</strong></div>
            </div>
        </section>
    </div>

    <section class="co-card co-ticket-gallery-card">
        <div class="co-card-head">
            <div>
                <span class="co-eyebrow">Complemento del establecimiento</span>
                <h2>Registro fotográfico</h2>
            </div>
            <span class="co-count">{{ $ticket->imagenes->count() }}/{{ config('centro_operaciones.ticket_imagenes.maximo', 10) }}</span>
        </div>

        @if($ticket->imagenes->isEmpty())
            <div class="co-empty">
                <i class="bi bi-images" aria-hidden="true"></i>
                El establecimiento todavía no ha agregado fotografías.
            </div>
        @else
            <div class="co-ticket-gallery">
                @foreach($ticket->imagenes as $imagen)
                    <a href="{{ route('centro-operaciones.tickets.imagenes.show', [$ticket, $imagen]) }}" target="_blank" rel="noopener">
                        <img src="{{ route('centro-operaciones.tickets.imagenes.show', [$ticket, $imagen]) }}" alt="Fotografía {{ $loop->iteration }} del ticket {{ $ticket->numero }}" loading="lazy">
                        <span>Fotografía {{ $loop->iteration }}</span>
                    </a>
                @endforeach
            </div>
        @endif

        @if($puedeSubirImagenes)
            @if($ticket->imagenes->count() < config('centro_operaciones.ticket_imagenes.maximo', 10))
                <form method="POST" action="{{ route('centro-operaciones.tickets.imagenes.store', $ticket) }}" enctype="multipart/form-data" class="co-ticket-upload-form">
                    @csrf
                    <div>
                        <label class="form-label fw-semibold" for="imagenes">Subir fotos</label>
                        <input id="imagenes" name="imagenes[]" type="file" class="form-control @error('imagenes') is-invalid @enderror" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" multiple required>
                        <div class="form-text">
                            JPG, PNG o WebP. Máximo 20 MB por imagen y 10 imágenes acumuladas por ticket.
                        </div>
                    </div>
                    <button class="btn btn-primary" type="submit">
                        <i class="bi bi-cloud-arrow-up"></i> Subir fotos
                    </button>
                </form>
            @else
                <div class="co-ticket-upload-limit">
                    <i class="bi bi-check-circle"></i> Este ticket alcanzó el máximo de 10 imágenes.
                </div>
            @endif
        @endif
    </section>

    <section class="co-card co-resolution-card">
        <div class="co-card-head">
            <div><span class="co-eyebrow">Cierre y trazabilidad</span><h2>{{ $estadoTicket === 'resuelto' ? 'Resolución registrada' : 'Resolver ticket' }}</h2></div>
            <i class="bi {{ $estadoTicket === 'resuelto' ? 'bi-shield-check' : 'bi-check2-square' }} co-card-head-icon" aria-hidden="true"></i>
        </div>
        @if($estadoTicket !== 'resuelto' && $puedeResolver)
            <form method="POST" action="{{ route('centro-operaciones.tickets.resolver', $ticket) }}" class="co-resolution-form">
                @csrf
                @method('PATCH')
                <label for="resolucion">
                    <strong>Detalle de la resolución</strong>
                    <span>Describe las acciones realizadas y el resultado obtenido. Esta información quedará en el historial.</span>
                </label>
                <textarea id="resolucion" name="resolucion" class="form-control" rows="5" required maxlength="2000" placeholder="Ej.: Se coordinó la reparación con el proveedor y el servicio quedó restablecido…">{{ old('resolucion') }}</textarea>
                <div class="co-resolution-actions">
                    <small><i class="bi bi-info-circle"></i> Al resolver el ticket también se cerrará la incidencia asociada.</small>
                    <button class="btn btn-success"><i class="bi bi-check2-circle"></i> Resolver ticket</button>
                </div>
            </form>
        @elseif($estadoTicket === 'resuelto')
            <div class="co-resolution-result">
                <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                <div>
                    <strong>Ticket resuelto</strong>
                    <p>{{ $ticket->resolucion ?: 'Sin detalle de resolución.' }}</p>
                    @if($ticket->firmaResolucion)
                        <small>
                            Firmado electrónicamente por {{ $ticket->firmaResolucion->nombre_firmante }}
                            el {{ $ticket->firmaResolucion->fecha_firma?->format('d/m/Y H:i') }} hrs.
                        </small>
                    @elseif($ticket->resuelto_en)
                        <small>Registrado el {{ $ticket->resuelto_en->format('d/m/Y H:i') }} hrs.</small>
                    @endif
                </div>
            </div>
        @else
            <div class="alert alert-info mb-0">
                El ticket está visible para seguimiento, pero su resolución corresponde a las personas responsables asignadas.
            </div>
        @endif
    </section>
</div>

@endsection
