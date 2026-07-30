CREATE TABLE IF NOT EXISTS `cometidos_notificaciones_configuracion` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `clave` varchar(120) NOT NULL,
  `nombre` varchar(180) NOT NULL,
  `descripcion` text NULL,
  `correos` text NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `updated_by` bigint unsigned NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `com_notif_conf_clave_unique` (`clave`),
  KEY `com_notif_conf_user_fk` (`updated_by`),
  CONSTRAINT `com_notif_conf_user_fk` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `cometidos_notificaciones_configuracion` (`clave`, `nombre`, `descripcion`, `correos`, `activo`, `created_at`, `updated_at`)
VALUES ('servicios_generales_vehiculo_institucional', 'Servicios Generales - vehículo institucional', 'Correo(s) que reciben aviso cuando un cometido autorizado contempla uso de vehículo institucional.', 'johanna.isla@slepandaliencosta.gob.cl', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
  `nombre` = VALUES(`nombre`),
  `descripcion` = VALUES(`descripcion`),
  `correos` = VALUES(`correos`),
  `activo` = VALUES(`activo`),
  `updated_at` = NOW();

ALTER TABLE `cometidos_funcionarios`
  ADD COLUMN IF NOT EXISTS `ssgg_notificado_vehiculo_at` timestamp NULL DEFAULT NULL AFTER `requiere_pasaje_aereo`,
  ADD COLUMN IF NOT EXISTS `ssgg_notificado_vehiculo_email` varchar(500) NULL DEFAULT NULL AFTER `ssgg_notificado_vehiculo_at`,
  ADD COLUMN IF NOT EXISTS `ssgg_notificado_vehiculo_por` bigint unsigned NULL DEFAULT NULL AFTER `ssgg_notificado_vehiculo_email`;

ALTER TABLE `cometidos_funcionarios`
  ADD CONSTRAINT `com_sg_veh_user_fk` FOREIGN KEY (`ssgg_notificado_vehiculo_por`) REFERENCES `users` (`id`) ON DELETE SET NULL;
