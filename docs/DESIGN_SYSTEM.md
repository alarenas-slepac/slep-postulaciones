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

### Barras de progreso y cifras animadas

Usar el patrón compartido `partials.progreso-animado`, con sus recursos `resources/css/progreso-animado.css` y `resources/js/progreso-animado.js`. La etiqueta va a la izquierda y la cantidad y porcentaje a la derecha, con números tabulares. La pista es clara, delgada y redondeada; el relleno usa azul institucional `#0b3d91` a `#0d6efd`, con un pequeño punto claro al extremo. Conservar las superficies blancas y los textos del sistema; no copiar el fondo oscuro ni los colores rosados de referencias externas.

- El servidor entrega el valor final, el total, el porcentaje con un decimal y la barra completa. Siempre indicar qué representa el denominador y conservar los datos legibles sin JavaScript.
- Al entrar al menos un 20 % del bloque en pantalla, animar el relleno y contar las cifras y porcentajes desde cero durante un segundo, con desaceleración suave. Ejecutar una sola vez por bloque; volver a desplazar la página no reinicia el conteo.
- Las barras se observan con `IntersectionObserver` y los contadores usan `requestAnimationFrame`; no usar temporizadores o escuchas permanentes de scroll. Para animar un conjunto de cifras, agrupar elementos `data-slep-count` dentro de `data-slep-reveal` sin anidar grupos.
- Con `prefers-reduced-motion: reduce`, sin soporte de observación o al imprimir, mostrar inmediatamente los valores finales. Si la preferencia cambia durante la animación, finalizarla. No parpadear, mover paneles ni introducir cambios de tamaño.
- Mantener etiquetas únicas y `role="progressbar"` con valores ARIA finales. Los números visuales animados llevan `aria-hidden="true"` y una alternativa estática accesible; no anunciar cada fotograma con `aria-live`.
- Con total desconocido o cero no inventar un porcentaje: mostrar «—» y explicar el dato faltante. Si el resultado supera el total, la pista se limita al 100 %, se conserva la cifra real y se explica la inconsistencia. En resultados por opción sin votos válidos, mostrar 0 %.
- Las cantidades, porcentajes y barras deben verificarse en escritorio, móvil, movimiento reducido, ausencia de JavaScript y con valores cero, completos y superiores al total.

Ejemplo de inclusión: `@include('partials.progreso-animado', ['id' => 'participacion-padron', 'etiqueta' => 'Votantes del padrón', 'valor' => 320, 'maximo' => 1000, 'detalle' => '320 de 1.000 personas.'])`. El parcial carga sus recursos una sola vez mediante las pilas `styles` y `scripts` del layout.

### Formularios

Agrupar campos por tarea en secciones con título e icono. Asociar `label` e `id`; marcar los obligatorios con texto o asterisco visible y conservar validación del servidor. Usar `.form-control`, `.form-select`, `.form-check` y mensajes de ayuda/errores de Bootstrap como base; bordes suaves, radio cercano a `0.75rem` y foco azul visible. Mostrar los campos de solo lectura con fondo tenue. Colocar **Cancelar/Volver** como acción secundaria y **Guardar/Enviar** como acción principal al final. Deshabilitar una acción solo cuando exista una razón comprensible para el usuario.

En una fila, alinear los bloques por su borde superior y dejar la ayuda y los errores **dentro** del bloque al que pertenecen. No aplicar `height: 100%` a un contenedor si sus etiquetas, ayudas o errores quedan fuera de él: el contenido puede invadir la fila siguiente. Las secciones condicionales deben ocupar su propia celda de la cuadrícula y mantener espacio al aparecer o desaparecer.

### Selectores

Usar el `select` nativo para listas cortas y fáciles de recorrer. Para catálogos largos, como establecimientos o roles disponibles en un filtro, ofrecer búsqueda con Select2 y el tema Bootstrap 5 ya usado en el proyecto. El selector visible debe respetar el alto mínimo de `2.65rem`, radio cercano a `0.75rem`, borde `#dbe4f0`, texto `#0f172a` y foco azul. El desplegable debe mostrar búsqueda, opciones con espacio suficiente y el mismo estilo de foco/selección.

Conservar el `<select>` real, su `name`, `id`, grupos, opción vacía, valor seleccionado y validación del servidor. Asociar su `label` y mostrar el error junto al control visible. Si los recursos de Select2 no cargan, el selector nativo debe seguir siendo utilizable. Al cambiar una sección condicional o limpiar un valor por código, actualizar también el control enriquecido. Comprobar teclado, móvil y que el desplegable no quede recortado por una tarjeta o modal.

#### Selectores con presentación institucional en listados

Cuando se requiere reemplazar toda la apariencia nativa de una pantalla, usar
`resources/js/slep-listados.js` con el contenedor `data-slep-listing`. El paquete
incluye localmente Select2, su tema Bootstrap 5, Bootstrap Icons y `resources/css/slep-listados.css`;
no depende de CDN ni modifica el jQuery de otros módulos. Mantener el control
HTML como fuente de los valores y como alternativa si no se ejecuta JavaScript.

