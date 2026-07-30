<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { size: 216mm 356mm; margin: 1.45cm 1.85cm 1.55cm 1.85cm; }
        body {
            font-family: Arial, Helvetica, DejaVu Sans, sans-serif;
            font-size: 10.5pt;
            line-height: 1.5;
            color: #000;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 18px 0;
        }
        .header-table td {
            border: 0;
            padding: 0;
            vertical-align: top;
        }
        .logo-andac {
            width: 178px;
            height: auto;
        }
        .logo-gobierno {
            width: 88px;
            height: auto;
        }
        h1 {
            margin: 8px 0 18px 0;
            text-align: center;
            font-size: 12pt;
            line-height: 1.1;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1px;
        }
        p {
            margin: 0 0 8pt 0;
            text-align: justify;
            line-height: 1.5;
        }
        .clausula {
            font-weight: 700;
            text-transform: uppercase;
        }
        .center { text-align: center; }
        .firma-wrap {
            margin-top: 128px;
            width: 100%;
            page-break-inside: avoid;
        }
        .firma-table {
            width: 100%;
            border-collapse: collapse;
        }
        .firma-table td {
            width: 50%;
            text-align: center;
            vertical-align: top;
            border: 0;
            padding: 0 16px;
        }
        .firma-linea {
            border-top: 1px solid #000;
            margin: 0 auto 6px auto;
            width: 82%;
            height: 1px;
        }
        .firma-titulo {
            font-weight: 700;
        }
        .pie-institucional {
            margin-top: 38px;
            text-align: center;
            font-weight: 700;
            line-height: 1.25;
        }
        .nowrap { white-space: nowrap; }
        strong { font-weight: 700; }
    </style>
</head>
<body>
@php
    $meses = [1=>'enero',2=>'febrero',3=>'marzo',4=>'abril',5=>'mayo',6=>'junio',7=>'julio',8=>'agosto',9=>'septiembre',10=>'octubre',11=>'noviembre',12=>'diciembre'];
    $fechaTexto = $fechaEmision->format('d') . ' de ' . $meses[(int) $fechaEmision->format('n')] . ' de ' . $fechaEmision->format('Y');
$periodoInicio = $periodoInicioFiniquito ?? $s->fecha_inicio_trabajo;
    $periodoTermino = $periodoTerminoFiniquito ?? $s->fecha_termino;
    $inicio = $periodoInicio ? \Illuminate\Support\Carbon::parse($periodoInicio)->format('d/m/Y') : 'fecha no informada';
    $termino = $periodoTermino ? \Illuminate\Support\Carbon::parse($periodoTermino)->format('d/m/Y') : 'fecha no informada';
    $establecimiento = $s->establecimiento?->nombre_establecimiento ?? 'establecimiento no informado';
    $comuna = $s->establecimiento?->comuna ?? 'comuna no informada';
    $cargoFirmante = $firmante['cargo_documento'] ?? 'Director Ejecutivo';
    $montoEntero = (int) $monto;
    $montoFormateado = '$' . number_format($montoEntero, 0, ',', '.') . '.-';
    $esMontoCero = $montoEntero === 0;
@endphp

<table class="header-table">
    <tr>
        <td style="width: 62%;">
            @if(!empty($logoDataUri))
                <img class="logo-andac" src="{{ $logoDataUri }}" alt="Servicio Local de Educación Pública Andalién Costa">
            @endif
        </td>
        <td style="width: 38%; text-align: right;">
            @if(!empty($gobiernoLogoDataUri))
                <img class="logo-gobierno" src="{{ $gobiernoLogoDataUri }}" alt="Gobierno de Chile">
            @endif
        </td>
    </tr>
</table>

<h1>FINIQUITO DE CONTRATO DE TRABAJO</h1>

<p>En Coronel a {{ $fechaTexto }}, comparecen, por una parte, Don(a) <strong>{{ $firmante['nombre'] ?? '' }}</strong>, Rut {{ $firmante['rut'] ?? '' }}, {{ $cargoFirmante }} del Servicio Local de Educación Pública de Andalién Costa, RUT 61.981.100-3, con domicilio en Calle Manuel Montt 798, de la ciudad de Coronel, en su calidad de Empleador, y Don(a) <strong>{{ $nombreReemplazante }}</strong>, <strong>RUT N° {{ $rutReemplazante }}</strong> funcionario/a, del <strong>{{ $establecimiento }}</strong> de la comuna de {{ mb_strtoupper((string) $comuna, 'UTF-8') }}, {{ $jornada }}, dependiente del Servicio Local de Educación Pública de Andalién Costa, y acuerdan el siguiente finiquito.</p>

