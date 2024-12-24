########################## Eliminar jugadores que NO participaron del torneo ##########################
DELETE plantilla_jugadors FROM
plantillas INNER JOIN plantilla_jugadors ON plantillas.id = plantilla_jugadors.plantilla_id
 INNER JOIN grupos ON grupos.id = plantillas.grupo_id
 INNER JOIN torneos ON torneos.id = grupos.torneo_id
WHERE torneos.id = 10 AND NOT EXISTS (SELECT alineacions.jugador_id
FROM torneos t2 INNER JOIN grupos g2 ON t2.id = g2.torneo_id
INNER JOIN fechas ON fechas.grupo_id = g2.id
INNER JOIN partidos ON partidos.fecha_id = fechas.id
INNER JOIN alineacions ON alineacions.partido_id = partidos.id
WHERE t2.id = 2 AND alineacions.equipo_id = plantillas.equipo_id AND plantilla_jugadors.jugador_id = alineacions.jugador_id);

########################## Control dorsales jugadores ##########################
SELECT  fechas.numero, alineacions.dorsal, plantilla_jugadors.dorsal, alineacions.jugador_id,
 partidos.id, equipos.nombre, personas.apellido, personas.nombre
FROM plantillas
INNER JOIN plantilla_jugadors ON plantillas.id = plantilla_jugadors.plantilla_id
INNER JOIN grupos ON plantillas.grupo_id = grupos.id
INNER JOIN fechas ON fechas.grupo_id = grupos.id
INNER JOIN partidos ON fechas.id = partidos.fecha_id
INNER JOIN alineacions ON alineacions.partido_id = partidos.id AND alineacions.equipo_id = plantillas.equipo_id
INNER JOIN equipos ON alineacions.equipo_id = equipos.id
INNER JOIN jugadors ON alineacions.jugador_id = jugadors.id
INNER JOIN personas ON jugadors.persona_id = personas.id
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
SELECT tecnicos.id, personas.apellido, equipos.nombre, partidos.id
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
WHERE NOT EXISTS (SELECT alineacions.id FROM alineacions WHERE alineacions.partido_id = tarjetas.partido_id AND alineacions.jugador_id = tarjetas.jugador_id)

########################## Con goles y no jugaron ##########################
SELECT *
FROM gols
WHERE NOT EXISTS (
	SELECT alineacions.id
	FROM alineacions
	LEFT JOIN cambios ON alineacions.partido_id = cambios.partido_id
	WHERE alineacions.partido_id = gols.partido_id
	AND alineacions.jugador_id = gols.jugador_id AND (alineacions.tipo = 'Titular' OR cambios.tipo = 'Entra'))

SELECT *
FROM gols
WHERE NOT EXISTS (
    SELECT alineacions.id
    FROM alineacions
             LEFT JOIN cambios ON alineacions.partido_id = cambios.partido_id
        AND alineacions.jugador_id = cambios.jugador_id
    WHERE alineacions.partido_id = gols.partido_id
      AND alineacions.jugador_id = gols.jugador_id
      AND (alineacions.tipo = 'Titular' OR cambios.tipo = 'Entra')
    );


########################## Con cambios y no jugaron ##########################
SELECT *
FROM cambios
WHERE NOT EXISTS (SELECT alineacions.id FROM alineacions WHERE alineacions.partido_id = cambios.partido_id AND alineacions.jugador_id = cambios.jugador_id)

########################## Goles repetidas ##########################
SELECT partido_id, jugador_id, COUNT(partido_id)
FROM gols
GROUP BY partido_id,jugador_id, minuto
HAVING COUNT(partido_id)>1

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

########################## Eliminar Cambios repetidos ##########################
DELETE FROM cambios

WHERE id IN

      (

          SELECT

              id

          FROM

              (

                  SELECT id
                  FROM cambios
                  GROUP BY partido_id,jugador_id, tipo
                  HAVING COUNT(partido_id)>1

              ) AS duplicate_ids

      );

