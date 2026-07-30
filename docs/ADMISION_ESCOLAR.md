# Módulo Admisión Escolar

Implementación aditiva para administrar y publicar una vitrina pública de establecimientos, utilizando `establecimientos` como fuente maestra y sin modificar su CRUD ni sus rutas existentes.

## Componentes incorporados

### Administración

- Ruta: `/admin/admision-escolar`
- Roles activos autorizados:
  - `admin`
  - `coordinador_uatp`
  - `comunicaciones`
- Módulo: `admin.admision-escolar`
- Funciones:
  - listado de todos los establecimientos;
  - filtros por nombre, RBD, comuna y estado;
  - indicador de completitud;
  - edición del sello educativo y descripción pública;
  - director/a, fotografía y reseña;
  - logo del establecimiento;
  - sitio web, Facebook e Instagram;
  - dirección y contacto público;
  - galería, portada, metadatos y orden;
  - previsualización privada;
  - publicación y despublicación explícitas.

### Vitrina pública

- Listado: `/admision-escolar`
- Ficha: `/admision-escolar/establecimientos/{slug}`
- No requiere autenticación.
- Solo consulta perfiles cuyo estado sea `publicado` y que tengan `publicado_at`.
- Filtros disponibles:
  - texto;
  - comuna;
  - nivel educativo;
  - tipo de establecimiento;
  - sector;
  - orden.

## Estructura de datos

### `admision_establecimientos`

Relación uno a uno con `establecimientos`. Contiene únicamente información editorial y de publicación.

### `admision_establecimiento_imagenes`

Relación uno a muchos con el perfil de Admisión. Contiene galería, portada, orden, texto alternativo y pie de fotografía.

Los datos oficiales —nombre, RBD, comuna, tipo, niveles educativos y coordenadas— continúan leyéndose desde `establecimientos`.

## Variables de entorno

Agregar al entorno de despliegue según corresponda:

```dotenv
ADMISION_PUBLICA_HABILITADA=false
ADMISION_MOSTRAR_PROXIMAMENTE=true
ADMISION_ANIO=2027
ADMISION_TITULO="Admisión Escolar"
ADMISION_POR_PAGINA=12
ADMISION_MEDIA_DISK=public
ADMISION_MEDIA_DIRECTORY=admision-establecimientos
ADMISION_MAX_IMAGEN_MB=5
ADMISION_MAX_IMAGENES_POR_CARGA=10
ADMISION_MAX_IMAGENES_POR_ESTABLECIMIENTO=20
ADMISION_MIN_IMAGENES_PUBLICACION=1
ADMISION_SAE_URL=https://www.sistemadeadmisionescolar.cl/
ADMISION_CONTACTO_EMAIL=
ADMISION_CONTACTO_TELEFONO=
```

La configuración predeterminada mantiene la administración habilitada y la vitrina pública en modo “Próximamente”.

## Despliegue recomendado

1. Respaldar la base de datos y `storage/app/public`.
2. Desplegar el código con `ADMISION_PUBLICA_HABILITADA=false`.
3. Ejecutar:

```bash
php artisan migrate --force
php artisan storage:link
php artisan optimize:clear
```

4. Ingresar con rol `admin`, `coordinador_uatp` o `comunicaciones`.
5. Completar y previsualizar establecimientos piloto.
6. Publicar únicamente fichas validadas.
7. Habilitar la vitrina:

```dotenv
ADMISION_PUBLICA_HABILITADA=true
```

8. Ejecutar nuevamente:

```bash
php artisan optimize:clear
```

## Publicación segura

Guardar una ficha no la publica. La acción **Publicar** verifica:

- nombre, RBD y comuna;
- sello educativo;
- director/a;
- fotografía del director/a;
- logo;
- imagen de portada;
- cantidad mínima de imágenes configurada.

Si una ficha publicada pierde uno de esos requisitos al editarla o eliminar archivos, se despublica automáticamente y conserva todo el contenido como borrador.

## Archivos

Los archivos se almacenan en:

```text
storage/app/public/admision-establecimientos/{establecimiento_id}/
    logo/
    director/
    galeria/
```

Se aceptan JPG, PNG y WebP. Cuando Intervention Image está disponible, las imágenes se orientan, reducen y convierten a WebP. Si el procesador no está disponible, se utiliza el archivo validado original.

No se aceptan SVG ni formatos ejecutables.

## Seguridad y permisos

El acceso administrativo requiere simultáneamente:

- autenticación y correo verificado;
- módulo `admin.admision-escolar` asignado;
- uno de los roles autorizados;
- que ese rol sea el contexto activo del usuario.

La vitrina pública no expone borradores ni previsualizaciones.

## Rollback operativo

Para ocultar inmediatamente la vitrina sin perder datos:

```dotenv
ADMISION_PUBLICA_HABILITADA=false
```

Luego:

```bash
php artisan optimize:clear
```

No es necesario revertir migraciones para un rollback operativo. Las tablas nuevas no son utilizadas por los módulos anteriores.

## Validaciones posteriores al despliegue

- comprobar `storage:link`;
- verificar carga de logo, fotografía y galería;
- probar previsualización con la vitrina deshabilitada;
- validar acceso de los tres roles;
- comprobar 403 para roles no autorizados;
- comprobar 404 pública para borradores;
- verificar filtros y paginación;
- revisar móvil, tablet y escritorio;
- ejecutar pruebas automatizadas del proyecto.
