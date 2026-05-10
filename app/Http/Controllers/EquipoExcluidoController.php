<?php

namespace App\Http\Controllers;

use App\EquipoExcluido;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EquipoExcluidoController extends Controller
{
    /**
     * Show the index view.
     */
    public function index()
    {
        return view('competencias_excluidas.index');
    }

    /**
     * Return all rules as JSON for the AJAX table.
     */
    public function listar()
    {
        $items = CompetenciaExcluida::orderBy('activo', 'desc')
            ->orderBy('patron', 'asc')
            ->get();

        return response()->json($items);
    }

    /**
     * Store a new rule.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'patron'     => 'required|string|max:255|unique:competencias_excluidas,patron',
            'tipo_match' => 'required|in:exacto,contiene,regex',
            'motivo'     => 'nullable|string|max:255',
            'activo'     => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok'     => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $item = CompetenciaExcluida::create([
            'patron'     => trim($request->patron),
            'tipo_match' => $request->tipo_match,
            'motivo'     => $request->motivo,
            'activo'     => $request->has('activo') ? (bool) $request->activo : true,
        ]);

        return response()->json(['ok' => true, 'item' => $item]);
    }

    /**
     * Update an existing rule.
     */
    public function update(Request $request, $id)
    {
        $item = CompetenciaExcluida::find($id);

        if (!$item) {
            return response()->json(['ok' => false, 'msg' => 'No encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            'patron'     => 'required|string|max:255|unique:competencias_excluidas,patron,' . $id,
            'tipo_match' => 'required|in:exacto,contiene,regex',
            'motivo'     => 'nullable|string|max:255',
            'activo'     => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok'     => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $item->patron     = trim($request->patron);
        $item->tipo_match = $request->tipo_match;
        $item->motivo     = $request->motivo;
        $item->activo     = $request->has('activo') ? (bool) $request->activo : false;
        $item->save();

        return response()->json(['ok' => true, 'item' => $item]);
    }

    /**
     * Toggle active flag (quick switch from the table).
     */
    public function toggle($id)
    {
        $item = CompetenciaExcluida::find($id);

        if (!$item) {
            return response()->json(['ok' => false], 404);
        }

        $item->activo = !$item->activo;
        $item->save();

        return response()->json(['ok' => true, 'activo' => $item->activo]);
    }

    /**
     * Delete a rule.
     */
    public function destroy($id)
    {
        $item = CompetenciaExcluida::find($id);

        if (!$item) {
            return response()->json(['ok' => false], 404);
        }

        $item->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Test a single name against the active rules.
     */
    public function probar(Request $request)
    {
        $nombre = trim($request->nombre);

        if (!$nombre) {
            return response()->json(['ok' => false, 'msg' => 'Falta el nombre']);
        }

        return response()->json([
            'ok'       => true,
            'nombre'   => $nombre,
            'excluido' => CompetenciaExcluida::debeExcluir($nombre),
        ]);
    }

    /**
     * Quick add from the scraper UI: stores the competition name as a 'contiene' rule,
     * stripping the trailing year so the pattern is reusable across seasons.
     */
    public function excluirRapido(Request $request)
    {
        $nombre = trim($request->nombre ?? '');
        if (!$nombre) {
            return response()->json(['ok' => false, 'msg' => 'Nombre vacío']);
        }

        $existe = \App\EquipoExcluido::where('nombre', $nombre)->first();

        if ($existe) {
            return response()->json([
                'ok' => true,
                'creado' => false,
                'nombre' => $nombre,
            ]);
        }

        \App\EquipoExcluido::create(['nombre' => $nombre]);

        return response()->json([
            'ok' => true,
            'creado' => true,
            'nombre' => $nombre,
        ]);
    }
}
