# Design System · SGA SLEP Andalién Costa

## Propósito y alcance

El módulo de **Cometidos funcionarios** es la referencia visual del sistema. Toda pantalla nueva y toda pantalla que se modifique debe converger hacia estas reglas: administración, trámites, formularios, listados, detalles, acceso y páginas públicas. Los correos y PDF conservan las restricciones de su medio, pero usan la misma identidad de color y jerarquía cuando corresponda. Esta guía define el diseño objetivo; su existencia no implica que todas las pantallas actuales ya estén adaptadas.

Referencias en el código:

- `resources/views/tramites/cometidos-funcionarios/index.blade.php`: encabezado, indicadores, filtros, pestañas, tablas, estados vacíos y paginación.
- `resources/views/tramites/cometidos-funcionarios/form.blade.php`: formulario por secciones, navegación de pasos, ayudas, campos y barra de acciones.
- `resources/views/tramites/cometidos-funcionarios/show.blade.php`: detalle, datos, etapas del flujo, documentos, historial y acciones por estado.
- `resources/views/tramites/cometidos-funcionarios/informe.blade.php` y `rendicion-reembolso.blade.php`: paneles de proceso y resúmenes.
- `resources/views/layouts/app.blade.php`: estructura compartida, navegación y variables `--slep-*`.
- `resources/scss/app.scss`: tipografía y compilación con Bootstrap 5/Vite.

## Reglas generales

1. Usar `layouts.app` en las vistas autenticadas y respetar su menú lateral, barra superior y área de contenido. Los layouts públicos deben conservar su función, pero acercarse a esta identidad al ser intervenidos.
2. Componer con Bootstrap 5 y Bootstrap Icons. Crear clases reutilizables para patrones compartidos; limitar los estilos propios de una página a necesidades específicas del módulo. No copiar grandes bloques de CSS entre vistas ni aplicar selectores globales que alteren módulos ajenos.
3. Mantener una misma jerarquía: contexto breve, título principal, descripción útil, acciones; después filtros o resumen y finalmente contenido. Una acción principal visible por bloque de decisión.
4. El color comunica función o estado. Ningún estado depende solo del color: acompañarlo de texto y, cuando ayude, icono.
5. El diseño debe responder a escritorio, tableta y móvil, sin ocultar información esencial ni crear desplazamiento horizontal de toda la página.
6. Al intervenir una pantalla o módulo, revisar todos sus elementos y componentes visibles. Cualquier componente existente que no siga estas reglas debe incorporarse al alcance del cambio y ajustarse antes de entregar: encabezados, filtros, formularios, selectores, tablas, estados, mensajes, acciones, vistas vacías y adaptación móvil. Aplicar el mismo criterio a documentos y vistas públicas, respetando las limitaciones de cada medio.

## Tokens visuales

Estos son los valores de referencia para las nuevas interfaces. Centralizar los tokens compartidos en el tema/layout existente cuando se implementen componentes globales. Hay variantes históricas dentro de Cometidos (por ejemplo, azul `#2563eb` en el listado y `#0d6efd` en el formulario); al consolidar un componente compartido, usar el token principal `#0d6efd` y revisar sus estados interactivos.

| Uso | Valor de referencia | Regla |
| --- | --- | --- |
| Acción principal | `#0d6efd` | Fondo azul y texto blanco; hover `#0b5ed7`. |
| Azul institucional oscuro | `#0b3d91` | Marca, navegación o énfasis puntual. |
| Fondo de aplicación | `#f5f7fb` | Superficie exterior de las pantallas. |
| Superficie principal | `#ffffff` | Tarjetas, paneles, campos y navegación. |
| Superficie secundaria | `#f8fafc` / `#f8fbff` | Filas, datos resumidos y cabeceras suaves. |
| Texto principal | `#0f172a` | Títulos y datos importantes. |
| Texto secundario | `#334155` | Texto de controles y contenido complementario. |
| Texto atenuado | `#64748b` | Ayudas, metadatos y etiquetas secundarias. |
| Borde | `#dbe4f0` | Separación discreta; variantes cercanas según componente. |
| Éxito / completado | `#0f5132` sobre `#ecfdf3` | Confirmaciones y etapas completas. |
| Advertencia / observado | `#8a4b00` sobre `#fff8e1` | Atención y observaciones. |
| Error / rechazado | `#b42318` sobre `#fff1f2` | Errores, rechazos y acciones peligrosas. |
| Información / en curso | `#0d47a1` sobre `#eef6ff` | Ayudas y etapa actual. |

