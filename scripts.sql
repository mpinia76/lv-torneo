########################## Eliminar jugadores que NO participaron del torneo ##########################
DELETE plantilla_jugadors FROM
plantillas INNER JOIN plantilla_jugadors ON plantillas.id = plantilla_jugadors.plantilla_id
 INNER JOIN grupos ON grupos.id = plantillas.grupo_id
 INNER JOIN torneos ON torneos.id = grupos.torneo_id
WHERE torneos.id = 30 AND NOT EXISTS (SELECT alineacions.jugador_id
FROM torneos t2 INNER JOIN grupos g2 ON t2.id = g2.torneo_id
INNER JOIN fechas ON fechas.grupo_id = g2.id
INNER JOIN partidos ON partidos.fecha_id = fechas.id
INNER JOIN alineacions ON alineacions.partido_id = partidos.id
WHERE t2.id = 30 AND alineacions.equipo_id = plantillas.equipo_id AND plantilla_jugadors.jugador_id = alineacions.jugador_id);

########################## Control dorsales jugadores ##########################
SELECT  fechas.numero, alineacions.dorsal, plantilla_jugadors.dorsal, alineacions.jugador_id,
 partidos.id, equipos.nombre
FROM plantillas
INNER JOIN plantilla_jugadors ON plantillas.id = plantilla_jugadors.plantilla_id
INNER JOIN grupos ON plantillas.grupo_id = grupos.id
INNER JOIN fechas ON fechas.grupo_id = grupos.id
INNER JOIN partidos ON fechas.id = partidos.fecha_id
INNER JOIN alineacions ON alineacions.partido_id = partidos.id AND alineacions.equipo_id = plantillas.equipo_id
INNER JOIN equipos ON alineacions.equipo_id = equipos.id
WHERE alineacions.dorsal <> plantilla_jugadors.dorsal AND grupos.torneo_id = 21
AND plantilla_jugadors.jugador_id = alineacions.jugador_id AND plantilla_jugadors.dorsal < 100

########################## Jugadores Sin nacimiento ##########################
SELECT jugadors.id, personas.apellido, equipos.nombre, partidos.id
FROM personas
INNER JOIN jugadors ON personas.id = jugadors.persona_id
INNER JOIN alineacions ON jugadors.id = alineacions.jugador_id
INNER JOIN partidos ON partidos.id = alineacions.partido_id
INNER JOIN equipos ON alineacions.equipo_id = equipos.id

WHERE personas.nacimiento IS null

########################## Técnicos Sin nacimiento ##########################
SELECT jugadors.id, personas.apellido, equipos.nombre, partidos.id
FROM personas
INNER JOIN tecnicos ON tecnicos.id = tecnicos.persona_id
INNER JOIN partido_tecnicos ON tecnicos.id = partido_tecnicos.tecnico_id
INNER JOIN partidos ON partidos.id = partido_tecnicos.partido_id
INNER JOIN equipos ON partido_tecnicos.equipo_id = equipos.id

WHERE personas.nacimiento IS null

########################## Alineaciones distintas a 11 jugadores ##########################
SELECT partido_id, equipo_id, COUNT(partido_id)
FROM alineacions
WHERE tipo = 'Titular'
GROUP BY partido_id,equipo_id
HAVING COUNT(partido_id)!=11

########################## Con tarjetas y no jugaron ##########################
SELECT *
FROM tarjetas
WHERE NOT EXISTS (SELECT alineacions.id FROM alineacions WHERE alineacions.partido_id = tarjetas.partido_id)

########################## Con goles y no jugaron ##########################
SELECT *
FROM gols
WHERE NOT EXISTS (SELECT alineacions.id FROM alineacions WHERE alineacions.partido_id = gols.partido_id)

########################## Tarjetas repetidas ##########################
SELECT partido_id, jugador_id, COUNT(partido_id)
FROM tarjetas
GROUP BY partido_id,jugador_id, tipo
HAVING COUNT(partido_id)>1

########################## Cambios repetidos ##########################
SELECT partido_id, jugador_id, COUNT(partido_id)
FROM cambios
GROUP BY partido_id,jugador_id, tipo
HAVING COUNT(partido_id)>1

########################## Cambios impares ##########################
SELECT partido_id, COUNT(partido_id)
FROM cambios
GROUP BY partido_id
HAVING COUNT(partido_id) % 2 != 0

########################## Diferencia en goles ##########################
SELECT *
FROM partidos
WHERE partidos.golesl + partidos.golesv !=
(SELECT COUNT(gols.id) FROM gols WHERE partidos.id = gols.partido_id GROUP BY gols.partido_id)

########################## más de 3 arbitros ##########################
SELECT partido_id, COUNT(partido_id)
FROM partido_arbitros
GROUP BY partido_id
HAVING COUNT(partido_id)>3