<p><span class="clausula">PRIMERO:</span> Don(a) <strong>{{ $nombreReemplazante }}, RUT N° {{ $rutReemplazante }}</strong> declara haber prestado funciones para el Servicio Local de Educación Pública de Andalién Costa desde el <strong>{{ $inicio }}</strong> hasta el <strong>{{ $termino }}</strong>, fecha esta última de terminación de sus servicios por la causal que se encuentra dispuesta en la <strong>{{ $causalLegal }}</strong>, {{ $glosaCausal }}, la cual, en este acto es aceptada por Don(a) <strong>{{ $nombreReemplazante }}</strong>, no teniendo reparo o reclamo alguno que formular en contra de la referida causal.</p>

@if($esMontoCero)
    <p><span class="clausula">SEGUNDO:</span> Con ocasión de la terminación de los servicios, las partes dejan expresa constancia de que el monto total a pagar por concepto de haberes e indemnizaciones asciende a la suma de <strong>{{ $montoFormateado }}</strong> Esta liquidación en valor cero se fundamenta en los siguientes puntos de derecho:</p>
    <p>A) <strong>Naturaleza del Feriado:</strong> Dada la calidad de asistente de la educación de el/la trabajador/a, su derecho a feriado se rige por el artículo 41 de la Ley N° 21.109, que establece un descanso fijo durante los periodos de interrupción de actividades escolares, esto es, entre los meses de enero y febrero, el que medie entre el término del año escolar y el comienzo del siguiente, y la interrupción invernal.</p>
    <p>B) <strong>Inaplicabilidad del Feriado Proporcional:</strong> Conforme a la jurisprudencia administrativa de la Contraloría General de la República en su Dictamen N° E287743N22, el feriado de los asistentes de la educación tiene una naturaleza y fundamentos distintos al establecido en el Código del Trabajo, motivo por el cual no procede el pago de feriado proporcional al término del vínculo laboral.</p>
    <p>C) <strong>Ausencia de Labores Esenciales:</strong> El/la trabajador/a declara expresamente que no fue llamado/a a cumplir labores esenciales de reparación, mantención, aseo o seguridad durante los periodos de interrupción escolar, razón por la cual no le asiste el derecho a percibir un feriado compensatorio según lo previsto en el artículo 73 del Código del Trabajo.</p>
    <p>En consecuencia, el Servicio Local de Educación Pública de Andalién Costa no adeuda suma alguna a el/la trabajador/a por los conceptos antes señalados ni por ningún otro derivado de la relación laboral que hoy fenece.</p>
@else
    <p><span class="clausula">SEGUNDO:</span> Con ocasión de la terminación de los servicios, las partes dejan expresa constancia de que el monto total a pagar por concepto de haberes e indemnizaciones asciende a la suma de <strong>{{ $montoFormateado }} ({{ $montoTexto }} PESOS)</strong>, monto informado para efectos de pago y liquidación del presente finiquito.</p>
@endif

<p><span class="clausula">TERCERO:</span> Don(a) <strong>{{ $nombreReemplazante }}</strong>, viene en declarar y dejar expresa constancia que durante todo el tiempo que prestó servicios para el Servicio Local de Educación Pública de Andalién Costa, recibió de éste, íntegra, correcta y oportunamente el sueldo, reajustes, asignaciones y bonificaciones que pudieron corresponderle en virtud de su contrato de trabajo, clase de trabajo ejecutado o por Ley.</p>

