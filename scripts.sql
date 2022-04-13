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
WHERE t2.id = 30 AND alineacions.equipo_id = plantillas.equipo_id AND plantilla_jugadors.jugador_id = alineacions.jugador_id)
