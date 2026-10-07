-- ============================================================================
--  lv-torneo — equivalente en SQL de la migración
--  2026_09_18_100000_add_desaparicion_a_equipos.php
--
--  Agrega `equipos.desaparicion` (DATE, NULL): cuándo dejó de existir el club.
--  Transfermarkt marca los clubes desaparecidos con el año de cierre en el
--  nombre, "(- 2019)" o "(1981-2019)"; ese año pasa a esta columna y el
--  nombre queda limpio.
--
--  No toca ningún dato: la columna arranca en NULL. Lo ya cargado se repasa
--  en /admin/import-detalles/clubes-desaparecidos.
--
--  Es seguro correrlo dos veces: si la columna ya está, no hace nada.
-- ============================================================================

SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME   = 'equipos'
                  AND COLUMN_NAME  = 'desaparicion');

SET @sql := IF(@existe = 0,
    'ALTER TABLE `equipos` ADD COLUMN `desaparicion` DATE NULL DEFAULT NULL AFTER `fundacion`',
    'SELECT "la columna ya existe" AS aviso');

PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
