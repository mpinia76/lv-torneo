<?php

namespace App\Http\Controllers;

use App\TecnicoEstadisticaManual;
use App\Tecnico;
use App\Equipo;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use DB;

class TecnicoEstadisticaManualController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($tecnicoId)
    {

    }

    public function indexPorTecnico($tecnicoId)
    {
        $tecnico = Tecnico::findOrFail($tecnicoId);

        $stats = TecnicoEstadisticaManual::with('equipo')
            ->where('tecnico_id', $tecnicoId)
            ->paginate(20);

        return view('tecnico_estadisticas.index', compact('stats', 'tecnico'));
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function createPorTecnico($tecnicoId)
    {
        $tecnico = Tecnico::findOrFail($tecnicoId);
        $equipos = Equipo::orderBy('nombre', 'asc')->get();
        $equipos = $equipos->pluck('nombre', 'id')->prepend('','');

        return view('tecnico_estadisticas.create', compact('tecnico', 'equipos'));
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
            TecnicoEstadisticaManual::create($request->all());
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


        return redirect()->route('tecnico-estadisticas.indexPorTecnico', $request->tecnico_id)->with($respuestaID,$respuestaMSJ);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\TecnicoEstadisticaManual  $tecnicoEstadisticaManual
     * @return \Illuminate\Http\Response
     */
    public function show(TecnicoEstadisticaManual $tecnicoEstadisticaManual)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\TecnicoEstadisticaManual  $tecnicoEstadisticaManual
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $stat = TecnicoEstadisticaManual::findOrFail($id);
        $tecnico = $stat->tecnico;
        $equipos = Equipo::orderBy('nombre', 'asc')->get();
        $equipos = $equipos->pluck('nombre', 'id')->prepend('','');

        return view('tecnico_estadisticas.edit', compact('stat', 'tecnico', 'equipos'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\TecnicoEstadisticaManual  $tecnicoEstadisticaManual
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $stat = TecnicoEstadisticaManual::findOrFail($id);


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

        return redirect()->route('tecnico-estadisticas.indexPorTecnico', $request->tecnico_id)->with($respuestaID,$respuestaMSJ);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\TecnicoEstadisticaManual  $tecnicoEstadisticaManual
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        TecnicoEstadisticaManual::destroy($id);
        return back();
    }
}
