# SLEP Postulaciones (Laravel 12)

Sistema de **postulaciones** y **gestión de antecedentes** para establecimientos SLEP. Incluye perfil de postulante con checklist dinámico, documentos requeridos con flujo de revisión, catálogos (menciones/subsectores), **mensajería interna** tipo chat, exportación **PDF** del perfil y notificaciones por **email / SMS / WhatsApp**.

---

## ✨ Características

* **Autenticación y Roles** (Spatie Permission): `admin`, `funcionario_slep`, `coordinador_gdp`, `coordinador_uatp`, `postulante`.
* **Perfil del postulante**

  * Formulario dinámico (reglas por estamento/área).
  * **Checklist** visual en tiempo real y % de avance.
  * Región → Comuna dependiente.
  * Foto con thumbnail (Intervention Image v3).
  * **PDF** de perfil (Dompdf v4) con logo y colores de marca.
  * Autofill Nº de Cuenta si Banco=BancoEstado y Tipo=Cuenta RUT.
* **Documentos**

  * Catálogo condicional por estamento/área.
  * Subida **solo PDF** (MIME/extensión, 10MB).
  * Renombrado: `RUT_NombreCompleto_TipoDocumento.pdf`.
  * Estados: `pending / approved / rejected` + revisor/fecha.
  * Previsualización PDF + **ZIP** de aprobados.
  * Avisos por email; si es **rechazado**, incluye motivo.
* **Menciones / Subsectores**

  * CRUD (admin / funcionario_slep).
  * Importador desde Excel.
* **Mensajería interna**

  * Conversaciones y mensajes con *polling*.
  * Postulante **solo responde**; otros roles **inician y responden**.
  * **Presencia** (online/offline) con `last_seen`.
  * Avatar con inicial y color por rol; punto verde/rojo de conexión.
* **Notificaciones**

  * Email por cambios de documentos y mensajes nuevos.
  * **SMS (Twilio)** y **WhatsApp (Meta Cloud API)** opcionales.

---

## 🧱 Stack & Requisitos

* **PHP** 8.3+, **Laravel** 12.x, **MySQL** 8+
* Node 18+, npm 9+
* Extensiones PHP: `zip`, `fileinfo`, `mbstring`, `openssl`, `pdo_mysql`, `gd`, `exif`
* Paquetes clave:

  * `spatie/laravel-permission`
  * `intervention/image:^3`
  * `barryvdh/laravel-dompdf:^4`

---

## 🚀 Instalación

```bash
# 1) Clonar
git clone https://github.com/<ORG>/<REPO>.git
cd <REPO>

# 2) Dependencias backend
composer install

# 3) Copiar .env y generar APP_KEY
cp .env.example .env
php artisan key:generate

# 4) Configurar BD en .env y migrar
php artisan migrate

# (opcional) Seed de roles/usuario admin
# php artisan db:seed --class=RolesAndAdminSeeder

# 5) Enlazar storage
php artisan storage:link
```

---

## ⚙️ Configuración (.env)

```dotenv
APP_NAME="SLEP Postulaciones"
APP_URL=http://localhost

# BD
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=slep_postulaciones
DB_USERNAME=root
DB_PASSWORD=secret

# Correo (ajusta a tu SMTP/Mailhog)
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_FROM_ADDRESS="no-reply@slep.local"
MAIL_FROM_NAME="${APP_NAME}"

# SMS (Twilio)
TWILIO_SID=
TWILIO_TOKEN=
TWILIO_FROM=+56XXXXXXXXX

# WhatsApp Cloud API (Meta)
WHATSAPP_TOKEN=
WHATSAPP_PHONE_ID=
WHATSAPP_TMPL_NEW_MESSAGE=postulaciones_alerta
WHATSAPP_TMPL_LANG=es
```

---

## 🎨 Frontend / Assets

```bash
npm install
npm run dev     # desarrollo (Vite)
# npm run build # producción
```

---

## 📦 Módulos

### Perfil del Postulante

