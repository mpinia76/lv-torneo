<?php

namespace App\Http\Controllers;

use App\Torneo;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use App\Grupo;
use App\PromedioTorneo;
use function GuzzleHttp\Promise\iter_for;

use DB;

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
        $torneos=Torneo::orderBy('year','DESC')->get();
        $torneosAnteriores = $torneos->pluck('full_name', 'id')->prepend('','');
        return view('torneos.create', compact( 'torneosAnteriores'));
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
        DB::beginTransaction();
        $ok=1;
        try {


            $torneo = Torneo::create($request->all());


            for ($i = 1; $i <= $torneo->grupos; $i++) {
                $equipos = $torneo->equipos/$torneo->grupos;




                $grupo = new Grupo([
                    'nombre' => $i,
                    'torneo_id' => $torneo->id,
                    'equipos' => $equipos
                ]);

                try {
                    $grupo->save();
                }catch(QueryException $ex){
                    $error = $ex->getMessage();
                    $ok=0;
                    continue;
                }



            }
            if(count($request->torneoAnterior) > 0)
            {
                foreach($request->torneoAnterior as $item=>$v){

                    $data2=array(
                        'torneo_id'=>$torneo->id,
                        'torneoAnterior_id'=>$request->torneoAnterior[$item]
                    );
                    try {
                        PromedioTorneo::create($data2);
                    }catch(QueryException $ex){
                        $error = $ex->getMessage();
                        $ok=0;
                        continue;
                    }
                }
            }
        }catch(Exception $e){
            //if email or phone exist before in db redirect with error messages
            $ok=0;
        }
        if ($ok){
            DB::commit();
            $respuestaID='success';
            $respuestaMSJ='Registro creado satisfactoriamente';
        }
        else{
            DB::rollback();
            $respuestaID='error';
            $respuestaMSJ=$error;
        }

        return redirect()->route('torneos.index')->with($respuestaID,$respuestaMSJ);
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

        $torneos=Torneo::WHERE('id','!=',$id)->orderBy('year','DESC')->get();
        $torneosAnteriores = $torneos->pluck('full_name', 'id')->prepend('','');

        $promedioTorneos = PromedioTorneo::where('torneo_id','=',"$id")->get();

        return view('torneos.edit', compact('torneo','torneo','torneosAnteriores','promedioTorneos'));
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

        DB::beginTransaction();
        $noBorrar='';
        if($request->promedioTorneo_id)  {

                foreach ($request->promedioTorneo_id as $anterior_id){
                    $noBorrar .=$anterior_id.',';
                }


        }
        $ok=1;

        $torneo=torneo::find($id);
        try {
            $torneo->update($request->all());
            $torneo->grupoDetalle()->delete();
            for ($i = 1; $i <= $torneo->grupos; $i++) {
                $equipos = $torneo->equipos / $torneo->grupos;




                $grupo = new Grupo([
                    'nombre' => $i,
                    'torneo_id' => $torneo->id,
                    'equipos' => $equipos
                ]);

                try {
                    $grupo->save();
                }catch(QueryException $ex){
                    $error = $ex->getMessage();
                    $ok=0;
                    continue;
                }
            }
            if($request->torneoAnterior)
            {
                foreach($request->torneoAnterior as $item=>$v){

                    $data2=array(
                        'torneo_id'=>$id,
                        'torneoAnterior_id'=>$request->torneoAnterior[$item]
                    );
                    try {
                        if (!empty($request->promedioTorneo_id[$item])){
                            $data2['id']=$request->promedioTorneo_id[$item];
                            $promedioTorneo=PromedioTorneo::find($request->promedioTorneo_id[$item]);
                            $promedioTorneo->update($data2);
                        }
                        else{
                            $promedioTorneo = PromedioTorneo::create($data2);
                        }
                        $noBorrar .=$promedioTorneo->id.',';


                    }catch(QueryException $ex){
                        $error = $ex->getMessage();
                        $ok=0;
                        continue;
                    }
                }
            }
        }catch(Exception $e){
            //if email or phone exist before in db redirect with error messages
            $ok=0;
        }
        try {
            PromedioTorneo::where('torneo_id',"$id")->whereNotIn('id', explode(',', $noBorrar))->delete();
        }
        catch(QueryException $ex){
            $error = $ex->getMessage();
            $ok=0;

        }
        if ($ok){
            DB::commit();



            $respuestaID='success';
            $respuestaMSJ='Registro actualizado satisfactoriamente';
        }
        else{
            DB::rollback();
            $respuestaID='error';
            $respuestaMSJ=$error;
        }
        return redirect()->route('torneos.index')->with($respuestaID,$respuestaMSJ);

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
        $torneo->grupoDetalle()->delete();
        PromedioTorneo::where('torneo_id',"$id")->delete();
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
