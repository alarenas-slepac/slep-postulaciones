# Validación alternativa de cuentas por WhatsApp

Parche 2026.11.12.689. La pantalla de verificación ofrece correo y WhatsApp.
El registro inicial conserva únicamente su correo y datos actuales; el celular
se solicita al elegir **Validar por WhatsApp**. Reutiliza el servicio, sesión y
worker existentes. No modifica `.env` ni requiere renovar QR.

## Funcionamiento

- Acepta móviles chilenos `9XXXXXXXX`, `569XXXXXXXX` y `+569XXXXXXXX`.
  El formulario limpia los caracteres y normaliza a `+569XXXXXXXX`; el servidor
  valida nuevamente. No acepta números fijos, listas ni otros países.
- Cada solicitud crea un código aleatorio de seis dígitos, vinculado a la cuenta,
  teléfono, correo actual y desafío. Vence exactamente a los cinco minutos de
  aceptar la solicitud. Es de un solo uso y admite cinco intentos.
- Se puede solicitar otro a los 60 segundos; invalida el anterior. El contador
  de pantalla no sustituye el límite persistido en la base de datos. Se aplican
  además límites por cuenta, teléfono e IP (10 solicitudes por hora por cuenta y teléfono,
  300 por IP y 10 comprobaciones por minuto por cuenta).
- Sólo la cuenta autenticada puede consultar y confirmar su propio desafío.
  El código no se devuelve en respuestas, no se copia a la sesión y no se escribe
  en logs. Su comprobación utiliza HMAC con la clave de la aplicación.
- El texto que contiene el código se guarda cifrado en `whatsapp_dispatches`;
  el job sólo lleva su UUID y espera el commit. El worker revisa desafío actual,
  destinatario, expiración, cuenta y habilitación antes de cada intento. Omite
  códigos vencidos o sustituidos. Mantiene el UUID durante los reintentos y no
  fuerza reenvíos de resultados inciertos. Un retraso de cola no extiende la vigencia.
- Se muestra un mensaje cuando el servicio no reconoce el número o falla la
  entrega, con actualización de estado mientras el envío sigue pendiente.

## Cuenta, teléfono y perfil

`account_whatsapp_verifications` conserva el teléfono confirmado y su fecha,
separados de `users.email_verified_at`. No se afirma que el correo pertenece al
usuario por haber confirmado WhatsApp. El middleware `verified` admite cualquiera
de las dos confirmaciones, manteniendo los roles y permisos. Los filtros explícitos
de correo confirmado y los enlaces firmados por correo conservan su significado.
Los destinatarios de avisos WhatsApp admiten también cuentas confirmadas por este canal,
manteniendo sus reglas de ficha, preferencia, rol, asignación y teléfono.

Una restricción única impide confirmar un teléfono en dos cuentas. Los pendientes
no reservan de forma permanente un número ajeno; la primera confirmación válida
establece su pertenencia. No se reasigna automáticamente al eliminar lógicamente
una cuenta; una recuperación de número requiere revisión administrativa. No se
reclaman ni alteran teléfonos históricos sin un código confirmado.

Si existe perfil, se actualiza únicamente su `telefono1`. Si todavía no existe,
el número queda asociado a la cuenta y se presenta como teléfono principal al
completar Mi perfil. No se crea un perfil incompleto ni se inventan fechas u otros
datos obligatorios. Los teléfonos de contacto editables no sustituyen por sí mismos
la identidad telefónica ya confirmada para el acceso.

## Catálogo y despliegue

El evento **Verificación de cuenta por WhatsApp** (`auth.whatsapp_verification`)
aparece automáticamente en **Administración → Eventos de notificaciones → Acceso**,
sin sembrar preferencias ni realizar envíos. Comienza desactivado. Permite habilitar
el canal y personalizar la presentación conservando el código y enlace originales.
No admite copias ni destinatarios arbitrarios. Su vista previa usa un código ficticio.

Después de desplegar PHP, rutas, vistas, configuración y recursos compilados:

```bash
cd /home/slepac/apps/slep_postulaciones
/opt/cpanel/ea-php83/root/usr/bin/php artisan migrate --force
/opt/cpanel/ea-php83/root/usr/bin/php artisan optimize:clear
/opt/cpanel/ea-php83/root/usr/bin/php artisan config:cache
/opt/cpanel/ea-php83/root/usr/bin/php artisan queue:restart
```

La migración `2026_11_12_160000_create_account_whatsapp_verifications` es aditiva.
Comprueba separadamente tabla, índices `awv_user_uq` / `awv_phone_uq` y clave
`awv_user_fk` para reintentar tras un fallo DDL parcial. No elimina datos en `down`.

Habilitar **sólo este evento** en el catálogo y comprobar que el worker habitual
consume `whatsapp`. La pausa de `sga-mail` no detiene esta cola. No es necesario
reanudar el correo para usar esta alternativa. Mantener su configuración durante
la reparación del DNS.

Realizar una prueba manual autorizada con un celular propio: registro, envío,
contador de un minuto, recepción, confirmación y acceso; comprobar que el correo
sigue pendiente y que Mi perfil recibe el teléfono. Las pruebas automáticas usan
SQLite en memoria, HTTP y cola simulados; no envían mensajes reales. El SQL de
la migración se compila con la gramática MySQL sin conectar a producción.
