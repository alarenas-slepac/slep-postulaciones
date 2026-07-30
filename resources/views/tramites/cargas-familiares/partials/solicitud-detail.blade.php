@php
    $benef = (array) ($solicitud->beneficiario_snapshot ?? []);
    $sol = (array) ($solicitud->solicitante_snapshot ?? []);
    $decl = (array) ($solicitud->declaracion_ingresos ?? []);
    $incomeColumns = [
        'mismo_empleador' => 'Mismo empleador',
        'otros_empleadores' => 'Otros empleadores',
        'trabajador_independiente' => 'Independiente',
        'subsidios' => 'Subsidios',
        'pensiones_misma_entidad' => 'Pensiones misma entidad',
        'otras_pensiones' => 'Otras pensiones',
        'total' => 'Total',
    ];
@endphp

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header fw-semibold">Identificación del beneficiario</div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-4">Nombre</dt><dd class="col-8">{{ $benef['nombre_completo'] ?? $solicitud->user?->nombre_completo ?? '—' }}</dd>
                    <dt class="col-4">RUN</dt><dd class="col-8">{{ $benef['rut'] ?? $solicitud->user?->rut ?? '—' }}</dd>
                    <dt class="col-4">Correo</dt><dd class="col-8">{{ $benef['correo'] ?? $solicitud->user?->email ?? '—' }}</dd>
                    <dt class="col-4">Domicilio</dt><dd class="col-8">{{ $benef['domicilio'] ?? '—' }}</dd>
                    <dt class="col-4">Comuna/Ciudad/Reg.</dt><dd class="col-8">{{ trim(($benef['comuna'] ?? '') . ' ' . ($benef['ciudad'] ?? '') . ' ' . ($benef['region'] ?? '')) ?: '—' }}</dd>
                    <dt class="col-4">Pago directo</dt><dd class="col-8">{{ $solicitud->solicita_pago_directo ? 'Sí' : 'No' }}</dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header fw-semibold">Solicitante y revisión</div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-4">Solicitante distinto</dt><dd class="col-8">{{ $solicitud->solicitante_distinto ? 'Sí' : 'No' }}</dd>
                    @if ($solicitud->solicitante_distinto)
                        <dt class="col-4">Nombre/RUT</dt><dd class="col-8">{{ ($sol['nombre'] ?? '—') . ' · ' . ($sol['rut'] ?? '—') }}</dd>
                        <dt class="col-4">Correo</dt><dd class="col-8">{{ $sol['correo'] ?? '—' }}</dd>
                    @endif
                    <dt class="col-4">Enviada</dt><dd class="col-8">{{ $solicitud->fecha_envio?->timezone(config('app.display_timezone', 'America/Santiago'))->format('d-m-Y H:i') ?: '—' }}</dd>
                    <dt class="col-4">Revisada</dt><dd class="col-8">{{ $solicitud->fecha_revision?->timezone(config('app.display_timezone', 'America/Santiago'))->format('d-m-Y H:i') ?: '—' }}</dd>
                    <dt class="col-4">Revisor</dt><dd class="col-8">{{ $solicitud->revisadoPor?->nombre_completo ?: '—' }}</dd>
                    <dt class="col-4">Observación</dt><dd class="col-8">{{ $solicitud->observacion_revision ?: '—' }}</dd>
                </dl>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-header fw-semibold">Declaración Jurada de Ingresos registrada en formulario web</div>
    <div class="card-body">
        <div class="row g-3 mb-3 small">
            <div class="col-md-3"><span class="text-muted">Condición:</span> <strong>{{ ucfirst($decl['condicion'] ?? '—') }}</strong></div>
            <div class="col-md-5"><span class="text-muted">Alternativa:</span> <strong>{{ ($decl['alternativa'] ?? '') === 'mas_de_un_ingreso' ? 'Haber percibido más de un ingreso' : 'No haber percibido otros ingresos' }}</strong></div>
            <div class="col-md-2"><span class="text-muted">Año ene-jun:</span> <strong>{{ $decl['anio_primer_semestre'] ?? '—' }}</strong></div>
            <div class="col-md-2"><span class="text-muted">Jul-dic:</span> <strong>{{ !empty($decl['declara_segundo_semestre']) ? ($decl['anio_segundo_semestre'] ?? 'Sí') : 'No aplica' }}</strong></div>
        </div>
        @if (($decl['alternativa'] ?? '') === 'mas_de_un_ingreso')
            @include('tramites.cargas-familiares.partials.ingresos-readonly', ['title' => 'Enero a junio', 'rows' => (array) ($decl['ingresos_primer_semestre'] ?? []), 'incomeColumns' => $incomeColumns])
            @if (!empty($decl['declara_segundo_semestre']))
                <div class="mt-3">
                    @include('tramites.cargas-familiares.partials.ingresos-readonly', ['title' => 'Julio a diciembre', 'rows' => (array) ($decl['ingresos_segundo_semestre'] ?? []), 'incomeColumns' => $incomeColumns])
                </div>
            @endif
        @else
            <div class="alert alert-secondary mb-0 small">El beneficiario declaró no haber percibido otros ingresos adicionales a sus remuneraciones/pensiones del empleador o entidad previsional informada.</div>
        @endif
    </div>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-header fw-semibold">Documentos generales</div>
    <div class="card-body p-0">
        @include('tramites.cargas-familiares.partials.documentos-table', ['documentos' => $solicitud->documentosSolicitud, 'solicitud' => $solicitud, 'canReview' => $canReview])
    </div>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-header fw-semibold">Causantes</div>
    <div class="card-body">
        @forelse ($solicitud->causantes as $causante)
            <div class="border rounded p-3 mb-3">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <div class="fw-semibold">{{ $causante->nombre_completo }}</div>
                        <div class="small text-muted">{{ $causante->rut_completo }} · {{ $causante->parentesco }} · Edad al enviar: {{ $causante->edad_al_enviar ?? '—' }}</div>
                    </div>
                    <div class="text-end">
                        <span class="badge {{ $causante->estado_revision_badge_class }}">{{ $causante->estado_revision_label }}</span>
                    </div>
                </div>
                <dl class="row small mb-3">
                    <dt class="col-md-3">Sexo</dt><dd class="col-md-3">{{ $causante->sexo ?: '—' }}</dd>
                    <dt class="col-md-3">Fecha nacimiento</dt><dd class="col-md-3">{{ $causante->fecha_nacimiento?->format('d-m-Y') ?: '—' }}</dd>
                    <dt class="col-md-3">Cod. beneficio</dt><dd class="col-md-3">{{ $causante->codigo_tipo_beneficio ?: '—' }}</dd>
                    <dt class="col-md-3">Cod. causante</dt><dd class="col-md-3">{{ $causante->codigo_tipo_causante ?: '—' }}</dd>
                    <dt class="col-md-3">Inicio solicitado</dt><dd class="col-md-3">{{ $causante->fecha_inicio_beneficio?->format('d-m-Y') ?: '—' }}</dd>
                    <dt class="col-md-3">Acción</dt><dd class="col-md-3">{{ $causante->accion === 'modificar' ? 'Modificar carga vigente' : 'Nuevo causante' }}</dd>
                    <dt class="col-md-3">Observaciones</dt><dd class="col-md-9">{{ $causante->observaciones ?: '—' }}</dd>
                </dl>
                <div class="fw-semibold small mb-2">Documentos del causante</div>
                @include('tramites.cargas-familiares.partials.documentos-table', ['documentos' => $causante->documentos, 'solicitud' => $solicitud, 'canReview' => $canReview])
            </div>
        @empty
            <div class="text-muted">No hay causantes registrados.</div>
        @endforelse
    </div>
</div>
