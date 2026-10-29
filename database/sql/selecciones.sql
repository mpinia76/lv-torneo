-- Torneos de selecciones (29/10/2026). Lo mismo que la migración
-- 2026_10_29_120000_add_selecciones_to_torneos_table. Correr ANTES del git pull.

ALTER TABLE torneos ADD COLUMN selecciones TINYINT(1) NOT NULL DEFAULT 0 AFTER ambito;

-- Un torneo de selecciones va con ambito = 'Internacional', selecciones = 1 y
-- la región cargada (fifa, conmebol, uefa, concacaf, afc, caf, ofc): la región
-- decide en qué zona del menú «Selecciones» aparece. Sin región va a FIFA.
