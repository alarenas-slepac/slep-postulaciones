# Excel por financiamiento

Desde **Dotación establecimiento → Informe por función**, el botón **Excel por financiamiento** encola el libro para el año, la comuna y la búsqueda seleccionados. La pantalla de estado permite volver al informe y retomar desde **Ver última exportación**. La descarga queda disponible para la cuenta que la solicitó durante 24 horas desde que el archivo se genera.

## Puesta en marcha en cPanel

1. Desplegar el código y aplicar las migraciones con PHP 8.3.
2. Comprobar que cPanel ejecute `artisan schedule:run` cada minuto. Si el cron ya existe, usar esa entrada. Si falta, añadirla con el siguiente comando.

   ```cron
   * * * * * cd /home/slepac/apps/slep_postulaciones && /opt/cpanel/ea-php83/root/usr/bin/php artisan schedule:run >> storage/logs/scheduler.log 2>&1
   ```

3. Desde el directorio de la aplicación, verificar con `/opt/cpanel/ea-php83/root/usr/bin/php artisan schedule:list` que aparezca el worker de `dotacion-financiamiento` cada minuto. El programador también retira los archivos vencidos.
4. Solicitar el Excel territorial y comprobar que el estado cambie de **En cola** a **Descargar Excel**. Si permanece en cola, revisar la ejecución del cron y su log. Ante un estado de error, revisar el log de Laravel.
