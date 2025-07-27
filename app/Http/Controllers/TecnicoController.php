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
use App\Services\HttpHelper;
use App\Tecnico;
use App\Torneo;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use DB;
use Illuminate\Support\Facades\Log;

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

        if ($request->has('buscarpor')){
            $nombre = $request->get('buscarpor');

            $request->session()->put('nombre_filtro_tecnico', $request->get('buscarpor'));

        }
        else{
            $nombre = $request->session()->get('nombre_filtro_tecnico');

        }

        //$tecnicos=Tecnico::where('nombre','like',"%$nombre%")->orWhere('apellido','like',"%$nombre%")->orWhere('email','like',"%$nombre%")->orWhere(DB::raw('TIMESTAMPDIFF(YEAR,nacimiento,CURDATE())'),'=',"$nombre")->orderBy('apellido','ASC')->paginate();


        $tecnicos=Tecnico::SELECT('tecnicos.*','personas.name','personas.nombre','personas.apellido','personas.nacimiento','personas.fallecimiento','personas.email','personas.foto')->Join('personas','personas.id','=','tecnicos.persona_id')->where('nombre','like',"%$nombre%")->orWhere('apellido','like',"%$nombre%")->orWhere('name','like',"%$nombre%")->orWhere(DB::raw('TIMESTAMPDIFF(YEAR,nacimiento,CURDATE())'),'=',"$nombre")->orderBy('apellido','ASC')->paginate();

        return view('tecnicos.index', compact('tecnicos'));
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
        $this->validate($request,[ 'name'=>'required','nombre'=>'required', 'apellido'=>'required','foto' => 'image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048']);


        if ($files = $request->file('foto')) {
            $image = $request->file('foto');
            $name = time().'.'.$image->getClientOriginalExtension();
            $destinationPath = public_path('/images');
            $image->move($destinationPath, $name);



            $insert['foto'] = "$name";
        }

        $insert['name'] = $request->get('name');
        $insert['nombre'] = $request->get('nombre');
        $insert['apellido'] = $request->get('apellido');
        $insert['email'] = $request->get('email');
        $insert['telefono'] = $request->get('telefono');
        $insert['ciudad'] = $request->get('ciudad');
        $insert['nacionalidad'] = $request->get('nacionalidad');
        $insert['observaciones'] = $request->get('observaciones');
        /*$insert['tipoDocumento'] = $request->get('tipoDocumento');
        $insert['documento'] = $request->get('documento');*/
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
                    $respuestaMSJ='Registro creado satisfactoriamente - existe como jugador';
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
        $this->validate($request,[ 'name'=>'required','nombre'=>'required', 'apellido'=>'required','foto' => 'image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048']);


        if ($files = $request->file('foto')) {
            $image = $request->file('foto');
            $name = time().'.'.$image->getClientOriginalExtension();
            $destinationPath = public_path('/images');
            $image->move($destinationPath, $name);



            $update['foto'] = "$name";
        }

        $update['name'] = $request->get('name');
        $update['nombre'] = $request->get('nombre');
        $update['apellido'] = $request->get('apellido');
        $update['email'] = $request->get('email');
        $update['telefono'] = $request->get('telefono');
        $update['ciudad'] = $request->get('ciudad');
        $update['nacionalidad'] = $request->get('nacionalidad');
        $update['observaciones'] = $request->get('observaciones');
        /*$update['tipoDocumento'] = $request->get('tipoDocumento');
        $update['documento'] = $request->get('documento');*/
        $update['nacimiento'] = $request->get('nacimiento');
        $update['fallecimiento'] = $request->get('fallecimiento');
        $update['verificado'] = $request->get('verificado');

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
        $persona = Persona::find($tecnico->persona_id);
        // Verificar si la persona tiene una foto y eliminarla del servidor
        if ($persona->foto && file_exists(public_path('images/' . $persona->foto))) {
            unlink(public_path('images/' . $persona->foto)); // Eliminar la foto del servidor
        }
        $persona->delete();
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

        $sql = 'SELECT torneos.id as idTorneo, CONCAT(torneos.nombre," ",torneos.year) AS nombreTorneo, "" AS escudo, "0" AS jugados, "0" AS ganados, "0" AS perdidos, "0" AS empatados, "0" AS favor, "0" AS contra, "0" AS puntaje, "0" as porcentaje, torneos.tipo, torneos.ambito, torneos.escudo as escudoTorneo
