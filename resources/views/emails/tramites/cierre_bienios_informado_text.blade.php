Trámite de Reconocimiento de Bienios finalizado

Estimado/a {{ $tramite->nombre_completo_snapshot ?: ($tramite->user->nombre_completo ?? 'usuario/a') }}:

Informamos que su Trámite de Reconocimiento de Bienios ha sido finalizado.

Posteriormente será cargada la resolución exenta respectiva y en el próximo pago de remuneraciones se verá reflejado el pago correspondiente de bienios.

Resumen:
- Trámite: Reconocimiento de Bienios
- N° de trámite: #{{ $tramite->id }}
- Fecha de reconocimiento: {{ $fechaReconocimiento }}
- Estado informado: Finalizado informado

{{ $platformName ?? config('brand.platform_name', 'Plataforma SLEP Andalién Costa') }}
Este mensaje fue generado automáticamente. No responda directamente a este correo.
