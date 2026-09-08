<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Contador de consultas, para medir antes de optimizar.
 *
 * Se activa SOLO con ?perf=1 en la URL y SOLO con sesion iniciada: un visitante
 * anonimo nunca paga el costo del log ni ve el resultado.
 *
 * Uso: abrir cualquier pagina con &perf=1 y mirar el final del HTML (Ctrl+U).
 * Deja un comentario con el total de consultas, el tiempo que se fue en la base,
 * el tiempo total de la pagina y las 5 consultas mas lentas.
 *
 * Es temporal: cuando terminemos de optimizar se saca de Kernel.php.
 */
class PerfDebug
{
    public function handle($request, Closure $next)
    {
        $activo = $request->query('perf') && Auth::check();

        if (!$activo) {
            return $next($request);
        }

        $inicio = microtime(true);
        DB::enableQueryLog();

        $response = $next($request);

        $log      = DB::getQueryLog();
        $consultas = count($log);
        $msBase   = 0;
        foreach ($log as $q) {
            $msBase += $q['time'];
        }
        $msTotal = round((microtime(true) - $inicio) * 1000);

        // Las 5 mas lentas, recortadas para que el comentario no sea ilegible.
        usort($log, function ($a, $b) {
            return $b['time'] <=> $a['time'];
        });
        $lentas = '';
        foreach (array_slice($log, 0, 5) as $q) {
            $sql = preg_replace('/\s+/', ' ', $q['query']);
            $lentas .= sprintf("\n  %7.2f ms  %s", $q['time'], substr($sql, 0, 160));
        }

        $resumen = sprintf(
            "\n<!-- PERF: %d consultas | %.0f ms en la base | %d ms la pagina entera\n  las 5 mas lentas:%s\n-->",
            $consultas,
            $msBase,
            $msTotal,
            $lentas
        );

        $contenido = $response->getContent();
        if (is_string($contenido) && stripos($contenido, '</html>') !== false) {
            $response->setContent($contenido . $resumen);
        }

        return $response;
    }
}