########################## Cambios impares ##########################
SELECT partido_id, minuto, COUNT(partido_id)
FROM cambios
GROUP BY partido_id, minuto
HAVING COUNT(partido_id) % 2 != 0

########################## Diferencia en goles ##########################
SELECT *
FROM partidos
WHERE partidos.golesl + partidos.golesv !=
(SELECT COUNT(gols.id) FROM gols WHERE partidos.id = gols.partido_id GROUP BY gols.partido_id)

########################## sin técnico local ##########################
SELECT *
FROM partidos
WHERE NOT EXISTS (
    SELECT partido_id
    FROM partido_tecnicos
    WHERE partidos.id = partido_tecnicos.partido_id AND partidos.equipol_id = partido_tecnicos.equipo_id
    GROUP BY partido_id)

########################## sin técnico visitante ##########################
SELECT *
FROM partidos
WHERE NOT EXISTS (
    SELECT partido_id
    FROM partido_tecnicos
    WHERE partidos.id = partido_tecnicos.partido_id AND partidos.equipov_id = partido_tecnicos.equipo_id
    GROUP BY partido_id)


########################## sin arbitro ##########################
SELECT *
FROM partidos
WHERE NOT EXISTS (
    SELECT partido_id
    FROM partido_arbitros
    WHERE TIPO = 'Principal' AND partidos.id = partido_arbitros.partido_id
    GROUP BY partido_id)

########################## distinto de 3 arbitros ##########################
SELECT partido_id, COUNT(partido_id)
FROM partido_arbitros
GROUP BY partido_id
HAVING COUNT(partido_id)!=3
ORDER BY partido_id desc

########################## tipos de árbitros repetidos ##########################
SELECT partido_id, COUNT(partido_id)
FROM partido_arbitros
GROUP BY partido_id, tipo
HAVING COUNT(partido_id)>1

########################## jugadores misma fechas en 2 equipos ##########################
SELECT jugadors.id, CONCAT(personas.apellido,', ',personas.nombre) AS jugador, partidos.id, partidos.fecha_id, alineacions.equipo_id
FROM alineacions
         INNER JOIN partidos ON alineacions.partido_id = partidos.id
         INNER JOIN jugadors ON alineacions.jugador_id = jugadors.id
         INNER JOIN personas ON jugadors.persona_id = personas.id
WHERE partidos.fecha_id NOT IN(16,89,90,92,113,146,386,389,708,749,808,991) AND (alineacions.jugador_id, partidos.fecha_id) = ANY (
    SELECT A2.jugador_id, P2.fecha_id
    FROM alineacions A2
             INNER JOIN partidos P2 ON A2.partido_id = P2.id
    GROUP BY P2.fecha_id,A2.jugador_id
    HAVING COUNT(P2.fecha_id)>1)
ORDER BY partidos.fecha_id

########################## consultar jugador equipo dorsal ##########################
SELECT torneos.id, torneos.nombre, torneos.year, fechas.numero, dorsal, partidos.id
FROM alineacions
INNER JOIN partidos ON alineacions.partido_id = partidos.id
INNER JOIN fechas ON fechas.id = partidos.fecha_id
INNER JOIN grupos ON fechas.grupo_id = grupos.id
INNER JOIN torneos ON grupos.torneo_id = torneos.id
WHERE jugador_id = 575 AND equipo_id = 14

########################## modificar jugador en un torneo ##########################
UPDATE alineacions

    INNER JOIN partidos ON alineacions.partido_id = partidos.id
    INNER JOIN fechas ON fechas.id = partidos.fecha_id
    INNER JOIN grupos ON fechas.grupo_id = grupos.id
    INNER JOIN torneos ON grupos.torneo_id = torneos.id
    SET alineacions.jugador_id=2174
WHERE alineacions.jugador_id = 2608 AND alineacions.equipo_id = 22 AND torneos.id = 10;

