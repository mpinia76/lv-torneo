<?php

namespace App\Http\Controllers;

use App\PlantillaJugador;
use App\PartidoTecnico;
use App\Torneo;
use App\Plantilla;
use App\Jugador;
use App\Tecnico;
use App\Equipo;
use Illuminate\Http\Request;

use Illuminate\Database\QueryException;

use DB;

class PlantillaController extends Controller
{

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $torneo_id= $request->query('torneoId');
        $nombre = $request->get('buscarpor');
        $torneo=Torneo::findOrFail($torneo_id);


        $plantillas=Plantilla::with('equipo')->where('torneo_id','=',"$torneo_id")->whereHas('equipo', function($query) use ($nombre){
            if($nombre){
                $query->where('nombre', 'LIKE', "%$nombre%");
            }
        })->get()->sortBy(function($query){
            return $query->equipo->nombre;
        });





        //dd($plantillas);

        return view('plantillas.index', compact('plantillas','torneo'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        $torneo_id= $request->query('torneoId');
        $torneo=Torneo::findOrFail($torneo_id);

        $jugadors = Jugador::orderBy('apellido', 'asc')->orderBy('nombre', 'asc')->get();
        $jugadors = $jugadors->pluck('full_name', 'id')->prepend('','');

        $equipos = Equipo::orderBy('nombre', 'asc')->get();
        $equipos = $equipos->pluck('nombre', 'id')->prepend('','');


        /*$tecnicos = Tecnico::orderBy('apellido', 'asc')->orderBy('nombre', 'asc')->get();
        $tecnicos = $tecnicos->pluck('full_name', 'id')->prepend('','');*/
        //
        return view('plantillas.create', compact('torneo','jugadors','equipos'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->validate($request,[ 'equipo_id'=>'required',  'torneo_id'=>'required']);
        DB::beginTransaction();
        $ok=1;
        try {
            $plantilla = plantilla::create($request->all());

            $lastid=$plantilla->id;
            if(count($request->jugador) > 0)
            {
                foreach($request->jugador as $item=>$v){

                    $data2=array(
                        'plantilla_id'=>$lastid,
                        'jugador_id'=>$request->jugador[$item],
                        'dorsal'=>$request->dorsal[$item]
                    );
                    try {
                        PlantillaJugador::create($data2);
                    }catch(QueryException $ex){
                        $error = $ex->getMessage();
                        $ok=0;
                        continue;
                    }
                }
            }
            /*if(count($request->tecnico) > 0)
            {
                foreach($request->tecnico as $item=>$v){

                    $data2=array(
                        'plantilla_id'=>$lastid,
                        'tecnico_id'=>$request->tecnico[$item]
                    );
                    try {
                        PlantillaTecnico::create($data2);
                    }catch(QueryException $ex){
                        $error = $ex->getMessage();
                        $ok=0;
                        continue;
                    }
                }
            }*/
        }catch(Exception $e){
            //if email or phone exist before in db redirect with error messages
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

        return redirect()->route('plantillas.index', array('torneoId' => $plantilla->torneo->id))->with($respuestaID,$respuestaMSJ);
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
        $plantilla=Plantilla::findOrFail($id);

        $torneo=Torneo::findOrFail($plantilla->torneo->id);

        $plantillaJugadors = PlantillaJugador::where('plantilla_id','=',"$id")->orderBy('dorsal','asc')->get();

        $jugadors = Jugador::orderBy('apellido', 'asc')->orderBy('nombre', 'asc')->get();

        $jugadors = $jugadors->pluck('full_name', 'id')->prepend('','');


        $equipos = Equipo::orderBy('nombre', 'asc')->get();
        $equipos = $equipos->pluck('nombre', 'id')->prepend('','');

        /*$plantillaTecnicos = PlantillaTecnico::where('plantilla_id','=',"$id")->get();


        $tecnicos = Tecnico::orderBy('apellido', 'asc')->orderBy('nombre', 'asc')->get();
        $tecnicos = $tecnicos->pluck('full_name', 'id')->prepend('','');*/

        return view('plantillas.edit', compact('jugadors','torneo','equipos','plantilla', 'plantillaJugadors'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //


        //dd($request->plantillajugador_id);
        $this->validate($request,[ 'equipo_id'=>'required',  'torneo_id'=>'required']);
        DB::beginTransaction();
        if($request->plantillajugador_id){
            PlantillaJugador::where('plantilla_id',"$id")->whereNotIn('id', $request->plantillajugador_id)->delete();
        }
        if($request->plantillatecnico_id)  {
            PartidoTecnico::where('plantilla_id',"$id")->whereNotIn('id', $request->plantillatecnico_id)->delete();
        }
        $ok=1;
        $plantilla=plantilla::find($id);
        try {
            $plantilla->update($request->all());
            //PlantillaJugador::where('plantilla_id', '=', "$id")->delete();
            if(count($request->jugador) > 0)
            {
                foreach($request->jugador as $item=>$v){

                    $data2=array(
                        'plantilla_id'=>$id,
                        'jugador_id'=>$request->jugador[$item],
                        'dorsal'=>$request->dorsal[$item]
                    );
                    try {
                        if (!empty($request->plantillajugador_id[$item])){
                            $data2['id']=$request->plantillajugador_id[$item];
                            $plantillaJugador=PlantillaJugador::find($request->plantillajugador_id[$item]);
                            $plantillaJugador->update($data2);
                        }
                        else{
                            PlantillaJugador::create($data2);
                        }



                    }catch(QueryException $ex){
                        $error = $ex->getMessage();
                        $ok=0;
                        continue;
                    }
                }
            }

        }catch(Exception $e){
            //if email or phone exist before in db redirect with error messages
            $ok=0;
        }
        if ($ok){
            DB::commit();



            $respuestaID='success';
            $respuestaMSJ='Registro actualizado satisfactoriamente';
        }
        else{
            DB::rollback();
            $respuestaID='error';
            $respuestaMSJ=$error;
        }


        return redirect()->route('plantillas.index', array('torneoId' => $plantilla->torneo->id))->with($respuestaID,$respuestaMSJ);
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
