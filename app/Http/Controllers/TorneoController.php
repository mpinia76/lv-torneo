<?php

namespace App\Http\Controllers;

use App\AcumuladoTorneo;
use App\Fecha;
use App\Plantilla;
use App\Torneo;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use App\Grupo;
use App\PromedioTorneo;
use App\Equipo;

use Illuminate\Support\Facades\Log;
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
        $this->middleware('auth')->except('ver','promediosPublic','historiales','goleadores','tarjetas','posiciones','estadisticasTorneo','estadisticasOtras', 'tecnicos', 'arqueros','acumulado','jugadores');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if ($request->has('buscarpor')){
            $nombre = $request->get('buscarpor');

            $request->session()->put('nombre_filtro_torneo', $request->get('buscarpor'));

        }
        else{
            $nombre = $request->session()->get('nombre_filtro_torneo');

        }

        $torneos1=Torneo::where('nombre','like',"%$nombre%")->orWhere('year','like',"%$nombre%")->orderBy('year','DESC')->paginate();

        return view('torneos.index', compact('torneos1'));
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
            if(!empty($request->torneoAnteriorAcumulado))
            {
                foreach($request->torneoAnteriorAcumulado as $item=>$v){

                    $data2=array(
                        'torneo_id'=>$torneo->id,
                        'torneoAnterior_id'=>$request->torneoAnteriorAcumulado[$item]
                    );
                    try {
                        AcumuladoTorneo::create($data2);
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

        $acumuladoTorneos = AcumuladoTorneo::where('torneo_id','=',"$id")->get();

        $grupos = Grupo::where('torneo_id','=',"$id")->get();

        return view('torneos.edit', compact('torneo','torneosAnteriores','promedioTorneos','grupos','acumuladoTorneos'));
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
        //print_r($request);
        $this->validate($request,[ 'nombre'=>'required', 'year'=>'required', 'equipos'=>'required', 'grupos'=>'required']);

        DB::beginTransaction();
        $noBorrar='';
        $noBorrarAcumulado='';
        $noBorrarGrupos='';
        if($request->promedioTorneo_id)  {

                foreach ($request->promedioTorneo_id as $anterior_id){
                    $noBorrar .=$anterior_id.',';
                }


        }
        if($request->acumuladoTorneo_id)  {

            foreach ($request->acumuladoTorneo_id as $anterior_id){
                $noBorrarAcumulado .=$anterior_id.',';
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
                    $acumulado=0;
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

                    if($request->get('acumuladoGrupo')) {
                        if (in_array($request->items[$item], $request->get('acumuladoGrupo'))) {
                            $acumulado = 1;
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
                        'acumulado'=>$acumulado,
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
            if($request->torneoAnteriorAcumulado)
            {
                foreach($request->torneoAnteriorAcumulado as $item=>$v){

                    $data2=array(
                        'torneo_id'=>$id,
                        'torneoAnterior_id'=>$request->torneoAnteriorAcumulado[$item]
                    );
                    try {
                        if (!empty($request->acumuladoTorneo_id[$item])){
                            $data2['id']=$request->acumuladoTorneo_id[$item];
                            $acumuladoTorneo=AcumuladoTorneo::find($request->acumuladoTorneo_id[$item]);
                            $acumuladoTorneo->update($data2);
                        }
                        else{
                            $acumuladoTorneo = AcumuladoTorneo::create($data2);
                        }
                        $noBorrarAcumulado .=$acumuladoTorneo->id.',';


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
            AcumuladoTorneo::where('torneo_id',"$id")->whereNotIn('id', explode(',', $noBorrarAcumulado))->delete();
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
        AcumuladoTorneo::where('torneo_id',"$id")->delete();
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


        $grupos = Grupo::where('torneo_id', '=',$torneo_id)->get();
        $arrgrupos='';
        foreach ($grupos as $grupo){
            $arrgrupos .=$grupo->id.',';
        }
        $arrgrupos = substr($arrgrupos, 0, -1);//quito última coma



        $equipos = Plantilla::with('equipo')->wherein('grupo_id',explode(',', $arrgrupos))->get()->pluck('equipo.nombre', 'equipo_id');


        //print_r($equipos);


        $promedioTorneos = PromedioTorneo::where('torneo_id','=',"$torneo_id")->orderBy('id','DESC')->get();
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
       ) puntaje, equipo_id
from ( ';

            //print_r($promedioTorneos[0]);
            //echo $promedioTorneos[0]->torneoAnterior_id;
            foreach ($promedioTorneos as $promedioTorneo){
                $grupos = Grupo::where('torneo_id', '=',$promedioTorneo->torneoAnterior_id)->get();
                $arrgrupos='';
                foreach ($grupos as $grupo){
                    $arrgrupos .=$grupo->id.',';
                }
                $arrgrupos = substr($arrgrupos, 0, -1);//quito última coma



                $equiposAux = Plantilla::with('equipo')->wherein('grupo_id',explode(',', $arrgrupos))->get()->pluck('equipo.nombre', 'equipo_id');



                $borrar = array();
                foreach ($equipos as $key => $value){

                    if (!$equiposAux->contains($value) ){
                        $borrar[]=$key;
                    }
                }

                foreach ($borrar as $key){
                    $equipos->forget($key);
                }

                $arrequipos='';
                foreach ($equipos as $key => $value){
                    $arrequipos .=$key.',';
                }
                $arrequipos = substr($arrequipos, 0, -1);//quito última coma





                $sql .='select  equipos.nombre equipo, golesl, golesv, equipos.escudo foto, equipos.id equipo_id
		 from partidos
		 INNER JOIN equipos ON partidos.equipol_id = equipos.id
		 INNER JOIN fechas ON partidos.fecha_id = fechas.id
		 INNER JOIN plantillas ON plantillas.equipo_id = equipos.id
		 INNER JOIN grupos ON plantillas.grupo_id = grupos.id AND grupos.promedios = 1 AND grupos.torneo_id = '.$torneo_id.'
		 INNER JOIN grupos G1 ON fechas.grupo_id = G1.id AND G1.promedios = 1 AND G1.torneo_id = '.$promedioTorneo->torneoAnterior_id.'
		 WHERE golesl is not null AND golesv is not null ';
                $sql .=' AND equipos.id IN ('.$arrequipos.')';
                $sql .=' union all
       select equipos.nombre equipo, golesv, golesl, equipos.escudo foto, equipos.id equipo_id
		 from partidos
		 INNER JOIN equipos ON partidos.equipov_id = equipos.id
		 INNER JOIN fechas ON partidos.fecha_id = fechas.id
		 INNER JOIN plantillas ON plantillas.equipo_id = equipos.id
		 INNER JOIN grupos ON plantillas.grupo_id = grupos.id AND grupos.promedios = 1 AND grupos.torneo_id = '.$torneo_id.'
		 INNER JOIN grupos G1 ON fechas.grupo_id = G1.id AND G1.promedios = 1 AND G1.torneo_id = '.$promedioTorneo->torneoAnterior_id.'
		 WHERE golesl is not null AND golesv is not null';
                $sql .=' AND equipos.id IN ('.$arrequipos.')';
                $sql .=' union all ';
            }

            $sql .='select  equipos.nombre equipo, golesl, golesv, equipos.escudo foto, equipos.id equipo_id
		 from partidos
		 INNER JOIN equipos ON partidos.equipol_id = equipos.id
		 INNER JOIN fechas ON partidos.fecha_id = fechas.id
		 INNER JOIN plantillas ON plantillas.equipo_id = equipos.id
		 INNER JOIN grupos ON plantillas.grupo_id = grupos.id AND grupos.promedios = 1 AND grupos.torneo_id = '.$torneo_id.'
		 INNER JOIN grupos G1 ON fechas.grupo_id = G1.id AND G1.promedios = 1 AND G1.torneo_id = '.$torneo_id.'
		 WHERE golesl is not null AND golesv is not null

     union all
       select equipos.nombre equipo, golesv, golesl, equipos.escudo foto, equipos.id equipo_id
		 from partidos
		 INNER JOIN equipos ON partidos.equipov_id = equipos.id
		 INNER JOIN fechas ON partidos.fecha_id = fechas.id
		 INNER JOIN plantillas ON plantillas.equipo_id = equipos.id
		 INNER JOIN grupos ON plantillas.grupo_id = grupos.id AND grupos.promedios = 1 AND grupos.torneo_id = '.$torneo_id.'
		 INNER JOIN grupos G1 ON fechas.grupo_id = G1.id AND G1.promedios = 1 AND G1.torneo_id = '.$torneo_id.'
		 WHERE golesl is not null AND golesv is not null) a
group by equipo, foto, equipo_id

order by promedio desc, puntaje desc, equipo ASC';


            //echo $sql;
            $promedios = DB::select(DB::raw($sql));
        }


        //dd($promedios);

        $i=1;
        return view('torneos.promediosPublic', compact('torneo','promedios','i'));
    }


    public function acumulado(Request $request)
    {
        $torneo_id= $request->query('torneoId');
        $torneo=Torneo::findOrFail($torneo_id);


        $grupos = Grupo::where('torneo_id', '=',$torneo_id)->get();
        $arrgrupos='';
        foreach ($grupos as $grupo){
            $arrgrupos .=$grupo->id.',';
        }
        $arrgrupos = substr($arrgrupos, 0, -1);//quito última coma



        $equipos = Plantilla::with('equipo')->wherein('grupo_id',explode(',', $arrgrupos))->get()->pluck('equipo.nombre', 'equipo_id');


        //print_r($equipos);


        $acumuladoTorneos = AcumuladoTorneo::where('torneo_id','=',"$torneo_id")->orderBy('id','DESC')->get();
        $acumulado = array();
        if (count($acumuladoTorneos)>0){
            $sql='SELECT foto, equipo,
       count(*) jugados,
       count(case when golesl > golesv then 1 end) ganados,
       count(case when golesv > golesl then 1 end) perdidos,
       count(case when golesl = golesv then 1 end) empatados,
       sum(golesl) golesl,
       sum(golesv) golesv,
       sum(golesl) - sum(golesv) diferencia,
       sum(
             case when golesl > golesv then 3 else 0 end
           + case when golesl = golesv then 1 else 0 end
       ) puntaje, (sum(
             case when golesl > golesv then 3 else 0 end
           + case when golesl = golesv then 1 else 0 end
       )/count(*)) promedio, equipo_id

from ( ';


            foreach ($acumuladoTorneos as $acumuladoTorneo){
                $grupos = Grupo::where('torneo_id', '=',$acumuladoTorneo->torneoAnterior_id)->get();
                $arrgrupos='';
                foreach ($grupos as $grupo){
                    $arrgrupos .=$grupo->id.',';
                }
                $arrgrupos = substr($arrgrupos, 0, -1);//quito última coma



                $equiposAux = Plantilla::with('equipo')->wherein('grupo_id',explode(',', $arrgrupos))->get()->pluck('equipo.nombre', 'equipo_id');



                $borrar = array();
                foreach ($equipos as $key => $value){

                    if (!$equiposAux->contains($value) ){
                        $borrar[]=$key;
                    }
                }

                foreach ($borrar as $key){
                    $equipos->forget($key);
                }

                $arrequipos='';
                foreach ($equipos as $key => $value){
                    $arrequipos .=$key.',';
                }
                $arrequipos = substr($arrequipos, 0, -1);//quito última coma





                $sql .='select  equipos.nombre equipo, golesl, golesv, equipos.escudo foto, equipos.id equipo_id
		 from partidos
		 INNER JOIN equipos ON partidos.equipol_id = equipos.id
		 INNER JOIN fechas ON partidos.fecha_id = fechas.id
		 INNER JOIN plantillas ON plantillas.equipo_id = equipos.id
		 INNER JOIN grupos ON plantillas.grupo_id = grupos.id AND grupos.acumulado = 1 AND grupos.torneo_id = '.$torneo_id.'
		 INNER JOIN grupos G1 ON fechas.grupo_id = G1.id AND G1.acumulado = 1 AND G1.torneo_id = '.$acumuladoTorneo->torneoAnterior_id.'
		 WHERE golesl is not null AND golesv is not null ';
                $sql .=' AND equipos.id IN ('.$arrequipos.')';
                $sql .=' union all
       select equipos.nombre equipo, golesv, golesl, equipos.escudo foto, equipos.id equipo_id
		 from partidos
		 INNER JOIN equipos ON partidos.equipov_id = equipos.id
		 INNER JOIN fechas ON partidos.fecha_id = fechas.id
		 INNER JOIN plantillas ON plantillas.equipo_id = equipos.id
		 INNER JOIN grupos ON plantillas.grupo_id = grupos.id AND grupos.acumulado = 1 AND grupos.torneo_id = '.$torneo_id.'
		 INNER JOIN grupos G1 ON fechas.grupo_id = G1.id AND G1.acumulado = 1 AND G1.torneo_id = '.$acumuladoTorneo->torneoAnterior_id.'
		 WHERE golesl is not null AND golesv is not null';
                $sql .=' AND equipos.id IN ('.$arrequipos.')';
                $sql .=' union all ';
            }

            $sql .='select  equipos.nombre equipo, golesl, golesv, equipos.escudo foto, equipos.id equipo_id
		 from partidos
		 INNER JOIN equipos ON partidos.equipol_id = equipos.id
		 INNER JOIN fechas ON partidos.fecha_id = fechas.id
		 INNER JOIN plantillas ON plantillas.equipo_id = equipos.id
		 INNER JOIN grupos ON plantillas.grupo_id = grupos.id AND grupos.acumulado = 1 AND grupos.torneo_id = '.$torneo_id.'
		 INNER JOIN grupos G1 ON fechas.grupo_id = G1.id AND G1.acumulado = 1 AND G1.torneo_id = '.$torneo_id.'
		 WHERE golesl is not null AND golesv is not null

     union all
       select equipos.nombre equipo, golesv, golesl, equipos.escudo foto, equipos.id equipo_id
		 from partidos
		 INNER JOIN equipos ON partidos.equipov_id = equipos.id
		 INNER JOIN fechas ON partidos.fecha_id = fechas.id
		 INNER JOIN plantillas ON plantillas.equipo_id = equipos.id
		 INNER JOIN grupos ON plantillas.grupo_id = grupos.id AND grupos.acumulado = 1 AND grupos.torneo_id = '.$torneo_id.'
		 INNER JOIN grupos G1 ON fechas.grupo_id = G1.id AND G1.acumulado = 1 AND G1.torneo_id = '.$torneo_id.'
		 WHERE golesl is not null AND golesv is not null) a


group by equipo, foto, equipo_id

order by  puntaje desc, promedio DESC, diferencia DESC, golesl DESC, equipo ASC';
            //echo $sql;
            $acumulado = DB::select(DB::raw($sql));
        }


        //dd($promedios);

        $i=1;
        return view('torneos.acumulado', compact('torneo','acumulado','i'));
    }


    public function historiales(Request $request)
    {
        $equipo1= $request->query('equipo1');

        if (!empty($equipo1)){
            $e1=Equipo::findOrFail($equipo1);
        }
        else{
            $e1 = new Equipo();
        }

        $equipo2= $request->query('equipo2');

        if (!empty($equipo2)){
            $e2=Equipo::findOrFail($equipo2);
        }
        else{
            $e2 = new Equipo();
        }


        $equipos = Equipo::orderBy('nombre', 'asc')->get();

        $partidos=array();
        $posiciones=array();

        if ($equipo1 && $equipo2){
            $sql='SELECT torneos.nombre AS nombreTorneo, torneos.year, fechas.numero, partidos.dia, e1.id AS equipol_id, e1.escudo AS fotoLocal, e1.nombre AS local, e2.id AS equipov_id,e2.escudo AS fotoVisitante, e2.nombre AS visitante, partidos.golesl,
partidos.golesv, partidos.penalesl, partidos.penalesv, partidos.id partido_id
FROM partidos
INNER JOIN equipos e1 ON partidos.equipol_id = e1.id
INNER JOIN equipos e2 ON partidos.equipov_id = e2.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON fechas.grupo_id = grupos.id
INNER JOIN torneos ON grupos.torneo_id = torneos.id
WHERE (e1.id = '.$equipo1.' and e2.id = '.$equipo2.')  OR (e1.id = '.$equipo2.' and e2.id = '.$equipo1.')
ORDER BY partidos.dia ASC';


            //echo $sql;
            $partidos = DB::select(DB::raw($sql));

            $sql='SELECT foto, equipo,
       count(*) jugados,
       count(case when golesl > golesv then 1 end) ganados,
       count(case when golesv > golesl then 1 end) perdidos,
       count(case when golesl = golesv then 1 end) empatados,
       sum(golesl) golesl,
       sum(golesv) golesv,
       sum(golesl) - sum(golesv) diferencia,
       sum(
             case when golesl > golesv then 3 else 0 end
           + case when golesl = golesv then 1 else 0 end
       ) puntaje, equipo_id
from (
       select  DISTINCT equipos.nombre equipo, golesl, golesv, equipos.escudo foto, fechas.id fecha_id, equipos.id equipo_id
		 from partidos
		 INNER JOIN equipos ON partidos.equipol_id = equipos.id
		 INNER JOIN plantillas ON plantillas.equipo_id = equipos.id
		 INNER JOIN fechas ON partidos.fecha_id = fechas.id
		 INNER JOIN grupos ON fechas.grupo_id = grupos.id
		 WHERE golesl is not null AND golesv is not null AND (partidos.equipol_id = '.$equipo1.' and partidos.equipov_id = '.$equipo2.')  OR (partidos.equipov_id = '.$equipo1.' and partidos.equipol_id = '.$equipo2.')
     union all
       select DISTINCT equipos.nombre equipo, golesv, golesl, equipos.escudo foto, fechas.id fecha_id, equipos.id equipo_id
		 from partidos
		 INNER JOIN equipos ON partidos.equipov_id = equipos.id
		 INNER JOIN plantillas ON plantillas.equipo_id = equipos.id
		 INNER JOIN fechas ON partidos.fecha_id = fechas.id
		 INNER JOIN grupos ON fechas.grupo_id = grupos.id
		 WHERE golesl is not null AND golesv is not NULL AND (partidos.equipol_id = '.$equipo1.' and partidos.equipov_id = '.$equipo2.')  OR (partidos.equipov_id = '.$equipo1.' and partidos.equipol_id = '.$equipo2.')
) a
group by equipo, foto, equipo_id

order by puntaje desc, diferencia DESC, golesl DESC, equipo ASC';


            //echo $sql;
            $posiciones = DB::select(DB::raw($sql));
        }





        return view('torneos.historiales', compact('equipos','e1','e2', 'partidos','posiciones'));
    }

    public function goleadores(Request $request)
    {

        $order= ($request->query('order'))?$request->query('order'):'Goles';
        $tipoOrder= ($request->query('tipoOrder'))?$request->query('tipoOrder'):'DESC';
        $actuales= ($request->query('actuales'))?1:0;

        if($request->query('torneoId')) {
            $torneoId = $request->query('torneoId');

        }
        else{
            $torneo=Torneo::orderBy('year','DESC')->first();
            $torneoId = $torneo->id;
        }

        $torneos=Torneo::orderBy('year','DESC')->get();

        $sql = 'SELECT jugadors.id, CONCAT(personas.apellido,\', \',personas.nombre) jugador, personas.foto, COUNT(gols.id) goles, count( case when tipo=\'Jugada\' then 1 else NULL end) as  Jugada, "" as escudo, "0" as jugados
, count( case when tipo=\'Cabeza\' then 1 else NULL end) as  Cabeza, count( case when tipo=\'Penal\' then 1 else NULL end) as  Penal, count( case when tipo=\'Tiro Libre\' then 1 else NULL end) as  Tiro_Libre, "" AS jugando
FROM gols
INNER JOIN jugadors ON gols.jugador_id = jugadors.id
INNER JOIN partidos ON gols.partido_id = partidos.id
INNER JOIN personas ON jugadors.persona_id = personas.id

WHERE gols.tipo <> \'En contra\'';
        $sql .=($actuales)?' AND EXISTS (
SELECT DISTINCT J1.id
FROM alineacions
INNER JOIN jugadors J1 ON alineacions.jugador_id = J1.id
INNER JOIN partidos P1 ON alineacions.partido_id = P1.id
INNER JOIN fechas F1 ON P1.fecha_id = F1.id
INNER JOIN grupos G1 ON G1.id = F1.grupo_id

WHERE G1.torneo_id = '.$torneoId.' AND J1.id = jugadors.id

)':'';

$sql .=' GROUP BY jugadors.id,jugador, foto
ORDER BY '.$order.' '.$tipoOrder.', jugador ASC';




        $goleadores = DB::select(DB::raw($sql));



        $page = $request->query('page', 1);

        $paginate = 15;

        $offSet = ($page * $paginate) - $paginate;

        $itemsForCurrentPage = array_slice($goleadores, $offSet, $paginate, true);



        $goleadores = new \Illuminate\Pagination\LengthAwarePaginator($itemsForCurrentPage, count($goleadores), $paginate, $page);


        foreach ($goleadores as $goleador){

            $sqlJugando = 'SELECT DISTINCT equipos.escudo, alineacions.equipo_id
FROM alineacions
INNER JOIN jugadors ON alineacions.jugador_id = jugadors.id
INNER JOIN personas ON jugadors.persona_id = personas.id
INNER JOIN partidos ON alineacions.partido_id = partidos.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id
INNER JOIN equipos ON alineacions.equipo_id = equipos.id
WHERE grupos.torneo_id = '.$torneoId.' AND jugadors.id = '.$goleador->id;
            $jugando = '';
            $juega = DB::select(DB::raw($sqlJugando));
            foreach ($juega as $e){

                $jugando .= $e->escudo.'_'.$e->equipo_id.',';
            }

            $goleador->jugando = $jugando;

            $sql2='SELECT escudo, equipo_id, COUNT(gols.id) goles
FROM equipos
INNER JOIN alineacions ON equipos.id = alineacions.equipo_id
INNER JOIN partidos ON partidos.id = alineacions.partido_id
INNER JOIN gols ON gols.partido_id = partidos.id AND gols.jugador_id = alineacions.jugador_id
WHERE alineacions.jugador_id = '.$goleador->id.' AND gols.tipo <> \'En contra\'
GROUP BY escudo, equipo_id
            ORDER BY partidos.dia ASC';



            $escudos = DB::select(DB::raw($sql2));


            foreach ($escudos as $escudo){

                $goleador->escudo .= $escudo->escudo.'_'.$escudo->equipo_id.'_'.$escudo->goles.',';

            }

            $sql3="SELECT alineacions.jugador_id, COUNT(alineacions.jugador_id) as jugados
FROM torneos t2 INNER JOIN grupos g2 ON t2.id = g2.torneo_id
INNER JOIN fechas ON fechas.grupo_id = g2.id
INNER JOIN partidos ON partidos.fecha_id = fechas.id
INNER JOIN alineacions ON alineacions.partido_id = partidos.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id
WHERE alineacions.tipo = 'Titular' AND alineacions.jugador_id = ".$goleador->id. " GROUP BY alineacions.jugador_id";

            //echo $sql3;

            $jugados = DB::select(DB::raw($sql3));


            foreach ($jugados as $jugado){

                $goleador->jugados += $jugado->jugados;
            }

            $sql4="SELECT cambios.jugador_id, COUNT(cambios.jugador_id)  as jugados
FROM torneos t2 INNER JOIN grupos g2 ON t2.id = g2.torneo_id
INNER JOIN fechas ON fechas.grupo_id = g2.id
INNER JOIN partidos ON partidos.fecha_id = fechas.id
INNER JOIN cambios ON cambios.partido_id = partidos.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id
WHERE cambios.tipo = 'Entra' AND cambios.jugador_id = ".$goleador->id. " GROUP BY cambios.jugador_id";



            $jugados = DB::select(DB::raw($sql4));


            foreach ($jugados as $jugado){

                $goleador->jugados += $jugado->jugados;
            }


        }

        $goleadores->setPath(route('torneos.goleadores',array('order'=>$order,'tipoOrder'=>$tipoOrder,'actuales'=>$actuales,'torneoId'=>$torneoId)));



        $i=$offSet+1;


        return view('torneos.goleadores', compact('goleadores','i','order','tipoOrder','actuales','torneoId'));
    }

    public function tarjetas(Request $request)
    {
        $order= ($request->query('order'))?$request->query('order'):'rojas';
        $tipoOrder= ($request->query('tipoOrder'))?$request->query('tipoOrder'):'DESC';
        $actuales= ($request->query('actuales'))?1:0;

        if($request->query('torneoId')) {
            $torneoId = $request->query('torneoId');

        }
        else{
            $torneo=Torneo::orderBy('year','DESC')->first();
            $torneoId = $torneo->id;
        }

        $torneos=Torneo::orderBy('year','DESC')->get();

        $sql ='SELECT jugadors.id, CONCAT(personas.apellido,\', \',personas.nombre) jugador, personas.foto,count( case when tipo=\'Amarilla\' then 1 else NULL end) as  amarillas
, count( case when tipo=\'Roja\' or tipo=\'Doble Amarilla\' then 1 else NULL end) as  rojas, "" escudo, "0" as jugados, "" as jugando
FROM tarjetas
INNER JOIN jugadors ON tarjetas.jugador_id = jugadors.id
INNER JOIN personas ON jugadors.persona_id = personas.id
INNER JOIN partidos ON tarjetas.partido_id = partidos.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id';
        $sql .=($actuales)?' WHERE EXISTS (
SELECT DISTINCT J1.id
FROM alineacions
INNER JOIN jugadors J1 ON alineacions.jugador_id = J1.id
INNER JOIN partidos P1 ON alineacions.partido_id = P1.id
INNER JOIN fechas F1 ON P1.fecha_id = F1.id
INNER JOIN grupos G1 ON G1.id = F1.grupo_id

WHERE G1.torneo_id = '.$torneoId.' AND J1.id = jugadors.id

)':'';

$sql .=' GROUP BY jugadors.id, jugador, foto
ORDER BY '.$order.' '.$tipoOrder.', amarillas DESC, jugador ASC';


        $tarjetas = DB::select(DB::raw($sql));


        $page = $request->query('page', 1);

        $paginate = 15;

        $offSet = ($page * $paginate) - $paginate;

        $itemsForCurrentPage = array_slice($tarjetas, $offSet, $paginate, true);

        $tarjetas = new \Illuminate\Pagination\LengthAwarePaginator($itemsForCurrentPage, count($tarjetas), $paginate, $page);


        foreach ($tarjetas as $tarjeta){
            $sqlJugando = 'SELECT DISTINCT equipos.escudo, alineacions.equipo_id
FROM alineacions
INNER JOIN jugadors ON alineacions.jugador_id = jugadors.id
INNER JOIN personas ON jugadors.persona_id = personas.id
INNER JOIN partidos ON alineacions.partido_id = partidos.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id
INNER JOIN equipos ON alineacions.equipo_id = equipos.id
WHERE grupos.torneo_id = '.$torneoId.' AND jugadors.id = '.$tarjeta->id;
            $jugando = '';
            $juega = DB::select(DB::raw($sqlJugando));
            foreach ($juega as $e){

                $jugando .= $e->escudo.'_'.$e->equipo_id.',';
            }

            $tarjeta->jugando = $jugando;
            $sql2='SELECT escudo, equipo_id, count( case when tarjetas.tipo=\'Amarilla\' then 1 else NULL end) as  amarillas
, count( case when tarjetas.tipo=\'Roja\' or tarjetas.tipo=\'Doble Amarilla\' then 1 else NULL end) as  rojas
FROM equipos
INNER JOIN alineacions ON equipos.id = alineacions.equipo_id
INNER JOIN partidos ON partidos.id = alineacions.partido_id
INNER JOIN tarjetas ON tarjetas.partido_id = partidos.id AND tarjetas.jugador_id = alineacions.jugador_id
WHERE alineacions.jugador_id = '.$tarjeta->id.'
GROUP BY escudo, equipo_id
ORDER BY partidos.dia ASC';



            $escudos = DB::select(DB::raw($sql2));


            foreach ($escudos as $escudo){

                $tarjeta->escudo .= $escudo->escudo.'_'.$escudo->equipo_id.'_'.$escudo->rojas.'_'.$escudo->amarillas.',';

            }
            $sql3="SELECT alineacions.jugador_id, COUNT(alineacions.jugador_id) as jugados
FROM torneos t2 INNER JOIN grupos g2 ON t2.id = g2.torneo_id
INNER JOIN fechas ON fechas.grupo_id = g2.id
INNER JOIN partidos ON partidos.fecha_id = fechas.id
INNER JOIN alineacions ON alineacions.partido_id = partidos.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id
WHERE alineacions.tipo = 'Titular' AND alineacions.jugador_id = ".$tarjeta->id. " GROUP BY alineacions.jugador_id";

            //echo $sql3;

            $jugados = DB::select(DB::raw($sql3));


            foreach ($jugados as $jugado){

                $tarjeta->jugados += $jugado->jugados;
            }

            $sql4="SELECT cambios.jugador_id, COUNT(cambios.jugador_id)  as jugados
FROM torneos t2 INNER JOIN grupos g2 ON t2.id = g2.torneo_id
INNER JOIN fechas ON fechas.grupo_id = g2.id
INNER JOIN partidos ON partidos.fecha_id = fechas.id
INNER JOIN cambios ON cambios.partido_id = partidos.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id
WHERE cambios.tipo = 'Entra' AND cambios.jugador_id = ".$tarjeta->id. " GROUP BY cambios.jugador_id";



            $jugados = DB::select(DB::raw($sql4));


            foreach ($jugados as $jugado){

                $tarjeta->jugados += $jugado->jugados;
            }
        }

        $tarjetas->setPath(route('torneos.tarjetas',array('order'=>$order,'tipoOrder'=>$tipoOrder,'actuales'=>$actuales,'torneoId'=>$torneoId)));

        //dd($tarjetas);

        $i=$offSet+1;
        return view('torneos.tarjetas', compact('tarjetas','i','order','tipoOrder','actuales','torneoId'));
    }

    public function posiciones(Request $request)
    {



                $sql='SELECT foto, equipo,
       count(*) jugados,
       count(case when golesl > golesv then 1 end) ganados,
       count(case when golesv > golesl then 1 end) perdidos,
       count(case when golesl = golesv then 1 end) empatados,
       sum(golesl) golesl,
       sum(golesv) golesv,
       sum(golesl) - sum(golesv) diferencia,
       sum(
             case when golesl > golesv then 3 else 0 end
           + case when golesl = golesv then 1 else 0 end
       ) puntaje, (sum(
             case when golesl > golesv then 3 else 0 end
           + case when golesl = golesv then 1 else 0 end
       )/count(*)) promedio, equipo_id
from (
       select  DISTINCT equipos.nombre equipo, golesl, golesv, equipos.escudo foto, fechas.id fecha_id, equipos.id equipo_id
		 from partidos
		 INNER JOIN equipos ON partidos.equipol_id = equipos.id
		 INNER JOIN plantillas ON plantillas.equipo_id = equipos.id
		 INNER JOIN fechas ON partidos.fecha_id = fechas.id
		 INNER JOIN grupos ON fechas.grupo_id = grupos.id
		 WHERE golesl is not null AND golesv is not null
     union all
       select DISTINCT equipos.nombre equipo, golesv, golesl, equipos.escudo foto, fechas.id fecha_id, equipos.id equipo_id
		 from partidos
		 INNER JOIN equipos ON partidos.equipov_id = equipos.id
		 INNER JOIN plantillas ON plantillas.equipo_id = equipos.id
		 INNER JOIN fechas ON partidos.fecha_id = fechas.id
		 INNER JOIN grupos ON fechas.grupo_id = grupos.id
		 WHERE golesl is not null AND golesv is not null
) a
group by equipo, foto, equipo_id

order by  puntaje desc, promedio DESC, diferencia DESC, golesl DESC, equipo ASC';

                $posiciones = DB::select(DB::raw($sql));

        $page = $request->query('page', 1);

        $paginate = 15;

        $offSet = ($page * $paginate) - $paginate;

        $itemsForCurrentPage = array_slice($posiciones, $offSet, $paginate, true);



        $posiciones = new \Illuminate\Pagination\LengthAwarePaginator($itemsForCurrentPage, count($posiciones), $paginate, $page);


        $posiciones->setPath(route('torneos.posiciones'));





        return view('torneos.posiciones', compact('posiciones'));
    }

    public function estadisticasTorneo(Request $request)
    {
        $torneo_id= $request->query('torneoId');
        //$torneo=Torneo::findOrFail($torneo_id);



        $estadisticas=array();


        $sql='SELECT torneos.nombre AS nombreTorneo, torneos.year, sum(partidos.golesl+
partidos.golesv) as total, COUNT(*) partidos, (SUM(partidos.golesl+partidos.golesv)/COUNT(*)) promedio, sum(partidos.golesl) as local, COUNT(*) partidos, (SUM(partidos.golesl)/COUNT(*)) promediolocal, sum(partidos.golesv) as visitante, COUNT(*) partidos, (SUM(partidos.golesv)/COUNT(*)) promediovisitante
FROM partidos
INNER JOIN equipos e1 ON partidos.equipol_id = e1.id
INNER JOIN equipos e2 ON partidos.equipov_id = e2.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON fechas.grupo_id = grupos.id
INNER JOIN torneos ON grupos.torneo_id = torneos.id
WHERE torneos.id='.$torneo_id.'
GROUP BY torneos.nombre, torneos.year';


        //echo $sql;
        $goles = DB::select(DB::raw($sql));


        $sql='SELECT torneos.nombre AS nombreTorneo, torneos.year, fechas.numero, partidos.dia, e1.id AS equipol_id, e1.escudo AS fotoLocal, e1.nombre AS local, e2.id AS equipov_id,e2.escudo AS fotoVisitante, e2.nombre AS visitante, partidos.golesl,
partidos.golesv, partidos.penalesl, partidos.penalesv, partidos.id partido_id
FROM partidos
INNER JOIN equipos e1 ON partidos.equipol_id = e1.id
INNER JOIN equipos e2 ON partidos.equipov_id = e2.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON fechas.grupo_id = grupos.id
INNER JOIN torneos ON grupos.torneo_id = torneos.id
WHERE torneos.id='.$torneo_id.' and partidos.golesl+partidos.golesv = (
select MAX(p1.golesl+p1.golesv)
from partidos p1
INNER JOIN fechas f1 ON p1.fecha_id = f1.id
INNER JOIN grupos g1 ON f1.grupo_id = g1.id
INNER JOIN torneos t1 ON g1.torneo_id = t1.id
WHERE t1.id='.$torneo_id.'


)

ORDER BY partidos.dia ASC';


        //echo $sql;
        $maxGoles = DB::select(DB::raw($sql));

        $sql='SELECT torneos.nombre AS nombreTorneo, torneos.year, fechas.numero, partidos.dia, e1.id AS equipol_id, e1.escudo AS fotoLocal, e1.nombre AS local, e2.id AS equipov_id,e2.escudo AS fotoVisitante, e2.nombre AS visitante, partidos.golesl,
partidos.golesv, partidos.penalesl, partidos.penalesv, partidos.id partido_id
FROM partidos
INNER JOIN equipos e1 ON partidos.equipol_id = e1.id
INNER JOIN equipos e2 ON partidos.equipov_id = e2.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON fechas.grupo_id = grupos.id
INNER JOIN torneos ON grupos.torneo_id = torneos.id
WHERE torneos.id='.$torneo_id.' and partidos.golesl+partidos.golesv = (
select MIN(p1.golesl+p1.golesv)
from partidos p1
INNER JOIN fechas f1 ON p1.fecha_id = f1.id
INNER JOIN grupos g1 ON f1.grupo_id = g1.id
INNER JOIN torneos t1 ON g1.torneo_id = t1.id
WHERE t1.id='.$torneo_id.'


)

ORDER BY partidos.dia ASC';


        //echo $sql;
        $minGoles = DB::select(DB::raw($sql));

        $sql='SELECT torneos.nombre AS nombreTorneo, torneos.year, fechas.numero, partidos.dia, e1.id AS equipol_id, e1.escudo AS fotoLocal, e1.nombre AS local, e2.id AS equipov_id,e2.escudo AS fotoVisitante, e2.nombre AS visitante, partidos.golesl,
partidos.golesv, partidos.penalesl, partidos.penalesv, partidos.id partido_id
FROM partidos
INNER JOIN equipos e1 ON partidos.equipol_id = e1.id
INNER JOIN equipos e2 ON partidos.equipov_id = e2.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON fechas.grupo_id = grupos.id
INNER JOIN torneos ON grupos.torneo_id = torneos.id
WHERE torneos.id='.$torneo_id.' and partidos.golesl = (
select MAX(p1.golesl)
from partidos p1
INNER JOIN fechas f1 ON p1.fecha_id = f1.id
INNER JOIN grupos g1 ON f1.grupo_id = g1.id
INNER JOIN torneos t1 ON g1.torneo_id = t1.id
WHERE t1.id='.$torneo_id.'


)

ORDER BY partidos.dia ASC';


        //echo $sql;
        $maxGolesLocales = DB::select(DB::raw($sql));

        $sql='SELECT torneos.nombre AS nombreTorneo, torneos.year, fechas.numero, partidos.dia, e1.id AS equipol_id, e1.escudo AS fotoLocal, e1.nombre AS local, e2.id AS equipov_id,e2.escudo AS fotoVisitante, e2.nombre AS visitante, partidos.golesl,
partidos.golesv, partidos.penalesl, partidos.penalesv, partidos.id partido_id
FROM partidos
INNER JOIN equipos e1 ON partidos.equipol_id = e1.id
INNER JOIN equipos e2 ON partidos.equipov_id = e2.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON fechas.grupo_id = grupos.id
INNER JOIN torneos ON grupos.torneo_id = torneos.id
WHERE torneos.id='.$torneo_id.' and partidos.golesl = (
select MIN(p1.golesl)
from partidos p1
INNER JOIN fechas f1 ON p1.fecha_id = f1.id
INNER JOIN grupos g1 ON f1.grupo_id = g1.id
INNER JOIN torneos t1 ON g1.torneo_id = t1.id
WHERE t1.id='.$torneo_id.'


)

ORDER BY partidos.dia ASC';


        //echo $sql;
        $minGolesLocales = DB::select(DB::raw($sql));

        $sql='SELECT torneos.nombre AS nombreTorneo, torneos.year, fechas.numero, partidos.dia, e1.id AS equipol_id, e1.escudo AS fotoLocal, e1.nombre AS local, e2.id AS equipov_id,e2.escudo AS fotoVisitante, e2.nombre AS visitante, partidos.golesl,
partidos.golesv, partidos.penalesl, partidos.penalesv, partidos.id partido_id
FROM partidos
INNER JOIN equipos e1 ON partidos.equipol_id = e1.id
INNER JOIN equipos e2 ON partidos.equipov_id = e2.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON fechas.grupo_id = grupos.id
INNER JOIN torneos ON grupos.torneo_id = torneos.id
WHERE torneos.id='.$torneo_id.' and partidos.golesv = (
select MAX(p1.golesv)
from partidos p1
INNER JOIN fechas f1 ON p1.fecha_id = f1.id
INNER JOIN grupos g1 ON f1.grupo_id = g1.id
INNER JOIN torneos t1 ON g1.torneo_id = t1.id
WHERE t1.id='.$torneo_id.'


)

ORDER BY partidos.dia ASC';


        //echo $sql;
        $maxGolesVisitantes = DB::select(DB::raw($sql));

        $sql='SELECT torneos.nombre AS nombreTorneo, torneos.year, fechas.numero, partidos.dia, e1.id AS equipol_id, e1.escudo AS fotoLocal, e1.nombre AS local, e2.id AS equipov_id,e2.escudo AS fotoVisitante, e2.nombre AS visitante, partidos.golesl,
partidos.golesv, partidos.penalesl, partidos.penalesv, partidos.id partido_id
FROM partidos
INNER JOIN equipos e1 ON partidos.equipol_id = e1.id
INNER JOIN equipos e2 ON partidos.equipov_id = e2.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON fechas.grupo_id = grupos.id
INNER JOIN torneos ON grupos.torneo_id = torneos.id
WHERE torneos.id='.$torneo_id.' and partidos.golesv = (
select MIN(p1.golesv)
from partidos p1
INNER JOIN fechas f1 ON p1.fecha_id = f1.id
INNER JOIN grupos g1 ON f1.grupo_id = g1.id
INNER JOIN torneos t1 ON g1.torneo_id = t1.id
WHERE t1.id='.$torneo_id.'


)

ORDER BY partidos.dia ASC';


        //echo $sql;
        $minGolesVisitantes = DB::select(DB::raw($sql));


        $sql='SELECT t.nombreTorneo, t.year, t.numero,t.partidos,t.goles, t.promedio
FROM

(SELECT torneos.nombre AS nombreTorneo, torneos.year, fechas.numero, SUM(partidos.golesl+partidos.golesv) goles, COUNT(*) partidos, (SUM(partidos.golesl+partidos.golesv)/COUNT(*)) promedio
FROM partidos
INNER JOIN equipos e1 ON partidos.equipol_id = e1.id
INNER JOIN equipos e2 ON partidos.equipov_id = e2.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON fechas.grupo_id = grupos.id
INNER JOIN torneos ON grupos.torneo_id = torneos.id
WHERE torneos.id='.$torneo_id.'

GROUP BY torneos.nombre, torneos.year, fechas.numero) AS t
WHERE t.goles = (SELECT max(t.goles)
FROM

(SELECT torneos.nombre AS nombreTorneo, torneos.year, fechas.numero, SUM(partidos.golesl+partidos.golesv) goles, COUNT(*) partidos, (SUM(partidos.golesl+partidos.golesv)/COUNT(*)) promedio
FROM partidos
INNER JOIN equipos e1 ON partidos.equipol_id = e1.id
INNER JOIN equipos e2 ON partidos.equipov_id = e2.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON fechas.grupo_id = grupos.id
INNER JOIN torneos ON grupos.torneo_id = torneos.id
WHERE torneos.id='.$torneo_id.'

GROUP BY torneos.nombre, torneos.year, fechas.numero) AS t )';


        //echo $sql;
        $fechaMasGoles = DB::select(DB::raw($sql));

        $sql='SELECT t.nombreTorneo, t.year, t.numero,t.partidos,t.goles, t.promedio
FROM

(SELECT torneos.nombre AS nombreTorneo, torneos.year, fechas.numero, SUM(partidos.golesl+partidos.golesv) goles, COUNT(*) partidos, (SUM(partidos.golesl+partidos.golesv)/COUNT(*)) promedio
FROM partidos
INNER JOIN equipos e1 ON partidos.equipol_id = e1.id
INNER JOIN equipos e2 ON partidos.equipov_id = e2.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON fechas.grupo_id = grupos.id
INNER JOIN torneos ON grupos.torneo_id = torneos.id
WHERE torneos.id='.$torneo_id.'

GROUP BY torneos.nombre, torneos.year, fechas.numero) AS t
WHERE t.goles = (SELECT min(t.goles)
FROM

(SELECT torneos.nombre AS nombreTorneo, torneos.year, fechas.numero, SUM(partidos.golesl+partidos.golesv) goles, COUNT(*) partidos, (SUM(partidos.golesl+partidos.golesv)/COUNT(*)) promedio
FROM partidos
INNER JOIN equipos e1 ON partidos.equipol_id = e1.id
INNER JOIN equipos e2 ON partidos.equipov_id = e2.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON fechas.grupo_id = grupos.id
INNER JOIN torneos ON grupos.torneo_id = torneos.id
WHERE torneos.id='.$torneo_id.'

GROUP BY torneos.nombre, torneos.year, fechas.numero) AS t )';


        //echo $sql;
        $fechaMinGoles = DB::select(DB::raw($sql));

        $sql='SELECT t.nombreTorneo, t.year, t.numero,t.partidos,t.goles, t.promedio
FROM

(SELECT torneos.nombre AS nombreTorneo, torneos.year, fechas.numero, SUM(partidos.golesl) goles, COUNT(*) partidos, (SUM(partidos.golesl)/COUNT(*)) promedio
FROM partidos
INNER JOIN equipos e1 ON partidos.equipol_id = e1.id
INNER JOIN equipos e2 ON partidos.equipov_id = e2.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON fechas.grupo_id = grupos.id
INNER JOIN torneos ON grupos.torneo_id = torneos.id
WHERE torneos.id='.$torneo_id.'

GROUP BY torneos.nombre, torneos.year, fechas.numero) AS t
WHERE t.goles = (SELECT max(t.goles)
FROM

(SELECT torneos.nombre AS nombreTorneo, torneos.year, fechas.numero, SUM(partidos.golesl) goles, COUNT(*) partidos, (SUM(partidos.golesl)/COUNT(*)) promedio
FROM partidos
INNER JOIN equipos e1 ON partidos.equipol_id = e1.id
INNER JOIN equipos e2 ON partidos.equipov_id = e2.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON fechas.grupo_id = grupos.id
INNER JOIN torneos ON grupos.torneo_id = torneos.id
WHERE torneos.id='.$torneo_id.'

GROUP BY torneos.nombre, torneos.year, fechas.numero) AS t )';


        //echo $sql;
        $fechaMasGolesLocales = DB::select(DB::raw($sql));

        $sql='SELECT t.nombreTorneo, t.year, t.numero,t.partidos,t.goles, t.promedio
FROM

(SELECT torneos.nombre AS nombreTorneo, torneos.year, fechas.numero, SUM(partidos.golesl) goles, COUNT(*) partidos, (SUM(partidos.golesl)/COUNT(*)) promedio
FROM partidos
INNER JOIN equipos e1 ON partidos.equipol_id = e1.id
INNER JOIN equipos e2 ON partidos.equipov_id = e2.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON fechas.grupo_id = grupos.id
INNER JOIN torneos ON grupos.torneo_id = torneos.id
WHERE torneos.id='.$torneo_id.'

GROUP BY torneos.nombre, torneos.year, fechas.numero) AS t
WHERE t.goles = (SELECT min(t.goles)
FROM

(SELECT torneos.nombre AS nombreTorneo, torneos.year, fechas.numero, SUM(partidos.golesl) goles, COUNT(*) partidos, (SUM(partidos.golesl)/COUNT(*)) promedio
FROM partidos
INNER JOIN equipos e1 ON partidos.equipol_id = e1.id
INNER JOIN equipos e2 ON partidos.equipov_id = e2.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON fechas.grupo_id = grupos.id
INNER JOIN torneos ON grupos.torneo_id = torneos.id
WHERE torneos.id='.$torneo_id.'

GROUP BY torneos.nombre, torneos.year, fechas.numero) AS t )';


        //echo $sql;
        $fechaMinGolesLocales = DB::select(DB::raw($sql));


        $sql='SELECT t.nombreTorneo, t.year, t.numero,t.partidos,t.goles, t.promedio
FROM

(SELECT torneos.nombre AS nombreTorneo, torneos.year, fechas.numero, SUM(partidos.golesv) goles, COUNT(*) partidos, (SUM(partidos.golesv)/COUNT(*)) promedio
FROM partidos
INNER JOIN equipos e1 ON partidos.equipol_id = e1.id
INNER JOIN equipos e2 ON partidos.equipov_id = e2.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON fechas.grupo_id = grupos.id
INNER JOIN torneos ON grupos.torneo_id = torneos.id
WHERE torneos.id='.$torneo_id.'

GROUP BY torneos.nombre, torneos.year, fechas.numero) AS t
WHERE t.goles = (SELECT max(t.goles)
FROM

(SELECT torneos.nombre AS nombreTorneo, torneos.year, fechas.numero, SUM(partidos.golesv) goles, COUNT(*) partidos, (SUM(partidos.golesv)/COUNT(*)) promedio
FROM partidos
INNER JOIN equipos e1 ON partidos.equipol_id = e1.id
INNER JOIN equipos e2 ON partidos.equipov_id = e2.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON fechas.grupo_id = grupos.id
INNER JOIN torneos ON grupos.torneo_id = torneos.id
WHERE torneos.id='.$torneo_id.'

GROUP BY torneos.nombre, torneos.year, fechas.numero) AS t )';


        //echo $sql;
        $fechaMasGolesVisitantes = DB::select(DB::raw($sql));

        $sql='SELECT t.nombreTorneo, t.year, t.numero,t.partidos,t.goles, t.promedio
FROM

(SELECT torneos.nombre AS nombreTorneo, torneos.year, fechas.numero, SUM(partidos.golesv) goles, COUNT(*) partidos, (SUM(partidos.golesv)/COUNT(*)) promedio
FROM partidos
INNER JOIN equipos e1 ON partidos.equipol_id = e1.id
INNER JOIN equipos e2 ON partidos.equipov_id = e2.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON fechas.grupo_id = grupos.id
INNER JOIN torneos ON grupos.torneo_id = torneos.id
WHERE torneos.id='.$torneo_id.'

GROUP BY torneos.nombre, torneos.year, fechas.numero) AS t
WHERE t.goles = (SELECT min(t.goles)
FROM

(SELECT torneos.nombre AS nombreTorneo, torneos.year, fechas.numero, SUM(partidos.golesv) goles, COUNT(*) partidos, (SUM(partidos.golesv)/COUNT(*)) promedio
FROM partidos
INNER JOIN equipos e1 ON partidos.equipol_id = e1.id
INNER JOIN equipos e2 ON partidos.equipov_id = e2.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON fechas.grupo_id = grupos.id
INNER JOIN torneos ON grupos.torneo_id = torneos.id
WHERE torneos.id='.$torneo_id.'

GROUP BY torneos.nombre, torneos.year, fechas.numero) AS t )';


        //echo $sql;
        $fechaMinGolesVisitantes = DB::select(DB::raw($sql));


        $estadisticas['goles']=$goles;
        $estadisticas['maxGoles']=$maxGoles;
        $estadisticas['minGoles']=$minGoles;
        $estadisticas['maxGolesLocales']=$maxGolesLocales;
        $estadisticas['minGolesLocales']=$minGolesLocales;
        $estadisticas['maxGolesVisitantes']=$maxGolesVisitantes;
        $estadisticas['minGolesVisitantes']=$minGolesVisitantes;
        $estadisticas['fechaMasGoles']=$fechaMasGoles;
        $estadisticas['fechaMinGoles']=$fechaMinGoles;
        $estadisticas['fechaMasGolesLocales']=$fechaMasGolesLocales;
        $estadisticas['fechaMinGolesLocales']=$fechaMinGolesLocales;
        $estadisticas['fechaMasGolesVisitantes']=$fechaMasGolesVisitantes;
        $estadisticas['fechaMinGolesVisitantes']=$fechaMinGolesVisitantes;


        return view('torneos.estadisticasTorneo', compact('estadisticas'));
    }


    public function estadisticasOtras(Request $request)
    {
        //$torneo= $request->query('torneo');



        $estadisticas=array();



            $sql='SELECT torneos.nombre AS nombreTorneo, torneos.year, fechas.numero, partidos.dia, e1.id AS equipol_id, e1.escudo AS fotoLocal, e1.nombre AS local, e2.id AS equipov_id,e2.escudo AS fotoVisitante, e2.nombre AS visitante, partidos.golesl,
partidos.golesv, partidos.penalesl, partidos.penalesv, partidos.id partido_id
FROM partidos
INNER JOIN equipos e1 ON partidos.equipol_id = e1.id
INNER JOIN equipos e2 ON partidos.equipov_id = e2.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON fechas.grupo_id = grupos.id
INNER JOIN torneos ON grupos.torneo_id = torneos.id
WHERE partidos.golesl+partidos.golesv = (
select MAX(p1.golesl+p1.golesv)
from partidos p1)

ORDER BY partidos.dia ASC';


            //echo $sql;
            $maxGoles = DB::select(DB::raw($sql));

        $sql='SELECT torneos.nombre AS nombreTorneo, torneos.year, fechas.numero, partidos.dia, e1.id AS equipol_id, e1.escudo AS fotoLocal, e1.nombre AS local, e2.id AS equipov_id,e2.escudo AS fotoVisitante, e2.nombre AS visitante, partidos.golesl,
partidos.golesv, partidos.penalesl, partidos.penalesv, partidos.id partido_id
FROM partidos
INNER JOIN equipos e1 ON partidos.equipol_id = e1.id
INNER JOIN equipos e2 ON partidos.equipov_id = e2.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON fechas.grupo_id = grupos.id
INNER JOIN torneos ON grupos.torneo_id = torneos.id
WHERE partidos.golesl = (
select MAX(p1.golesl)
from partidos p1)

ORDER BY partidos.dia ASC';


        //echo $sql;
        $maxGolesLocales = DB::select(DB::raw($sql));

        $sql='SELECT torneos.nombre AS nombreTorneo, torneos.year, fechas.numero, partidos.dia, e1.id AS equipol_id, e1.escudo AS fotoLocal, e1.nombre AS local, e2.id AS equipov_id,e2.escudo AS fotoVisitante, e2.nombre AS visitante, partidos.golesl,
partidos.golesv, partidos.penalesl, partidos.penalesv, partidos.id partido_id
FROM partidos
INNER JOIN equipos e1 ON partidos.equipol_id = e1.id
INNER JOIN equipos e2 ON partidos.equipov_id = e2.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON fechas.grupo_id = grupos.id
INNER JOIN torneos ON grupos.torneo_id = torneos.id
WHERE partidos.golesv = (
select MAX(p1.golesv)
from partidos p1)

ORDER BY partidos.dia ASC';


        //echo $sql;
        $maxGolesVisitantes = DB::select(DB::raw($sql));


        $sql='SELECT t.nombreTorneo, t.year, t.numero,t.partidos,t.goles, t.promedio
FROM

(SELECT torneos.nombre AS nombreTorneo, torneos.year, fechas.numero, SUM(partidos.golesl+partidos.golesv) goles, COUNT(*) partidos, (SUM(partidos.golesl+partidos.golesv)/COUNT(*)) promedio
FROM partidos
INNER JOIN equipos e1 ON partidos.equipol_id = e1.id
INNER JOIN equipos e2 ON partidos.equipov_id = e2.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON fechas.grupo_id = grupos.id
INNER JOIN torneos ON grupos.torneo_id = torneos.id


GROUP BY torneos.nombre, torneos.year, fechas.numero) AS t
WHERE t.goles = (SELECT max(t.goles)
FROM

(SELECT torneos.nombre AS nombreTorneo, torneos.year, fechas.numero, SUM(partidos.golesl+partidos.golesv) goles, COUNT(*) partidos, (SUM(partidos.golesl+partidos.golesv)/COUNT(*)) promedio
FROM partidos
INNER JOIN equipos e1 ON partidos.equipol_id = e1.id
INNER JOIN equipos e2 ON partidos.equipov_id = e2.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON fechas.grupo_id = grupos.id
INNER JOIN torneos ON grupos.torneo_id = torneos.id


GROUP BY torneos.nombre, torneos.year, fechas.numero) AS t )';


        //echo $sql;
        $fechaMasGoles = DB::select(DB::raw($sql));

        $sql='SELECT t.nombreTorneo, t.year, t.numero,t.partidos,t.goles, t.promedio
FROM

(SELECT torneos.nombre AS nombreTorneo, torneos.year, fechas.numero, SUM(partidos.golesl) goles, COUNT(*) partidos, (SUM(partidos.golesl)/COUNT(*)) promedio
FROM partidos
INNER JOIN equipos e1 ON partidos.equipol_id = e1.id
INNER JOIN equipos e2 ON partidos.equipov_id = e2.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON fechas.grupo_id = grupos.id
INNER JOIN torneos ON grupos.torneo_id = torneos.id


GROUP BY torneos.nombre, torneos.year, fechas.numero) AS t
WHERE t.goles = (SELECT max(t.goles)
FROM

(SELECT torneos.nombre AS nombreTorneo, torneos.year, fechas.numero, SUM(partidos.golesl) goles, COUNT(*) partidos, (SUM(partidos.golesl)/COUNT(*)) promedio
FROM partidos
INNER JOIN equipos e1 ON partidos.equipol_id = e1.id
INNER JOIN equipos e2 ON partidos.equipov_id = e2.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON fechas.grupo_id = grupos.id
INNER JOIN torneos ON grupos.torneo_id = torneos.id


GROUP BY torneos.nombre, torneos.year, fechas.numero) AS t )';


        //echo $sql;
        $fechaMasGolesLocales = DB::select(DB::raw($sql));


        $sql='SELECT t.nombreTorneo, t.year, t.numero,t.partidos,t.goles, t.promedio
FROM

(SELECT torneos.nombre AS nombreTorneo, torneos.year, fechas.numero, SUM(partidos.golesv) goles, COUNT(*) partidos, (SUM(partidos.golesv)/COUNT(*)) promedio
FROM partidos
INNER JOIN equipos e1 ON partidos.equipol_id = e1.id
INNER JOIN equipos e2 ON partidos.equipov_id = e2.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON fechas.grupo_id = grupos.id
INNER JOIN torneos ON grupos.torneo_id = torneos.id


GROUP BY torneos.nombre, torneos.year, fechas.numero) AS t
WHERE t.goles = (SELECT max(t.goles)
FROM

(SELECT torneos.nombre AS nombreTorneo, torneos.year, fechas.numero, SUM(partidos.golesv) goles, COUNT(*) partidos, (SUM(partidos.golesv)/COUNT(*)) promedio
FROM partidos
INNER JOIN equipos e1 ON partidos.equipol_id = e1.id
INNER JOIN equipos e2 ON partidos.equipov_id = e2.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON fechas.grupo_id = grupos.id
INNER JOIN torneos ON grupos.torneo_id = torneos.id


GROUP BY torneos.nombre, torneos.year, fechas.numero) AS t )';


        //echo $sql;
        $fechaMasGolesVisitantes = DB::select(DB::raw($sql));


        $sql='SELECT t.nombreTorneo, t.year,t.partidos,t.goles, t.promedio
FROM

(SELECT torneos.nombre AS nombreTorneo, torneos.year, SUM(partidos.golesl+partidos.golesv) goles, COUNT(*) partidos, (SUM(partidos.golesl+partidos.golesv)/COUNT(*)) promedio
FROM partidos
INNER JOIN equipos e1 ON partidos.equipol_id = e1.id
INNER JOIN equipos e2 ON partidos.equipov_id = e2.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON fechas.grupo_id = grupos.id
INNER JOIN torneos ON grupos.torneo_id = torneos.id


GROUP BY torneos.nombre, torneos.year) AS t
WHERE t.goles = (SELECT max(t.goles)
FROM

(SELECT torneos.nombre AS nombreTorneo, torneos.year, SUM(partidos.golesl+partidos.golesv) goles, COUNT(*) partidos, (SUM(partidos.golesl+partidos.golesv)/COUNT(*)) promedio
FROM partidos
INNER JOIN equipos e1 ON partidos.equipol_id = e1.id
INNER JOIN equipos e2 ON partidos.equipov_id = e2.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON fechas.grupo_id = grupos.id
INNER JOIN torneos ON grupos.torneo_id = torneos.id


GROUP BY torneos.nombre, torneos.year) AS t )';


        //echo $sql;
        $torneoMasGoles = DB::select(DB::raw($sql));

        $sql='SELECT t.nombreTorneo, t.year,t.partidos,t.goles, t.promedio
FROM

(SELECT torneos.nombre AS nombreTorneo, torneos.year, SUM(partidos.golesl) goles, COUNT(*) partidos, (SUM(partidos.golesl)/COUNT(*)) promedio
FROM partidos
INNER JOIN equipos e1 ON partidos.equipol_id = e1.id
INNER JOIN equipos e2 ON partidos.equipov_id = e2.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON fechas.grupo_id = grupos.id
INNER JOIN torneos ON grupos.torneo_id = torneos.id


GROUP BY torneos.nombre, torneos.year) AS t
WHERE t.goles = (SELECT max(t.goles)
FROM

(SELECT torneos.nombre AS nombreTorneo, torneos.year, SUM(partidos.golesl) goles, COUNT(*) partidos, (SUM(partidos.golesl)/COUNT(*)) promedio
FROM partidos
INNER JOIN equipos e1 ON partidos.equipol_id = e1.id
INNER JOIN equipos e2 ON partidos.equipov_id = e2.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON fechas.grupo_id = grupos.id
INNER JOIN torneos ON grupos.torneo_id = torneos.id


GROUP BY torneos.nombre, torneos.year) AS t )';


        //echo $sql;
        $torneoMasGolesLocales = DB::select(DB::raw($sql));

        $sql='SELECT t.nombreTorneo, t.year,t.partidos,t.goles, t.promedio
FROM

(SELECT torneos.nombre AS nombreTorneo, torneos.year, SUM(partidos.golesv) goles, COUNT(*) partidos, (SUM(partidos.golesv)/COUNT(*)) promedio
FROM partidos
INNER JOIN equipos e1 ON partidos.equipol_id = e1.id
INNER JOIN equipos e2 ON partidos.equipov_id = e2.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON fechas.grupo_id = grupos.id
INNER JOIN torneos ON grupos.torneo_id = torneos.id


GROUP BY torneos.nombre, torneos.year) AS t
WHERE t.goles = (SELECT max(t.goles)
FROM

(SELECT torneos.nombre AS nombreTorneo, torneos.year, SUM(partidos.golesv) goles, COUNT(*) partidos, (SUM(partidos.golesv)/COUNT(*)) promedio
FROM partidos
INNER JOIN equipos e1 ON partidos.equipol_id = e1.id
INNER JOIN equipos e2 ON partidos.equipov_id = e2.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON fechas.grupo_id = grupos.id
INNER JOIN torneos ON grupos.torneo_id = torneos.id


GROUP BY torneos.nombre, torneos.year) AS t )';


        //echo $sql;
        $torneoMasGolesVisitantes = DB::select(DB::raw($sql));

        $estadisticas['torneoMasGoles']=$torneoMasGoles;
        $estadisticas['torneoMasGolesLocales']=$torneoMasGolesLocales;
        $estadisticas['torneoMasGolesVisitantes']=$torneoMasGolesVisitantes;
        $estadisticas['maxGoles']=$maxGoles;
        $estadisticas['maxGolesLocales']=$maxGolesLocales;
        $estadisticas['maxGolesVisitantes']=$maxGolesVisitantes;
        $estadisticas['fechaMasGoles']=$fechaMasGoles;
        $estadisticas['fechaMasGolesLocales']=$fechaMasGolesLocales;
        $estadisticas['fechaMasGolesVisitantes']=$fechaMasGolesVisitantes;


        return view('torneos.estadisticasOtras', compact('estadisticas'));
    }


    public function tecnicos(Request $request)
    {

        $order= ($request->query('order'))?$request->query('order'):'puntaje';
        $tipoOrder= ($request->query('tipoOrder'))?$request->query('tipoOrder'):'DESC';
        $actuales= ($request->query('actuales'))?1:0;
        if($request->query('torneoId')) {
            $torneoId = $request->query('torneoId');

        }
        else{
            $torneo=Torneo::orderBy('year','DESC')->first();
            $torneoId = $torneo->id;
        }

        $torneos=Torneo::orderBy('year','DESC')->get();

        $sql = 'SELECT tecnico, fotoTecnico, tecnico_id,
       count(*) jugados,
       count(case when golesl > golesv then 1 end) ganados,
       count(case when golesv > golesl then 1 end) perdidos,
       count(case when golesl = golesv then 1 end) empatados,
       sum(golesl) golesl,
       sum(golesv) golesv,
       sum(golesl) - sum(golesv) diferencia,
       sum(
             case when golesl > golesv then 3 else 0 end
           + case when golesl = golesv then 1 else 0 end
       ) puntaje, CONCAT(
    ROUND(
      (  sum(
             case when golesl > golesv then 3 else 0 end
           + case when golesl = golesv then 1 else 0 end
       ) * 100/(COUNT(*)*3) ),
      2
    ), \'%\') porcentaje,
    ROUND(
      (  sum(
             case when golesl > golesv then 3 else 0 end
           + case when golesl = golesv then 1 else 0 end
       ) /COUNT(*) ),
      2
    ) prom, "" escudo, "" AS jugando
from (
       select  DISTINCT CONCAT (personas.apellido,\', \', personas.nombre) tecnico, personas.foto fotoTecnico, tecnicos.id tecnico_id, golesl, golesv, equipos.escudo foto, fechas.id fecha_id
		 from partidos
		 INNER JOIN equipos ON partidos.equipol_id = equipos.id
		 INNER JOIN plantillas ON plantillas.equipo_id = equipos.id
		 INNER JOIN fechas ON partidos.fecha_id = fechas.id
		 INNER JOIN grupos ON fechas.grupo_id = grupos.id
		 INNER JOIN partido_tecnicos ON partidos.id = partido_tecnicos.partido_id AND equipos.id = partido_tecnicos.equipo_id
		 INNER JOIN tecnicos ON tecnicos.id = partido_tecnicos.tecnico_id
		 INNER JOIN personas ON personas.id = tecnicos.persona_id
		 WHERE golesl is not null AND golesv is not null';
        $sql .=($actuales)?' AND EXISTS (
        SELECT PT1.id
FROM partido_tecnicos PT1
INNER JOIN tecnicos T1 ON PT1.tecnico_id = T1.id

INNER JOIN partidos ON PT1.partido_id = partidos.id
INNER JOIN fechas F1 ON partidos.fecha_id = F1.id
INNER JOIN grupos G1 ON G1.id = F1.grupo_id

WHERE G1.torneo_id = '.$torneoId.' AND T1.id = tecnicos.id

        )':'';

     $sql .=' union all
       select DISTINCT CONCAT (personas.apellido,\', \', personas.nombre) tecnico, personas.foto fotoTecnico, tecnicos.id tecnico_id, golesv, golesl, equipos.escudo foto, fechas.id fecha_id
		 from partidos
		 INNER JOIN equipos ON partidos.equipov_id = equipos.id
		 INNER JOIN plantillas ON plantillas.equipo_id = equipos.id
		 INNER JOIN fechas ON partidos.fecha_id = fechas.id
		 INNER JOIN grupos ON fechas.grupo_id = grupos.id
		 INNER JOIN partido_tecnicos ON partidos.id = partido_tecnicos.partido_id AND equipos.id = partido_tecnicos.equipo_id
		 INNER JOIN tecnicos ON tecnicos.id = partido_tecnicos.tecnico_id
		 INNER JOIN personas ON personas.id = tecnicos.persona_id
		 WHERE golesl is not null AND golesv is not null';
        $sql .=($actuales)?' AND EXISTS (
        SELECT PT1.id
FROM partido_tecnicos PT1
INNER JOIN tecnicos T1 ON PT1.tecnico_id = T1.id

INNER JOIN partidos ON PT1.partido_id = partidos.id
INNER JOIN fechas F1 ON partidos.fecha_id = F1.id
INNER JOIN grupos G1 ON G1.id = F1.grupo_id

WHERE G1.torneo_id = '.$torneoId.' AND T1.id = tecnicos.id

        )':'';
$sql .=' ) a
group by tecnico, fotoTecnico, tecnico_id



        ORDER BY '.$order.' '.$tipoOrder.', jugados DESC, tecnico ASC';

//echo $sql;

        $goleadores = DB::select(DB::raw($sql));



        $page = $request->query('page', 1);

        $paginate = 15;

        $offSet = ($page * $paginate) - $paginate;

        $itemsForCurrentPage = array_slice($goleadores, $offSet, $paginate, true);



        $goleadores = new \Illuminate\Pagination\LengthAwarePaginator($itemsForCurrentPage, count($goleadores), $paginate, $page);


        foreach ($goleadores as $goleador){

            $sqlJugando = 'SELECT DISTINCT equipos.escudo, partido_tecnicos.equipo_id
FROM partido_tecnicos
INNER JOIN tecnicos ON partido_tecnicos.tecnico_id = tecnicos.id
INNER JOIN personas ON tecnicos.persona_id = personas.id
INNER JOIN partidos ON partido_tecnicos.partido_id = partidos.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id
INNER JOIN equipos ON partido_tecnicos.equipo_id = equipos.id
WHERE grupos.torneo_id = '.$torneoId.' AND tecnicos.id = '.$goleador->tecnico_id;
            $jugando = '';
            $juega = DB::select(DB::raw($sqlJugando));
            foreach ($juega as $e){

                $jugando .= $e->escudo.'_'.$e->equipo_id.',';
            }

            $goleador->jugando = $jugando;

            $sql2='SELECT equipo, escudo, equipo_id,
       count(*) jugados,
       count(case when golesl > golesv then 1 end) ganados,
       count(case when golesv > golesl then 1 end) perdidos,
       count(case when golesl = golesv then 1 end) empatados,
       sum(golesl) golesl,
       sum(golesv) golesv,
       sum(golesl) - sum(golesv) diferencia,
       sum(
             case when golesl > golesv then 3 else 0 end
           + case when golesl = golesv then 1 else 0 end
       ) puntaje, CONCAT(
    ROUND(
      (  sum(
             case when golesl > golesv then 3 else 0 end
           + case when golesl = golesv then 1 else 0 end
       ) * 100/(COUNT(*)*3) ),
      2
    ), \'%\') porcentaje
from (
       select   equipos.nombre equipo, equipos.id equipo_id, golesl, golesv, equipos.escudo
		 from partidos
		 INNER JOIN equipos ON partidos.equipol_id = equipos.id

		 INNER JOIN partido_tecnicos ON partidos.id = partido_tecnicos.partido_id AND equipos.id = partido_tecnicos.equipo_id
		 INNER JOIN tecnicos ON tecnicos.id = partido_tecnicos.tecnico_id
		 WHERE golesl is not null AND golesv is not NULL AND tecnicos.id = '.$goleador->tecnico_id.'
     union all
       select equipos.nombre equipo, equipos.id equipo_id, golesv, golesl, equipos.escudo
		 from partidos
		 INNER JOIN equipos ON partidos.equipov_id = equipos.id

		 INNER JOIN partido_tecnicos ON partidos.id = partido_tecnicos.partido_id AND equipos.id = partido_tecnicos.equipo_id
		 INNER JOIN tecnicos ON tecnicos.id = partido_tecnicos.tecnico_id
		 WHERE golesl is not null AND golesv is not NULL AND tecnicos.id = '.$goleador->tecnico_id.'
) a
group by equipo, escudo, equipo_id

order by puntaje desc, diferencia DESC, golesl DESC';



            $escudos = DB::select(DB::raw($sql2));


            foreach ($escudos as $escudo){

                $goleador->escudo .= $escudo->escudo.'_'.$escudo->equipo_id.'_'.$escudo->puntaje.'_'.$escudo->porcentaje.',';

            }

        }

        $goleadores->setPath(route('torneos.tecnicos',array('order'=>$order,'tipoOrder'=>$tipoOrder,'actuales'=>$actuales,'torneoId'=>$torneoId)));


        $i=$offSet+1;


        return view('torneos.tecnicos', compact('goleadores','i','order','tipoOrder','actuales','torneoId'));
    }

    public function arqueros(Request $request)
    {

        $order= ($request->query('order'))?$request->query('order'):'jugados';
        $tipoOrder= ($request->query('tipoOrder'))?$request->query('tipoOrder'):'DESC';
        $actuales= ($request->query('actuales'))?1:0;

        if($request->query('torneoId')) {
            $torneoId = $request->query('torneoId');

        }
        else{
            $torneo=Torneo::orderBy('year','DESC')->first();
            $torneoId = $torneo->id;
        }

        $torneos=Torneo::orderBy('year','DESC')->get();

        $sql = 'SELECT jugadors.id, CONCAT(personas.apellido,\', \',personas.nombre) jugador, COUNT(jugadors.id) as jugados,
sum(case when alineacions.equipo_id=partidos.equipol_id then partidos.golesv else partidos.golesl END) AS recibidos,
sum(case when alineacions.equipo_id=partidos.equipol_id and partidos.golesv = 0 then 1 else CASE when alineacions.equipo_id=partidos.equipov_id and partidos.golesl = 0 THEN 1 ELSE 0 END END) AS invictas, "" escudo, personas.foto, "" as jugando
FROM alineacions
INNER JOIN jugadors ON alineacions.jugador_id = jugadors.id AND jugadors.tipoJugador = \'Arquero\'
INNER JOIN personas ON jugadors.persona_id = personas.id
INNER JOIN partidos ON alineacions.partido_id = partidos.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id

WHERE  alineacions.tipo = \'Titular\'';
        $sql .=($actuales)?' AND EXISTS (
SELECT DISTINCT J1.id
FROM alineacions
INNER JOIN jugadors J1 ON alineacions.jugador_id = J1.id
INNER JOIN partidos P1 ON alineacions.partido_id = P1.id
INNER JOIN fechas F1 ON P1.fecha_id = F1.id
INNER JOIN grupos G1 ON G1.id = F1.grupo_id

WHERE G1.torneo_id = '.$torneoId.' AND J1.id = jugadors.id

)':'';

$sql .=' GROUP BY jugadors.id, jugador, foto
ORDER BY '.$order.' '.$tipoOrder.', jugados DESC, recibidos ASC';
        $arqueros = DB::select(DB::raw($sql));

        if($request->query('torneoId')) {
            $torneoId = $request->query('torneoId');

        }
        else{
            $torneo=Torneo::orderBy('year','DESC')->first();
            $torneoId = $torneo->id;
        }

        $torneos=Torneo::orderBy('year','DESC')->get();

        /*$sqlUltimoTorneo = 'SELECT MAX(id) as ultimo FROM torneos';

        $ultimoTorneos = DB::select(DB::raw($sqlUltimoTorneo));
        //$maxId = $ultimoTorneo['ultimo'];
        foreach ($ultimoTorneos as $ultimoTorneo){
            $maxId = $ultimoTorneo->ultimo;
        }*/


        $page = $request->query('page', 1);

        $paginate = 15;

        $offSet = ($page * $paginate) - $paginate;

        $itemsForCurrentPage = array_slice($arqueros, $offSet, $paginate, true);

        $arqueros = new \Illuminate\Pagination\LengthAwarePaginator($itemsForCurrentPage, count($arqueros), $paginate, $page);


        foreach ($arqueros as $arquero){

            $sqlJugando = 'SELECT DISTINCT equipos.escudo, alineacions.equipo_id
FROM alineacions
INNER JOIN jugadors ON alineacions.jugador_id = jugadors.id
INNER JOIN personas ON jugadors.persona_id = personas.id
INNER JOIN partidos ON alineacions.partido_id = partidos.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id
INNER JOIN equipos ON alineacions.equipo_id = equipos.id
WHERE grupos.torneo_id = '.$torneoId.' AND jugadors.id = '.$arquero->id;
            $jugando = '';
            $juega = DB::select(DB::raw($sqlJugando));
            foreach ($juega as $e){

                $jugando .= $e->escudo.'_'.$e->equipo_id.',';
            }

            $arquero->jugando = $jugando;

            $sql2='SELECT escudo, equipo_id, COUNT(jugadors.id) as jugados,
sum(case when alineacions.equipo_id=partidos.equipol_id then partidos.golesv else partidos.golesl END) AS recibidos,
sum(case when alineacions.equipo_id=partidos.equipol_id and partidos.golesv = 0 then 1 else CASE when alineacions.equipo_id=partidos.equipov_id and partidos.golesl = 0 THEN 1 ELSE 0 END END) AS invictas
FROM equipos
INNER JOIN alineacions ON equipos.id = alineacions.equipo_id
INNER JOIN partidos ON partidos.id = alineacions.partido_id
INNER JOIN jugadors ON alineacions.jugador_id = jugadors.id AND jugadors.tipoJugador = \'Arquero\'
WHERE alineacions.tipo = \'Titular\' AND alineacions.jugador_id = '.$arquero->id.'
GROUP BY escudo, equipo_id
ORDER BY partidos.dia ASC';



            $escudos = DB::select(DB::raw($sql2));


            foreach ($escudos as $escudo){

                $arquero->escudo .= $escudo->escudo.'_'.$escudo->equipo_id.'_'.$escudo->recibidos.'_'.$escudo->invictas.',';

            }

        }




        $arqueros->setPath(route('torneos.arqueros',array('order'=>$order,'tipoOrder'=>$tipoOrder,'actuales'=>$actuales,'torneoId'=>$torneoId)));

        //dd($arqueros);

        $i=$offSet+1;
        return view('torneos.arqueros', compact('arqueros','i','order','tipoOrder','actuales','torneoId'));
    }

    public function jugadores(Request $request)
    {

        set_time_limit(0);
        //dd($request);

        $order= ($request->query('order'))?$request->query('order'):'jugados';
        $tipoOrder= ($request->query('tipoOrder'))?$request->query('tipoOrder'):'DESC';
        $actuales= ($request->query('actuales'))?1:0;

        if($request->query('torneoId')) {
            $torneoId = $request->query('torneoId');

        }
        else{
            $torneo=Torneo::orderBy('year','DESC')->first();
            $torneoId = $torneo->id;
        }

        $torneos=Torneo::orderBy('year','DESC')->get();

        //dd($torneoId);

        /*$sqlUltimoTorneo = 'SELECT MAX(id) as ultimo FROM torneos';

        $ultimoTorneos = DB::select(DB::raw($sqlUltimoTorneo));
        //$torneoId = $ultimoTorneo['ultimo'];
        foreach ($ultimoTorneos as $ultimoTorneo){
            $torneoId = $ultimoTorneo->ultimo;
        }*/

        //$torneoId = $request->query('torneoId');

        $sql = 'SELECT jugador_id, "" escudo, foto, jugador,
       sum(jugados) jugados,

       sum(goles) goles,
       sum(rojas) rojas,
       sum(amarillas) amarillas,
       sum(recibidos) recibidos,
       sum(invictas) invictas

from

(SELECT jugadors.id AS jugador_id, personas.foto,"0" as jugados, CONCAT(personas.apellido,\', \',personas.nombre) jugador, "1" as goles, "0" as  amarillas
, "0" as  rojas, "0" as  recibidos, "0" as  invictas, "0" AS jugando
FROM gols
INNER JOIN jugadors ON gols.jugador_id = jugadors.id
INNER JOIN personas ON jugadors.persona_id = personas.id
INNER JOIN partidos ON gols.partido_id = partidos.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id

WHERE gols.tipo <> \'En contra\'';

        $sql .=($actuales)?' AND EXISTS (
SELECT DISTINCT J1.id
FROM alineacions
INNER JOIN jugadors J1 ON alineacions.jugador_id = J1.id
INNER JOIN partidos P1 ON alineacions.partido_id = P1.id
INNER JOIN fechas F1 ON P1.fecha_id = F1.id
INNER JOIN grupos G1 ON G1.id = F1.grupo_id

WHERE G1.torneo_id = '.$torneoId.' AND J1.id = jugadors.id

)':'';

 $sql .=' UNION ALL
 SELECT jugadors.id AS jugador_id, personas.foto,"0" as jugados, CONCAT(personas.apellido,\', \',personas.nombre) jugador, "0" AS goles, ( case when tipo=\'Amarilla\' then 1 else NULL end) as  amarillas
, ( case when tipo=\'Roja\' or tipo=\'Doble Amarilla\' then 1 else NULL end) as  rojas, "0" as  recibidos, "0" as  invictas, "0" AS jugando
FROM tarjetas
INNER JOIN jugadors ON tarjetas.jugador_id = jugadors.id
INNER JOIN personas ON jugadors.persona_id = personas.id
LEFT JOIN partidos ON tarjetas.partido_id = partidos.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id';

        $sql .=($actuales)?' WHERE EXISTS (
SELECT DISTINCT J1.id
FROM alineacions
INNER JOIN jugadors J1 ON alineacions.jugador_id = J1.id
INNER JOIN partidos P1 ON alineacions.partido_id = P1.id
INNER JOIN fechas F1 ON P1.fecha_id = F1.id
INNER JOIN grupos G1 ON G1.id = F1.grupo_id

WHERE G1.torneo_id = '.$torneoId.' AND J1.id = jugadors.id

)':'';


$sql .=' UNION ALL
 SELECT jugadors.id AS jugador_id, personas.foto,"1" as jugados, CONCAT(personas.apellido,\', \',personas.nombre) jugador, "0" AS goles, "0" as  amarillas
, "0" as  rojas, (case when alineacions.equipo_id=partidos.equipol_id then partidos.golesv else partidos.golesl END) AS recibidos,
(case when alineacions.equipo_id=partidos.equipol_id and partidos.golesv = 0 then 1 else CASE when alineacions.equipo_id=partidos.equipov_id and partidos.golesl = 0 THEN 1 ELSE 0 END END) AS invictas, "0" AS jugando
FROM alineacions
INNER JOIN jugadors ON alineacions.jugador_id = jugadors.id AND jugadors.tipoJugador = \'Arquero\'
INNER JOIN personas ON jugadors.persona_id = personas.id
INNER JOIN partidos ON alineacions.partido_id = partidos.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id

WHERE  alineacions.tipo = \'Titular\'';
        $sql .=($actuales)?' AND EXISTS (
SELECT DISTINCT J1.id
FROM alineacions
INNER JOIN jugadors J1 ON alineacions.jugador_id = J1.id
INNER JOIN partidos P1 ON alineacions.partido_id = P1.id
INNER JOIN fechas F1 ON P1.fecha_id = F1.id
INNER JOIN grupos G1 ON G1.id = F1.grupo_id

WHERE G1.torneo_id = '.$torneoId.' AND J1.id = jugadors.id

)':'';

$sql .= ' UNION ALL
 SELECT jugadors.id AS jugador_id, personas.foto,"1" as jugados, CONCAT(personas.apellido,\', \',personas.nombre) jugador, "0" AS goles, "0" as  amarillas
, "0" as  rojas, "0" AS recibidos,
"0" AS invictas, "0" AS jugando
FROM alineacions
INNER JOIN jugadors ON alineacions.jugador_id = jugadors.id AND jugadors.tipoJugador != \'Arquero\'
INNER JOIN personas ON jugadors.persona_id = personas.id
INNER JOIN partidos ON alineacions.partido_id = partidos.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id
LEFT JOIN cambios ON alineacions.partido_id = cambios.partido_id AND cambios.jugador_id = jugadors.id
WHERE  (alineacions.tipo = \'Titular\' OR cambios.tipo = \'Entra\')';
        $sql .=($actuales)?' AND EXISTS (
SELECT DISTINCT J1.id
FROM alineacions
INNER JOIN jugadors J1 ON alineacions.jugador_id = J1.id
INNER JOIN partidos P1 ON alineacions.partido_id = P1.id
INNER JOIN fechas F1 ON P1.fecha_id = F1.id
INNER JOIN grupos G1 ON G1.id = F1.grupo_id

WHERE G1.torneo_id = '.$torneoId.' AND J1.id = jugadors.id

)':'';

$sql .=' UNION ALL
 SELECT jugadors.id AS jugador_id, personas.foto,"1" as jugados, CONCAT(personas.apellido,\', \',personas.nombre) jugador, "0" AS goles, "0" as  amarillas
, "0" as  rojas, "0" AS recibidos,
"0" AS invictas, "0" AS jugando
FROM alineacions
INNER JOIN jugadors ON alineacions.jugador_id = jugadors.id AND jugadors.tipoJugador = \'Arquero\'
INNER JOIN personas ON jugadors.persona_id = personas.id
INNER JOIN partidos ON alineacions.partido_id = partidos.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id
LEFT JOIN cambios ON alineacions.partido_id = cambios.partido_id AND cambios.jugador_id = jugadors.id
WHERE  (cambios.tipo = \'Entra\')';
        $sql .=($actuales)?' AND EXISTS (
SELECT DISTINCT J1.id
FROM alineacions
INNER JOIN jugadors J1 ON alineacions.jugador_id = J1.id
INNER JOIN partidos P1 ON alineacions.partido_id = P1.id
INNER JOIN fechas F1 ON P1.fecha_id = F1.id
INNER JOIN grupos G1 ON G1.id = F1.grupo_id

WHERE G1.torneo_id = '.$torneoId.' AND J1.id = jugadors.id

)':'';
$sql .=' ) a

group by jugador_id,jugador, foto
ORDER BY '.$order.' '.$tipoOrder.', jugador ASC';

        $jugadores = DB::select(DB::raw($sql));



        $page = $request->query('page', 1);

        $paginate = 15;

        $offSet = ($page * $paginate) - $paginate;

        $itemsForCurrentPage = array_slice($jugadores, $offSet, $paginate, true);



        $jugadores = new \Illuminate\Pagination\LengthAwarePaginator($itemsForCurrentPage, count($jugadores), $paginate, $page);


        foreach ($jugadores as $jugador){

            $sqlJugando = 'SELECT DISTINCT equipos.escudo, alineacions.equipo_id
FROM alineacions
INNER JOIN jugadors ON alineacions.jugador_id = jugadors.id
INNER JOIN personas ON jugadors.persona_id = personas.id
INNER JOIN partidos ON alineacions.partido_id = partidos.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id
INNER JOIN equipos ON alineacions.equipo_id = equipos.id
WHERE grupos.torneo_id = '.$torneoId.' AND jugadors.id = '.$jugador->jugador_id;
            $jugando = '';
            $juega = DB::select(DB::raw($sqlJugando));
            foreach ($juega as $e){

                $jugando .= $e->escudo.'_'.$e->equipo_id.',';
            }

            $jugador->jugando = $jugando;

            $sql2='SELECT DISTINCT escudo, equipo_id
FROM equipos
INNER JOIN alineacions ON equipos.id = alineacions.equipo_id
INNER JOIN partidos ON partidos.id = alineacions.partido_id
WHERE alineacions.jugador_id = '.$jugador->jugador_id;



            $escudos = DB::select(DB::raw($sql2));


            foreach ($escudos as $escudo){

                $jugador->escudo .= $escudo->escudo.'_'.$escudo->equipo_id.',';
            }



        }

        $jugadores->setPath(route('torneos.jugadores',  array('order'=>$order,'tipoOrder'=>$tipoOrder,'actuales'=>$actuales,'torneoId'=>$torneoId)));



        $i=$offSet+1;


        return view('torneos.jugadores', compact('jugadores','i','order','tipoOrder','actuales','torneos','torneoId'));
    }

    public function finalizar(Request $request)
    {



        $torneo_id= $request->query('torneoId');
        $torneo=Torneo::findOrFail($torneo_id);

        $grupos = Grupo::where('torneo_id', '=',$torneo_id)->get();


        $arrgrupos='';
        foreach ($grupos as $grupo2){
            $arrgrupos .=$grupo2->id.',';
        }

        $equipos = Plantilla::with('equipo')->wherein('grupo_id',explode(',', $arrgrupos))->get()->pluck('equipo.nombre', 'equipo_id')->prepend('','');

        if (count($grupos)==1){
            foreach ($grupos as $grupo){
                if ($grupo->posiciones){
                    $posiciones = DB::select(
                        "SELECT foto, equipo,
    count(*) jugados,
    count(case when golesl > golesv then 1 end) ganados,
    count(case when golesv > golesl then 1 end) perdidos,
    count(case when golesl = golesv then 1 end) empatados,
    sum(golesl) golesl,
    sum(golesv) golesv,
    sum(golesl) - sum(golesv) diferencia,
    sum(
        case when golesl > golesv then 3 else 0 end
        + case when golesl = golesv then 1 else 0 end
    ) puntaje, equipo_id
FROM (
    SELECT DISTINCT equipos.nombre equipo, golesl, golesv, equipos.escudo foto, fechas.id fecha_id, equipos.id equipo_id
    FROM partidos
    INNER JOIN equipos ON partidos.equipol_id = equipos.id
    INNER JOIN plantillas ON plantillas.equipo_id = equipos.id
    INNER JOIN fechas ON partidos.fecha_id = fechas.id
    INNER JOIN grupos ON fechas.grupo_id = grupos.id AND grupos.posiciones = 1 AND grupos.torneo_id = ? AND grupos.agrupacion = ?
    WHERE golesl IS NOT NULL AND golesv IS NOT NULL AND EXISTS (SELECT p2.id FROM plantillas p2 WHERE plantillas.equipo_id = p2.equipo_id AND p2.grupo_id = ?)
    UNION ALL
    SELECT DISTINCT equipos.nombre equipo, golesv, golesl, equipos.escudo foto, fechas.id fecha_id, equipos.id equipo_id
    FROM partidos
    INNER JOIN equipos ON partidos.equipov_id = equipos.id
    INNER JOIN plantillas ON plantillas.equipo_id = equipos.id
    INNER JOIN fechas ON partidos.fecha_id = fechas.id
    INNER JOIN grupos ON fechas.grupo_id = grupos.id AND grupos.posiciones = 1 AND grupos.torneo_id = ? AND grupos.agrupacion = ?
    WHERE golesl IS NOT NULL AND golesv IS NOT NULL AND EXISTS (SELECT p2.id FROM plantillas p2 WHERE plantillas.equipo_id = p2.equipo_id AND p2.grupo_id = ?)
) a
GROUP BY equipo, foto, equipo_id
ORDER BY puntaje DESC, diferencia DESC, golesl DESC, equipo ASC",
                        [
                            $grupo->torneo->id,
                            $grupo->agrupacion,
                            $grupo->id,
                            $grupo->torneo->id,
                            $grupo->agrupacion,
                            $grupo->id,
                        ]
                    );



                    //dd($posiciones);
                }

            }
        }







        return view('torneos.finalizar', compact('torneo','equipos'));
    }


    public function guardarFinalizar(Request $request)
    {
        //


        //dd($request->plantillajugador_id);
        /*$this->validate($request,[ 'equipo_id'=>'required',  'grupo_id'=>'required']);
        DB::beginTransaction();
        if($request->plantillajugador_id){
            PlantillaJugador::where('plantilla_id',"$id")->whereNotIn('id', $request->plantillajugador_id)->delete();
        }
        if($request->plantillatecnico_id)  {
            PartidoTecnico::where('plantilla_id',"$id")->whereNotIn('id', $request->plantillatecnico_id)->delete();
        }
        $ok=1;
        $plantilla=plantilla::find($id);
        try {
            $plantilla->update($request->all());
            //PlantillaJugador::where('plantilla_id', '=', "$id")->delete();
            if(count($request->jugador) > 0)
            {
                foreach($request->jugador as $item=>$v){

                    $data2=array(
                        'plantilla_id'=>$id,
                        'jugador_id'=>$request->jugador[$item],
                        'dorsal'=>$request->dorsal[$item]
                    );
                    try {
                        if (!empty($request->plantillajugador_id[$item])){
                            $data2['id']=$request->plantillajugador_id[$item];
                            $plantillaJugador=PlantillaJugador::find($request->plantillajugador_id[$item]);
                            $plantillaJugador->update($data2);
                        }
                        else{
                            PlantillaJugador::create($data2);
                        }



                    }catch(QueryException $ex){
                        if ($ex->errorInfo[1] === 1062) {
                            if (strpos($ex->errorInfo[2], 'plantilla_id_dorsal') !== false) {
                                $consultarPlantilla=PlantillaJugador::where('plantilla_id',"$id")->where('dorsal', $request->dorsal[$item])->first();
                                $jugadorRepetido = Jugador::where('id', '=', $consultarPlantilla->jugador_id)->first();
                                $error = "El dorsal ".$request->dorsal[$item]." ya lo usa ".$jugadorRepetido->persona->apellido.", ".$jugadorRepetido->persona->nombre;
                            } elseif (strpos($ex->errorInfo[2], 'plantilla_id_jugador_id') !== false) {
                                $jugadorRepetido = Jugador::where('id', '=', $request->jugador[$item])->first();
                                $error = "Jugador repetido: ".$jugadorRepetido->persona->apellido.", ".$jugadorRepetido->persona->nombre." dorsal ".$request->dorsal[$item];
                            } else {
                                $error = $ex->getMessage();
                            }
                        } else {
                            $error = $ex->getMessage();
                        }

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
            $respuestaMSJ='Registro actualizado satisfactoriamente';
        }
        else{
            DB::rollback();
            $respuestaID='error';
            $respuestaMSJ=$error;
        }


        return redirect()->route('plantillas.index', array('grupoId' => $plantilla->grupo->id))->with($respuestaID,$respuestaMSJ);*/
    }

}
