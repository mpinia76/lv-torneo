-- ============================================================================
--  lv-torneo — equivalente en SQL de la migración
--  2026_08_21_140000_create_persona_duplicados_table.php
--
--  Sirve para aplicar los cambios a mano (phpMyAdmin, consola de mysql) cuando
--  no se puede correr `php artisan migrate` en el hosting.
--
--  Es seguro correrlo dos veces: las tablas usan CREATE TABLE IF NOT EXISTS y
--  las columnas nuevas se agregan solo si no existen todavía (MySQL 5.7 no
--  soporta ADD COLUMN IF NOT EXISTS, por eso el rodeo con information_schema).
--
--  Charset tomado de config/database.php: utf8mb4 / utf8mb4_unicode_ci.
--  Si tu conexión tiene `prefix` configurado, agregalo a los nombres de tabla.
--
--  IMPORTANTE: hacé backup de la base antes de correrlo.
-- ============================================================================


-- ----------------------------------------------------------------------------
-- 1) Claves normalizadas sobre `personas`
--
-- Acá está la velocidad: son columnas INDEXADAS, comparables con `=` en vez del
-- LIKE '%...%' sobre un CONCAT que usaba la pantalla vieja (que no puede usar
-- índice y obliga a escanear la tabla entera por cada fila).
--
--   clave_norm  = "juan carlos perez"  (nombre + apellido, sin acentos ni símbolos)
--   clave_orden = "carlos juan perez"  (los mismos tokens, ordenados alfabéticamente)
--
-- Al ordenar los tokens, "Juan Perez" y "Perez Juan" dan exactamente la misma
-- clave: el caso de nombre y apellido invertidos sale gratis.
--
-- Las dos quedan en NULL: las llena `php artisan personas:duplicados`.
--
-- Los PREPARE/EXECUTE de acá abajo son solo para poder correr el script dos
-- veces sin que explote. Si tu hosting no te deja usar PREPARE, o sabés que las
-- columnas todavía no existen, reemplazá los cuatro bloques por esta sola línea:
--
--   ALTER TABLE `personas`
--       ADD COLUMN `clave_norm`  VARCHAR(191) NULL,
--       ADD COLUMN `clave_orden` VARCHAR(191) NULL,
--       ADD INDEX `personas_clave_norm_idx`  (`clave_norm`),
--       ADD INDEX `personas_clave_orden_idx` (`clave_orden`);
-- ----------------------------------------------------------------------------

SET @sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `personas` ADD COLUMN `clave_norm` VARCHAR(191) NULL',
        'SELECT ''clave_norm ya existe'' AS aviso'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personas' AND COLUMN_NAME = 'clave_norm'
);
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `personas` ADD COLUMN `clave_orden` VARCHAR(191) NULL',
        'SELECT ''clave_orden ya existe'' AS aviso'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personas' AND COLUMN_NAME = 'clave_orden'
);
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `personas` ADD INDEX `personas_clave_norm_idx` (`clave_norm`)',
        'SELECT ''personas_clave_norm_idx ya existe'' AS aviso'
    )
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personas' AND INDEX_NAME = 'personas_clave_norm_idx'
);
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `personas` ADD INDEX `personas_clave_orden_idx` (`clave_orden`)',
        'SELECT ''personas_clave_orden_idx ya existe'' AS aviso'
    )
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personas' AND INDEX_NAME = 'personas_clave_orden_idx'
);
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;


