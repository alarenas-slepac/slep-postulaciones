Solicitud de Reconocimiento de Bienios recibida

Estimado/a {{ $tramite->nombre_completo_snapshot ?: ($tramite->user->nombre_completo ?? $tramite->user->name ?? 'usuario/a') }}:

Informamos que su solicitud de Reconocimiento de Bienios fue recibida correctamente en la {{ $platformName ?? config('brand.platform_name', 'Plataforma SLEP Andalién Costa') }}.

El plazo máximo estimado de tramitación es de {{ $plazoMaximoDias ?? 30 }} días corridos desde la recepción de la solicitud, sujeto a revisión documental y validación de antecedentes.

Resumen:
- Trámite: Reconocimiento de Bienios
- N° de solicitud: #{{ $tramite->id }}
- Fecha de recepción: {{ $fechaRecepcion }}
- Estado inicial: Enviado
- Plazo máximo estimado: {{ $plazoMaximoDias ?? 30 }} días corridos

Recibirá notificaciones al correo registrado en su cuenta cuando existan avances o resultados asociados a la revisión.

Este mensaje fue generado automáticamente por la {{ $platformName ?? config('brand.platform_name', 'Plataforma SLEP Andalién Costa') }}. No responda directamente a este correo.
