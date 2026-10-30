# Maternidad

Disponible en **Trámites y operación → Maternidad**, para los roles activos `admin`, `funcionario_slep`, `coordinador_gdp`, `coordinador_uatp` y `supervisor_plani`. Cada embarazo o proceso es un expediente independiente, con UUID en las rutas, referencia y RUT. No se impone un único expediente por RUT.

## Ingreso y antecedentes

Las pestañas Asistentes de la educación y Docentes muestran los casos registrados, con búsqueda, establecimiento, estado de seguimiento y documentos pendientes. El ingreso individual busca el RUT con dígito verificador en `reemplazos_personal` y `solicitudes_reemplazo` (tabla existente singular), considerando la persona reemplazante efectivamente contratada antes de la propuesta. Permite elegir el vínculo cuando hay varias contrataciones o establecimientos.

Se prefieren nombres y apellidos separados. El nombre completo del padrón sigue la convención apellidos–nombres; la separación automática se indica para revisión, incluidos apellidos compuestos. Los datos corregidos se guardan en el expediente. No se modifican usuarios, padrón ni solicitudes de reemplazo. Si la fuente no informa estamento o calidad contractual, deben completarse antes de guardar.

Cada caso conserva establecimiento, RBD, comuna, tipo contractual, fechas de ingreso y término y una copia de la fuente utilizada. Los cambios posteriores del padrón no reescriben esa copia. Las actualizaciones registran sus valores anteriores y nuevos; las observaciones se agregan con fecha y responsable. Una versión de edición impide sobreescribir cambios simultáneos sin recargar.

## Documentos y acreditación

El ingreso individual requiere certificado de embarazo y su fecha. La Orden de trabajo es opcional y admite sólo PDF. Los demás respaldos admiten PDF, JPG, PNG o WEBP, hasta 10 MB por archivo. Informar FNH requiere certificado de nacimiento; activar Ley Dominga o lactancia requiere su respaldo. Registrar postnatal parental exige permiso, fechas y modalidad Completo o Compartido; adjuntar el permiso activa el registro. La REX tiene archivo y fecha propios.

Los casos importados pueden completar los documentos gradualmente. «Pendiente de respaldo» distingue un beneficio informado de uno registrado con documento; cargar un archivo no equivale a una aprobación administrativa. Los archivos se guardan en el disco **local privado**, bajo `maternidad/casos/{uuid}`, y se descargan mediante rutas autenticadas y autorizadas. Nunca se crea un enlace público. Adjuntar otro archivo conserva todas las versiones anteriores. No hay acciones de borrado de expedientes, documentos u observaciones.

## Fechas de seguimiento

Las fechas se calculan en el servidor, con fechas civiles y sin desbordar el año en los 29 de febrero:

| Antecedente | Cálculo |
| --- | --- |
| FPP informada | FPP + un año + 84 días: término estimado |
| FNH con certificado de nacimiento | FNH + un año + 84 días: término por nacimiento certificado |
| Ley Dominga activa con certificado y fecha | Fecha Ley Dominga + **30 días corridos**, con prioridad sobre FPP y FNH |
| Ampliación GDP de Ley Dominga | Fecha Ley Dominga + un año + 84 días, con la misma prioridad |
| Lactancia con solicitud y nacimiento acreditados | FNH + dos años; inicio entre nacimiento y segundo cumpleaños |

La ampliación está restringida a `coordinador_gdp` y `admin`, tanto por rol asignado como activo. Requiere causal **fallecimiento por parto prematuro** o **fallecimiento durante el parto** y fundamento. La regla, causal, fundamento y usuario quedan en el historial. Los demás roles pueden actualizar otros antecedentes conservando la decisión, pero no modificarla. La importación comienza con el plazo ordinario de 30 días; GDP revisa las excepciones en cada caso.

Se informa el permiso Ley Dominga de siete días hábiles; no se deduce un calendario de permiso sin resolución. Los plazos de esta pantalla implementan las reglas operativas confirmadas para el SGA. La DT describe el fuero general y diferencia la pérdida gestacional: [fuero maternal](https://www.dt.gob.cl/portal/1628/w3-article-60062.html), [pérdida gestacional](https://www.dt.gob.cl/portal/1628/w3-article-60072.html). Las ampliaciones se documentan mediante decisión de GDP; la pantalla no sustituye su resolución ni calcula extensiones médicas adicionales.

FPC se ingresa desde el antecedente, sin inferir una concepción. Los datos de hijos permiten nombre completo, nacimiento y RUT; se admiten hasta diez por caso. El nacimiento de seguimiento FNH se registra expresamente para el caso.

## Excel

Descargar la plantilla desde Ingreso masivo; no cambiar encabezados. Hoja **Casos**: `referencia_caso` y `rut` obligatorios. Los nombres, estamento y datos contractuales vacíos se completan desde la fuente; revisar la separación automática. `rbd` resuelve varios establecimientos y `fuente` permite elegir exactamente `padron:ID` o `reemplazo:ID`, identificable en el buscador individual. Estamento: `docente` o `asistente` (también AAEE); beneficios: Sí/No; modalidad: completo/compartido. Registro vacío usa la fecha de carga.

Hoja **Hijos**: `rut_funcionaria`, `referencia_caso`, `nombre_completo`, `fecha_nacimiento`, `rut_hijo`. Se vincula por RUT y referencia. Se rechazan hijos sin caso, referencias duplicadas para el mismo RUT y casos ya existentes. Puede repetirse el RUT con referencias distintas. Se admiten fechas de Excel y texto AAAA-MM-DD, DD-MM-AAAA o DD/MM/AAAA. No se evalúan fórmulas ni se importan archivos adjuntos.

Límites: 500 casos, 2.000 filas de hijos, 10 MB de XLSX y 50 MB de contenido descomprimido. La previsualización valida todo antes de crear expedientes; errores identifican hoja, fila y campo. El contenido temporal queda cifrado, pertenece al usuario que lo cargó y vence en 30 minutos. Confirmar vuelve a comprobar duplicados y crea el lote en una transacción; un fallo revierte todo. Reconfirmar el mismo lote no duplica casos y al confirmar se elimina el contenido temporal cifrado. Los casos existentes nunca se actualizan desde Excel.

## Instalación y verificación

El parche 2026.10.30.663 incorpora la migración aditiva `2026_10_30_180000_create_maternidad_tables.php`: casos, hijos, documentos, historial e importaciones. Las claves foráneas restringen la eliminación de antecedentes asociados. `down()` no borra expedientes ni respaldos; cualquier reversión de datos requiere un procedimiento autorizado. Desplegar el código y los recursos compilados y ejecutar las migraciones pendientes con el binario **absoluto de PHP 8.3** del servidor y `artisan migrate --force`; luego `artisan optimize:clear`. No se requiere un seeder ni nuevas notificaciones. No ejecutar operaciones destructivas ni Artisan con el PHP predeterminado de Composer en cPanel.

Pruebas: `php artisan test --compact tests/Feature/MaternidadTest.php`, aisladas en SQLite en memoria y almacenamiento temporal. Cubren fechas, roles, fuente del reemplazante, nombres, copia histórica, adjuntos privados y versiones, observaciones, concurrencia, rollback, Excel, expiración y confirmación repetida. La aplicación requiere la clave habitual de cifrado de Laravel para previsualizaciones; no se añade ninguna clave ni dato personal al parche.
