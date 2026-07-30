ALTER TABLE cometidos_funcionarios
    ADD COLUMN IF NOT EXISTS solicita_anticipo_viatico TINYINT(1) NOT NULL DEFAULT 0 AFTER servicio_contempla_colacion,
    ADD COLUMN IF NOT EXISTS porcentaje_anticipo_viatico TINYINT UNSIGNED NULL AFTER solicita_anticipo_viatico,
    ADD COLUMN IF NOT EXISTS monto_anticipo_viatico BIGINT UNSIGNED NULL AFTER porcentaje_anticipo_viatico,
    ADD COLUMN IF NOT EXISTS monto_saldo_viatico BIGINT UNSIGNED NULL AFTER monto_anticipo_viatico;
