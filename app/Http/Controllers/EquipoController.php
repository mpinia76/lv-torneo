<?php

namespace App\Http\Controllers;

use App\Equipo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use DB;

class EquipoController extends Controller
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

        $equipos=Equipo::orwhere('nombre','like',"%$nombre%")->orWhere('siglas','like',"%$nombre%")->orWhere('socios','like',"%$nombre%")->orWhere(DB::raw('TIMESTAMPDIFF(YEAR,fundacion,CURDATE())'),'=',"$nombre")->orderBy('nombre','ASC')->paginate();


        //
        //$equipos=Equipo::orderBy('apellido','ASC')->paginate(2);
        //return view('Equipo.index',compact('equipos'));
        //$equipos = Equipo::all();
        return view('equipos.index', compact('equipos','equipos'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
        return view('equipos.create');
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

        $this->validate($request,[ 'nombre'=>'required','escudo' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048']);


        if ($files = $request->file('escudo')) {
            $image = $request->file('escudo');
            $name = time().'.'.$image->getClientOriginalExtension();
            $destinationPath = public_path('/images');
            $image->move($destinationPath, $name);


            /*$destinationPath = 'public/image/'; // upload path
            $profileImage = date('YmdHis') . "." . $files->getClientOriginalExtension();
            Log::info($profileImage);
            $files->move($destinationPath, $profileImage);*/
            $insert['escudo'] = "$name";
        }


        $insert['nombre'] = $request->get('nombre');
        $insert['siglas'] = $request->get('siglas');
        $insert['socios'] = $request->get('socios');
        $insert['fundacion'] = $request->get('fundacion');
        $insert['estadio'] = $request->get('estadio');
        $insert['historia'] = $request->get('historia');


        $equipo = Equipo::create($insert);

        //$equipo = Equipo::create($request->all());

        return redirect()->route('equipos.index')->with('success','Registro creado satisfactoriamente');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $equipo=Equipo::findOrFail($id);
        return view('equipos.show', compact('equipo','equipo'));
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
        $equipo=equipo::findOrFail($id);

        return view('equipos.edit', compact('equipo','equipo'));
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
        $this->validate($request,[ 'nombre'=>'required','escudo' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048']);


        if ($files = $request->file('escudo')) {
            $image = $request->file('escudo');
            $name = time().'.'.$image->getClientOriginalExtension();
            $destinationPath = public_path('/images');
            $image->move($destinationPath, $name);


            /*$destinationPath = 'public/image/'; // upload path
            $profileImage = date('YmdHis') . "." . $files->getClientOriginalExtension();
            Log::info($profileImage);
            $files->move($destinationPath, $profileImage);*/
            $insert['escudo'] = "$name";
        }


        $update['nombre'] = $request->get('nombre');
        $update['siglas'] = $request->get('siglas');
        $update['socios'] = $request->get('socios');
        $update['fundacion'] = $request->get('fundacion');
        $update['estadio'] = $request->get('estadio');
        $update['historia'] = $request->get('historia');



        $equipo=equipo::find($id);
        $equipo->update($update);

        return redirect()->route('equipos.index')->with('success','Registro actualizado satisfactoriamente');

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $equipo = Equipo::find($id);

        $equipo->delete();
        return redirect()->route('equipos.index')->with('success','Registro eliminado satisfactoriamente');
    }

    public function findEquipo(Request $req)
    {

        $nombre = $req->input('q');

        //$equipos=Equipo::where('nombre','like',"%$nombre%")->orWhere('siglas','like',"%$nombre%");

        $equipos=Equipo::where('nombre','like',"%$nombre%")->orWhere('siglas','like',"%$nombre%")->orderBy('nombre','ASC')->get();

        return $equipos;
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function ver(Request $request)
    {
        $id= $request->query('equipoId');
        $equipo=Equipo::findOrFail($id);
        return view('equipos.ver', compact('equipo','equipo'));
    }

}