FROM torneos INNER JOIN grupos ON torneos.id = grupos.torneo_id
INNER JOIN fechas ON grupos.id = fechas.grupo_id
INNER JOIN partidos ON fechas.id = partidos.fecha_id
INNER JOIN partido_tecnicos ON partidos.id = partido_tecnicos.partido_id
WHERE partido_tecnicos.tecnico_id = '.$id.'
GROUP BY torneos.id, torneos.nombre,torneos.year, torneos.tipo, torneos.ambito
ORDER BY torneos.year DESC, torneos.id DESC';




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

                    $partidoTecnico = PartidoTecnico::where('partido_id','=',"$ultimoPartido->id")->where('equipo_id','=',$posicionTorneo->equipo_id)->where('tecnico_id','=',$id)->first();
                //print_r($partidoTecnico);
                    if(!empty($partidoTecnico)) {
                        //if ((stripos($torneo->nombreTorneo, 'Copa') !== false)||(stripos($torneo->nombreTorneo, 'Trofeo') !== false)) {
                        if ($torneo->ambito == 'Nacional') {
                            if ($torneo->tipo == 'Copa') {
                                $titulosTecnicoCopa++;
                            } else {
                                $titulosTecnicoLiga++;
                            }
                        }else {
                            $titulosTecnicoInternacional++;
                        }
                    }
                //}
            }



            $sqlEscudos='SELECT DISTINCT escudo, equipo_id
FROM equipos
INNER JOIN partido_tecnicos ON equipos.id = partido_tecnicos.equipo_id
INNER JOIN partidos ON partidos.id = partido_tecnicos.partido_id
WHERE partido_tecnicos.tecnico_id = '.$id.' AND partido_tecnicos.partido_id IN ('.$arrpartidos.') ORDER BY partidos.dia ASC';



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

                    $partidoTecnico = PartidoTecnico::where('partido_id','=',"$ultimoPartido->id")->where('equipo_id','=',$posicionTorneo->equipo_id)->where('tecnico_id','=',$id)->first();

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

        $sql = 'SELECT torneos.id as idTorneo, CONCAT(torneos.nombre," ",torneos.year) AS nombreTorneo, "" AS escudo, "0" AS jugados, "0" AS goles, "0" AS amarillas, "0" AS rojas, "0" recibidos, "0" invictas, "" as idJugador, torneos.tipo, torneos.ambito, torneos.escudo as escudoTorneo
FROM torneos INNER JOIN grupos ON torneos.id = grupos.torneo_id
INNER JOIN plantillas ON grupos.id = plantillas.grupo_id
INNER JOIN plantilla_jugadors ON plantillas.id = plantilla_jugadors.plantilla_id
INNER JOIN jugadors ON jugadors.id = plantilla_jugadors.jugador_id
WHERE jugadors.persona_id = '.$tecnico->persona_id.'
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
                $consultarJugador = Jugador::where('persona_id', '=', $tecnico->persona_id)->first();
                $alineacion = Alineacion::whereIn('partido_id', explode(',', $arrpartidos))->where('equipo_id','=',$posicionTorneo->equipo_id)->where('jugador_id','=',$consultarJugador->id)->first();




                //print_r($partidoTecnico);
                if(!empty($alineacion)) {
                    //if ((stripos($torneo->nombreTorneo, 'Copa') !== false)||(stripos($torneo->nombreTorneo, 'Trofeo') !== false)) {
                    if ($torneo->ambito == 'Nacional') {
                        if ($torneo->tipo == 'Copa') {
                            $titulosJugadorCopa++;
                        } else {
                            $titulosJugadorLiga++;
                        }
                    } else {
                        $titulosJugadorInternacional++;
                    }
                }
                //}
            }

            $sqlEscudos='SELECT DISTINCT escudo, equipo_id
