<?php

namespace App\Http\Controllers;

use App\Services\ControlTorneos;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Control de torneos: equipos de más, equipos de menos, y los completos que
 * todavía no tienen guardadas las posiciones finales. Solo lee.
 */
class ControlTorneoController extends Controller
{
    const POR_PAGINA = 50;

    public function index(Request $request, ControlTorneos $servicio)
    {
        $lista = $request->input('lista');
        if (!array_key_exists($lista, ControlTorneos::LISTAS)) {
            $lista = 'sobran';
        }

        $filtros = [
            'year'      => $request->input('year'),
            'tipo'      => $request->input('tipo'),
            'q'         => trim((string) $request->input('q')),
            'parciales' => $request->input('parciales') ? 1 : null,
            'estado'    => $request->input('estado'),
        ];

        $listas = $servicio->clasificar($servicio->torneos($filtros));

        // "En curso" = tiene partidos sin resultado. Solo tiene sentido en la
        // lista de posiciones: un torneo que se está jugando no puede tener
        // tabla final todavía.
        if ($lista === 'sin_posiciones' && in_array($filtros['estado'], ['terminados', 'en_curso'], true)) {
            $enCurso = $filtros['estado'] === 'en_curso';
            $listas['sin_posiciones'] = array_values(array_filter($listas['sin_posiciones'], function ($t) use ($enCurso) {
                return $enCurso ? $t->sin_resultado > 0 : ((int) $t->sin_resultado === 0 && $t->partidos > 0);
            }));
        }

        $conteos = array_map('count', $listas);

        $page  = max(1, (int) $request->input('page', 1));
        $todas = $listas[$lista];
        $filas = new LengthAwarePaginator(
            array_slice($todas, ($page - 1) * self::POR_PAGINA, self::POR_PAGINA),
            count($todas),
            self::POR_PAGINA,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $detalle = in_array($lista, ['sin_posiciones', 'partidos_faltan'], true)
            ? []
            : $servicio->detalle(array_map(function ($t) { return $t->id; }, $filas->items()));

        return view('controles.torneos', [
            'listas'      => ControlTorneos::LISTAS,
            'lista'       => $lista,
            'conteos'     => $conteos,
            'filas'       => $filas,
            'detalle'     => $detalle,
            'filtros'     => $filtros,
            'anios'       => $servicio->anios(),
            'tiposTorneo' => $servicio->tipos(),
        ]);
    }
}
