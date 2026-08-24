<?php

namespace App\Http\Controllers;

use App\Arbitro;
use App\Equipo;
use App\Jugador;
use App\Tecnico;
use Illuminate\Http\Request;

/**
 * Buscador general del sitio público: equipos, jugadores, técnicos y árbitros
 * en una sola consulta desde la barra de navegación.
 */
class BuscadorController extends Controller
{
    /** Cuántos resultados se muestran por tipo. */
    const LIMITE = 20;

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $equipos    = collect();
        $jugadores  = collect();
        $tecnicos   = collect();
        $arbitros   = collect();
        $demasiadoCorto = mb_strlen($q) < 2;

        if (!$demasiadoCorto) {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';

            $equipos = Equipo::where('nombre', 'like', $like)
                ->orWhere('siglas', 'like', $like)
                ->orderBy('nombre')
                ->limit(self::LIMITE)
                ->get();

            $porPersona = function ($consulta) use ($like) {
                $consulta->where('name', 'like', $like)
                    ->orWhere('nombre', 'like', $like)
                    ->orWhere('apellido', 'like', $like)
                    ->orWhereRaw("CONCAT(COALESCE(nombre,''),' ',COALESCE(apellido,'')) like ?", [$like]);
            };

            $jugadores = Jugador::with('persona')
                ->whereHas('persona', $porPersona)
                ->limit(self::LIMITE)
                ->get()
                ->sortBy(function ($j) { return optional($j->persona)->name; })
                ->values();

            $tecnicos = Tecnico::with('persona')
                ->whereHas('persona', $porPersona)
                ->limit(self::LIMITE)
                ->get()
                ->sortBy(function ($t) { return optional($t->persona)->name; })
                ->values();

            $arbitros = Arbitro::with('persona')
                ->whereHas('persona', $porPersona)
                ->limit(self::LIMITE)
                ->get()
                ->sortBy(function ($a) { return optional($a->persona)->name; })
                ->values();
        }

        $total = $equipos->count() + $jugadores->count() + $tecnicos->count() + $arbitros->count();

        return view('buscar', compact('q', 'equipos', 'jugadores', 'tecnicos', 'arbitros', 'total', 'demasiadoCorto'));
    }
}
