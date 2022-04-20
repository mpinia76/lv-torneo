<?php

namespace App\Http\Controllers;

use App\Equipo;
use App\Fecha;
use App\Grupo;
use App\Partido;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use DB;

class EquipoController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth')->except(['ver']);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {

        $nombre = $request->get('buscarpor');

        $equipos=Equipo::orwhere('nombre','like',"%$nombre%")->orWhere('siglas','like',"%$nombre%")->orWhere('socios','like',"%$nombre%")->orWhere(DB::raw('TIMESTAMPDIFF(YEAR,fundacion,CURDATE())'),'=',"$nombre")->orderBy('nombre','ASC')->paginate();


        //
        //$equipos=Equipo::orderBy('apellido','ASC')->paginate(2);
        //return view('Equipo.index',compact('equipos'));
        //$equipos = Equipo::all();
        return view('equipos.index', compact('equipos','equipos'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
        return view('equipos.create');
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
        //Log::info(print_r($request->file(), true));

        $this->validate($request,[ 'nombre'=>'required','escudo' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048']);


        if ($files = $request->file('escudo')) {
            $image = $request->file('escudo');
            $name = time().'.'.$image->getClientOriginalExtension();
            $destinationPath = public_path('/images');
            $image->move($destinationPath, $name);


            /*$destinationPath = 'public/image/'; // upload path
            $profileImage = date('YmdHis') . "." . $files->getClientOriginalExtension();
            Log::info($profileImage);
            $files->move($destinationPath, $profileImage);*/
            $insert['escudo'] = "$name";
        }


        $insert['nombre'] = $request->get('nombre');
        $insert['siglas'] = $request->get('siglas');
        $insert['socios'] = $request->get('socios');
        $insert['fundacion'] = $request->get('fundacion');
        $insert['estadio'] = $request->get('estadio');
        $insert['historia'] = $request->get('historia');


        $equipo = Equipo::create($insert);

        //$equipo = Equipo::create($request->all());

        return redirect()->route('equipos.index')->with('success','Registro creado satisfactoriamente');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $equipo=Equipo::findOrFail($id);
        return view('equipos.show', compact('equipo','equipo'));
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
        $equipo=equipo::findOrFail($id);

        return view('equipos.edit', compact('equipo','equipo'));
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
        $this->validate($request,[ 'nombre'=>'required','escudo' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048']);


        if ($files = $request->file('escudo')) {
            $image = $request->file('escudo');
            $name = time().'.'.$image->getClientOriginalExtension();
            $destinationPath = public_path('/images');
            $image->move($destinationPath, $name);


            /*$destinationPath = 'public/image/'; // upload path
            $profileImage = date('YmdHis') . "." . $files->getClientOriginalExtension();
            Log::info($profileImage);
            $files->move($destinationPath, $profileImage);*/
            $update['escudo'] = "$name";
        }


        $update['nombre'] = $request->get('nombre');
        $update['siglas'] = $request->get('siglas');
        $update['socios'] = $request->get('socios');
        $update['fundacion'] = $request->get('fundacion');
        $update['estadio'] = $request->get('estadio');
        $update['historia'] = $request->get('historia');



        $equipo=equipo::find($id);
        $equipo->update($update);

        return redirect()->route('equipos.index')->with('success','Registro actualizado satisfactoriamente');

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $equipo = Equipo::find($id);

        $equipo->delete();
        return redirect()->route('equipos.index')->with('success','Registro eliminado satisfactoriamente');
    }

    public function findEquipo(Request $req)
    {

        $nombre = $req->input('q');

        //$equipos=Equipo::where('nombre','like',"%$nombre%")->orWhere('siglas','like',"%$nombre%");

        $equipos=Equipo::where('nombre','like',"%$nombre%")->orWhere('siglas','like',"%$nombre%")->orderBy('nombre','ASC')->get();

        return $equipos;
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function ver(Request $request)
    {
        $id= $request->query('equipoId');
        $equipo=Equipo::findOrFail($id);


		$sql = 'SELECT torneos.id as idTorneo, CONCAT(torneos.nombre," ",torneos.year) AS nombreTorneo, "0" AS jugados, "0" AS ganados, "0" AS perdidos, "0" AS empatados, "0" AS favor, "0" AS contra, "0" AS puntaje, "0" as porcentaje
FROM torneos INNER JOIN grupos ON torneos.id = grupos.torneo_id
INNER JOIN fechas ON grupos.id = fechas.grupo_id
INNER JOIN partidos ON fechas.id = partidos.fecha_id

WHERE partidos.equipol_id = '.$id.' OR partidos.equipov_id = '.$id.'
GROUP BY torneos.id, torneos.nombre,torneos.year
ORDER BY torneos.year DESC';




        $torneosEquipo = DB::select(DB::raw($sql));

        foreach ($torneosEquipo as $torneo){
            $grupos = Grupo::where('torneo_id', '=',$torneo->idTorneo)->get();
            $arrgrupos='';
            foreach ($grupos as $grupo){
                $arrgrupos .=$grupo->id.',';
            }
            $arrgrupos = substr($arrgrupos, 0, -1);//quito última coma

            $fechas = Fecha::wherein('grupo_id',explode(',', $arrgrupos))->get();

            $arrfechas='';
            foreach ($fechas as $fecha){
                $arrfechas .=$fecha->id.',';
            }
            $arrfechas = substr($arrfechas, 0, -1);//quito última coma

            $partidos = Partido::wherein('fecha_id',explode(',', $arrfechas))->get();

            $arrpartidos='';
            foreach ($partidos as $partido){
                $arrpartidos .=$partido->id.',';
            }
            $arrpartidos = substr($arrpartidos, 0, -1);//quito última coma

           

            $sqlJugados="SELECT count(*)  as jugados, count(case when golesl > golesv then 1 end) ganados,
       count(case when golesv > golesl then 1 end) perdidos,
       count(case when golesl = golesv then 1 end) empatados,
       sum(golesl) golesl,
       sum(golesv) golesv,
       sum(golesl) - sum(golesv) diferencia,
       sum(
             case when golesl > golesv then 3 else 0 end
           + case when golesl = golesv then 1 else 0 end
       ) puntaje, CONCAT(
    ROUND(
      (  sum(
             case when golesl > golesv then 3 else 0 end
           + case when golesl = golesv then 1 else 0 end
       ) * 100/(COUNT(*)*3) ),
      2
    ), '%') porcentaje
    from (
       select  DISTINCT partidos.equipol_id equipo_id, golesl, golesv, fechas.id fecha_id
		 from partidos
		 INNER JOIN equipos ON partidos.equipol_id = equipos.id
		 INNER JOIN plantillas ON plantillas.equipo_id = equipos.id
		 INNER JOIN fechas ON partidos.fecha_id = fechas.id
		 INNER JOIN grupos ON fechas.grupo_id = grupos.id
		
		 WHERE golesl is not null AND golesv is not null AND grupos.torneo_id=".$torneo->idTorneo." AND grupos.id IN (".$arrgrupos.") AND partidos.equipol_id = ".$id."
     union all
       select DISTINCT partidos.equipov_id equipo_id, golesv, golesl, fechas.id fecha_id
		 from partidos
		 INNER JOIN equipos ON partidos.equipov_id = equipos.id
		 INNER JOIN plantillas ON plantillas.equipo_id = equipos.id
		 INNER JOIN fechas ON partidos.fecha_id = fechas.id
		 INNER JOIN grupos ON fechas.grupo_id = grupos.id
		 
		 WHERE golesl is not null AND golesv is not null AND grupos.torneo_id=".$torneo->idTorneo." AND grupos.id IN (".$arrgrupos.") AND partidos.equipov_id = ".$id."
) a
group by equipo_id
";

            //echo $sql3;

            $jugados = DB::select(DB::raw($sqlJugados));


            foreach ($jugados as $jugado){

                $torneo->jugados = $jugado->jugados;
                $torneo->ganados = $jugado->ganados;
                $torneo->empatados = $jugado->empatados;
                $torneo->perdidos = $jugado->perdidos;
                $torneo->favor = $jugado->golesl;
                $torneo->contra = $jugado->golesv;
                $torneo->puntaje = $jugado->puntaje;
                $torneo->porcentaje = $jugado->porcentaje;
            }
        }


        return view('equipos.ver', compact('equipo', 'torneosEquipo'));
    }

}


