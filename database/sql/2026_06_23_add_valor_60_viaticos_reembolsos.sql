SET @col_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'viaticos_reembolsos_valores'
      AND COLUMN_NAME = 'valor_60'
);

SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE viaticos_reembolsos_valores ADD COLUMN valor_60 INT UNSIGNED NULL AFTER valor_100',
    'SELECT "valor_60 already exists"'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE viaticos_reembolsos_valores
SET valor_60 = ROUND(valor_100 * 0.60)
WHERE valor_60 IS NULL
  AND valor_100 IS NOT NULL;
