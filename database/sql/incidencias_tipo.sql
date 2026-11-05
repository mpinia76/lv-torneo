-- ============================================================================
-- Tipo de incidencia (24/10/2026). Ver App\Incidencia::TIPOS / TAPA.
--
-- Correr EN ORDEN, en la consola de MariaDB, ANTES del git pull que trae el
-- código (el código nuevo pregunta por incidencias.tipo).
--
-- Lo que no se clasifique queda en NULL y se comporta como antes: tapa todos
-- los controles. Se clasifica después desde el admin (Incidencias → editar:
-- el formulario ahora pide el tipo).
-- ============================================================================

-- 0) Copia por las dudas
CREATE TABLE incidencias_bak_20261024 AS SELECT * FROM incidencias;

-- 1) La columna (lo mismo que la migración 2026_10_24_120000)
ALTER TABLE incidencias
  ADD COLUMN tipo VARCHAR(40) NULL AFTER partido_id,
  ADD INDEX incidencias_partido_tipo (partido_id, tipo);


-- 2) Notas de carga: textos fijos que escribe el botón "no se puede arreglar"
--    de cada control (Controles::motivoSinDatos) y sus versiones viejas.
--    La colación es utf8mb4_unicode_ci: el LIKE no distingue acentos.

UPDATE incidencias SET tipo = 'arbitros.terna'
 WHERE tipo IS NULL AND partido_id IS NOT NULL
   AND (observaciones LIKE 'Terna incompleta:%'
     OR observaciones LIKE 'Sin asistentes%'
     OR observaciones LIKE 'Sin arbitros%');

UPDATE incidencias SET tipo = 'alineaciones.sin_jugadores'
 WHERE tipo IS NULL AND observaciones LIKE 'No hay registro de la alineación de alguno de los dos equipos%';

UPDATE incidencias SET tipo = 'alineaciones.faltan'
 WHERE tipo IS NULL AND observaciones LIKE 'La alineación registrada para este partido está incompleta%';

UPDATE incidencias SET tipo = 'tecnicos.faltan'
 WHERE tipo IS NULL AND observaciones LIKE 'No hay registro del técnico de alguno de los dos equipos%';

UPDATE incidencias SET tipo = 'goles.diferencia'
 WHERE tipo IS NULL AND observaciones LIKE 'No hay registro de todos los goles del partido%';

UPDATE incidencias SET tipo = 'goles.por_equipo'
 WHERE tipo IS NULL AND observaciones LIKE 'No hay registro de qué equipo convirtió%';

UPDATE incidencias SET tipo = 'goles.sin_jugar'
 WHERE tipo IS NULL AND observaciones LIKE 'El goleador no figura en la alineación%';

UPDATE incidencias SET tipo = 'tarjetas.sin_jugar'
 WHERE tipo IS NULL AND observaciones LIKE 'El amonestado no figura en la alineación%';

UPDATE incidencias SET tipo = 'cambios.sin_jugar'
 WHERE tipo IS NULL AND observaciones LIKE 'El jugador del cambio no figura en la alineación%';

UPDATE incidencias SET tipo = 'cambios.impares'
 WHERE tipo IS NULL AND observaciones LIKE 'No hay registro de quién salió en ese cambio%';

-- "Salida sin reemplazo: ..." (fijo) y las escritas a mano con el nombre
-- ("Sale Lionel Messi sin reemplazo", "Hernán Menosse sale lesionado sin reemplazo")
UPDATE incidencias SET tipo = 'cambios.sale_solo'
 WHERE tipo IS NULL AND observaciones LIKE '%sin reemplazo%';

UPDATE incidencias SET tipo = 'no_jugado'
 WHERE tipo IS NULL AND observaciones LIKE 'Sin resultado a propósito%';

-- El genérico (partido entero sin detalle), en sus dos redacciones
UPDATE incidencias SET tipo = 'sin_detalle'
 WHERE tipo IS NULL
   AND (observaciones LIKE 'No hay registro del detalle completo de este partido%'
     OR observaciones_en LIKE 'The full details of this match are not available%');


-- 3) Hechos del partido que ya revisamos uno por uno (24/10/2026).
--    Sólo pisan las que siguen sin tipo, salvo Ankaraspor (punto 4).

