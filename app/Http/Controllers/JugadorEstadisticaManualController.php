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
}
