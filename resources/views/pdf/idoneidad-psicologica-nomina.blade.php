<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 14mm 13mm 17mm; }
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 9.3px; line-height: 1.35; }
        .header { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .header td { padding: 3px 5px; vertical-align: top; }
        .header__brand { width: 55%; font-size: 10px; font-weight: bold; line-height: 1.25; }
        .header__subject { width: 45%; font-size: 8.5px; }
        .header__label { font-weight: bold; }
        .address { margin: 14px 0 13px 32px; line-height: 1.35; font-weight: bold; }
        .body-copy { margin: 0 0 9px; text-align: justify; }
        .period { background: #f3f4f6; border: 1px solid #d1d5db; padding: 7px 9px; margin: 10px 0 12px; font-size: 8.8px; }
        .table-title { font-weight: bold; margin: 11px 0 5px; }
        .nomina { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 7.1px; }
        .nomina thead { display: table-header-group; }
        .nomina th { background: #e5e7eb; border: 1px solid #4b5563; color: #111827; padding: 4px 3px; text-align: center; font-weight: bold; vertical-align: middle; }
        .nomina td { border: 1px solid #6b7280; padding: 3px; vertical-align: top; word-wrap: break-word; }
        .nomina tr { page-break-inside: avoid; }
        .center { text-align: center; }
        .signature { margin-top: 19px; text-align: center; font-weight: bold; line-height: 1.35; }
        .distribution { margin-top: 16px; font-size: 8px; }
        .footer { position: fixed; bottom: -10mm; left: 0; right: 0; color: #4b5563; font-size: 7px; }
    </style>
</head>
<body>
    @php
        $meses = [1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril', 5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto', 9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'];
        $fechaOficio = 'Coronel, ' . $fechaEmision->day . ' de ' . $meses[(int) $fechaEmision->month] . ' de ' . $fechaEmision->year;
    @endphp

    <table class="header">
        <tr>
            <td class="header__brand">SERVICIO LOCAL DE EDUCACIÓN PÚBLICA<br>DE ANDALIÉN COSTA</td>
            <td class="header__subject"><span class="header__label">ANT.:</span> Art. 4 de la Ley N° 21.109.</td>
        </tr>
        <tr>
            <td></td>
            <td class="header__subject"><span class="header__label">MAT.:</span> Solicita evaluación de idoneidad psicológica de asistentes de la educación del SLEP Andalién Costa.</td>
        </tr>
        <tr><td></td><td class="header__subject">{{ $fechaOficio }}</td></tr>
    </table>

    <div class="address">
        A: SR.(A) DIRECTOR(A) REGIONAL<br>
        SERVICIO DE SALUD CONCEPCIÓN<br><br>
        DE: DIRECTOR(A) EJECUTIVO(A)<br>
        SERVICIO LOCAL DE EDUCACIÓN PÚBLICA DE ANDALIÉN COSTA
    </div>

    <p class="body-copy">Junto con saludar cordialmente, y en cumplimiento de las exigencias de idoneidad psicológica establecidas para quienes se desempeñan como asistentes de la educación, vengo en solicitar a usted la evaluación de las personas individualizadas en la nómina que se acompaña.</p>
    <p class="body-copy"><strong>1.</strong> La Ley N° 21.040 creó el Sistema de Educación Pública y los Servicios Locales de Educación Pública, a los que corresponde administrar y gestionar los establecimientos educacionales de su dependencia.</p>
    <p class="body-copy"><strong>2.</strong> El artículo 4 de la Ley N° 21.109 exige acreditar idoneidad psicológica para desempeñarse como asistente de la educación, mediante el informe que corresponda, con carácter previo a la celebración del respectivo contrato.</p>
    <p class="body-copy"><strong>3.</strong> El Servicio Local no cuenta actualmente con un profesional de su dotación que pueda efectuar dichas evaluaciones, razón por la cual solicita la colaboración del Servicio de Salud competente.</p>

    <div class="period"><strong>Período de ingresos considerado en esta solicitud:</strong> {{ optional($solicitud->fecha_inicio)->format('d/m/Y') }} al {{ optional($solicitud->fecha_termino)->format('d/m/Y') }}. &nbsp; <strong>Solicitud:</strong> #{{ $solicitud->id }}.</div>

    <p class="body-copy">Por lo anterior, se solicita efectuar las evaluaciones de idoneidad psicológica respecto de los siguientes asistentes de la educación:</p>
    <div class="table-title">NÓMINA DE FUNCIONARIOS(AS)</div>

    <table class="nomina">
        <thead><tr>
            <th style="width:6.5%">Nro.</th><th style="width:20.8%">NOMBRE</th><th style="width:13%">RUT</th><th style="width:16.9%">CARGO</th><th style="width:18.2%">ESTABLECIMIENTO</th><th style="width:13%">TIPO DE CONTRATO</th><th style="width:11.6%">COMUNA</th>
        </tr></thead>
        <tbody>
            @foreach($solicitud->funcionarios as $index => $funcionario)
                <tr>
                    <td class="center">{{ $index + 1 }}</td><td>{{ $funcionario->nombre }}</td><td class="center">{{ $funcionario->rut }}</td><td>{{ $funcionario->cargo_funcion ?: 'Sin cargo informado' }}</td><td>{{ $funcionario->establecimiento_nombre ?: 'Sin establecimiento informado' }}</td><td>{{ $funcionario->tipo_contrato ?: 'Sin contrato informado' }}</td><td>{{ $funcionario->comuna ?: 'Sin comuna informada' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p class="body-copy" style="margin-top: 13px;">Para fines de coordinación, agradeceremos informar el resultado de las evaluaciones a través de los canales institucionales establecidos.</p>
    <p class="body-copy">Sin otro particular, saluda atentamente,</p>
    <div class="signature">DIRECTOR(A) EJECUTIVO(A)<br>SERVICIO LOCAL DE EDUCACIÓN PÚBLICA DE ANDALIÉN COSTA</div>
    <div class="distribution"><strong>Distribución:</strong><br>– Destinatario<br>– Archivo</div>
    <div class="footer">SGA SLEP Andalién Costa · Oficio generado el {{ $fechaEmision->format('d/m/Y H:i') }} desde el último padrón vigente utilizado al crear la solicitud.</div>
</body>
</html>
