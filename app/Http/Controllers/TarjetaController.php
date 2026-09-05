<?php

namespace App\Http\Controllers;

use App\Grupo;
use App\Jugador;
use App\Tarjeta;
use App\Partido;
use App\Plantilla;
use App\PlantillaJugador;
use Illuminate\Http\Request;
use DB;

class TarjetaController extends Controller
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

            $tarjetas=Tarjeta::where('partido_id','=',"$partido_id")->orderBy('minuto','ASC')->orderBy('adicionado','ASC')->get();


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

            $jugadors = Jugador::SELECT('jugadors.*',DB::raw("CONCAT(personas.name, ' (',plantilla_jugadors.dorsal,')') as 'nombre_dorsal'"), 'personas.foto')->Join('plantilla_jugadors','plantilla_jugadors.jugador_id','=','jugadors.id')->Join('personas','personas.id','=','jugadors.persona_id')->wherein('plantilla_id',explode(',', $arrPlantillas))->distinct()->get();

            $jugadors = $jugadors->pluck('nombre_dorsal','id')->sortBy('apellido')->prepend('','');


            //dd($partido->fecha->grupo->torneo->nombre);
            return view('tarjetas.index', compact('tarjetas','partido','jugadors'));
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
        $partido=Partido::findOrFail($partido_id);
        DB::beginTransaction();
        $noBorrar='';
        $ok=1;
        if(!empty($request->jugador))
        {
            if($request->tarjeta_id){
                foreach ($request->tarjeta_id as $id){
                    $noBorrar .=$id.',';
                }

            }

            foreach($request->jugador as $item=>$v){

                $min = \App\Services\MinutoHelper::normalizar(
                    $request->minuto[$item] ?? null,
                    $request->adicionado[$item] ?? null
                );

                $data2=array(
                    'partido_id'=>$partido_id,
                    'jugador_id'=>$request->jugador[$item],
                    // El minuto son dos campos: el del reloj y el descuento. `normalizar()`
                    // guarda 0 como NULL y suma un descuento colgado de un minuto que no
                    // cierra ningún período («70+3» es el minuto 73).
                    'minuto'=>$min['minuto'],
                    'adicionado'=>$min['adicionado'],
                    'tipo'=>$request->tipo[$item]
                );
                try {
                    if (!empty($request->tarjeta_id[$item])){
                        $data2['id']=$request->tarjeta_id[$item];
                        $tarjeta=Tarjeta::find($request->tarjeta_id[$item]);
                        $tarjeta->update($data2);
                    }
                    else{
                        $tarjeta=Tarjeta::create($data2);
                    }
                    $noBorrar .=$tarjeta->id.',';
                }catch(QueryException $ex){
                    $error = $ex->getMessage();
                    $ok=0;
                    continue;
                }


            }

        }

        try {
            Tarjeta::where('partido_id',"$partido_id")->whereNotIn('id', explode(',', $noBorrar))->delete();

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
