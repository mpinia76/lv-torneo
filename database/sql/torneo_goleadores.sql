-- Goleador del torneo (24/10/2026). Lo mismo que la migración
-- 2026_10_24_150000_create_torneo_goleadores_table. Correr ANTES del git pull.
-- Después, como deploy:  php artisan torneos:goleadores

CREATE TABLE torneo_goleadores (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  torneo_id   BIGINT UNSIGNED NOT NULL,
  jugador_id  BIGINT UNSIGNED NOT NULL,
  goles       SMALLINT UNSIGNED NOT NULL,
  compartido  TINYINT(1) NOT NULL DEFAULT 0,
  inconcluso  TINYINT(1) NOT NULL DEFAULT 0,
  created_at  TIMESTAMP NULL,
  updated_at  TIMESTAMP NULL,
  UNIQUE KEY torneo_goleadores_torneo_jugador (torneo_id, jugador_id),
  KEY torneo_goleadores_jugador (jugador_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Para mirar el resultado después de correr el comando:
-- SELECT t.nombre, t.year, CONCAT(pe.nombre, ' ', pe.apellido) AS goleador, tg.goles, tg.compartido, tg.inconcluso
--   FROM torneo_goleadores tg
--   JOIN torneos t ON t.id = tg.torneo_id
--   JOIN jugadors j ON j.id = tg.jugador_id JOIN personas pe ON pe.id = j.persona_id
--  ORDER BY t.year DESC, t.nombre LIMIT 50;
