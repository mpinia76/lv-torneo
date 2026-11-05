-- Torneo que no da título (ver la migración 2026_11_03_120000_add_sin_titulo_to_torneos_table).
ALTER TABLE torneos ADD COLUMN sin_titulo TINYINT(1) NOT NULL DEFAULT 0 AFTER inconcluso;
