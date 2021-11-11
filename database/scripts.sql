SELECT *
FROM fechas
INNER JOIN partidos ON fechas.id = partidos.fecha_id
INNER JOIN alineacions ON alineacions.partido_id = partidos.id
INNER JOIN jugadors ON alineacions.jugador_id = jugadors.id
INNER JOIN equipos ON alineacions.equipo_id = equipos.id
WHERE fechas.grupo_id = 29 AND
jugadors.apellido LIKE '%%' AND equipos.nombre LIKE '%%'


SELECT *

 FROM  partido_tecnicos

 INNER JOIN tecnicos ON tecnicos.id = partido_tecnicos.tecnico_id
 INNER JOIN equipos ON equipos.id = partido_tecnicos.equipo_id
WHERE
tecnicos.apellido LIKE '%%' AND equipos.nombre LIKE '%%'
