<?php

namespace App\Http\Controllers;

use App\Equipo;
use App\Fecha;
use App\Partido;
use App\PlantillaJugador;
use App\Plantilla;
use App\Torneo;
use Illuminate\Http\Request;
use App\Grupo;

use DB;
use Illuminate\Support\Facades\Log;


class GrupoController extends Controller
{

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //$this->middleware('auth');
        $this->middleware('auth')->except(['posicionesPublic','goleadoresPublic','tarjetasPublic', 'arqueros', 'metodo','jugadores']);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
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
        $this->validate($request,[ 'nombre'=>'required', 'torneo_id'=>'required', 'equipos'=>'required']);

        Grupo::create($request->all());
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
    public function update(Request $request, $id)
    {
        //
    }


    public function posiciones(Request $request)
    {
        $grupo_id= $request->query('grupoId');
        $grupo=Grupo::findOrFail($grupo_id);
        $arrMetodo = array();

        $sql='SELECT foto, equipo,
       count(*) jugados,
       count(case when golesl > golesv then 1 end) ganados,
       count(case when golesv > golesl then 1 end) perdidos,
       count(case when golesl = golesv then 1 end) empatados,
       sum(golesl) golesl,
       sum(golesv) golesv,
       sum(golesl) - sum(golesv) diferencia,
       sum(
             case when golesl > golesv then 3 else 0 end
           + case when golesl = golesv then 1 else 0 end
       ) puntaje
from (
       select  DISTINCT equipos.nombre equipo, golesl, golesv, equipos.escudo foto, fechas.id
		 from partidos
		 INNER JOIN equipos ON partidos.equipol_id = equipos.id
		 INNER JOIN plantillas ON plantillas.equipo_id = equipos.id
		 INNER JOIN fechas ON partidos.fecha_id = fechas.id
		 INNER JOIN grupos ON fechas.grupo_id = grupos.id AND grupos.posiciones = 1 AND grupos.torneo_id = '.$grupo->torneo->id.' AND grupos.agrupacion = '.$grupo->agrupacion.'
		 WHERE golesl is not null AND golesv is not null AND EXISTS (SELECT p2.id FROM plantillas p2 WHERE plantillas.equipo_id = p2.equipo_id AND p2.grupo_id = '.$grupo_id.')
     union all
       select DISTINCT equipos.nombre equipo, golesv, golesl, equipos.escudo foto, fechas.id
		 from partidos
		 INNER JOIN equipos ON partidos.equipov_id = equipos.id
		 INNER JOIN plantillas ON plantillas.equipo_id = equipos.id
		 INNER JOIN fechas ON partidos.fecha_id = fechas.id
		 INNER JOIN grupos ON fechas.grupo_id = grupos.id AND grupos.posiciones = 1 AND grupos.torneo_id = '.$grupo->torneo->id.' AND grupos.agrupacion = '.$grupo->agrupacion.'
		 WHERE golesl is not null AND golesv is not null AND EXISTS (SELECT p2.id FROM plantillas p2 WHERE plantillas.equipo_id = p2.equipo_id AND p2.grupo_id = '.$grupo_id.')
) a
group by equipo, foto

order by puntaje desc, diferencia DESC, golesl DESC, equipo ASC';

        $posiciones = DB::select(DB::raw($sql));



        //dd($posiciones);
        //echo $sql;
        $i=1;
        return view('grupos.posiciones', compact('grupo','posiciones','i'));
    }

