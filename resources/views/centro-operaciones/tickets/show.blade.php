@extends('layouts.app')

@push('styles')
    @vite('resources/css/centro-operaciones.css')
@endpush

@section('content')
@php
    $estadoTicket = strtolower($ticket->estado);
    $estadoLabel = match ($estadoTicket) {
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
            <div><strong>Revisa la resolución ingresada.</strong><ul class="mb-0 mt-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        </div>
    @endif

    <div class="co-detail-meta co-ticket-meta">
        <div><span>Creado</span><strong>{{ $ticket->created_at->format('d/m/Y H:i') }} hrs.</strong></div>
        <div><span>Vencimiento</span><strong class="{{ in_array($estadoTicket, ['vencido', 'escalado'], true) ? 'text-danger' : '' }}">{{ $ticket->vence_en->format('d/m/Y H:i') }} hrs.</strong></div>
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
                <div><span><i class="bi bi-person-check"></i> Reportado por</span><strong>{{ $ticket->incidencia->reporte?->reportadoPor?->name ?? 'Usuario no disponible' }}</strong></div>
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
                <div><span><i class="bi bi-hourglass-split"></i> Plazo límite</span><strong>{{ $ticket->vence_en->translatedFormat('d \d\e F \d\e Y, H:i') }} hrs.</strong></div>

                <!-- Segundo responsable -->
                @if($ticket->segunda_subdireccion_responsable)
                    <div class="co-second-responsible">
                        <span><i class="bi bi-person-plus"></i> Segundo responsable (subdirección)</span>
                        <strong>{{ $ticket->segunda_subdireccion_responsable }}</strong>
                    </div>
                @endif

                @if($ticket->segunda_responsable_subdireccion)
                    <div class="co-second-responsible">
                        <span><i class="bi bi-person-plus"></i> Segundo responsable (subdirección)</span>
                        <strong>{{ $ticket->segunda_responsable_subdireccion }}</strong>
                    </div>
                @endif
            </div>
        </section>
    </div>

    <!-- Formulario para editar segundo responsable -->
    <section class="co-card co-resolution-card">
        <div class="co-card-head">
            <div><span class="co-eyebrow">Asignación</span><h2>Segundo responsable (opcional)</h2></div>
            <i class="bi bi-person-plus co-card-head-icon" aria-hidden="true"></i>
        </div>

        <form method="POST" action="{{ route('centro-operaciones.tickets.update-second-responsible', $ticket) }}" class="co-resolution-form">
            @csrf
            @method('PUT')

            <div class="row">
                <div class="col-md-6">
                    <label for="segunda_subdireccion_responsable">
                        <strong>Segunda subdirección responsable</strong>
                        <span>Seleccione la subdirección (opcional)</span>
                    </label>
                    <select id="segunda_subdireccion_responsable" name="segunda_subdireccion_responsable" class="form-select">
                        <option value="">— Ninguno —</option>
                        @foreach($subdirecciones as $subdir)
                            <option value="{{ $subdir->id }}" {{ $ticket->segunda_subdireccion_responsable == $subdir->id ? 'selected' : '' }}>
                                {{ $subdir->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-6">
                    <label for="segunda_responsable_subdireccion">
                        <strong>Segundo responsable de subdirección</strong>
                        <span>Seleccione el responsable (opcional)</span>
                    </label>
                    <select id="segunda_responsable_subdireccion" name="segunda_responsable_subdireccion" class="form-select">
                        <option value="">— Ninguno —</option>
                        @foreach($responsables as $resp)
                            <option value="{{ $resp->id }}" {{ $ticket->segunda_responsable_subdireccion == $resp->id ? 'selected' : '' }}>
                                {{ $resp->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="co-resolution-actions mt-3">
                <button class="btn btn-primary"><i class="bi bi-save"></i> Guardar cambios</button>
            </div>
        </form>
    </section>

    <section class="co-card co-resolution-card">
        <div class="co-card-head">
            <div><span class="co-eyebrow">Cierre y trazabilidad</span><h2>{{ $estadoTicket === 'resuelto' ? 'Resolución registrada' : 'Resolver ticket' }}</h2></div>
            <i class="bi {{ $estadoTicket === 'resuelto' ? 'bi-shield-check' : 'bi-check2-square' }} co-card-head-icon" aria-hidden="true"></i>
        </div>
        @if($estadoTicket !== 'resuelto')
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
        @else
            <div class="co-resolution-result">
                <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                <div><strong>Ticket resuelto</strong><p>{{ $ticket->resolucion ?: 'Sin detalle de resolución.' }}</p>@if($ticket->resuelto_en)<small>Registrado el {{ $ticket->resuelto_en->format('d/m/Y H:i') }} hrs.</small>@endif</div>
            </div>
        @endif
    </section>
</div>

@section('scripts')
    <script>
        // Cargar subdirecciones dinámicas
        function loadSubdirecciones() {
            fetch('/api/subdirecciones')
                .then(response => response.json())
                .then(data => {
                    const select = document.getElementById('segunda_subdireccion_responsable');
                    if (select) {
                        select.innerHTML = '<option value="">— Ninguno —</option>' +
                            data.map(s => `<option value="${s.id}" ${select.value === s.id ? 'selected' : ''}>${s.nombre}</option>`).join('');
                    }
                })
                .catch(console.error);
        }

        // Cargar responsables dinámicos
        function loadResponsables() {
            fetch('/api/responsables')
                .then(response => response.json())
                .then(data => {
                    const select = document.getElementById('segunda_responsable_subdireccion');
                    if (select) {
                        select.innerHTML = '<option value="">— Ninguno —</option>' +
                            data.map(s => `<option value="${s.id}" ${select.value === s.id ? 'selected' : ''}>${s.nombre}</option>`).join('');
                    }
                })
                .catch(console.error);
        }

        // Cargar al cargar la página
        document.addEventListener('DOMContentLoaded', function () {
            loadSubdirecciones();
            loadResponsables();
        });

        // Recargar al cambio en el primer selector
        document.getElementById('segunda_subdireccion_responsable')?.addEventListener('change', function () {
            loadResponsables();
        });

        // Recargar al cambio en el segundo selector
        document.getElementById('segunda_responsable_subdireccion')?.addEventListener('change', function () {
            loadResponsables();
        });
    </script>
@endsection