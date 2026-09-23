<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { size: 216mm 340mm; margin: 25mm 30mm 25mm; }
        @font-face { font-family: "Century Gothic"; font-style: normal; font-weight: 400; src: url("{{ $fuenteRegularDataUri }}") format("truetype"); }
        @font-face { font-family: "Century Gothic"; font-style: normal; font-weight: 700; src: url("{{ $fuenteBoldDataUri }}") format("truetype"); }
        body { margin: 0; color: #000; font-family: "Century Gothic", sans-serif; font-size: 12pt; line-height: 1.14; }
        .header { width: 100%; border-collapse: collapse; margin: 0 0 14pt; }
        .header td { padding: 0; vertical-align: top; }
        .logo { width: 124pt; height: auto; display: block; }
        .reference { width: 48%; padding-top: 3pt !important; font-size: 11pt; line-height: 1.14; }
        .reference p { margin: 0 0 9pt; }
        .reference-label { font-weight: 700; }
        .date { margin: 0 0 18pt; text-align: right; }
        .address { margin: 0 0 18pt; line-height: 1.14; }
        .address p { margin: 0; }
        .address-name { font-weight: 700; text-transform: uppercase; }
        .address .spacer { height: 10pt; }
        .body-copy { margin: 0 0 10pt; text-align: justify; }
        .numbered { margin: 0 0 10pt; padding-left: 19pt; text-align: justify; text-indent: -19pt; }
        .numbered--new-page { page-break-before: always; }
        .table-intro { margin-top: 12pt; }
        .nomina { width: 100%; border-collapse: collapse; table-layout: fixed; margin: 8pt 0 13pt; font-size: 7.2pt; line-height: 1.12; }
        .nomina thead { display: table-header-group; }
        .nomina th { border: 0.6pt solid #000; padding: 3.5pt 2pt; text-align: center; font-weight: 700; vertical-align: middle; }
        .nomina td { border: 0.6pt solid #000; padding: 3pt 2pt; vertical-align: top; overflow-wrap: break-word; }
        .nomina tr { page-break-inside: avoid; }
        .center { text-align: center; }
        .signature { margin-top: 104pt; text-align: center; line-height: 1.16; page-break-inside: avoid; }
        .signature-name { font-weight: 700; text-transform: uppercase; }
        .signature-role { font-size: 11pt; text-transform: uppercase; }
        .visadores { margin-top: 13pt; font-size: 8.5pt; font-weight: 700; line-height: 1.18; page-break-inside: avoid; }
        .distribution { margin-top: 13pt; font-size: 8.5pt; line-height: 1.3; page-break-inside: avoid; }
        .distribution-title { font-weight: 700; text-decoration: underline; }
    </style>
</head>
<body>
    @php
        $meses = [1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril', 5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto', 9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'];
        $fechaOficio = 'Coronel, ' . $fechaEmision->day . ' de ' . $meses[(int) $fechaEmision->month] . ' de ' . $fechaEmision->year;
    @endphp

    <table class="header">
        <tr>
            <td style="width:52%"><img class="logo" src="{{ $logoDataUri }}" alt="Servicio Local de Educación Pública Andalién Costa"></td>
            <td class="reference">
                <p><span class="reference-label">ANT.:</span> Art. 4 Ley N° 21.109.</p>
                <p><span class="reference-label">MAT.:</span> Solicita evaluación de idoneidad sicológica de asistentes de la educación SLEP Andalién Costa.</p>
            </td>
        </tr>
    </table>

    <p class="date">{{ $fechaOficio }}</p>

    <div class="address">
        <p><strong>A:</strong> <span class="address-name">{{ $datosOficio['director_regional_nombre'] }}</span></p>
        <p>{{ $datosOficio['director_regional_cargo'] }}</p>
        <p>SERVICIO DE SALUD CONCEPCIÓN</p>
        <div class="spacer"></div>
        <p><strong>DE:</strong> <span class="address-name">{{ $datosOficio['director_ejecutivo_nombre'] }}</span></p>
        <p>{{ $datosOficio['director_ejecutivo_cargo'] }}</p>
        <p>SERVICIO LOCAL DE EDUCACIÓN PÚBLICA DE ANDALIÉN COSTA</p>
    </div>

    <p class="body-copy">Junto con saludar cordialmente, vengo en informar y solicitar a Ud. lo siguiente:</p>

    <p class="numbered"><strong>1.</strong> Con fecha 24 de noviembre de 2017, entró en vigor la Ley N° 21.040, que creó el nuevo sistema de educación pública, estableciendo las instituciones que lo componen y regulando su funcionamiento. Sistema que tiene como objetivo que el Estado provea, a través de los establecimientos educacionales de su propiedad y administración, que formen parte de los Servicios Locales de Educación Pública que son creados en la presente ley, una educación pública, gratuita y de calidad, laica, esto es, respetuosa de toda expresión religiosa, y pluralista, que promueva la inclusión social y cultural, la equidad, la tolerancia, el respeto a la diversidad y la libertad, considerando las particularidades locales y regionales, garantizando el ejercicio del derecho a la educación de conformidad a lo dispuesto en la Constitución Política de la República, en todo el territorio nacional.</p>

    <p class="numbered"><strong>2.</strong> Como es de público conocimiento, este Servicio Local asumió como sostenedor de los establecimientos públicos de las comunas de Coronel, Lota, San Pedro de la Paz y Santa Juana, a contar del 1 de enero de 2025, de acuerdo con lo dispuesto en el Artículo Octavo Transitorio de la Ley N° 21.040.</p>

    <p class="numbered numbered--new-page"><strong>3.</strong> El Art. 21 de la Ley N° 21.040 establece que la “dirección y administración de cada Servicio Local estará a cargo de un funcionario denominado Director Ejecutivo, quien será el jefe superior del servicio”. El Art. 22 letra a) de la Ley N° 21.040 establece como atribución del Director Ejecutivo del Servicio Local de Educación Pública la de: a) Dirigir, organizar, administrar y gestionar el Servicio Local, velando por la mejora continua de la calidad de la educación pública en el territorio de su competencia”.</p>

    <p class="numbered"><strong>4.</strong> El Art. 1° de la Ley N° 21.109 establece que: “La presente ley regula el estatuto funcionario de los asistentes de la educación que se desempeñen en establecimientos educacionales dependientes de los Servicios Locales de Educación Pública (en adelante “el servicio local” o “el servicio”).” Por su parte, el Artículo 3 señala que las relaciones laborales entre los servicios locales y los asistentes de la educación de su dependencia se regirán por las disposiciones de esta ley y, para estos efectos, serán considerados como funcionarios públicos.</p>

    <p class="numbered"><strong>5.</strong> En la misma línea argumental, el artículo 4 de la Ley N° 21.109 señala: “Asimismo, para desempeñarse como asistentes de la educación deberá acreditarse idoneidad sicológica para desempeñar dicha función, sobre la base de un informe que deberá emitir el Servicio de Salud correspondiente o el mismo servicio local a través de un profesional competente de su propia dotación, y no podrán encontrarse inhabilitados para trabajar con menores de edad o desempeñarse en establecimientos educacionales, de acuerdo con la Ley N° 20.594. La idoneidad sicológica para desempeñarse como asistente de la educación deberá acreditarse en forma previa a la celebración del respectivo contrato”.</p>

    <p class="numbered"><strong>6.</strong> Cabe indicar que, dentro de la dotación del SLEP de Andalién Costa, no se cuenta con un profesional para efectuar las evaluaciones a que se hace alusión en el art. 4 del párrafo anterior.</p>

    <p class="numbered table-intro"><strong>7.</strong> Por lo anterior, para dar cumplimiento a los requisitos de contratación establecidos en la citada ley, para el caso de los asistentes de la educación, vengo en solicitar se efectúen por parte de la repartición pública que Ud. dirige las evaluaciones de idoneidad sicológica correspondientes respecto de los funcionarios que en el recuadro siguiente se individualizan:</p>

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

    <p class="numbered"><strong>8.</strong> Teniendo en cuenta lo expuesto en los numerales anteriores, y a fin de plasmar el principio de coordinación entre los servicios públicos, dejo el contacto del funcionario(a) de la Subdirección de Gestión de Personas del Servicio Local de Educación Pública de Andalién Costa, Sr(a). <strong>{{ $datosOficio['contacto_nombre'] }}</strong>, mail <strong>{{ $datosOficio['contacto_email'] }}</strong>.</p>

    <p class="body-copy">Esperando una buena recepción a la presente solicitud, saluda atentamente,</p>

    <div class="signature">
        <div class="signature-name">{{ $datosOficio['director_ejecutivo_nombre'] }}</div>
        <div class="signature-role">{{ $datosOficio['director_ejecutivo_cargo'] }}</div>
        <div class="signature-role">Servicio Local de Educación Pública de Andalién Costa</div>
    </div>

    <div class="visadores">{{ $datosOficio['iniciales_firmantes_visadores'] }}</div>

    <div class="distribution">
        <div class="distribution-title">Distribución:</div>
        <div>- Destinatario</div>
        <div>- Archivo</div>
    </div>
</body>
</html>
