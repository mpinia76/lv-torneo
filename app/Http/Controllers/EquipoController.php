<?php

namespace App\Http\Controllers;

use App\Equipo;
use App\Fecha;
use App\Grupo;
use App\Partido;
use App\PosicionTorneo;
use App\Torneo;
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
        $this->middleware('auth')->except(['ver','jugados']);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {

        if ($request->has('buscarpor')){
            $nombre = $request->get('buscarpor');

            $request->session()->put('nombre_filtro_equipo', $request->get('buscarpor'));

        }
        else{
            $nombre = $request->session()->get('nombre_filtro_equipo');

        }

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

        $this->validate($request,[ 'nombre'=>'required','pais'=>'required','escudo' => 'image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048']);


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
        $insert['pais'] = $request->get('pais');
        $insert['url_nombre'] = $request->get('url_nombre');
        $insert['url_id'] = $request->get('url_id');


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
        $this->validate($request,[ 'nombre'=>'required','pais'=>'required','escudo' => 'image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048']);


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
        $update['pais'] = $request->get('pais');
        $update['url_id'] = $request->get('url_id');
        $update['url_nombre'] = $request->get('url_nombre');


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


		$sql = 'SELECT torneos.id as idTorneo, CONCAT(torneos.nombre," ",torneos.year) AS nombreTorneo, "0" AS jugados, "0" AS ganados, "0" AS perdidos, "0" AS empatados, "0" AS favor, "0" AS contra, "0" AS puntaje, "0" as porcentaje, "" as posicion, torneos.tipo, torneos.ambito
FROM torneos INNER JOIN grupos ON torneos.id = grupos.torneo_id
INNER JOIN fechas ON grupos.id = fechas.grupo_id
INNER JOIN partidos ON fechas.id = partidos.fecha_id

WHERE partidos.equipol_id = '.$id.' OR partidos.equipov_id = '.$id.'
GROUP BY torneos.id, torneos.nombre,torneos.year, torneos.tipo, torneos.ambito
ORDER BY torneos.year DESC';

        $titulosLiga=0;
        $titulosCopa=0;
        $titulosInternacional=0;

        $torneosEquipo = DB::select(DB::raw($sql));
        $torneosTitulos=array();
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

            $posicionTorneo = PosicionTorneo::where('torneo_id', '=',$torneo->idTorneo)->where('equipo_id', '=',$id)->first();

            if(!empty($posicionTorneo)){
                if ($posicionTorneo->posicion == 1){
                    //if ((stripos($torneo->nombreTorneo, 'Copa') !== false)||(stripos($torneo->nombreTorneo, 'Trofeo') !== false)) {
                    if ($torneo->ambito == 'Nacional'){
                        if ($torneo->tipo == 'Copa') {
                            $titulosCopa++;
                        }
                        else{
                            $titulosLiga++;
                        }
                    }
                    else{
                        $titulosInternacional++;
                    }

                }
            }

            foreach ($jugados as $jugado){

                $torneo->jugados = $jugado->jugados;
                $torneo->ganados = $jugado->ganados;
                $torneo->empatados = $jugado->empatados;
                $torneo->perdidos = $jugado->perdidos;
                $torneo->favor = $jugado->golesl;
                $torneo->contra = $jugado->golesv;
                $torneo->puntaje = $jugado->puntaje;
                $torneo->porcentaje = $jugado->porcentaje;
                $torneo->posicion = (!empty($posicionTorneo)) ? (
                ($posicionTorneo->posicion == 1) ?
                    '<img id="original" src="' . asset('images/campeon.png') . '" height="20"> Campeón' :
                    (($posicionTorneo->posicion == 2) ? '<img id="original" src="' . asset('images/subcampeon.png') . '" height="20">Subcampeón' : $posicionTorneo->posicion)
                ) : '';
                if ((!empty($posicionTorneo))&&($posicionTorneo->posicion == 1)){
                    $torneosTitulos[]=$torneo;
                }
            }
        }
        set_time_limit(0);
        //dd($request);

        $order= ($request->query('order'))?$request->query('order'):'jugados';
        $tipoOrder= ($request->query('tipoOrder'))?$request->query('tipoOrder'):'DESC';




        $sql = 'SELECT jugador_id, "" escudo, foto, jugador,
       sum(jugados) jugados,

       sum(goles) goles,
       sum(rojas) rojas,
       sum(amarillas) amarillas,
       sum(recibidos) recibidos,
       sum(invictas) invictas, sum(titulos) titulos

