<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Orden de Trabajo</title>
    <style>
        @page { margin: 22px 22px; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #111; }
        .header { width: 100%; margin-bottom: 14px; }
        .header td { vertical-align: middle; }
        .logo { width: 170px; height: auto; }
        .title { text-align: center; font-weight: 700; font-size: 16px; }
        .title small { display:block; font-size: 14px; margin-top: 2px; }
        .row { margin: 6px 0; }
        .label { display: inline-block; width: 150px; font-weight: 700; }
        .value { display: inline-block; }
        .spacer { height: 10px; }
        .footer { margin-top: 26px; }

        /* bloque firma/timbre/pie */
        .firma-section {
            position: relative;
            width: 100%;
            margin-top: 34px;
            text-align: center;
        }

        /* área donde se montan timbre y firma */
        .firma-layer {
            position: relative;
            width: 320px;
            height: 120px;
            margin: 0 auto -28px auto; /* hace que baje sobre el pie de firma */
        }

        /* timbre atrás */
        .firma-layer .timbre-img {
            position: absolute;
            left: 50%;
            margin-left: -85px;
            top: 18px;
            width: 170px;
            height: auto;
            z-index: 1;
            opacity: 0.95;
        }

        /* firma encima del timbre */
        .firma-layer .firma-img {
            position: absolute;
            left: 50%;
            margin-left: -140px;
            top: 0;
            width: 280px;
            height: auto;
            z-index: 2;
        }

        /* fallback cuando solo existe una imagen combinada */
        .firma-combo {
            text-align: center;
            margin: 0 auto -18px auto;
        }
        .firma-combo .firma-img {
            width: 280px;
            height: auto;
        }

        .firma-text {
            position: relative;
            z-index: 0;
            text-align: center;
            font-size: 12px;
            line-height: 1.3;
            padding-top: 34px; /* espacio para que el montaje entre parcialmente sobre el pie */
        }

        .elaborado-por {
            margin-top: 18px;
            font-size: 11px;
            text-align: left;
        }
    </style>
</head>
<body>
@php
    // helpers
    $fmtRut = function ($rut) {
        $rut = strtoupper(preg_replace('/[^0-9Kk]/', '', (string)$rut));
        if ($rut === '') return '';
        return substr($rut, 0, -1) . '-' . substr($rut, -1);
    };
    $fmtDate = function ($d) {
        try {
            return $d ? \Illuminate\Support\Carbon::parse($d)->format('d/m/Y') : '';
        } catch (\Throwable $e) { return ''; }
    };
    $up = fn($v) => mb_strtoupper(trim((string)$v), 'UTF-8');

    // Logo antiguo usado específicamente para la Orden de Trabajo.
    $logoFile = public_path('branding/logo-andalien-costa.svg');
    $logoData = is_file($logoFile) ? 'data:image/svg+xml;base64,' . base64_encode(file_get_contents($logoFile)) : null;

    // Firma y timbre (preferimos archivos separados). Mantengo fallback al archivo único antiguo.
    $firmaSoloFile  = public_path('branding/firma.png');
    $timbreFile     = public_path('branding/timbre.png');
    $firmaComboFile = public_path('branding/firma-makarena.png');

    $firmaSoloData = is_file($firmaSoloFile) ? 'data:image/png;base64,' . base64_encode(file_get_contents($firmaSoloFile)) : null;
    $timbreData    = is_file($timbreFile) ? 'data:image/png;base64,' . base64_encode(file_get_contents($timbreFile)) : null;
    $firmaComboData = (!$firmaSoloData && is_file($firmaComboFile))
        ? 'data:image/png;base64,' . base64_encode(file_get_contents($firmaComboFile))
        : null;

    // Postulante
    $postUser = $s->postulante?->user;

    $postName = '';
    if ($postUser) {
        $postName = $postUser->nombre_completo
            ?? $postUser->display_name
            ?? $postUser->full_name
            ?? $postUser->name
            ?? trim(($postUser->nombres ?? '') . ' ' . ($postUser->apellido_paterno ?? '') . ' ' . ($postUser->apellido_materno ?? ''));
    }
    $postRut  = $fmtRut($postUser?->rut ?? '');

    // Establecimiento / cargo
    $estabRbd = $s->establecimiento?->rbd ?? '';
    $estabNom = $s->establecimiento?->nombre_establecimiento ?? '';
    $estabVal = trim($estabRbd . ' - ' . $up($estabNom), ' -');

    $cargoVal = $up($s->areaDesempeno?->nombre ?? '');

    // Jornada
    $hrsTot = (float) $s->jornadas->sum('reemplazo_total');
    $hrsTxt = rtrim(rtrim(number_format($hrsTot, 2, '.', ''), '0'), '.');
    if ($hrsTxt === '') $hrsTxt = '0';

    // Fechas
    // A CONTAR DE = fecha_inicio_trabajo (fallback a fecha_inicio por si aún no está seteada en algún caso)
    $inicioTrab = $fmtDate($s->fecha_inicio_trabajo ?? $s->fecha_inicio ?? null);
    $finTrab    = $fmtDate($s->fecha_termino ?? null);

    // Motivo / observación
    $tipo = (string) ($s->tipo_reemplazo ?? '');
    if ($tipo === 'otro') {
        $tipo = (string) ($s->tipo_reemplazo_otro ?? 'OTRO');
    }
    $tipo = $up(str_replace('_', ' ', $tipo));

    $titNombre = $up($s->funcionarioTitular?->nombre ?? '');
    $titRut    = $fmtRut($s->funcionarioTitular?->rut ?? '');

    $motivo = trim($tipo . ' ' . $titNombre . ' - RUT: ' . $titRut);

    // Financiamiento: {FIN} - TOTAL HORAS - HRS BÁSICA (>0) - HRS MEDIA (>0)
    $finParts = [];
    foreach ($s->jornadas as $j) {
        $fin = $up($j->financiamiento ?? '');
        $tot = (float) $j->reemplazo_total;
        if ($tot <= 0) continue;

        $seg = $fin . ' - ' . rtrim(rtrim(number_format($tot, 2, '.', ''), '0'), '.') . ' HRS';
        $bas = (float) $j->reemplazo_basica;
        $med = (float) $j->reemplazo_media;

        if ($bas > 0) $seg .= ' - BÁSICA ' . rtrim(rtrim(number_format($bas, 2, '.', ''), '0'), '.') . ' HRS';
        if ($med > 0) $seg .= ' - MEDIA '  . rtrim(rtrim(number_format($med, 2, '.', ''), '0'), '.') . ' HRS';

        $finParts[] = $seg;
    }
    $finTxt = implode(' / ', $finParts);

    $creator = $s->ordenTrabajoCreadaPor;
    $elaboradoPor = '';
    if ($creator) {
        $elaboradoPor = $creator->nombre_completo
            ?? $creator->full_name
            ?? $creator->display_name
            ?? trim(($creator->nombres ?? '') . ' ' . ($creator->apellido_paterno ?? '') . ' ' . ($creator->apellido_materno ?? ''))
            ?? $creator->email
            ?? '';
    }
@endphp

<table class="header">
    <tr>
        <td style="width: 180px;">
            @if($logoData)
                <img class="logo" src="{{ $logoData }}" alt="SLEP Andalién Costa">
            @endif
        </td>
        <td>
            <div class="title">
                ORDEN DE TRABAJO
                <small>N° {{ $otNumero ?? '' }}</small>
            </div>
        </td>
        <td style="width: 180px;"></td>
    </tr>
</table>

<div class="row"><span class="label">NOMBRE :</span> <span class="value">{{ $up($postName) }}</span></div>
<div class="row"><span class="label">RUT :</span> <span class="value">{{ $postRut }}</span></div>

<div class="spacer"></div>

<div class="row"><span class="label">ESTABLECIMIENTO :</span> <span class="value">{{ $estabVal }}</span></div>
<div class="row"><span class="label">CARGO :</span> <span class="value">{{ $cargoVal }}</span></div>
<div class="row"><span class="label">JORNADA :</span> <span class="value">{{ $hrsTxt }} HRS</span></div>

<div class="spacer"></div>

<div class="row"><span class="label">A CONTAR DE :</span> <span class="value">{{ $inicioTrab }}</span></div>
<div class="row"><span class="label">FECHA DE TÉRMINO :</span> <span class="value">{{ $finTrab }}</span></div>
<div class="row"><span class="label">MOTIVO U OBSERVACIÓN :</span> <span class="value">{{ $motivo }}</span></div>
<div class="row"><span class="label">FINANCIAMIENTO :</span> <span class="value">{{ $finTxt }}</span></div>

@php
    $estatutoTitular = mb_strtoupper(trim((string) ($s->funcionarioTitular?->estatuto ?? '')), 'UTF-8');
    $titularEsDocente = in_array($estatutoTitular, ['DOCENTE', 'PROFESOR', 'PROFESORA'], true) || str_contains($estatutoTitular, 'DOC');
    $fmtHours = function ($value) {
        if ($value === null || $value === '') return '0';
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    };
@endphp
@if($titularEsDocente)
    <div class="spacer"></div>
    <div class="row"><span class="label">HORAS AULA TITULAR :</span> <span class="value">CRONOLÓGICAS {{ $fmtHours($s->horas_aula_cronologicas_titular) }} / PEDAGÓGICAS {{ $fmtHours($s->horas_aula_pedagogicas_titular) }}</span></div>
    <div class="row"><span class="label">HORAS AULA REEMPLAZO :</span> <span class="value">CRONOLÓGICAS {{ $fmtHours($s->horas_aula_cronologicas_reemplazo) }} / PEDAGÓGICAS {{ $fmtHours($s->horas_aula_pedagogicas_reemplazo) }}</span></div>
@endif

<div class="footer">
    <div class="firma-section">

        @if($firmaSoloData || $timbreData)
            <div class="firma-layer">
                @if($timbreData)
                    <img class="timbre-img" src="{{ $timbreData }}" alt="Timbre">
                @endif

                @if($firmaSoloData)
                    <img class="firma-img" src="{{ $firmaSoloData }}" alt="Firma">
                @endif
            </div>
        @elseif($firmaComboData)
            <div class="firma-combo">
                <img class="firma-img" src="{{ $firmaComboData }}" alt="Firma y timbre">
            </div>
        @endif

        <div class="firma-text">
            <strong>MAKARENA PAREDES AGUILERA</strong><br>
            <strong>SUBDIRECTORA DE GESTION Y DESARROLLO DE LAS PERSONAS</strong><br>
            “POR ORDEN DEL DIRECTOR EJECUTIVO”<br>
            SERVICIO LOCAL DE EDUCACIÓN PUBLICA ANDALIEN COSTA
        </div>
    </div>

    @if($elaboradoPor !== '')
        <div class="elaborado-por">
            <strong>Elaborado por:</strong> {{ $elaboradoPor }}
        </div>
    @endif
</div>

</body>
</html>