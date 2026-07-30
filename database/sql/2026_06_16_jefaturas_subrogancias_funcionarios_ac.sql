ALTER TABLE `funcionarios_ac_autorizados`
    ADD COLUMN IF NOT EXISTS `jefatura` TINYINT(1) NOT NULL DEFAULT 0 AFTER `subdireccion_dependencia`;

CREATE TABLE IF NOT EXISTS `funcionarios_ac_jefaturas_dependencias` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `subdireccion_dependencia` VARCHAR(255) NOT NULL,
    `jefatura_funcionario_ac_id` BIGINT UNSIGNED NULL,
    `subrogante_1_funcionario_ac_id` BIGINT UNSIGNED NULL,
    `subrogante_2_funcionario_ac_id` BIGINT UNSIGNED NULL,
    `subrogante_3_funcionario_ac_id` BIGINT UNSIGNED NULL,
    `subrogancia_activa` TINYINT(1) NOT NULL DEFAULT 0,
    `subrogante_activo_nivel` TINYINT UNSIGNED NULL,
    `subrogancia_desde` DATE NULL,
    `subrogancia_hasta` DATE NULL,
    `subrogancia_activada_por` BIGINT UNSIGNED NULL,
    `motivo_subrogancia` TEXT NULL,
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `observaciones` TEXT NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `fac_jef_dep_unique` (`subdireccion_dependencia`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `funcionarios_ac_jefaturas_dependencias` (`subdireccion_dependencia`, `activo`, `created_at`, `updated_at`) VALUES
('Subdirección de Gestión y Desarrollo de las Personas', 1, NOW(), NOW()),
('Subdirección de Administración y Finanzas', 1, NOW(), NOW()),
('Subdirección de Planificación y Control de Gestión', 1, NOW(), NOW()),
('Subdirección de Apoyo Técnico Pedagógico', 1, NOW(), NOW()),
('Subdirección de Infraestructura y Mantenimiento', 1, NOW(), NOW()),
('Gabinete', 1, NOW(), NOW()),
('Unidad Jurídica', 1, NOW(), NOW()),
('Dirección Ejecutiva', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE `activo` = VALUES(`activo`), `updated_at` = NOW();