- `data-slep-select="search"`: catálogo con búsqueda, grupos y selección única.
- `data-slep-select="compact"`: lista corta con desplegable institucional, sin
  buscador, por ejemplo la cantidad de resultados por página.
- `data-slep-select="tags"`: selección o texto libre, por ejemplo una mención.
  Mantener el límite validado por el servidor; este patrón admite hasta 255 caracteres.
- Usar etiquetas visibles y propagar `aria-labelledby`, ayudas, errores y estado
  inválido al combobox. Al abrir, llevar el foco al buscador. Conservar Escape,
  flechas y Enter. Los textos de opciones siempre se escapan.
- Los filtros condicionales se ocultan y deshabilitan juntos, limpiando sólo los
  valores que dejan de aplicar y actualizando Select2 al cambiar el área.
- Para números enteros sencillos, el campo institucional puede usar `type="text"`
  e `inputmode="numeric"`, sin flechas del navegador. Mostrar unidad, rango y error
  junto al campo. Conservar validación de servidor y ofrecer mensajes propios,
  sin ventanas emergentes de validación nativa.
- Mostrar el texto completo mediante ajuste de líneas. No depender de `title`
  ni de un tooltip del navegador para información necesaria.

### Botones y enlaces de acción

Primario azul sólido; secundario blanco con borde gris; éxito verde; advertencia ámbar; peligro rojo, preferentemente con fondo blanco salvo confirmación explícita. Botones con texto directo, icono opcional, peso fuerte y esquinas redondeadas. Los enlaces que actúan como botones deben seguir la misma apariencia. Mantener un área de interacción cómoda, especialmente en móvil, y estados hover, foco, carga y deshabilitado reconocibles.

### Listados y tablas

Colocar filtros en un panel propio. Usar cabecera gris muy clara, separadores tenues, alineación consistente y suficiente espacio en las celdas. Mostrar nombre o dato principal con mayor peso y metadatos debajo. Las acciones de fila se agrupan sin competir con el dato principal. En anchos reducidos, envolver la tabla en un contenedor con desplazamiento horizontal local o cambiar a tarjetas si la lectura lo requiere. Incluir estado vacío con mensaje y acción posible; mostrar contexto de paginación.

Si una fila permite cambiar un estado, mostrar primero el **estado actual** con texto y color; ubicar después el formulario en un bloque propio. Cada control de la fila necesita una etiqueta visible y un `id` único. Dar espacio al botón de guardar y a los errores para que no se superpongan con la fila siguiente. Mantener el desplazamiento horizontal dentro de la tabla en pantallas pequeñas.

### Estados, mensajes y confirmaciones

Usar chips redondeados con texto explícito para estados; reutilizar el significado cromático de la tabla de tokens. Los avisos informativos y de validación muestran icono, mensaje breve y, si hace falta, el siguiente paso. Confirmar acciones irreversibles con el patrón existente de la aplicación y explicar su efecto. Mostrar el estado real del proceso; no inferirlo solo de un color decorativo.

### Tarjetas de estado dentro de una fila

Usar `partials.ui.estado-laboral` y las clases compartidas `.slep-record-state`
para mostrar antecedentes laborales en una columna propia del registro. Las
tarjetas llevan radio de `.875rem`, borde fino con acento lateral, título con
icono y detalles legibles a la vista, sin depender de hover o de colorear toda
la fila. El parcial recibe contratos y reemplazos ya consultados; no hace
consultas ni decide nuevas reglas de vigencia.

| Variante | Uso en el buscador | Colores |
| --- | --- | --- |
| `--warning` | Vinculación laboral activa registrada: establecimiento, RBD, tipo, horas y término de cada contrato | Texto `#8a4b00`, fondo amarillo `#fff8e1`, borde `#f3d99c`, acento `#c58c16` |
| `--success` | Trabajo externo informado y su observación | Texto `#0f5132`, fondo `#ecfdf3`, borde `#b8e2c8` |
| `--info` | Reemplazo vigente: establecimiento, solicitud y término | Texto `#0d47a1`, fondo `#eef6ff`, borde `#c9dcf5` |
| `--neutral` | Sin vinculación laboral activa registrada | Texto `#475569`, fondo `#f8fafc`, borde `#dbe4f0` |

Una persona puede tener varias tarjetas simultáneas. Mostrar siempre primero
la vinculación activa amarilla y conservar todos sus establecimientos; un aviso
de trabajo externo no la oculta. La ausencia de vínculo registrado no acredita
disponibilidad. Las fechas o campos desconocidos deben indicarse como tales,
sin inventar un tipo contractual ni un estado laboral.

Los chips `.slep-status-pill` usan las mismas variantes; `--danger` se reserva
para faltantes o errores con texto `#b42318`, fondo `#fff1f2` y borde `#fecdd3`.
Para documentos, distinguir carga de aprobación administrativa.

