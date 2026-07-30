-- Correctivo 2026.5.18.154
-- Permite registrar cometidos de Administración Central sin vínculo a reemplazos_personal.
ALTER TABLE cometidos_funcionarios
    MODIFY reemplazo_personal_id BIGINT UNSIGNED NULL;