FROM equipos
INNER JOIN alineacions ON equipos.id = alineacions.equipo_id
INNER JOIN partidos ON partidos.id = alineacions.partido_id
INNER JOIN jugadors ON jugadors.id = alineacions.jugador_id
WHERE jugadors.persona_id = '.$tecnico->persona_id.' AND alineacions.partido_id IN ('.$arrpartidos.') ORDER BY partidos.dia ASC';



            $escudos = DB::select(DB::raw($sqlEscudos));


            foreach ($escudos as $escudo){
                $strPosicion='';
                $posicionTorneo = PosicionTorneo::where('torneo_id', '=',$torneo->idTorneo)->where('equipo_id', '=',$escudo->equipo_id)->first();

                if(!empty($posicionTorneo)){
                    $consultarJugador = Jugador::where('persona_id', '=', $tecnico->persona_id)->first();
                    $alineacion = Alineacion::whereIn('partido_id', explode(',', $arrpartidos))->where('equipo_id','=',$posicionTorneo->equipo_id)->where('jugador_id','=',$consultarJugador->id)->first();




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


        return view('tecnicos.ver', compact('tecnico', 'torneosTecnico', 'torneosJugador','titulosTecnicoLiga','titulosTecnicoCopa','titulosJugadorLiga','titulosJugadorCopa','titulosJugadorInternacional','titulosTecnicoInternacional'));
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

    public function importar(Request $request)
    {


        //
        return view('tecnicos.importar');
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
                $htmlContent =  HttpHelper::getHtmlContent($url, true);
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
            // Buscar el div con id 'sidebar'
            $sidebarDiv = $xpath->query('//div[@class="sidebar"]')->item(0);

            if ($sidebarDiv) {
                // Seleccionar el div con id 'previewArea' que contiene la imagen

                $fotoDiv = $xpath->query('.//div[@class="data"]/img', $sidebarDiv);
                //log::info(print_r($fotoDiv, true));
                if ($fotoDiv->length > 0) {

                    //$imageUrl = $fotoDiv[0]->getAttribute('data-cfsrc');
                    $imageUrl = $fotoDiv[0]->getAttribute('src');

                    //dd($imageUrl);
                    //Log::info('Foto: ' . $imageUrl, []);
                }


                $name = null;
                $nombre = '';
                $apellido = '';
                $nacimiento = '';
                $fallecimiento = '';
                $ciudad = '';
                $nacionalidad = '';


                $nameNode = $xpath->query('.//div[@class="head"]/h2', $sidebarDiv)->item(0);
                $name = $nameNode ? trim($nameNode->nodeValue) : null;

                // Obtener el nombre completo si existe en <td colspan="2" align="center">
                $fullNameNode = $xpath->query('.//div[@class="data"]//td[@colspan="2" and @align="center"]', $sidebarDiv)->item(0);
                $apellido = $fullNameNode ? trim($fullNameNode->textContent) : '';

                // 3. Obtener las filas de la tabla
                $rows = $xpath->query('.//table[@class="standard_tabelle yellow"]//tr', $sidebarDiv);

                foreach ($rows as $row) {
                    $td1 = $xpath->query('.//td[1]', $row)->item(0);
                    $label = trim($td1 ? $td1->textContent : '');

                    $valueCell = $xpath->query('.//td[2]', $row)->item(0);
                    $value = trim($valueCell ? $valueCell->textContent : '');


                    // Agregar los datos a la persona según el título (dt) encontrado
                    switch ($label) {
                        case 'Nombre completo:':

                            $apellido = $value;
                            break;

                        case 'Fecha de nacimiento:':
                            if (!empty($value)) {
                                $nacimiento = Carbon::createFromFormat('d.m.Y', $value)->format('Y-m-d');
                            }
                            break;

                        case 'Fecha de fallecimiento:':
                            if (!empty($value)) {
                                $fallecimiento = Carbon::createFromFormat('d.m.Y', $value)->format('Y-m-d');
                            }
                            break;
                        case 'Lugar de nacimiento:':
                            $ciudad = $value;
                            break;
                        case 'Nacionalidad:':
                            $nacionalidad = $value;
                            break;

                        default:
                            // Manejar otros datos según sea necesario
                            break;
                    }
                }
            }

            // Descarga y guarda la imagen si no es el avatar por defecto
            if (!empty($imageUrl) && !str_contains($imageUrl, 'avatar-player.jpg')) {
                try {
                    $client = new Client();

                    //$response = $client->get($imageUrl);
                    // Intentar obtener la imagen con reintentos y asegurarnos de que Guzzle lanza excepciones en caso de error HTTP
                    $response = $client->get($imageUrl, [
                        'http_errors' => false,  // No lanzar excepción en errores HTTP (como 404)
                        'timeout' => 10, // Tiempo máximo de espera
                    ]);

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
                } catch (RequestException $e) {
                    // Capturar la excepción y continuar con el flujo
                    Log::error('Error al intentar obtener la imagen: ' . $e->getMessage(), []);
                    $insert['foto'] = null;
                }
            } else {
                Log::info('No tiene foto: ' . $imageUrl, []);
                $success .='No tiene foto: ' . $imageUrl.'<br>';
            }
            //dd($name,$imageUrl,$apellido,$nacimiento,$fallecimiento,$ciudad,$nacionalidad);
            // Insertar los datos de la persona
            if ($name) {
                $insert['name'] = trim($name);
            } else {
                Log::info('Falta el name', []);
                $success .='Falta el name <br>';
            }
            /*if ($nombre) {
                $insert['nombre'] = $nombre;
            } else {
                Log::info('Falta el nombre', []);
                $success .='Falta el nombre <br>';
            }*/

            if ($apellido) {
                $insert['apellido'] = trim($apellido);
            } else {
                Log::info('Falta el apellido', []);
                $success .='Falta el apellido <br>';
            }

            if ($ciudad) {
                $insert['ciudad'] = trim($ciudad);
            }
            if ($nacionalidad) {
                $insert['nacionalidad'] = trim($nacionalidad);
            } else {
                Log::info('Falta la nacionalidad', []);
                $success .='Falta la nacionalidad <br>';
            }

            if ($nacimiento) {
                $insert['nacimiento'] = trim($nacimiento);
            } else {
                Log::info('Falta la fecha de nacimiento', []);
                $success .='Falta la fecha de nacimiento <br>';
            }
            if ($fallecimiento) {
                $insert['fallecimiento'] = trim($fallecimiento);
            }


            $request->session()->put('nombre_filtro_tecnico', $name);
            //log::info(print_r($insert, true));
            try {
                $persona = Persona::create($insert);
                $persona->tecnico()->create($insert);
            } catch (QueryException $ex) {
                try {
                    $persona = Persona::where('nombre', '=', $insert['nombre'])
                        ->where('apellido', '=', $insert['apellido'])
                        ->where('nacimiento', '=', $insert['nacimiento'])
                        ->first();

                    if (!empty($persona)) {
                        if (!empty($persona->nacionalidad)) {
                            unset($insert['nacionalidad']);
                        }
                        $persona->update($insert);
                        $persona->tecnico()->create($insert);
                    }
                } catch (QueryException $ex) {
                    //$ok = 0;
                    $errorCode = $ex->errorInfo[1];

                    if ($errorCode == 1062) {
                        $success .= 'Tecnico repetido';
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

        return redirect()->route('tecnicos.index')->with($respuestaID, $respuestaMSJ);
    }

    public function reasignar($id)
    {
        $tecnico=Tecnico::findOrFail($id);

        return view('tecnicos.reasignar', compact('tecnico'));
    }

    public function guardarReasignar(Request $request)
    {
        // Validar los datos de entrada
        $request->validate([
            'tecnicoId' => 'required|integer|exists:tecnicos,id',
            'reasignarId' => 'required|integer|exists:jugadors,id|different:jugadorId',
        ]);

        $tecnicoId = $request->input('tecnicoId');
        $jugadorNuevoId = $request->input('reasignarId');

        try {
            // Inicia una transacción para garantizar que todas las actualizaciones se completen
            DB::beginTransaction();

            $tecnico = Tecnico::findOrFail($tecnicoId);
            $jugadorNuevo = Jugador::findOrFail($jugadorNuevoId);
            $personaNueva = Persona::findOrFail($jugadorNuevo->persona_id);

            // Guardar persona anterior antes de sobrescribir
            $personaAnterior = Persona::find($tecnico->persona_id);

            // Reasignar persona al técnico
            $tecnico->persona_id = $personaNueva->id;
            $tecnico->save();

            // Eliminar persona anterior (opcional: verificar relaciones primero)
            if ($personaAnterior) {
                // Eliminar la foto si existe
                if ($personaAnterior->foto && file_exists(public_path('images/' . $personaAnterior->foto))) {
                    // unlink(public_path('images/' . $personaAnterior->foto)); // Descomenta si deseas eliminarla
                }

                $personaAnterior->delete();
            }

            DB::commit();

            // Redirigir con un mensaje de éxito
            return redirect()->route('jugadores.verificarPersonas')->with('success', 'Técnico reasignado exitosamente.');
        } catch (\Exception $e) {
            // Revertir los cambios si hay algún error
            DB::rollBack();

            // Regresar con un mensaje de error
            return redirect()->back()->withErrors(['error' => 'Hubo un problema al reasignar el jugador.']);
        }
    }

}
