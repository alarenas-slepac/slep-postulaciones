ALTER TABLE cometidos_funcionarios_informes
    ADD COLUMN fecha_revision_jefatura TIMESTAMP NULL AFTER fecha_envio,
    ADD COLUMN jefatura_revisora_id BIGINT UNSIGNED NULL AFTER fecha_revision_jefatura,
    ADD COLUMN decision_jefatura VARCHAR(40) NULL AFTER jefatura_revisora_id,
    ADD COLUMN observacion_jefatura TEXT NULL AFTER decision_jefatura;

ALTER TABLE cometidos_funcionarios_informes
    ADD CONSTRAINT com_inf_jef_fk
    FOREIGN KEY (jefatura_revisora_id) REFERENCES users(id)
    ON DELETE SET NULL;