-- ----------------------------------------------------------------------------
-- 2) Índice invertido de tokens
--
-- Es el "bloqueo" que evita comparar todos contra todos: con 14.000 personas
-- serían 98 millones de pares. Acá solo se comparan las que comparten al menos
-- una palabra de apellido.
--
--   campo = 'a' -> token del apellido (es el que bloquea)
--   campo = 'n' -> token del nombre
--
-- Sin claves foráneas a propósito: `persona_tokens` se reconstruye entera con
-- TRUNCATE en cada reindexado, y TRUNCATE falla si hay FKs apuntando.
-- ----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `persona_tokens` (
    `persona_id` BIGINT UNSIGNED NOT NULL,
    `token`      VARCHAR(64)     NOT NULL,
    `campo`      CHAR(1)         NOT NULL DEFAULT 'a',
    PRIMARY KEY (`persona_id`, `token`, `campo`),
    KEY `persona_tokens_token_idx` (`token`, `campo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 3) Los pares candidatos ya resueltos
--
-- La pantalla lee de acá y nada más.
--
-- INVARIANTE: `persona_id` guarda SIEMPRE el id menor y `simil_id` el mayor.
-- Por eso el par (A,B) y el (B,A) son la misma fila, y el índice único
-- `persona_duplicados_par_uk` hace imposible que la lista muestre info repetida.
--
--   estado = 'pendiente'  -> hay que revisarlo
--            'descartado' -> el usuario dijo que son personas distintas
--
-- Los pares fusionados no quedan acá: la fusión los borra y deja el rastro en
-- `persona_fusiones`.
-- ----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `persona_duplicados` (
    `id`         BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `persona_id` BIGINT UNSIGNED  NOT NULL,
    `simil_id`   BIGINT UNSIGNED  NOT NULL,
    `puntaje`    TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `motivo`     VARCHAR(150)     NULL,
    `estado`     VARCHAR(20)      NOT NULL DEFAULT 'pendiente',
    `created_at` TIMESTAMP        NULL,
    `updated_at` TIMESTAMP        NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `persona_duplicados_par_uk` (`persona_id`, `simil_id`),
    KEY `persona_duplicados_estado_idx` (`estado`, `puntaje`),
    KEY `persona_duplicados_simil_idx` (`simil_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 4) Bitácora de fusiones
--
-- Si una fusión sale mal, al menos queda el rastro de qué se absorbió y con qué
-- datos (`detalle` guarda el JSON de la persona borrada y de todo lo que se movió).
-- ----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `persona_fusiones` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `persona_id`       BIGINT UNSIGNED NOT NULL,  -- la que quedó
    `absorbida_id`     BIGINT UNSIGNED NOT NULL,  -- la que se borró
    `absorbida_nombre` VARCHAR(191)    NULL,
    `detalle`          TEXT            NULL,
    `created_at`       TIMESTAMP       NULL,
    `updated_at`       TIMESTAMP       NULL,
    PRIMARY KEY (`id`),
    KEY `persona_fusiones_persona_idx` (`persona_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 5) Las decisiones ya tomadas en la pantalla vieja no se pierden
--
-- `personas_verificadas` (los pares que ya marcaste como "no son la misma
-- persona") pasa a ser el estado 'descartado'. Se normaliza el orden del par con
-- LEAST/GREATEST y se agrupa, porque ahí un mismo par podía estar en las dos
-- direcciones.
--
-- INSERT IGNORE: si ya lo corriste antes, no duplica nada.
--
-- Si en tu base no existiera la tabla `personas_verificadas`, salteá este bloque
-- entero: no hay nada que rescatar.
-- ----------------------------------------------------------------------------

INSERT IGNORE INTO `persona_duplicados`
    (`persona_id`, `simil_id`, `puntaje`, `motivo`, `estado`, `created_at`, `updated_at`)
SELECT LEAST(v.persona_id, v.simil_id),
       GREATEST(v.persona_id, v.simil_id),
       0,
       'descartado en la pantalla anterior',
       'descartado',
       NOW(), NOW()
FROM `personas_verificadas` v
WHERE v.persona_id IS NOT NULL
  AND v.simil_id IS NOT NULL
  AND v.persona_id <> v.simil_id
GROUP BY LEAST(v.persona_id, v.simil_id), GREATEST(v.persona_id, v.simil_id);


-- ----------------------------------------------------------------------------
-- 6) Índice en nacionalidad
--
-- La pestaña de banderas agrupa por nacionalidad; con índice es instantánea.
-- ----------------------------------------------------------------------------

SET @sql := (
    SELECT IF(
        (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personas' AND COLUMN_NAME = 'nacionalidad') > 0
        AND
        (SELECT COUNT(*) FROM information_schema.STATISTICS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personas' AND INDEX_NAME = 'personas_nacionalidad_idx') = 0,
        'ALTER TABLE `personas` ADD INDEX `personas_nacionalidad_idx` (`nacionalidad`)',
        'SELECT ''no hace falta tocar el indice de nacionalidad'' AS aviso'
    )
);
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;


-- ----------------------------------------------------------------------------
-- 7) Marcar la migración como corrida
--
-- Para que `php artisan migrate` no intente volver a aplicarla y falle porque
-- las tablas ya existen. La subconsulta derivada es el rodeo para poder leer y
-- escribir la misma tabla en el mismo INSERT.
--
-- El DELETE previo es lo que hace que se pueda correr dos veces sin dejar la
-- fila duplicada (un WHERE NOT EXISTS no serviría: con MAX() y sin GROUP BY la
-- consulta devuelve una fila igual aunque el WHERE filtre todo).
-- ----------------------------------------------------------------------------

DELETE FROM `migrations`
WHERE `migration` = '2026_08_21_140000_create_persona_duplicados_table';

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_08_21_140000_create_persona_duplicados_table',
       COALESCE(MAX(t.batch), 0) + 1
FROM (SELECT `batch` FROM `migrations`) t;


-- ============================================================================
--  Después de correr esto, llená el índice (las columnas quedan en NULL):
--
--      php artisan personas:duplicados
--
--  Si no tenés acceso a la consola, entrá a /admin/verificarPersonas y apretá
--  "Recalcular" con el tilde de "reconstruir índice" puesto. Ojo: sobre 14.000
--  personas el hosting compartido puede cortar por max_execution_time; por eso
--  la primera pasada conviene hacerla por consola.
-- ============================================================================


-- ============================================================================
--  DESHACER (el `down()` de la migración)
--  Descomentar solo si querés volver atrás. Borra los pares y las decisiones
--  tomadas en la pantalla nueva; `personas_verificadas` queda intacta.
-- ============================================================================
--
-- DROP TABLE IF EXISTS `persona_fusiones`;
-- DROP TABLE IF EXISTS `persona_duplicados`;
-- DROP TABLE IF EXISTS `persona_tokens`;
-- ALTER TABLE `personas` DROP INDEX `personas_clave_norm_idx`;
-- ALTER TABLE `personas` DROP INDEX `personas_clave_orden_idx`;
-- ALTER TABLE `personas` DROP COLUMN `clave_norm`;
-- ALTER TABLE `personas` DROP COLUMN `clave_orden`;
-- DELETE FROM `migrations` WHERE `migration` = '2026_08_21_140000_create_persona_duplicados_table';
