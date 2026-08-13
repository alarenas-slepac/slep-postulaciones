@php
    $solicitud = $deuda->solicitud;
    $postulante = $deuda->postulante?->user;
@endphp

<p>Estimada encargada de Remuneraciones:</p>

<p>
    Junto con saludar, se remiten los antecedentes del postulante registrado con
    <strong>deuda de pensión de alimentos</strong>, asociado a la solicitud de reemplazo
    <strong>{{ $solicitud?->numero_solicitud ?? $deuda->solicitud_reemplazo_id }}</strong>.
</p>

<ul>
    <li><strong>Postulante:</strong> {{ $postulante?->full_name ?? '—' }}</li>
    <li><strong>RUT:</strong> {{ \App\Support\Rut::format($postulante?->rut) ?? '—' }}</li>
    <li><strong>Establecimiento:</strong> {{ $solicitud?->establecimiento?->nombre_establecimiento ?? '—' }}</li>
    <li><strong>Estado de la solicitud:</strong> {{ ucfirst(str_replace('_', ' ', (string) $solicitud?->estado)) }}</li>
    <li><strong>Valor informado de cuota alimentaria:</strong> ${{ number_format((float) $deuda->valor_cuota_alimentaria, 0, ',', '.') }}</li>
</ul>

<p>Se adjuntan el certificado de deuda, la resolución o dictamen actualizado y la declaración jurada vigente para ejercer cargo público.</p>

<p>Una vez enviado este correo, el sistema desbloquea la solicitud para continuar con la Orden de Trabajo o el Contrato.</p>

<p>Saludos cordiales,<br>Servicio Local de Educación Pública Andalién Costa</p>
