@php
    $tituloOrden = $numero === null ? 'Orden de Trabajo · Vista previa' : 'Orden de Trabajo N° '.$numero;
@endphp
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $tituloOrden }}</title>
    <style>
        @page { margin: 42pt 64pt; }
        body { font: 10pt DejaVu Sans, sans-serif; color: #171717; }
        .logo { width: 152pt; }
        h1 { margin: 46pt 0 16pt; text-align: center; font-size: 13pt; text-decoration: underline; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 5pt 0; vertical-align: top; text-align: left; }
        th { width: 135pt; font-size: 9pt; }
        .separador { width: 9pt; }
        @include('pdf.partials.orden-trabajo-firma-estilos')
        .firma-section { page-break-inside: avoid; }
        .firma-layer { height: 176px; margin-bottom: 0; }
        .firma-layer .firma-img { width: 220px; margin-left: -110px; }
        .firma-layer .timbre-img { width: 132px; margin-left: -66px; }
        .firma-text { padding-top: 6px; }
        .firma-combo { margin-bottom: 0; }
        .respaldo { margin-top: 28pt; font-size: 8pt; }
    </style>
</head>
<body>
    @php
        $logo = 'data:image/png;base64,'.base64_encode(file_get_contents(resource_path('branding/pdf/logo-andaliencosta.png')));
        $fecha = fn ($valor) => \Carbon\CarbonImmutable::parse($valor)->format('d/m/Y');
        $horas = rtrim(rtrim(number_format((float) $reemplazo['horas'], 2, ',', ''), '0'), ',');
        $capital = fn ($valor) => mb_strtoupper((string) $valor, 'UTF-8');
        $filas = [
            'NOMBRE' => $capital($reemplazo['nombre']),
            'RUT' => \App\Support\Rut::format($prorroga->rut) ?: '—',
            'ESTABLECIMIENTO' => $capital($reemplazo['establecimiento']),
            'CARGO' => $capital($reemplazo['cargo']),
            'JORNADA' => $horas.' HRS',
            'A CONTAR DE' => $fecha($vigencia['desde']),
            'FECHA DE TÉRMINO' => $fecha($vigencia['hasta']),
            'MOTIVO U OBSERVACIÓN' => $motivo,
            'FINANCIAMIENTO' => $capital($reemplazo['desglose']),
        ];
    @endphp
    <img class="logo" src="{{ $logo }}" alt="SLEP Andalién Costa">
    <h1>{{ mb_strtoupper($tituloOrden) }}</h1>
    <table>
        @foreach ($filas as $etiqueta => $valor)
            <tr><th scope="row">{{ $etiqueta }}</th><td class="separador">:</td><td>{{ $valor }}</td></tr>
        @endforeach
    </table>
    @include('pdf.partials.orden-trabajo-firma')
    <p class="respaldo">
        @if ($inicialesEmisor !== '')
            /{{ $inicialesEmisor }}<br>
        @endif
        @if ($prorroga->tieneResolucion())
            Resolución N° {{ $prorroga->numero_resolucion }} del {{ $fecha($prorroga->fecha_resolucion) }} ·
        @endif
        Reemplazo {{ $reemplazo['solicitud'] }}
    </p>
</body>
</html>
