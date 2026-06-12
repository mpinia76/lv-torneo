<?php

namespace App\Http\Controllers;

use App\JugadorEstadisticaManual;
use App\Jugador;
use App\Equipo;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use DB;

class JugadorEstadisticaManualController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($jugadorId)
    {

    }

    public function indexPorJugador($jugadorId)
    {
        $jugador = Jugador::findOrFail($jugadorId);

        $stats = JugadorEstadisticaManual::with('equipo')
            ->where('jugador_id', $jugadorId)
            ->paginate(20);

        return view('jugador_estadisticas.index', compact('stats', 'jugador'));
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function createPorJugador($jugadorId)
    {
        $jugador = Jugador::findOrFail($jugadorId);
        $equipos = Equipo::orderBy('nombre', 'asc')->get();
        $equipos = $equipos->pluck('nombre', 'id')->prepend('','');

        return view('jugador_estadisticas.create', compact('jugador', 'equipos'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if ($files = $request->file('escudoTmp')) {
            $image = $request->file('escudoTmp');
            $name = time().'.'.$image->getClientOriginalExtension();
            $destinationPath = public_path('/images');
            $image->move($destinationPath, $name);



            $request->merge(['torneo_logo' => $name]);
        }
        DB::beginTransaction();
        $ok=1;
        try {
            JugadorEstadisticaManual::create($request->all());
        }catch(QueryException $ex){
            //if email or phone exist before in db redirect with error messages

            $ok=0;
            $errorCode = $ex->errorInfo[1];

            if ($errorCode == 1062) {
                $error='Ya tiene estadísticas para ese equipo en ese torneo';
            }
            else{
                $error = $ex->getMessage();
            }
        }
        if ($ok){
            DB::commit();
            $respuestaID='success';
            $respuestaMSJ='Registro creado satisfactoriamente';
        }
        else{
            DB::rollback();
            $respuestaID='error';
            $respuestaMSJ=$error;
        }


        return redirect()->route('jugador-estadisticas.indexPorJugador', $request->jugador_id)->with($respuestaID,$respuestaMSJ);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\JugadorEstadisticaManual  $jugadorEstadisticaManual
     * @return \Illuminate\Http\Response
     */
    public function show(JugadorEstadisticaManual $jugadorEstadisticaManual)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\JugadorEstadisticaManual  $jugadorEstadisticaManual
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $stat = JugadorEstadisticaManual::findOrFail($id);
        $jugador = $stat->jugador;
        $equipos = Equipo::orderBy('nombre', 'asc')->get();
        $equipos = $equipos->pluck('nombre', 'id')->prepend('','');

        return view('jugador_estadisticas.edit', compact('stat', 'jugador', 'equipos'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\JugadorEstadisticaManual  $jugadorEstadisticaManual
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $stat = JugadorEstadisticaManual::findOrFail($id);


        if ($files = $request->file('escudoTmp')) {
            $image = $request->file('escudoTmp');
            $name = time().'.'.$image->getClientOriginalExtension();
            $destinationPath = public_path('/images');
            $image->move($destinationPath, $name);



            $request->merge(['torneo_logo' => $name]);
        }
        DB::beginTransaction();
        $ok=1;
        try {
            $stat->update($request->all());
        }catch(QueryException $ex){
            //if email or phone exist before in db redirect with error messages

            $ok=0;
            $errorCode = $ex->errorInfo[1];

            if ($errorCode == 1062) {
                $error='Ya tiene estadísticas para ese equipo en ese torneo';
            }
            else{
                $error = $ex->getMessage();
            }
        }
        if ($ok){
            DB::commit();
            $respuestaID='success';
            $respuestaMSJ='Registro modificado satisfactoriamente';
        }
        else{
            DB::rollback();
            $respuestaID='error';
            $respuestaMSJ=$error;
        }

        return redirect()->route('jugador-estadisticas.indexPorJugador', $request->jugador_id)->with($respuestaID,$respuestaMSJ);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\JugadorEstadisticaManual  $jugadorEstadisticaManual
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        JugadorEstadisticaManual::destroy($id);
        return back();
    }

    // ============================================================================
//  Add this method to JugadorEstadisticaManualController.
//  Reads multipart FormData (torneos[i][campo] + torneos[i][logo_file]),
//  scopes everything to jugador_id, skips duplicates (1062) without aborting
//  the batch, and reports the per-row outcome so the UI can remove only the
//  rows that were actually saved.
// ============================================================================

    /**
     * Store several scraped tournaments at once (from a single scrape).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeMasivo(Request $request)
    {
        $jugadorId = $request->input('jugador_id');
        $torneos   = $request->input('torneos', []);

        // Whitelist of stat fields we accept from the client payload.
        // Aligned with the model $fillable. torneo_logo is handled separately (file upload).
        $campos = [
            'equipo_id', 'torneo_nombre', 'tipo', 'ambito',
            'posicion', 'partidos',
            'goles_cabeza', 'goles_jugada', 'goles_penal', 'goles_tiro_libre', 'goles_en_contra',
            'amarillas', 'rojas',
            'penales_errados', 'penales_atajados',
            'goles_recibidos', 'vallas_invictas', 'penales_atajo',
        ];

        $guardados = 0;
        $salteados = 0;
        $errores   = [];
        // Per-row outcome keyed by the client index, so the UI removes only saved cards.
        $resultados = [];

        foreach ($torneos as $index => $torneo) {
            // Always scope to the current player. Never trust a jugador_id coming inside the row.
            $data = ['jugador_id' => $jugadorId];

            foreach ($campos as $campo) {
                $data[$campo] = $torneo[$campo] ?? null;
            }

            // Skip empty/garbage rows (no team and no tournament name).
            if (empty($data['equipo_id']) && empty($data['torneo_nombre'])) {
                $salteados++;
                $resultados[] = ['i' => $index, 'ok' => false, 'motivo' => 'vacio'];
                continue;
            }

            // Per-row logo. Priority: an uploaded file wins; otherwise fall back to
            // the preloaded logo name from the scraper map/reuse (torneo_logo).
            $data['torneo_logo'] = null;
            $logoFile = $request->file("torneos.$index.logo_file");

            if ($logoFile) {
                // time()_uniqid() avoids collisions when several logos are saved in the same second.
                $name = time() . '_' . uniqid() . '.' . $logoFile->getClientOriginalExtension();
                $logoFile->move(public_path('/images'), $name);
                $data['torneo_logo'] = $name;
            } elseif (!empty($torneo['torneo_logo'])) {
                // Preloaded logo (already a file in public/images) — no upload needed.
                $data['torneo_logo'] = $torneo['torneo_logo'];
            }

            try {
                JugadorEstadisticaManual::create($data);
                $guardados++;
                $resultados[] = ['i' => $index, 'ok' => true];
            } catch (QueryException $ex) {
                $errorCode = $ex->errorInfo[1] ?? null;

                if ($errorCode == 1062) {
                    // Duplicate for this player/team/tournament: skip, keep going.
                    $salteados++;
                    $resultados[] = ['i' => $index, 'ok' => false, 'motivo' => 'duplicado'];
                } else {
                    $salteados++;
                    $errores[] = ($data['torneo_nombre'] ?? '?') . ': ' . $ex->getMessage();
                    $resultados[] = ['i' => $index, 'ok' => false, 'motivo' => 'error'];
                }
            }
        }

        return response()->json([
            'ok'         => true,
            'guardados'  => $guardados,
            'salteados'  => $salteados,
            'errores'    => $errores,
            'resultados' => $resultados,
        ]);
    }
}