* **Controller:** `App\Http\Controllers\PostulantProfileController`
* **Vista:** `resources/views/postulant/profile/edit.blade.php`
* **Checklist:** `App\Support\ProfileChecklist::compute($user)`
* **PDF:**

  * Vista: `resources/views/pdf/profile.blade.php`
  * Rutas: `GET /postulante/perfil.pdf` (postulante) y export desde admin/funcionario.
* **Almacenamiento:** fotos en `storage/app/public/profile_photos` y thumbs en `profile_photos/thumbs`.

### Documentos

* **Modelos:** `UserDocument`, `DocumentType`
* **Validación:** solo **PDF** (`mimetypes:application/pdf`, `mimes:pdf`, `max:10240`)
* **Renombrado:** `RUT_NombreCompleto_TipoDocumento.pdf`
* **Revisión:** `pending/approved/rejected`, guarda revisor y timestamp.
* **Vistas:** resumen por postulante (admin/func.), listado con estados y revisor/fecha.
* **Descarga:** ZIP de documentos aprobados.
* **Notificaciones:** email al aprobar/rechazar (incluye motivo si rechazo).

### Menciones / Subsectores

* **Acceso:** admin y funcionario_slep.
* **CRUDs** y **import Excel** (archivos en `storage/app/imports`).
* **Tablas:** `subsectors`, `menciones`.

### Mensajería Interna

* **Tablas:**

  * `conversations` (creador, last_message_at)
  * `conversation_participants`
  * `messages` (body, user_id)
* **Permisos:**

  * Admin/Funcionario/Coordinadores: inician y responden.
  * Postulante: solo responde a conversaciones iniciadas por staff.
* **UI:**

  * Buscador por nombre y apellidos (typeahead).
  * Avatar con inicial + color por rol.
  * Punto de presencia (verde/rojo).
  * Chat con **polling** y envío por AJAX.
* **Middleware:** `App\Http\Middleware\TouchLastSeen` (actualiza `last_seen` del usuario).

---

## 🔔 Notificaciones

* **Documentos:** email al aprobar/rechazar (comentario si rechazado).
* **Mensajes:** email + (opcional) **SMS** y **WhatsApp** al recibir un nuevo mensaje.
* **Jobs:** centralizado en un Job de notificaciones; requiere **queues** activas.

---

## 🧵 Colas (Queues)

Ejecutar worker en desarrollo:

```bash
php artisan queue:work
```

Ejemplo Supervisor (producción):

```
[program:slep-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/slep-postulaciones/artisan queue:work --sleep=3 --tries=3 --timeout=120
autostart=true
autorestart=true
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/supervisor/slep-queue.log
```

---

## 🗺️ Rutas clave

```
# Perfil
GET  /postulant/profile/edit
GET  /postulante/perfil.pdf

# Documentos (admin/funcionario)
GET  /admin/documentos
GET  /admin/documentos/{user}
GET  /admin/documentos/preview/{doc}
GET  /admin/documentos/{user}/zip-aprobados

# Mensajes
GET  /mensajes
GET  /mensajes/search-users?q=...
POST /mensajes/start
GET  /mensajes/{conversation}
GET  /mensajes/{conversation}/poll?after_id=...
POST /mensajes/{conversation}/send
```

---

## 🧰 Troubleshooting

* **Imágenes no aparecen en PDF**
  Usar rutas **absolutas** con `public_path()` y preferir **PNG**.
* **“Vite manifest not found”**
  Ejecuta `npm run dev` (o `npm run build`) y verifica `@vite` en layout.
* **Cola no envía notificaciones**
  Asegura `php artisan queue:work` corriendo y revisa `storage/logs/laravel.log`.
* **Campos nombre de usuario**
  El código contempla `name` o `first_name/last_name`. Ajusta accessors/queries si tu esquema difiere.
* **Import Excel**
  Habilita `ext-zip` en PHP.

---

## 🔐 Licencia

Propiedad de la organización. Si no se indica lo contrario: **All Rights Reserved**.

---

## 🙌 Créditos

Laravel, Spatie Permission, Intervention Image, Barryvdh Dompdf, Twilio, Meta Cloud API (WhatsApp).

---
