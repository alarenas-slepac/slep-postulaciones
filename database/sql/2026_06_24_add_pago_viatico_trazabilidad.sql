ALTER TABLE cometidos_funcionarios
    ADD COLUMN IF NOT EXISTS monto_pagado_viatico INT UNSIGNED NULL AFTER fecha_pago_viatico,
    ADD COLUMN IF NOT EXISTS documento_pago_viatico_path VARCHAR(255) NULL AFTER monto_pagado_viatico,
    ADD COLUMN IF NOT EXISTS observacion_pago_viatico TEXT NULL AFTER documento_pago_viatico_path,
    ADD COLUMN IF NOT EXISTS usuario_pago_viatico_id BIGINT UNSIGNED NULL AFTER observacion_pago_viatico,
    ADD COLUMN IF NOT EXISTS fecha_registro_pago_viatico TIMESTAMP NULL AFTER usuario_pago_viatico_id;