    public function posicionesPublic(Request $request)
    {
        $torneo_id= $request->query('torneoId');
        $torneo=Torneo::findOrFail($torneo_id);

        $grupos = Grupo::where('torneo_id', '=',$torneo_id)->get();

        $arrPosiciones = array();

        foreach ($grupos as $grupo){
            if ($grupo->posiciones){
                $sql='SELECT foto, equipo,
       count(*) jugados,
       count(case when golesl > golesv then 1 end) ganados,
       count(case when golesv > golesl then 1 end) perdidos,
       count(case when golesl = golesv then 1 end) empatados,
       sum(golesl) golesl,
       sum(golesv) golesv,
       sum(golesl) - sum(golesv) diferencia,
       sum(
             case when golesl > golesv then 3 else 0 end
           + case when golesl = golesv then 1 else 0 end
       ) puntaje, equipo_id
from (
       select  DISTINCT equipos.nombre equipo, golesl, golesv, equipos.escudo foto, fechas.id fecha_id, equipos.id equipo_id
		 from partidos
		 INNER JOIN equipos ON partidos.equipol_id = equipos.id
		 INNER JOIN plantillas ON plantillas.equipo_id = equipos.id
		 INNER JOIN fechas ON partidos.fecha_id = fechas.id
		 INNER JOIN grupos ON fechas.grupo_id = grupos.id AND grupos.posiciones = 1 AND grupos.torneo_id = '.$grupo->torneo->id.' AND grupos.agrupacion = '.$grupo->agrupacion.'
		 WHERE golesl is not null AND golesv is not null AND EXISTS (SELECT p2.id FROM plantillas p2 WHERE plantillas.equipo_id = p2.equipo_id AND p2.grupo_id = '.$grupo->id.')
     union all
       select DISTINCT equipos.nombre equipo, golesv, golesl, equipos.escudo foto, fechas.id fecha_id, equipos.id equipo_id
		 from partidos
		 INNER JOIN equipos ON partidos.equipov_id = equipos.id
		 INNER JOIN plantillas ON plantillas.equipo_id = equipos.id
		 INNER JOIN fechas ON partidos.fecha_id = fechas.id
		 INNER JOIN grupos ON fechas.grupo_id = grupos.id AND grupos.posiciones = 1 AND grupos.torneo_id = '.$grupo->torneo->id.' AND grupos.agrupacion = '.$grupo->agrupacion.'
		 WHERE golesl is not null AND golesv is not null AND EXISTS (SELECT p2.id FROM plantillas p2 WHERE plantillas.equipo_id = p2.equipo_id AND p2.grupo_id = '.$grupo->id.')
) a
group by equipo, foto, equipo_id

order by puntaje desc, diferencia DESC, golesl DESC, equipo ASC';

                $posiciones = DB::select(DB::raw($sql));

                $arrPosiciones[$grupo->nombre]=$posiciones;
            }

        }







        return view('grupos.posicionesPublic', compact('torneo','arrPosiciones'));
    }

