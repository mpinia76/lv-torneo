<?php

namespace App\Http\Controllers;

use App\Tecnico;
use Illuminate\Http\Request;
use DB;

class TecnicoController extends Controller
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

        $tecnicos=Tecnico::where('nombre','like',"%$nombre%")->orWhere('apellido','like',"%$nombre%")->orWhere('email','like',"%$nombre%")->orWhere(DB::raw('TIMESTAMPDIFF(YEAR,nacimiento,CURDATE())'),'=',"$nombre")->orderBy('apellido','ASC')->paginate();



        return view('tecnicos.index', compact('tecnicos','tecnicos'));
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
        $this->validate($request,[ 'nombre'=>'required', 'apellido'=>'required','foto' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048']);


        if ($files = $request->file('foto')) {
            $image = $request->file('foto');
            $name = time().'.'.$image->getClientOriginalExtension();
            $destinationPath = public_path('/images');
            $image->move($destinationPath, $name);



            $insert['foto'] = "$name";
        }


        $insert['nombre'] = $request->get('nombre');
        $insert['apellido'] = $request->get('apellido');
        $insert['email'] = $request->get('email');
        $insert['telefono'] = $request->get('telefono');
        $insert['ciudad'] = $request->get('ciudad');
        $insert['observaciones'] = $request->get('observaciones');
        $insert['tipoDocumento'] = $request->get('tipoDocumento');
        $insert['documento'] = $request->get('documento');
        $insert['nacimiento'] = $request->get('nacimiento');
        $insert['fallecimiento'] = $request->get('fallecimiento');

        $tecnico = Tecnico::create($insert);

        if($request->get('partido_id')){
            $partido_id = $request->get('partido_id');
            $redirect = redirect()->route('alineaciones.index',['partidoId' => $partido_id])->with('success','Registro creado satisfactoriamente');

        }

        else{
            $redirect = redirect()->route('tecnicos.index')->with('success','Registro creado satisfactoriamente');
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
        $this->validate($request,[ 'nombre'=>'required', 'apellido'=>'required','foto' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048']);


        if ($files = $request->file('foto')) {
            $image = $request->file('foto');
            $name = time().'.'.$image->getClientOriginalExtension();
            $destinationPath = public_path('/images');
            $image->move($destinationPath, $name);



            $update['foto'] = "$name";
        }


        $update['nombre'] = $request->get('nombre');
        $update['apellido'] = $request->get('apellido');
        $update['email'] = $request->get('email');
        $update['telefono'] = $request->get('telefono');
        $update['ciudad'] = $request->get('ciudad');
        $update['observaciones'] = $request->get('observaciones');
        $update['tipoDocumento'] = $request->get('tipoDocumento');
        $update['documento'] = $request->get('documento');
        $update['nacimiento'] = $request->get('nacimiento');
        $update['fallecimiento'] = $request->get('fallecimiento');


        $tecnico=tecnico::find($id);
        $tecnico->update($update);

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
        return view('tecnicos.ver', compact('tecnico'));
    }


}
