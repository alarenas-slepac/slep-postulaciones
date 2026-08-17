<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 32px 38px; }
        body { color: #263238; font-family: DejaVu Sans, sans-serif; font-size: 11px; }
        h1 { margin: 0 0 4px; color: #174a7e; font-size: 22px; }
        h2 { margin: 18px 0 8px; color: #174a7e; font-size: 15px; }
        .subtitle { margin: 0 0 14px; color: #607286; }
        .box { margin: 12px 0; padding: 12px 14px; border: 1px solid #ccd5dd; }
        dl { margin: 0; }
        dt { margin-top: 7px; font-weight: bold; }
        dd { margin: 2px 0 7px; }
        .photo { width: 48%; display: inline-block; margin: 0 1% 12px 0; vertical-align: top; page-break-inside: avoid; }
        .photo img { width: 100%; max-height: 255px; display: block; object-fit: contain; }
        .photo span { display: block; margin-top: 3px; color: #607286; font-size: 9px; text-align: center; }
    </style>
</head>
<body>
    <h1>Ticket de Incidencia {{ $ticket->numero }}</h1>
    <p class="subtitle">Sistema SGA · SLEP Andalién Costa</p>
    <div class="box">
        <dl>
            <dt>Incidencia</dt><dd>{{ $ticket->incidencia->tipo_label }}</dd>
            <dt>Detalle</dt><dd>{{ $ticket->incidencia->descripcion ?: 'Sin detalle informado' }}</dd>
            <dt>Establecimiento</dt><dd>{{ $ticket->incidencia->establecimiento?->nombre_establecimiento ?? '—' }}</dd>
            <dt>Unidad responsable</dt><dd>{{ $ticket->unidad_departamento }}</dd>
            <dt>Subdirección</dt><dd>{{ $ticket->subdireccion_dependencia }}</dd>
            <dt>Responsable</dt><dd>{{ $ticket->responsable?->nombre_completo ?? '—' }}</dd>
            <dt>Fecha de creación</dt><dd>{{ $ticket->created_at?->format('d/m/Y H:i') }}</dd>
            <dt>Tiempo de resolución</dt><dd>Hasta el {{ $ticket->vence_en->format('d/m/Y H:i') }}</dd>
        </dl>
    </div>
    @if($imagenesPdf->isNotEmpty())
        <h2>Registro fotográfico del establecimiento</h2>
        <div>
            @foreach($imagenesPdf as $imagen)
                <div class="photo">
                    <img src="{{ $imagen }}" alt="Fotografía {{ $loop->iteration }}">
                    <span>Fotografía {{ $loop->iteration }} de {{ $imagenesPdf->count() }}</span>
                </div>
            @endforeach
        </div>
    @endif
</body>
</html>