    public function goleadores(Request $request)
    {
        $torneo_id= $request->query('torneoId');
        $torneo=Torneo::findOrFail($torneo_id);

        $grupos = Grupo::where('torneo_id', '=',$torneo_id)->get();
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

        $sql = 'SELECT jugadors.id, CONCAT(personas.apellido,\', \',personas.nombre) jugador, COUNT(gols.id) goles, count( case when tipo=\'Jugada\' then 1 else NULL end) as  Jugada, "" as foto
, count( case when tipo=\'Cabeza\' then 1 else NULL end) as  Cabeza, count( case when tipo=\'Penal\' then 1 else NULL end) as  Penal, count( case when tipo=\'Tiro Libre\' then 1 else NULL end) as  Tiro_Libre
FROM gols
INNER JOIN jugadors ON gols.jugador_id = jugadors.id
INNER JOIN personas ON jugadors.persona_id = personas.id
INNER JOIN partidos ON gols.partido_id = partidos.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id

WHERE gols.tipo <> \'En contra\' AND grupos.torneo_id='.$torneo_id.' AND grupos.id IN ('.$arrgrupos.')
GROUP BY jugadors.id,jugador
ORDER BY goles DESC, jugador ASC';




        $goleadores = DB::select(DB::raw($sql));



        $page = $request->query('page', 1);

        $paginate = 15;

        $offSet = ($page * $paginate) - $paginate;

        $itemsForCurrentPage = array_slice($goleadores, $offSet, $paginate, true);



        $goleadores = new \Illuminate\Pagination\LengthAwarePaginator($itemsForCurrentPage, count($goleadores), $paginate, $page);


        foreach ($goleadores as $goleador){

            $sql2='SELECT DISTINCT escudo
FROM equipos
INNER JOIN alineacions ON equipos.id = alineacions.equipo_id
INNER JOIN partidos ON partidos.id = alineacions.partido_id
WHERE alineacions.jugador_id = '.$goleador->id.' AND alineacions.partido_id IN ('.$arrpartidos.')
 ORDER BY partidos.dia ASC';



            $escudos = DB::select(DB::raw($sql2));


            foreach ($escudos as $escudo){

                $goleador->foto .= $escudo->escudo.',';
            }

        }

        $goleadores->setPath(route('grupos.goleadoresPublic',  array('torneoId' => $torneo->id)));



        $i=$offSet+1;


        return view('grupos.goleadores', compact('torneo','goleadores','i'));
    }

    public function goleadoresPublic(Request $request)
    {
        $torneo_id= $request->query('torneoId');
        $torneo=Torneo::findOrFail($torneo_id);

        $order= ($request->query('order'))?$request->query('order'):'goles';
        $tipoOrder= ($request->query('tipoOrder'))?$request->query('tipoOrder'):'DESC';

        $grupos = Grupo::where('torneo_id', '=',$torneo_id)->get();
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

        $sql = 'SELECT jugadors.id, CONCAT(personas.apellido,\', \',personas.nombre) jugador, COUNT(gols.id) goles, count( case when tipo=\'Jugada\' then 1 else NULL end) as  Jugada, "" as escudo, personas.foto, "0" as jugados
, count( case when tipo=\'Cabeza\' then 1 else NULL end) as  Cabeza, count( case when tipo=\'Penal\' then 1 else NULL end) as  Penal, count( case when tipo=\'Tiro Libre\' then 1 else NULL end) as  Tiro_Libre
FROM gols
INNER JOIN jugadors ON gols.jugador_id = jugadors.id
INNER JOIN personas ON jugadors.persona_id = personas.id
INNER JOIN partidos ON gols.partido_id = partidos.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id

WHERE gols.tipo <> \'En contra\' AND grupos.torneo_id='.$torneo_id.' AND grupos.id IN ('.$arrgrupos.')
GROUP BY jugadors.id,jugador, foto
ORDER BY '.$order.' '.$tipoOrder.', jugador ASC';




        $goleadores = DB::select(DB::raw($sql));



        $page = $request->query('page', 1);

        $paginate = 15;

        $offSet = ($page * $paginate) - $paginate;

        $itemsForCurrentPage = array_slice($goleadores, $offSet, $paginate, true);



        $goleadores = new \Illuminate\Pagination\LengthAwarePaginator($itemsForCurrentPage, count($goleadores), $paginate, $page);


        foreach ($goleadores as $goleador){

            $sql2='SELECT DISTINCT escudo, equipo_id
FROM equipos
INNER JOIN alineacions ON equipos.id = alineacions.equipo_id
INNER JOIN partidos ON partidos.id = alineacions.partido_id
WHERE alineacions.jugador_id = '.$goleador->id.' AND alineacions.partido_id IN ('.$arrpartidos.') ORDER BY partidos.dia ASC';



            $escudos = DB::select(DB::raw($sql2));


            foreach ($escudos as $escudo){

                $goleador->escudo .= $escudo->escudo.'_'.$escudo->equipo_id.',';
            }

            $sql3="SELECT alineacions.jugador_id, COUNT(alineacions.jugador_id) as jugados
FROM torneos t2 INNER JOIN grupos g2 ON t2.id = g2.torneo_id
INNER JOIN fechas ON fechas.grupo_id = g2.id
INNER JOIN partidos ON partidos.fecha_id = fechas.id
INNER JOIN alineacions ON alineacions.partido_id = partidos.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id
WHERE alineacions.tipo = 'Titular' AND grupos.torneo_id=".$torneo_id." AND grupos.id IN (".$arrgrupos.") AND alineacions.jugador_id = ".$goleador->id. " GROUP BY alineacions.jugador_id";

            //echo $sql3;

            $jugados = DB::select(DB::raw($sql3));


            foreach ($jugados as $jugado){

                $goleador->jugados += $jugado->jugados;
            }

            $sql4="SELECT cambios.jugador_id, COUNT(cambios.jugador_id)  as jugados
FROM torneos t2 INNER JOIN grupos g2 ON t2.id = g2.torneo_id
INNER JOIN fechas ON fechas.grupo_id = g2.id
INNER JOIN partidos ON partidos.fecha_id = fechas.id
INNER JOIN cambios ON cambios.partido_id = partidos.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id
WHERE cambios.tipo = 'Entra' AND grupos.torneo_id=".$torneo_id." AND grupos.id IN (".$arrgrupos.") AND cambios.jugador_id = ".$goleador->id. " GROUP BY cambios.jugador_id";



            $jugados = DB::select(DB::raw($sql4));


            foreach ($jugados as $jugado){

                $goleador->jugados += $jugado->jugados;
            }

        }

        $goleadores->setPath(route('grupos.goleadoresPublic',  array('torneoId' => $torneo->id,'order'=>$order,'tipoOrder'=>$tipoOrder)));



        $i=$offSet+1;


        return view('grupos.goleadoresPublic', compact('torneo','goleadores','i','order','tipoOrder'));
    }

    public function tarjetas(Request $request)
    {
        $torneo_id= $request->query('torneoId');
        $torneo=Torneo::findOrFail($torneo_id);

        $grupos = Grupo::where('torneo_id', '=',$torneo_id)->get();
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

        $tarjetas = DB::select(DB::raw('SELECT jugadors.id, CONCAT(personas.apellido,\', \',personas.nombre) jugador, count( case when tipo=\'Amarilla\' then 1 else NULL end) as  amarillas
, count( case when tipo=\'Roja\' or tipo=\'Doble Amarilla\' then 1 else NULL end) as  rojas, "" foto
FROM tarjetas
INNER JOIN jugadors ON tarjetas.jugador_id = jugadors.id
INNER JOIN personas ON jugadors.persona_id = personas.id
INNER JOIN partidos ON tarjetas.partido_id = partidos.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id

WHERE  grupos.torneo_id='.$torneo_id.' AND grupos.id IN ('.$arrgrupos.')
GROUP BY jugadors.id, jugador
ORDER BY rojas DESC, amarillas DESC, jugador ASC'));


        $page = $request->query('page', 1);

        $paginate = 15;

        $offSet = ($page * $paginate) - $paginate;

        $itemsForCurrentPage = array_slice($tarjetas, $offSet, $paginate, true);

        $tarjetas = new \Illuminate\Pagination\LengthAwarePaginator($itemsForCurrentPage, count($tarjetas), $paginate, $page);


        foreach ($tarjetas as $tarjeta){

            $sql2='SELECT DISTINCT escudo
FROM equipos
INNER JOIN alineacions ON equipos.id = alineacions.equipo_id
INNER JOIN partidos ON partidos.id = alineacions.partido_id
WHERE alineacions.jugador_id = '.$tarjeta->id.' AND alineacions.partido_id IN ('.$arrpartidos.') ORDER BY partidos.dia ASC';



            $escudos = DB::select(DB::raw($sql2));


            foreach ($escudos as $escudo){

                $tarjeta->foto .= $escudo->escudo.',';
            }


        }

        $tarjetas->setPath(route('grupos.tarjetas',  array('torneoId' => $torneo->id)));

        //dd($tarjetas);

        $i=$offSet+1;
        return view('grupos.tarjetas', compact('torneo','tarjetas','i'));
    }

    public function tarjetasPublic(Request $request)
    {

        $order= ($request->query('order'))?$request->query('order'):'rojas';
        $tipoOrder= ($request->query('tipoOrder'))?$request->query('tipoOrder'):'DESC';

        $torneo_id= $request->query('torneoId');
        $torneo=Torneo::findOrFail($torneo_id);

        $grupos = Grupo::where('torneo_id', '=',$torneo_id)->get();
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


        $tarjetas = DB::select(DB::raw('SELECT jugadors.id, CONCAT(personas.apellido,\', \',personas.nombre) jugador, count( case when tipo=\'Amarilla\' then 1 else NULL end) as  amarillas
, count( case when tipo=\'Roja\' or tipo=\'Doble Amarilla\' then 1 else NULL end) as  rojas, "" escudo, personas.foto, "0" as jugados
FROM tarjetas
INNER JOIN jugadors ON tarjetas.jugador_id = jugadors.id
INNER JOIN personas ON jugadors.persona_id = personas.id
INNER JOIN partidos ON tarjetas.partido_id = partidos.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id

WHERE  grupos.torneo_id='.$torneo_id.' AND grupos.id IN ('.$arrgrupos.')
GROUP BY jugadors.id, jugador, foto

        ORDER BY '.$order.' '.$tipoOrder.', amarillas DESC, jugador ASC'));

        $page = $request->query('page', 1);

        $paginate = 15;

        $offSet = ($page * $paginate) - $paginate;

        $itemsForCurrentPage = array_slice($tarjetas, $offSet, $paginate, true);

        $tarjetas = new \Illuminate\Pagination\LengthAwarePaginator($itemsForCurrentPage, count($tarjetas), $paginate, $page);


        foreach ($tarjetas as $tarjeta){

            $sql2='SELECT DISTINCT escudo, equipo_id
FROM equipos
INNER JOIN alineacions ON equipos.id = alineacions.equipo_id
INNER JOIN partidos ON partidos.id = alineacions.partido_id
WHERE alineacions.jugador_id = '.$tarjeta->id.' AND alineacions.partido_id IN ('.$arrpartidos.') ORDER BY partidos.dia ASC';



            $escudos = DB::select(DB::raw($sql2));


            foreach ($escudos as $escudo){

                $tarjeta->escudo .= $escudo->escudo.'_'.$escudo->equipo_id.',';
            }
            $sql3="SELECT alineacions.jugador_id, COUNT(alineacions.jugador_id) as jugados
FROM torneos t2 INNER JOIN grupos g2 ON t2.id = g2.torneo_id
INNER JOIN fechas ON fechas.grupo_id = g2.id
INNER JOIN partidos ON partidos.fecha_id = fechas.id
INNER JOIN alineacions ON alineacions.partido_id = partidos.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id
WHERE alineacions.tipo = 'Titular' AND grupos.torneo_id=".$torneo_id." AND grupos.id IN (".$arrgrupos.") AND alineacions.jugador_id = ".$tarjeta->id. " GROUP BY alineacions.jugador_id";

            //echo $sql3;

            $jugados = DB::select(DB::raw($sql3));


            foreach ($jugados as $jugado){

                $tarjeta->jugados += $jugado->jugados;
            }

            $sql4="SELECT cambios.jugador_id, COUNT(cambios.jugador_id)  as jugados
FROM torneos t2 INNER JOIN grupos g2 ON t2.id = g2.torneo_id
INNER JOIN fechas ON fechas.grupo_id = g2.id
INNER JOIN partidos ON partidos.fecha_id = fechas.id
INNER JOIN cambios ON cambios.partido_id = partidos.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id
WHERE cambios.tipo = 'Entra' AND grupos.torneo_id=".$torneo_id." AND grupos.id IN (".$arrgrupos.") AND cambios.jugador_id = ".$tarjeta->id. " GROUP BY cambios.jugador_id";



            $jugados = DB::select(DB::raw($sql4));


            foreach ($jugados as $jugado){

                $tarjeta->jugados += $jugado->jugados;
            }
        }

        $tarjetas->setPath(route('grupos.tarjetasPublic',  array('torneoId' => $torneo->id,'order'=>$order,'tipoOrder'=>$tipoOrder)));

        //dd($tarjetas);

        $i=$offSet+1;
        return view('grupos.tarjetasPublic', compact('torneo','tarjetas','i','order','tipoOrder'));
    }

    public function arqueros(Request $request)
    {
        $torneo_id= $request->query('torneoId');
        $torneo=Torneo::findOrFail($torneo_id);
        $order= ($request->query('order'))?$request->query('order'):'jugados';
        $tipoOrder= ($request->query('tipoOrder'))?$request->query('tipoOrder'):'DESC';
        $grupos = Grupo::where('torneo_id', '=',$torneo_id)->get();
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


        $arqueros = DB::select(DB::raw('SELECT jugadors.id, CONCAT(personas.apellido,\', \',personas.nombre) jugador, COUNT(jugadors.id) as jugados,
sum(case when alineacions.equipo_id=partidos.equipol_id then partidos.golesv else partidos.golesl END) AS recibidos,
sum(case when alineacions.equipo_id=partidos.equipol_id and partidos.golesv = 0 then 1 else CASE when alineacions.equipo_id=partidos.equipov_id and partidos.golesl = 0 THEN 1 ELSE 0 END END) AS invictas, personas.foto, "" escudo
FROM alineacions
INNER JOIN jugadors ON alineacions.jugador_id = jugadors.id AND jugadors.tipoJugador = \'Arquero\'
INNER JOIN personas ON jugadors.persona_id = personas.id
INNER JOIN partidos ON alineacions.partido_id = partidos.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id

WHERE  alineacions.tipo = \'Titular\'  AND grupos.torneo_id='.$torneo_id.' AND grupos.id IN ('.$arrgrupos.')
GROUP BY jugadors.id, jugador, foto
ORDER BY '.$order.' '.$tipoOrder.', jugados DESC, recibidos ASC'));


        $page = $request->query('page', 1);

        $paginate = 15;

        $offSet = ($page * $paginate) - $paginate;

        $itemsForCurrentPage = array_slice($arqueros, $offSet, $paginate, true);

        $arqueros = new \Illuminate\Pagination\LengthAwarePaginator($itemsForCurrentPage, count($arqueros), $paginate, $page);


        foreach ($arqueros as $arquero){

            $sql2='SELECT DISTINCT escudo, equipo_id
FROM equipos
INNER JOIN alineacions ON equipos.id = alineacions.equipo_id
INNER JOIN partidos ON partidos.id = alineacions.partido_id
WHERE alineacions.jugador_id = '.$arquero->id.' AND alineacions.partido_id IN ('.$arrpartidos.') ORDER BY partidos.dia ASC';



            $escudos = DB::select(DB::raw($sql2));


            foreach ($escudos as $escudo){

                $arquero->escudo .= $escudo->escudo.'_'.$escudo->equipo_id.',';
            }

        }



        $arqueros->setPath(route('grupos.arqueros',  array('torneoId' => $torneo->id,'order'=>$order,'tipoOrder'=>$tipoOrder)));

        //dd($tarjetas);

        $i=$offSet+1;
        return view('grupos.arqueros', compact('torneo','arqueros','i','order','tipoOrder'));
    }

    public function metodo(Request $request)
    {
        $torneo_id= $request->query('torneoId');
        $torneo=Torneo::findOrFail($torneo_id);

        $grupos = Grupo::where('torneo_id', '=',$torneo_id)->get();

        $arrPosiciones = array();
        $arrFaltantes = array();
        $arrPrimeros = array();

        $arrgrupos='';
        foreach ($grupos as $grupo){
            $arrgrupos .=$grupo->id.',';
        }

        $fechaNumero= $request->query('fechaNumero');

        if (empty($fechaNumero)){
            //$fechaNumero = '01';
            $ultimaFecha=Fecha::wherein('grupo_id',explode(',', $arrgrupos))->orderBy('numero','desc')->get();
            $fechaNumero = $ultimaFecha[0]->numero;
        }

        $fechas=Fecha::wherein('grupo_id',explode(',', $arrgrupos))->where('numero','=',$fechaNumero)->get();
        $arrfechas='';
        foreach ($fechas as $fecha){
            $arrfechas .=$fecha->id.',';
        }

        $fechas=Fecha::select('numero')->distinct()->wherein('grupo_id',explode(',', $arrgrupos))->orderBy('numero','ASC')->get();



        foreach ($grupos as $grupo){
            if ($grupo->posiciones){
                $sql="SELECT foto, equipo,
       count(*) jugados,
       count(case when golesl > golesv then 1 end) ganados,
       count(case when golesv > golesl then 1 end) perdidos,
       count(case when golesl = golesv then 1 end) empatados,
       sum(golesl) golesl,
       sum(golesv) golesv,
       sum(golesl) - sum(golesv) diferencia,
       sum(
             case when golesl > golesv then 3 else 0 end
           + case when golesl = golesv then 1 else 0 end
       ) puntaje, equipo_id, '' puntos
from (
       select  DISTINCT equipos.nombre equipo, golesl, golesv, equipos.escudo foto, fechas.id fecha_id, equipos.id equipo_id
		 from partidos
		 INNER JOIN equipos ON partidos.equipol_id = equipos.id
		 INNER JOIN plantillas ON plantillas.equipo_id = equipos.id
		 INNER JOIN fechas ON partidos.fecha_id = fechas.id
		 INNER JOIN grupos ON fechas.grupo_id = grupos.id AND grupos.posiciones = 1 AND grupos.torneo_id = ".$grupo->torneo->id." AND grupos.agrupacion = ".$grupo->agrupacion."
		 WHERE fechas.numero <= '".$fechaNumero."' AND golesl is not null AND golesv is not null AND EXISTS (SELECT p2.id FROM plantillas p2 WHERE plantillas.equipo_id = p2.equipo_id AND p2.grupo_id = ".$grupo->id.")
     union all
       select DISTINCT equipos.nombre equipo, golesv, golesl, equipos.escudo foto, fechas.id fecha_id, equipos.id equipo_id
		 from partidos
		 INNER JOIN equipos ON partidos.equipov_id = equipos.id
		 INNER JOIN plantillas ON plantillas.equipo_id = equipos.id
		 INNER JOIN fechas ON partidos.fecha_id = fechas.id
		 INNER JOIN grupos ON fechas.grupo_id = grupos.id AND grupos.posiciones = 1 AND grupos.torneo_id = ".$grupo->torneo->id." AND grupos.agrupacion = ".$grupo->agrupacion."
		 WHERE fechas.numero <= '".$fechaNumero."' AND golesl is not null AND golesv is not null AND EXISTS (SELECT p2.id FROM plantillas p2 WHERE plantillas.equipo_id = p2.equipo_id AND p2.grupo_id = ".$grupo->id.")
) a
group by equipo, foto, equipo_id

order by puntaje desc, diferencia DESC, golesl DESC, equipo ASC";

                $posiciones = DB::select(DB::raw($sql));
                for ($j = 0; $j <= 4; $j++) {
                    //dd($posiciones[$j]);
                    $totalPuntos = 0;
                    $arrPrimeros[$posiciones[$j]->equipo_id]=$posiciones[$j];
                    $sql1 = "SELECT e2.nombre, e2.id
FROM equipos e2
LEFT JOIN plantillas ON e2.id = plantillas.equipo_id
WHERE plantillas.grupo_id = ".$grupo->id." AND e2.id!=".$posiciones[$j]->equipo_id." and e2.id NOT IN (
SELECT locales.id
FROM fechas
LEFT JOIN partidos ON fechas.id = partidos.fecha_id
LEFT JOIN equipos locales ON partidos.equipol_id = locales.id

WHERE fechas.numero <= '".$fechaNumero."' AND fechas.grupo_id = ".$grupo->id." AND partidos.golesl IS not NULL AND partidos.golesv IS not NULL
AND partidos.equipov_id = ".$posiciones[$j]->equipo_id."
UNION ALL
SELECT visitantes.id
FROM fechas
LEFT JOIN partidos ON fechas.id = partidos.fecha_id
LEFT JOIN equipos visitantes ON partidos.equipov_id = visitantes.id

WHERE fechas.numero <= '".$fechaNumero."' AND fechas.grupo_id = ".$grupo->id." AND partidos.golesl IS not NULL AND partidos.golesv IS not NULL
AND partidos.equipol_id = ".$posiciones[$j]->equipo_id.")";
                    $faltantes = DB::select(DB::raw($sql1));
                    foreach ($faltantes as $faltante){
                        foreach ($posiciones as $equipo){
                            if($faltante->id == $equipo->equipo_id){
                                Log::info($posiciones[$j]->equipo.': '.$equipo->equipo.' -> '.$equipo->puntaje);
                                $totalPuntos += $equipo->puntaje;
                                break;
                            }

                        }
                    }

                    //$arrFaltantes[$posiciones[$j]->equipo_id]=$faltantes;
                    $arrPrimeros[$posiciones[$j]->equipo_id]->puntos = $totalPuntos;
                }
                foreach ($arrPrimeros as $key => $row)
                {
                    $count[$key] = $row->puntos;
                }
                array_multisort($count, SORT_ASC, $arrPrimeros);
                //dd($arrFaltantes);

                $arrPosiciones[$grupo->nombre]=$posiciones;
            }

        }





        return view('grupos.metodo', compact('torneo','arrPosiciones','arrPrimeros','fechas','fecha'));
    }

    public function jugadores(Request $request)
    {
        $torneo_id= $request->query('torneoId');
        $torneo=Torneo::findOrFail($torneo_id);

        $order= ($request->query('order'))?$request->query('order'):'jugados';
        $tipoOrder= ($request->query('tipoOrder'))?$request->query('tipoOrder'):'DESC';

        $grupos = Grupo::where('torneo_id', '=',$torneo_id)->get();
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




        $sql = 'SELECT jugador_id, "" escudo, foto, jugador,
       sum(jugados) jugados,

       sum(goles) goles,
       sum(rojas) rojas,
       sum(amarillas) amarillas,
       sum(recibidos) recibidos,
       sum(invictas) invictas

from

(SELECT jugadors.id AS jugador_id, personas.foto,"0" as jugados, CONCAT(personas.apellido,\', \',personas.nombre) jugador, "1" as goles, "0" as  amarillas
, "0" as  rojas, "0" as  recibidos, "0" as  invictas
FROM gols
INNER JOIN jugadors ON gols.jugador_id = jugadors.id
INNER JOIN personas ON jugadors.persona_id = personas.id
INNER JOIN partidos ON gols.partido_id = partidos.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id

WHERE gols.tipo <> \'En contra\' AND grupos.torneo_id='.$torneo_id.' AND grupos.id IN ('.$arrgrupos.')

 UNION ALL
 SELECT jugadors.id AS jugador_id, personas.foto,"0" as jugados, CONCAT(personas.apellido,\', \',personas.nombre) jugador, "0" AS goles, ( case when tipo=\'Amarilla\' then 1 else NULL end) as  amarillas
, ( case when tipo=\'Roja\' or tipo=\'Doble Amarilla\' then 1 else NULL end) as  rojas, "0" as  recibidos, "0" as  invictas
FROM tarjetas
INNER JOIN jugadors ON tarjetas.jugador_id = jugadors.id
INNER JOIN personas ON jugadors.persona_id = personas.id
LEFT JOIN partidos ON tarjetas.partido_id = partidos.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id

WHERE  grupos.torneo_id='.$torneo_id.' AND grupos.id IN ('.$arrgrupos.')

UNION ALL
 SELECT jugadors.id AS jugador_id, personas.foto,"1" as jugados, CONCAT(personas.apellido,\', \',personas.nombre) jugador, "0" AS goles, "0" as  amarillas
, "0" as  rojas, (case when alineacions.equipo_id=partidos.equipol_id then partidos.golesv else partidos.golesl END) AS recibidos,
(case when alineacions.equipo_id=partidos.equipol_id and partidos.golesv = 0 then 1 else CASE when alineacions.equipo_id=partidos.equipov_id and partidos.golesl = 0 THEN 1 ELSE 0 END END) AS invictas
FROM alineacions
INNER JOIN jugadors ON alineacions.jugador_id = jugadors.id AND jugadors.tipoJugador = \'Arquero\'
INNER JOIN personas ON jugadors.persona_id = personas.id
INNER JOIN partidos ON alineacions.partido_id = partidos.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id

WHERE  alineacions.tipo = \'Titular\'  AND grupos.torneo_id='.$torneo_id.' AND grupos.id IN ('.$arrgrupos.')

UNION ALL
 SELECT jugadors.id AS jugador_id, personas.foto,"1" as jugados, CONCAT(personas.apellido,\', \',personas.nombre) jugador, "0" AS goles, "0" as  amarillas
, "0" as  rojas, "0" AS recibidos,
"0" AS invictas
FROM alineacions
INNER JOIN jugadors ON alineacions.jugador_id = jugadors.id AND jugadors.tipoJugador != \'Arquero\'
INNER JOIN personas ON jugadors.persona_id = personas.id
INNER JOIN partidos ON alineacions.partido_id = partidos.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id
LEFT JOIN cambios ON alineacions.partido_id = cambios.partido_id AND cambios.jugador_id = jugadors.id
WHERE  (alineacions.tipo = \'Titular\' OR cambios.tipo = \'Entra\')  AND grupos.torneo_id='.$torneo_id.' AND grupos.id IN ('.$arrgrupos.')
) a

group by jugador_id,jugador, foto
ORDER BY '.$order.' '.$tipoOrder.', jugador ASC';

        $jugadores = DB::select(DB::raw($sql));



        $page = $request->query('page', 1);

        $paginate = 15;

        $offSet = ($page * $paginate) - $paginate;

        $itemsForCurrentPage = array_slice($jugadores, $offSet, $paginate, true);



        $jugadores = new \Illuminate\Pagination\LengthAwarePaginator($itemsForCurrentPage, count($jugadores), $paginate, $page);


        foreach ($jugadores as $jugador){

            $sql2='SELECT DISTINCT escudo, equipo_id
FROM equipos
INNER JOIN alineacions ON equipos.id = alineacions.equipo_id
INNER JOIN partidos ON partidos.id = alineacions.partido_id
WHERE alineacions.jugador_id = '.$jugador->jugador_id.' AND alineacions.partido_id IN ('.$arrpartidos.') ORDER BY partidos.dia ASC';



            $escudos = DB::select(DB::raw($sql2));


            foreach ($escudos as $escudo){

                $jugador->escudo .= $escudo->escudo.'_'.$escudo->equipo_id.',';
            }



        }

        $jugadores->setPath(route('grupos.jugadores',  array('torneoId' => $torneo->id,'order'=>$order,'tipoOrder'=>$tipoOrder)));



        $i=$offSet+1;


        return view('grupos.jugadores', compact('torneo','jugadores','i','order','tipoOrder'));
    }
}
