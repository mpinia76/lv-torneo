<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Traduce un club de Transfermarkt a un equipo nuestro.
 *
 * Vivía adentro de `ImportPartidosController` como métodos privados. Se sacó
 * acá cuando hizo falta la misma lógica en el lector de calendarios HTML: dos
 * copias del apareo de nombres es la forma segura de que una arregle un caso y
 * la otra no, y acá una traducción equivocada carga el partido con el rival
 * cambiado. El controlador ahora delega en esto, así que sigue habiendo una
 * sola implementación.
 *
 * Dos caminos, en orden: el mapeo explícito de `equipo_tm` —que es el que
 * manda— y, si no está, el nombre normalizado.
 */
class MapeoClubesTm
{
    /** @var array|null tm_club_id => equipo_id */
    private $porId = null;

    /** @var array|null nombre normalizado => equipo_id (null si es ambiguo) */
    private $porNombre = null;

    /** [tm_club_id => equipo_id] de la tabla `equipo_tm`. */
    public function porId(): array
    {
        if ($this->porId === null) {
            $this->porId = [];

            foreach (DB::table('equipo_tm')->select('tm_club_id', 'equipo_id')->get() as $r) {
                $this->porId[(string) $r->tm_club_id] = (int) $r->equipo_id;
            }
        }

        return $this->porId;
    }

    /**
     * Nombre normalizado -> equipo_id.
     *
     * Si dos equipos comparten la misma clave (pasa con los homónimos), la
     * clave se marca **ambigua** y no matchea con nadie: mejor un conflicto
     * para resolver a mano que un partido con el rival cambiado.
     */
    public function porNombre(): array
    {
        if ($this->porNombre === null) {
            $this->porNombre = [];

            foreach (\App\Equipo::select('id', 'nombre')->get() as $e) {
                foreach ($this->claves($e->nombre) as $k) {
                    if ($k === '') continue;

                    if (isset($this->porNombre[$k]) && $this->porNombre[$k] !== $e->id) {
                        $this->porNombre[$k] = null;      // ambigua
                    } elseif (!array_key_exists($k, $this->porNombre)) {
                        $this->porNombre[$k] = $e->id;
                    }
                }
            }
        }

        return $this->porNombre;
    }

    /**
     * El equipo nuestro que corresponde a un club de TM, o null.
     *
     * `$comoAparecio` devuelve 'id' o 'nombre' según por dónde salió: el que
     * salió por nombre es candidato a aprender el mapeo, el que salió por id ya
     * lo tiene.
     */
    public function resolver($tmId, $nombre, &$comoAparecio = null)
    {
        $comoAparecio = null;

        if ($tmId !== null && $tmId !== '' && isset($this->porId()[(string) $tmId])) {
            $comoAparecio = 'id';
            return $this->porId()[(string) $tmId];
        }

        foreach ($this->claves($nombre) as $k) {
            $mapa = $this->porNombre();

            if ($k !== '' && isset($mapa[$k]) && $mapa[$k] !== null) {
                $comoAparecio = 'nombre';
                return $mapa[$k];
            }
        }

        return null;
    }

    /** Deja aprendido el mapeo para que la próxima salga por id. */
    public function guardar($tmClubId, $equipoId, $nombre, $origen)
    {
        // `origen` es varchar(20) y `nombre_tm` varchar(191). Un texto mas
        // largo no es un dato mejor: es un 500 en la cara del usuario en
        // medio de una pantalla que estaba andando. Se recorta y se sigue.
        $origen = mb_substr((string) $origen, 0, 20);
        $nombre = mb_substr((string) $nombre, 0, 191);

        DB::table('equipo_tm')->updateOrInsert(
            ['tm_club_id' => (string) $tmClubId],
            ['equipo_id' => (int) $equipoId, 'nombre_tm' => $nombre, 'origen' => $origen,
                'updated_at' => now(), 'created_at' => now()]
        );

        $this->porId = null;   // el mapa cacheado quedó viejo
    }

    /**
     * Claves normalizadas de un nombre de club.
     *
     * REGLA IMPORTANTE: si el nombre trae un paréntesis aclaratorio —"Sarmiento
     * (Junín)", "Central Córdoba (SdE)"— ese paréntesis es parte del nombre y
     * NO se descarta. Sin esta regla, "CA Sarmiento (Junín)" matchea contra un
     * "Sarmiento" cualquiera y termina creando partidos con el rival equivocado.
     */
    public function claves($nombre): array
    {
        $nombre = (string) $nombre;

        if (trim($nombre) === '') {
            return [];
        }

        $base = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nombre);
        if ($base === false) $base = $nombre;
        $base = mb_strtolower($base);

        // Los paréntesis se aplanan (pasan a ser texto), no se borran.
        $base = str_replace(['(', ')', '.', ','], ' ', $base);

        $claves = [
            $this->soloLetras($base),
            $this->soloLetras($this->quitarPrefijos($base)),
        ];

        return array_values(array_unique(array_filter($claves)));
    }

    private function quitarPrefijos($str)
    {
        $str = preg_replace('/\b(c\.?a\.?|a\.?a\.?|c\.?s\.?|c\.?d\.?|c\.?s\.?d\.?|a\.?c\.?|s\.?c\.?|f\.?c\.?|c\.?f\.?|c\.?b\.?|s\.?a\.?d\.?)\b/u', ' ', $str);
        $str = preg_replace('/\b(club|atletico|atletica|deportivo|deportiva|deportes|asociacion|association|sportivo|sporting|social|futbol|football|de|del|la|el)\b/u', ' ', $str);
        return $str;
    }

    private function soloLetras($str)
    {
        return (string) preg_replace('/[^\p{L}\p{N}]+/u', '', (string) $str);
    }
}
