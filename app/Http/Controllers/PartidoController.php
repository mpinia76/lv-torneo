<?php

namespace App\Http\Controllers;
use App\Arbitro;
use App\Partido;
use App\Fecha;
use App\PartidoArbitro;
use Illuminate\Http\Request;
use App\Tarjeta;
use App\Gol;
use App\Cambio;
use DB;

class PartidoController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $fecha_id= $request->query('fechaId');
        $nombre = $request->get('buscarpor');

        $fecha=Fecha::findOrFail($fecha_id);



        $partidos=Partido::where('fecha_id','=',"$fecha_id")->orderBy('fecha','ASC')->paginate();




        return view('partidos.index', compact('partidos','partidos'), compact('fecha','fecha'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
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
        //
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

    public function arbitros(Request $request)
    {
        $partido_id= $request->query('partidoId');

        $partido=Partido::findOrFail($partido_id);

        $partidoarbitros=PartidoArbitro::where('partido_id','=',"$partido_id")->get();


        /*$arbitros = Arbitro::orderBy('apellido', 'asc')->orderBy('nombre', 'asc')->get();

        $arbitros = $arbitros->pluck('full_name', 'id')->prepend('','');*/

        $arbitros = Arbitro::SELECT('arbitros.*','personas.nombre','personas.apellido','personas.nacimiento','personas.fallecimiento','personas.foto')->JOIN('personas','personas.id','=','arbitros.persona_id')->orderBy('apellido', 'asc')->orderBy('nombre', 'asc')->get();
        //dd($arbitros);
        $arbitros = $arbitros->pluck('persona.full_name', 'id')->prepend('','');

        //dd($partido->fecha->grupo->torneo->nombre);
        return view('partidos.arbitros', compact('partidoarbitros','partido','arbitros'));
    }

    public function controlarAlineaciones(Request $request)
    {
        //DB::enableQueryLog();
        $partidos = DB::table('partidos')
            ->join('equipos as equipo_local', 'partidos.equipol_id', '=', 'equipo_local.id')
            ->join('equipos as equipo_visitante', 'partidos.equipov_id', '=', 'equipo_visitante.id')
            ->join('fechas as fecha', 'partidos.fecha_id', '=', 'fecha.id')
            ->join('grupos as grupo', 'fecha.grupo_id', '=', 'grupo.id')
            ->join('torneos as torneo', 'grupo.torneo_id', '=', 'torneo.id')
            ->select(
                'partidos.id',
                'partidos.dia',
                'partidos.golesl',
                'partidos.golesv',
                'partidos.penalesl',
                'partidos.penalesv',
                'fecha.numero as fecha',
                'torneo.nombre as torneo',
                'torneo.year as year',
                'equipo_local.nombre as equipo_local_nombre',
                'equipo_visitante.nombre as equipo_visitante_nombre',
                'equipo_local.escudo as equipo_local_escudo',
                'equipo_visitante.escudo as equipo_visitante_escudo'
            )

            ->whereIn('partidos.id', function ($query) {
                $query->select('partido_id')
                    ->from('alineacions')
                    ->where('tipo', 'Titular')
                    ->groupBy('partido_id','equipo_id')
                    ->havingRaw('COUNT(partido_id) != 11');
            })
            ->whereNotNull('golesl')
            ->whereNotNull('golesv')
            ->orderBy('year','DESC')
            ->orderBy('torneo')
            ->orderBy('fecha')
            ->paginate();
        //$queries = DB::getQueryLog();
        //dd($queries);

        $partidosSinJugadores = DB::table('partidos')
            ->join('equipos as equipo_local', 'partidos.equipol_id', '=', 'equipo_local.id')
            ->join('equipos as equipo_visitante', 'partidos.equipov_id', '=', 'equipo_visitante.id')
            ->join('fechas as fecha', 'partidos.fecha_id', '=', 'fecha.id')
            ->join('grupos as grupo', 'fecha.grupo_id', '=', 'grupo.id')
            ->join('torneos as torneo', 'grupo.torneo_id', '=', 'torneo.id')
            ->select(
                'partidos.id',
                'partidos.dia',
                'partidos.golesl',
                'partidos.golesv',
                'partidos.penalesl',
                'partidos.penalesv',
                'fecha.numero as fecha',
                'torneo.nombre as torneo',
                'torneo.year as year',
                'equipo_local.nombre as equipo_local_nombre',
                'equipo_visitante.nombre as equipo_visitante_nombre',
                'equipo_local.escudo as equipo_local_escudo',
                'equipo_visitante.escudo as equipo_visitante_escudo'
            )
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('alineacions')
                    ->whereColumn('alineacions.partido_id', 'partidos.id')
                    ->where('alineacions.tipo', 'Titular');
            })
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('incidencias')
                    ->whereColumn('incidencias.partido_id', 'partidos.id');
            })
            ->whereNotNull('golesl')
            ->whereNotNull('golesv')
            ->orderBy('year', 'DESC')
            ->orderBy('torneo')
            ->orderBy('fecha')
            ->paginate();



        return view('torneos.controlarAlineaciones', compact('partidos','partidosSinJugadores'));
    }

    public function controlarTarjetas(Request $request)
    {
        //DB::enableQueryLog();
        $tarjetasSinCoincidencia = Tarjeta::whereNotExists(function ($query) {
            $query->select(DB::raw(1))
                ->from('alineacions')
                ->whereRaw('alineacions.partido_id = tarjetas.partido_id')
                ->whereRaw('alineacions.jugador_id = tarjetas.jugador_id');
        });

        $partidos = $tarjetasSinCoincidencia
            ->join('partidos', 'tarjetas.partido_id', '=', 'partidos.id')
            ->join('equipos as equipo_local', 'partidos.equipol_id', '=', 'equipo_local.id')
            ->join('equipos as equipo_visitante', 'partidos.equipov_id', '=', 'equipo_visitante.id')
            ->join('fechas as fecha', 'partidos.fecha_id', '=', 'fecha.id')
            ->join('grupos as grupo', 'fecha.grupo_id', '=', 'grupo.id')
            ->join('torneos as torneo', 'grupo.torneo_id', '=', 'torneo.id')
            ->join('jugadors', 'tarjetas.jugador_id', '=', 'jugadors.id')
            ->join('personas', 'jugadors.persona_id', '=', 'personas.id')
            ->select(
                'partidos.id',
                'partidos.dia',
                'partidos.golesl',
                'partidos.golesv',
                'partidos.penalesl',
                'partidos.penalesv',
                'fecha.numero as fecha',
                'torneo.nombre as torneo',
                'torneo.year as year',
                'equipo_local.nombre as equipo_local_nombre',
                'equipo_visitante.nombre as equipo_visitante_nombre',
                'equipo_local.escudo as equipo_local_escudo',
                'equipo_visitante.escudo as equipo_visitante_escudo',
                'personas.nombre as jugador_nombre',
                'personas.apellido as jugador_apellido',
                'personas.foto as jugador_foto'
            )
            ->whereNotNull('golesl')
            ->whereNotNull('golesv')
            ->orderBy('year','DESC')
            ->orderBy('torneo')
            ->orderBy('fecha')
            ->paginate();
        //$queries = DB::getQueryLog();
        //dd($queries);

        $tarjetas = DB::table(DB::raw('(
                    SELECT partido_id, jugador_id, COUNT(*) AS cantidad_tarjetas
                    FROM tarjetas
                    GROUP BY partido_id, jugador_id, tipo
                    HAVING COUNT(*) > 1
                ) AS t1'))
            ->select(
                'partidos.id',
                'partidos.dia',
                'partidos.golesl',
                'partidos.golesv',
                'partidos.penalesl',
                'partidos.penalesv',
                'fecha.numero as fecha',
                'torneo.nombre as torneo',
                'torneo.year as year',
                'equipo_local.nombre as equipo_local_nombre',
                'equipo_visitante.nombre as equipo_visitante_nombre',
                'equipo_local.escudo as equipo_local_escudo',
                'equipo_visitante.escudo as equipo_visitante_escudo',
                'personas.nombre as jugador_nombre',
                'personas.apellido as jugador_apellido',
                'personas.foto as jugador_foto'
            )
            ->join('partidos', 't1.partido_id', '=', 'partidos.id')
            ->join('equipos as equipo_local', 'partidos.equipol_id', '=', 'equipo_local.id')
            ->join('equipos as equipo_visitante', 'partidos.equipov_id', '=', 'equipo_visitante.id')
            ->join('fechas as fecha', 'partidos.fecha_id', '=', 'fecha.id')
            ->join('grupos as grupo', 'fecha.grupo_id', '=', 'grupo.id')
            ->join('torneos as torneo', 'grupo.torneo_id', '=', 'torneo.id')
            ->join('jugadors', 't1.jugador_id', '=', 'jugadors.id')
            ->join('personas', 'jugadors.persona_id', '=', 'personas.id')
            ->whereNotNull('golesl')
            ->whereNotNull('golesv')
            ->orderBy('year','DESC')
            ->orderBy('torneo')
            ->orderBy('fecha')
            ->paginate();




        return view('torneos.controlarTarjetas', compact('partidos','tarjetas'));
    }

    public function controlarGoles(Request $request)
    {
        //DB::enableQueryLog();


        $partidos = DB::table('partidos')
            ->select(
                'partidos.id',
                'partidos.dia',
                'partidos.golesl',
                'partidos.golesv',
                'partidos.penalesl',
                'partidos.penalesv',
                'fecha.numero as fecha',
                'torneo.nombre as torneo',
                'torneo.year as year',
                'equipo_local.nombre as equipo_local_nombre',
                'equipo_visitante.nombre as equipo_visitante_nombre',
                'equipo_local.escudo as equipo_local_escudo',
                'equipo_visitante.escudo as equipo_visitante_escudo',
                'personas.nombre as jugador_nombre',
                'personas.apellido as jugador_apellido',
                'personas.foto as jugador_foto'
            )
            ->join('equipos as equipo_local', 'partidos.equipol_id', '=', 'equipo_local.id')
            ->join('equipos as equipo_visitante', 'partidos.equipov_id', '=', 'equipo_visitante.id')
            ->join('fechas as fecha', 'partidos.fecha_id', '=', 'fecha.id')
            ->join('grupos as grupo', 'fecha.grupo_id', '=', 'grupo.id')
            ->join('torneos as torneo', 'grupo.torneo_id', '=', 'torneo.id')
            ->join('gols', 'partidos.id', '=', 'gols.partido_id')
            ->join('jugadors', 'gols.jugador_id', '=', 'jugadors.id')
            ->join('personas', 'jugadors.persona_id', '=', 'personas.id')
            ->whereIn('partidos.id', function ($query) {
                $query->select('gols.partido_id')
                    ->from('gols')
                    ->whereNotExists(function ($subquery) {
                        $subquery->select(DB::raw(1))
                            ->from('alineacions')
                            ->leftJoin('cambios', function ($join) {
                                $join->on('alineacions.partido_id', '=', 'cambios.partido_id')
                                    ->on('alineacions.jugador_id', '=', 'cambios.jugador_id');
                            })
                            ->whereRaw('alineacions.partido_id = gols.partido_id')
                            ->whereRaw('alineacions.jugador_id = gols.jugador_id')
                            ->where(function ($query) {
                                $query->where('alineacions.tipo', '=', 'Titular')
                                    ->orWhere('cambios.tipo', '=', 'Entra');
                            });
                    });
            })
            ->whereIn('jugadors.id', function ($query) {
                $query->select('gols.jugador_id')
                    ->from('gols')
                    ->whereNotExists(function ($subquery) {
                        $subquery->select(DB::raw(1))
                            ->from('alineacions')
                            ->leftJoin('cambios', function ($join) {
                                $join->on('alineacions.partido_id', '=', 'cambios.partido_id')
                                    ->on('alineacions.jugador_id', '=', 'cambios.jugador_id');
                            })
                            ->whereRaw('alineacions.partido_id = gols.partido_id')
                            ->whereRaw('alineacions.jugador_id = gols.jugador_id')
                            ->where(function ($query) {
                                $query->where('alineacions.tipo', '=', 'Titular')
                                    ->orWhere('cambios.tipo', '=', 'Entra');
                            });
                    });
            })
            ->whereNotNull('golesl')
            ->whereNotNull('golesv')
            ->orderBy('year','DESC')
            ->orderBy('torneo')
            ->orderBy('fecha')
            ->paginate();


        $gols = DB::table(DB::raw('(
                    SELECT partido_id, jugador_id, COUNT(*) AS cantidad_goles
                    FROM gols
                    GROUP BY partido_id, jugador_id, minuto
                    HAVING COUNT(*) > 1
                ) AS g1'))
            ->select(
                'partidos.id',
                'partidos.dia',
                'partidos.golesl',
                'partidos.golesv',
                'partidos.penalesl',
                'partidos.penalesv',
                'fecha.numero as fecha',
                'torneo.nombre as torneo',
                'torneo.year as year',
                'equipo_local.nombre as equipo_local_nombre',
                'equipo_visitante.nombre as equipo_visitante_nombre',
                'equipo_local.escudo as equipo_local_escudo',
                'equipo_visitante.escudo as equipo_visitante_escudo',
                'personas.nombre as jugador_nombre',
                'personas.apellido as jugador_apellido',
                'personas.foto as jugador_foto'
            )
            ->join('partidos', 'g1.partido_id', '=', 'partidos.id')
            ->join('equipos as equipo_local', 'partidos.equipol_id', '=', 'equipo_local.id')
            ->join('equipos as equipo_visitante', 'partidos.equipov_id', '=', 'equipo_visitante.id')
            ->join('fechas as fecha', 'partidos.fecha_id', '=', 'fecha.id')
            ->join('grupos as grupo', 'fecha.grupo_id', '=', 'grupo.id')
            ->join('torneos as torneo', 'grupo.torneo_id', '=', 'torneo.id')
            ->join('jugadors', 'g1.jugador_id', '=', 'jugadors.id')
            ->join('personas', 'jugadors.persona_id', '=', 'personas.id')
            ->whereNotNull('golesl')
            ->whereNotNull('golesv')
            ->orderBy('year','DESC')
            ->orderBy('torneo')
            ->orderBy('fecha')
            ->paginate();

        $diferencia = DB::table('partidos')
            ->select(
                'partidos.id',
                'partidos.dia',
                'partidos.golesl',
                'partidos.golesv',
                'partidos.penalesl',
                'partidos.penalesv',
                'fecha.numero as fecha',
                'torneo.nombre as torneo',
                'torneo.year as year',
                'equipo_local.nombre as equipo_local_nombre',
                'equipo_visitante.nombre as equipo_visitante_nombre',
                'equipo_local.escudo as equipo_local_escudo',
                'equipo_visitante.escudo as equipo_visitante_escudo'
            )
            ->join('equipos as equipo_local', 'partidos.equipol_id', '=', 'equipo_local.id')
            ->join('equipos as equipo_visitante', 'partidos.equipov_id', '=', 'equipo_visitante.id')
            ->join('fechas as fecha', 'partidos.fecha_id', '=', 'fecha.id')
            ->join('grupos as grupo', 'fecha.grupo_id', '=', 'grupo.id')
            ->join('torneos as torneo', 'grupo.torneo_id', '=', 'torneo.id')
            ->whereRaw('partidos.golesl + partidos.golesv != (SELECT COUNT(gols.id) FROM gols WHERE partidos.id = gols.partido_id GROUP BY gols.partido_id)')
            ->orderBy('year','desc')
            ->orderBy('torneo')
            ->orderBy('fecha')
            ->paginate();

// Ahora $partidos contiene los datos de los partidos que cumplen con la condición.


        return view('torneos.controlarGoles', compact('partidos','gols','diferencia'));
    }

    public function controlarCambios(Request $request)
    {
        //DB::enableQueryLog();
        $cambiosSinCoincidencia = Cambio::whereNotExists(function ($query) {
            $query->select(DB::raw(1))
                ->from('alineacions')
                ->whereRaw('alineacions.partido_id = cambios.partido_id')
                ->whereRaw('alineacions.jugador_id = cambios.jugador_id');
        });

        $partidos = $cambiosSinCoincidencia
            ->join('partidos', 'cambios.partido_id', '=', 'partidos.id')
            ->join('equipos as equipo_local', 'partidos.equipol_id', '=', 'equipo_local.id')
            ->join('equipos as equipo_visitante', 'partidos.equipov_id', '=', 'equipo_visitante.id')
            ->join('fechas as fecha', 'partidos.fecha_id', '=', 'fecha.id')
            ->join('grupos as grupo', 'fecha.grupo_id', '=', 'grupo.id')
            ->join('torneos as torneo', 'grupo.torneo_id', '=', 'torneo.id')
            ->join('jugadors', 'cambios.jugador_id', '=', 'jugadors.id')
            ->join('personas', 'jugadors.persona_id', '=', 'personas.id')
            ->select(
                'partidos.id',
                'partidos.dia',
                'partidos.golesl',
                'partidos.golesv',
                'partidos.penalesl',
                'partidos.penalesv',
                'fecha.numero as fecha',
                'torneo.nombre as torneo',
                'torneo.year as year',
                'equipo_local.nombre as equipo_local_nombre',
                'equipo_visitante.nombre as equipo_visitante_nombre',
                'equipo_local.escudo as equipo_local_escudo',
                'equipo_visitante.escudo as equipo_visitante_escudo',
                'personas.nombre as jugador_nombre',
                'personas.apellido as jugador_apellido',
                'personas.foto as jugador_foto'
            )
            ->whereNotNull('golesl')
            ->whereNotNull('golesv')
            ->orderBy('year','DESC')
            ->orderBy('torneo')
            ->orderBy('fecha')
            ->paginate();
        //$queries = DB::getQueryLog();
        //dd($queries);

        $cambios = DB::table(DB::raw('(
                    SELECT partido_id, jugador_id, COUNT(*) AS cantidad_cambios
                    FROM cambios
                    GROUP BY partido_id, jugador_id, tipo
                    HAVING COUNT(*) > 1
                ) AS t1'))
            ->select(
                'partidos.id',
                'partidos.dia',
                'partidos.golesl',
                'partidos.golesv',
                'partidos.penalesl',
                'partidos.penalesv',
                'fecha.numero as fecha',
                'torneo.nombre as torneo',
                'torneo.year as year',
                'equipo_local.nombre as equipo_local_nombre',
                'equipo_visitante.nombre as equipo_visitante_nombre',
                'equipo_local.escudo as equipo_local_escudo',
                'equipo_visitante.escudo as equipo_visitante_escudo',
                'personas.nombre as jugador_nombre',
                'personas.apellido as jugador_apellido',
                'personas.foto as jugador_foto'
            )
            ->join('partidos', 't1.partido_id', '=', 'partidos.id')
            ->join('equipos as equipo_local', 'partidos.equipol_id', '=', 'equipo_local.id')
            ->join('equipos as equipo_visitante', 'partidos.equipov_id', '=', 'equipo_visitante.id')
            ->join('fechas as fecha', 'partidos.fecha_id', '=', 'fecha.id')
            ->join('grupos as grupo', 'fecha.grupo_id', '=', 'grupo.id')
            ->join('torneos as torneo', 'grupo.torneo_id', '=', 'torneo.id')
            ->join('jugadors', 't1.jugador_id', '=', 'jugadors.id')
            ->join('personas', 'jugadors.persona_id', '=', 'personas.id')
            ->whereNotNull('golesl')
            ->whereNotNull('golesv')
            ->orderBy('year','DESC')
            ->orderBy('torneo')
            ->orderBy('fecha')
            ->paginate();

        $impares = DB::table(DB::raw('(
                    SELECT partido_id, minuto, COUNT(partido_id) AS cantidad_cambios
                    FROM cambios
                    GROUP BY partido_id, minuto
                    HAVING COUNT(partido_id) % 2 != 0
                ) AS t1'))
            ->select(
                'partidos.id',
                'partidos.dia',
                'partidos.golesl',
                'partidos.golesv',
                'partidos.penalesl',
                'partidos.penalesv',
                'fecha.numero as fecha',
                'torneo.nombre as torneo',
                'torneo.year as year',
                'equipo_local.nombre as equipo_local_nombre',
                'equipo_visitante.nombre as equipo_visitante_nombre',
                'equipo_local.escudo as equipo_local_escudo',
                'equipo_visitante.escudo as equipo_visitante_escudo'
            )
            ->join('partidos', 't1.partido_id', '=', 'partidos.id')
            ->join('equipos as equipo_local', 'partidos.equipol_id', '=', 'equipo_local.id')
            ->join('equipos as equipo_visitante', 'partidos.equipov_id', '=', 'equipo_visitante.id')
            ->join('fechas as fecha', 'partidos.fecha_id', '=', 'fecha.id')
            ->join('grupos as grupo', 'fecha.grupo_id', '=', 'grupo.id')
            ->join('torneos as torneo', 'grupo.torneo_id', '=', 'torneo.id')
            ->whereNotNull('golesl')
            ->whereNotNull('golesv')
            ->orderBy('year','DESC')
            ->orderBy('torneo')
            ->orderBy('fecha')
            ->paginate();

// Consulta para titulares que tienen "Entra" en cambios
        $titularesQueEntran = DB::table('alineacions')
            ->join('cambios', function($join) {
                $join->on('alineacions.partido_id', '=', 'cambios.partido_id')
                    ->on('alineacions.jugador_id', '=', 'cambios.jugador_id')
                    ->where('cambios.tipo', '=', 'Entra');
            })
            ->join('partidos', 'alineacions.partido_id', '=', 'partidos.id')
            ->join('equipos as equipo_local', 'partidos.equipol_id', '=', 'equipo_local.id')
            ->join('equipos as equipo_visitante', 'partidos.equipov_id', '=', 'equipo_visitante.id')
            ->join('fechas as fecha', 'partidos.fecha_id', '=', 'fecha.id')
            ->join('grupos as grupo', 'fecha.grupo_id', '=', 'grupo.id')
            ->join('torneos as torneo', 'grupo.torneo_id', '=', 'torneo.id')
            ->join('jugadors', 'alineacions.jugador_id', '=', 'jugadors.id')
            ->join('personas', 'jugadors.persona_id', '=', 'personas.id')
            ->select(
                'partidos.id',
                'partidos.dia',
                'partidos.golesl',
                'partidos.golesv',
                'partidos.penalesl',
                'partidos.penalesv',
                'fecha.numero as fecha',
                'torneo.nombre as torneo',
                'torneo.year as year',
                'equipo_local.nombre as equipo_local_nombre',
                'equipo_visitante.nombre as equipo_visitante_nombre',
                'equipo_local.escudo as equipo_local_escudo',
                'equipo_visitante.escudo as equipo_visitante_escudo',
                'personas.nombre as jugador_nombre',
                'personas.apellido as jugador_apellido',
                'personas.foto as jugador_foto'
            )
            ->where('alineacions.tipo', '=', 'Titular') // Verificamos que el jugador es titular
            ->whereNotNull('golesl')
            ->whereNotNull('golesv')
            ->orderBy('year', 'DESC')
            ->orderBy('torneo')
            ->orderBy('fecha')
            ->paginate();

//dd($titularesQueEntran);


        return view('torneos.controlarCambios', compact('partidos','cambios','impares','titularesQueEntran'));
    }

    public function controlarArbitros(Request $request)
    {
        //DB::enableQueryLog();
        $partidosSinArbitroPrincipal = DB::table('partidos')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('partido_arbitros')
                    ->where('tipo', 'Principal')
                    ->whereColumn('partidos.id', 'partido_arbitros.partido_id')
                    ->groupBy('partido_id');
            })
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('incidencias')
                    ->whereColumn('partidos.id', 'incidencias.partido_id');
            })
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('alineacions')
                    ->whereRaw('(partidos.equipol_id = alineacions.equipo_id OR partidos.equipov_id = alineacions.equipo_id)')
                    ->whereColumn('partidos.id', 'alineacions.partido_id')
                    ->groupBy('alineacions.partido_id');
            });

        $partidos = $partidosSinArbitroPrincipal

            ->join('equipos as equipo_local', 'partidos.equipol_id', '=', 'equipo_local.id')
            ->join('equipos as equipo_visitante', 'partidos.equipov_id', '=', 'equipo_visitante.id')
            ->join('fechas as fecha', 'partidos.fecha_id', '=', 'fecha.id')
            ->join('grupos as grupo', 'fecha.grupo_id', '=', 'grupo.id')
            ->join('torneos as torneo', 'grupo.torneo_id', '=', 'torneo.id')
            ->select(
                'partidos.id',
                'partidos.dia',
                'partidos.golesl',
                'partidos.golesv',
                'partidos.penalesl',
                'partidos.penalesv',
                'fecha.numero as fecha',
                'torneo.nombre as torneo',
                'torneo.year as year',
                'equipo_local.nombre as equipo_local_nombre',
                'equipo_visitante.nombre as equipo_visitante_nombre',
                'equipo_local.escudo as equipo_local_escudo',
                'equipo_visitante.escudo as equipo_visitante_escudo'
            )
            ->whereNotNull('golesl')
            ->whereNotNull('golesv')
            ->orderBy('year','DESC')
            ->orderBy('torneo')
            ->orderBy('fecha')
            ->paginate();
        //$queries = DB::getQueryLog();
        //dd($queries);

        $jueces = DB::table(DB::raw('(
                    SELECT partido_id, COUNT(partido_id)
                    FROM partido_arbitros
                    GROUP BY partido_id
                    HAVING COUNT(partido_id)!=3
                ) AS t1'))

            ->select(
                'partidos.id',
                'partidos.dia',
                'partidos.golesl',
                'partidos.golesv',
                'partidos.penalesl',
                'partidos.penalesv',
                'fecha.numero as fecha',
                'torneo.nombre as torneo',
                'torneo.year as year',
                'equipo_local.nombre as equipo_local_nombre',
                'equipo_visitante.nombre as equipo_visitante_nombre',
                'equipo_local.escudo as equipo_local_escudo',
                'equipo_visitante.escudo as equipo_visitante_escudo'
            )
            ->join('partidos', 't1.partido_id', '=', 'partidos.id')
            ->join('equipos as equipo_local', 'partidos.equipol_id', '=', 'equipo_local.id')
            ->join('equipos as equipo_visitante', 'partidos.equipov_id', '=', 'equipo_visitante.id')
            ->join('fechas as fecha', 'partidos.fecha_id', '=', 'fecha.id')
            ->join('grupos as grupo', 'fecha.grupo_id', '=', 'grupo.id')
            ->join('torneos as torneo', 'grupo.torneo_id', '=', 'torneo.id')
            ->whereNotNull('golesl')
            ->whereNotNull('golesv')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('incidencias')
                    ->whereColumn('partidos.id', 'incidencias.partido_id');
            })
            ->orderBy('year','DESC')
            ->orderBy('torneo')
            ->orderBy('fecha')
            ->paginate();
        //dd($arbitros);

        $repetidos = DB::table(DB::raw('(
                    SELECT partido_id, COUNT(partido_id)
                    FROM partido_arbitros
                    GROUP BY partido_id, tipo
                    HAVING COUNT(partido_id)>1
                ) AS t1'))
            ->select(
                'partidos.id',
                'partidos.dia',
                'partidos.golesl',
                'partidos.golesv',
                'partidos.penalesl',
                'partidos.penalesv',
                'fecha.numero as fecha',
                'torneo.nombre as torneo',
                'torneo.year as year',
                'equipo_local.nombre as equipo_local_nombre',
                'equipo_visitante.nombre as equipo_visitante_nombre',
                'equipo_local.escudo as equipo_local_escudo',
                'equipo_visitante.escudo as equipo_visitante_escudo'
            )
            ->join('partidos', 't1.partido_id', '=', 'partidos.id')
            ->join('equipos as equipo_local', 'partidos.equipol_id', '=', 'equipo_local.id')
            ->join('equipos as equipo_visitante', 'partidos.equipov_id', '=', 'equipo_visitante.id')
            ->join('fechas as fecha', 'partidos.fecha_id', '=', 'fecha.id')
            ->join('grupos as grupo', 'fecha.grupo_id', '=', 'grupo.id')
            ->join('torneos as torneo', 'grupo.torneo_id', '=', 'torneo.id')
            ->whereNotNull('golesl')
            ->whereNotNull('golesv')
            ->orderBy('year','DESC')
            ->orderBy('torneo')
            ->orderBy('fecha')
            ->paginate();


        return view('torneos.controlarArbitros', compact('partidos','jueces','repetidos'));
    }

    public function controlarTecnicos(Request $request)
    {
        $partidos = DB::table('partidos')
            ->select(
                'partidos.id',
                'partidos.dia',
                'partidos.golesl',
                'partidos.golesv',
                'partidos.penalesl',
                'partidos.penalesv',
                'fecha.numero as fecha',
                'torneo.nombre as torneo',
                'torneo.year as year',
                'equipo_local.nombre as equipo_local_nombre',
                'equipo_visitante.nombre as equipo_visitante_nombre',
                'equipo_local.escudo as equipo_local_escudo',
                'equipo_visitante.escudo as equipo_visitante_escudo'
            )
            ->join('equipos as equipo_local', 'partidos.equipol_id', '=', 'equipo_local.id')
            ->join('equipos as equipo_visitante', 'partidos.equipov_id', '=', 'equipo_visitante.id')
            ->join('fechas as fecha', 'partidos.fecha_id', '=', 'fecha.id')
            ->join('grupos as grupo', 'fecha.grupo_id', '=', 'grupo.id')
            ->join('torneos as torneo', 'grupo.torneo_id', '=', 'torneo.id')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('partido_tecnicos')
                    ->whereRaw('partidos.id = partido_tecnicos.partido_id AND (partidos.equipol_id = partido_tecnicos.equipo_id OR partidos.equipov_id = partido_tecnicos.equipo_id)')
                    ->groupBy('partido_id');
            })
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('incidencias')
                    ->whereColumn('partidos.id', 'incidencias.partido_id');
            })
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('alineacions')
                    ->whereRaw('(partidos.equipol_id = alineacions.equipo_id OR partidos.equipov_id = alineacions.equipo_id)')
                    ->whereColumn('partidos.id', 'alineacions.partido_id')
                    ->groupBy('alineacions.partido_id');
            })
            ->whereNotNull('partidos.golesl')
            ->whereNotNull('partidos.golesv')
            ->orderBy('torneo.year', 'desc')
            ->orderBy('torneo.nombre')
            ->orderBy('fecha.numero')
            ->paginate();

        return view('torneos.controlarTecnicos', compact('partidos'));
    }

}
