-- Agrega columna grado para Funcionarios Administración Central.
-- Ejecutar en phpMyAdmin sólo si se requiere aplicar manualmente y confirmar primero el nombre real de la tabla.

ALTER TABLE `funcionario_ac_autorizado`
    ADD COLUMN `grado` VARCHAR(20) NULL AFTER `cargo_funcion`;

-- Si en producción la tabla usa otro nombre, aplicar la variante correspondiente:
-- ALTER TABLE `funcionarios_ac_autorizadas` ADD COLUMN `grado` VARCHAR(20) NULL AFTER `cargo_funcion`;
-- ALTER TABLE `funcionarios_ac_autorizados` ADD COLUMN `grado` VARCHAR(20) NULL AFTER `cargo_funcion`;
