# Usabilidad de Dotación establecimiento

Parche `2026.10.5.570`, rama `feat/dotacion-usabilidad-asignacion`.

## Cambios del flujo

| Zona | Comportamiento |
| --- | --- |
| Navegación | Pestañas antes de los paneles de configuración e indicadores; cada vista de trabajo conserva sus enlaces, año y permisos. |
| Proceso guiado | Seis etapas visibles, estado textual, siguiente acción y enlaces que abren la sección correspondiente aunque esté plegada. |
| Configuración | Etiquetas asociadas a los campos, mensajes locales y explicación del máximo faltante o insuficiente y de la cobertura pendiente. |
| Docentes por asignatura | Búsqueda por asignatura, curso, docente asociado, RUT y título; filtros de asociación; apertura del nivel encontrado o con errores. |
| Selectores | Tema Bootstrap 5, títulos y disponibilidad, nombres accesibles y activación al utilizar los niveles o editores. Se conserva el selector nativo si no carga Select2. |
| Asignación | Búsqueda y filtros de sección, curso y pendientes; avance por curso; formularios por necesidad; explicación de acciones deshabilitadas. |
| Formularios | Recuperación de datos en el formulario que falló, mensajes junto al campo, estado Guardando y recuperación del botón al volver con el navegador. |
| Revisión contractual | Resumen por bloque con vigente, asignado, reservado, saldo y máximo; explicación de redondeo Parvularia y cobertura AAEE. |
| Docentes y sobredotación | Filtros por persona, bloque y estado; enlaces al detalle, las asignaciones o la justificación; reservas separadas sin duplicarlas. |
| Funciones | Estado actual separado de Validar, Observar y Eliminar; recuperación independiente de cada formulario y bloqueo con enlace al proceso. |
| Cursos combinados | Etiquetas únicas de grupo, curso y asignatura, identificación de horas aula y feedback al guardar. |
| Cargas anuales | Búsqueda por RBD/nombre y estado. Cero se distingue de un máximo sin configurar; totales y plantillas mantienen su alcance completo. |
| Pantallas pequeñas | Necesidades como tarjetas; tablas de revisión con desplazamiento horizontal local; campos, títulos y acciones adaptados al ancho disponible. |

Las reglas de conversión, prelación, titularidad, límites, reservas, autorización y persistencia continúan en los servicios y controladores existentes. El parche no cambia el contrato del padrón ni requiere migraciones.

## Archivos principales

- `resources/views/admin/dotacion-establecimiento/show.blade.php` e `index.blade.php`: estructura y navegación.
- Parciales `_proceso_etapas`, `_proceso_2027`, `_docentes_subsector`, `_catalogo_filtros`, `_campo_error`: proceso, asociación, filtros y errores.
- Parciales `_asignacion`, `_asignacion_editor`, `_asignacion_errores`, `_asignacion_filtros`, `_restore_context`: edición y recuperación del contexto.
- Parciales `_docentes`, `_sobredotacion`, `_resumen_contractual_bloques`, `_revision_filtros`, `_revision_sobrecargas`: revisión por docente y bloque.
- Parciales `_cursos_combinados`, `_asignaturas`: etiquetas y acciones de configuración/revisión.
- `resources/views/admin/dotacion-funciones/{show,convivencia-carga,maximos-carga}.blade.php`: funciones y cargas anuales.
- `resources/css/dotacion-establecimiento.css`, `resources/js/dotacion-asignacion.js`, `vite.config.js` y sus entradas en `public/build`: comportamiento y presentación compartidos.
- `config/changelog.php`: relación exacta de archivos y versión.

## Validación reproducible

Antes de Artisan, usar una configuración de pruebas que no pueda seleccionar la base real:

```powershell
$env:APP_CONFIG_CACHE = Join-Path $env:TEMP ('dotacion-tests-' + [guid]::NewGuid() + '.php')
$env:APP_ENV = 'testing'
$env:DB_CONNECTION = 'sqlite'
$env:DB_DATABASE = ':memory:'
$env:DB_URL = 'null'
$env:CACHE_STORE = 'array'
$env:SESSION_DRIVER = 'array'
$env:MAIL_MAILER = 'array'

php -d memory_limit=256M artisan test --filter='Dotacion|DirectorAdpNormativaAccessTest'
node --test tests/js/dotacion-asignacion.test.mjs
php artisan optimize:clear
npm run build
git diff --check
```

Ejecutar además `php -l` para cada PHP/Blade nuevo o modificado. Las pruebas nuevas usan `IsolatedSecurityTestCase`: verifican SQLite en memoria antes del bootstrap, impiden una configuración cacheada real y emplean almacenamiento simulado. Los datos de prueba son sintéticos.

Cobertura nueva: recuperación de formularios y contexto, filtros combinados, unidades aula/contrato, reservas y redondeo, alcance anual, permisos de presentación, enlaces por etapa, funciones y cargas, identificadores accesibles y arranque de JavaScript sin fragmento en la URL. Las pruebas existentes verifican los cálculos y las operaciones autorizadas del flujo.

Resultado local del 5 de octubre de 2026: **402 pruebas PHP / 4488 comprobaciones**, **8 pruebas JavaScript**, sin fallos. El comando PHP principal se inició con `memory_limit=256M`; el conjunto específico de las vistas contiene 24 pruebas y 352 comprobaciones. Vite compila correctamente, con avisos previos de deprecación Sass/Bootstrap y fuentes de admisión que se resuelven al ejecutar la aplicación.

## Revisión visual y límites

Previsualizaciones sintéticas con recursos compilados; anchos de escritorio, tablet y móvil. Se comprueba navegación por etapa, apertura de niveles, búsqueda, teclado, presentación de títulos y opciones de los selectores y desplazamiento horizontal local. El catálogo amplio contiene 100 asignaturas y 60 docentes (6000 opciones nativas).

Estas comprobaciones no equivalen a un flujo manual completo con usuarios reales ni a una medición del servidor cPanel. No se utilizó la base SQL descargada, no se modificó `.env`, no se guardaron archivos reales de usuarios y no se ejecutaron migraciones. La publicación y la verificación posterior en el ambiente de destino son pasos separados.
