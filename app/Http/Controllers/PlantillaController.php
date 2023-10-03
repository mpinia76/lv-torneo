<?php

namespace App\Http\Controllers;

use App\Grupo;
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
        $grupo_id= $request->query('grupoId');
        //$nombre = $request->get('buscarpor');
        if ($request->has('buscarpor')){
            $nombre = $request->get('buscarpor');

            $request->session()->put('nombre_filtro_plantilla', $request->get('buscarpor'));

        }
        else{
            $nombre = $request->session()->get('nombre_filtro_plantilla');

        }
        $grupo=Grupo::findOrFail($grupo_id);


        $plantillas=Plantilla::with('equipo')->where('grupo_id','=',"$grupo_id")->whereHas('equipo', function($query) use ($nombre){
            if($nombre){
                $query->where('nombre', 'LIKE', "%$nombre%");
            }
        })->get()->sortBy(function($query){
            return $query->equipo->nombre;
        });





        //dd($plantillas);

        return view('plantillas.index', compact('plantillas','grupo'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        $grupo_id= $request->query('grupoId');
        $grupo=Grupo::findOrFail($grupo_id);

        $jugadors = Jugador::SELECT('jugadors.*','personas.nombre','personas.apellido','personas.nacimiento','personas.fallecimiento','personas.foto')->Join('personas','personas.id','=','jugadors.persona_id')->orderBy('personas.apellido', 'asc')->orderBy('personas.nombre', 'asc')->get();
        $jugadors = $jugadors->pluck('full_name', 'id')->prepend('','');

        $equipos = Equipo::orderBy('nombre', 'asc')->get();
        $equipos = $equipos->pluck('nombre', 'id')->prepend('','');


        /*$tecnicos = Tecnico::orderBy('apellido', 'asc')->orderBy('nombre', 'asc')->get();
        $tecnicos = $tecnicos->pluck('full_name', 'id')->prepend('','');*/
        //
        return view('plantillas.create', compact('grupo','jugadors','equipos'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->validate($request,[ 'equipo_id'=>'required',  'grupo_id'=>'required']);
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

        return redirect()->route('plantillas.index', array('grupoId' => $plantilla->grupo->id))->with($respuestaID,$respuestaMSJ);
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

    public function search(Request $request)
    {
        /*$cities = City::where('name', 'LIKE', '%'.$request->input('term', '').'%')
            ->get(['id', 'name as text']);*/
        $search = $request->search;
        $jugadors = Jugador::SELECT('jugadors.*','personas.nombre','personas.apellido','personas.nacimiento','personas.fallecimiento','personas.foto')->Join('personas','personas.id','=','jugadors.persona_id')->where('apellido', 'LIKE', '%'.$search.'%')->orwhere('nombre', 'LIKE', '%'.$search.'%')->orderBy('personas.apellido', 'asc')->orderBy('personas.nombre', 'asc')->get();
        //$jugadors = $jugadors->pluck('persona.full_name_age', 'id')->prepend('','');

        $response = array();
        foreach($jugadors as $jugador){
            $response[] = array(
                "id"=>$jugador->id,
                "text"=>$jugador->full_name_age_tipo
            );
        }

        //return ['results' => $jugadors];
        return response()->json($response);
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

        $grupo=Grupo::findOrFail($plantilla->grupo->id);

        $plantillaJugadors = PlantillaJugador::where('plantilla_id','=',"$id")->orderBy('dorsal','asc')->get();

        $arrplantillajugador='';
        foreach ($plantillaJugadors as $plantillaJugador){
            $arrplantillajugador .=$plantillaJugador->jugador->id.',';
        }

        //$jugadors = Jugador::orderBy('apellido', 'asc')->orderBy('nombre', 'asc')->get();
        //$jugadors = Jugador::SELECT('jugadors.*','personas.nombre','personas.apellido','personas.nacimiento','personas.fallecimiento','personas.foto')->Join('personas','personas.id','=','jugadors.persona_id')->orderBy('personas.apellido', 'asc')->orderBy('personas.nombre', 'asc')->get();

        $jugadors = Jugador::SELECT('jugadors.*','personas.nombre','personas.apellido','personas.nacimiento','personas.fallecimiento','personas.foto')->Join('personas','personas.id','=','jugadors.persona_id')->wherein('jugadors.id',explode(',', $arrplantillajugador))->orderBy('personas.apellido', 'asc')->orderBy('personas.nombre', 'asc')->get();

        $jugadors = $jugadors->pluck('persona.full_name_age', 'id')->prepend('','');


        $equipos = Equipo::orderBy('nombre', 'asc')->get();
        $equipos = $equipos->pluck('nombre', 'id')->prepend('','');

        /*$plantillaTecnicos = PlantillaTecnico::where('plantilla_id','=',"$id")->get();


        $tecnicos = Tecnico::orderBy('apellido', 'asc')->orderBy('nombre', 'asc')->get();
        $tecnicos = $tecnicos->pluck('full_name', 'id')->prepend('','');*/

        return view('plantillas.edit', compact('jugadors','grupo','equipos','plantilla', 'plantillaJugadors'));
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
        $this->validate($request,[ 'equipo_id'=>'required',  'grupo_id'=>'required']);
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


        return redirect()->route('plantillas.index', array('grupoId' => $plantilla->grupo->id))->with($respuestaID,$respuestaMSJ);
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

    public function import(Request $request)
    {
        $grupo_id= $request->query('grupoId');
        $grupo=Grupo::findOrFail($grupo_id);

        $equipos = Equipo::orderBy('nombre', 'asc')->get();
        $equipos = $equipos->pluck('nombre', 'id')->prepend('','');


        $torneos=Torneo:: orderBy('year','DESC')->get();
        $torneosAnteriores = $torneos->pluck('full_name', 'id')->prepend('','');

        //
        return view('plantillas.import', compact('grupo','equipos','torneosAnteriores'));
    }

    public function importprocess(Request $request)
    {

        set_time_limit(0);

        $this->validate($request,[ 'equipo_id'=>'required',  'torneo_id'=>'required']);

        $grupo_id = $request->get('grupo_id');
        $equipo_id = $request->get('equipo_id');
        $torneo_id = $request->get('torneo_id');

        $grupos = Grupo::where('torneo_id', '=',$torneo_id)->get();
        $arrgrupos='';
        foreach ($grupos as $grupo){
            $arrgrupos .=$grupo->id.',';
        }




        $plantillas = Plantilla::wherein('grupo_id',explode(',', $arrgrupos))->wherein('equipo_id',[$equipo_id])->get();
        $arrPlantillas='';
        foreach ($plantillas as $plantilla){
            $arrPlantillas .=$plantilla->id.',';
        }

        $plantillaJugadors = PlantillaJugador::wherein('plantilla_id',explode(',', $arrPlantillas))->get();

        DB::beginTransaction();
        $ok=1;
        try {
            $data1=array(
                'equipo_id'=>$equipo_id,
                'grupo_id'=>$grupo_id
            );
            $plantilla = plantilla::create($data1);

            $lastid=$plantilla->id;

            foreach ($plantillaJugadors as $plantillaJugador){

                    $data2=array(
                        'plantilla_id'=>$lastid,
                        'jugador_id'=>$plantillaJugador->jugador->id,
                        'dorsal'=>$plantillaJugador->dorsal
                    );
                    try {
                        PlantillaJugador::create($data2);
                    }catch(QueryException $ex){
                        $error = $ex->getMessage();
                        $ok=0;
                        continue;
                    }
                }


        }catch(Exception $e){
            $error =  $ex->getMessage();
            $ok=0;
        }








        if ($ok){



            DB::commit();
            $respuestaID='success';
            $respuestaMSJ='Importación exitosa. (ver log)';
        }
        else{
            DB::rollback();
            $respuestaID='error';
            $respuestaMSJ=$error;
        }

        //
        return redirect()->route('plantillas.index', array('grupoId' => $grupo_id))->with($respuestaID,$respuestaMSJ);
    }

    /*
  AJAX request
  */
    public function getJugadors(Request $request){

        $search = $request->search;





        $jugadors = Jugador::orderBy('apellido', 'asc')->orderBy('nombre', 'asc')->get();
        $jugadors = $jugadors->pluck('full_name', 'id')->prepend('','');
        if($search == ''){
            $jugadors = Jugador::orderBy('apellido', 'asc')->orderBy('nombre', 'asc')->limit(5)->get();
        }else{
            $jugadors = Jugador::orderBy('apellido', 'asc')->orderBy('nombre', 'asc')->where('name', 'like', '%' .$search . '%')->limit(5)->get();
        }

        $response = array();
        foreach($jugadors as $jugador){
            $response[] = array(
                "id"=>$jugador->id,
                "text"=>$jugador->name
            );
        }

        return response()->json($response);
    }
}
