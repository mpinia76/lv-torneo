-- ============================================================================
--  lv-torneo — equivalente en SQL de la migración
--  2026_09_05_100000_add_adicionado_a_incidencias.php
--
--  Sirve para aplicar los cambios a mano (phpMyAdmin, consola de mysql) cuando
--  no se puede correr `php artisan migrate` en el hosting.
--
--  Es seguro correrlo dos veces: cada columna se agrega sólo si no existe
--  todavía (MySQL 5.7 no soporta ADD COLUMN IF NOT EXISTS, por eso el rodeo
--  con information_schema). Y como la migración de Laravel también chequea con
--  `Schema::hasColumn`, correr después `artisan migrate` tampoco rompe nada.
--
--  QUÉ HACE: parte el minuto de una incidencia en dos números.
--
--    `minuto`     — el del reloj: 45, 90, 105, 120, o cualquiera anterior.
--    `adicionado` — el descuento, el «+6» del 90+6. NULL = sin descuento.
--
--  Nunca se suman. Sumarlos pierde el dato: 45+2 se confundiría con el minuto
--  47 del segundo tiempo, y 90+6 con el 96 del primer suplementario. El
--  período no se guarda: sale del minuto del reloj.
--
--  NO cambia ni un valor de lo que ya está cargado: todas las filas quedan con
--  `adicionado` en NULL, que dice exactamente lo que se sabía de ellas hasta
--  hoy («minuto 90»). El descuento se lo pone después el repaso de minutos,
--  en Administración → Detalle de los partidos → Repaso de lo ya cargado.
--
--  IMPORTANTE: hacé backup de la base antes de correrlo.
-- ============================================================================

SET NAMES utf8mb4;


-- ----------------------------------------------------------------------------
-- 1) El descuento en las cuatro tablas de incidencias
--
-- TINYINT UNSIGNED alcanza y sobra: el descuento más largo que registra
-- Transfermarkt no llega a 30.
-- ----------------------------------------------------------------------------

-- gols
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME   = 'gols'
                  AND COLUMN_NAME  = 'adicionado');

SET @sql := IF(@existe = 0,
    'ALTER TABLE `gols` ADD COLUMN `adicionado` TINYINT UNSIGNED NULL DEFAULT NULL AFTER `minuto`',
    'SELECT "gols.adicionado ya existe" AS aviso');

PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;


-- tarjetas
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME   = 'tarjetas'
                  AND COLUMN_NAME  = 'adicionado');

SET @sql := IF(@existe = 0,
    'ALTER TABLE `tarjetas` ADD COLUMN `adicionado` TINYINT UNSIGNED NULL DEFAULT NULL AFTER `minuto`',
    'SELECT "tarjetas.adicionado ya existe" AS aviso');

PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;


-- cambios
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME   = 'cambios'
                  AND COLUMN_NAME  = 'adicionado');

SET @sql := IF(@existe = 0,
    'ALTER TABLE `cambios` ADD COLUMN `adicionado` TINYINT UNSIGNED NULL DEFAULT NULL AFTER `minuto`',
    'SELECT "cambios.adicionado ya existe" AS aviso');

PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;


-- penals
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME   = 'penals'
                  AND COLUMN_NAME  = 'adicionado');

SET @sql := IF(@existe = 0,
    'ALTER TABLE `penals` ADD COLUMN `adicionado` TINYINT UNSIGNED NULL DEFAULT NULL AFTER `minuto`',
    'SELECT "penals.adicionado ya existe" AS aviso');

PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;


-- ----------------------------------------------------------------------------
-- 2) La marca del repaso de minutos
--
-- Va APARTE de `tipos_gol_revisado_at` a propósito: los partidos que ya
-- figuran revisados de tipo lo fueron cuando el importador todavía tiraba el
-- descuento, así que esa marca no dice nada sobre los minutos. Compartirla los
-- daría por repasados sin haberles mirado nunca el descuento.
--
-- Arranca en NULL para todos, que es lo que hace que vuelvan solos a la cola
-- del repaso.
-- ----------------------------------------------------------------------------
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME   = 'import_partidos'
                  AND COLUMN_NAME  = 'minutos_revisado_at');

SET @sql := IF(@existe = 0,
    'ALTER TABLE `import_partidos` ADD COLUMN `minutos_revisado_at` DATETIME NULL',
    'SELECT "import_partidos.minutos_revisado_at ya existe" AS aviso');

PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;


-- ----------------------------------------------------------------------------
-- Para verificar que quedó bien:
--
--   SHOW COLUMNS FROM `gols` LIKE 'adicionado';
--   -- tinyint unsigned, NULL, sin default
--
--   SELECT COUNT(*) FROM gols WHERE adicionado IS NOT NULL;
--   -- 0: el descuento lo escribe el repaso, no la migración.
--
--
-- Y para ver el tamaño del trabajo que le queda al repaso — los minutos que
-- hoy están mal por el descuento sumado del scraper viejo:
--
--   SELECT 'gols' AS tabla, COUNT(*) FROM gols     WHERE minuto > 90
--   UNION ALL SELECT 'tarjetas', COUNT(*) FROM tarjetas WHERE minuto > 90
--   UNION ALL SELECT 'cambios',  COUNT(*) FROM cambios  WHERE minuto > 90
--   UNION ALL SELECT 'penals',   COUNT(*) FROM penals   WHERE minuto > 90;
--
-- OJO: en un partido con prórroga un minuto mayor a 90 es legítimo (91 a 120).
-- Esa consulta es para tener una idea, no para corregir nada a mano: quién es
-- quién lo decide el repaso preguntándole a Transfermarkt.
-- ----------------------------------------------------------------------------
