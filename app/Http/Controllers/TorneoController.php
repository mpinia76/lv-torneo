<?php

namespace App\Http\Controllers;

use App\Torneo;
use Illuminate\Http\Request;
use App\Grupo;
use function GuzzleHttp\Promise\iter_for;

class TorneoController extends Controller
{

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //$this->middleware('auth');
        $this->middleware('auth')->except('ver');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $nombre = $request->get('buscarpor');

        $torneos=Torneo::where('nombre','like',"%$nombre%")->orWhere('year','like',"%$nombre%")->orderBy('year','DESC')->paginate();

        return view('torneos.index', compact('torneos','torneos'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
        return view('torneos.create');
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
        $this->validate($request,[ 'nombre'=>'required', 'year'=>'required', 'equipos'=>'required', 'grupos'=>'required', 'playoffs'=>'required']);

        $torneo = Torneo::create($request->all());
        //print_r($torneo);

        for ($i = 1; $i <= $torneo->grupos; $i++) {
            $equipos = $torneo->equipos/$torneo->grupos;


            /*app(GrupoController::class)->store( (object)[
                'nombre' => $i,
                'torneo_id' => $torneo->id,
                'equipos' => $equipos
            ]);*/

            $grupo = new Grupo([
                'nombre' => $i,
                'torneo_id' => $torneo->id,
                'equipos' => $equipos
            ]);

            $grupo->save();

        }

        return redirect()->route('torneos.index')->with('success','Registro creado satisfactoriamente');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $torneo=Torneo::findOrFail($id);
        return view('torneos.show', compact('torneo','torneo'));
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
        $torneo=torneo::findOrFail($id);

        return view('torneos.edit', compact('torneo','torneo'));
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
        $this->validate($request,[ 'nombre'=>'required', 'year'=>'required', 'equipos'=>'required', 'grupos'=>'required', 'playoffs'=>'required']);

        $torneo=torneo::find($id);
        $torneo->update($request->all());
        $torneo->grupos()->delete();
        for ($i = 1; $i <= $torneo->grupos; $i++) {
            $equipos = $torneo->equipos / $torneo->grupos;


            /*app(GrupoController::class)->store( (object)[
                'nombre' => $i,
                'torneo_id' => $torneo->id,
                'equipos' => $equipos
            ]);*/

            $grupo = new Grupo([
                'nombre' => $i,
                'torneo_id' => $torneo->id,
                'equipos' => $equipos
            ]);

            $grupo->save();
        }
        return redirect()->route('torneos.index')->with('success','Registro actualizado satisfactoriamente');

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
    	$torneo = Torneo::find($id);
        $torneo->grupos()->delete();
        $torneo->delete();
        return redirect()->route('torneos.index')->with('success','Registro eliminado satisfactoriamente');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function ver(Request $request)
    {
        $torneo_id= $request->query('torneoId');

        $torneo=Torneo::findOrFail($torneo_id);
        $request->session()->put('nombreTorneo', $torneo->nombre.' '.$torneo->year);
        return view('torneos.ver', compact('torneo','torneo'));
    }

}
