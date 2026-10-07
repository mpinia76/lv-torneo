-- ============================================================================
--  lv-torneo — equivalente en SQL de la migración
--  2026_09_01_100000_add_gol_olimpico.php
--
--  Sirve para aplicar los cambios a mano (phpMyAdmin, consola de mysql) cuando
--  no se puede correr `php artisan migrate` en el hosting.
--
--  Es seguro correrlo dos veces: las columnas nuevas se agregan sólo si no
--  existen todavía (MySQL 5.7 no soporta ADD COLUMN IF NOT EXISTS, por eso el
--  rodeo con information_schema) y el MODIFY del enum deja siempre la misma
--  lista de valores.
--
--  IMPORTANTE: 'Olímpico' lleva tilde. Si lo corrés desde una consola con el
--  cliente en latin1, el valor entra mal escrito y después PHP no va a poder
--  guardar ningún gol olímpico. Por eso la primera línea es SET NAMES: no la
--  saques. En phpMyAdmin no hace falta pero tampoco molesta.
--
--  IMPORTANTE: hacé backup de la base antes de correrlo.
-- ============================================================================

SET NAMES utf8mb4;


-- ----------------------------------------------------------------------------
-- 1) El tipo de gol nuevo
--
-- MODIFY reemplaza el enum entero: hay que listar TODOS los valores, no sólo
-- el que se agrega. Los goles ya cargados no cambian.
-- ----------------------------------------------------------------------------
ALTER TABLE `gols`
    MODIFY `tipo` ENUM('Cabeza','En Contra','Jugada','Penal','Tiro Libre','Olímpico') NOT NULL;


-- ----------------------------------------------------------------------------
-- 2) El olímpico en las estadísticas manuales
--
-- Sin esta columna los cuadros de goleadores mezclarían dos criterios: los
-- goles de partidos cargados separarían el olímpico y los manuales lo seguirían
-- contando adentro de otro tipo.
-- ----------------------------------------------------------------------------
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME   = 'jugador_estadistica_manuals'
                  AND COLUMN_NAME  = 'goles_olimpico');

SET @sql := IF(@existe = 0,
    'ALTER TABLE `jugador_estadistica_manuals` ADD COLUMN `goles_olimpico` INT NOT NULL DEFAULT 0 AFTER `goles_jugada`',
    'SELECT "jugador_estadistica_manuals.goles_olimpico ya existe" AS aviso');

PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;


-- ----------------------------------------------------------------------------
-- 3) La marca del relevamiento de tipos de gol
--
-- Qué partido tiene un olímpico no se puede saber desde la base: el tipo de gol
-- sólo existe en Transfermarkt y hay que preguntarlo partido por partido, a 1
-- llamada cada uno. Acá queda anotado cuáles ya se preguntaron, así la llamada
-- se paga una sola vez (mismo criterio que `penales_revisado_at`).
-- ----------------------------------------------------------------------------
SET @existe2 := (SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = 'import_partidos'
                   AND COLUMN_NAME  = 'tipos_gol_revisado_at');

SET @sql2 := IF(@existe2 = 0,
    'ALTER TABLE `import_partidos` ADD COLUMN `tipos_gol_revisado_at` DATETIME NULL',
    'SELECT "import_partidos.tipos_gol_revisado_at ya existe" AS aviso');

PREPARE st2 FROM @sql2; EXECUTE st2; DEALLOCATE PREPARE st2;


-- ----------------------------------------------------------------------------
-- Para verificar que quedó bien:
--
--   SHOW COLUMNS FROM `gols` LIKE 'tipo';
--   -- tiene que decir: enum('Cabeza','En Contra','Jugada','Penal','Tiro Libre','Olímpico')
--
--   SELECT tipo, COUNT(*) FROM gols GROUP BY tipo;
--   -- 'Olímpico' arranca en cero: los que ya estaban cargados siguen en
--   -- 'Jugada' hasta que corras el relevamiento.
-- ----------------------------------------------------------------------------
