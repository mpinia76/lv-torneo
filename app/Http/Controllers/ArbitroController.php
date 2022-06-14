<?php

namespace App\Http\Controllers;

use App\Arbitro;
use App\Persona;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use DB;

class ArbitroController extends Controller
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

        //$arbitros=Arbitro::where('nombre','like',"%$nombre%")->orWhere('apellido','like',"%$nombre%")->orWhere('email','like',"%$nombre%")->orWhere(DB::raw('TIMESTAMPDIFF(YEAR,nacimiento,CURDATE())'),'=',"$nombre")->orderBy('apellido','ASC')->paginate();

        $arbitros=Arbitro::SELECT('arbitros.*','personas.nombre','personas.apellido','personas.nacimiento','personas.fallecimiento','personas.email','personas.foto')->Join('personas','personas.id','=','arbitros.persona_id')->where('nombre','like',"%$nombre%")->orWhere('apellido','like',"%$nombre%")->orWhere('email','like',"%$nombre%")->orWhere(DB::raw('TIMESTAMPDIFF(YEAR,nacimiento,CURDATE())'),'=',"$nombre")->orderBy('apellido','ASC')->paginate();

        return view('arbitros.index', compact('arbitros','arbitros'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {

        if($request->get('partidoId')){
            $partido_id = $request->get('partidoId');
            $vista =view('arbitros.create', compact('partido_id'));
        }
        else {
            $vista =view('arbitros.create');
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

        //$arbitro = Arbitro::create($insert);

        try {
            $persona = Persona::create($insert);
            $persona->arbitro()->create($insert);

            $respuestaID='success';
            $respuestaMSJ='Registro creado satisfactoriamente';
        }catch(QueryException $ex){

            try {
                $persona = Persona::where('nombre','=',$insert['nombre'])->Where('apellido','=',$insert['apellido'])->Where('nacimiento','=',$insert['nacimiento'])->first();
                if (!empty($persona)){
                    $persona->arbitro()->create($insert);
                    $respuestaID='success';
                    $respuestaMSJ='Registro creado satisfactoriamente';
                }
            }catch(QueryException $ex){

                $respuestaID='error';
                $respuestaMSJ=$ex->getMessage();

            }


        }

        if($request->get('partido_id')){
            $redirect = redirect()->route('partidos.arbitros', ['partidoId' => $request->get('partido_id')])->with($respuestaID,$respuestaMSJ);
        }
        else{
            $redirect = redirect()->route('arbitros.index')->with($respuestaID,$respuestaMSJ);
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
        $arbitro=Arbitro::findOrFail($id);
        return view('arbitros.show', compact('arbitro','arbitro'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $arbitro=arbitro::findOrFail($id);

        return view('arbitros.edit', compact('arbitro','arbitro'));
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



        $arbitro=arbitro::find($id);
        //$arbitro->update($update);
        $arbitro->persona()->update($update);

        return redirect()->route('arbitros.index')->with('success','Registro actualizado satisfactoriamente');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $arbitro = Arbitro::find($id);

        $arbitro->delete();
        return redirect()->route('arbitros.index')->with('success','Registro eliminado satisfactoriamente');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function ver(Request $request)
    {
        $id= $request->query('arbitroId');
        $arbitro=Arbitro::findOrFail($id);
        return view('arbitros.ver', compact('arbitro'));
    }
}
