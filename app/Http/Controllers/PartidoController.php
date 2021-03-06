<?php

namespace App\Http\Controllers;
use App\Arbitro;
use App\Partido;
use App\Fecha;
use App\PartidoArbitro;
use Illuminate\Http\Request;

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


        $arbitros = Arbitro::orderBy('apellido', 'asc')->orderBy('nombre', 'asc')->get();

        $arbitros = $arbitros->pluck('full_name', 'id')->prepend('','');


        //dd($partido->fecha->grupo->torneo->nombre);
        return view('partidos.arbitros', compact('partidoarbitros','partido','arbitros'));
    }
}
