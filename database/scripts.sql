SELECT *
FROM fechas
INNER JOIN partidos ON fechas.id = partidos.fecha_id
INNER JOIN alineacions ON alineacions.partido_id = partidos.id
INNER JOIN jugadors ON alineacions.jugador_id = jugadors.id
INNER JOIN equipos ON alineacions.equipo_id = equipos.id
WHERE fechas.grupo_id = 29 AND
jugadors.apellido LIKE '%%' AND equipos.nombre LIKE '%%'
