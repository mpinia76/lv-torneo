<?php

namespace App\Http\Controllers;

use App\Cambio;
use App\Grupo;
use App\Partido;
use App\Plantilla;
use App\PlantillaJugador;
use Illuminate\Http\Request;
use DB;

class CambioController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $partido_id= $request->query('partidoId');

        $partido=Partido::findOrFail($partido_id);

        $cambios=Cambio::where('partido_id','=',"$partido_id")->orderBy('minuto','ASC')->get();


        $torneo_id = $partido->fecha->grupo->torneo->id;
        $grupos = Grupo::where('torneo_id', '=',$torneo_id)->get();
        $arrgrupos='';
        foreach ($grupos as $grupo){
            $arrgrupos .=$grupo->id.',';
        }
        $plantillas = Plantilla::wherein('grupo_id',explode(',', $arrgrupos))->wherein('equipo_id',[$partido->equipol->id,$partido->equipov->id])->get();
        $arrPlantillas='';
        foreach ($plantillas as $plantilla){
            $arrPlantillas .=$plantilla->id.',';
        }

        $jugadors = PlantillaJugador::wherein('plantilla_id',explode(',', $arrPlantillas))->with('jugador')->get();

        $jugadors = $jugadors->pluck('jugador.full_name','jugador_id')->prepend('','');


        //dd($partido->fecha->grupo->torneo->nombre);
        return view('cambios.index', compact('cambios','partido','jugadors'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $partido_id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $partido_id)
    {
        $partido=Partido::findOrFail($partido_id);
        DB::beginTransaction();
        $noBorrar='';
        $ok=1;
        if(!empty($request->jugador) )
        {
            if($request->cambio_id){

                foreach ($request->cambio_id as $id){
                    $noBorrar .=$id.',';
                }
            }

            foreach($request->jugador as $item=>$v){

                $data2=array(
                    'partido_id'=>$partido_id,
                    'jugador_id'=>$request->jugador[$item],
                    'minuto'=>$request->minuto[$item],
                    'tipo'=>$request->tipo[$item]
                );


                try {
                    if (!empty($request->cambio_id[$item])){
                        $data2['id']=$request->cambio_id[$item];
                        $cambio=Cambio::find($request->cambio_id[$item]);
                        $cambio->update($data2);
                    }
                    else{
                        $cambio=Cambio::create($data2);
                    }
                    $noBorrar .=$cambio->id.',';
                }catch(QueryException $ex){
                    $error = $ex->getMessage();
                    $ok=0;
                    continue;
                }
            }

        }

        try {
            Cambio::where('partido_id',"$partido_id")->whereNotIn('id', explode(',', $noBorrar))->delete();


        }
        catch(QueryException $ex){
            $error = $ex->getMessage();
            $ok=0;

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

        return redirect()->route('fechas.show', $partido->fecha->id)->with($respuestaID,$respuestaMSJ);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
