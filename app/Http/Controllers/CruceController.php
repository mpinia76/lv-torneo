<?php

namespace App\Http\Controllers;

use App\Torneo;
use Illuminate\Http\Request;
use App\Cruce;

class CruceController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth')->except([]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if ($request->has('buscarpor')) {
            $nombre = $request->get('buscarpor');
            $request->session()->put('nombre_filtro_cruce', $nombre);
        } else {
            $nombre = $request->session()->get('nombre_filtro_cruce');
        }

        $torneo = null;
        $torneoId = $request->query('torneo_id');
        if ($torneoId) {
            $torneo = Torneo::findOrFail($torneoId);
        }
        $crucesQuery = Cruce::with('torneo');

        // Filtrar por torneo si se pasa
        if ($torneoId) {
            $crucesQuery->where('torneo_id', $torneoId);
        }

        // Filtro por búsqueda
        if ($nombre) {
            $crucesQuery->where(function ($query) use ($nombre) {
                $query->whereHas('torneo', function ($q) use ($nombre) {
                    $q->where('nombre', 'like', "%$nombre%")
                        ->orWhere('year', 'like', "%$nombre%");
                })
                    ->orWhere('fase', 'like', "%$nombre%")
                    ->orWhere('clasificado_1', 'like', "%$nombre%")
                    ->orWhere('clasificado_2', 'like', "%$nombre%");
            });
        }

        $cruces = $crucesQuery
            ->orderBy('torneo_id')
            ->orderBy('fase')
            ->orderBy('orden')
            ->paginate();

        return view('cruces.index', compact('cruces','torneo'));
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        $torneoId = $request->input('torneo_id');
        $torneo = null;

        // Si se proporciona un ID de torneo, buscalo en la base de datos
        if ($torneoId) {
            $torneo = Torneo::findOrFail($torneoId);
        }
        return view('cruces.create', compact('torneo'));
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

        $this->validate($request,[ 'torneo_id'=>'required','fase'=>'required','orden'=>'required','clasificado_1'=>'required','clasificado_2'=>'required']);





        $insert['torneo_id'] = $request->get('torneo_id');
        $insert['dia'] = $request->get('fecha') . ' ' . $request->get('hora');
        $insert['fase'] = $request->get('fase');
        $insert['orden'] = $request->get('orden');
        $insert['clasificado_1'] = $request->get('clasificado_1');
        $insert['clasificado_2'] = $request->get('clasificado_2');
        $torneo=Torneo::findOrFail($request->get('torneo_id'));
        $insert['neutral'] = $torneo->neutral;
        $insert['siguiente_fase'] = $request->get('siguiente_fase');
        $insert['orden_siguiente'] = $request->get('orden_siguiente');

        $cruce = Cruce::create($insert);

        //$cruce = Cruce::create($request->all());

        return redirect()->route('cruces.index', array('torneo_id' => $torneo->id))->with('success','Registro creado satisfactoriamente');

    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {

        $cruce=cruce::findOrFail($id);


        return view('cruces.edit', compact('cruce'));
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
        $this->validate($request,[ 'fase'=>'required','orden'=>'required','clasificado_1'=>'required','clasificado_2'=>'required']);






        $update['dia'] = $request->get('fecha') . ' ' . $request->get('hora');
        $update['fase'] = $request->get('fase');
        $update['orden'] = $request->get('orden');
        $update['clasificado_1'] = $request->get('clasificado_1');
        $update['clasificado_2'] = $request->get('clasificado_2');
        $update['neutral'] = $request->get('neutral');
        $update['siguiente_fase'] = $request->get('siguiente_fase');
        $update['orden_siguiente'] = $request->get('orden_siguiente');

        $cruce=cruce::find($id);
        $cruce->update($update);

        return redirect()->route('cruces.index', array('torneo_id' => $cruce->torneo->id))->with('success','Registro actualizado satisfactoriamente');

    }

}

