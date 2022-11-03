<?php

namespace App\Http\Controllers;

use App\Fecha;
use App\Grupo;
use App\Jugador;
use App\Partido;
use App\Persona;
use App\Torneo;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use DB;


class JugadorController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth')->except(['ver','jugados','goles']);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {


        $data = $request->all();
        $nombre = $request->get('buscarpor');

        //$jugadores=Jugador::where('nombre','like',"%$nombre%")->orWhere('apellido','like',"%$nombre%")->orWhere('email','like',"%$nombre%")->orWhere('tipoJugador','like',"%$nombre%")->orWhere(DB::raw('TIMESTAMPDIFF(YEAR,nacimiento,CURDATE())'),'=',"$nombre")->orderBy('apellido','ASC')->paginate();

        $jugadores=Jugador::SELECT('jugadors.*','personas.nombre','personas.apellido','personas.nacimiento','personas.fallecimiento','personas.email','personas.foto')->Join('personas','personas.id','=','jugadors.persona_id')->where('nombre','like',"%$nombre%")->orWhere('apellido','like',"%$nombre%")->orWhere('email','like',"%$nombre%")->orWhere('tipoJugador','like',"%$nombre%")->orWhere(DB::raw('TIMESTAMPDIFF(YEAR,nacimiento,CURDATE())'),'=',"$nombre")->orderBy('apellido','ASC')->paginate();

        //$jugadores=Jugador::where('persona_id','like',"%4914%")->paginate();

        //dd($jugadores);
        //
        //$jugadores=Jugador::orderBy('apellido','ASC')->paginate(2);
        //return view('Jugador.index',compact('jugadores'));
        //$jugadores = Jugador::all();
        return view('jugadores.index', compact('jugadores','jugadores', 'data'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {

        if ($request->get('plantillaId')){
            $plantilla_id = $request->get('plantillaId');
            $vista =view('jugadores.create', compact('plantilla_id'));
        }
        elseif($request->get('torneoId')){
            $torneo_id = $request->get('torneoId');
            $vista =view('jugadores.create', compact('torneo_id'));
        }
        else {
            $vista =view('jugadores.create');
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
        //
        //Log::info(print_r($request->file(), true));

        $this->validate($request,[ 'tipoJugador'=>'required','nombre'=>'required', 'apellido'=>'required','foto' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048']);


        if ($files = $request->file('foto')) {
            $image = $request->file('foto');
            $name = time().'.'.$image->getClientOriginalExtension();
            $destinationPath = public_path('/images');
            $image->move($destinationPath, $name);


            /*$destinationPath = 'public/image/'; // upload path
            $profileImage = date('YmdHis') . "." . $files->getClientOriginalExtension();
            Log::info($profileImage);
            $files->move($destinationPath, $profileImage);*/
            $insert['foto'] = "$name";
        }

        $insert['nombre'] = $request->get('nombre');
        $insert['apellido'] = $request->get('apellido');
        $insert['email'] = $request->get('email');
        $insert['telefono'] = $request->get('telefono');
        $insert['ciudad'] = $request->get('ciudad');
        $insert['altura'] = $request->get('altura');
        $insert['peso'] = $request->get('peso');
        $insert['observaciones'] = $request->get('observaciones');
        $insert['tipoDocumento'] = $request->get('tipoDocumento');
        $insert['documento'] = $request->get('documento');
        $insert['nacimiento'] = $request->get('nacimiento');
        $insert['fallecimiento'] = $request->get('fallecimiento');

        $insert['tipoJugador'] = $request->get('tipoJugador');

        $insert['pie'] = $request->get('pie');








        try {
            $persona = Persona::create($insert);
            $persona->jugador()->create($insert);

            $respuestaID='success';
            $respuestaMSJ='Registro creado satisfactoriamente';
        }catch(QueryException $ex){

            try {
                $persona = Persona::where('nombre','=',$insert['nombre'])->Where('apellido','=',$insert['apellido'])->Where('nacimiento','=',$insert['nacimiento'])->first();
                if (!empty($persona)){
                    $persona->update($insert);
                    $persona->jugador()->create($insert);
                    $respuestaID='success';
                    $respuestaMSJ='Registro creado satisfactoriamente';
                }
            }catch(QueryException $ex){

                $respuestaID='error';
                $respuestaMSJ=$ex->getMessage();

            }


        }

        if($request->get('plantilla_id')){
            $plantilla_id = $request->get('plantilla_id');
            $redirect = redirect()->route('plantillas.edit',[$plantilla_id])->with($respuestaID,$respuestaMSJ);

        }
        elseif($request->get('torneo_id')){
            $redirect = redirect()->route('plantillas.create', ['grupoId' => $request->get('grupo_id')])->with($respuestaID,$respuestaMSJ);
        }
        else{
            $redirect = redirect()->route('jugadores.index')->with($respuestaID,$respuestaMSJ);
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
        $jugador=Jugador::findOrFail($id);
        return view('jugadores.show', compact('jugador','jugador'));
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
        $jugador=jugador::findOrFail($id);

        return view('jugadores.edit', compact('jugador','jugador'));
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
        $this->validate($request,[ 'tipoJugador'=>'required','nombre'=>'required', 'apellido'=>'required','foto' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048']);


        if ($files = $request->file('foto')) {
            $image = $request->file('foto');
            $name = time().'.'.$image->getClientOriginalExtension();
            $destinationPath = public_path('/images');
            $image->move($destinationPath, $name);


            /*$destinationPath = 'public/image/'; // upload path
            $profileImage = date('YmdHis') . "." . $files->getClientOriginalExtension();
            Log::info($profileImage);
            $files->move($destinationPath, $profileImage);*/
            $update['foto'] = "$name";
        }

        $update['nombre'] = $request->get('nombre');
        $update['apellido'] = $request->get('apellido');
        $update['email'] = $request->get('email');
        $update['telefono'] = $request->get('telefono');
        $update['ciudad'] = $request->get('ciudad');
        $update['altura'] = $request->get('altura');
        $update['peso'] = $request->get('peso');
        $update['observaciones'] = $request->get('observaciones');
        $update['tipoDocumento'] = $request->get('tipoDocumento');
        $update['documento'] = $request->get('documento');
        $update['nacimiento'] = $request->get('nacimiento');
        $update['fallecimiento'] = $request->get('fallecimiento');

        $updateJ['tipoJugador'] = $request->get('tipoJugador');

        $updateJ['pie'] = $request->get('pie');





        $jugador=jugador::find($id);
        $jugador->update($updateJ);
        $jugador->persona()->update($update);

        return redirect()->route('jugadores.index')->with('success','Registro actualizado satisfactoriamente');

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $jugador = Jugador::find($id);

        $jugador->delete();
        return redirect()->route('jugadores.index')->with('success','Registro eliminado satisfactoriamente');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function ver(Request $request)
    {
        $id= $request->query('jugadorId');
        $jugador=Jugador::findOrFail($id);



        $sql = 'SELECT torneos.id as idTorneo, CONCAT(torneos.nombre," ",torneos.year) AS nombreTorneo, "" AS escudo, "0" AS jugados, "0" AS goles, "0" AS amarillas, "0" AS rojas, "0" recibidos, "0" invictas
FROM torneos INNER JOIN grupos ON torneos.id = grupos.torneo_id
INNER JOIN plantillas ON grupos.id = plantillas.grupo_id
INNER JOIN plantilla_jugadors ON plantillas.id = plantilla_jugadors.plantilla_id
WHERE plantilla_jugadors.jugador_id = '.$id.'
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
WHERE alineacions.jugador_id = '.$id.' AND alineacions.partido_id IN ('.$arrpartidos.') ORDER BY partidos.dia ASC';



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
WHERE alineacions.tipo = 'Titular' AND grupos.torneo_id=".$torneo->idTorneo." AND grupos.id IN (".$arrgrupos.") AND alineacions.jugador_id = ".$id. " GROUP BY alineacions.jugador_id";

            //echo $sql3;

            $jugados = DB::select(DB::raw($sqlTitular));


            foreach ($jugados as $jugado){

                $torneo->jugados += $jugado->jugados;
            }

            $sql4="SELECT cambios.jugador_id, COUNT(cambios.jugador_id)  as jugados
FROM torneos t2 INNER JOIN grupos g2 ON t2.id = g2.torneo_id
INNER JOIN fechas ON fechas.grupo_id = g2.id
INNER JOIN partidos ON partidos.fecha_id = fechas.id
INNER JOIN cambios ON cambios.partido_id = partidos.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id
WHERE cambios.tipo = 'Entra' AND grupos.torneo_id=".$torneo->idTorneo." AND grupos.id IN (".$arrgrupos.") AND cambios.jugador_id = ".$id. " GROUP BY cambios.jugador_id";



            $jugados = DB::select(DB::raw($sql4));


            foreach ($jugados as $jugado){

                $torneo->jugados += $jugado->jugados;
            }

            $sqlGoles = 'SELECT COUNT(gols.id) goles
FROM gols
INNER JOIN partidos ON gols.partido_id = partidos.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id

WHERE gols.tipo <> \'En contra\' AND grupos.torneo_id='.$torneo->idTorneo.' AND grupos.id IN ('.$arrgrupos.') AND gols.jugador_id = '.$id;




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

WHERE  grupos.torneo_id='.$torneo->idTorneo.' AND grupos.id IN ('.$arrgrupos.') AND tarjetas.jugador_id = '.$id;


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

WHERE  alineacions.tipo = \'Titular\'  AND grupos.torneo_id='.$torneo->idTorneo.' AND grupos.id IN ('.$arrgrupos.') AND jugadors.id = '.$id;


            $arqueros = DB::select(DB::raw($sqlArqueros));

            foreach ($arqueros as $arquero){

                $torneo->recibidos += $arquero->recibidos;
                $torneo->invictas += $arquero->invictas;
            }

        }


        $sql = 'SELECT torneos.id as idTorneo, CONCAT(torneos.nombre," ",torneos.year) AS nombreTorneo, "" AS escudo, "0" AS jugados, "0" AS ganados, "0" AS perdidos, "0" AS empatados, "0" AS favor, "0" AS contra, "0" AS puntaje, "0" as porcentaje
FROM torneos INNER JOIN grupos ON torneos.id = grupos.torneo_id
INNER JOIN fechas ON grupos.id = fechas.grupo_id
INNER JOIN partidos ON fechas.id = partidos.fecha_id
INNER JOIN partido_tecnicos ON partidos.id = partido_tecnicos.partido_id
INNER JOIN tecnicos ON tecnicos.id = partido_tecnicos.tecnico_id
WHERE tecnicos.persona_id = '.$jugador->persona_id.'
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
INNER JOIN tecnicos ON tecnicos.id = partido_tecnicos.tecnico_id
WHERE tecnicos.persona_id = '.$jugador->persona_id.' AND partido_tecnicos.partido_id IN ('.$arrpartidos.') ORDER BY partidos.dia ASC';



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
		 WHERE golesl is not null AND golesv is not null AND grupos.torneo_id=".$torneo->idTorneo." AND grupos.id IN (".$arrgrupos.") AND tecnicos.persona_id = ".$jugador->persona_id."
     union all
       select DISTINCT tecnicos.id tecnico_id, golesv, golesl, fechas.id fecha_id
		 from partidos
		 INNER JOIN equipos ON partidos.equipov_id = equipos.id
		 INNER JOIN plantillas ON plantillas.equipo_id = equipos.id
		 INNER JOIN fechas ON partidos.fecha_id = fechas.id
		 INNER JOIN grupos ON fechas.grupo_id = grupos.id
		 INNER JOIN partido_tecnicos ON partidos.id = partido_tecnicos.partido_id AND equipos.id = partido_tecnicos.equipo_id
		 INNER JOIN tecnicos ON tecnicos.id = partido_tecnicos.tecnico_id
		 WHERE golesl is not null AND golesv is not null AND grupos.torneo_id=".$torneo->idTorneo." AND grupos.id IN (".$arrgrupos.") AND tecnicos.persona_id = ".$jugador->persona_id."
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



        return view('jugadores.ver', compact('jugador','torneosJugador','torneosTecnico'));
    }

    public function jugados(Request $request)
    {
        $id= $request->query('jugadorId');
        $jugador=Jugador::findOrFail($id);

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
       select  DISTINCT alineacions.jugador_id, golesl, golesv, fechas.id fecha_id
		 from partidos
		 INNER JOIN equipos ON partidos.equipol_id = equipos.id
		 INNER JOIN plantillas ON plantillas.equipo_id = equipos.id
		 INNER JOIN fechas ON partidos.fecha_id = fechas.id
		 INNER JOIN grupos ON fechas.grupo_id = grupos.id
		 INNER JOIN alineacions ON partidos.id = alineacions.partido_id AND equipos.id = alineacions.equipo_id
		  LEFT JOIN cambios ON cambios.partido_id = partidos.id AND cambios.jugador_id = alineacions.jugador_id
		 WHERE golesl is not null AND golesv is not null AND ((alineacions.tipo = 'Titular' AND alineacions.jugador_id = ".$id.") OR (cambios.tipo = 'Entra' AND cambios.jugador_id = ".$id.")) ";
            $sql .=($idTorneo)?" AND grupos.torneo_id = ".$idTorneo:"";
            $sql .=" union all
       select DISTINCT alineacions.jugador_id, golesv, golesl, fechas.id fecha_id
		 from partidos
		 INNER JOIN equipos ON partidos.equipov_id = equipos.id
		 INNER JOIN plantillas ON plantillas.equipo_id = equipos.id
		 INNER JOIN fechas ON partidos.fecha_id = fechas.id
		 INNER JOIN grupos ON fechas.grupo_id = grupos.id
		 INNER JOIN alineacions ON partidos.id = alineacions.partido_id AND equipos.id = alineacions.equipo_id
		 LEFT JOIN cambios ON cambios.partido_id = partidos.id AND cambios.jugador_id = alineacions.jugador_id
		 WHERE golesl is not null AND golesv is not null AND ((alineacions.tipo = 'Titular' AND alineacions.jugador_id = ".$id.") OR (cambios.tipo = 'Entra' AND cambios.jugador_id = ".$id."))";
            $sql .=($idTorneo)?" AND grupos.torneo_id = ".$idTorneo:"";
            $sql .=" ) a
group by jugador_id";

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
INNER JOIN alineacions ON alineacions.partido_id = partidos.id
LEFT JOIN cambios ON cambios.partido_id = partidos.id AND cambios.jugador_id = alineacions.jugador_id
WHERE ((alineacions.tipo = 'Titular' AND alineacions.jugador_id = ".$id.") OR (cambios.tipo = 'Entra' AND cambios.jugador_id = ".$id."))";
        $sql .=($tipo=='Ganados')?" AND ((alineacions.equipo_id = e1.id AND partidos.golesl > partidos.golesv) OR (alineacions.equipo_id = e2.id AND partidos.golesv > partidos.golesl))":"";
        $sql .=($tipo=='Empatados')?" AND ((alineacions.equipo_id = e1.id AND partidos.golesl = partidos.golesv))":"";
        $sql .=($tipo=='Perdidos')?" AND ((alineacions.equipo_id = e1.id AND partidos.golesl < partidos.golesv) OR (alineacions.equipo_id = e2.id AND partidos.golesv < partidos.golesl))":"";
        $sql .=($idTorneo)?" AND grupos.torneo_id = ".$idTorneo:"";
        $sql .=" ORDER BY partidos.dia DESC";



            $partidos = DB::select(DB::raw($sql));


        $page = $request->query('page', 1);

        $paginate = 15;

        $offSet = ($page * $paginate) - $paginate;

        $itemsForCurrentPage = array_slice($partidos, $offSet, $paginate, true);



        $partidos = new \Illuminate\Pagination\LengthAwarePaginator($itemsForCurrentPage, count($partidos), $paginate, $page);

        $arrayParam = array('jugadorId' => $id);

        if ($idTorneo){
            $arrayParam['torneoId'] = $idTorneo;
        }
        if ($tipo){
            $arrayParam['tipo'] = $tipo;
        }


        $partidos->setPath(route('jugadores.jugados',  $arrayParam));


        $i=$offSet+1;



        return view('jugadores.jugados', compact('jugador','torneo','totalJugados','totalGanados','totalEmpatados','totalPerdidos','partidos','tipo'));
    }

    public function goles(Request $request)
    {
        $id= $request->query('jugadorId');
        $jugador=Jugador::findOrFail($id);

        $idTorneo = ($request->query('torneoId'))?$request->query('torneoId'):'';
        $torneo='';
        if ($idTorneo){
            $torneo=Torneo::findOrFail($idTorneo);
        }
        $totalTodos =0;
        $totalJugada =0;
        $totalCabeza =0;
        $totalPenal =0;
        $totalTiroLibre =0;

        $tipo = ($request->query('tipo'))?$request->query('tipo'):'';

        $sql = 'SELECT jugadors.id, CONCAT(personas.apellido,\', \',personas.nombre) jugador, COUNT(gols.id) goles, count( case when tipo=\'Jugada\' then 1 else NULL end) as  Jugada, "" as foto
, count( case when tipo=\'Cabeza\' then 1 else NULL end) as  Cabeza, count( case when tipo=\'Penal\' then 1 else NULL end) as  Penal, count( case when tipo=\'Tiro Libre\' then 1 else NULL end) as  Tiro_Libre
FROM gols
INNER JOIN jugadors ON gols.jugador_id = jugadors.id
INNER JOIN personas ON jugadors.persona_id = personas.id
INNER JOIN partidos ON gols.partido_id = partidos.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id

WHERE gols.tipo <> \'En contra\' AND jugadors.id ='.$id;
        $sql .=($idTorneo)?" AND grupos.torneo_id = ".$idTorneo:"";
        $sql .=' GROUP BY jugadors.id,jugador';




            $gols = DB::select(DB::raw($sql));

            foreach ($gols as $gol){
                $totalTodos =$gol->goles;
                $totalJugada =$gol->Jugada;
                $totalCabeza =$gol->Cabeza;
                $totalPenal =$gol->Penal;
                $totalTiroLibre =$gol->Tiro_Libre;
            }



        $sql ="SELECT DISTINCT torneos.nombre AS nombreTorneo, torneos.year, fechas.numero, partidos.dia, e1.id AS equipol_id, e1.escudo AS fotoLocal, e1.nombre AS local, e2.id AS equipov_id,e2.escudo AS fotoVisitante, e2.nombre AS visitante, partidos.golesl,
partidos.golesv, partidos.penalesl, partidos.penalesv, partidos.id partido_id
FROM partidos
INNER JOIN equipos e1 ON partidos.equipol_id = e1.id
INNER JOIN equipos e2 ON partidos.equipov_id = e2.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON fechas.grupo_id = grupos.id
INNER JOIN torneos ON grupos.torneo_id = torneos.id
INNER JOIN alineacions ON alineacions.partido_id = partidos.id
LEFT JOIN gols ON gols.partido_id = partidos.id AND gols.jugador_id = alineacions.jugador_id
WHERE (alineacions.jugador_id = ".$id.")";
        $sql .=($tipo=='Jugada')?" AND (gols.tipo = 'Jugada')":"";
        $sql .=($tipo=='Cabeza')?" AND (gols.tipo = 'Cabeza')":"";
        $sql .=($tipo=='Penal')?" AND (gols.tipo = 'Penal')":"";
        $sql .=($tipo=='Tiro Libre')?" AND (gols.tipo = 'Tiro Libre')":"";
        $sql .=($idTorneo)?" AND grupos.torneo_id = ".$idTorneo:"";
        $sql .=" ORDER BY partidos.dia DESC";


        $partidos = DB::select(DB::raw($sql));


        $page = $request->query('page', 1);

        $paginate = 15;

        $offSet = ($page * $paginate) - $paginate;

        $itemsForCurrentPage = array_slice($partidos, $offSet, $paginate, true);



        $partidos = new \Illuminate\Pagination\LengthAwarePaginator($itemsForCurrentPage, count($partidos), $paginate, $page);

        $arrayParam = array('jugadorId' => $id);

        if ($idTorneo){
            $arrayParam['torneoId'] = $idTorneo;
        }
        if ($tipo){
            $arrayParam['tipo'] = $tipo;
        }


        $partidos->setPath(route('jugadores.goles',  $arrayParam));


        $i=$offSet+1;



        return view('jugadores.goles', compact('jugador','torneo','totalTodos','totalJugada','totalCabeza','totalPenal','totalTiroLibre','partidos','tipo'));
    }

    public function tarjetas(Request $request)
    {
        $id= $request->query('jugadorId');
        $jugador=Jugador::findOrFail($id);

        $idTorneo = ($request->query('torneoId'))?$request->query('torneoId'):'';
        $torneo='';
        if ($idTorneo){
            $torneo=Torneo::findOrFail($idTorneo);
        }
        $totalTodos =0;
        $totalRojas =0;
        $totalAmarillas =0;


        $tipo = ($request->query('tipo'))?$request->query('tipo'):'';

        $sql = 'SELECT jugadors.id, CONCAT(personas.apellido,\', \',personas.nombre) jugador, count( case when tipo=\'Amarilla\' then 1 else NULL end) as  amarillas
, count( case when tipo=\'Roja\' or tipo=\'Doble Amarilla\' then 1 else NULL end) as  rojas, "" foto
FROM tarjetas
INNER JOIN jugadors ON tarjetas.jugador_id = jugadors.id
INNER JOIN personas ON jugadors.persona_id = personas.id
INNER JOIN partidos ON tarjetas.partido_id = partidos.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id

WHERE jugadors.id ='.$id;
        $sql .=($idTorneo)?" AND grupos.torneo_id = ".$idTorneo:"";
        $sql .=' GROUP BY jugadors.id,jugador';




        $tarjetas = DB::select(DB::raw($sql));

        foreach ($tarjetas as $tarjeta){
            $totalTodos =$tarjeta->amarillas + $tarjeta->rojas;
            $totalRojas =$tarjeta->rojas;
            $totalAmarillas =$tarjeta->amarillas;

        }



        $sql ="SELECT DISTINCT torneos.nombre AS nombreTorneo, torneos.year, fechas.numero, partidos.dia, e1.id AS equipol_id, e1.escudo AS fotoLocal, e1.nombre AS local, e2.id AS equipov_id,e2.escudo AS fotoVisitante, e2.nombre AS visitante, partidos.golesl,
partidos.golesv, partidos.penalesl, partidos.penalesv, partidos.id partido_id
FROM partidos
INNER JOIN equipos e1 ON partidos.equipol_id = e1.id
INNER JOIN equipos e2 ON partidos.equipov_id = e2.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON fechas.grupo_id = grupos.id
INNER JOIN torneos ON grupos.torneo_id = torneos.id
INNER JOIN alineacions ON alineacions.partido_id = partidos.id
LEFT JOIN tarjetas ON tarjetas.partido_id = partidos.id AND tarjetas.jugador_id = alineacions.jugador_id
WHERE (alineacions.jugador_id = ".$id.")";
        $sql .=($tipo=='Roja')?" AND (tarjetas.tipo = 'Roja')":"";
        $sql .=($tipo=='Amarilla')?" AND (tarjetas.tipo = 'Amarilla')":"";

        $sql .=($idTorneo)?" AND grupos.torneo_id = ".$idTorneo:"";
        $sql .=" ORDER BY partidos.dia DESC";


        $partidos = DB::select(DB::raw($sql));


        $page = $request->query('page', 1);

        $paginate = 15;

        $offSet = ($page * $paginate) - $paginate;

        $itemsForCurrentPage = array_slice($partidos, $offSet, $paginate, true);



        $partidos = new \Illuminate\Pagination\LengthAwarePaginator($itemsForCurrentPage, count($partidos), $paginate, $page);

        $arrayParam = array('jugadorId' => $id);

        if ($idTorneo){
            $arrayParam['torneoId'] = $idTorneo;
        }
        if ($tipo){
            $arrayParam['tipo'] = $tipo;
        }


        $partidos->setPath(route('jugadores.tarjetas',  $arrayParam));


        $i=$offSet+1;



        return view('jugadores.tarjetas', compact('jugador','torneo','totalTodos','totalRojas','totalAmarillas','partidos','tipo'));
    }

}
