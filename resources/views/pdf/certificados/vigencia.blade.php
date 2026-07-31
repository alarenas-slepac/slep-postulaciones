<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { size: letter portrait; margin: 0; }
        @font-face {
            font-family: "Century Gothic";
            font-style: normal;
            font-weight: 400;
            src: url("{{ $fuenteRegularDataUri }}") format("truetype");
        }
        @font-face {
            font-family: "Century Gothic";
            font-style: normal;
            font-weight: 700;
            src: url("{{ $fuenteBoldDataUri }}") format("truetype");
        }
        * { box-sizing: border-box; }
        html, body {
            width: 612pt;
            height: 792pt;
            margin: 0;
            padding: 0;
            color: #000;
            font-family: "Century Gothic", sans-serif;
            font-size: 9.24pt;
            line-height: 1.28;
        }
        .page {
            position: relative;
            width: 612pt;
            height: 792pt;
            overflow: hidden;
        }
        .logo {
            position: absolute;
            left: 74.6pt;
            top: 66pt;
            width: 88.2pt;
            height: auto;
        }
        .title {
            position: absolute;
            top: 141pt;
            left: 70.4pt;
            width: 488.6pt;
            text-align: center;
            font-size: 12.36pt;
            line-height: 1.1;
            font-weight: 700;
            text-decoration: underline;
        }
        .intro {
            position: absolute;
            top: 175pt;
            left: 70.4pt;
            width: 488.6pt;
            line-height: 1.55;
        }
        .intro-line-one { text-align: right; }
        .intro-line-two { text-align: left; }
        table {
            border-collapse: collapse;
            table-layout: fixed;
        }
        .identity {
            position: absolute;
            left: 70.4pt;
            top: 233.4pt;
            width: 488.6pt;
        }
        .identity tr,
        .details tr {
            height: 18.1pt;
        }
        .identity td,
        .details td {
            height: 18.1pt;
            padding: 0 3pt;
            vertical-align: middle;
            border: .7pt solid #000;
            line-height: 1;
        }
        .identity .label,
        .details .label {
            width: 30%;
            text-align: right;
        }
        .identity .value,
        .details .value {
            width: 70%;
            text-align: left;
        }
        .statement {
            position: absolute;
            left: 70.4pt;
            top: 292pt;
            width: 488.6pt;
            text-align: justify;
        }
        .details {
            position: absolute;
            left: 70.4pt;
            top: 366.4pt;
            width: 488.6pt;
        }
        .details .value {
            font-size: 8.4pt;
            white-space: nowrap;
        }
        .request {
            position: absolute;
            left: 70.4pt;
            top: 488pt;
            width: 488.6pt;
            text-align: justify;
        }
        .firma {
            position: absolute;
            left: 171.4pt;
            top: 514pt;
            width: 212.4pt;
            height: auto;
        }
        .timbre {
            position: absolute;
            left: 396pt;
            top: 514pt;
            width: 94.3pt;
            height: auto;
        }
        .signer {
            position: absolute;
            left: 142pt;
            top: 586pt;
            width: 320pt;
            text-align: center;
            font-size: 10.8pt;
            line-height: 1.28;
            font-weight: 700;
        }
        .date {
            position: absolute;
            left: 70.4pt;
            top: 687pt;
            width: 360pt;
        }
        .email {
            position: absolute;
            left: 70.4pt;
            top: 735pt;
            width: 405pt;
            font-size: 8.4pt;
        }
        .verification {
            position: absolute;
            right: 57pt;
            top: 674pt;
            width: 76pt;
            text-align: center;
            font-size: 5.4pt;
            line-height: 1.15;
        }
        .verification img {
            display: block;
            width: 58pt;
            height: 58pt;
            margin: 0 auto 2pt;
        }
        .verification .code {
            font-weight: 700;
            word-break: break-all;
        }
        .page.has-multiple .details tr.multi,
        .page.has-multiple .details tr.multi td {
            height: 28pt;
        }
        .page.has-multiple .details tr.multi td.value {
            font-size: 6.5pt;
            line-height: 1.1;
            white-space: normal;
        }
        .page.has-multiple .request { top: 510pt; }
        .page.has-multiple .firma,
        .page.has-multiple .timbre { top: 530pt; }
        .page.has-multiple .signer { top: 600pt; }
    </style>
