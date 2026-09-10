# Conservar contratos ausentes del Excel

Disponible en la revisión administrativa del padrón completo. Esta decisión sirve para una omisión comprobada del archivo: mantiene el mismo ID contractual y sus datos para el mes de carga. No es una incorporación ni una autorización automática de horas adicionales.

## Operación

1. Abrir **Ver filas del RUT** y localizar cada registro **Ausente** que deba continuar.
2. En **Resolver ausencia**, seleccionar **Conservar este ID en el período de carga** y registrar una justificación.
3. Para conservar varios contratos del mismo funcionario, seleccionar la opción en cada fila y usar **Registrar selecciones del mismo RUT**. Solo se envían las selecciones completadas de la página actual; si alguna es inválida, no se guarda ninguna del lote.
4. Revisar la cobertura actualizada. Si se conservó solo parte del RUT, decidir expresamente las demás ausencias: conservar o confirmar baja. El caso no se considera resuelto por una selección parcial.
5. Si ya se confirmó **Baja por ausencia del padrón completo**, retirar primero esa confirmación. No es compatible con conservar sus contratos.

## Efectos y protecciones

- En revisión solo se guardan decisiones auditadas, su justificación y, cuando corresponde, el nuevo total de jornada sujeto a autorización. El análisis original y el Excel no se reescriben.
- La cobertura propuesta suma los contratos conservados. Las asignaciones permanecen activas; no se liberan, trasladan ni duplican. Los excesos de asignación siguen las reglas vigentes de avisos y bloqueos.
- Se suman también las horas conservadas al control de más de 44 horas por RUT, en todos sus establecimientos. La autorización debe corresponder al total propuesto. Si cambia un total que ya estaba autorizado, se rechaza el cambio y se requiere una nueva revisión; no se sobrescribe la autorización anterior.
- Solo se permite un contrato regular vigente, del mismo año, de un mes no posterior al de carga y que no haya terminado antes de ese mes. Esta opción no prorroga contratos ni corrige tipos desconocidos: esos casos deben revisarse en el Excel.
- Se reconocen las denominaciones históricas PLANTA PIE/SEP y las variantes de CONTRATA, INDEFINIDO y PLAZO FIJO cuando el sufijo coincide exactamente con su financiamiento (ignorando mayúsculas y espacios). Se conservan el nombre contractual y los demás datos originales: este reconocimiento solo clasifica la conservación, no renombra contratos ni amplía los tipos del Excel nuevo. Reemplazos, suplencias y sufijos incompatibles siguen rechazándose.
- Un rechazo indica el ID contractual y el motivo concreto; no es necesario deducir si falló el período, la vigencia o el tipo de contrato.
- No puede seleccionarse un ID ajeno a la ausencia ni utilizarse el mismo ID en dos propuestas. Se revalidan la base, la identidad, las decisiones concurrentes y las dependencias antes de aplicar.
- Al aplicar mediante el ejecutor autorizado se actualiza el mes del ID existente y su marca de modificación. Se preservan jornada, financiamiento, fechas, origen del dato, documentos e historial mensual. La escritura y su auditoría forman una transacción; un fallo revierte los cambios y un reintento no los duplica.
- Antes de aplicar se puede cambiar una conservación por una decisión explícita de baja. El historial de decisiones se conserva y se recalculan los bloqueos; esto no libera asignaciones por sí mismo.

## Alcance del parche

No añade migraciones ni rutas ni modifica producción. Reutiliza las decisiones auditadas de revisión. **No habilita la aplicación definitiva en producción**: la pantalla continúa siendo de previsualización mientras el ejecutor general permanezca cerrado.

La corrección 2026.9.10.510 no cambia la huella base ni las decisiones guardadas. Tras desplegarla y limpiar las vistas/cachés con PHP 8.3, recargar la misma revisión y volver a registrar las conservaciones que fueron rechazadas. No es necesario volver a cargar el Excel por esta corrección; los controles por cambios reales de la base continúan vigentes.

Pruebas sintéticas: `php vendor/phpunit/phpunit/phpunit tests/Feature/PadronConservarAusentesTest.php`. Incluyen conservación múltiple, cobertura, decisión parcial, control de jornada, reversión, historial, reintento y rollback. La validación sobre una copia MariaDB es una etapa separada del despliegue; no ejecutar pruebas transaccionales sobre la base productiva.
