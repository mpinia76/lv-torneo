<?php

namespace App\Http\Controllers;

use App\Services\MenuTorneos;
use Illuminate\Http\Request;

/**
 * Navegación pública de torneos por país / región.
 *
 *  - json():     lo que baja el menú «Torneos» la primera vez que se abre.
 *  - explorar(): la misma información como página común (/competiciones).
 *                Sirve sin JavaScript, para los buscadores y para compartir
 *                el link de un país.
 */
class MenuTorneosController extends Controller
{
    public function json(Request $request)
    {
        $datos = MenuTorneos::paraJson();

        // El menú pide la URL con ?v=<version>: si coincide, el navegador la
        // puede guardar un día entero (cambia de versión al tocar un torneo).
        $cacheable = $request->query('v') === $datos['v'];

        return response()
            ->json($datos, 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ->header('Cache-Control', $cacheable ? 'public, max-age=86400' : 'public, max-age=300');
    }

    public function explorar(Request $request)
    {
        $datos = MenuTorneos::zonas();
        $zonas = $datos['zonas'];

        $clave = (string) $request->query('zona', '');
        if (!isset($zonas[$clave])) {
            // Sin zona pedida (o una que ya no existe): la primera, que es la local.
            reset($zonas);
            $clave = key($zonas);
        }

        return view('torneos.explorar', [
            'zonas'      => $zonas,
            'zonaActual' => $clave ? $zonas[$clave] : null,
        ]);
    }
}
