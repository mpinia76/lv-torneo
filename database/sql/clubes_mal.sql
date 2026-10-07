-- ============================================================================
--  lv-torneo — equivalente en SQL de la migración
--  2026_09_11_100000_add_clubes_mal_a_import_partidos.php
--
--  Agrega `import_partidos.clubes_mal_at`: la marca de «para este external_id,
--  Transfermarkt dice otros clubes que los que juegan el partido».
--
--  Sin la marca, esos partidos vuelven a salir primeros en cada tanda de los
--  pases livianos (tipos de gol y penales) y pagan una llamada cada vez, sin
--  poder escribir nada nunca. No toca ningún dato: la columna arranca en NULL.
--
--  Es seguro correrlo dos veces: si la columna ya está, no hace nada.
-- ============================================================================

SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME   = 'import_partidos'
                  AND COLUMN_NAME  = 'clubes_mal_at');

SET @sql := IF(@existe = 0,
    'ALTER TABLE `import_partidos` ADD COLUMN `clubes_mal_at` TIMESTAMP NULL DEFAULT NULL AFTER `partido_id`',
    'SELECT "la columna ya existe" AS aviso');

PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
