<?php

namespace App\Services;

/**
 * El año de cierre que Transfermarkt le cuelga al nombre de un club que ya no
 * existe.
 *
 * TM no renombra la ficha cuando un club desaparece (o se fusiona, o se
 * refunda): deja el nombre y le agrega un paréntesis al final, en dos formas:
 *
 *   · "Al-Ahli Dubai Club (- 2017)"      — sólo el año de cierre.
 *   · "Sarayköy 1926 FK (1981-2019)"     — el de inicio y el de cierre.
 *
 * `partir()` separa el nombre del paréntesis. Sólo reconoce un paréntesis AL
 * FINAL, con guión y con año de cierre de cuatro cifras: "Hannover 96" o
 * "Club (1921)" no se tocan. Lo que no se entiende devuelve null — un nombre
 * mal cortado se nota menos que uno sin cortar.
 *
 * **Ojo con los fusionados** (memoria [[clubes-fusionados-tm]]): el verein
 * viejo de un club que se fusionó o refundó también lleva el "(- AAAA)", pero
 * el club SIGUE vivo en el otro verein, mapeado al mismo equipo nuestro. Que
 * un nombre de TM tenga el paréntesis no alcanza para dar al equipo por
 * desaparecido: eso lo decide quien llama, mirando todos los mapeos.
 */
class ClubDesaparecido
{
    const PATRON = '/^(.*?)\s*\(\s*(\d{4})?\s*[-–—]\s*(\d{4})\s*\)\s*$/u';

    /**
     * ['nombre' => 'Sarayköy 1926 FK', 'desde' => 1981|null, 'hasta' => 2019,
     *  'parentesis' => '(1981-2019)'] — o null si el nombre no lo trae.
     */
    public static function partir($nombre)
    {
        $nombre = trim((string) $nombre);
        if ($nombre === '' || !preg_match(self::PATRON, $nombre, $m)) return null;

        $limpio = trim($m[1]);
        $desde  = ($m[2] !== '') ? (int) $m[2] : null;
        $hasta  = (int) $m[3];

        if ($limpio === '') return null;
        if ($hasta < 1850 || $hasta > (int) date('Y')) return null;
        if ($desde !== null && ($desde < 1800 || $desde > $hasta)) return null;

        return [
            'nombre'     => $limpio,
            'desde'      => $desde,
            'hasta'      => $hasta,
            'parentesis' => trim(mb_substr($nombre, mb_strlen($m[1]))),
        ];
    }

    /** El 1º de enero del año de cierre, que es lo único que da TM. */
    public static function fechaDeCierre(array $partes)
    {
        return sprintf('%04d-01-01', $partes['hasta']);
    }
}
