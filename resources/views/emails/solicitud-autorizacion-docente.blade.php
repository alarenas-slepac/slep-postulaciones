@php
    $solicitud = $autorizacion->solicitud;
    $postulante = $solicitud?->postulante?->user;
@endphp

<p>Estimadas/os:</p>

<p>
    Junto con saludar, se remiten los antecedentes para gestionar una
    <strong>autorización docente</strong> asociada a la solicitud de reemplazo
    <strong>{{ $solicitud?->numero_solicitud ?? $autorizacion->solicitud_reemplazo_id }}</strong>.
</p>

<ul>
    <li><strong>Establecimiento:</strong> {{ $solicitud?->establecimiento?->nombre_establecimiento ?? '—' }}</li>
    <li><strong>Área de desempeño:</strong> {{ $solicitud?->areaDesempeno?->nombre ?? '—' }}</li>
    <li><strong>Postulante propuesto:</strong> {{ $postulante?->full_name ?? '—' }}</li>
    <li><strong>RUT:</strong> {{ \App\Support\Rut::format($postulante?->rut) ?? '—' }}</li>
    <li><strong>Período:</strong> {{ optional($solicitud?->fecha_inicio)->format('d/m/Y') ?? '—' }} al {{ optional($solicitud?->fecha_termino)->format('d/m/Y') ?? '—' }}</li>
</ul>

<p>Se adjuntan los siguientes documentos:</p>

<ul>
    @foreach ($documentos as $documento)
        <li>{{ $documento->type?->label ?? $documento->original_name ?? 'Documento' }}</li>
    @endforeach
</ul>

<p>
    La solicitud fue registrada en el sistema con estado <strong>En trámite</strong>.
    Este registro es de seguimiento y no interrumpe el flujo de la solicitud de reemplazo.
</p>

<p>Saludos cordiales,<br>Servicio Local de Educación Pública Andalién Costa</p>
