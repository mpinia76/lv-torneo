-- ============================================================================
--  ¿Transfermarkt trae la terna completa?
--
--  El control "Terna incompleta" mira SÓLO los partidos que ya tienen
--  alineación cargada (ver Controles::conAlineacion) y que no tengan una
--  incidencia marcada. Así que 578 no se compara contra los 33.182 partidos
--  con Principal: se compara contra los partidos con detalle importado.
--
--  Estas consultas dicen si el faltante es parejo (TM nunca trae asistentes)
--  o si depende de la competencia y la época (que es lo que se espera).
-- ============================================================================


-- ── 1) El número que falta: ¿sobre cuántos partidos es el 578? ──────────────
SELECT
    COUNT(*)                                                                  AS con_alineacion,
    SUM(CASE WHEN EXISTS (SELECT 1 FROM partido_arbitros pa
                          WHERE pa.partido_id = p.id AND pa.tipo = 'Principal') THEN 1 ELSE 0 END) AS con_principal,
    SUM(CASE WHEN EXISTS (SELECT 1 FROM partido_arbitros pa
                          WHERE pa.partido_id = p.id AND pa.tipo = 'Linea 1')   THEN 1 ELSE 0 END) AS con_linea1,
    SUM(CASE WHEN EXISTS (SELECT 1 FROM partido_arbitros pa
                          WHERE pa.partido_id = p.id AND pa.tipo = 'Linea 2')   THEN 1 ELSE 0 END) AS con_linea2
FROM partidos p
WHERE EXISTS (SELECT 1 FROM alineacions a WHERE a.partido_id = p.id);


-- ── 2) Abierto por torneo y año ─────────────────────────────────────────────
--  Si el problema fuera del importador, la columna `con_linea1` daría 0 en
--  TODAS las filas. Si da 0 sólo en ciertos torneos o años viejos, entonces es
--  cobertura de la fuente y no hay nada que arreglar.
SELECT
    t.year,
    t.nombre                                                                  AS torneo,
    COUNT(*)                                                                  AS partidos,
    SUM(CASE WHEN EXISTS (SELECT 1 FROM partido_arbitros pa
                          WHERE pa.partido_id = p.id AND pa.tipo = 'Principal') THEN 1 ELSE 0 END) AS principal,
    SUM(CASE WHEN EXISTS (SELECT 1 FROM partido_arbitros pa
                          WHERE pa.partido_id = p.id AND pa.tipo = 'Linea 1')   THEN 1 ELSE 0 END) AS linea1,
    SUM(CASE WHEN EXISTS (SELECT 1 FROM partido_arbitros pa
                          WHERE pa.partido_id = p.id AND pa.tipo = 'Linea 2')   THEN 1 ELSE 0 END) AS linea2,
    SUM(CASE WHEN EXISTS (SELECT 1 FROM partido_arbitros pa
                          WHERE pa.partido_id = p.id AND pa.tipo = 'VAR')       THEN 1 ELSE 0 END) AS var
FROM partidos p
JOIN fechas  f ON f.id = p.fecha_id
JOIN grupos  g ON g.id = f.grupo_id
JOIN torneos t ON t.id = g.torneo_id
WHERE EXISTS (SELECT 1 FROM alineacions a WHERE a.partido_id = p.id)
GROUP BY t.year, t.nombre
ORDER BY t.year DESC, t.nombre;


-- ── 3) Sólo lo que vino de Transfermarkt ────────────────────────────────────
--  Separa lo importado (tiene fila en import_partidos con external_id) del
--  resto, que vino de promiedos o se cargó a mano. Si acá linea1 = 0 y en el
--  resto de la base es alto, la conclusión es directa.
SELECT
    CASE WHEN ip.partido_id IS NULL THEN 'otras fuentes' ELSE 'Transfermarkt' END AS origen,
    COUNT(*)                                                                  AS partidos,
    SUM(CASE WHEN EXISTS (SELECT 1 FROM partido_arbitros pa
                          WHERE pa.partido_id = p.id AND pa.tipo = 'Principal') THEN 1 ELSE 0 END) AS principal,
    SUM(CASE WHEN EXISTS (SELECT 1 FROM partido_arbitros pa
                          WHERE pa.partido_id = p.id AND pa.tipo = 'Linea 1')   THEN 1 ELSE 0 END) AS linea1,
    SUM(CASE WHEN EXISTS (SELECT 1 FROM partido_arbitros pa
                          WHERE pa.partido_id = p.id AND pa.tipo = 'Linea 2')   THEN 1 ELSE 0 END) AS linea2
FROM partidos p
LEFT JOIN (SELECT DISTINCT partido_id FROM import_partidos
           WHERE partido_id IS NOT NULL AND external_id IS NOT NULL AND external_id <> '') ip
       ON ip.partido_id = p.id
WHERE EXISTS (SELECT 1 FROM alineacions a WHERE a.partido_id = p.id)
GROUP BY origen;


-- ── 4) Los 578, agrupados ───────────────────────────────────────────────────
--  Para ver de un vistazo si se concentran en pocos torneos.
SELECT
    t.year,
    t.nombre AS torneo,
    COUNT(*) AS sin_terna
FROM partidos p
JOIN fechas  f ON f.id = p.fecha_id
JOIN grupos  g ON g.id = f.grupo_id
JOIN torneos t ON t.id = g.torneo_id
WHERE EXISTS (SELECT 1 FROM alineacions a WHERE a.partido_id = p.id)
  AND (   NOT EXISTS (SELECT 1 FROM partido_arbitros pa WHERE pa.partido_id = p.id AND pa.tipo = 'Principal')
       OR NOT EXISTS (SELECT 1 FROM partido_arbitros pa WHERE pa.partido_id = p.id AND pa.tipo = 'Linea 1')
       OR NOT EXISTS (SELECT 1 FROM partido_arbitros pa WHERE pa.partido_id = p.id AND pa.tipo = 'Linea 2'))
GROUP BY t.year, t.nombre
ORDER BY sin_terna DESC;