### Listado adaptable y paginación institucional

El contenedor `.slep-listing` comparte cabecera, paneles, botones, leyenda,
filtros y estados vacíos inspirados en Cometidos, con un único `h1` y acciones
de al menos `2.75rem`. En escritorio, agrupar los datos por tarea y mantener
el desplazamiento horizontal dentro de la tabla. Por debajo de 768 px, las
filas se apilan como fichas con etiquetas `data-label`; conservar roles de
tabla, encabezados accesibles y toda la información, incluidas las tarjetas
laborales. Nombres y observaciones extensas deben partirse sin desbordar.

Usar `partials.ui.paginacion` con `paginador` y una `etiqueta` accesible. Mostrar
intervalo y total reales, enlaces anterior/siguiente, primera/última página y
una ventana de hasta cinco páginas alrededor de la actual. Los saltos usan
puntos suspensivos, los extremos deshabilitados son texto no interactivo y la
página actual lleva `aria-current="page"`. Mantener los filtros en las URL del
paginador. No mezclar la paginación Tailwind predeterminada con Bootstrap.

Los filtros muestran «Buscando…» y `aria-busy` al enviar; al volver con el
navegador se restauran. Los errores van junto a los controles y en un resumen
enfocable. Comprobar escritorio, móvil, teclado, ausencia de JavaScript, estados
vacíos, errores y coincidencia de varios estados en una misma persona.

### Detalles, documentos e historial

Mostrar los datos en bloques de etiqueta/valor sobre superficie suave, con textos largos que puedan partirse. Separar los documentos por nombre, metadatos y acciones de ver/descargar. Para procesos con varias aprobaciones, usar tarjetas de etapas y un historial cronológico como en `show.blade.php`; cada etapa debe presentar estado y responsable o siguiente acción de forma legible.

#### Fichas de personas y formularios de vinculación

La ficha del buscador usa `resources/js/postulante-ficha.js` y
`resources/css/postulante-ficha.css`, sobre los componentes de `slep-listados`.
Conservar un único título principal, identidad y acciones de volver, ver y descargar.
La navegación por anclas permite llegar a datos, estado laboral, restricciones y
solicitudes sin ocultar contenido. Los datos usan listas semánticas `dl/dt/dd`,
dos columnas en escritorio y una en móvil. Mostrar los valores desconocidos
explícitamente; el historial y las restricciones tienen bloques propios.

- Reutilizar `partials.ui.estado-laboral` en la ficha: `mostrarAuditoria` añade
  fecha y responsable con relaciones ya cargadas; `gestionarContratos` habilita
  acciones sólo si el controlador las autoriza. Los permisos de documentos,
  vinculación, trabajo externo y solicitudes conservan sus reglas independientes.
- Las ventanas usan Bootstrap Modal con cabecera clara, borde institucional,
  radio de `1.375rem`, cierre con nombre accesible y botones de 44 px. Mantener
  Escape, retención y retorno del foco. Reabrir la ventana correspondiente tras
  errores del servidor y conservar sus valores. La finalización explica su efecto
  y pide motivo; el registro anterior permanece en el historial.
- Las filas dinámicas se agrupan en `fieldset/legend`, numeradas y con etiquetas
  e identificadores únicos. Explicar el mínimo y máximo de filas, deshabilitar
  acciones en esos extremos y anunciar la cantidad con `aria-live="polite"`.
  Al retirar una fila, destruir su Select2; al agregarla, usar `enhanceSelects`.
  El desplegable se inserta dentro del modal para conservar el foco y la búsqueda.
- El interruptor conserva un checkbox real con `role="switch"` y etiqueta
  pulsable. Su presentación propia usa azul al activar, pista gris al desactivar
  y foco visible; debe funcionar con la barra espaciadora.
- Para fechas con presentación institucional, empaquetar Flatpickr localmente,
  en español y con `disableMobile: true`, calendario dentro del modal y clase
  `.slep-date-calendar`. Mostrar `DD-MM-AAAA` y enviar `AAAA-MM-DD` desde el
  campo original. La alternativa sin JavaScript muestra el formato ISO requerido.
  Conservar etiquetas, ayuda y errores vinculados al control visible, teclado,
  selección y acción «Limpiar fecha». Evitar el selector nativo de fechas.
  Cuando el tipo contractual sea Indefinido, limpiar y deshabilitar la fecha.
- Los errores se presentan junto al control y en un resumen; el primer campo
  inválido recibe el foco. Mostrar «Guardando…» durante el envío y restaurar la
  acción al volver con el navegador. La validación del servidor es obligatoria.

Verificar también varias vinculaciones simultáneas, historial vencido o
desactivado, restricciones judiciales/manuales, roles de sólo consulta, filas
agregadas y retiradas repetidamente, calendario y formularios en móvil.

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
