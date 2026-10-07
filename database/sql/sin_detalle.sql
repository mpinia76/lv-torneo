-- ============================================================================
--  lv-torneo — equivalente en SQL de la migración
--  2026_09_06_100000_add_sin_detalle_a_import_partidos.php
--
--  Agrega `import_partidos.sin_detalle_at`: la marca de «a este partido nunca
--  se le bajó el detalle de Transfermarkt», que detecta gratis el repaso de
--  tipos de gol (ningún gol apareó y de los dos lados había goles).
--
--  Sin la marca ese aviso vivía sólo en el informe de la tanda, y en el modo
--  continuado se lo llevaba la tanda siguiente. No toca ningún dato: la
--  columna arranca en NULL.
--
--  Es seguro correrlo dos veces: si la columna ya está, no hace nada.
-- ============================================================================

SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME   = 'import_partidos'
                  AND COLUMN_NAME  = 'sin_detalle_at');

SET @sql := IF(@existe = 0,
    'ALTER TABLE `import_partidos` ADD COLUMN `sin_detalle_at` TIMESTAMP NULL DEFAULT NULL AFTER `partido_id`',
    'SELECT "la columna ya existe" AS aviso');

PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
