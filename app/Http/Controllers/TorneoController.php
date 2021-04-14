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
        $this->middleware('auth')->except('ver','promediosPublic');
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
        $this->validate($request,[ 'nombre'=>'required', 'year'=>'required', 'equipos'=>'required', 'grupos'=>'required']);
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
            if(!empty($request->torneoAnterior))
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

        $grupos = Grupo::where('torneo_id','=',"$id")->get();

        return view('torneos.edit', compact('torneo','torneosAnteriores','promedioTorneos','grupos'));
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
        $this->validate($request,[ 'nombre'=>'required', 'year'=>'required', 'equipos'=>'required', 'grupos'=>'required']);

        DB::beginTransaction();
        $noBorrar='';
        $noBorrarGrupos='';
        if($request->promedioTorneo_id)  {

                foreach ($request->promedioTorneo_id as $anterior_id){
                    $noBorrar .=$anterior_id.',';
                }


        }
        if($request->grupo_id)  {

            foreach ($request->grupo_id as $grupo_id){
                $noBorrarGrupos .=$grupo_id.',';
            }


        }
        $ok=1;

        $torneo=torneo::find($id);
        try {
            $torneo->update($request->all());
            if($request->nombreGrupo)
            {
                foreach($request->nombreGrupo as $item=>$v){
                    //print_r($request->posicionesGrupo);
                    $posiciones=0;
                    $promedios=0;
                    $penales=0;
                    if($request->get('posicionesGrupo')){
                        if(in_array($request->items[$item], $request->get('posicionesGrupo'))){
                            $posiciones = 1;
                        }
                    }

                    if($request->get('promediosGrupo')) {
                        if (in_array($request->items[$item], $request->get('promediosGrupo'))) {
                            $promedios = 1;
                        }
                    }

                    if($request->get('penalesGrupo')) {
                        if (in_array($request->items[$item], $request->get('penalesGrupo'))) {
                            $penales = 1;
                        }
                    }

                    $data1=array(
                        'torneo_id'=>$id,
                        'nombre'=>$request->nombreGrupo[$item],
                        'equipos'=>$request->equiposGrupo[$item],
                        'posiciones'=>$posiciones,
                        'promedios'=>$promedios,
                        'penales'=>$penales,
                        'agrupacion'=>$request->agrupacionGrupo[$item]
                    );
                    try {
                        if (!empty($request->grupo_id[$item])){
                            $data1['id']=$request->grupo_id[$item];
                            $grupo=Grupo::find($request->grupo_id[$item]);
                            $grupo->update($data1);
                        }
                        else{
                            $grupo = Grupo::create($data1);
                        }
                        $noBorrarGrupos .=$grupo->id.',';


                    }catch(QueryException $ex){
                        $error = $ex->getMessage();
                        $ok=0;
                        continue;
                    }
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
            Grupo::where('torneo_id',"$id")->whereNotIn('id', explode(',', $noBorrarGrupos))->delete();
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
        $request->session()->put('codigoTorneo', $torneo_id);
        return view('torneos.ver', compact('torneo','torneo'));
    }

    public function promedios(Request $request)
    {
        $torneo_id= $request->query('torneoId');
        $torneo=Torneo::findOrFail($torneo_id);


        $promedioTorneos = PromedioTorneo::where('torneo_id','=',"$torneo_id")->orderBy('id','ASC')->get();
        $promedios = array();
        if (count($promedioTorneos)>0){
            $sql='SELECT foto, equipo, (sum(
             case when golesl > golesv then 3 else 0 end
           + case when golesl = golesv then 1 else 0 end
       )/count(*)) promedio,
       count(*) jugados,

       sum(
             case when golesl > golesv then 3 else 0 end
           + case when golesl = golesv then 1 else 0 end
       ) puntaje
from ( ';

            //print_r($promedioTorneos[0]);
            //echo $promedioTorneos[0]->torneoAnterior_id;
            //foreach ($promedioTorneos as $promedioTorneo){
            for ($i=0; $i<count($promedioTorneos); $i++){
                   $sql .='select  equipos.nombre equipo, golesl, golesv, equipos.escudo foto
		 from partidos
		 INNER JOIN equipos ON partidos.equipol_id = equipos.id
		 INNER JOIN fechas ON partidos.fecha_id = fechas.id
		 INNER JOIN plantillas ON plantillas.equipo_id = equipos.id
		 INNER JOIN grupos ON plantillas.grupo_id = grupos.id AND grupos.promedios = 1 AND grupos.torneo_id = '.$torneo_id.'
		 INNER JOIN grupos G1 ON fechas.grupo_id = G1.id AND G1.promedios = 1 AND G1.torneo_id = '.$promedioTorneos[$i]->torneoAnterior_id.'
		 WHERE golesl is not null AND golesv is not null ';
                $sql .=($i+1==count($promedioTorneos))?'':' AND EXISTS (SELECT p2.id FROM plantillas p2 INNER JOIN grupos G2 ON p2.grupo_id = G2.id WHERE plantillas.equipo_id = p2.equipo_id AND G2.torneo_id = '.$promedioTorneos[$i+1]->torneoAnterior_id.')';
     $sql .=' union all
       select equipos.nombre equipo, golesv, golesl, equipos.escudo foto
		 from partidos
		 INNER JOIN equipos ON partidos.equipov_id = equipos.id
		 INNER JOIN fechas ON partidos.fecha_id = fechas.id
		 INNER JOIN plantillas ON plantillas.equipo_id = equipos.id
		 INNER JOIN grupos ON plantillas.grupo_id = grupos.id AND grupos.promedios = 1 AND grupos.torneo_id = '.$torneo_id.'
		 INNER JOIN grupos G1 ON fechas.grupo_id = G1.id AND G1.promedios = 1 AND G1.torneo_id = '.$promedioTorneos[$i]->torneoAnterior_id.'
		 WHERE golesl is not null AND golesv is not null';
                $sql .=($i+1==count($promedioTorneos))?'':' AND EXISTS (SELECT p2.id FROM plantillas p2 INNER JOIN grupos G2 ON p2.grupo_id = G2.id WHERE plantillas.equipo_id = p2.equipo_id AND G2.torneo_id = '.$promedioTorneos[$i+1]->torneoAnterior_id.')';
     $sql .=' union all ';
            }

            $sql .='select  equipos.nombre equipo, golesl, golesv, equipos.escudo foto
		 from partidos
		 INNER JOIN equipos ON partidos.equipol_id = equipos.id
		 INNER JOIN fechas ON partidos.fecha_id = fechas.id
		 INNER JOIN plantillas ON plantillas.equipo_id = equipos.id
		 INNER JOIN grupos ON plantillas.grupo_id = grupos.id AND grupos.promedios = 1 AND grupos.torneo_id = '.$torneo_id.'
		 INNER JOIN grupos G1 ON fechas.grupo_id = G1.id AND G1.promedios = 1 AND G1.torneo_id = '.$torneo_id.'
		 WHERE golesl is not null AND golesv is not null

     union all
       select equipos.nombre equipo, golesv, golesl, equipos.escudo foto
		 from partidos
		 INNER JOIN equipos ON partidos.equipov_id = equipos.id
		 INNER JOIN fechas ON partidos.fecha_id = fechas.id
		 INNER JOIN plantillas ON plantillas.equipo_id = equipos.id
		 INNER JOIN grupos ON plantillas.grupo_id = grupos.id AND grupos.promedios = 1 AND grupos.torneo_id = '.$torneo_id.'
		 INNER JOIN grupos G1 ON fechas.grupo_id = G1.id AND G1.promedios = 1 AND G1.torneo_id = '.$torneo_id.'
		 WHERE golesl is not null AND golesv is not null) a
group by equipo, foto

order by promedio desc, puntaje desc, equipo ASC';


            //echo $sql;
            $promedios = DB::select(DB::raw($sql));
        }


        //dd($promedios);

        $i=1;
        return view('torneos.promedios', compact('torneo','promedios','i'));
    }

    public function promediosPublic(Request $request)
    {
        $torneo_id= $request->query('torneoId');
        $torneo=Torneo::findOrFail($torneo_id);


        $promedioTorneos = PromedioTorneo::where('torneo_id','=',"$torneo_id")->orderBy('id','ASC')->get();
        $promedios = array();
        if (count($promedioTorneos)>0){
            $sql='SELECT foto, equipo, (sum(
             case when golesl > golesv then 3 else 0 end
           + case when golesl = golesv then 1 else 0 end
       )/count(*)) promedio,
       count(*) jugados,

       sum(
             case when golesl > golesv then 3 else 0 end
           + case when golesl = golesv then 1 else 0 end
       ) puntaje
