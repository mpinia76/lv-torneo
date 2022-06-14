<?php

namespace App\Http\Controllers;

use App\Gol;
use App\Grupo;
use App\Jugador;
use App\Partido;
use App\Plantilla;
use App\PlantillaJugador;
use Illuminate\Http\Request;
use DB;

class GolController extends Controller
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

        $goles=Gol::where('partido_id','=',"$partido_id")->orderBy('minuto','ASC')->get();


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

        /*$jugadors = PlantillaJugador::wherein('plantilla_id',explode(',', $arrPlantillas))->with('jugador')->get();

        $jugadors = $jugadors->pluck('jugador.full_name','jugador_id')->sortBy('jugador.apellido')->prepend('','');*/

        $jugadors = Jugador::SELECT('jugadors.*','personas.nombre','personas.apellido','personas.nacimiento','personas.fallecimiento','personas.foto')->Join('plantilla_jugadors','plantilla_jugadors.jugador_id','=','jugadors.id')->Join('personas','personas.id','=','jugadors.persona_id')->wherein('plantilla_id',explode(',', $arrPlantillas))->distinct()->get();

        $jugadors = $jugadors->pluck('persona.full_name','id')->sortBy('apellido')->prepend('','');


        //dd($partido->fecha->grupo->torneo->nombre);
        return view('goles.index', compact('goles','partido','jugadors'));
        //
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
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $partido_id)
    {

        $golesTotales= intval($request->totalGoles);
        $partido=Partido::findOrFail($partido_id);
        DB::beginTransaction();
        $noBorrar='';
        $ok=1;
        if (!empty($request->jugador)){

            if (count($request->jugador) > $golesTotales){
                $ok=0;
                $error='No se pueden cargar más goles que los del resultado';
            }
            elseif(count($request->jugador) > 0)
            {
                if ($request->gol_id){
                    foreach ($request->gol_id as $id){
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
                        if (!empty($request->gol_id[$item])){
                            $data2['id']=$request->gol_id[$item];
                            $gol=Gol::find($request->gol_id[$item]);
                            $gol->update($data2);
                        }
                        else{
                            $gol=Gol::create($data2);
                        }
                        $noBorrar .=$gol->id.',';
                    }catch(QueryException $ex){
                        $error = $ex->getMessage();
                        $ok=0;
                        continue;
                    }

                }

            }
        }

        try {
            Gol::where('partido_id',"$partido_id")->whereNotIn('id', explode(',', $noBorrar))->delete();


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
