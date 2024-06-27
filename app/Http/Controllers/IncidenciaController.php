<?php

namespace App\Http\Controllers;

use App\Fecha;
use App\Grupo;
use App\Incidencia;
use App\Partido;
use App\Torneo;
use App\Plantilla;
use Illuminate\Http\Request;

use DB;

class IncidenciaController extends Controller
{

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $torneo_id= $request->query('torneoId');
        $nombre = $request->get('buscarpor');

        //$fecha=Fecha::findOrFail($fecha_id);



        $incidencias=Incidencia::where('torneo_id','=',"$torneo_id")->paginate();




        return view('incidencias.index', compact('incidencias','incidencias'), compact('torneo_id','torneo_id'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {

        $torneo_id= $request->query('torneoId');
        $torneo=Torneo::findOrFail($torneo_id);
        $grupos = Grupo::where('torneo_id', '=',$torneo->id)->get();
        $arrgrupos='';
        foreach ($grupos as $grupo2){
            $arrgrupos .=$grupo2->id.',';
        }

        $equipos = Plantilla::with('equipo')->wherein('grupo_id',explode(',', $arrgrupos))->get()->pluck('equipo.nombre', 'equipo_id')->prepend('','');

        $fechas=Fecha::wherein('grupo_id',explode(',', $arrgrupos))->get();
        $arrfechas='';
        foreach ($fechas as $fecha){
            $arrfechas .=$fecha->id.',';
        }
        //$partidos=Partido::wherein('fecha_id',explode(',', $arrfechas))->orderBy('dia','ASC')->get()->pluck('equipol.nombre', 'partido_id')->prepend('','');
        $partidos = Partido::whereIn('fecha_id', explode(',', $arrfechas))
            ->orderBy('dia', 'ASC')
            ->get()
            ->mapWithKeys(function ($partido) {
                $equipoL = $partido->equipol->nombre;
                $equipoV = $partido->equipov->nombre;
                $dia = $partido->dia;
                $golesL = $partido->golesl;
                $golesV = $partido->golesv;
                $penalesL = $partido->penalesl;
                $penalesV = $partido->penalesv;

                $label = "$equipoL $golesL-$golesV $equipoV";
                return [$partido->id => $label];
            })
            ->prepend('', '');

        //dd($partidos);

        return view('incidencias.create', compact('torneo','equipos','partidos'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {





        try {
            $incidencia = Incidencia::create($request->all());

            $respuestaID='success';
            $respuestaMSJ='Registro creado satisfactoriamente';
        }catch(QueryException $ex){



                $respuestaID='error';
                $respuestaMSJ=$ex->getMessage();




        }

        return redirect()->route('incidencias.index', array('torneoId' => $incidencia->torneo_id))->with($respuestaID,$respuestaMSJ);


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
        $incidencia = Incidencia::findOrFail($id);
        $torneo_id= $incidencia->torneo_id;
        $torneo=Torneo::findOrFail($torneo_id);
        $grupos = Grupo::where('torneo_id', '=',$torneo->id)->get();
        $arrgrupos='';
        foreach ($grupos as $grupo2){
            $arrgrupos .=$grupo2->id.',';
        }

        $equipos = Plantilla::with('equipo')->wherein('grupo_id',explode(',', $arrgrupos))->get()->pluck('equipo.nombre', 'equipo_id')->prepend('','');

        $fechas=Fecha::wherein('grupo_id',explode(',', $arrgrupos))->get();
        $arrfechas='';
        foreach ($fechas as $fecha){
            $arrfechas .=$fecha->id.',';
        }
        //$partidos=Partido::wherein('fecha_id',explode(',', $arrfechas))->orderBy('dia','ASC')->get()->pluck('equipol.nombre', 'partido_id')->prepend('','');
        $partidos = Partido::whereIn('fecha_id', explode(',', $arrfechas))
            ->orderBy('dia', 'ASC')
            ->get()
            ->mapWithKeys(function ($partido) {
                $equipoL = $partido->equipol->nombre;
                $equipoV = $partido->equipov->nombre;
                $dia = $partido->dia;
                $golesL = $partido->golesl;
                $golesV = $partido->golesv;
                $penalesL = $partido->penalesl;
                $penalesV = $partido->penalesv;

                $label = "$equipoL $golesL-$golesV $equipoV";
                return [$partido->id => $label];
            })
            ->prepend('', '');

        //dd($partidos);

        return view('incidencias.edit', compact('torneo','equipos','partidos','incidencia'));

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
        $incidencia= Incidencia::find($id);
        try {
            $incidencia->update($request->all());

            $respuestaID='success';
            $respuestaMSJ='Registro modificado satisfactoriamente';
        }catch(QueryException $ex){



            $respuestaID='error';
            $respuestaMSJ=$ex->getMessage();




        }

        return redirect()->route('incidencias.index', array('torneoId' => $incidencia->torneo_id))->with($respuestaID,$respuestaMSJ);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $incidencia= Incidencia::find($id);
        $torneo_id=$incidencia->torneo_id;
        $incidencia->delete();
        return redirect()->route('incidencias.index', array('torneoId' => $torneo_id))->with('success','Registro eliminado satisfactoriamente');

    }


}