UPDATE plantilla_jugadors
         INNER JOIN plantillas ON plantilla_jugadors.plantilla_id = plantillas.id

         INNER JOIN grupos ON plantillas.grupo_id = grupos.id
         INNER JOIN torneos ON grupos.torneo_id = torneos.id
    SET plantilla_jugadors.jugador_id=2174
WHERE plantilla_jugadors.jugador_id = 2608 AND plantillas.equipo_id = 22 AND torneos.id = 10;

UPDATE gols
         INNER JOIN partidos ON gols.partido_id = partidos.id
         INNER JOIN fechas ON fechas.id = partidos.fecha_id
         INNER JOIN grupos ON fechas.grupo_id = grupos.id
         INNER JOIN torneos ON grupos.torneo_id = torneos.id
    SET gols.jugador_id=2174
WHERE gols.jugador_id = 2608 AND (partidos.equipol_id  = 22 OR partidos.equipov_id  = 22) AND torneos.id = 10;

UPDATE cambios
    INNER JOIN partidos ON cambios.partido_id = partidos.id
    INNER JOIN fechas ON fechas.id = partidos.fecha_id
    INNER JOIN grupos ON fechas.grupo_id = grupos.id
    INNER JOIN torneos ON grupos.torneo_id = torneos.id
    SET cambios.jugador_id=2174
WHERE cambios.jugador_id = 2608 AND (partidos.equipol_id  = 22 OR partidos.equipov_id  = 22) AND torneos.id = 10;

UPDATE tarjetas
    INNER JOIN partidos ON tarjetas.partido_id = partidos.id
    INNER JOIN fechas ON fechas.id = partidos.fecha_id
    INNER JOIN grupos ON fechas.grupo_id = grupos.id
    INNER JOIN torneos ON grupos.torneo_id = torneos.id
    SET tarjetas.jugador_id=2174
WHERE tarjetas.jugador_id = 2608 AND (partidos.equipol_id  = 22 OR partidos.equipov_id  = 22) AND torneos.id = 10;

########################## modificar jugador repetido ##########################
UPDATE alineacions
    SET alineacions.jugador_id=4136
WHERE alineacions.jugador_id = 15432 ;

UPDATE plantilla_jugadors

    SET plantilla_jugadors.jugador_id=4136
WHERE plantilla_jugadors.jugador_id = 15432 ;

UPDATE gols

    SET gols.jugador_id=4136
WHERE gols.jugador_id = 15432 ;

UPDATE cambios

    SET cambios.jugador_id=4136
WHERE cambios.jugador_id = 15432 ;

UPDATE tarjetas

    SET tarjetas.jugador_id=4136
WHERE tarjetas.jugador_id = 15432 ;

########################## modificar arbitro repetido ##########################
UPDATE `partido_arbitros` SET `arbitro_id`=207 WHERE  `arbitro_id`=6;

########################## Modificar dorsal en un torneo ##########################
SET @dorsal := 6;
SET @jugador_id := 9754;
SET @equipo_id := 182;
SET @torneo_id := 102;

UPDATE plantilla_jugadors
    INNER JOIN plantillas ON plantilla_jugadors.plantilla_id = plantillas.id
    INNER JOIN grupos ON plantillas.grupo_id = grupos.id
    INNER JOIN torneos ON grupos.torneo_id = torneos.id
    SET plantilla_jugadors.dorsal = @dorsal
WHERE plantilla_jugadors.jugador_id = @jugador_id AND plantillas.equipo_id = @equipo_id AND torneos.id = @torneo_id;

UPDATE alineacions
    INNER JOIN partidos ON alineacions.partido_id = partidos.id
    INNER JOIN fechas ON fechas.id = partidos.fecha_id
    INNER JOIN grupos ON fechas.grupo_id = grupos.id
    INNER JOIN torneos ON grupos.torneo_id = torneos.id
    SET alineacions.dorsal = @dorsal
WHERE alineacions.jugador_id = @jugador_id AND alineacions.equipo_id = @equipo_id AND torneos.id = @torneo_id;

