CREATE TABLE IF NOT EXISTS viaticos_disponibilidad_movimientos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    viatico_disponibilidad_presupuestaria_id BIGINT UNSIGNED NOT NULL,
    cometido_funcionario_id BIGINT UNSIGNED NULL,
    tipo_movimiento VARCHAR(80) NOT NULL,
    monto BIGINT UNSIGNED NOT NULL DEFAULT 0,
    saldo_anterior BIGINT UNSIGNED NOT NULL DEFAULT 0,
    saldo_nuevo BIGINT UNSIGNED NOT NULL DEFAULT 0,
    referencia VARCHAR(255) NULL,
    observacion TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX viaticos_disp_mov_tipo_cometido_idx (tipo_movimiento, cometido_funcionario_id),
    CONSTRAINT viaticos_disp_mov_disp_fk FOREIGN KEY (viatico_disponibilidad_presupuestaria_id) REFERENCES viaticos_disponibilidad_presupuestaria(id) ON DELETE CASCADE,
    CONSTRAINT viaticos_disp_mov_cometido_fk FOREIGN KEY (cometido_funcionario_id) REFERENCES cometidos_funcionarios(id) ON DELETE SET NULL,
    CONSTRAINT viaticos_disp_mov_user_fk FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
