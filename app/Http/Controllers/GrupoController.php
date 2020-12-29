<?php

namespace App\Http\Controllers;

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



        $posiciones = DB::select(DB::raw('SELECT foto, equipo,
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
       select  equipos.nombre equipo, golesl, golesv, equipos.escudo foto
		 from partidos
		 INNER JOIN equipos ON partidos.equipol_id = equipos.id
		 INNER JOIN fechas ON partidos.fecha_id = fechas.id AND fechas.grupo_id='.$grupo_id.'
     union all
       select equipos.nombre equipo, golesv, golesl, equipos.escudo foto
		 from partidos
		 INNER JOIN equipos ON partidos.equipov_id = equipos.id
		 INNER JOIN fechas ON partidos.fecha_id = fechas.id AND fechas.grupo_id='.$grupo_id.'
) a
group by equipo, foto

order by puntaje desc, diferencia DESC, golesl DESC, equipo ASC'));

        //dd($posiciones);

        $i=1;
        return view('grupos.posiciones', compact('grupo','posiciones','i'));
    }

    public function posicionesPublic(Request $request)
    {
        $grupo_id= $request->query('grupoId');
        $grupo=Grupo::findOrFail($grupo_id);



        $posiciones = DB::select(DB::raw('SELECT foto, equipo,
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
       select  equipos.nombre equipo, golesl, golesv, equipos.escudo foto
		 from partidos
		 INNER JOIN equipos ON partidos.equipol_id = equipos.id
		 INNER JOIN fechas ON partidos.fecha_id = fechas.id AND fechas.grupo_id='.$grupo_id.'
     union all
       select equipos.nombre equipo, golesv, golesl, equipos.escudo foto
		 from partidos
		 INNER JOIN equipos ON partidos.equipov_id = equipos.id
		 INNER JOIN fechas ON partidos.fecha_id = fechas.id AND fechas.grupo_id='.$grupo_id.'
) a
group by equipo, foto

order by puntaje desc, diferencia DESC, golesl DESC, equipo ASC'));

        //dd($posiciones);

        $i=1;
        return view('grupos.posicionesPublic', compact('grupo','posiciones','i'));
    }

    public function goleadores(Request $request)
    {
        $torneo_id= $request->query('torneoId');
        $torneo=Torneo::findOrFail($torneo_id);



        $goleadores = DB::select(DB::raw('SELECT CONCAT(jugadors.apellido,\', \',jugadors.nombre) jugador, COUNT(gols.id) goles, count( case when tipo=\'Jugada\' then 1 else NULL end) as  Jugada
, count( case when tipo=\'Cabeza\' then 1 else NULL end) as  Cabeza, count( case when tipo=\'Penal\' then 1 else NULL end) as  Penal, count( case when tipo=\'Tiro Libre\' then 1 else NULL end) as  Tiro_Libre, equipos.escudo foto
FROM gols
INNER JOIN jugadors ON gols.jugador_id = jugadors.id
INNER JOIN partidos ON gols.partido_id = partidos.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id
INNER JOIN plantilla_jugadors ON plantilla_jugadors.jugador_id = jugadors.id
INNER JOIN plantillas ON plantilla_jugadors.plantilla_id = plantillas.id
INNER JOIN equipos ON plantillas.equipo_id = equipos.id
WHERE gols.tipo <> \'En contra\' AND grupos.torneo_id='.$torneo_id.' AND plantillas.torneo_id = '.$torneo_id.'
GROUP BY jugador, foto
ORDER BY goles DESC, jugador ASC'));

        $page = $request->query('page', 1);

        $paginate = 15;

        $offSet = ($page * $paginate) - $paginate;

        $itemsForCurrentPage = array_slice($goleadores, $offSet, $paginate, true);

        $goleadores = new \Illuminate\Pagination\LengthAwarePaginator($itemsForCurrentPage, count($goleadores), $paginate, $page);

        $goleadores->setPath(route('grupos.goleadores',  array('torneoId' => $torneo->id)));



        $i=$offSet+1;


        return view('grupos.goleadores', compact('torneo','goleadores','i'));
    }

    public function goleadoresPublic(Request $request)
    {
        $torneo_id= $request->query('torneoId');
        $torneo=Torneo::findOrFail($torneo_id);



        $goleadores = DB::select(DB::raw('SELECT CONCAT(jugadors.apellido,\', \',jugadors.nombre) jugador, COUNT(gols.id) goles, count( case when tipo=\'Jugada\' then 1 else NULL end) as  Jugada
, count( case when tipo=\'Cabeza\' then 1 else NULL end) as  Cabeza, count( case when tipo=\'Penal\' then 1 else NULL end) as  Penal, count( case when tipo=\'Tiro Libre\' then 1 else NULL end) as  Tiro_Libre, equipos.escudo foto
FROM gols
INNER JOIN jugadors ON gols.jugador_id = jugadors.id
INNER JOIN partidos ON gols.partido_id = partidos.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id
INNER JOIN plantilla_jugadors ON plantilla_jugadors.jugador_id = jugadors.id
INNER JOIN plantillas ON plantilla_jugadors.plantilla_id = plantillas.id
INNER JOIN equipos ON plantillas.equipo_id = equipos.id
WHERE gols.tipo <> \'En contra\' AND grupos.torneo_id='.$torneo_id.' AND plantillas.torneo_id = '.$torneo_id.'
GROUP BY jugador, foto
ORDER BY goles DESC, jugador ASC'));

        $page = $request->query('page', 1);

        $paginate = 15;

        $offSet = ($page * $paginate) - $paginate;

        $itemsForCurrentPage = array_slice($goleadores, $offSet, $paginate, true);

        $goleadores = new \Illuminate\Pagination\LengthAwarePaginator($itemsForCurrentPage, count($goleadores), $paginate, $page);

        $goleadores->setPath(route('grupos.goleadoresPublic',  array('torneoId' => $torneo->id)));



        $i=$offSet+1;


        return view('grupos.goleadoresPublic', compact('torneo','goleadores','i'));
    }

    public function tarjetas(Request $request)
    {
        $torneo_id= $request->query('torneoId');
        $torneo=Torneo::findOrFail($torneo_id);



        $tarjetas = DB::select(DB::raw('SELECT CONCAT(jugadors.apellido,\', \',jugadors.nombre) jugador, count( case when tipo=\'Amarilla\' then 1 else NULL end) as  amarillas
, count( case when tipo=\'Roja\' or tipo=\'Doble Amarilla\' then 1 else NULL end) as  rojas, equipos.escudo foto
FROM tarjetas
INNER JOIN jugadors ON tarjetas.jugador_id = jugadors.id
INNER JOIN partidos ON tarjetas.partido_id = partidos.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id
INNER JOIN plantilla_jugadors ON plantilla_jugadors.jugador_id = jugadors.id
INNER JOIN plantillas ON plantilla_jugadors.plantilla_id = plantillas.id
INNER JOIN equipos ON plantillas.equipo_id = equipos.id
WHERE  grupos.torneo_id='.$torneo_id.' AND plantillas.torneo_id = '.$torneo_id.'
GROUP BY jugador, foto
ORDER BY rojas DESC, amarillas DESC, jugador ASC'));


        $page = $request->query('page', 1);

        $paginate = 15;

        $offSet = ($page * $paginate) - $paginate;

        $itemsForCurrentPage = array_slice($tarjetas, $offSet, $paginate, true);

        $tarjetas = new \Illuminate\Pagination\LengthAwarePaginator($itemsForCurrentPage, count($tarjetas), $paginate, $page);

        $tarjetas->setPath(route('grupos.tarjetas',  array('torneoId' => $torneo->id)));

        //dd($tarjetas);

        $i=$offSet+1;
        return view('grupos.tarjetas', compact('torneo','tarjetas','i'));
    }

    public function tarjetasPublic(Request $request)
    {
        $torneo_id= $request->query('torneoId');
        $torneo=Torneo::findOrFail($torneo_id);



        $tarjetas = DB::select(DB::raw('SELECT CONCAT(jugadors.apellido,\', \',jugadors.nombre) jugador, count( case when tipo=\'Amarilla\' then 1 else NULL end) as  amarillas
, count( case when tipo=\'Roja\' or tipo=\'Doble Amarilla\' then 1 else NULL end) as  rojas, equipos.escudo foto
FROM tarjetas
INNER JOIN jugadors ON tarjetas.jugador_id = jugadors.id
INNER JOIN partidos ON tarjetas.partido_id = partidos.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id
INNER JOIN plantilla_jugadors ON plantilla_jugadors.jugador_id = jugadors.id
INNER JOIN plantillas ON plantilla_jugadors.plantilla_id = plantillas.id
INNER JOIN equipos ON plantillas.equipo_id = equipos.id
WHERE  grupos.torneo_id='.$torneo_id.' AND plantillas.torneo_id = '.$torneo_id.'
GROUP BY jugador, foto
ORDER BY rojas DESC, amarillas DESC, jugador ASC'));


        $page = $request->query('page', 1);

        $paginate = 15;

        $offSet = ($page * $paginate) - $paginate;

        $itemsForCurrentPage = array_slice($tarjetas, $offSet, $paginate, true);

        $tarjetas = new \Illuminate\Pagination\LengthAwarePaginator($itemsForCurrentPage, count($tarjetas), $paginate, $page);

        $tarjetas->setPath(route('grupos.tarjetasPublic',  array('torneoId' => $torneo->id)));

        //dd($tarjetas);

        $i=$offSet+1;
        return view('grupos.tarjetasPublic', compact('torneo','tarjetas','i'));
    }
}
