-- Ejecutar sólo si se requiere aplicar manualmente en phpMyAdmin.
-- Verifique primero cuál es el nombre real de la tabla en producción.

ALTER TABLE `funcionarios_ac_autorizados`
ADD COLUMN `subdireccion_dependencia` VARCHAR(255) NULL AFTER `unidad_departamento`;

-- Si su instalación usa otro nombre de tabla, use uno de estos comandos en vez del anterior:
-- ALTER TABLE `funcionario_ac_autorizado` ADD COLUMN `subdireccion_dependencia` VARCHAR(255) NULL AFTER `unidad_departamento`;
-- ALTER TABLE `funcionarios_ac_autorizadas` ADD COLUMN `subdireccion_dependencia` VARCHAR(255) NULL AFTER `unidad_departamento`;