</head>
<body>
@php
    $establecimientos = collect($certificado->establecimientos_snapshot ?? []);
    $nombresEstablecimientos = $establecimientos
        ->pluck('establecimiento')
        ->filter()
        ->unique()
        ->implode(' / ');
    $comunas = $establecimientos
        ->pluck('comuna')
        ->filter()
        ->unique()
        ->implode(' / ');
    $cantidadComunas = $establecimientos
        ->pluck('comuna')
        ->filter()
        ->unique()
        ->count();
    $regimenExtendido = mb_strlen((string) $certificado->regimen_juridico_snapshot) > 75;
    $tieneFilasExtendidas = $establecimientos->count() > 1
        || $cantidadComunas > 1
        || $regimenExtendido;
    $rut = strtoupper((string) $certificado->rut_normalizado);
    $rutCuerpo = mb_substr($rut, 0, -1);
    $rutFormateado = $rutCuerpo . '-' . mb_substr($rut, -1);
    $fechaEmision = $certificado->emitido_at->locale('es');
    $fechaIncorporacion = \Carbon\CarbonImmutable::parse(
        $institucion['incorporacion_desde']
    )->locale('es');
    $fechaIncorporacionTexto = $fechaIncorporacion->translatedFormat('d \d\e F')
        . ' del año ' . $fechaIncorporacion->format('Y');
    $codigoFormateado = implode('-', str_split($certificado->codigo_validacion, 8));
@endphp
<div class="page {{ $tieneFilasExtendidas ? 'has-multiple' : '' }}">
    @if ($logoDataUri)
        <img class="logo" src="{{ $logoDataUri }}" alt="">
    @endif

    <div class="title">CERTIFICADO DE VIGENCIA</div>

    <div class="intro">
        <div class="intro-line-one">
            {{ $firmante['nombre'] }}, {{ $firmante['cargo'] }} del
        </div>
        <div class="intro-line-two">
            {{ $institucion['nombre'] }}, que suscribe, certifica que:
        </div>
    </div>

    <table class="identity">
        <colgroup>
            <col style="width:146.5pt">
            <col style="width:342.1pt">
        </colgroup>
        <tr>
            <td class="label">Don(a)</td>
            <td class="value">{{ mb_strtoupper($certificado->nombre_snapshot) }}</td>
        </tr>
        <tr>
            <td class="label">RUT</td>
            <td class="value">{{ $rutFormateado }}</td>
        </tr>
    </table>

    <div class="statement">
        @if ($certificado->es_funcionario_ac_snapshot)
            Mantiene contrato vigente en el {{ $institucion['nombre'] }},
            RUT {{ $institucion['rut'] }}, con domicilio en {{ $institucion['domicilio'] }}
            como su actual empleador, según los siguientes antecedentes:
        @else
            Mantiene contrato vigente en
            {{ $establecimientos->count() === 1 ? 'un establecimiento educacional' : 'establecimientos educacionales' }}
            que a contar del {{ $fechaIncorporacionTexto }}, conforme a ley,
            {{ $establecimientos->count() === 1 ? 'forma' : 'forman' }} parte del
            {{ $institucion['nombre'] }}, RUT {{ $institucion['rut'] }}, con domicilio en
            {{ $institucion['domicilio'] }} como su actual sostenedor, según los siguientes antecedentes:
        @endif
    </div>

    <table class="details">
        <colgroup>
            <col style="width:146.5pt">
            <col style="width:342.1pt">
        </colgroup>
        <tr>
            <td class="label">Desde</td>
            <td class="value">{{ $certificado->fecha_antiguedad->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="label">Calidad Jurídica</td>
            <td class="value">{{ mb_strtoupper($certificado->calidad_juridica_snapshot) }}</td>
        </tr>
        <tr class="{{ $establecimientos->count() > 1 ? 'multi' : '' }}">
            <td class="label">Establecimiento Educacional</td>
            <td class="value">{{ mb_strtoupper($nombresEstablecimientos) }}</td>
        </tr>
        <tr class="{{ $regimenExtendido ? 'multi' : '' }}">
            <td class="label">Régimen Jurídico</td>
            <td class="value">{{ mb_strtoupper($certificado->regimen_juridico_snapshot) }}</td>
        </tr>
        <tr class="{{ $cantidadComunas > 1 ? 'multi' : '' }}">
            <td class="label">Comuna</td>
            <td class="value">{{ mb_strtoupper($comunas) }}</td>
        </tr>
    </table>

    <div class="request">
        Se extiende el presente certificado a petición del interesado(a) para los fines que estime conveniente.
    </div>

    @if ($firmaDataUri)
        <img class="firma" src="{{ $firmaDataUri }}" alt="">
    @endif
    @if ($timbreDataUri)
        <img class="timbre" src="{{ $timbreDataUri }}" alt="">
    @endif

    <div class="signer">
        {{ $firmante['nombre'] }}<br>
        {{ $firmante['cargo'] }}<br>
        {{ $institucion['nombre'] }}<br>
        RUT {{ $institucion['rut'] }}
    </div>

    <div class="date">
        {{ $institucion['ciudad_emision'] }},
        {{ $fechaEmision->translatedFormat('d \d\e F \d\e Y') }}
    </div>
    <div class="email">Email: {{ $institucion['email'] }}</div>

    <div class="verification">
        @if ($qrDataUri)
            <img src="{{ $qrDataUri }}" alt="">
        @endif
        Verificación documental<br>
        <span class="code">{{ $certificado->numero }}</span><br>
        Código: {{ $codigoFormateado }}
    </div>
</div>
</body>
</html>
