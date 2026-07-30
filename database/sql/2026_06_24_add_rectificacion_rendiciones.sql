ALTER TABLE cometidos_funcionarios_rendiciones
    ADD COLUMN IF NOT EXISTS rectificacion_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER estado,
    ADD COLUMN IF NOT EXISTS fecha_ultima_rectificacion TIMESTAMP NULL AFTER fecha_envio_rendicion,
    ADD COLUMN IF NOT EXISTS observacion_rectificacion TEXT NULL AFTER observacion_establecimiento,
    ADD COLUMN IF NOT EXISTS rectificado_por BIGINT UNSIGNED NULL AFTER created_by;
