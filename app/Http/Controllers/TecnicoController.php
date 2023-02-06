<?php

namespace App\Http\Controllers;

use App\Fecha;
use App\Grupo;
use App\Partido;
use App\Persona;
use App\Tecnico;
use App\Torneo;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use DB;

class TecnicoController extends Controller
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

        $nombre = $request->get('buscarpor');

        //$tecnicos=Tecnico::where('nombre','like',"%$nombre%")->orWhere('apellido','like',"%$nombre%")->orWhere('email','like',"%$nombre%")->orWhere(DB::raw('TIMESTAMPDIFF(YEAR,nacimiento,CURDATE())'),'=',"$nombre")->orderBy('apellido','ASC')->paginate();


        $tecnicos=Tecnico::SELECT('tecnicos.*','personas.nombre','personas.apellido','personas.nacimiento','personas.fallecimiento','personas.email','personas.foto')->Join('personas','personas.id','=','tecnicos.persona_id')->where('nombre','like',"%$nombre%")->orWhere('apellido','like',"%$nombre%")->orWhere('email','like',"%$nombre%")->orWhere(DB::raw('TIMESTAMPDIFF(YEAR,nacimiento,CURDATE())'),'=',"$nombre")->orderBy('apellido','ASC')->paginate();

        return view('tecnicos.index', compact('tecnicos','tecnicos'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {

        if ($request->get('partidoId')){
            $partido_id = $request->get('partidoId');
            $vista =view('tecnicos.create', compact('partido_id'));
        }
        elseif($request->get('torneoId')){
            $torneo_id = $request->get('torneoId');
            $vista =view('tecnicos.create', compact('torneo_id'));
        }
        else {
            $vista =view('tecnicos.create');
        }

        return $vista;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->validate($request,[ 'nombre'=>'required', 'apellido'=>'required','foto' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048']);


        if ($files = $request->file('foto')) {
            $image = $request->file('foto');
            $name = time().'.'.$image->getClientOriginalExtension();
            $destinationPath = public_path('/images');
            $image->move($destinationPath, $name);



            $insert['foto'] = "$name";
        }


        $insert['nombre'] = $request->get('nombre');
        $insert['apellido'] = $request->get('apellido');
        $insert['email'] = $request->get('email');
        $insert['telefono'] = $request->get('telefono');
        $insert['ciudad'] = $request->get('ciudad');
        $insert['observaciones'] = $request->get('observaciones');
        $insert['tipoDocumento'] = $request->get('tipoDocumento');
        $insert['documento'] = $request->get('documento');
        $insert['nacimiento'] = $request->get('nacimiento');
        $insert['fallecimiento'] = $request->get('fallecimiento');



        //$tecnico = Tecnico::create($insert);

        try {
            $persona = Persona::create($insert);
            $persona->tecnico()->create($insert);

            $respuestaID='success';
            $respuestaMSJ='Registro creado satisfactoriamente';
        }catch(QueryException $ex){

            try {
                $persona = Persona::where('nombre','=',$insert['nombre'])->Where('apellido','=',$insert['apellido'])->Where('nacimiento','=',$insert['nacimiento'])->first();
                if (!empty($persona)){
                    $persona->tecnico()->create($insert);
                    $respuestaID='success';
                    $respuestaMSJ='Registro creado satisfactoriamente';
                }
            }catch(QueryException $ex){

                $respuestaID='error';
                $respuestaMSJ=$ex->getMessage();

            }


        }

        if($request->get('partido_id')){
            $partido_id = $request->get('partido_id');
            $redirect = redirect()->route('alineaciones.index',['partidoId' => $partido_id])->with($respuestaID,$respuestaMSJ);

        }

        else{
            $redirect = redirect()->route('tecnicos.index')->with($respuestaID,$respuestaMSJ);
        }

        return $redirect;
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $tecnico=Tecnico::findOrFail($id);
        return view('tecnicos.show', compact('tecnico','tecnico'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $tecnico=tecnico::findOrFail($id);

        return view('tecnicos.edit', compact('tecnico','tecnico'));
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
        $this->validate($request,[ 'nombre'=>'required', 'apellido'=>'required','foto' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048']);


        if ($files = $request->file('foto')) {
            $image = $request->file('foto');
            $name = time().'.'.$image->getClientOriginalExtension();
            $destinationPath = public_path('/images');
            $image->move($destinationPath, $name);



            $update['foto'] = "$name";
        }


        $update['nombre'] = $request->get('nombre');
        $update['apellido'] = $request->get('apellido');
        $update['email'] = $request->get('email');
        $update['telefono'] = $request->get('telefono');
        $update['ciudad'] = $request->get('ciudad');
        $update['observaciones'] = $request->get('observaciones');
        $update['tipoDocumento'] = $request->get('tipoDocumento');
        $update['documento'] = $request->get('documento');
        $update['nacimiento'] = $request->get('nacimiento');
        $update['fallecimiento'] = $request->get('fallecimiento');


        $tecnico=tecnico::find($id);
        //$tecnico->update($update);
        $tecnico->persona()->update($update);

        return redirect()->route('tecnicos.index')->with('success','Registro actualizado satisfactoriamente');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $tecnico = Tecnico::find($id);

        $tecnico->delete();
        return redirect()->route('tecnicos.index')->with('success','Registro eliminado satisfactoriamente');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function ver(Request $request)
    {
        $id= $request->query('tecnicoId');
        $tecnico=Tecnico::findOrFail($id);

        $sql = 'SELECT torneos.id as idTorneo, CONCAT(torneos.nombre," ",torneos.year) AS nombreTorneo, "" AS escudo, "0" AS jugados, "0" AS ganados, "0" AS perdidos, "0" AS empatados, "0" AS favor, "0" AS contra, "0" AS puntaje, "0" as porcentaje
FROM torneos INNER JOIN grupos ON torneos.id = grupos.torneo_id
INNER JOIN fechas ON grupos.id = fechas.grupo_id
INNER JOIN partidos ON fechas.id = partidos.fecha_id
INNER JOIN partido_tecnicos ON partidos.id = partido_tecnicos.partido_id
WHERE partido_tecnicos.tecnico_id = '.$id.'
GROUP BY torneos.id, torneos.nombre,torneos.year
ORDER BY torneos.year DESC';




        $torneosTecnico = DB::select(DB::raw($sql));

        foreach ($torneosTecnico as $torneo){
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

            $sqlEscudos='SELECT DISTINCT escudo, equipo_id
FROM equipos
INNER JOIN partido_tecnicos ON equipos.id = partido_tecnicos.equipo_id
INNER JOIN partidos ON partidos.id = partido_tecnicos.partido_id
WHERE partido_tecnicos.tecnico_id = '.$id.' AND partido_tecnicos.partido_id IN ('.$arrpartidos.') ORDER BY partidos.dia ASC';



            $escudos = DB::select(DB::raw($sqlEscudos));


            foreach ($escudos as $escudo){

                $torneo->escudo .= $escudo->escudo.'_'.$escudo->equipo_id.',';
            }

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
       select  DISTINCT tecnicos.id tecnico_id, golesl, golesv, fechas.id fecha_id
		 from partidos
		 INNER JOIN equipos ON partidos.equipol_id = equipos.id
		 INNER JOIN plantillas ON plantillas.equipo_id = equipos.id
		 INNER JOIN fechas ON partidos.fecha_id = fechas.id
		 INNER JOIN grupos ON fechas.grupo_id = grupos.id
		 INNER JOIN partido_tecnicos ON partidos.id = partido_tecnicos.partido_id AND equipos.id = partido_tecnicos.equipo_id
		 INNER JOIN tecnicos ON tecnicos.id = partido_tecnicos.tecnico_id
		 WHERE golesl is not null AND golesv is not null AND grupos.torneo_id=".$torneo->idTorneo." AND grupos.id IN (".$arrgrupos.") AND partido_tecnicos.tecnico_id = ".$id."
     union all
       select DISTINCT tecnicos.id tecnico_id, golesv, golesl, fechas.id fecha_id
		 from partidos
		 INNER JOIN equipos ON partidos.equipov_id = equipos.id
		 INNER JOIN plantillas ON plantillas.equipo_id = equipos.id
		 INNER JOIN fechas ON partidos.fecha_id = fechas.id
		 INNER JOIN grupos ON fechas.grupo_id = grupos.id
		 INNER JOIN partido_tecnicos ON partidos.id = partido_tecnicos.partido_id AND equipos.id = partido_tecnicos.equipo_id
		 INNER JOIN tecnicos ON tecnicos.id = partido_tecnicos.tecnico_id
		 WHERE golesl is not null AND golesv is not null AND grupos.torneo_id=".$torneo->idTorneo." AND grupos.id IN (".$arrgrupos.") AND partido_tecnicos.tecnico_id = ".$id."
) a
group by tecnico_id
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

        $sql = 'SELECT torneos.id as idTorneo, CONCAT(torneos.nombre," ",torneos.year) AS nombreTorneo, "" AS escudo, "0" AS jugados, "0" AS goles, "0" AS amarillas, "0" AS rojas, "0" recibidos, "0" invictas, "" as idJugador
FROM torneos INNER JOIN grupos ON torneos.id = grupos.torneo_id
INNER JOIN plantillas ON grupos.id = plantillas.grupo_id
INNER JOIN plantilla_jugadors ON plantillas.id = plantilla_jugadors.plantilla_id
INNER JOIN jugadors ON jugadors.id = plantilla_jugadors.jugador_id
WHERE jugadors.persona_id = '.$tecnico->persona_id.'
GROUP BY torneos.id, torneos.nombre,torneos.year
ORDER BY torneos.year DESC';




        $torneosJugador = DB::select(DB::raw($sql));

        foreach ($torneosJugador as $torneo){
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

            $sqlEscudos='SELECT DISTINCT escudo, equipo_id
FROM equipos
INNER JOIN alineacions ON equipos.id = alineacions.equipo_id
INNER JOIN partidos ON partidos.id = alineacions.partido_id
INNER JOIN jugadors ON jugadors.id = alineacions.jugador_id
WHERE jugadors.persona_id = '.$tecnico->persona_id.' AND alineacions.partido_id IN ('.$arrpartidos.') ORDER BY partidos.dia ASC';



            $escudos = DB::select(DB::raw($sqlEscudos));


            foreach ($escudos as $escudo){

                $torneo->escudo .= $escudo->escudo.'_'.$escudo->equipo_id.',';
            }

            $sqlTitular="SELECT alineacions.jugador_id, COUNT(alineacions.jugador_id) as jugados
FROM torneos t2 INNER JOIN grupos g2 ON t2.id = g2.torneo_id
INNER JOIN fechas ON fechas.grupo_id = g2.id
INNER JOIN partidos ON partidos.fecha_id = fechas.id
INNER JOIN alineacions ON alineacions.partido_id = partidos.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id
INNER JOIN jugadors ON jugadors.id = alineacions.jugador_id
WHERE alineacions.tipo = 'Titular' AND grupos.torneo_id=".$torneo->idTorneo." AND grupos.id IN (".$arrgrupos.") AND jugadors.persona_id = ".$tecnico->persona_id. " GROUP BY alineacions.jugador_id";

            //echo $sql3;

            $jugados = DB::select(DB::raw($sqlTitular));


            foreach ($jugados as $jugado){
                $torneo->idJugador = $jugado->jugador_id;
                $torneo->jugados += $jugado->jugados;
            }

            $sql4="SELECT cambios.jugador_id, COUNT(cambios.jugador_id)  as jugados
FROM torneos t2 INNER JOIN grupos g2 ON t2.id = g2.torneo_id
INNER JOIN fechas ON fechas.grupo_id = g2.id
INNER JOIN partidos ON partidos.fecha_id = fechas.id
INNER JOIN cambios ON cambios.partido_id = partidos.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id
INNER JOIN jugadors ON jugadors.id = cambios.jugador_id
WHERE cambios.tipo = 'Entra' AND grupos.torneo_id=".$torneo->idTorneo." AND grupos.id IN (".$arrgrupos.") AND jugadors.persona_id = ".$tecnico->persona_id. " GROUP BY cambios.jugador_id";



            $jugados = DB::select(DB::raw($sql4));


            foreach ($jugados as $jugado){
                $torneo->idJugador = $jugado->jugador_id;
                $torneo->jugados += $jugado->jugados;
            }

            $sqlGoles = 'SELECT COUNT(gols.id) goles
FROM gols
INNER JOIN partidos ON gols.partido_id = partidos.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id
INNER JOIN jugadors ON jugadors.id = gols.jugador_id
WHERE gols.tipo <> \'En contra\' AND grupos.torneo_id='.$torneo->idTorneo.' AND grupos.id IN ('.$arrgrupos.') AND jugadors.persona_id = '.$tecnico->persona_id;




            $goleadores = DB::select(DB::raw($sqlGoles));

            foreach ($goleadores as $gol){

                $torneo->goles += $gol->goles;
            }

            $sqlTarjetas = 'SELECT count( case when tipo=\'Amarilla\' then 1 else NULL end) as  amarillas
, count( case when tipo=\'Roja\' or tipo=\'Doble Amarilla\' then 1 else NULL end) as  rojas
FROM tarjetas

INNER JOIN partidos ON tarjetas.partido_id = partidos.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id
INNER JOIN jugadors ON jugadors.id = tarjetas.jugador_id
WHERE  grupos.torneo_id='.$torneo->idTorneo.' AND grupos.id IN ('.$arrgrupos.') AND jugadors.persona_id = '.$tecnico->persona_id;


            $tarjetas = DB::select(DB::raw($sqlTarjetas));

            foreach ($tarjetas as $tarjeta){
                //Log::info('Tarjetas: '.$torneo->amarillas.' -> '.$tarjeta->amarillas);
                $torneo->amarillas += $tarjeta->amarillas;
                $torneo->rojas += $tarjeta->rojas;
            }

            $sqlArqueros = 'SELECT case when alineacions.equipo_id=partidos.equipol_id then partidos.golesv else partidos.golesl END AS recibidos,
case when alineacions.equipo_id=partidos.equipol_id and partidos.golesv = 0 then 1 else CASE when alineacions.equipo_id=partidos.equipov_id and partidos.golesl = 0 THEN 1 ELSE 0 END END AS invictas, personas.foto, "" escudo
FROM alineacions
INNER JOIN jugadors ON alineacions.jugador_id = jugadors.id AND jugadors.tipoJugador = \'Arquero\'
INNER JOIN partidos ON alineacions.partido_id = partidos.id
INNER JOIN personas ON jugadors.persona_id = personas.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id

WHERE  alineacions.tipo = \'Titular\'  AND grupos.torneo_id='.$torneo->idTorneo.' AND grupos.id IN ('.$arrgrupos.') AND jugadors.persona_id = '.$tecnico->persona_id;


            $arqueros = DB::select(DB::raw($sqlArqueros));

            foreach ($arqueros as $arquero){

                $torneo->recibidos += $arquero->recibidos;
                $torneo->invictas += $arquero->invictas;
            }

        }


        return view('tecnicos.ver', compact('tecnico', 'torneosTecnico', 'torneosJugador'));
    }

    public function jugados(Request $request)
    {
        $id= $request->query('tecnicoId');
        $tecnico=Tecnico::findOrFail($id);

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
       select  DISTINCT tecnicos.id tecnico_id, golesl, golesv, fechas.id fecha_id
		 from partidos
		 INNER JOIN equipos ON partidos.equipol_id = equipos.id
		 INNER JOIN plantillas ON plantillas.equipo_id = equipos.id
		 INNER JOIN fechas ON partidos.fecha_id = fechas.id
		 INNER JOIN grupos ON fechas.grupo_id = grupos.id
		 INNER JOIN partido_tecnicos ON partidos.id = partido_tecnicos.partido_id AND equipos.id = partido_tecnicos.equipo_id
		 INNER JOIN tecnicos ON tecnicos.id = partido_tecnicos.tecnico_id
		 WHERE golesl is not null AND golesv is not null AND (tecnicos.id = ".$id.") ";
        $sql .=($idTorneo)?" AND grupos.torneo_id = ".$idTorneo:"";
        $sql .=" union all
        select DISTINCT tecnicos.id tecnico_id, golesv, golesl, fechas.id fecha_id
		 from partidos
		 INNER JOIN equipos ON partidos.equipov_id = equipos.id
		 INNER JOIN plantillas ON plantillas.equipo_id = equipos.id
		 INNER JOIN fechas ON partidos.fecha_id = fechas.id
		 INNER JOIN grupos ON fechas.grupo_id = grupos.id
		 INNER JOIN partido_tecnicos ON partidos.id = partido_tecnicos.partido_id AND equipos.id = partido_tecnicos.equipo_id
		 INNER JOIN tecnicos ON tecnicos.id = partido_tecnicos.tecnico_id
		 WHERE golesl is not null AND golesv is not null AND (tecnicos.id = ".$id.") ";
        $sql .=($idTorneo)?" AND grupos.torneo_id = ".$idTorneo:"";
        $sql .=" ) a
group by tecnico_id";

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
INNER JOIN partido_tecnicos ON partidos.id = partido_tecnicos.partido_id AND (e1.id = partido_tecnicos.equipo_id OR e2.id = partido_tecnicos.equipo_id)
INNER JOIN tecnicos ON tecnicos.id = partido_tecnicos.tecnico_id
WHERE (tecnicos.id = ".$id.")";
        $sql .=($tipo=='Ganados')?" AND ((partido_tecnicos.equipo_id = e1.id AND partidos.golesl > partidos.golesv) OR (partido_tecnicos.equipo_id = e2.id AND partidos.golesv > partidos.golesl))":"";
        $sql .=($tipo=='Empatados')?" AND ((partido_tecnicos.equipo_id = e1.id AND partidos.golesl = partidos.golesv) OR (partido_tecnicos.equipo_id = e2.id AND partidos.golesl = partidos.golesv))":"";
        $sql .=($tipo=='Perdidos')?" AND ((partido_tecnicos.equipo_id = e1.id AND partidos.golesl < partidos.golesv) OR (partido_tecnicos.equipo_id = e2.id AND partidos.golesv < partidos.golesl))":"";
        $sql .=($idTorneo)?" AND grupos.torneo_id = ".$idTorneo:"";
        $sql .=" ORDER BY partidos.dia DESC";


//echo $sql;
        $partidos = DB::select(DB::raw($sql));


        $page = $request->query('page', 1);

        $paginate = 15;

        $offSet = ($page * $paginate) - $paginate;

        $itemsForCurrentPage = array_slice($partidos, $offSet, $paginate, true);



        $partidos = new \Illuminate\Pagination\LengthAwarePaginator($itemsForCurrentPage, count($partidos), $paginate, $page);

        $arrayParam = array('tecnicoId' => $id);

        if ($idTorneo){
            $arrayParam['torneoId'] = $idTorneo;
        }
        if ($tipo){
            $arrayParam['tipo'] = $tipo;
        }


        $partidos->setPath(route('tecnicos.jugados',  $arrayParam));


        $i=$offSet+1;



        return view('tecnicos.jugados', compact('tecnico','torneo','totalJugados','totalGanados','totalEmpatados','totalPerdidos','partidos','tipo'));
    }


}
