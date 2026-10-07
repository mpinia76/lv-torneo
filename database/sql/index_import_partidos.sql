-- ============================================================================
--  lv-torneo — equivalente en SQL de la migración
--  2026_09_01_110000_add_index_partido_id_a_import_partidos.php
--
--  Sólo velocidad: no toca ningún dato. `import_partidos` se creó sin índice
--  por `partido_id` y ya hay varias pantallas que la cruzan por ese campo
--  (controles, penales fallados, tipos de gol y la búsqueda de partidos sin
--  gameId, que la cruza contra `partidos` entera).
--
--  Es seguro correrlo dos veces: si el índice ya está, no hace nada.
-- ============================================================================

SET @existe := (SELECT COUNT(*) FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME   = 'import_partidos'
                  AND INDEX_NAME   = 'import_partidos_partido_id_index');

SET @sql := IF(@existe = 0,
    'ALTER TABLE `import_partidos` ADD INDEX `import_partidos_partido_id_index` (`partido_id`)',
    'SELECT "el índice ya existe" AS aviso');

PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
