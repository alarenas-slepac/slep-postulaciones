ALTER TABLE cometidos_funcionarios
    ADD COLUMN IF NOT EXISTS folio_compromiso_viatico VARCHAR(100) NULL AFTER fecha_pago_viatico,
    ADD COLUMN IF NOT EXISTS fecha_compromiso_viatico DATE NULL AFTER folio_compromiso_viatico,
    ADD COLUMN IF NOT EXISTS folio_devengo_viatico VARCHAR(100) NULL AFTER fecha_compromiso_viatico,
    ADD COLUMN IF NOT EXISTS fecha_devengo_viatico DATE NULL AFTER folio_devengo_viatico,
    ADD COLUMN IF NOT EXISTS documento_contable_viatico_path VARCHAR(255) NULL AFTER fecha_devengo_viatico,
    ADD COLUMN IF NOT EXISTS observacion_contable_viatico TEXT NULL AFTER documento_contable_viatico_path,
    ADD COLUMN IF NOT EXISTS daf_contable_viatico_user_id BIGINT UNSIGNED NULL AFTER observacion_contable_viatico,
    ADD COLUMN IF NOT EXISTS daf_contable_viatico_at TIMESTAMP NULL AFTER daf_contable_viatico_user_id;

ALTER TABLE cometidos_funcionarios_resoluciones_reembolso
    ADD COLUMN IF NOT EXISTS folio_compromiso_contable VARCHAR(100) NULL AFTER fecha_emision_resolucion,
    ADD COLUMN IF NOT EXISTS fecha_compromiso_contable DATE NULL AFTER folio_compromiso_contable,
    ADD COLUMN IF NOT EXISTS folio_devengo_contable VARCHAR(100) NULL AFTER fecha_compromiso_contable,
    ADD COLUMN IF NOT EXISTS fecha_devengo_contable DATE NULL AFTER folio_devengo_contable,
    ADD COLUMN IF NOT EXISTS documento_contable_path VARCHAR(255) NULL AFTER fecha_devengo_contable,
    ADD COLUMN IF NOT EXISTS observacion_contable TEXT NULL AFTER documento_contable_path,
    ADD COLUMN IF NOT EXISTS usuario_contable_id BIGINT UNSIGNED NULL AFTER observacion_contable,
    ADD COLUMN IF NOT EXISTS fecha_registro_contable TIMESTAMP NULL AFTER usuario_contable_id;