Las superficies de estado llevan borde de la misma familia cromática. Reservar morado y otros acentos para categorías de proceso que realmente deban distinguirse; no asignar un color nuevo a cada pantalla. Conservar las variables `--slep-*` ya presentes en `layouts.app` y coordinar cualquier ajuste del SCSS con el layout para evitar diferencias entre Bootstrap y los componentes SLEP.

## Tipografía, espacio y forma

- Usar **Gilmer** para texto corriente y **MuseoSans900** para encabezados, según `resources/scss/app.scss`. Mantener alternativas del sistema si la fuente local no carga. No introducir otra familia por módulo.
- Un solo `h1` por pantalla. Título de página aproximado `1.7–2.2rem`, peso alto y color principal; título de panel cercano a `1.25–1.35rem`; texto base cercano a `1rem`; ayudas y metadatos `0.78–0.9rem`. Mantener el orden semántico de encabezados aunque cambie el tamaño visual.
- Usar la escala de espacios de Bootstrap: `0.5`, `0.75`, `1`, `1.5`, `2` y `3rem`. Separación entre bloques principales de `1–1.5rem`; relleno interior de paneles de `1–1.5rem`. Evitar márgenes arbitrarios para alinear elementos equivalentes.
- Paneles blancos con borde sutil, radio aproximado de `1–1.375rem` y sombra tenue. Elementos internos con radio aproximado de `0.75–1rem`; chips de estado redondeados por completo. Usar gradientes claros solo en encabezados o énfasis de marca; no como fondo de cada control.
- Los iconos acompañan títulos, estados y acciones; mantener tamaño y alineación coherentes. No usar iconos solos cuando el significado pueda ser ambiguo.

## Patrones por componente

### Encabezado de página

Ubicar sobre el contenido un encabezado con etiqueta de contexto opcional, icono, `h1`, descripción de una línea o breve párrafo y acciones alineadas al extremo. En móvil, apilar texto y acciones. El listado de Cometidos (`.cf-page-header`) es la referencia para pantallas de bandeja; para formularios y detalles conservar la misma jerarquía sin forzar su tamaño de cabecera.

### Paneles, resúmenes y navegación

Usar tarjetas de superficie blanca, cabecera clara y cuerpo con espacio consistente. Los indicadores de resumen deben mostrar etiqueta, cifra y explicación breve. Las pestañas o pasos muestran estado activo con texto, borde y fondo; el paso actual, completado, observado, rechazado y pendiente conservan los mismos significados en todo el sistema. La navegación de pasos no sustituye las etiquetas de las secciones.

### Formularios

Agrupar campos por tarea en secciones con título e icono. Asociar `label` e `id`; marcar los obligatorios con texto o asterisco visible y conservar validación del servidor. Usar `.form-control`, `.form-select`, `.form-check` y mensajes de ayuda/errores de Bootstrap como base; bordes suaves, radio cercano a `0.75rem` y foco azul visible. Mostrar los campos de solo lectura con fondo tenue. Colocar **Cancelar/Volver** como acción secundaria y **Guardar/Enviar** como acción principal al final. Deshabilitar una acción solo cuando exista una razón comprensible para el usuario.

En una fila, alinear los bloques por su borde superior y dejar la ayuda y los errores **dentro** del bloque al que pertenecen. No aplicar `height: 100%` a un contenedor si sus etiquetas, ayudas o errores quedan fuera de él: el contenido puede invadir la fila siguiente. Las secciones condicionales deben ocupar su propia celda de la cuadrícula y mantener espacio al aparecer o desaparecer.

### Selectores

Usar el `select` nativo para listas cortas y fáciles de recorrer. Para catálogos largos, como establecimientos o roles disponibles en un filtro, ofrecer búsqueda con Select2 y el tema Bootstrap 5 ya usado en el proyecto. El selector visible debe respetar el alto mínimo de `2.65rem`, radio cercano a `0.75rem`, borde `#dbe4f0`, texto `#0f172a` y foco azul. El desplegable debe mostrar búsqueda, opciones con espacio suficiente y el mismo estilo de foco/selección.

Conservar el `<select>` real, su `name`, `id`, grupos, opción vacía, valor seleccionado y validación del servidor. Asociar su `label` y mostrar el error junto al control visible. Si los recursos de Select2 no cargan, el selector nativo debe seguir siendo utilizable. Al cambiar una sección condicional o limpiar un valor por código, actualizar también el control enriquecido. Comprobar teclado, móvil y que el desplegable no quede recortado por una tarjeta o modal.

### Botones y enlaces de acción

