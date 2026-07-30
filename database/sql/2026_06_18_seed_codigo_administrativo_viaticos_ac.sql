INSERT INTO viaticos_reembolsos_valores
(estamento, cargo_funcion, vigente_desde, vigente_hasta, valor_100, valor_40, activo, created_at, updated_at)
VALUES
('Código Administrativo', '1° al 4°', '2026-06-01', '2026-12-31', 89416, 35766, 1, NOW(), NOW()),
('Código Administrativo', '5° al 10°', '2026-06-01', '2026-12-31', 82249, 32900, 1, NOW(), NOW()),
('Código Administrativo', '11° al 21°', '2026-06-01', '2026-12-31', 66751, 26700, 1, NOW(), NOW()),
('Código Administrativo', '22° al 31°', '2026-06-01', '2026-12-31', 49648, 19859, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
valor_100 = VALUES(valor_100),
valor_40 = VALUES(valor_40),
activo = VALUES(activo),
updated_at = NOW();
