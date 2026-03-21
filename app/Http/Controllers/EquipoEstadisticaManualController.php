<?php

namespace App\Http\Controllers;

use App\EquipoEstadisticaManual;
use App\Equipo;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use DB;

class EquipoEstadisticaManualController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($EquipoId)
    {

    }

    public function indexPorEquipo($equipoId)
    {
        $equipo = Equipo::findOrFail($equipoId);

        $stats = EquipoEstadisticaManual::with('equipo')
            ->where('equipo_id', $equipoId)
            ->paginate(20);

        return view('equipo_estadisticas.index', compact('stats', 'equipo'));
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function createPorEquipo($equipoId)
    {
        $equipo = Equipo::findOrFail($equipoId);



        return view('equipo_estadisticas.create', compact('equipo') );
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
            EquipoEstadisticaManual::create($request->all());
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


        return redirect()->route('equipo-estadisticas.indexPorEquipo', $request->equipo_id)->with($respuestaID,$respuestaMSJ);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\EquipoEstadisticaManual  $equipoEstadisticaManual
     * @return \Illuminate\Http\Response
     */
    public function show(EquipoEstadisticaManual $equipoEstadisticaManual)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\EquipoEstadisticaManual  $equipoEstadisticaManual
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $stat = EquipoEstadisticaManual::findOrFail($id);
        $equipo = $stat->equipo;


        return view('equipo_estadisticas.edit', compact('stat', 'equipo'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\EquipoEstadisticaManual  $equipoEstadisticaManual
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $stat = EquipoEstadisticaManual::findOrFail($id);


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

        return redirect()->route('equipo-estadisticas.indexPorEquipo', $request->equipo_id)->with($respuestaID,$respuestaMSJ);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\EquipoEstadisticaManual  $equipoEstadisticaManual
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        EquipoEstadisticaManual::destroy($id);
        return back();
    }
}
