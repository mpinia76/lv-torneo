<?php

namespace App\Http\Controllers;

use App\Alineacion;
use App\Fecha;
use App\Grupo;
use App\Jugador;
use App\Partido;
use App\PartidoTecnico;
use App\Persona;
use App\PosicionTorneo;
use App\Tecnico;
use App\Torneo;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Sunra\PhpSimple\HtmlDomParser;
use DB;
use GuzzleHttp\Client;
use Carbon\Carbon;


class JugadorController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth')->except(['ver','jugados','goles','tarjetas','titulos']);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {


        $data = $request->all();
        if ($request->has('buscarpor')){
            $nombre = $request->get('buscarpor');

            $request->session()->put('nombre_filtro_jugador', $request->get('buscarpor'));

        }
        else{
            $nombre = $request->session()->get('nombre_filtro_jugador');

        }


        //$jugadores=Jugador::where('nombre','like',"%$nombre%")->orWhere('apellido','like',"%$nombre%")->orWhere('email','like',"%$nombre%")->orWhere('tipoJugador','like',"%$nombre%")->orWhere(DB::raw('TIMESTAMPDIFF(YEAR,nacimiento,CURDATE())'),'=',"$nombre")->orderBy('apellido','ASC')->paginate();

        $jugadores=Jugador::SELECT('jugadors.*','personas.nombre','personas.apellido','personas.nacimiento','personas.fallecimiento','personas.ciudad','personas.nacionalidad','personas.foto')->Join('personas','personas.id','=','jugadors.persona_id')->where('nombre','like',"%$nombre%")->orWhere('apellido','like',"%$nombre%")->orWhere('email','like',"%$nombre%")->orWhere('tipoJugador','like',"%$nombre%")->orWhere(DB::raw('TIMESTAMPDIFF(YEAR,nacimiento,CURDATE())'),'=',"$nombre")->orderBy('apellido','ASC')->orderBy('nombre','ASC')->paginate();

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

        $this->validate($request,[ 'tipoJugador'=>'required','nombre'=>'required', 'apellido'=>'required','foto' => 'image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048']);


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
        $insert['nacionalidad'] = $request->get('nacionalidad');
        $insert['altura'] = $request->get('altura');
        $insert['peso'] = $request->get('peso');
        $insert['observaciones'] = $request->get('observaciones');
        $insert['tipoDocumento'] = $request->get('tipoDocumento');
        $insert['documento'] = $request->get('documento');
        $insert['nacimiento'] = $request->get('nacimiento');
        $insert['fallecimiento'] = $request->get('fallecimiento');

        $insert['tipoJugador'] = $request->get('tipoJugador');

        $insert['pie'] = $request->get('pie');
        $insert['url_nombre'] = $request->get('url_nombre');







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
                    $respuestaMSJ='Registro creado satisfactoriamente - existe como tecnico';
                }
            }catch(QueryException $ex){

                $respuestaID='error';
                $errorCode = $ex->errorInfo[1];

                if ($errorCode == 1062) {
                    $respuestaMSJ='Jugador repetido';
                }
                //$respuestaMSJ=$ex->getMessage();

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
        $this->validate($request,[ 'tipoJugador'=>'required','nombre'=>'required', 'apellido'=>'required','foto' => 'image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048']);


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
        $update['nacionalidad'] = $request->get('nacionalidad');
        $update['altura'] = $request->get('altura');
        $update['peso'] = $request->get('peso');
        $update['observaciones'] = $request->get('observaciones');
        $update['tipoDocumento'] = $request->get('tipoDocumento');
        $update['documento'] = $request->get('documento');
        $update['nacimiento'] = $request->get('nacimiento');
        $update['fallecimiento'] = $request->get('fallecimiento');
        $update['verificado'] = $request->get('verificado');

        $updateJ['tipoJugador'] = $request->get('tipoJugador');

        $updateJ['pie'] = $request->get('pie');
        $updateJ['url_nombre'] = $request->get('url_nombre');




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
        $persona = Persona::find($jugador->persona_id);
        $persona->delete();
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



        $sql = 'SELECT torneos.id as idTorneo, CONCAT(torneos.nombre," ",torneos.year) AS nombreTorneo, "" AS escudo, "0" AS jugados, "0" AS goles, "0" AS amarillas, "0" AS rojas, "0" recibidos, "0" invictas, torneos.tipo, torneos.ambito, torneos.escudo as escudoTorneo
FROM torneos INNER JOIN grupos ON torneos.id = grupos.torneo_id
INNER JOIN plantillas ON grupos.id = plantillas.grupo_id
INNER JOIN plantilla_jugadors ON plantillas.id = plantilla_jugadors.plantilla_id
WHERE plantilla_jugadors.jugador_id = '.$id.'
GROUP BY torneos.id, torneos.nombre,torneos.year, torneos.tipo, torneos.ambito
ORDER BY torneos.year DESC, torneos.id DESC';




        $torneosJugador = DB::select(DB::raw($sql));
        $titulosJugadorCopa=0;
        $titulosJugadorLiga=0;
        $titulosJugadorInternacional=0;
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

            $posicionTorneo = PosicionTorneo::where('torneo_id', '=',$torneo->idTorneo)->where('posicion', '=',1)->first();

            if(!empty($posicionTorneo)){
                //if ($posicionTorneo->posicion == 1){

                $alineacion = Alineacion::whereIn('partido_id', explode(',', $arrpartidos))->where('equipo_id','=',$posicionTorneo->equipo_id)->where('jugador_id','=',$id)->first();




                //print_r($partidoTecnico);
                if(!empty($alineacion)) {
                    //if ((stripos($torneo->nombreTorneo, 'Copa') !== false)||(stripos($torneo->nombreTorneo, 'Trofeo') !== false)) {
                    if ($torneo->ambito == 'Nacional'){
                        if ($torneo->tipo == 'Copa') {
                            $titulosJugadorCopa++;
                        } else {
                            $titulosJugadorLiga++;
                        }
                    }
                    else{
                        $titulosJugadorInternacional++;
                    }
                }
                //}
            }



            $sqlEscudos='SELECT DISTINCT escudo, equipo_id
FROM equipos
INNER JOIN alineacions ON equipos.id = alineacions.equipo_id
INNER JOIN partidos ON partidos.id = alineacions.partido_id
WHERE alineacions.jugador_id = '.$id.' AND alineacions.partido_id IN ('.$arrpartidos.')
ORDER BY partidos.dia ASC';



            $escudos = DB::select(DB::raw($sqlEscudos));


            foreach ($escudos as $escudo){
                $strPosicion='';
                $posicionTorneo = PosicionTorneo::where('torneo_id', '=',$torneo->idTorneo)->where('equipo_id', '=',$escudo->equipo_id)->first();

                if(!empty($posicionTorneo)){

                    $alineacion = Alineacion::whereIn('partido_id', explode(',', $arrpartidos))->where('equipo_id','=',$posicionTorneo->equipo_id)->where('jugador_id','=',$id)->first();




                    //print_r($partidoTecnico);
                    if(!empty($alineacion)) {
                        $strPosicion = (!empty($posicionTorneo)) ? (
                        ($posicionTorneo->posicion == 1) ?
                            '<img id="original" src="' . asset('images/campeon.png') . '" height="20"> Campeón' :
                            (($posicionTorneo->posicion == 2) ? '<img id="original" src="' . asset('images/subcampeon.png') . '" height="20">Subcampeón' : $posicionTorneo->posicion)
                        ) : '';
                    }

                }

                $torneo->escudo .= $escudo->escudo.'_'.$escudo->equipo_id.'_'.$strPosicion.',';
                //$torneo->escudo .= $escudo->escudo.'_'.$escudo->equipo_id.',';
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


        $sql = 'SELECT torneos.id as idTorneo, CONCAT(torneos.nombre," ",torneos.year) AS nombreTorneo, "" AS escudo, "0" AS jugados, "0" AS ganados, "0" AS perdidos, "0" AS empatados, "0" AS favor, "0" AS contra, "0" AS puntaje, "0" as porcentaje, tecnicos.id as idTecnico, torneos.tipo, torneos.ambito
FROM torneos INNER JOIN grupos ON torneos.id = grupos.torneo_id
INNER JOIN fechas ON grupos.id = fechas.grupo_id
INNER JOIN partidos ON fechas.id = partidos.fecha_id
INNER JOIN partido_tecnicos ON partidos.id = partido_tecnicos.partido_id
INNER JOIN tecnicos ON tecnicos.id = partido_tecnicos.tecnico_id
WHERE tecnicos.persona_id = '.$jugador->persona_id.'
GROUP BY torneos.id, torneos.nombre,torneos.year, tecnicos.id, torneos.tipo, torneos.ambito
ORDER BY torneos.year DESC';




        $torneosTecnico = DB::select(DB::raw($sql));
        $titulosTecnicoCopa=0;
        $titulosTecnicoLiga=0;
        $titulosTecnicoInternacional=0;
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

            $posicionTorneo = PosicionTorneo::where('torneo_id', '=',$torneo->idTorneo)->where('posicion', '=',1)->first();

            if(!empty($posicionTorneo)){
                //if ($posicionTorneo->posicion == 1){
                $ultimoPartido = Partido::whereIn('fecha_id', explode(',', $arrfechas))
                    ->where(function ($query) use ($posicionTorneo) {
                        $query->where('equipol_id', $posicionTorneo->equipo_id)
                            ->orWhere('equipov_id', $posicionTorneo->equipo_id);
                    })
                    ->orderBy('dia', 'DESC')
                    ->first();
                $consultarTecnico = Tecnico::where('persona_id', '=', $jugador->persona_id)->first();
                $partidoTecnico = PartidoTecnico::where('partido_id','=',"$ultimoPartido->id")->where('equipo_id','=',$posicionTorneo->equipo_id)->where('tecnico_id','=',$consultarTecnico->id)->first();
                //print_r($partidoTecnico);
                if(!empty($partidoTecnico)) {
                    //if ((stripos($torneo->nombreTorneo, 'Copa') !== false)||(stripos($torneo->nombreTorneo, 'Trofeo') !== false)) {
                    if ($torneo->ambito == 'Nacional'){
                        if ($torneo->tipo == 'Copa') {
                            $titulosTecnicoCopa++;
                        } else {
                            $titulosTecnicoLiga++;
                        }
                    }
                    else{
                        $titulosTecnicoInternacional++;
                    }
                }
                //}
            }

            $sqlEscudos='SELECT DISTINCT escudo, equipo_id
FROM equipos
INNER JOIN partido_tecnicos ON equipos.id = partido_tecnicos.equipo_id
INNER JOIN partidos ON partidos.id = partido_tecnicos.partido_id
INNER JOIN tecnicos ON tecnicos.id = partido_tecnicos.tecnico_id
WHERE tecnicos.persona_id = '.$jugador->persona_id.' AND partido_tecnicos.partido_id IN ('.$arrpartidos.')
ORDER BY partidos.dia ASC';



            $escudos = DB::select(DB::raw($sqlEscudos));


            foreach ($escudos as $escudo){
                $strPosicion='';
                $posicionTorneo = PosicionTorneo::where('torneo_id', '=',$torneo->idTorneo)->where('equipo_id', '=',$escudo->equipo_id)->first();

                if(!empty($posicionTorneo)){
                    //if ($posicionTorneo->posicion == 1){
                    $ultimoPartido = Partido::whereIn('fecha_id', explode(',', $arrfechas))
                        ->where(function ($query) use ($posicionTorneo) {
                            $query->where('equipol_id', $posicionTorneo->equipo_id)
                                ->orWhere('equipov_id', $posicionTorneo->equipo_id);
                        })
                        ->orderBy('dia', 'DESC')
                        ->first();
                    $consultarTecnico = Tecnico::where('persona_id', '=', $jugador->persona_id)->first();
                    $partidoTecnico = PartidoTecnico::where('partido_id','=',"$ultimoPartido->id")->where('equipo_id','=',$posicionTorneo->equipo_id)->where('tecnico_id','=',$consultarTecnico->id)->first();

                    if(!empty($partidoTecnico)) {
                        $strPosicion = (!empty($posicionTorneo)) ? (
                        ($posicionTorneo->posicion == 1) ?
                            '<img id="original" src="' . asset('images/campeon.png') . '" height="20"> Campeón' :
                            (($posicionTorneo->posicion == 2) ? '<img id="original" src="' . asset('images/subcampeon.png') . '" height="20">Subcampeón' : $posicionTorneo->posicion)
                        ) : '';
                    }

                }

                $torneo->escudo .= $escudo->escudo.'_'.$escudo->equipo_id.'_'.$strPosicion.',';

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



        return view('jugadores.ver', compact('jugador','torneosJugador','torneosTecnico','titulosTecnicoLiga','titulosTecnicoCopa','titulosJugadorLiga','titulosJugadorCopa','titulosJugadorInternacional','titulosTecnicoInternacional'));
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
INNER JOIN gols ON gols.partido_id = partidos.id AND gols.jugador_id = alineacions.jugador_id
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
INNER JOIN tarjetas ON tarjetas.partido_id = partidos.id AND tarjetas.jugador_id = alineacions.jugador_id
WHERE (alineacions.jugador_id = ".$id.")";
        $sql .=($tipo=='Rojas')?" AND (tarjetas.tipo = 'Roja')":"";
        $sql .=($tipo=='Amarillas')?" AND (tarjetas.tipo = 'Amarilla')":"";

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

    public function importar(Request $request)
    {


        //
        return view('jugadores.importar');
    }

    function getHtmlContent($url) {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Para seguir redirecciones

        // Opcional: Si necesitas establecer un timeout
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Timeout de 30 segundos

        $response = curl_exec($ch);

        // Maneja errores de cURL
        if (curl_errno($ch)) {
            Log::channel('mi_log')->error('Error en cURL: ' . curl_error($ch));
            return false;
        }

        // Verificar si $response es falso, lo que indica un fallo en la ejecución
        if ($response === false) {
            Log::channel('mi_log')->error('Fallo en la solicitud cURL para la URL: ' . $url);
            curl_close($ch);
            return false;
        }

        // Obtener el código de estado HTTP
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        //dd($httpCode);
        curl_close($ch);
        // Controlar el código 404
        if ($httpCode == 404) {
            Log::channel('mi_log')->warning('Página no encontrada (404) para la URL: ' . $url);
            return false;
        }


        return $response;
    }

    public function importarProcess(Request $request)
    {
        set_time_limit(0);
        $url = $request->get('url');
        $ok = 1;
        DB::beginTransaction();
        $success='';
        $html = '';
        try {
            if ($url) {
                // Obtener el contenido de la URL
                //$htmlContent = file_get_contents($url);
                $htmlContent = $this->getHtmlContent($url);
                // Crear un nuevo DOMDocument
                $dom = new \DOMDocument();
                libxml_use_internal_errors(true); // Suprimir errores de análisis HTML
                $dom->loadHTML($htmlContent);
                libxml_clear_errors();

                // Crear un nuevo objeto XPath
                $xpath = new \DOMXPath($dom);
            }
        } catch (Exception $ex) {
            $html = '';
        }

        if ($htmlContent) {
            // Seleccionar el div con id 'previewArea' que contiene la imagen
            $fotoDiv = $xpath->query('//div[@id="previewArea"]/img');
            if ($fotoDiv->length > 0) {
                $imageUrl = $fotoDiv[0]->getAttribute('src');
                //Log::info('Foto: ' . $imageUrl, []);
            }

            // Seleccionar todos los dt y dd dentro de div.contentitem
            $dtElements = $xpath->query('//div[@class="contentitem"]/dl/dt');
            $ddElements = $xpath->query('//div[@class="contentitem"]/dl/dd');

            $nombre = '';
            $apellido = '';
            $nacimiento = '';
            $fallecimiento = '';
            $ciudad = '';
            $nacionalidad = '';
            $tipo = '';
            $altura = '';
            $peso = '';

            for ($i = 0; $i < $dtElements->length; $i++) {
                $dtText = trim($dtElements[$i]->textContent);
                $ddText = trim($ddElements[$i]->textContent);

                // Agregar los datos a la persona según el título (dt) encontrado
                switch ($dtText) {
                    case 'Nombre':
                        $nombre = $ddText;
                        break;
                    case 'Apellidos':
                        $apellido = $ddText;
                        break;
                    case 'Fecha de nacimiento':
                        $nacimiento = Carbon::createFromFormat('d-m-Y', $ddText)->format('Y-m-d');
                        break;
                    case 'Fecha de fallecimiento':
                        $fallecimiento = Carbon::createFromFormat('d-m-Y', $ddText)->format('Y-m-d');
                        break;
                    case 'Lugar de nacimiento':
                        $ciudad = $ddText;
                        break;
                    case 'Nacionalidad':
                        $nacionalidad = $ddText;
                        break;
                    case 'Demarcación':
                        switch ($ddText) {
                            case 'Portero':
                                $tipo = 'Arquero';
                                break;
                            case 'Defensa':
                                $tipo = 'Defensor';
                                break;
                            case 'Centrocampista':
                                $tipo = 'Medio';
                                break;
                            case 'Delantero':
                                $tipo = 'Delantero';
                                break;
                        }
                        break;
                    case 'Altura':
                        if (preg_match('/\d+/', $ddText, $matches)) {
                            $altura = number_format($matches[0] / 100, 2);
                        }
                        break;
                    case 'Peso':
                        if (preg_match('/\d+/', $ddText, $matches)) {
                            $peso = $matches[0];
                        }
                        break;
                    default:
                        // Manejar otros datos según sea necesario
                        break;
                }
            }

            // Descarga y guarda la imagen si no es el avatar por defecto
            if (!str_contains($imageUrl, 'avatar-player.jpg')) {
                $client = new Client();
                $response = $client->get($imageUrl);

                if ($response->getStatusCode() === 200) {
                    $imageData = $response->getBody()->getContents();
                    $parsedUrl = parse_url($imageUrl);
                    $pathInfo = pathinfo($parsedUrl['path']);
                    $nombreArchivo = $pathInfo['filename'];
                    $extension = $pathInfo['extension'];

                    if (strrchr($nombreArchivo, '.') === '.') {
                        $nombreArchivo = substr($nombreArchivo, 0, -1);
                    }

                    // Define la ubicación donde deseas guardar la imagen en tu sistema de archivos
                    $localFilePath = public_path('images/') . $nombreArchivo . '.' . $extension;
                    Log::info('URL de la foto: ' . $localFilePath, []);
                    $insert['foto'] = "$nombreArchivo.$extension";

                    file_put_contents($localFilePath, $imageData);
                    Log::info('Foto subida', []);
                } else {
                    Log::info('Foto no subida: ' . $fotoDiv[0]->getAttribute('alt'), []);
                    $success .='Foto no subida: ' . $fotoDiv[0]->getAttribute('alt').'<br>';
                }
            } else {
                Log::info('No tiene foto: ' . $imageUrl, []);
                $success .='No tiene foto: ' . $imageUrl.'<br>';
            }

            // Insertar los datos de la persona
            if ($nombre) {
                $insert['nombre'] = $nombre;
            } else {
                Log::info('Falta el nombre', []);
                $success .='Falta el nombre <br>';
            }

            if ($apellido) {
                $insert['apellido'] = $apellido;
            } else {
                Log::info('Falta el apellido', []);
                $success .='Falta el apellido <br>';
            }

            if ($ciudad) {
                $insert['ciudad'] = $ciudad;
            }
            if ($nacionalidad) {
                $insert['nacionalidad'] = $nacionalidad;
            } else {
                Log::info('Falta la nacionalidad', []);
                $success .='Falta la nacionalidad <br>';
            }
            if ($altura) {
                $insert['altura'] = $altura;
            }
            if ($peso) {
                $insert['peso'] = $peso;
            }
            if ($nacimiento) {
                $insert['nacimiento'] = $nacimiento;
            } else {
                Log::info('Falta la fecha de nacimiento', []);
                $success .='Falta la fecha de nacimiento <br>';
            }
            if ($fallecimiento) {
                $insert['fallecimiento'] = $fallecimiento;
            }
            if ($tipo) {
                $insert['tipoJugador'] = $tipo;
            } else {
                Log::info('Falta el tipo de jugador', []);
                $success .='Falta el tipo de jugador <br>';
            }

            $request->session()->put('nombre_filtro_jugador', $apellido);

            try {
                $persona = Persona::create($insert);
                $persona->jugador()->create($insert);
            } catch (QueryException $ex) {
                try {
                    $persona = Persona::where('nombre', '=', $insert['nombre'])
                        ->where('apellido', '=', $insert['apellido'])
                        ->where('nacimiento', '=', $insert['nacimiento'])
                        ->first();

                    if (!empty($persona)) {
                        $persona->update($insert);
                        $persona->jugador()->create($insert);
                    }
                } catch (QueryException $ex) {
                    $ok = 0;
                    $errorCode = $ex->errorInfo[1];

                    if ($errorCode == 1062) {
                        $error = 'Jugador repetido';
                    }
                }
            }
        } else {
            Log::info('No se encontró la URL: ' . $url, []);
            $error = 'No se encontró la URL: ' . $url;
        }

        if ($ok) {
            DB::commit();
            $respuestaID = 'success';
            $respuestaMSJ = $success;
        } else {
            DB::rollback();
            $respuestaID = 'error';
            $respuestaMSJ=$error.'<br>'.$success;
        }

        return redirect()->route('jugadores.index')->with($respuestaID, $respuestaMSJ);
    }


    public function importarProcess_old(Request $request)
    {
        set_time_limit(0);
        //Log::info('Entraaaaaa', []);

        $url = $request->get('url');
        $ok=1;
        DB::beginTransaction();



        $html='';
        try {
            if ($url){


                $html = HtmlDomParser::file_get_html($url, false, null, 0);
            }


        }
        catch (Exception $ex) {
            $html='';
        }
        if ($html){
            foreach ($html->find('div[id=previewArea]') as $element) {
                $fotoDiv = $element->find('img');

                //$foto = $fotoDiv->src;
                Log::info('Foto ' . $fotoDiv[0]->src, []);
                $imageUrl = $fotoDiv[0]->src; // Obtén la URL de la imagen desde la solicitud


            }

            $dtElements = $html->find('div.contentitem dl dt');
            $ddElements = $html->find('div.contentitem dl dd');
            $nombre='';
            $apellido='';
            $nacimiento='';
            $fallecimiento='';
            $ciudad='';
            $nacionalidad='';
            $tipo='';
            $altura='';
            $peso='';
            for ($i = 0; $i < count($dtElements); $i++) {
                $dtText = trim($dtElements[$i]->plaintext);
                $ddText = trim($ddElements[$i]->plaintext);

                // Agregar los datos a la persona según el título (dt) encontrado
                switch ($dtText) {

                    case 'Nombre':
                        $nombre = $ddText;

                        break;
                    case 'Apellidos':
                       $apellido = $ddText;
                        break;
                    case 'Fecha de nacimiento':
                        $nacimiento = Carbon::createFromFormat('d-m-Y', $ddText)->format('Y-m-d');

                        break;
                    case 'Fecha de fallecimiento':
                        $fallecimiento = Carbon::createFromFormat('d-m-Y', $ddText)->format('Y-m-d');

                        break;
                    case 'Lugar de nacimiento':
                        $ciudad = trim($ddText);

                        break;
                    case 'Nacionalidad':
                        $nacionalidad =$ddText;
                        break;
                    case 'Demarcación':
                        switch ($ddText) {
                            case 'Portero':
                                $tipo = 'Arquero';
                                break;
                            case 'Defensa':
                                $tipo = 'Defensor';
                                break;
                            case 'Centrocampista':
                                $tipo = 'Medio';
                                break;
                            case 'Delantero':
                                $tipo = 'Delantero';
                                break;
                        }


                        break;
                    case 'Altura':
                        if (preg_match('/\d+/', $ddText, $matches)) {
                            $altura=  $matches[0];
                        }
                        $altura = number_format($altura / 100, 2);

                        break;
                    case 'Peso':
                        if (preg_match('/\d+/', $ddText, $matches)) {
                            $peso=  $matches[0];
                        }

                        break;

                    default:
                        // Manejar otros datos según sea necesario
                        break;
                }
            }

            if (!str_contains($imageUrl, 'avatar-player.jpg')) {

                // Utiliza GuzzleHTTP para realizar una solicitud GET a la URL de la imagen
                $client = new Client();
                $response = $client->get($imageUrl);

                // Verifica si la solicitud fue exitosa
                if ($response->getStatusCode() === 200) {
                    $imageData = $response->getBody()->getContents();

                    // Parsea la URL para obtener la parte de la ruta
                    $parsedUrl = parse_url($imageUrl);
                    $pathInfo = pathinfo($parsedUrl['path']);

                    $nombreArchivo = $pathInfo['filename'];
                    // Obtiene la extensión del archivo
                    $extension = $pathInfo['extension'];
                    if (strrchr($nombreArchivo, '.') === '.') {
                        $nombreArchivo = substr($nombreArchivo, 0, -1);
                    }
                    // Define la ubicación donde deseas guardar la imagen en tu PC
                    $localFilePath = public_path('images/') . $nombreArchivo.'.'.$extension;
                    Log::info('Ojo!! url foto: ' . $localFilePath, []);
                    $insert['foto'] = "$nombreArchivo.$extension";
                    // Guarda la imagen en tu sistema de archivos local
                    file_put_contents($localFilePath, $imageData);

                    // Puedes retornar una respuesta de éxito u otra lógica según tus necesidades
                    Log::info('Foto subida', []);
                } else {
                    // Maneja el caso en que la descarga de la imagen no fue exitosa
                    Log::info('Ojo!! Foto no subida: ' . $fotoDiv[0]->alt, []);
                }
            }
            else{
                Log::info('OJO!!! no tiene foto: ' .$imageUrl, []);
            }
            if ($nombre){
                $insert['nombre'] = $nombre;
            }
            else{
                Log::info('OJO!!! falta el nombre', []);
            }

            if ($apellido){
                $insert['apellido'] = $apellido;
            }
            else{
                Log::info('OJO!!! falta el apellido', []);
            }
            if ($ciudad){
                $insert['ciudad'] = $ciudad;
            }
            if ($nacionalidad){
                $insert['nacionalidad'] = $nacionalidad;
            }
            else{
                Log::info('OJO!!! falta la nacionalidad', []);
            }
            if ($altura){
                $insert['altura'] = $altura;
            }
            if ($peso){
                $insert['peso'] = $peso;
            }
            if ($nacimiento){
                $insert['nacimiento'] = $nacimiento;
            }
            else{
                Log::info('OJO!!! falta el nacimiento', []);
            }
            if ($fallecimiento){
                $insert['fallecimiento'] = $fallecimiento;
            }

            if ($tipo){
                $insert['tipoJugador'] = $tipo;
            }
            else{
                Log::info('OJO!!! falta el tipo', []);
            }




            $request->session()->put('nombre_filtro_jugador', $apellido);



        try {

            $persona = Persona::create($insert);
            $persona->jugador()->create($insert);


        }catch(QueryException $ex){

            try {
                $persona = Persona::where('nombre','=',$insert['nombre'])->Where('apellido','=',$insert['apellido'])->Where('nacimiento','=',$insert['nacimiento'])->first();
                if (!empty($persona)){
                    $persona->update($insert);
                    $persona->jugador()->create($insert);

                }
            }catch(QueryException $ex){

                $ok=0;
                $errorCode = $ex->errorInfo[1];

                if ($errorCode == 1062) {
                    $error='Jugador repetido';
                }


            }


        }

        }
        else{
            Log::info('OJO!!! No se econtró la URL' .$url, []);

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
        return redirect()->route('jugadores.index')->with($respuestaID,$respuestaMSJ);

        //return view('fechas.index', compact('grupo'));
    }

    public function titulos(Request $request)
    {
        $id= $request->query('jugadorId');
        $jugador=Jugador::findOrFail($id);



        $sql = 'SELECT torneos.id as idTorneo, CONCAT(torneos.nombre," ",torneos.year) AS nombreTorneo, "" AS escudo, "0" AS jugados, "0" AS goles, "0" AS amarillas, "0" AS rojas, "0" recibidos, "0" invictas, torneos.tipo, torneos.ambito
FROM torneos INNER JOIN grupos ON torneos.id = grupos.torneo_id
INNER JOIN plantillas ON grupos.id = plantillas.grupo_id
INNER JOIN plantilla_jugadors ON plantillas.id = plantilla_jugadors.plantilla_id
INNER JOIN posicion_torneos ON torneos.id = posicion_torneos.torneo_id AND plantillas.equipo_id = posicion_torneos.equipo_id AND posicion_torneos.posicion = 1
WHERE plantilla_jugadors.jugador_id = '.$id.'
GROUP BY torneos.id, torneos.nombre,torneos.year, torneos.tipo, torneos.ambito
ORDER BY torneos.year DESC';




        $torneosJugador = DB::select(DB::raw($sql));
        $titulosJugadorCopa=0;
        $titulosJugadorLiga=0;
        $titulosJugadorInternacional=0;
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

            $posicionTorneo = PosicionTorneo::where('torneo_id', '=',$torneo->idTorneo)->where('posicion', '=',1)->first();

            if(!empty($posicionTorneo)){
                //if ($posicionTorneo->posicion == 1){

                $alineacion = Alineacion::whereIn('partido_id', explode(',', $arrpartidos))->where('equipo_id','=',$posicionTorneo->equipo_id)->where('jugador_id','=',$id)->first();




                //print_r($partidoTecnico);
                if(!empty($alineacion)) {
                    //if ((stripos($torneo->nombreTorneo, 'Copa') !== false)||(stripos($torneo->nombreTorneo, 'Trofeo') !== false)) {
                    if ($torneo->ambito == 'Nacional'){
                        if ($torneo->tipo == 'Copa') {
                            $titulosJugadorCopa++;
                        } else {
                            $titulosJugadorLiga++;
                        }
                    }
                    else{
                        $titulosJugadorInternacional++;
                    }
                }
                //}
            }



            $sqlEscudos='SELECT DISTINCT escudo, equipo_id
FROM equipos
INNER JOIN alineacions ON equipos.id = alineacions.equipo_id
INNER JOIN partidos ON partidos.id = alineacions.partido_id
WHERE alineacions.jugador_id = '.$id.' AND alineacions.partido_id IN ('.$arrpartidos.')
ORDER BY partidos.dia ASC';



            $escudos = DB::select(DB::raw($sqlEscudos));


            foreach ($escudos as $escudo){
                $strPosicion='';
                $posicionTorneo = PosicionTorneo::where('torneo_id', '=',$torneo->idTorneo)->where('equipo_id', '=',$escudo->equipo_id)->first();

                if(!empty($posicionTorneo)){

                    $alineacion = Alineacion::whereIn('partido_id', explode(',', $arrpartidos))->where('equipo_id','=',$posicionTorneo->equipo_id)->where('jugador_id','=',$id)->first();




                    //print_r($partidoTecnico);
                    if(!empty($alineacion)) {
                        $strPosicion = (!empty($posicionTorneo)) ? (
                        ($posicionTorneo->posicion == 1) ?
                            '<img id="original" src="' . asset('images/campeon.png') . '" height="20"> Campeón' :
                            (($posicionTorneo->posicion == 2) ? '<img id="original" src="' . asset('images/subcampeon.png') . '" height="20">Subcampeón' : $posicionTorneo->posicion)
                        ) : '';
                    }

                }

                $torneo->escudo .= $escudo->escudo.'_'.$escudo->equipo_id.'_'.$strPosicion.',';
                //$torneo->escudo .= $escudo->escudo.'_'.$escudo->equipo_id.',';
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


        $sql = 'SELECT torneos.id as idTorneo, CONCAT(torneos.nombre," ",torneos.year) AS nombreTorneo, "" AS escudo, "0" AS jugados, "0" AS ganados, "0" AS perdidos, "0" AS empatados, "0" AS favor, "0" AS contra, "0" AS puntaje, "0" as porcentaje, tecnicos.id as idTecnico, torneos.tipo, torneos.ambito
FROM torneos INNER JOIN grupos ON torneos.id = grupos.torneo_id
INNER JOIN fechas ON grupos.id = fechas.grupo_id
INNER JOIN partidos ON fechas.id = partidos.fecha_id
INNER JOIN partido_tecnicos ON partidos.id = partido_tecnicos.partido_id
INNER JOIN tecnicos ON tecnicos.id = partido_tecnicos.tecnico_id
WHERE tecnicos.persona_id = '.$jugador->persona_id.'
GROUP BY torneos.id, torneos.nombre,torneos.year, tecnicos.id, torneos.tipo, torneos.ambito
ORDER BY torneos.year DESC';




        $torneosTecnico = DB::select(DB::raw($sql));
        $titulosTecnicoCopa=0;
        $titulosTecnicoLiga=0;
        $titulosTecnicoInternacional=0;
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

            $posicionTorneo = PosicionTorneo::where('torneo_id', '=',$torneo->idTorneo)->where('posicion', '=',1)->first();

            if(!empty($posicionTorneo)){
                //if ($posicionTorneo->posicion == 1){
                $ultimoPartido = Partido::whereIn('fecha_id', explode(',', $arrfechas))
                    ->where(function ($query) use ($posicionTorneo) {
                        $query->where('equipol_id', $posicionTorneo->equipo_id)
                            ->orWhere('equipov_id', $posicionTorneo->equipo_id);
                    })
                    ->orderBy('dia', 'DESC')
                    ->first();
                $consultarTecnico = Tecnico::where('persona_id', '=', $jugador->persona_id)->first();
                $partidoTecnico = PartidoTecnico::where('partido_id','=',"$ultimoPartido->id")->where('equipo_id','=',$posicionTorneo->equipo_id)->where('tecnico_id','=',$consultarTecnico->id)->first();
                //print_r($partidoTecnico);
                if(!empty($partidoTecnico)) {
                    //if ((stripos($torneo->nombreTorneo, 'Copa') !== false)||(stripos($torneo->nombreTorneo, 'Trofeo') !== false)) {
                    if ($torneo->ambito == 'Nacional'){
                        if ($torneo->tipo == 'Copa') {
                            $titulosTecnicoCopa++;
                        } else {
                            $titulosTecnicoLiga++;
                        }
                    }
                    else{
                        $titulosTecnicoInternacional++;
                    }
                }
                //}
            }

            $sqlEscudos='SELECT DISTINCT escudo, equipo_id
FROM equipos
INNER JOIN partido_tecnicos ON equipos.id = partido_tecnicos.equipo_id
INNER JOIN partidos ON partidos.id = partido_tecnicos.partido_id
INNER JOIN tecnicos ON tecnicos.id = partido_tecnicos.tecnico_id
WHERE tecnicos.persona_id = '.$jugador->persona_id.' AND partido_tecnicos.partido_id IN ('.$arrpartidos.')
ORDER BY partidos.dia ASC';



            $escudos = DB::select(DB::raw($sqlEscudos));


            foreach ($escudos as $escudo){
                $strPosicion='';
                $posicionTorneo = PosicionTorneo::where('torneo_id', '=',$torneo->idTorneo)->where('equipo_id', '=',$escudo->equipo_id)->first();

                if(!empty($posicionTorneo)){
                    //if ($posicionTorneo->posicion == 1){
                    $ultimoPartido = Partido::whereIn('fecha_id', explode(',', $arrfechas))
                        ->where(function ($query) use ($posicionTorneo) {
                            $query->where('equipol_id', $posicionTorneo->equipo_id)
                                ->orWhere('equipov_id', $posicionTorneo->equipo_id);
                        })
                        ->orderBy('dia', 'DESC')
                        ->first();
                    $consultarTecnico = Tecnico::where('persona_id', '=', $jugador->persona_id)->first();
                    $partidoTecnico = PartidoTecnico::where('partido_id','=',"$ultimoPartido->id")->where('equipo_id','=',$posicionTorneo->equipo_id)->where('tecnico_id','=',$consultarTecnico->id)->first();

                    if(!empty($partidoTecnico)) {
                        $strPosicion = (!empty($posicionTorneo)) ? (
                        ($posicionTorneo->posicion == 1) ?
                            '<img id="original" src="' . asset('images/campeon.png') . '" height="20"> Campeón' :
                            (($posicionTorneo->posicion == 2) ? '<img id="original" src="' . asset('images/subcampeon.png') . '" height="20">Subcampeón' : $posicionTorneo->posicion)
                        ) : '';
                    }

                }

                $torneo->escudo .= $escudo->escudo.'_'.$escudo->equipo_id.'_'.$strPosicion.',';

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



        return view('jugadores.titulos', compact('jugador','torneosJugador','torneosTecnico','titulosTecnicoLiga','titulosTecnicoCopa','titulosJugadorLiga','titulosJugadorCopa','titulosJugadorInternacional','titulosTecnicoInternacional'));
    }

    private function sonApellidosSimilares($apellido1, $apellido2)
    {
        $apellido1 = strtolower($apellido1);
        $apellido2 = strtolower($apellido2);

        // Verificar si uno de los apellidos es subcadena del otro
        if (strpos($apellido1, $apellido2) !== false || strpos($apellido2, $apellido1) !== false) {
            return true;
        }

        // Usar similar_text para comparar la similitud de los apellidos
        similar_text($apellido1, $apellido2, $percent);
        return $percent > 80; // Ajusta este umbral según tus necesidades
    }

    public function verificarPersonas(Request $request)
    {

        $verificados= ($request->query('verificados'))?1:0;
        // Obtener todas las personas de la base de datos
        if ($verificados){
            $personas = Persona::orderBy('apellido','ASC')->get();
        }
        else{
            $personas = Persona::where('verificado', false)->orderBy('apellido','ASC')->get();
        }



        // Separar personas con y sin fecha de nacimiento
        $personasConFechaNacimiento = $personas->filter(function ($persona) {
            return !is_null($persona->nacimiento);
        });

        $personasSinFechaNacimiento = $personas->filter(function ($persona) {
            return is_null($persona->nacimiento);
        });

        /*$personasSinFoto = $personas->filter(function ($persona) {
            return is_null($persona->foto);
        });*/
        $personasSinFoto =array();
        // Agrupar personas por fecha de nacimiento
        $personasPorFechaNacimiento = $personasConFechaNacimiento->groupBy('nacimiento');

        // Colección para almacenar personas con apellidos similares
        $resultados = collect();

        // Verificar personas con la misma fecha de nacimiento y apellidos similares
        foreach ($personasPorFechaNacimiento as $grupo) {
            foreach ($grupo as $persona) {
                $similares = $grupo->filter(function ($item) use ($persona) {
                    // Verificar si el apellido de la persona actual es similar al de las otras personas
                    return $item->id !== $persona->id && $this->sonApellidosSimilares($item->apellido, $persona->apellido);
                });

                if ($similares->isNotEmpty()) {
                    // Si hay personas con apellido similar, agregar la persona actual y los similares a los resultados
                    $resultados = $resultados->merge([$persona])->merge($similares);
                }
            }
        }

        // Eliminar duplicados
        $resultados = $resultados->unique('id');


        return view('jugadores.verificarPersona', ['personas' => $resultados,'sinNacimiento'=>$personasSinFechaNacimiento,'sinFoto'=>$personasSinFoto, 'verificados' => $verificados]);
    }


    public function reasignar($id)
    {
        $jugador=Jugador::findOrFail($id);

        return view('jugadores.reasignar', compact('jugador'));
    }
}
