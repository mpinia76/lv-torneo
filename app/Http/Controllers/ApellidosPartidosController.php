<?php

namespace App\Http\Controllers;

use App\Services\ApellidosPartidos;
use Illuminate\Http\Request;

/**
 * Pantalla para arreglar en lote los árbitros con el apellido doble partido.
 * El GET sólo muestra; el POST recalcula y guarda únicamente lo tildado.
 * Ver App\Services\ApellidosPartidos.
 */
class ApellidosPartidosController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $soloTm     = $request->query('origen', 'tm') !== 'todos';
        $conNombres = $request->query('ver') === 'nombres';

        $filas = ApellidosPartidos::candidatos($soloTm, $conNombres);

        $resumen = [];
        foreach ($filas as $f) {
            $resumen[$f['veredicto']] = (isset($resumen[$f['veredicto']]) ? $resumen[$f['veredicto']] : 0) + 1;
        }

        return view('jugadores.apellidosPartidos', [
            'filas'      => $filas,
            'resumen'    => $resumen,
            'soloTm'     => $soloTm,
            'conNombres' => $conNombres,
        ]);
    }

    public function aplicar(Request $request)
    {
        $ids   = (array) $request->input('ids', []);
        $antes = (array) $request->input('antes', []);

        if (!$ids) {
            return redirect()->back()->withErrors(['No tildaste ninguna ficha.']);
        }

        $r = ApellidosPartidos::aplicar($ids, $antes);

        $msg = 'Corregidas ' . $r['ok'] . ' fichas.';
        if ($r['salteados']) {
            $msg .= ' Salteadas ' . count($r['salteados']) . ': ' . implode(' · ', $r['salteados']);
        }

        return redirect()->back()->with('success', $msg);
    }
}
