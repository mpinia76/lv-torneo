<?php

namespace App\Http\Controllers;

use App\Jugador;
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
        $this->middleware('auth')->except(['ver']);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {

        $nombre = $request->get('buscarpor');

        $jugadores=Jugador::where('nombre','like',"%$nombre%")->orWhere('apellido','like',"%$nombre%")->orWhere('email','like',"%$nombre%")->orWhere('tipoJugador','like',"%$nombre%")->orWhere(DB::raw('TIMESTAMPDIFF(YEAR,nacimiento,CURDATE())'),'=',"$nombre")->orderBy('apellido','ASC')->paginate();

        //dd($jugadores);
        //
        //$jugadores=Jugador::orderBy('apellido','ASC')->paginate(2);
        //return view('Jugador.index',compact('jugadores'));
        //$jugadores = Jugador::all();
        return view('jugadores.index', compact('jugadores','jugadores'));
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

        $insert['tipoJugador'] = $request->get('tipoJugador');
        $insert['nombre'] = $request->get('nombre');
        $insert['apellido'] = $request->get('apellido');
        $insert['email'] = $request->get('email');
        $insert['telefono'] = $request->get('telefono');
        $insert['ciudad'] = $request->get('ciudad');
        $insert['altura'] = $request->get('altura');
        $insert['peso'] = $request->get('peso');
        $insert['pie'] = $request->get('pie');
        $insert['observaciones'] = $request->get('observaciones');
        $insert['tipoDocumento'] = $request->get('tipoDocumento');
        $insert['documento'] = $request->get('documento');
        $insert['nacimiento'] = $request->get('nacimiento');

        $jugador = Jugador::create($insert);

        if($request->get('plantilla_id')){
            $plantilla_id = $request->get('plantilla_id');
            $redirect = redirect()->route('plantillas.edit',[$plantilla_id])->with('success','Registro creado satisfactoriamente');

        }
        elseif($request->get('torneo_id')){
            $redirect = redirect()->route('plantillas.create', ['torneoId' => $request->get('torneo_id')])->with('success','Registro creado satisfactoriamente');
        }
        else{
            $redirect = redirect()->route('jugadores.index')->with('success','Registro creado satisfactoriamente');
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

        $update['tipoJugador'] = $request->get('tipoJugador');
        $update['nombre'] = $request->get('nombre');
        $update['apellido'] = $request->get('apellido');
        $update['email'] = $request->get('email');
        $update['telefono'] = $request->get('telefono');
        $update['ciudad'] = $request->get('ciudad');
        $update['altura'] = $request->get('altura');
        $update['peso'] = $request->get('peso');
        $update['pie'] = $request->get('pie');
        $update['observaciones'] = $request->get('observaciones');
        $update['tipoDocumento'] = $request->get('tipoDocumento');
        $update['documento'] = $request->get('documento');
        $update['nacimiento'] = $request->get('nacimiento');



        $jugador=jugador::find($id);
        $jugador->update($update);

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
        return view('jugadores.ver', compact('jugador','jugador'));
    }

}
