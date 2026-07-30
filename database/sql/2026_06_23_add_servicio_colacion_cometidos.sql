ALTER TABLE cometidos_funcionarios
    ADD COLUMN servicio_contempla_colacion VARCHAR(20) NOT NULL DEFAULT 'no_informado' AFTER contempla_alojamiento;
