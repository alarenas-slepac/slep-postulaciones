Tu trámite de {{ $tramite->tipo_label }} fue resuelto.

@if ($externalFlow ?? (bool) $tramite->bienios_flujo_externo)
Se adjuntan:
- Resolución firmada de reconocimiento de bienios.
- Detalle del cómputo administrativo.

La plataforma no genera cálculos preliminares. Los períodos y bienios reconocidos corresponden exclusivamente a los documentos adjuntos.
@else
Resumen:
- Total acumulado: {{ data_get($summary, 'duracion.years', 0) }} años, {{ data_get($summary, 'duracion.months', 0) }} meses y {{ data_get($summary, 'duracion.days', 0) }} días.
- Bienios reconocidos: {{ data_get($summary, 'bienios', 0) }}.
- Fecha de reconocimiento: {{ data_get($data, 'fecha_reconocimiento') ? \Illuminate\Support\Carbon::parse(data_get($data, 'fecha_reconocimiento'))->format('d-m-Y') : '—' }}.
- Fecha de antigüedad: {{ data_get($data, 'fecha_antiguedad_corta') ?: '—' }}.
- Tiempo faltante para el siguiente bienio: {{ data_get($summary, 'duracion_para_siguiente_bienio.years', 0) }} años, {{ data_get($summary, 'duracion_para_siguiente_bienio.months', 0) }} meses y {{ data_get($summary, 'duracion_para_siguiente_bienio.days', 0) }} días.

Períodos considerados:
@foreach ($periodos as $periodo)
- {{ \Illuminate\Support\Carbon::parse($periodo['inicio'])->format('d-m-Y') }} al {{ \Illuminate\Support\Carbon::parse($periodo['termino'])->format('d-m-Y') }} ({{ number_format((int) ($periodo['dias'] ?? 0), 0, ',', '.') }} días) · {{ $periodo['documento_label'] ?? 'Documento' }} · {{ $periodo['referencia'] ?: 'sin referencia' }}
@endforeach

Se adjunta la resolución en PDF.
@endif
