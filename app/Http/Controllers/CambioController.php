<?php

namespace App\Http\Controllers;

use App\Cambio;
use App\Partido;
use App\Plantilla;
use App\PlantillaJugador;
use Illuminate\Http\Request;

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
        $plantillas = Plantilla::where('torneo_id','=',"$torneo_id")->wherein('equipo_id',[$partido->equipol->id,$partido->equipov->id])->get();
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
        if(count($request->jugador) > 0)
        {
            if($request->cambio_id){
                Cambio::where('partido_id',"$partido_id")->whereNotIn('id', $request->cambio_id)->delete();
            }

            foreach($request->jugador as $item=>$v){

                $data2=array(
                    'partido_id'=>$partido_id,
                    'jugador_id'=>$request->jugador[$item],
                    'minuto'=>$request->minuto[$item],
                    'tipo'=>$request->tipo[$item]
                );
                if (!empty($request->cambio_id[$item])){
                    $data2['id']=$request->cambio_id[$item];
                    $cambio=Cambio::find($request->cambio_id[$item]);
                    $cambio->update($data2);
                }
                else{
                    $cambio=Cambio::create($data2);
                }

            }
            $respuestaID='success';
            $respuestaMSJ='Registro creado satisfactoriamente';
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
