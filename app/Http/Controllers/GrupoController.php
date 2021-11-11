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
        $this->middleware('auth')->except(['posicionesPublic','goleadoresPublic','tarjetasPublic']);
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

        $sql = 'SELECT jugadors.id, CONCAT(jugadors.apellido,\', \',jugadors.nombre) jugador, COUNT(gols.id) goles, count( case when tipo=\'Jugada\' then 1 else NULL end) as  Jugada, "" as foto
, count( case when tipo=\'Cabeza\' then 1 else NULL end) as  Cabeza, count( case when tipo=\'Penal\' then 1 else NULL end) as  Penal, count( case when tipo=\'Tiro Libre\' then 1 else NULL end) as  Tiro_Libre
FROM gols
INNER JOIN jugadors ON gols.jugador_id = jugadors.id
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

        $sql = 'SELECT jugadors.id, CONCAT(jugadors.apellido,\', \',jugadors.nombre) jugador, COUNT(gols.id) goles, count( case when tipo=\'Jugada\' then 1 else NULL end) as  Jugada, "" as escudo, jugadors.foto
, count( case when tipo=\'Cabeza\' then 1 else NULL end) as  Cabeza, count( case when tipo=\'Penal\' then 1 else NULL end) as  Penal, count( case when tipo=\'Tiro Libre\' then 1 else NULL end) as  Tiro_Libre
FROM gols
INNER JOIN jugadors ON gols.jugador_id = jugadors.id
INNER JOIN partidos ON gols.partido_id = partidos.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id

WHERE gols.tipo <> \'En contra\' AND grupos.torneo_id='.$torneo_id.' AND grupos.id IN ('.$arrgrupos.')
GROUP BY jugadors.id,jugador, foto
ORDER BY goles DESC, jugador ASC';




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

        }

        $goleadores->setPath(route('grupos.goleadoresPublic',  array('torneoId' => $torneo->id)));



        $i=$offSet+1;


        return view('grupos.goleadoresPublic', compact('torneo','goleadores','i'));
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

        $tarjetas = DB::select(DB::raw('SELECT jugadors.id, CONCAT(jugadors.apellido,\', \',jugadors.nombre) jugador, count( case when tipo=\'Amarilla\' then 1 else NULL end) as  amarillas
, count( case when tipo=\'Roja\' or tipo=\'Doble Amarilla\' then 1 else NULL end) as  rojas, "" foto
FROM tarjetas
INNER JOIN jugadors ON tarjetas.jugador_id = jugadors.id
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


        $tarjetas = DB::select(DB::raw('SELECT jugadors.id, CONCAT(jugadors.apellido,\', \',jugadors.nombre) jugador, count( case when tipo=\'Amarilla\' then 1 else NULL end) as  amarillas
, count( case when tipo=\'Roja\' or tipo=\'Doble Amarilla\' then 1 else NULL end) as  rojas, "" escudo, jugadors.foto
FROM tarjetas
INNER JOIN jugadors ON tarjetas.jugador_id = jugadors.id
INNER JOIN partidos ON tarjetas.partido_id = partidos.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id

WHERE  grupos.torneo_id='.$torneo_id.' AND grupos.id IN ('.$arrgrupos.')
GROUP BY jugadors.id, jugador, foto
ORDER BY rojas DESC, amarillas DESC, jugador ASC'));


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

        }

        $tarjetas->setPath(route('grupos.tarjetasPublic',  array('torneoId' => $torneo->id)));

        //dd($tarjetas);

        $i=$offSet+1;
        return view('grupos.tarjetasPublic', compact('torneo','tarjetas','i'));
    }
}
