INSERT INTO `modules` (`key`, `name`, `section`, `icon`, `sort`, `created_at`, `updated_at`)
VALUES ('admin.establecimiento-planes', 'Configuración de Planes por Establecimiento', 'Catálogos', NULL, 30, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `section` = VALUES(`section`),
    `sort` = VALUES(`sort`),
    `updated_at` = NOW();

INSERT IGNORE INTO `module_role` (`module_id`, `role_id`)
SELECT m.`id`, r.`id`
FROM `modules` m
JOIN `roles` r ON r.`name` IN ('admin', 'funcionario_directivo_estab')
WHERE m.`key` = 'admin.establecimiento-planes';
