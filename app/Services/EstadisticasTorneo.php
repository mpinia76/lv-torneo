<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Resumen estadístico de una temporada (un torneo): resultados, goles por
 * tipo y por minuto, rendimiento por equipo, rachas, récords y árbitros.
 *
 * cargar() hace unas pocas consultas y calcular() arma todo en PHP. Están
 * separados para poder probar el cálculo con datos inventados.
 *
 * Lo que sale del detalle de los partidos (tipo y minuto de gol, tarjetas,
 * árbitros) solo existe en los partidos con el detalle importado. Por eso cada
 * bloque lleva su "cobertura" y la vista lo muestra solo si alcanza.
 */
class EstadisticasTorneo
{
    /** Cobertura mínima (0..1) para mostrar un bloque que sale del detalle. */
    const COBERTURA_MINIMA = 0.5;

    /** Partidos mínimos para mostrar "el que marca primero…". */
    const MIN_PRIMER_GOL = 10;

    public static function cargar($torneoId)
    {
        $torneoId = (int) $torneoId;

        $partidos = DB::select("
            SELECT p.id, p.id AS partido_id, p.golesl, p.golesv, p.penalesl, p.penalesv, p.neutral, p.dia,
                   p.equipol_id, p.equipov_id, p.fecha_id, f.orden AS fecha_orden, f.numero,
                   e1.nombre AS local, e1.escudo AS fotoLocal, e1.pais AS paisLocal,
                   e2.nombre AS visitante, e2.escudo AS fotoVisitante, e2.pais AS paisVisitante
            FROM partidos p
            INNER JOIN fechas f ON p.fecha_id = f.id
            INNER JOIN grupos g ON f.grupo_id = g.id
            INNER JOIN equipos e1 ON p.equipol_id = e1.id
            INNER JOIN equipos e2 ON p.equipov_id = e2.id
            WHERE g.torneo_id = ?
              AND p.golesl IS NOT NULL AND p.golesv IS NOT NULL
            ORDER BY f.orden, p.dia, p.id
        ", [$torneoId]);

        if (!$partidos) {
            return self::calcular([], [], [], [], [], []);
        }

        $ids = implode(',', array_map(function ($p) { return (int) $p->id; }, $partidos));

        $conDetalle = [];
        foreach (DB::select("SELECT DISTINCT partido_id FROM alineacions WHERE partido_id IN ($ids)") as $a) {
            $conDetalle[(int) $a->partido_id] = true;
        }

        // Equipo de cada gol y cada tarjeta: el del jugador en la alineación de
        // ese partido (si no está en la alineación, queda sin equipo).
        $goles = DB::select("
            SELECT gl.partido_id, gl.tipo, gl.minuto, gl.adicionado, a.equipo_id
            FROM gols gl
            LEFT JOIN alineacions a ON a.partido_id = gl.partido_id AND a.jugador_id = gl.jugador_id
            WHERE gl.partido_id IN ($ids)
        ");

        $tarjetas = DB::select("
            SELECT t.partido_id, t.tipo, a.equipo_id
            FROM tarjetas t
            LEFT JOIN alineacions a ON a.partido_id = t.partido_id AND a.jugador_id = t.jugador_id
            WHERE t.partido_id IN ($ids)
        ");

        $arbitros = DB::select("
            SELECT pa.partido_id, ar.id AS arbitro_id, pe.name AS nombre, pe.foto
            FROM partido_arbitros pa
            INNER JOIN arbitros ar ON pa.arbitro_id = ar.id
            INNER JOIN personas pe ON ar.persona_id = pe.id
            WHERE pa.tipo = 'Principal' AND pa.partido_id IN ($ids)
        ");

        $equiposIds = [];
        foreach ($partidos as $p) {
            $equiposIds[(int) $p->equipol_id] = true;
            $equiposIds[(int) $p->equipov_id] = true;
        }
        $equipos = [];
        foreach (DB::select('SELECT id, nombre, escudo, pais FROM equipos WHERE id IN (' . implode(',', array_keys($equiposIds)) . ')') as $e) {
            $equipos[(int) $e->id] = $e;
        }

        return self::calcular($partidos, $conDetalle, $goles, $tarjetas, $arbitros, $equipos);
    }

    /** Resultado del partido para un equipo: 'g', 'e' o 'p' (los penales no cuentan: es empate). */
    protected static function resultado($p, $equipoId)
    {
        $gf = $equipoId == $p->equipol_id ? $p->golesl : $p->golesv;
        $gc = $equipoId == $p->equipol_id ? $p->golesv : $p->golesl;
        return $gf > $gc ? 'g' : ($gf < $gc ? 'p' : 'e');
    }

    protected static function esNeutral($p)
    {
        $n = $p->neutral;
        if (is_string($n)) {
            return in_array(mb_strtoupper(trim($n)), ['1', 'SI', 'SÍ', 'TRUE'], true);
        }
        return (bool) $n;
    }

    /** "En Contra", "en contra", "Tiro libre"… a una sola forma. */
    protected static function tipoGol($tipo)
    {
        $t = mb_strtolower(trim((string) $tipo));
        $mapa = [
            'jugada' => 'Jugada', 'cabeza' => 'Cabeza', 'penal' => 'Penal',
            'tiro libre' => 'Tiro Libre', 'olimpico' => 'Olímpico', 'olímpico' => 'Olímpico',
            'en contra' => 'En Contra',
        ];
        return isset($mapa[$t]) ? $mapa[$t] : ($t === '' ? null : ucfirst($t));
    }

    /** Minuto comparable: 45+2 va antes que 46. */
    protected static function momento($g)
    {
        return (int) $g->minuto + ((int) $g->adicionado) / 100;
    }

    public static function calcular(array $partidos, array $conDetalle, array $goles, array $tarjetas, array $arbitros, array $equipos)
    {
        $porId = [];
        foreach ($partidos as $p) {
            $porId[(int) $p->id] = $p;
        }

        // ---- Números generales y resultados --------------------------------
        $kpis = ['partidos' => count($partidos), 'goles' => 0, 'promedio' => 0, 'sin_goles' => 0,
                 'con_detalle' => 0, 'local' => null];
        $lev = ['l' => 0, 'e' => 0, 'v' => 0];
        $marcadores = [];

        foreach ($partidos as $p) {
            $tot = $p->golesl + $p->golesv;
            $kpis['goles'] += $tot;
            if ($tot == 0) $kpis['sin_goles']++;
            if (isset($conDetalle[(int) $p->id])) $kpis['con_detalle']++;

            if (!self::esNeutral($p)) {
                $lev[$p->golesl > $p->golesv ? 'l' : ($p->golesl < $p->golesv ? 'v' : 'e')]++;
            }

            // Marcador sin importar quién fue local: 0-1 y 1-0 son "1-0".
            $m = max($p->golesl, $p->golesv) . '-' . min($p->golesl, $p->golesv);
            $marcadores[$m] = ($marcadores[$m] ?? 0) + 1;
        }
        $kpis['promedio'] = $kpis['partidos'] ? $kpis['goles'] / $kpis['partidos'] : 0;
        $noNeutrales = $lev['l'] + $lev['e'] + $lev['v'];
        $kpis['local'] = $noNeutrales ? $lev['l'] * 100 / $noNeutrales : null;

        arsort($marcadores);
        $marcadoresTop = [];
        foreach (array_slice($marcadores, 0, 6, true) as $m => $n) {
            $marcadoresTop[] = ['marcador' => (string) $m, 'n' => $n, 'pct' => $n * 100 / max(1, $kpis['partidos'])];
        }

        // ---- Goles: tipo, minuto y quién marcó primero ---------------------
        $golesPorPartido = [];
        $tipos = [];
        $conTipo = 0;
        $conMinuto = 0;
        $franjas = ['1-15' => 0, '16-30' => 0, '31-45' => 0, '46-60' => 0, '61-75' => 0, '76-90' => 0, '91-120' => 0];

        foreach ($goles as $g) {
            if (!isset($porId[(int) $g->partido_id])) continue;
            $golesPorPartido[(int) $g->partido_id][] = $g;

            $tipo = self::tipoGol($g->tipo);
            if ($tipo) {
                $tipos[$tipo] = ($tipos[$tipo] ?? 0) + 1;
                $conTipo++;
            }

            if ($g->minuto !== null && $g->minuto !== '' && (int) $g->minuto > 0) {
                $conMinuto++;
                $min = (int) $g->minuto;
                // El descuento queda en su tiempo: 45+3 es primer tiempo, 90+5 es segundo.
                if ($min <= 15)       $franjas['1-15']++;
                elseif ($min <= 30)   $franjas['16-30']++;
                elseif ($min <= 45)   $franjas['31-45']++;
                elseif ($min <= 60)   $franjas['46-60']++;
                elseif ($min <= 75)   $franjas['61-75']++;
                elseif ($min <= 90)   $franjas['76-90']++;
                else                  $franjas['91-120']++;
            }
        }
        if ($franjas['91-120'] === 0) {
            unset($franjas['91-120']);
        }

        $totalGoles = max(1, $kpis['goles']);
        arsort($tipos);
        $tiposFilas = [];
        foreach ($tipos as $t => $n) {
            $tiposFilas[] = ['tipo' => $t, 'n' => $n, 'pct' => $n * 100 / max(1, $conTipo)];
        }
        $golesTipo = ($conTipo / $totalGoles >= self::COBERTURA_MINIMA && $tiposFilas)
            ? ['cobertura' => $conTipo * 100 / $totalGoles, 'filas' => $tiposFilas]
            : null;

        $golesMinuto = ($conMinuto / $totalGoles >= self::COBERTURA_MINIMA && $conMinuto > 0)
            ? ['cobertura' => $conMinuto * 100 / $totalGoles, 'franjas' => $franjas]
            : null;

        // Quién marcó primero: solo partidos con todos sus goles cargados, con
        // minuto y con equipo conocido.
        $primero = ['n' => 0, 'g' => 0, 'e' => 0, 'p' => 0];
        foreach ($golesPorPartido as $pid => $lista) {
            $p = $porId[$pid];
            if (count($lista) != $p->golesl + $p->golesv || count($lista) == 0) continue;
            $ok = true;
            foreach ($lista as $g) {
                if (!$g->minuto || !$g->equipo_id) { $ok = false; break; }
            }
            if (!$ok) continue;

            usort($lista, function ($a, $b) {
                return self::momento($a) <=> self::momento($b);
            });
            $g = $lista[0];
            $equipo = (int) $g->equipo_id;
            if (self::tipoGol($g->tipo) === 'En Contra') {
                $equipo = $equipo == $p->equipol_id ? (int) $p->equipov_id : (int) $p->equipol_id;
            }
            if ($equipo != $p->equipol_id && $equipo != $p->equipov_id) continue;

            $primero['n']++;
            $primero[self::resultado($p, $equipo)]++;
        }
        if ($primero['n'] < self::MIN_PRIMER_GOL) {
            $primero = null;
        }

        // ---- Tarjetas por partido y por equipo -----------------------------
        $tarjPartido = [];
        $tarjEquipo = [];
        foreach ($tarjetas as $t) {
            $pid = (int) $t->partido_id;
            if (!isset($porId[$pid])) continue;
            $clase = $t->tipo === 'Amarilla' ? 'am' : (in_array($t->tipo, ['Roja', 'Doble Amarilla'], true) ? 'ro' : null);
            if (!$clase) continue;
            $tarjPartido[$pid][$clase] = ($tarjPartido[$pid][$clase] ?? 0) + 1;
            if ($t->equipo_id) {
                $eid = (int) $t->equipo_id;
                $tarjEquipo[$eid][$clase] = ($tarjEquipo[$eid][$clase] ?? 0) + 1;
            }
        }
        $amarillas = 0;
        $rojas = 0;
        foreach ($tarjPartido as $pid => $c) {
            $amarillas += $c['am'] ?? 0;
            $rojas     += $c['ro'] ?? 0;
        }
        $kpis['tarjetas_pp'] = ($kpis['con_detalle'] && $kpis['con_detalle'] >= $kpis['partidos'] * self::COBERTURA_MINIMA)
            ? ($amarillas + $rojas) / $kpis['con_detalle']
            : null;

        // ---- Equipos --------------------------------------------------------
        $tabla = [];
        $secuencias = [];
        foreach ($partidos as $p) {
            foreach ([(int) $p->equipol_id => 'l', (int) $p->equipov_id => 'v'] as $eid => $lado) {
                if (!isset($tabla[$eid])) {
                    $e = $equipos[$eid] ?? null;
                    $tabla[$eid] = (object) [
                        'equipo_id' => $eid,
                        'nombre' => $e ? $e->nombre : '',
                        'escudo' => $e ? $e->escudo : null,
                        'pais' => $e ? $e->pais : null,
                        'pj' => 0, 'g' => 0, 'e' => 0, 'p' => 0, 'gf' => 0, 'gc' => 0,
                        'vallas' => 0, 'detalle' => 0,
                        'pts_local' => 0, 'pj_local' => 0, 'pts_visita' => 0, 'pj_visita' => 0,
                    ];
                }
                $f = $tabla[$eid];
                $gf = $lado === 'l' ? $p->golesl : $p->golesv;
                $gc = $lado === 'l' ? $p->golesv : $p->golesl;
                $r = self::resultado($p, $eid);
                $pts = $r === 'g' ? 3 : ($r === 'e' ? 1 : 0);

                $f->pj++;
                $f->$r++;
                $f->gf += $gf;
                $f->gc += $gc;
                if ($gc == 0) $f->vallas++;
                if (isset($conDetalle[(int) $p->id])) $f->detalle++;
                if (!self::esNeutral($p)) {
                    if ($lado === 'l') { $f->pts_local += $pts; $f->pj_local++; }
                    else               { $f->pts_visita += $pts; $f->pj_visita++; }
                }

                $secuencias[$eid][] = ['r' => $r, 'numero' => $p->numero, 'orden' => $p->fecha_orden];
            }
        }
        foreach ($tabla as $eid => $f) {
            $f->dif = $f->gf - $f->gc;
            $f->efect_local  = $f->pj_local  ? $f->pts_local  * 100 / ($f->pj_local * 3)  : null;
            $f->efect_visita = $f->pj_visita ? $f->pts_visita * 100 / ($f->pj_visita * 3) : null;
            $f->amarillas = $f->detalle ? ($tarjEquipo[$eid]['am'] ?? 0) : null;
            $f->rojas     = $f->detalle ? ($tarjEquipo[$eid]['ro'] ?? 0) : null;
        }
        $tabla = array_values($tabla);
        usort($tabla, function ($a, $b) {
            return [$b->g * 3 + $b->e, $b->dif, $b->gf, $a->nombre] <=> [$a->g * 3 + $a->e, $a->dif, $a->gf, $b->nombre];
        });

        // ---- Rachas (dentro del torneo el orden de las fechas es confiable) --
        $criterios = [
            'ganados'  => function ($r) { return $r === 'g'; },
            'invicto'  => function ($r) { return $r !== 'p'; },
            'sinGanar' => function ($r) { return $r !== 'g'; },
        ];
        $rachas = [];
        foreach ($criterios as $clave => $cumple) {
            $mejores = [];
            foreach ($secuencias as $eid => $seq) {
                $mejor = null;
                $actual = 0;
                $desde = null;
                foreach ($seq as $i => $s) {
                    if ($cumple($s['r'])) {
                        if ($actual === 0) $desde = $i;
                        $actual++;
                        if (!$mejor || $actual > $mejor['n']) {
                            $mejor = ['n' => $actual, 'desde' => $seq[$desde]['numero'], 'hasta' => $s['numero']];
                        }
                    } else {
                        $actual = 0;
                    }
                }
                if ($mejor && $mejor['n'] >= 2) {
                    $mejores[] = $mejor + ['equipo' => $tabla ? self::filaEquipo($tabla, $eid) : null];
                }
            }
            usort($mejores, function ($a, $b) { return $b['n'] <=> $a['n']; });
            $rachas[$clave] = array_slice($mejores, 0, 3);
        }

        // ---- Récords de partidos ------------------------------------------
        $orden = $partidos;
        usort($orden, function ($a, $b) {
            return [$b->golesl + $b->golesv, abs($b->golesl - $b->golesv)] <=> [$a->golesl + $a->golesv, abs($a->golesl - $a->golesv)];
        });
        $masGoles = array_slice($orden, 0, 5);
        usort($orden, function ($a, $b) {
            return [abs($b->golesl - $b->golesv), $b->golesl + $b->golesv] <=> [abs($a->golesl - $a->golesv), $a->golesl + $a->golesv];
        });
        $goleadas = array_values(array_filter(array_slice($orden, 0, 5), function ($p) {
            return abs($p->golesl - $p->golesv) >= 2;
        }));

        // ---- Goles por fecha ------------------------------------------------
        $porFecha = [];
        foreach ($partidos as $p) {
            $k = (string) $p->fecha_orden . '|' . $p->numero;
            if (!isset($porFecha[$k])) {
                $porFecha[$k] = ['numero' => $p->numero, 'orden' => $p->fecha_orden, 'partidos' => 0, 'goles' => 0];
            }
            $porFecha[$k]['partidos']++;
            $porFecha[$k]['goles'] += $p->golesl + $p->golesv;
        }
        $porFecha = array_values($porFecha);
        usort($porFecha, function ($a, $b) { return (float) $a['orden'] <=> (float) $b['orden']; });
        foreach ($porFecha as &$f) {
            $f['promedio'] = $f['partidos'] ? round($f['goles'] / $f['partidos'], 2) : 0;
        }
        unset($f);

        // ---- Árbitros (principal) -------------------------------------------
        $arb = [];
        foreach ($arbitros as $a) {
            $pid = (int) $a->partido_id;
            if (!isset($porId[$pid])) continue;
            $id = (int) $a->arbitro_id;
            if (!isset($arb[$id])) {
                $arb[$id] = (object) ['arbitro_id' => $id, 'nombre' => $a->nombre, 'foto' => $a->foto,
                                      'pj' => 0, 'detalle' => 0, 'am' => 0, 'ro' => 0, 'l' => 0, 'nn' => 0];
            }
            $r = $arb[$id];
            $p = $porId[$pid];
            $r->pj++;
            if (isset($conDetalle[$pid])) {
                $r->detalle++;
                $r->am += $tarjPartido[$pid]['am'] ?? 0;
                $r->ro += $tarjPartido[$pid]['ro'] ?? 0;
            }
            if (!self::esNeutral($p)) {
                $r->nn++;
                if ($p->golesl > $p->golesv) $r->l++;
            }
        }
        foreach ($arb as $r) {
            $r->am_pp = $r->detalle ? $r->am / $r->detalle : null;
            $r->ro_pp = $r->detalle ? $r->ro / $r->detalle : null;
            $r->local_pct = $r->nn ? $r->l * 100 / $r->nn : null;
        }
        $arb = array_values($arb);
        usort($arb, function ($a, $b) { return [$b->pj, $a->nombre] <=> [$a->pj, $b->nombre]; });

        return [
            'kpis'        => $kpis,
            'lev'         => $lev,
            'marcadores'  => $marcadoresTop,
            'primero'     => $primero,
            'golesTipo'   => $golesTipo,
            'golesMinuto' => $golesMinuto,
            'porFecha'    => $porFecha,
            'equipos'     => $tabla,
            'rachas'      => $rachas,
            'masGoles'    => $masGoles,
            'goleadas'    => $goleadas,
            'arbitros'    => $arb,
        ];
    }

    protected static function filaEquipo(array $tabla, $eid)
    {
        foreach ($tabla as $f) {
            if ($f->equipo_id == $eid) return $f;
        }
        return null;
    }
}