from

(SELECT jugadors.id AS jugador_id, personas.foto,"0" as jugados, CONCAT(personas.apellido,\', \',personas.nombre) jugador, "1" as goles, "0" as  amarillas
, "0" as  rojas, "0" as  recibidos, "0" as  invictas, "0" AS jugando, "0" AS titulos
FROM gols
INNER JOIN jugadors ON gols.jugador_id = jugadors.id
INNER JOIN personas ON jugadors.persona_id = personas.id
INNER JOIN partidos ON gols.partido_id = partidos.id
INNER JOIN alineacions ON alineacions.partido_id = partidos.id
AND alineacions.jugador_id = jugadors.id AND alineacions.equipo_id ='.$id.'
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id

WHERE gols.tipo <> \'En contra\'';



        $sql .=' UNION ALL
 SELECT jugadors.id AS jugador_id, personas.foto,"0" as jugados, CONCAT(personas.apellido,\', \',personas.nombre) jugador, "0" AS goles, ( case when tarjetas.tipo=\'Amarilla\' then 1 else NULL end) as  amarillas
, ( case when tarjetas.tipo=\'Roja\' or tarjetas.tipo=\'Doble Amarilla\' then 1 else NULL end) as  rojas, "0" as  recibidos, "0" as  invictas, "0" AS jugando, "0" AS titulos
FROM tarjetas
INNER JOIN jugadors ON tarjetas.jugador_id = jugadors.id
INNER JOIN personas ON jugadors.persona_id = personas.id
LEFT JOIN partidos ON tarjetas.partido_id = partidos.id
INNER JOIN alineacions ON alineacions.partido_id = partidos.id
AND alineacions.jugador_id = jugadors.id AND alineacions.equipo_id ='.$id.'
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id';




        $sql .=' UNION ALL
 SELECT jugadors.id AS jugador_id, personas.foto,"1" as jugados, CONCAT(personas.apellido,\', \',personas.nombre) jugador, "0" AS goles, "0" as  amarillas
, "0" as  rojas, (case when alineacions.equipo_id=partidos.equipol_id then partidos.golesv else partidos.golesl END) AS recibidos,
(case when alineacions.equipo_id=partidos.equipol_id and partidos.golesv = 0 then 1 else CASE when alineacions.equipo_id=partidos.equipov_id and partidos.golesl = 0 THEN 1 ELSE 0 END END) AS invictas, "0" AS jugando, "0" AS titulos
FROM alineacions
INNER JOIN jugadors ON alineacions.jugador_id = jugadors.id AND jugadors.tipoJugador = \'Arquero\'
INNER JOIN personas ON jugadors.persona_id = personas.id
INNER JOIN partidos ON alineacions.partido_id = partidos.id

INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id

WHERE  alineacions.tipo = \'Titular\' AND alineacions.equipo_id = '.$id;


        $sql .= ' UNION ALL
 SELECT jugadors.id AS jugador_id, personas.foto,"1" as jugados, CONCAT(personas.apellido,\', \',personas.nombre) jugador, "0" AS goles, "0" as  amarillas
, "0" as  rojas, "0" AS recibidos,
"0" AS invictas, "0" AS jugando, "0" AS titulos
FROM alineacions
INNER JOIN jugadors ON alineacions.jugador_id = jugadors.id AND jugadors.tipoJugador != \'Arquero\'
INNER JOIN personas ON jugadors.persona_id = personas.id
INNER JOIN partidos ON alineacions.partido_id = partidos.id

INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id
LEFT JOIN cambios ON alineacions.partido_id = cambios.partido_id AND cambios.jugador_id = jugadors.id
WHERE  (alineacions.tipo = \'Titular\' OR cambios.tipo = \'Entra\') AND alineacions.equipo_id = '.$id;


        $sql .=' UNION ALL
 SELECT jugadors.id AS jugador_id, personas.foto,"1" as jugados, CONCAT(personas.apellido,\', \',personas.nombre) jugador, "0" AS goles, "0" as  amarillas
, "0" as  rojas, "0" AS recibidos,
"0" AS invictas, "0" AS jugando, "0" AS titulos
FROM alineacions
INNER JOIN jugadors ON alineacions.jugador_id = jugadors.id AND jugadors.tipoJugador = \'Arquero\'
INNER JOIN personas ON jugadors.persona_id = personas.id
INNER JOIN partidos ON alineacions.partido_id = partidos.id

INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id
LEFT JOIN cambios ON alineacions.partido_id = cambios.partido_id AND cambios.jugador_id = jugadors.id
WHERE  (cambios.tipo = \'Entra\') AND alineacions.equipo_id = '.$id;

        $sql.=' UNION ALL
SELECT jugadors.id AS jugador_id, personas.foto,"0" as jugados, CONCAT(personas.apellido,\', \',personas.nombre) jugador, "0" AS goles, "0" as amarillas , "0" as rojas, "0" AS recibidos,
"0" AS invictas, "0" AS jugando, count(DISTINCT posicion_torneos.id) AS titulos
FROM plantilla_jugadors
INNER JOIN jugadors ON plantilla_jugadors.jugador_id = jugadors.id
INNER JOIN personas ON jugadors.persona_id = personas.id
INNER JOIN plantillas ON plantilla_jugadors.plantilla_id = plantillas.id

INNER JOIN grupos ON grupos.id = plantillas.grupo_id
INNER JOIN posicion_torneos ON posicion_torneos.torneo_id=grupos.torneo_id AND posicion_torneos.equipo_id = plantillas.equipo_id AND posicion_torneos.posicion=1
WHERE plantillas.equipo_id='.$id.'
GROUP BY jugadors.id,personas.foto,personas.apellido,personas.nombre';
        $sql .=' ) as subconsulta

group by jugador_id,jugador, foto
ORDER BY '.$order.' '.$tipoOrder.', jugador ASC';
//dd($sql);
        $jugadores = DB::select(DB::raw($sql));
        //echo $sql;


        $page = $request->query('page', 1);

        $paginate = 15;

        $offSet = ($page * $paginate) - $paginate;

        $itemsForCurrentPage = array_slice($jugadores, $offSet, $paginate, true);



        $jugadores = new \Illuminate\Pagination\LengthAwarePaginator($itemsForCurrentPage, count($jugadores), $paginate, $page);



        $jugadores->setPath(route('equipos.ver',  array('equipoId'=>$id,'order'=>$order,'tipoOrder'=>$tipoOrder,'pestActiva'=>'jugadores')));



        $iterator=$offSet+1;


        return view('equipos.ver', compact('equipo', 'torneosEquipo','titulosCopa','titulosLiga','torneosTitulos','jugadores','order','tipoOrder','iterator','titulosInternacional'));
    }

    public function jugados(Request $request)
    {
        $id= $request->query('equipoId');
        $equipo=Equipo::findOrFail($id);

        $idTorneo = ($request->query('torneoId'))?$request->query('torneoId'):'';
        $torneo='';
        if ($idTorneo){
            $torneo=Torneo::findOrFail($idTorneo);
        }
        $totalJugados =0;
        $totalGanados =0;
        $totalEmpatados =0;
        $totalPerdidos =0;

        $tipo = ($request->query('tipo'))?$request->query('tipo'):'';

        $sql ="SELECT count(*)  as jugados, count(case when golesl > golesv then 1 end) ganados,
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
       select  DISTINCT equipos.id equipo_id, golesl, golesv, fechas.id fecha_id
		 from partidos
		 INNER JOIN equipos ON partidos.equipol_id = equipos.id
		 INNER JOIN plantillas ON plantillas.equipo_id = equipos.id
		 INNER JOIN fechas ON partidos.fecha_id = fechas.id
		 INNER JOIN grupos ON fechas.grupo_id = grupos.id

		 WHERE golesl is not null AND golesv is not null AND (equipos.id = ".$id.") ";
        $sql .=($idTorneo)?" AND grupos.torneo_id = ".$idTorneo:"";
        $sql .=" union all
        select DISTINCT equipos.id equipo_id, golesv, golesl, fechas.id fecha_id
		 from partidos
		 INNER JOIN equipos ON partidos.equipov_id = equipos.id
		 INNER JOIN plantillas ON plantillas.equipo_id = equipos.id
		 INNER JOIN fechas ON partidos.fecha_id = fechas.id
		 INNER JOIN grupos ON fechas.grupo_id = grupos.id

		 WHERE golesl is not null AND golesv is not null AND (equipos.id = ".$id.") ";
        $sql .=($idTorneo)?" AND grupos.torneo_id = ".$idTorneo:"";
        $sql .=" ) a
group by equipo_id";

        $jugados = DB::select(DB::raw($sql));

        foreach ($jugados as $jugado){
            $totalJugados =$jugado->jugados;
            $totalGanados =$jugado->ganados;
            $totalEmpatados =$jugado->empatados;
            $totalPerdidos =$jugado->perdidos;
        }



        $sql ="SELECT DISTINCT torneos.nombre AS nombreTorneo, torneos.year, fechas.numero, partidos.dia, e1.id AS equipol_id, e1.escudo AS fotoLocal, e1.nombre AS local, e2.id AS equipov_id,e2.escudo AS fotoVisitante, e2.nombre AS visitante, partidos.golesl,
partidos.golesv, partidos.penalesl, partidos.penalesv, partidos.id partido_id
FROM partidos
INNER JOIN equipos e1 ON partidos.equipol_id = e1.id
INNER JOIN equipos e2 ON partidos.equipov_id = e2.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON fechas.grupo_id = grupos.id
INNER JOIN torneos ON grupos.torneo_id = torneos.id

WHERE golesl is not null AND golesv is not null AND ((e1.id = ".$id.") OR (e2.id = ".$id."))";
        $sql .=($tipo=='Ganados')?" AND ((e1.id = ".$id." AND partidos.golesl > partidos.golesv) OR (e2.id = ".$id." AND partidos.golesv > partidos.golesl))":"";
        $sql .=($tipo=='Empatados')?" AND ((partidos.golesl = partidos.golesv))":"";
        $sql .=($tipo=='Perdidos')?" AND ((e1.id = ".$id." AND partidos.golesl < partidos.golesv) OR (e2.id = ".$id." AND partidos.golesv < partidos.golesl))":"";
        $sql .=($idTorneo)?" AND grupos.torneo_id = ".$idTorneo:"";
        $sql .=" ORDER BY partidos.dia DESC";



        $partidos = DB::select(DB::raw($sql));


        $page = $request->query('page', 1);

        $paginate = 15;

        $offSet = ($page * $paginate) - $paginate;

        $itemsForCurrentPage = array_slice($partidos, $offSet, $paginate, true);



        $partidos = new \Illuminate\Pagination\LengthAwarePaginator($itemsForCurrentPage, count($partidos), $paginate, $page);

        $arrayParam = array('equipoId' => $id);

        if ($idTorneo){
            $arrayParam['torneoId'] = $idTorneo;
        }
        if ($tipo){
            $arrayParam['tipo'] = $tipo;
        }


        $partidos->setPath(route('equipos.jugados',  $arrayParam));


        $i=$offSet+1;



        return view('equipos.jugados', compact('equipo','torneo','totalJugados','totalGanados','totalEmpatados','totalPerdidos','partidos','tipo'));
    }

}