-- Escritorio: se jugó (entero o en parte) y el resultado oficial es otro.
UPDATE incidencias SET tipo = 'escritorio'
 WHERE tipo IS NULL AND partido_id IN (
   31598,  -- Celta 3-0 Estrella Roja (UEFA 2000/01, se jugó 5-3)
   41396,  -- Nantes 0-0 Bastia (Ligue 1 2013/14, goles anulados)
   58168,  -- Roma 0-3 Dynamo Kiev (suspendido en el entretiempo)
   58257,  -- Inter 0-3 Milan (Champions 2004/05, suspendido)
   28962,  -- Panathinaikos 0-3 Olympiacos (Grecia 2011/12)
   38437,  -- Aris 0-3 Asteras (suspendido a los 42')
   14669,  -- Blooming 0-3 River (URU) (Sudamericana 2009)
   16661,  -- Boca 0-3 River (Libertadores 2015, gas pimienta)
   17639,  -- Independiente 3-0 Santos (Libertadores 2018, Carlos Sánchez)
   36666,  -- Diyarbakirspor 0-3 Bursaspor (suspendido a los 17')
   27478, 36481, 36491, 36499  -- Ankaraspor: los cuatro que se llegaron a jugar
 );

-- No jugado: nunca se disputó.
UPDATE incidencias SET tipo = 'no_jugado'
 WHERE tipo IS NULL AND partido_id IN (
   1811,   -- River 0-1 Atlético Tucumán (2020, River no se presentó)
   3887,   -- Colón 0-1 Atlético Rafaela (2013, no se jugó)
   18926   -- Al Duhail 3-0 Auckland City (Mundial de Clubes 2020, Auckland se retiró)
 );

-- Contexto (no tapan controles ni anulan goles)
UPDATE incidencias SET tipo = 'otra'
 WHERE tipo IS NULL
   AND (observaciones LIKE '%Se continuó%'
     OR observaciones LIKE '%Se reanudó%'
     OR observaciones LIKE 'Un hincha de 53 años%'
     OR observaciones LIKE 'Guigou recibió dos amarillas%');


-- 4) Ankaraspor (Süper Lig 2009/10): los 30 partidos que no se jugaron tienen
--    la incidencia "no hay registro de la alineación", que no explica nada.
--    Pasan a no_jugado con el texto de la expulsión (copiado del 36481).
SELECT observaciones, observaciones_en INTO @t, @te
  FROM incidencias WHERE partido_id = 36481 AND tipo = 'escritorio' LIMIT 1;

UPDATE incidencias i
  JOIN partidos p ON p.id = i.partido_id
  JOIN fechas f   ON f.id = p.fecha_id
  JOIN grupos g   ON g.id = f.grupo_id
  JOIN equipos el ON el.id = p.equipol_id
  JOIN equipos ev ON ev.id = p.equipov_id
   SET i.tipo = 'no_jugado', i.observaciones = @t, i.observaciones_en = @te
 WHERE g.torneo_id = 380
   AND (el.nombre = 'Ankaraspor' OR ev.nombre = 'Ankaraspor')
   AND p.id NOT IN (27478, 36481, 36491, 36499)
   AND i.tipo IN ('alineaciones.sin_jugadores', 'sin_detalle')  -- la de terna, si hay, queda como está
   AND @t IS NOT NULL;

-- Control: tiene que devolver 30 (los no jugados de Ankaraspor con no_jugado)
SELECT COUNT(DISTINCT p.id) AS ankaraspor_no_jugados
  FROM partidos p
  JOIN fechas f ON f.id = p.fecha_id JOIN grupos g ON g.id = f.grupo_id
  JOIN equipos el ON el.id = p.equipol_id JOIN equipos ev ON ev.id = p.equipov_id
  JOIN incidencias i ON i.partido_id = p.id AND i.tipo = 'no_jugado'
 WHERE g.torneo_id = 380 AND (el.nombre = 'Ankaraspor' OR ev.nombre = 'Ankaraspor');


-- 5) Sanciones de puntos: con equipo y puntos, sin otro tipo.
UPDATE incidencias SET tipo = 'sancion_puntos'
 WHERE tipo IS NULL AND equipo_id IS NOT NULL AND puntos IS NOT NULL;


-- 6) Cómo quedó, y lo que falta clasificar a mano desde el admin
SELECT COALESCE(tipo, '(sin clasificar)') AS tipo, COUNT(*) AS n
  FROM incidencias GROUP BY tipo ORDER BY n DESC;

SELECT id, torneo_id, partido_id, equipo_id, puntos, LEFT(observaciones, 100) AS texto
  FROM incidencias WHERE tipo IS NULL ORDER BY torneo_id, partido_id;

-- Si algo salió mal:
--   UPDATE incidencias i JOIN incidencias_bak_20261024 b ON b.id = i.id
--      SET i.observaciones = b.observaciones, i.observaciones_en = b.observaciones_en, i.tipo = NULL;
-- Cuando esté todo bien:
--   DROP TABLE incidencias_bak_20261024;