<p><span class="clausula">CUARTO:</span> En consecuencia, Don(a) <strong>{{ $nombreReemplazante }}</strong>, declara y deja expresa constancia que nada se le adeuda por los conceptos mencionados y por ningún otro, sean de origen legal o contractual, derivados de la prestación de servicios o la terminación de los mismos, motivo por el cual, no tiene reclamo ni cargo alguno que formular en contra de su ex empleador. Declara expresamente que durante el curso de la relación laboral no sufrió ningún tipo de accidente ni enfermedad en el marco de la ley 16.744 y decretos complementarios; declara también que el empleador ha dado fiel y cabal cumplimiento a lo dispuesto por el artículo 184 del Código del Trabajo, no teniendo nada que reprochar en lo que respecta a higiene y seguridad en el trabajo, ni tampoco en lo relativo al ambiente laboral, pues aquel siempre se preocupó por velar por el respeto de las garantías constitucionales de sus funcionarios, servidores y trabajadores, declarando en este aspecto que se han respetado cabalmente todos sus derechos, especialmente los de integridad física y psíquica, no discriminación, respeto a la honra y vida privada, y en general, todo el catálogo garantizado por la carta fundamental en el plano del trabajo.</p>

<p><span class="clausula">QUINTO:</span> En virtud de todo lo anteriormente expuesto y con pleno y cabal conocimiento de sus derechos Don(a) <strong>{{ $nombreReemplazante }}</strong>, otorga al Servicio Local de Educación Pública de Andalién Costa el más amplio, total y completo finiquito, en relación al contrato de trabajo que los vinculó y la terminación del mismo, no reservándose reclamo alguno, renunciando, en consecuencia, expresamente y desde ya, a toda acción que pudiera emanar de la relación laboral que los vinculó como asimismo de la terminación de la misma, especialmente declara que: su despido fue justificado, que los montos contenidos en el presente finiquito abarcan todas y cada una de las indemnizaciones legales, que sus cotizaciones previsionales, de salud y todas las que correspondan se encuentran debidamente pagadas; que no ha sido víctima de accidente del trabajo, y que el empleador siempre ha tomado las medidas necesarias para garantizar su seguridad y salud; que conoce las implicancias y contenido de una acción de tutela laboral y que sus presupuestos no aplican de modo alguno a su relación contractual. Por lo tanto, y, en definitiva, no existe acción judicial ni administrativa que pudiese quedar pendiente entre las partes.</p>

<p><span class="clausula">SEXTO:</span> De conformidad a lo expuesto en las cláusulas precedentes, las partes comparecientes acuerdan, declaran y dejan expresa constancia que en este acto no formulan ningún tipo de reserva de derechos y acuerdan que, en razón de no tener reclamos o materias pendientes, no formularán en el futuro ningún tipo de reserva de derechos, ya sea por las materias o estipendios expresamente consignados en este instrumento, como de aquellos respecto de los cuales no se hace mención expresa.</p>

<p><span class="clausula">SÉPTIMO:</span> El empleador declara que <strong>Don(a) {{ $nombreReemplazante }}, RUT N° {{ $rutReemplazante }}</strong>, hasta la fecha de término de su contrato de trabajo, no ha sido notificado por resolución judicial alguna, que obligue a efectuar retenciones o descuentos del presente finiquito por concepto de pensiones alimenticias, conforme a lo que dispone el Art. 13 de la Ley N° 21.389 del 18 de noviembre de 2021.</p>

<p><span class="clausula">OCTAVO:</span> Déjese constancia que, a contar del 01 de enero del 2025, el Servicio Local de Educación Pública se hace cargo de todas sus obligaciones y prerrogativas, los cuales involucran los pagos que incurra en cotizaciones previsionales y de salud, las cuales a la fecha de la suscripción del presente finiquito de trabajo y durante el período trabajado se encuentran al día.</p>

<p><span class="clausula">NOVENO:</span> El presente finiquito se suscribe en tres ejemplares, quedando uno en poder del trabajador.</p>

<p class="center">Previa lectura los comparecientes ratifican y firman.</p>

<div class="firma-wrap">
    <table class="firma-table">
        <tr>
            <td>
                <div class="firma-linea"></div>
                <div class="firma-titulo">Firma del Empleador</div>
                <div>{{ $cargoFirmante }}</div>
                <div>RUT: {{ $firmante['rut'] ?? '' }}</div>
            </td>
            <td>
                <div class="firma-linea"></div>
                <div class="firma-titulo">Firma del Trabajador</div>
                <div>RUT: {{ $rutReemplazante }}</div>
            </td>
        </tr>
    </table>
</div>

<div class="pie-institucional">
    SERVICIO LOCAL DE EDUCACIÓN PÚBLICA DE ANDALIÉN COSTA<br>
    RUT 61.981.100-3
</div>
</body>
</html>