Primario azul sólido; secundario blanco con borde gris; éxito verde; advertencia ámbar; peligro rojo, preferentemente con fondo blanco salvo confirmación explícita. Botones con texto directo, icono opcional, peso fuerte y esquinas redondeadas. Los enlaces que actúan como botones deben seguir la misma apariencia. Mantener un área de interacción cómoda, especialmente en móvil, y estados hover, foco, carga y deshabilitado reconocibles.

### Listados y tablas

Colocar filtros en un panel propio. Usar cabecera gris muy clara, separadores tenues, alineación consistente y suficiente espacio en las celdas. Mostrar nombre o dato principal con mayor peso y metadatos debajo. Las acciones de fila se agrupan sin competir con el dato principal. En anchos reducidos, envolver la tabla en un contenedor con desplazamiento horizontal local o cambiar a tarjetas si la lectura lo requiere. Incluir estado vacío con mensaje y acción posible; mostrar contexto de paginación.

Si una fila permite cambiar un estado, mostrar primero el **estado actual** con texto y color; ubicar después el formulario en un bloque propio. Cada control de la fila necesita una etiqueta visible y un `id` único. Dar espacio al botón de guardar y a los errores para que no se superpongan con la fila siguiente. Mantener el desplazamiento horizontal dentro de la tabla en pantallas pequeñas.

### Estados, mensajes y confirmaciones

Usar chips redondeados con texto explícito para estados; reutilizar el significado cromático de la tabla de tokens. Los avisos informativos y de validación muestran icono, mensaje breve y, si hace falta, el siguiente paso. Confirmar acciones irreversibles con el patrón existente de la aplicación y explicar su efecto. Mostrar el estado real del proceso; no inferirlo solo de un color decorativo.

### Detalles, documentos e historial

Mostrar los datos en bloques de etiqueta/valor sobre superficie suave, con textos largos que puedan partirse. Separar los documentos por nombre, metadatos y acciones de ver/descargar. Para procesos con varias aprobaciones, usar tarjetas de etapas y un historial cronológico como en `show.blade.php`; cada etapa debe presentar estado y responsable o siguiente acción de forma legible.

### Correos de notificación

Usar `resources/views/emails/layouts/institutional.blade.php` como base para correos transaccionales. El asunto, preencabezado y título deben identificar la etapa; el cuerpo presenta el motivo, un resumen breve con datos necesarios para actuar y una llamada a la acción hacia la vista autorizada. Reutilizar la identidad azul, superficies claras, tipografía legible y espaciado del layout institucional. Evitar tablas densas, colores como única señal de estado y datos personales que no sean necesarios para la gestión. Los destinatarios adicionales configurables se validan, se deduplican y se suman a los usuarios del rol responsable; documentar el evento y registrar el resultado del envío cuando exista auditoría de notificaciones.

## Adaptación y accesibilidad

- Seguir los cortes de Bootstrap que ya usa Cometidos: alrededor de `1200px` para panel lateral, `992px` para cuadrículas amplias, `768px` para encabezados/listas y `576px` para formularios compactos. Reducir columnas progresivamente hasta una columna cuando sea necesario.
- Conservar navegación por teclado y foco claramente visible. Mantener texto de botones y estados accesible; las funciones basadas en iconos requieren nombre accesible.
- Mantener contraste legible en texto normal, ayudas, estados y controles. No usar texto amarillo, verde claro o azul claro sobre blanco sin un tono de texto más oscuro.
- Mostrar errores junto al campo y un resumen cuando el formulario sea largo. No comunicar información únicamente mediante color, posición o animación.
- Las tablas, ayudas y barras de acciones deben seguir siendo utilizables con zoom y pantallas pequeñas.

## Aplicación en cambios futuros

1. Antes de editar una vista, revisar el archivo completo, su layout, estilos compilados, rutas, controladores, usos y pruebas relacionadas, conforme a `AGENTS.md`.
2. Identificar el patrón equivalente en Cometidos y reutilizar el componente compartido existente cuando lo haya. Si el patrón se repite, extraerlo a CSS/SCSS o Blade reutilizable en vez de duplicar estilos incrustados.
3. Aplicar los tokens y estados de esta guía a la pantalla o módulo intervenido. Ajustar también los componentes preexistentes que diverjan del Design System y preservar permisos, datos históricos, nombres de campos y comportamiento.
4. Revisar escritorio y móvil, navegación por teclado, validación y estados vacío/carga/error. Ejecutar las validaciones indicadas en `AGENTS.md`; si se modifica CSS o JavaScript compilado, ejecutar `npm run build`.

La documentación establece la dirección visual del sistema. La adaptación efectiva de cada módulo requiere cambios de interfaz específicos y su revisión funcional.