from ( ';

            //print_r($promedioTorneos[0]);
            //echo $promedioTorneos[0]->torneoAnterior_id;
            //foreach ($promedioTorneos as $promedioTorneo){
            for ($i=0; $i<count($promedioTorneos); $i++){
                $sql .='select  equipos.nombre equipo, golesl, golesv, equipos.escudo foto
		 from partidos
		 INNER JOIN equipos ON partidos.equipol_id = equipos.id
		 INNER JOIN fechas ON partidos.fecha_id = fechas.id
		 INNER JOIN plantillas ON plantillas.equipo_id = equipos.id
		 INNER JOIN grupos ON plantillas.grupo_id = grupos.id AND grupos.promedios = 1 AND grupos.torneo_id = '.$torneo_id.'
		 INNER JOIN grupos G1 ON fechas.grupo_id = G1.id AND G1.promedios = 1 AND G1.torneo_id = '.$promedioTorneos[$i]->torneoAnterior_id.'
		 WHERE golesl is not null AND golesv is not null ';
                $sql .=($i+1==count($promedioTorneos))?'':' AND EXISTS (SELECT p2.id FROM plantillas p2 INNER JOIN grupos G2 ON p2.grupo_id = G2.id WHERE plantillas.equipo_id = p2.equipo_id AND G2.torneo_id = '.$promedioTorneos[$i+1]->torneoAnterior_id.')';
                $sql .=' union all
       select equipos.nombre equipo, golesv, golesl, equipos.escudo foto
		 from partidos
		 INNER JOIN equipos ON partidos.equipov_id = equipos.id
		 INNER JOIN fechas ON partidos.fecha_id = fechas.id
		 INNER JOIN plantillas ON plantillas.equipo_id = equipos.id
		 INNER JOIN grupos ON plantillas.grupo_id = grupos.id AND grupos.promedios = 1 AND grupos.torneo_id = '.$torneo_id.'
		 INNER JOIN grupos G1 ON fechas.grupo_id = G1.id AND G1.promedios = 1 AND G1.torneo_id = '.$promedioTorneos[$i]->torneoAnterior_id.'
		 WHERE golesl is not null AND golesv is not null';
                $sql .=($i+1==count($promedioTorneos))?'':' AND EXISTS (SELECT p2.id FROM plantillas p2 INNER JOIN grupos G2 ON p2.grupo_id = G2.id WHERE plantillas.equipo_id = p2.equipo_id AND G2.torneo_id = '.$promedioTorneos[$i+1]->torneoAnterior_id.')';
                $sql .=' union all ';
            }

            $sql .='select  equipos.nombre equipo, golesl, golesv, equipos.escudo foto
		 from partidos
		 INNER JOIN equipos ON partidos.equipol_id = equipos.id
		 INNER JOIN fechas ON partidos.fecha_id = fechas.id
		 INNER JOIN plantillas ON plantillas.equipo_id = equipos.id
		 INNER JOIN grupos ON plantillas.grupo_id = grupos.id AND grupos.promedios = 1 AND grupos.torneo_id = '.$torneo_id.'
		 INNER JOIN grupos G1 ON fechas.grupo_id = G1.id AND G1.promedios = 1 AND G1.torneo_id = '.$torneo_id.'
		 WHERE golesl is not null AND golesv is not null

     union all
       select equipos.nombre equipo, golesv, golesl, equipos.escudo foto
		 from partidos
		 INNER JOIN equipos ON partidos.equipov_id = equipos.id
		 INNER JOIN fechas ON partidos.fecha_id = fechas.id
		 INNER JOIN plantillas ON plantillas.equipo_id = equipos.id
		 INNER JOIN grupos ON plantillas.grupo_id = grupos.id AND grupos.promedios = 1 AND grupos.torneo_id = '.$torneo_id.'
		 INNER JOIN grupos G1 ON fechas.grupo_id = G1.id AND G1.promedios = 1 AND G1.torneo_id = '.$torneo_id.'
		 WHERE golesl is not null AND golesv is not null) a
group by equipo, foto

order by promedio desc, puntaje desc, equipo ASC';


            //echo $sql;
            $promedios = DB::select(DB::raw($sql));
        }


        //dd($promedios);

        $i=1;
        return view('torneos.promediosPublic', compact('torneo','promedios','i'));
    }

}
