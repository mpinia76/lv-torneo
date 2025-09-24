<?php

namespace App\Http\Controllers;

use App\AcumuladoTorneo;
use App\Fecha;
use App\Partido;
use App\Jugador;
use App\Tecnico;
use App\PartidoTecnico;
use App\EquipoClasificado;
use App\Plantilla;
use App\PlantillaJugador;
use App\PosicionTorneo;
use App\Torneo;
use App\TorneoClasificacion;
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
        $this->middleware('auth')->except('ver','promediosPublic','historiales','goleadores','tarjetas','posiciones','estadisticasTorneo','estadisticasOtras', 'tecnicos', 'arqueros','acumulado','jugadores','titulos','plantillas');
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

        $torneos1=Torneo::where('nombre','like',"%$nombre%")->orWhere('year','like',"%$nombre%")->orderBy('year','DESC')->orderBy('id','DESC')->paginate();

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
        $this->validate($request,[ 'nombre'=>'required', 'year'=>'required', 'equipos'=>'required', 'grupos'=>'required', 'tipo'=>'required','escudo' => 'image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048']);



        if ($files = $request->file('escudoTmp')) {
            $image = $request->file('escudoTmp');
            $name = time().'.'.$image->getClientOriginalExtension();
            $destinationPath = public_path('/images');
            $image->move($destinationPath, $name);



            $request->merge(['escudo' => $name]);
        }
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
        $this->validate($request,[ 'nombre'=>'required', 'year'=>'required', 'equipos'=>'required', 'grupos'=>'required', 'tipo'=>'required','escudo' => 'image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048']);


        if ($files = $request->file('escudoTmp')) {
            $image = $request->file('escudoTmp');
            $name = time().'.'.$image->getClientOriginalExtension();
            $destinationPath = public_path('/images');
            $image->move($destinationPath, $name);


            $request->merge(['escudo' => $name]);
        }

        DB::beginTransaction();
        $noBorrar='';
        $noBorrarAcumulado='';
        $noBorrarGrupos='';
        $noBorrarClasificacion='';
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
        if($request->clasificacion_id)  {

            foreach ($request->clasificacion_id as $clasificacion_id){
                $noBorrarClasificacion .=$clasificacion_id.',';
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
            if ($request->nombreClasificacion) {
                foreach ($request->nombreClasificacion as $item => $nombre) {
                    $cantidad = $request->cantidadClasificacion[$item] ?? 0;
                    $data = [
                        'torneo_id' => $id,
                        'nombre'    => $nombre,
                        'cantidad'  => $cantidad
                    ];
                    if (!empty($request->clasificacion_id[$item])) {
                        $clasificacion = TorneoClasificacion::find($request->clasificacion_id[$item]);
                        $clasificacion->update($data);
                    } else {
                        $clasificacion=TorneoClasificacion::create($data);
                    }
                    $noBorrarClasificacion .=$clasificacion->id.',';
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
            TorneoClasificacion::where('torneo_id',"$id")->whereNotIn('id', explode(',', $noBorrarClasificacion))->delete();
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
        $request->session()->put('escudoTorneo', $torneo->escudo);
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
            $sql='SELECT foto, equipo, pais,
(sum( case when fecha_id IS NOT NULL AND golesl > golesv then 3 else 0 end + case when fecha_id IS NOT NULL AND golesl = golesv then 1 else 0 end + COALESCE(puntos, 0)))/count(CASE WHEN fecha_id IS NOT NULL THEN 1 END) promedio, COUNT(CASE WHEN fecha_id IS NOT NULL THEN 1 END) AS jugados, sum( case when fecha_id IS NOT NULL AND golesl > golesv then 3 else 0 end + case when fecha_id IS NOT NULL AND golesl = golesv then 1 else 0 end + COALESCE(puntos, 0)) puntaje, equipo_id
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





                $sql .='select  equipos.nombre equipo, equipos.pais pais, golesl, golesv, equipos.escudo foto, equipos.id equipo_id, fechas.id fecha_id, 0 as puntos
		 from partidos
		 INNER JOIN equipos ON partidos.equipol_id = equipos.id
		 INNER JOIN fechas ON partidos.fecha_id = fechas.id
		 INNER JOIN plantillas ON plantillas.equipo_id = equipos.id
		 INNER JOIN grupos ON plantillas.grupo_id = grupos.id AND grupos.promedios = 1 AND grupos.torneo_id = '.$torneo_id.'
		 INNER JOIN grupos G1 ON fechas.grupo_id = G1.id AND G1.promedios = 1 AND G1.torneo_id = '.$promedioTorneo->torneoAnterior_id.'
		 WHERE golesl is not null AND golesv is not null ';
                $sql .=' AND equipos.id IN ('.$arrequipos.')';
                $sql .=' union all
       select equipos.nombre equipo, equipos.pais pais, golesv, golesl, equipos.escudo foto, equipos.id equipo_id, fechas.id fecha_id, 0 as puntos
		 from partidos
		 INNER JOIN equipos ON partidos.equipov_id = equipos.id
		 INNER JOIN fechas ON partidos.fecha_id = fechas.id
		 INNER JOIN plantillas ON plantillas.equipo_id = equipos.id
		 INNER JOIN grupos ON plantillas.grupo_id = grupos.id AND grupos.promedios = 1 AND grupos.torneo_id = '.$torneo_id.'
		 INNER JOIN grupos G1 ON fechas.grupo_id = G1.id AND G1.promedios = 1 AND G1.torneo_id = '.$promedioTorneo->torneoAnterior_id.'
		 WHERE golesl is not null AND golesv is not null';
                $sql .=' AND equipos.id IN ('.$arrequipos.')';
                $sql .=' union all
       select equipos.nombre equipo, equipos.pais pais, 0 AS golesv, 0 AS golesl, equipos.escudo foto, equipos.id equipo_id, NULL fecha_id, sum(incidencias.puntos) as puntos
		 from incidencias
		 INNER JOIN equipos ON incidencias.equipo_id = equipos.id

		 WHERE incidencias.torneo_id = '.$promedioTorneo->torneoAnterior_id.' AND equipos.id IN ('.$arrequipos.') GROUP BY equipo, pais, foto, equipos.id, incidencias.puntos';
                $sql .=' union all ';
            }

            $sql .='select  equipos.nombre equipo, equipos.pais pais, golesl, golesv, equipos.escudo foto, equipos.id equipo_id, fechas.id fecha_id, 0 as puntos
		 from partidos
		 INNER JOIN equipos ON partidos.equipol_id = equipos.id
		 INNER JOIN fechas ON partidos.fecha_id = fechas.id
		 INNER JOIN plantillas ON plantillas.equipo_id = equipos.id
		 INNER JOIN grupos ON plantillas.grupo_id = grupos.id AND grupos.promedios = 1 AND grupos.torneo_id = '.$torneo_id.'
		 INNER JOIN grupos G1 ON fechas.grupo_id = G1.id AND G1.promedios = 1 AND G1.torneo_id = '.$torneo_id.'
		 WHERE golesl is not null AND golesv is not null

     union all
       select equipos.nombre equipo, equipos.pais pais, golesv, golesl, equipos.escudo foto, equipos.id equipo_id, fechas.id fecha_id, 0 as puntos
		 from partidos
		 INNER JOIN equipos ON partidos.equipov_id = equipos.id
		 INNER JOIN fechas ON partidos.fecha_id = fechas.id
		 INNER JOIN plantillas ON plantillas.equipo_id = equipos.id
		 INNER JOIN grupos ON plantillas.grupo_id = grupos.id AND grupos.promedios = 1 AND grupos.torneo_id = '.$torneo_id.'
		 INNER JOIN grupos G1 ON fechas.grupo_id = G1.id AND G1.promedios = 1 AND G1.torneo_id = '.$torneo_id.'
		 WHERE golesl is not null AND golesv is not null
	union all
       select equipos.nombre equipo, equipos.pais pais, 0 AS golesv, 0 AS golesl, equipos.escudo foto, equipos.id equipo_id, NULL fecha_id, sum(incidencias.puntos) as puntos
		 from incidencias
		 INNER JOIN equipos ON incidencias.equipo_id = equipos.id

		 WHERE incidencias.torneo_id = '.$torneo_id.' AND equipos.id IN ('.$arrequipos.') GROUP BY equipo, pais, foto, equipos.id, incidencias.puntos
		 ) a
group by equipo, pais, foto, equipo_id

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

        $descenso_promedio = $torneo->descenso_promedio ?? 0;
        if ($descenso_promedio > 0) {
            $promedioTorneos = PromedioTorneo::where('torneo_id', '=', "$torneo_id")->orderBy('id', 'ASC')->get();
            $promedios = array();
            if (count($promedioTorneos) > 0) {
                $sql = 'SELECT equipo_id, equipo, (sum(
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
                for ($i = 0; $i < count($promedioTorneos); $i++) {
                    $sql .= 'select  equipos.nombre equipo, golesl, golesv, equipos.id equipo_id
             from partidos
             INNER JOIN equipos ON partidos.equipol_id = equipos.id
             INNER JOIN fechas ON partidos.fecha_id = fechas.id
             INNER JOIN plantillas ON plantillas.equipo_id = equipos.id
             INNER JOIN grupos ON plantillas.grupo_id = grupos.id AND grupos.promedios = 1 AND grupos.torneo_id = ' . $torneo_id . '
             INNER JOIN grupos G1 ON fechas.grupo_id = G1.id AND G1.promedios = 1 AND G1.torneo_id = ' . $promedioTorneos[$i]->torneoAnterior_id . '
             WHERE golesl is not null AND golesv is not null ';
                    $sql .= ($i + 1 == count($promedioTorneos)) ? '' : ' AND EXISTS (SELECT p2.id FROM plantillas p2 INNER JOIN grupos G2 ON p2.grupo_id = G2.id WHERE plantillas.equipo_id = p2.equipo_id AND G2.torneo_id = ' . $promedioTorneos[$i + 1]->torneoAnterior_id . ')';
                    $sql .= ' union all
           select equipos.nombre equipo, golesv, golesl, equipos.id equipo_id
             from partidos
             INNER JOIN equipos ON partidos.equipov_id = equipos.id
             INNER JOIN fechas ON partidos.fecha_id = fechas.id
             INNER JOIN plantillas ON plantillas.equipo_id = equipos.id
             INNER JOIN grupos ON plantillas.grupo_id = grupos.id AND grupos.promedios = 1 AND grupos.torneo_id = ' . $torneo_id . '
             INNER JOIN grupos G1 ON fechas.grupo_id = G1.id AND G1.promedios = 1 AND G1.torneo_id = ' . $promedioTorneos[$i]->torneoAnterior_id . '
             WHERE golesl is not null AND golesv is not null';
                    $sql .= ($i + 1 == count($promedioTorneos)) ? '' : ' AND EXISTS (SELECT p2.id FROM plantillas p2 INNER JOIN grupos G2 ON p2.grupo_id = G2.id WHERE plantillas.equipo_id = p2.equipo_id AND G2.torneo_id = ' . $promedioTorneos[$i + 1]->torneoAnterior_id . ')';
                    $sql .= ' union all ';
                }

                $sql .= 'select  equipos.nombre equipo, golesl, golesv, equipos.id equipo_id
             from partidos
             INNER JOIN equipos ON partidos.equipol_id = equipos.id
             INNER JOIN fechas ON partidos.fecha_id = fechas.id
             INNER JOIN plantillas ON plantillas.equipo_id = equipos.id
             INNER JOIN grupos ON plantillas.grupo_id = grupos.id AND grupos.promedios = 1 AND grupos.torneo_id = ' . $torneo_id . '
             INNER JOIN grupos G1 ON fechas.grupo_id = G1.id AND G1.promedios = 1 AND G1.torneo_id = ' . $torneo_id . '
             WHERE golesl is not null AND golesv is not null

         union all
           select equipos.nombre equipo, golesv, golesl, equipos.id equipo_id
             from partidos
             INNER JOIN equipos ON partidos.equipov_id = equipos.id
             INNER JOIN fechas ON partidos.fecha_id = fechas.id
             INNER JOIN plantillas ON plantillas.equipo_id = equipos.id
             INNER JOIN grupos ON plantillas.grupo_id = grupos.id AND grupos.promedios = 1 AND grupos.torneo_id = ' . $torneo_id . '
             INNER JOIN grupos G1 ON fechas.grupo_id = G1.id AND G1.promedios = 1 AND G1.torneo_id = ' . $torneo_id . '
             WHERE golesl is not null AND golesv is not null) a
    group by equipo, equipo_id

    order by promedio desc, puntaje desc, equipo ASC';


                //echo $sql;
                $promedios = DB::select(DB::raw($sql));
                // Ordenar en PHP por promedio ascendente
                usort($promedios, function($a, $b) {
                    return $a->promedio <=> $b->promedio; // menor promedio primero
                });

// Tomar los que van a descenso
                $promedios = array_slice($promedios, 0, $descenso_promedio);
            }
        }


        $acumuladoTorneos = AcumuladoTorneo::where('torneo_id','=',"$torneo_id")->orderBy('id','DESC')->get();
        $acumulado = array();
        if (count($acumuladoTorneos)>0){
            $sql='SELECT foto, equipo,
       count(CASE WHEN fecha_id IS NOT NULL THEN 1 END) jugados,
       count(case when fecha_id IS NOT NULL AND golesl > golesv then 1 end) ganados,
       count(case when fecha_id IS NOT NULL AND golesv > golesl then 1 end) perdidos,
       count(case when fecha_id IS NOT NULL AND golesl = golesv then 1 end) empatados,
       sum(golesl) golesl,
       sum(golesv) golesv,
       sum(golesl) - sum(golesv) diferencia,
       sum(
             case when fecha_id IS NOT NULL AND golesl > golesv then 3 else 0 end
           + case when fecha_id IS NOT NULL AND golesl = golesv then 1 else 0 end
       ) + COALESCE(SUM(puntos), 0) puntaje, (sum(
             case when fecha_id IS NOT NULL AND golesl > golesv then 3 else 0 end
           + case when fecha_id IS NOT NULL AND golesl = golesv then 1 else 0 end
       )+ COALESCE(SUM(puntos), 0)
    ) / NULLIF(COUNT(CASE WHEN fecha_id IS NOT NULL THEN 1 END), 0) AS promedio, equipo_id

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





                $sql .='select  equipos.nombre equipo, golesl, golesv, equipos.escudo foto, equipos.id equipo_id,
        fechas.id AS fecha_id,
        0 AS puntos
		 from partidos
		 INNER JOIN equipos ON partidos.equipol_id = equipos.id
		 INNER JOIN fechas ON partidos.fecha_id = fechas.id
		 INNER JOIN plantillas ON plantillas.equipo_id = equipos.id
		 INNER JOIN grupos ON plantillas.grupo_id = grupos.id AND grupos.acumulado = 1 AND grupos.torneo_id = '.$torneo_id.'
		 INNER JOIN grupos G1 ON fechas.grupo_id = G1.id AND G1.acumulado = 1 AND G1.torneo_id = '.$acumuladoTorneo->torneoAnterior_id.'
		 WHERE golesl is not null AND golesv is not null ';
                $sql .=' AND equipos.id IN ('.$arrequipos.')';
                $sql .=' union all
       select equipos.nombre equipo, golesv, golesl, equipos.escudo foto, equipos.id equipo_id,
        fechas.id AS fecha_id,
        0 AS puntos
		 from partidos
		 INNER JOIN equipos ON partidos.equipov_id = equipos.id
		 INNER JOIN fechas ON partidos.fecha_id = fechas.id
		 INNER JOIN plantillas ON plantillas.equipo_id = equipos.id
		 INNER JOIN grupos ON plantillas.grupo_id = grupos.id AND grupos.acumulado = 1 AND grupos.torneo_id = '.$torneo_id.'
		 INNER JOIN grupos G1 ON fechas.grupo_id = G1.id AND G1.acumulado = 1 AND G1.torneo_id = '.$acumuladoTorneo->torneoAnterior_id.'
		 WHERE golesl is not null AND golesv is not null';
                $sql .=' AND equipos.id IN ('.$arrequipos.')';
                $sql .=' UNION ALL
    SELECT
        equipos.nombre AS equipo,

        0 AS golesv,
        0 AS golesl,
        equipos.escudo AS foto,

        equipos.id AS equipo_id,
        NULL AS fecha_id,
        SUM(puntos) AS puntos
    FROM incidencias
    INNER JOIN equipos ON incidencias.equipo_id = equipos.id
    WHERE incidencias.torneo_id = '.$acumuladoTorneo->torneoAnterior_id.' AND equipos.id IN ('.$arrequipos.')
    GROUP BY equipo, foto, equipos.id, incidencias.puntos';
                $sql .=' union all ';
            }

            $sql .='select  equipos.nombre equipo, golesl, golesv, equipos.escudo foto, equipos.id equipo_id,
        fechas.id AS fecha_id,
        0 AS puntos
		 from partidos
		 INNER JOIN equipos ON partidos.equipol_id = equipos.id
		 INNER JOIN fechas ON partidos.fecha_id = fechas.id
		 INNER JOIN plantillas ON plantillas.equipo_id = equipos.id
		 INNER JOIN grupos ON plantillas.grupo_id = grupos.id AND grupos.acumulado = 1 AND grupos.torneo_id = '.$torneo_id.'
		 INNER JOIN grupos G1 ON fechas.grupo_id = G1.id AND G1.acumulado = 1 AND G1.torneo_id = '.$torneo_id.'
		 WHERE golesl is not null AND golesv is not null

     union all
       select equipos.nombre equipo, golesv, golesl, equipos.escudo foto, equipos.id equipo_id,
        fechas.id AS fecha_id,
        0 AS puntos
		 from partidos
		 INNER JOIN equipos ON partidos.equipov_id = equipos.id
		 INNER JOIN fechas ON partidos.fecha_id = fechas.id
		 INNER JOIN plantillas ON plantillas.equipo_id = equipos.id
		 INNER JOIN grupos ON plantillas.grupo_id = grupos.id AND grupos.acumulado = 1 AND grupos.torneo_id = '.$torneo_id.'
		 INNER JOIN grupos G1 ON fechas.grupo_id = G1.id AND G1.acumulado = 1 AND G1.torneo_id = '.$torneo_id.'
		 WHERE golesl is not null AND golesv is not null
UNION ALL
    SELECT
        equipos.nombre AS equipo,

        0 AS golesv,
        0 AS golesl,
        equipos.escudo AS foto,

        equipos.id AS equipo_id,
        NULL AS fecha_id,
        SUM(puntos) AS puntos
    FROM incidencias
    INNER JOIN equipos ON incidencias.equipo_id = equipos.id
    WHERE incidencias.torneo_id = '.$torneo_id.'
    GROUP BY equipo, foto, equipos.id, incidencias.puntos) a

group by equipo, foto, equipo_id

order by  puntaje desc, diferencia DESC, golesl DESC, equipo ASC';
            //echo $sql;
            $acumulado = DB::select(DB::raw($sql));
        }
        else {
            // Solo torneo actual
            $sql = "
        SELECT escudo as foto, nombre as equipo,
           COUNT(CASE WHEN fecha_id IS NOT NULL THEN 1 END) AS jugados,
           COUNT(CASE WHEN fecha_id IS NOT NULL AND golesl > golesv THEN 1 END) AS ganados,
           COUNT(CASE WHEN fecha_id IS NOT NULL AND golesv > golesl THEN 1 END) AS perdidos,
           COUNT(CASE WHEN fecha_id IS NOT NULL AND golesl = golesv THEN 1 END) AS empatados,
           SUM(golesl) AS golesl,
           SUM(golesv) AS golesv,
           SUM(golesl) - SUM(golesv) AS diferencia,
           SUM(CASE WHEN fecha_id IS NOT NULL AND golesl > golesv THEN 3
                    WHEN fecha_id IS NOT NULL AND golesl = golesv THEN 1
                    ELSE 0 END) AS puntaje,
           (SUM(CASE WHEN fecha_id IS NOT NULL AND golesl > golesv THEN 3
                     WHEN fecha_id IS NOT NULL AND golesl = golesv THEN 1
                     ELSE 0 END) / NULLIF(COUNT(CASE WHEN fecha_id IS NOT NULL THEN 1 END),0)) AS promedio,
           equipos.id as equipo_id
        FROM partidos
        INNER JOIN equipos ON (equipos.id = partidos.equipol_id OR equipos.id = partidos.equipov_id)
        INNER JOIN fechas ON fechas.id = partidos.fecha_id
        WHERE equipos.id IN (" . implode(',', $equipos->keys()->toArray()) . ")
        GROUP BY nombre, foto, equipos.id
        ORDER BY puntaje DESC, diferencia DESC, golesl DESC, equipo ASC
    ";
            $acumulado = DB::select(DB::raw($sql));
        }

        // Obtener torneos involucrados en el acumulado
        $torneosAnterioresIds = $acumuladoTorneos->pluck('torneoAnterior_id')->toArray();

// Buscar los campeones
        $campeones = PosicionTorneo::whereIn('torneo_id', $torneosAnterioresIds)
            ->where('posicion', 1)
            ->pluck('equipo_id')
            ->toArray();


        $descenso = $torneo->descenso ?? 0; // default 2 si no está definido
        // 2. Recorrer acumulado y asignar zonas normales, campeones y descensos finales
        $descendidosAcumulado = [];
        $totalEquipos = count($acumulado);

// Ordenamos las clasificaciones por ID ascendente
        $clasificaciones = $torneo->clasificaciones->sortBy('id')->mapWithKeys(function($c) {
            return [$c->nombre => $c->cantidad];
        })->toArray();

        $zonaPrimera = array_key_first($clasificaciones); // ID más bajo = primera zona (ej: Libertadores)

        $equiposClasificados = EquipoClasificado::where('torneo_id', $torneo->id)
            ->with('clasificacion')
            ->get()
            ->keyBy('equipo_id');

        // 1. Marcar equipos de promedio como Descenso
        $promediosADescender = [];
        if (!empty($promedios)) {
            foreach ($promedios as $p) {
                //dd($p);
                $promediosADescender[$p->equipo_id] = $p;
            }
        }

        foreach ($acumulado as $index => $equipo) {

            $pos = $index + 1;
            $equipo->zona = 'Ninguna';

            // Campeones de torneos previos van a la zona de ID más bajo
            if (in_array($equipo->equipo_id, $campeones)) {
                $equipo->zona = $zonaPrimera;
                continue;
            }

            if(isset($equiposClasificados[$equipo->equipo_id])) {
                $equipo->zona = $equiposClasificados[$equipo->equipo_id]->clasificacion->nombre;
                continue;
            }

            $inicio = 1;
            foreach ($clasificaciones as $nombre => $cantidad) {
                $fin = $inicio + $cantidad - 1;
                if ($pos >= $inicio && $pos <= $fin) {
                    $equipo->zona = $nombre;
                    break; // asignamos solo una zona
                }
                $inicio = $fin + 1;
            }




            // Descenso por promedio tiene prioridad
            if (!empty($promediosADescender) && isset($promediosADescender[$equipo->equipo_id])) {
                $equipo->zona = 'Descenso';
                $descendidosAcumulado[$equipo->equipo_id] = $equipo;
                continue; // evitar que el descenso por posición sobrescriba
            }

            // Descenso por posición
            if ($pos > $totalEquipos - $descenso) {
                $equipo->zona = 'Descenso';
                $descendidosAcumulado[$equipo->equipo_id] = $equipo;
            }

        }
        //dd($promediosADescender);


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
            $sql='SELECT torneos.nombre AS nombreTorneo, torneos.year, fechas.numero, partidos.dia, e1.id AS equipol_id, e1.escudo AS fotoLocal, e1.nombre AS local, e1.pais AS paisLocal, e2.id AS equipov_id,e2.escudo AS fotoVisitante, e2.nombre AS visitante, e2.pais AS paisVisitante, partidos.golesl,
partidos.golesv, partidos.penalesl, partidos.penalesv, partidos.id partido_id, CASE WHEN partidos.neutral = 0 THEN \'NO\' ELSE \'SI\' END AS neutral
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
            $torneo=Torneo::orderBy('year','DESC')->orderBy('id','DESC')->first();
            $torneoId = $torneo->id;
        }
        if ($request->has('buscarpor')){
            $nombre = $request->get('buscarpor');

            $request->session()->put('nombre_filtro_jugador', $request->get('buscarpor'));

        }
        else{
            $nombre = $request->session()->get('nombre_filtro_jugador');

        }
        $nombreFiltro='';
        $nombreFiltro2='';
        if ($nombre) {
            $nombreFiltro = " AND (personas.apellido LIKE '%$nombre%' OR personas.nombre LIKE '%$nombre%') ";
            $nombreFiltro2 = " AND (P2.apellido LIKE '%$nombre%' OR P2.nombre LIKE '%$nombre%') ";
        }
        //$torneos=Torneo::orderBy('year','DESC')->orderBy('id','DESC')->get();

        $sql = 'SELECT jugadors.id, personas.name as jugador, CONCAT(personas.apellido,\', \',personas.nombre) completo, personas.foto, personas.nacionalidad, COUNT(gols.id) goles, count( case when tipo=\'Jugada\' then 1 else NULL end) as  Jugada, "" as escudo, "0" as jugados
, count( case when tipo=\'Cabeza\' then 1 else NULL end) as  Cabeza, count( case when tipo=\'Penal\' then 1 else NULL end) as  Penal, count( case when tipo=\'Tiro Libre\' then 1 else NULL end) as  Tiro_Libre, "" AS jugando
FROM gols
INNER JOIN jugadors ON gols.jugador_id = jugadors.id
INNER JOIN partidos ON gols.partido_id = partidos.id
INNER JOIN personas ON jugadors.persona_id = personas.id

WHERE gols.tipo <> \'En contra\''.$nombreFiltro;
        $sql .=($actuales)?' AND EXISTS (
SELECT DISTINCT J1.id
FROM alineacions
INNER JOIN jugadors J1 ON alineacions.jugador_id = J1.id
INNER JOIN personas P2 ON J1.persona_id = P2.id
INNER JOIN partidos P1 ON alineacions.partido_id = P1.id
INNER JOIN fechas F1 ON P1.fecha_id = F1.id
INNER JOIN grupos G1 ON G1.id = F1.grupo_id

WHERE G1.torneo_id = '.$torneoId.' AND J1.id = jugadors.id'. $nombreFiltro2.'

)':'';

$sql .=' GROUP BY jugadors.id,jugador, foto, nacionalidad
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
            $torneo=Torneo::orderBy('year','DESC')->orderBy('id','DESC')->first();
            $torneoId = $torneo->id;
        }

        if ($request->has('buscarpor')){
            $nombre = $request->get('buscarpor');

            $request->session()->put('nombre_filtro_jugador', $request->get('buscarpor'));

        }
        else{
            $nombre = $request->session()->get('nombre_filtro_jugador');

        }
        $nombreFiltro='';
        $nombreFiltro2='';
        if ($nombre) {
            $nombreFiltro = " AND (personas.apellido LIKE '%$nombre%' OR personas.nombre LIKE '%$nombre%') ";
            $nombreFiltro2 = " AND (P2.apellido LIKE '%$nombre%' OR P2.nombre LIKE '%$nombre%') ";
        }
        $torneos=Torneo::orderBy('year','DESC')->get();

        $sql ='SELECT jugadors.id, personas.name as jugador, CONCAT(personas.apellido,\', \',personas.nombre) completo, personas.foto, personas.nacionalidad,count( case when tipo=\'Amarilla\' then 1 else NULL end) as  amarillas
, count( case when tipo=\'Roja\' or tipo=\'Doble Amarilla\' then 1 else NULL end) as  rojas, "" escudo, "0" as jugados, "" as jugando
FROM tarjetas
INNER JOIN jugadors ON tarjetas.jugador_id = jugadors.id
INNER JOIN personas ON jugadors.persona_id = personas.id
INNER JOIN partidos ON tarjetas.partido_id = partidos.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id
WHERE 1=1'.$nombreFiltro;
        $sql .=($actuales)?' WHERE EXISTS (
SELECT DISTINCT J1.id
FROM alineacions
INNER JOIN jugadors J1 ON alineacions.jugador_id = J1.id
INNER JOIN personas P2 ON J1.persona_id = P2.id
INNER JOIN partidos P1 ON alineacions.partido_id = P1.id
INNER JOIN fechas F1 ON P1.fecha_id = F1.id
INNER JOIN grupos G1 ON G1.id = F1.grupo_id

WHERE G1.torneo_id = '.$torneoId.' AND J1.id = jugadors.id'. $nombreFiltro2.'

)':'';

$sql .=' GROUP BY jugadors.id, jugador, foto, nacionalidad
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
        if ($request->has('buscarpor')){
            $nombre = $request->get('buscarpor');

            $request->session()->put('nombre_filtro_equipo', $request->get('buscarpor'));

        }
        else{
            $nombre = $request->session()->get('nombre_filtro_equipo');

        }
        $nombreFiltro='';

        if ($nombre) {
            $nombreFiltro = " AND (equipos.nombre LIKE '%$nombre%') ";

        }
        $tipo='';
        if($request->query('tipo')) {
            $tipo = $request->query('tipo');

        }
        $ambito='';
        if($request->query('ambito')) {
            $ambito = $request->query('ambito');

        }

        $argentinos= ($request->query('argentinos'))?1:0;
                $sql='SELECT
    foto,
    equipo,
    pais,
    COUNT(CASE WHEN fecha_id IS NOT NULL THEN 1 END) AS jugados,
    COUNT(CASE WHEN fecha_id IS NOT NULL AND golesl > golesv THEN 1 END) AS ganados,
    COUNT(CASE WHEN fecha_id IS NOT NULL AND golesv > golesl THEN 1 END) AS perdidos,
    COUNT(CASE WHEN fecha_id IS NOT NULL AND golesl = golesv THEN 1 END) AS empatados,
    SUM(golesl) AS golesl,
    SUM(golesv) AS golesv,
    SUM(golesl) - SUM(golesv) AS diferencia,
    SUM(
        CASE
            WHEN fecha_id IS NOT NULL AND golesl > golesv THEN 3
            WHEN fecha_id IS NOT NULL AND golesl = golesv THEN 1
            ELSE 0
        END
    ) + COALESCE(SUM(puntos), 0) AS puntaje,
    (
        SUM(
            CASE
                WHEN fecha_id IS NOT NULL AND golesl > golesv THEN 3
                WHEN fecha_id IS NOT NULL AND golesl = golesv THEN 1
                ELSE 0
            END
        ) + COALESCE(SUM(puntos), 0)
    ) / NULLIF(COUNT(CASE WHEN fecha_id IS NOT NULL THEN 1 END), 0) AS promedio,
    equipo_id,
    "" as titulos
from (
       select  DISTINCT equipos.nombre equipo, equipos.pais pais, golesl, golesv, equipos.escudo foto, fechas.id fecha_id, equipos.id equipo_id, 0 as puntos
		 from partidos
		 INNER JOIN equipos ON partidos.equipol_id = equipos.id
		 INNER JOIN plantillas ON plantillas.equipo_id = equipos.id
		 INNER JOIN fechas ON partidos.fecha_id = fechas.id
		 INNER JOIN grupos ON fechas.grupo_id = grupos.id
        INNER JOIN torneos ON grupos.torneo_id = torneos.id
        WHERE golesl is not null AND golesv is not null'.$nombreFiltro;
        $sql .=($tipo)?' AND torneos.tipo = \''.$tipo.'\'':'';
        $sql .=($ambito)?' AND torneos.ambito = \''.$ambito.'\'':'';
        $sql .=' union all
       select DISTINCT equipos.nombre equipo, equipos.pais pais, golesv, golesl, equipos.escudo foto, fechas.id fecha_id, equipos.id equipo_id, 0 as puntos
		 from partidos
		 INNER JOIN equipos ON partidos.equipov_id = equipos.id
		 INNER JOIN plantillas ON plantillas.equipo_id = equipos.id
		 INNER JOIN fechas ON partidos.fecha_id = fechas.id
		 INNER JOIN grupos ON fechas.grupo_id = grupos.id
		 INNER JOIN torneos ON grupos.torneo_id = torneos.id
		 WHERE golesl is not null AND golesv is not null'.$nombreFiltro;
        $sql .=($tipo)?' AND torneos.tipo = \''.$tipo.'\'':'';
        $sql .=($ambito)?' AND torneos.ambito = \''.$ambito.'\'':'';
        $sql .= ' UNION ALL
    SELECT
        equipos.nombre AS equipo,
        equipos.pais AS pais,
        0 AS golesv,
        0 AS golesl,
        equipos.escudo AS foto,
        NULL AS fecha_id,
        equipos.id AS equipo_id,
        SUM(puntos) AS puntos
    FROM incidencias
    INNER JOIN equipos ON incidencias.equipo_id = equipos.id
    INNER JOIN torneos ON incidencias.torneo_id = torneos.id
    WHERE 1=1'.$nombreFiltro;;
        $sql .=($tipo)?' AND torneos.tipo = \''.$tipo.'\'':'';
        $sql .=($ambito)?' AND torneos.ambito = \''.$ambito.'\'':'';
        $sql .=' GROUP BY equipos.nombre, equipos.pais, equipos.escudo, equipos.id, incidencias.puntos';
        $sql .= ') a WHERE 1=1 ';

// 🔹 Aplico el filtro de argentinos aquí
        if ($argentinos) {
            $sql .= " AND pais = 'Argentina' ";
        }

        $sql .= ' group by equipo, pais, foto, equipo_id
order by puntaje desc, promedio DESC, diferencia DESC, golesl DESC, equipo ASC';
       // Log::channel('mi_log')->info('Sql pos: '.$sql,[]);
                $posiciones = DB::select(DB::raw($sql));

        $page = $request->query('page', 1);

        $paginate = 15;

        $offSet = ($page * $paginate) - $paginate;

        $itemsForCurrentPage = array_slice($posiciones, $offSet, $paginate, true);



        $posiciones = new \Illuminate\Pagination\LengthAwarePaginator($itemsForCurrentPage, count($posiciones), $paginate, $page);

        foreach ($posiciones as $posicion){
            $titulosCopa=0;
            $titulosLiga=0;
            $titulosInternacional=0;
            $posicionTorneo = PosicionTorneo::where('equipo_id', '=',$posicion->equipo_id)->get();
            foreach ($posicionTorneo as $pt){
                $torneo=Torneo::findOrFail($pt->torneo_id);
                if ($pt->posicion == 1){
                    //if ((stripos($torneo->nombre, 'Copa') !== false)||(stripos($torneo->nombre, 'Trofeo') !== false)) {
                    if ($torneo->ambito == 'Nacional') {
                        if (!$ambito || $ambito=='nacional') {
                            if ($torneo->tipo == 'Copa') {
                                if (!$tipo || $tipo == 'copa') {
                                    $titulosCopa++;
                                }

                            } else {
                                if (!$tipo || $tipo == 'liga') {
                                    $titulosLiga++;
                                }
                            }
                        }
                    }
                    else{
                        if (!$ambito || $ambito=='internacional') {
                            $titulosInternacional++;
                        }
                    }
                }
            }
            if (($titulosCopa+$titulosLiga+$titulosInternacional)==0){
                $posicion->titulos='';
            }
            else{
                $ligas=($titulosLiga)?$titulosLiga.' Ligas':'';
                $copas=($titulosCopa)?$titulosCopa.' Copas':'';
                $internacionales=($titulosInternacional)?$titulosInternacional.' Internacionales':'';
                $posicion->titulos=$titulosCopa+$titulosLiga+$titulosInternacional. ' ('.$ligas.' '.$copas.' '.$internacionales.')';
            }

        }

        $posiciones->setPath(route('torneos.posiciones',array('argentinos'=>$argentinos,'tipo'=>$tipo,'ambito'=>$ambito,'buscarpor'=>$nombre)));


        $i=$offSet+1;


        return view('torneos.posiciones', compact('posiciones','i','argentinos'));
    }

    public function estadisticasTorneo(Request $request)
    {
        $torneo_id = $request->query('torneoId');

        // Consulta general para las estadísticas básicas
        $estadisticas = DB::select(DB::raw("
    SELECT
        torneos.nombre AS nombreTorneo,
        torneos.escudo AS escudoTorneo,
        torneos.year,

        COUNT(*) AS total_partidos,
        SUM(partidos.golesl + partidos.golesv) AS total_goles,
        (SUM(partidos.golesl + partidos.golesv) * 1.0 / COUNT(*)) AS promedio_total,

        SUM(CASE WHEN partidos.neutral = 0 THEN partidos.golesl ELSE 0 END) AS goles_local,
        SUM(CASE WHEN partidos.neutral = 0 THEN partidos.golesv ELSE 0 END) AS goles_visitante,
        SUM(CASE WHEN partidos.neutral != 0 THEN partidos.golesl + partidos.golesv ELSE 0 END) AS goles_neutral,

        COUNT(CASE WHEN partidos.neutral = 0 THEN 1 ELSE NULL END) AS partidos_no_neutrales,
        COUNT(CASE WHEN partidos.neutral != 0 THEN 1 ELSE NULL END) AS partidos_neutrales,

        (SUM(CASE WHEN partidos.neutral = 0 THEN partidos.golesl ELSE 0 END) * 1.0 / NULLIF(COUNT(CASE WHEN partidos.neutral = 0 THEN 1 END), 0)) AS promedio_local,
        (SUM(CASE WHEN partidos.neutral = 0 THEN partidos.golesv ELSE 0 END) * 1.0 / NULLIF(COUNT(CASE WHEN partidos.neutral = 0 THEN 1 END), 0)) AS promedio_visitante,
        (SUM(CASE WHEN partidos.neutral != 0 THEN partidos.golesl + partidos.golesv ELSE 0 END) * 1.0 / NULLIF(COUNT(CASE WHEN partidos.neutral != 0 THEN 1 END), 0)) AS promedio_neutral

    FROM partidos
    INNER JOIN fechas ON partidos.fecha_id = fechas.id
    INNER JOIN grupos ON fechas.grupo_id = grupos.id
    INNER JOIN torneos ON grupos.torneo_id = torneos.id
    WHERE torneos.id = :torneo_id
    AND partidos.golesl IS NOT NULL
      AND partidos.golesv IS NOT NULL
    GROUP BY torneos.nombre, torneos.year
"), ['torneo_id' => $torneo_id]);


        // Reuso una función para obtener partidos con max o min goles totales
        $maxGoles = $this->partidosPorGolesTotales($torneo_id, 'MAX');
        $minGoles = $this->partidosPorGolesTotales($torneo_id, 'MIN');

        // Reuso una función para obtener partidos con max o min goles locales
        $maxGolesLocales = $this->partidosPorGolesIndividuales($torneo_id, 'golesl', 'MAX',2);
        $minGolesLocales = $this->partidosPorGolesIndividuales($torneo_id, 'golesl', 'MIN',2);

        // Reuso una función para obtener partidos con max o min goles visitantes
        $maxGolesVisitantes = $this->partidosPorGolesIndividuales($torneo_id, 'golesv', 'MAX',2);
        $minGolesVisitantes = $this->partidosPorGolesIndividuales($torneo_id, 'golesv', 'MIN',2);

        $maxGolesNeutrales = $this->partidosPorGolesIndividuales($torneo_id, 'golesl + golesv', 'MAX',1);
        $minGolesNeutrales = $this->partidosPorGolesIndividuales($torneo_id, 'golesl + golesv', 'MIN',1);

        // Reuso una función para obtener fechas con más/menos goles (totales, locales, visitantes)
        $fechaMasGoles = $this->fechasPorGoles($torneo_id, 'MAX', 'golesl + golesv');
        $fechaMinGoles = $this->fechasPorGoles($torneo_id, 'MIN', 'golesl + golesv');

        $fechaMasGolesLocales = $this->fechasPorGoles($torneo_id, 'MAX', 'golesl',2);
        $fechaMinGolesLocales = $this->fechasPorGoles($torneo_id, 'MIN', 'golesl',2);

        $fechaMasGolesVisitantes = $this->fechasPorGoles($torneo_id, 'MAX', 'golesv',2);
        $fechaMinGolesVisitantes = $this->fechasPorGoles($torneo_id, 'MIN', 'golesv',2);

        $fechaMasGolesNeutrales = $this->fechasPorGoles($torneo_id, 'MAX', 'golesl + golesv',1);
        $fechaMinGolesNeutrales = $this->fechasPorGoles($torneo_id, 'MIN', 'golesl + golesv',1);

        $estadisticas['goles']=$estadisticas;
        $estadisticas['maxGoles']=$maxGoles;
        $estadisticas['minGoles']=$minGoles;
        $estadisticas['maxGolesLocales']=$maxGolesLocales;
        $estadisticas['minGolesLocales']=$minGolesLocales;
        $estadisticas['maxGolesVisitantes']=$maxGolesVisitantes;
        $estadisticas['minGolesVisitantes']=$minGolesVisitantes;
        $estadisticas['maxGolesNeutrales']=$maxGolesNeutrales;
        $estadisticas['minGolesNeutrales']=$minGolesNeutrales;
        $estadisticas['fechaMasGoles']=$fechaMasGoles;
        $estadisticas['fechaMinGoles']=$fechaMinGoles;
        $estadisticas['fechaMasGolesLocales']=$fechaMasGolesLocales;
        $estadisticas['fechaMinGolesLocales']=$fechaMinGolesLocales;
        $estadisticas['fechaMasGolesVisitantes']=$fechaMasGolesVisitantes;
        $estadisticas['fechaMinGolesVisitantes']=$fechaMinGolesVisitantes;
        $estadisticas['fechaMasGolesNeutrales']=$fechaMasGolesNeutrales;
        $estadisticas['fechaMinGolesNeutrales']=$fechaMinGolesNeutrales;

        $promedioEquipo = DB::select(DB::raw("
    SELECT
        e.nombre AS nombre,
         e.escudo AS escudo,
         e.pais AS pais,
        COUNT(p.id) AS partidos,
        SUM(CASE WHEN p.equipol_id = e.id THEN p.golesl ELSE p.golesv END) AS goles_favor,
        SUM(CASE WHEN p.equipol_id = e.id THEN p.golesv ELSE p.golesl END) AS goles_contra,
        (SUM(CASE WHEN p.equipol_id = e.id THEN p.golesl ELSE p.golesv END) -
         SUM(CASE WHEN p.equipol_id = e.id THEN p.golesv ELSE p.golesl END)) AS diferencia,
        ROUND(
            (SUM(CASE WHEN p.equipol_id = e.id THEN p.golesl ELSE p.golesv END) * 1.0) / NULLIF(COUNT(p.id),0),
            2
        ) AS promedio_favor,
        ROUND(
            (SUM(CASE WHEN p.equipol_id = e.id THEN p.golesv ELSE p.golesl END) * 1.0) / NULLIF(COUNT(p.id),0),
            2
        ) AS promedio_contra
    FROM equipos e
    INNER JOIN partidos p ON (p.equipol_id = e.id OR p.equipov_id = e.id)
    INNER JOIN fechas f ON p.fecha_id = f.id
    INNER JOIN grupos g ON f.grupo_id = g.id
    INNER JOIN torneos t ON g.torneo_id = t.id
    WHERE t.id = :torneo_id
      AND p.golesl IS NOT NULL
      AND p.golesv IS NOT NULL
    GROUP BY e.nombre
    ORDER BY promedio_favor DESC
"), ['torneo_id' => $torneo_id]);

        $estadisticas['promedioEquipo'] = $promedioEquipo;



        return view('torneos.estadisticasTorneo', compact('estadisticas'));


    }

    /**
     * Obtiene partidos donde la suma de goles locales + visitantes
     * es el máximo o mínimo según $tipo ('MAX' o 'MIN')
     */
    private function partidosPorGolesTotales(int $torneo_id, string $tipo)
    {
        return DB::select(DB::raw("

		 SELECT torneos.nombre AS nombreTorneo, torneos.year, fechas.numero, partidos.dia, e1.id AS equipol_id, e1.escudo AS fotoLocal, e1.nombre AS local, e2.id AS equipov_id,e2.escudo AS fotoVisitante, e2.nombre AS visitante, partidos.golesl,
partidos.golesv, partidos.penalesl, partidos.penalesv, partidos.id partido_id, e1.pais AS paisLocal, e2.pais AS paisVisitante
        FROM partidos
        INNER JOIN equipos e1 ON partidos.equipol_id = e1.id
		INNER JOIN equipos e2 ON partidos.equipov_id = e2.id
		INNER JOIN fechas ON partidos.fecha_id = fechas.id
		INNER JOIN grupos ON fechas.grupo_id = grupos.id
		INNER JOIN torneos ON grupos.torneo_id = torneos.id
        WHERE torneos.id = :torneo_id_1
        AND partidos.golesl IS NOT NULL
        AND partidos.golesv IS NOT NULL

        AND (partidos.golesl + partidos.golesv) = (
            SELECT {$tipo}(p1.golesl + p1.golesv)
            FROM partidos p1
            INNER JOIN fechas f1 ON p1.fecha_id = f1.id
			INNER JOIN grupos g1 ON f1.grupo_id = g1.id
			INNER JOIN torneos t1 ON g1.torneo_id = t1.id
            WHERE t1.id = :torneo_id_2
            AND p1.golesl IS NOT NULL
            AND p1.golesv IS NOT NULL

        )
        ORDER BY partidos.dia ASC
    "), ['torneo_id_1' => $torneo_id, 'torneo_id_2' => $torneo_id]);
    }

    /**
     * Obtiene partidos donde los goles de un lado ($campo: 'golesl' o 'golesv')
     * son el máximo o mínimo según $tipo ('MAX' o 'MIN')
     */
    private function partidosPorGolesIndividuales(int $torneo_id, string $campo, string $tipo, $neutral=0)
    {
// Construcción dinámica del filtro según el valor de $neutral
        $neutralCondition = '';
        $neutralCondition2 = '';
        if ($neutral === 1) {
            $neutralCondition = 'AND partidos.neutral != 0';
            $neutralCondition2 = 'AND p1.neutral != 0';
        } elseif ($neutral === 2) {
            $neutralCondition = 'AND partidos.neutral = 0';
            $neutralCondition2 = 'AND p1.neutral = 0';
        }

        return DB::select(DB::raw("
        SELECT torneos.nombre AS nombreTorneo, torneos.year, fechas.numero, partidos.dia, e1.id AS equipol_id, e1.escudo AS fotoLocal, e1.nombre AS local, e2.id AS equipov_id,e2.escudo AS fotoVisitante, e2.nombre AS visitante, partidos.golesl,
partidos.golesv, partidos.penalesl, partidos.penalesv, partidos.id partido_id, e1.pais AS paisLocal, e2.pais AS paisVisitante
        FROM partidos
        INNER JOIN equipos e1 ON partidos.equipol_id = e1.id
		INNER JOIN equipos e2 ON partidos.equipov_id = e2.id
		INNER JOIN fechas ON partidos.fecha_id = fechas.id
		INNER JOIN grupos ON fechas.grupo_id = grupos.id
		INNER JOIN torneos ON grupos.torneo_id = torneos.id
        WHERE torneos.id = :torneo_id_1
        AND partidos.golesl IS NOT NULL
        AND partidos.golesv IS NOT NULL
        {$neutralCondition}
        AND partidos.{$campo} = (
            SELECT {$tipo}(p1.{$campo})
            FROM partidos p1
            INNER JOIN fechas f1 ON p1.fecha_id = f1.id
			INNER JOIN grupos g1 ON f1.grupo_id = g1.id
			INNER JOIN torneos t1 ON g1.torneo_id = t1.id
            WHERE t1.id = :torneo_id_2
            AND p1.golesl IS NOT NULL
            AND p1.golesv IS NOT NULL
            {$neutralCondition2}
        )
        ORDER BY partidos.dia ASC
    "), ['torneo_id_1' => $torneo_id, 'torneo_id_2' => $torneo_id]);
    }

    /**
     * Obtiene fechas con máximo o mínimo de goles según $tipo ('MAX' o 'MIN')
     * y el campo de goles para sumar ($campo, puede ser 'golesl + golesv', 'golesl', 'golesv')
     */
    private function fechasPorGoles(int $torneo_id, string $tipo, string $campo, int $neutral = 0)
    {
        // Construcción dinámica del filtro según el valor de $neutral
        $neutralCondition = '';
        if ($neutral === 1) {
            $neutralCondition = 'AND partidos.neutral != 0';
        } elseif ($neutral === 2) {
            $neutralCondition = 'AND partidos.neutral = 0';
        }

        return DB::select(DB::raw("
        SELECT t.nombreTorneo, t.year, t.numero, t.partidos, t.goles, t.promedio FROM (
            SELECT
                torneos.nombre AS nombreTorneo,
                torneos.year,
                fechas.numero,
                SUM({$campo}) AS goles,
                COUNT(*) AS partidos,
                (SUM({$campo}) / COUNT(*)) AS promedio
            FROM partidos
            INNER JOIN fechas ON partidos.fecha_id = fechas.id
            INNER JOIN grupos ON fechas.grupo_id = grupos.id
            INNER JOIN torneos ON grupos.torneo_id = torneos.id
            WHERE torneos.id = :torneo_id_1
            AND partidos.golesl IS NOT NULL
            AND partidos.golesv IS NOT NULL
            {$neutralCondition}
            GROUP BY torneos.nombre, torneos.year, fechas.numero
        ) AS t
        WHERE t.goles = (
            SELECT {$tipo}(t2.goles) FROM (
                SELECT SUM({$campo}) AS goles
                FROM partidos
                INNER JOIN fechas ON partidos.fecha_id = fechas.id
                INNER JOIN grupos ON fechas.grupo_id = grupos.id
                INNER JOIN torneos ON grupos.torneo_id = torneos.id
                WHERE torneos.id = :torneo_id_2
                AND partidos.golesl IS NOT NULL
                AND partidos.golesv IS NOT NULL
                {$neutralCondition}
                GROUP BY fechas.numero
            ) AS t2
        )
    "), [
            'torneo_id_1' => $torneo_id,
            'torneo_id_2' => $torneo_id,
        ]);
    }



    public function estadisticasOtras(Request $request)
    {
        //$torneo= $request->query('torneo');



        $estadisticas=array();



        // Helper para simplificar las consultas de partidos con condiciones dinámicas
        $getPartidosQuery = function ($condition) {
            return DB::select(DB::raw("
            SELECT
                torneos.nombre AS nombreTorneo, torneos.year,torneos.escudo AS escudoTorneo, fechas.numero, partidos.dia,
                e1.id AS equipol_id, e1.escudo AS fotoLocal, e1.nombre AS local,
                e2.id AS equipov_id, e2.escudo AS fotoVisitante, e2.nombre AS visitante,
                partidos.golesl, partidos.golesv, partidos.penalesl, partidos.penalesv,
                partidos.id AS partido_id,
                e1.pais AS paisLocal, e2.pais AS paisVisitante
            FROM partidos
            INNER JOIN equipos e1 ON partidos.equipol_id = e1.id
            INNER JOIN equipos e2 ON partidos.equipov_id = e2.id
            INNER JOIN fechas ON partidos.fecha_id = fechas.id
            INNER JOIN grupos ON fechas.grupo_id = grupos.id
            INNER JOIN torneos ON grupos.torneo_id = torneos.id
            WHERE $condition
            AND partidos.golesl IS NOT NULL
            AND partidos.golesv IS NOT NULL
            ORDER BY partidos.dia ASC
        "));
        };

        // Consultas de máximos
        $maxGoles = $getPartidosQuery('partidos.golesl + partidos.golesv = (SELECT MAX(golesl + golesv) FROM partidos)');
        $maxGolesLocales = $getPartidosQuery('partidos.neutral = 0 AND partidos.golesl = (SELECT MAX(golesl) FROM partidos WHERE partidos.neutral = 0)');
        $maxGolesVisitantes = $getPartidosQuery('partidos.neutral = 0 AND partidos.golesv = (SELECT MAX(golesv) FROM partidos WHERE partidos.neutral = 0)');
        $maxGolesNeutrales = $getPartidosQuery('partidos.neutral != 0 AND partidos.golesl + partidos.golesv = (SELECT MAX(golesl + golesv) FROM partidos WHERE partidos.neutral != 0)');

        //dd($maxGolesLocales);
        // Helper para fechas con más goles
        $getFechaMasGoles = function ($columna, $neutralCondition) {
            return DB::select(DB::raw("
            SELECT t.nombreTorneo, t.year, t.escudoTorneo, t.numero, t.partidos, t.goles, t.promedio
            FROM (
                SELECT torneos.nombre AS nombreTorneo, torneos.year, torneos.escudo as escudoTorneo, fechas.numero,
                       SUM(partidos.$columna) AS goles, COUNT(*) AS partidos,
                       (SUM(partidos.$columna)/COUNT(*)) AS promedio
                FROM partidos
                INNER JOIN equipos e1 ON partidos.equipol_id = e1.id
                INNER JOIN equipos e2 ON partidos.equipov_id = e2.id
                INNER JOIN fechas ON partidos.fecha_id = fechas.id
                INNER JOIN grupos ON fechas.grupo_id = grupos.id
                INNER JOIN torneos ON grupos.torneo_id = torneos.id
                WHERE $neutralCondition
                AND partidos.golesl IS NOT NULL
                AND partidos.golesv IS NOT NULL
                GROUP BY torneos.nombre, torneos.year, torneos.escudo, fechas.numero
            ) AS t
            WHERE t.goles = (
                SELECT MAX(t.goles)
                FROM (
                    SELECT torneos.nombre AS nombreTorneo, torneos.year, torneos.escudo as escudoTorneo, fechas.numero,
                           SUM(partidos.$columna) AS goles
                    FROM partidos
                    INNER JOIN equipos e1 ON partidos.equipol_id = e1.id
                    INNER JOIN equipos e2 ON partidos.equipov_id = e2.id
                    INNER JOIN fechas ON partidos.fecha_id = fechas.id
                    INNER JOIN grupos ON fechas.grupo_id = grupos.id
                    INNER JOIN torneos ON grupos.torneo_id = torneos.id
                    WHERE $neutralCondition
                    AND partidos.golesl IS NOT NULL
                    AND partidos.golesv IS NOT NULL
                    GROUP BY torneos.nombre, torneos.year, torneos.escudo, fechas.numero
                ) AS t
            )
        "));
        };

        $fechaMasGoles = $getFechaMasGoles('golesl + golesv','1=1');
        $fechaMasGolesLocales = $getFechaMasGoles('golesl','partidos.neutral = 0');
        $fechaMasGolesVisitantes = $getFechaMasGoles('golesv','partidos.neutral = 0');
        $fechaMasGolesNeutrales = $getFechaMasGoles('golesl + golesv','partidos.neutral != 0');

        // Helper para torneos con más goles
        $getTorneoMasGoles = function ($columna, $neutralCondition) {
            return DB::select(DB::raw("
        SELECT t.nombreTorneo, t.year, t.escudoTorneo, t.partidos, t.goles, t.promedio
        FROM (
            SELECT torneos.nombre AS nombreTorneo, torneos.year, torneos.escudo as escudoTorneo,
                   SUM(partidos.$columna) AS goles, COUNT(*) AS partidos,
                   (SUM(partidos.$columna)/COUNT(*)) AS promedio
            FROM partidos
            INNER JOIN equipos e1 ON partidos.equipol_id = e1.id
            INNER JOIN equipos e2 ON partidos.equipov_id = e2.id
            INNER JOIN fechas ON partidos.fecha_id = fechas.id
            INNER JOIN grupos ON fechas.grupo_id = grupos.id
            INNER JOIN torneos ON grupos.torneo_id = torneos.id
            WHERE $neutralCondition
            AND partidos.golesl IS NOT NULL
            AND partidos.golesv IS NOT NULL
            GROUP BY torneos.nombre, torneos.year, torneos.escudo
        ) AS t
        WHERE t.goles = (
            SELECT MAX(t.goles)
            FROM (
                SELECT torneos.nombre AS nombreTorneo, torneos.year, torneos.escudo as escudoTorneo,
                       SUM(partidos.$columna) AS goles
                FROM partidos
                INNER JOIN equipos e1 ON partidos.equipol_id = e1.id
                INNER JOIN equipos e2 ON partidos.equipov_id = e2.id
                INNER JOIN fechas ON partidos.fecha_id = fechas.id
                INNER JOIN grupos ON fechas.grupo_id = grupos.id
                INNER JOIN torneos ON grupos.torneo_id = torneos.id
                WHERE $neutralCondition
                AND partidos.golesl IS NOT NULL
                AND partidos.golesv IS NOT NULL
                GROUP BY torneos.nombre, torneos.year, torneos.escudo
            ) AS t
        )
    "));
        };


        $torneoMasGoles = $getTorneoMasGoles('golesl + golesv','1=1');
        $torneoMasGolesLocales = $getTorneoMasGoles('golesl', 'partidos.neutral = 0');
        $torneoMasGolesVisitantes = $getTorneoMasGoles('golesv', 'partidos.neutral = 0');
        $torneoMasGolesNeutrales = $getTorneoMasGoles('golesl + golesv','partidos.neutral != 0');

        $estadisticas['torneoMasGoles']=$torneoMasGoles;
        $estadisticas['torneoMasGolesLocales']=$torneoMasGolesLocales;
        $estadisticas['torneoMasGolesVisitantes']=$torneoMasGolesVisitantes;
        $estadisticas['torneoMasGolesNeutrales']=$torneoMasGolesNeutrales;
        $estadisticas['maxGoles']=$maxGoles;
        $estadisticas['maxGolesLocales']=$maxGolesLocales;
        $estadisticas['maxGolesVisitantes']=$maxGolesVisitantes;
        $estadisticas['maxGolesNeutrales']=$maxGolesNeutrales;
        $estadisticas['fechaMasGoles']=$fechaMasGoles;
        $estadisticas['fechaMasGolesLocales']=$fechaMasGolesLocales;
        $estadisticas['fechaMasGolesVisitantes']=$fechaMasGolesVisitantes;
        $estadisticas['fechaMasGolesNeutrales']=$fechaMasGolesNeutrales;

        $estadisticasResumen = [];

        $todosTorneos = DB::select(DB::raw("
    SELECT t.id, t.nombre AS nombreTorneo, t.year, t.escudo AS escudoTorneo
    FROM torneos t
    ORDER BY t.year DESC
"));

        foreach($todosTorneos as $torneo) {
            $torneoId = $torneo->id;

            // Totales (partidos y goles)
            $totales = DB::selectOne(DB::raw("
        SELECT COUNT(*) AS partidos,
               SUM(p.golesl + p.golesv) AS goles,
               SUM(p.golesl) AS goles_local,
               (SUM(p.golesl + p.golesv) * 1.0 / COUNT(*)) AS promedio_goles
        FROM partidos p
        INNER JOIN fechas f ON p.fecha_id = f.id
        INNER JOIN grupos g ON f.grupo_id = g.id
        WHERE g.torneo_id = :torneoId
    "), ['torneoId' => $torneoId]);

            // Máximo de goles
            $maxGoles = DB::selectOne(DB::raw("
        SELECT MAX(p.golesl + p.golesv) AS max_goles
        FROM partidos p
        INNER JOIN fechas f ON p.fecha_id = f.id
        INNER JOIN grupos g ON f.grupo_id = g.id
        WHERE g.torneo_id = :torneoId
    "), ['torneoId' => $torneoId]);

            // Empates
            $empates = DB::selectOne(DB::raw("
        SELECT COUNT(*) AS empates
        FROM partidos p
        INNER JOIN fechas f ON p.fecha_id = f.id
        INNER JOIN grupos g ON f.grupo_id = g.id
        WHERE g.torneo_id = :torneoId
          AND p.golesl = p.golesv
    "), ['torneoId' => $torneoId]);

            // Goles visitante
            $golesVisitante = DB::selectOne(DB::raw("
        SELECT SUM(p.golesv) AS goles_visitante
        FROM partidos p
        INNER JOIN fechas f ON p.fecha_id = f.id
        INNER JOIN grupos g ON f.grupo_id = g.id
        WHERE g.torneo_id = :torneoId
    "), ['torneoId' => $torneoId]);

            // Tarjetas
            $tarjetas = DB::selectOne(DB::raw("
        SELECT
            SUM(CASE WHEN t.tipo = 'Amarilla' THEN 1 ELSE 0 END) AS amarillas,
            SUM(CASE WHEN t.tipo = 'Roja' THEN 1 ELSE 0 END) AS rojas
        FROM tarjetas t
        INNER JOIN partidos p ON t.partido_id = p.id
        INNER JOIN fechas f ON p.fecha_id = f.id
        INNER JOIN grupos g ON f.grupo_id = g.id
        WHERE g.torneo_id = :torneoId
    "), ['torneoId' => $torneoId]);



            $estadisticasResumen[] = [
                'nombreTorneo' => $torneo->nombreTorneo,
                'year' => $torneo->year,
                'escudoTorneo' => $torneo->escudoTorneo,
                'partidos' => $totales->partidos ?? 0,
                'goles' => $totales->goles ?? 0,
                'goles_local' => $totales->goles_local ?? 0,
                'promedio_goles' => $totales->promedio_goles ?? 0,
                'max_goles' => $maxGoles->max_goles ?? 0,
                'empates' => $empates->empates ?? 0,
                'goles_visitante' => $golesVisitante->goles_visitante ?? 0,
                'amarillas' => $tarjetas->amarillas ?? 0,
                'rojas' => $tarjetas->rojas ?? 0,
            ];
        }

        $estadisticasResumen = collect($estadisticasResumen)->map(function($item) {
            return (object) $item;
        })->all();





        return view('torneos.estadisticasOtras', compact('estadisticas','estadisticasResumen'));
    }


    public function tecnicos(Request $request)
    {

        $order= ($request->query('order'))?$request->query('order'):'puntaje';
        $tipoOrder= ($request->query('tipoOrder'))?$request->query('tipoOrder'):'DESC';
        $actuales= ($request->query('actuales'))?1:0;
        $campeones= ($request->query('campeones'))?1:0;
        if($request->query('torneoId')) {
            $torneoId = $request->query('torneoId');

        }
        else{
            $torneo=Torneo::orderBy('year','DESC')->orderBy('id','DESC')->first();
            $torneoId = $torneo->id;
        }
        if ($request->has('buscarpor')){
            $nombre = $request->get('buscarpor');

            $request->session()->put('nombre_filtro_jugador', $request->get('buscarpor'));

        }
        else{
            $nombre = $request->session()->get('nombre_filtro_jugador');

        }
        $nombreFiltro='';
        $nombreFiltro2='';
        $nombreFiltro3='';
        if ($nombre) {
            $nombreFiltro = " AND (personas.apellido LIKE '%$nombre%' OR personas.nombre LIKE '%$nombre%') ";
            $nombreFiltro2 = " AND (P2.apellido LIKE '%$nombre%' OR P2.nombre LIKE '%$nombre%') ";
            $nombreFiltro3 = " AND (P3.apellido LIKE '%$nombre%' OR P3.nombre LIKE '%$nombre%') ";
        }
        $torneos=Torneo::orderBy('year','DESC')->get();

        $sql = 'SELECT tecnico, fotoTecnico, nacionalidadTecnico, tecnico_id,
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
    ) prom, "" escudo, "" AS jugando, "" AS titulos
from (
       select  DISTINCT personas.name as tecnico, CONCAT(personas.apellido,\', \',personas.nombre) completo, personas.foto fotoTecnico, personas.nacionalidad nacionalidadTecnico, tecnicos.id tecnico_id, golesl, golesv, equipos.escudo foto, fechas.id fecha_id
		 from partidos
		 INNER JOIN equipos ON partidos.equipol_id = equipos.id
		 INNER JOIN plantillas ON plantillas.equipo_id = equipos.id
		 INNER JOIN fechas ON partidos.fecha_id = fechas.id
		 INNER JOIN grupos ON fechas.grupo_id = grupos.id
		 INNER JOIN partido_tecnicos ON partidos.id = partido_tecnicos.partido_id AND equipos.id = partido_tecnicos.equipo_id
		 INNER JOIN tecnicos ON tecnicos.id = partido_tecnicos.tecnico_id
		 INNER JOIN personas ON personas.id = tecnicos.persona_id
		 WHERE golesl is not null AND golesv is not null'.$nombreFiltro;
        $sql .=($actuales)?' AND EXISTS (
        SELECT PT1.id
FROM partido_tecnicos PT1
INNER JOIN tecnicos T1 ON PT1.tecnico_id = T1.id
INNER JOIN personas P2 ON T1.persona_id = P2.id
INNER JOIN partidos ON PT1.partido_id = partidos.id
INNER JOIN fechas F1 ON partidos.fecha_id = F1.id
INNER JOIN grupos G1 ON G1.id = F1.grupo_id

WHERE G1.torneo_id = '.$torneoId.' AND T1.id = tecnicos.id'.$nombreFiltro2.'

        )':'';
        $sql .=($campeones)?' AND EXISTS (
        SELECT PT2.id
FROM partido_tecnicos PT2
INNER JOIN tecnicos T2 ON PT2.tecnico_id = T2.id
INNER JOIN personas P3 ON T2.persona_id = P3.id
INNER JOIN partidos par ON PT2.partido_id = par.id
INNER JOIN fechas F2 ON par.fecha_id = F2.id
INNER JOIN grupos G2 ON G2.id = F2.grupo_id
INNER JOIN posicion_torneos ON posicion_torneos.torneo_id = G2.torneo_id AND posicion_torneos.equipo_id = PT2.equipo_id AND posicion_torneos.posicion = 1 WHERE T2.id = tecnicos.id'.$nombreFiltro3.'

        )':'';

     $sql .=' union all
       select DISTINCT personas.name as tecnico, CONCAT(personas.apellido,\', \',personas.nombre) completo, personas.foto fotoTecnico, personas.nacionalidad nacionalidadTecnico, tecnicos.id tecnico_id, golesv, golesl, equipos.escudo foto, fechas.id fecha_id
		 from partidos
		 INNER JOIN equipos ON partidos.equipov_id = equipos.id
		 INNER JOIN plantillas ON plantillas.equipo_id = equipos.id
		 INNER JOIN fechas ON partidos.fecha_id = fechas.id
		 INNER JOIN grupos ON fechas.grupo_id = grupos.id
		 INNER JOIN partido_tecnicos ON partidos.id = partido_tecnicos.partido_id AND equipos.id = partido_tecnicos.equipo_id
		 INNER JOIN tecnicos ON tecnicos.id = partido_tecnicos.tecnico_id
		 INNER JOIN personas ON personas.id = tecnicos.persona_id
		 WHERE golesl is not null AND golesv is not null'.$nombreFiltro;
        $sql .=($actuales)?' AND EXISTS (
        SELECT PT1.id
FROM partido_tecnicos PT1
INNER JOIN tecnicos T1 ON PT1.tecnico_id = T1.id
INNER JOIN personas P2 ON T1.persona_id = P2.id
INNER JOIN partidos ON PT1.partido_id = partidos.id
INNER JOIN fechas F1 ON partidos.fecha_id = F1.id
INNER JOIN grupos G1 ON G1.id = F1.grupo_id

WHERE G1.torneo_id = '.$torneoId.' AND T1.id = tecnicos.id'.$nombreFiltro2.'

        )':'';
        $sql .=($campeones)?' AND EXISTS (
        SELECT PT2.id
FROM partido_tecnicos PT2
INNER JOIN tecnicos T2 ON PT2.tecnico_id = T2.id
INNER JOIN personas P3 ON T2.persona_id = P3.id
INNER JOIN partidos par ON PT2.partido_id = par.id
INNER JOIN fechas F2 ON par.fecha_id = F2.id
INNER JOIN grupos G2 ON G2.id = F2.grupo_id
INNER JOIN posicion_torneos ON posicion_torneos.torneo_id = G2.torneo_id AND posicion_torneos.equipo_id = PT2.equipo_id AND posicion_torneos.posicion = 1 WHERE T2.id = tecnicos.id'.$nombreFiltro3.'

        )':'';
$sql .=' ) a
group by tecnico, fotoTecnico, nacionalidadTecnico, tecnico_id



        ORDER BY '.$order.' '.$tipoOrder.', jugados DESC, tecnico ASC';

//echo $sql;

        $goleadores = DB::select(DB::raw($sql));



        $page = $request->query('page', 1);

        $paginate = 15;

        $offSet = ($page * $paginate) - $paginate;

        $itemsForCurrentPage = array_slice($goleadores, $offSet, $paginate, true);



        $goleadores = new \Illuminate\Pagination\LengthAwarePaginator($itemsForCurrentPage, count($goleadores), $paginate, $page);


        foreach ($goleadores as $goleador){
            $titulosTecnicoCopa=0;
            $titulosTecnicoLiga=0;
            $titulosTecnicoInternacional=0;
            $titulosTecnicoCopaEquipo=array();
            $titulosTecnicoLigaEquipo=array();
            $titulosTecnicoInternacionalEquipo=array();

            $sqlTorneos = 'SELECT DISTINCT grupos.torneo_id, partido_tecnicos.equipo_id
FROM partido_tecnicos
INNER JOIN tecnicos ON partido_tecnicos.tecnico_id = tecnicos.id
INNER JOIN personas ON tecnicos.persona_id = personas.id
INNER JOIN partidos ON partido_tecnicos.partido_id = partidos.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id
INNER JOIN equipos ON partido_tecnicos.equipo_id = equipos.id
WHERE tecnicos.id = '.$goleador->tecnico_id;

            $torneosJugados = DB::select(DB::raw($sqlTorneos));
            foreach ($torneosJugados as $tj){
                $grupos = Grupo::where('torneo_id', '=',$tj->torneo_id)->get();
                $arrgrupos='';
                foreach ($grupos as $grupo){
                    $arrgrupos .=$grupo->id.',';
                }
                $arrgrupos = substr($arrgrupos, 0, -1);//quito última coma

                $fechas = Fecha::wherein('grupo_id',explode(',', $arrgrupos))->get();

                $arrfechas='';
                foreach ($fechas as $fecha){
                    $arrfechas .=$fecha->id.',';
                }
                $arrfechas = substr($arrfechas, 0, -1);//quito última coma

                $partidos = Partido::wherein('fecha_id',explode(',', $arrfechas))->get();

                $arrpartidos='';
                foreach ($partidos as $partido){
                    $arrpartidos .=$partido->id.',';
                }
                $arrpartidos = substr($arrpartidos, 0, -1);//quito última coma

                $posicionTorneo = PosicionTorneo::where('torneo_id', '=',$tj->torneo_id)->where('equipo_id', '=',$tj->equipo_id)->where('posicion', '=',1)->first();

                if(!empty($posicionTorneo)){
                    //if ($posicionTorneo->posicion == 1){
                    $ultimoPartido = Partido::whereIn('fecha_id', explode(',', $arrfechas))
                        ->where(function ($query) use ($posicionTorneo) {
                            $query->where('equipol_id', $posicionTorneo->equipo_id)
                                ->orWhere('equipov_id', $posicionTorneo->equipo_id);
                        })
                        ->orderBy('dia', 'DESC')
                        ->first();

                    $partidoTecnico = PartidoTecnico::where('partido_id','=',"$ultimoPartido->id")->where('equipo_id','=',$posicionTorneo->equipo_id)->where('tecnico_id','=',$goleador->tecnico_id)->first();
                    //print_r($partidoTecnico);
                    if(!empty($partidoTecnico)) {
                        $torneo=Torneo::findOrFail($tj->torneo_id);
                        //if ((stripos($torneo->nombre, 'Copa') !== false)||(stripos($torneo->nombre, 'Trofeo') !== false)) {
                        if ($torneo->ambito == 'Nacional') {
                            if ($torneo->tipo == 'Copa') {
                                $titulosTecnicoCopa++;
                                if (!isset($titulosTecnicoCopaEquipo[$posicionTorneo->equipo_id])) {
                                    $titulosTecnicoCopaEquipo[$posicionTorneo->equipo_id] = 1;
                                } else {
                                    $titulosTecnicoCopaEquipo[$posicionTorneo->equipo_id]++;
                                }
                            } else {
                                $titulosTecnicoLiga++;
                                if (!isset($titulosTecnicoLigaEquipo[$posicionTorneo->equipo_id])) {
                                    $titulosTecnicoLigaEquipo[$posicionTorneo->equipo_id] = 1;
                                } else {
                                    $titulosTecnicoLigaEquipo[$posicionTorneo->equipo_id]++;
                                }
                            }
                        }else {
                            $titulosTecnicoInternacional++;
                            if (!isset($titulosTecnicoInternacionalEquipo[$posicionTorneo->equipo_id])) {
                                $titulosTecnicoInternacionalEquipo[$posicionTorneo->equipo_id] = 1;
                            } else {
                                $titulosTecnicoInternacionalEquipo[$posicionTorneo->equipo_id]++;
                            }
                        }
                    }
                    //}
                }

            }

            if (($titulosTecnicoCopa+$titulosTecnicoLiga+$titulosTecnicoInternacional)==0){
                $goleador->titulos='';
            }
            else{
                $ligas=($titulosTecnicoLiga)?$titulosTecnicoLiga.' Ligas':'';
                $copas=($titulosTecnicoCopa)?$titulosTecnicoCopa.' Copas':'';
                $internacionales=($titulosTecnicoInternacional)?$titulosTecnicoInternacional.' Internacionales':'';

                $goleador->titulos=$titulosTecnicoCopa+$titulosTecnicoLiga+$titulosTecnicoInternacional. ' ('.$ligas.' '.$copas.' '.$internacionales.')';
            }

            //print_r($titulosTecnicoLigaEquipo);

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
                $tl=0;
                $tc=0;
                $ti=0;
                if (isset($titulosTecnicoLigaEquipo[$escudo->equipo_id])){
                    $tl=$titulosTecnicoLigaEquipo[$escudo->equipo_id];
                }
                if (isset($titulosTecnicoCopaEquipo[$escudo->equipo_id])){
                    $tc=$titulosTecnicoCopaEquipo[$escudo->equipo_id];
                }
                if (isset($titulosTecnicoInternacionalEquipo[$escudo->equipo_id])){
                    $ti=$titulosTecnicoInternacionalEquipo[$escudo->equipo_id];
                }
                if ($tc+$tl+$ti>0){
                    $ligas=($tl)?$tl.' Ligas':'';
                    $copas=($tc)?$tc.' Copas':'';
                    $internacionales=($ti)?$ti.' Internacionales':'';
                     $titulos=$tc+$tl+$ti. ' ('.$ligas.' '.$copas.' '.$internacionales.')';
                    $goleador->escudo .= $escudo->escudo.'_'.$escudo->equipo_id.'_'.$escudo->puntaje.'_'.$escudo->porcentaje.'_'.$titulos.',';
                }
                else{
                    $goleador->escudo .= $escudo->escudo.'_'.$escudo->equipo_id.'_'.$escudo->puntaje.'_'.$escudo->porcentaje.',';
                }


            }


        }

        $goleadores->setPath(route('torneos.tecnicos',array('order'=>$order,'tipoOrder'=>$tipoOrder,'actuales'=>$actuales,'campeones'=>$campeones,'torneoId'=>$torneoId)));


        $i=$offSet+1;


        return view('torneos.tecnicos', compact('goleadores','i','order','tipoOrder','actuales','torneoId','campeones'));
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
            $torneo=Torneo::orderBy('year','DESC')->orderBy('id','DESC')->first();
            $torneoId = $torneo->id;
        }

        if ($request->has('buscarpor')){
            $nombre = $request->get('buscarpor');

            $request->session()->put('nombre_filtro_jugador', $request->get('buscarpor'));

        }
        else{
            $nombre = $request->session()->get('nombre_filtro_jugador');

        }
        $nombreFiltro='';
        $nombreFiltro2='';
        if ($nombre) {
            $nombreFiltro = " AND (personas.apellido LIKE '%$nombre%' OR personas.nombre LIKE '%$nombre%') ";
            $nombreFiltro2 = " AND (P2.apellido LIKE '%$nombre%' OR P2.nombre LIKE '%$nombre%') ";
        }

        $torneos=Torneo::orderBy('year','DESC')->get();

        $sql = 'SELECT jugadors.id, personas.name as jugador,CONCAT(personas.apellido,\', \',personas.nombre) completo, COUNT(jugadors.id) as jugados,
sum(case when alineacions.equipo_id=partidos.equipol_id then partidos.golesv else partidos.golesl END) AS recibidos,
sum(case when alineacions.equipo_id=partidos.equipol_id and partidos.golesv = 0 then 1 else CASE when alineacions.equipo_id=partidos.equipov_id and partidos.golesl = 0 THEN 1 ELSE 0 END END) AS invictas, "" escudo, personas.foto, personas.nacionalidad, "" as jugando
FROM alineacions
INNER JOIN jugadors ON alineacions.jugador_id = jugadors.id AND jugadors.tipoJugador = \'Arquero\'
INNER JOIN personas ON jugadors.persona_id = personas.id
INNER JOIN partidos ON alineacions.partido_id = partidos.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id
LEFT JOIN cambios ON alineacions.partido_id = cambios.partido_id AND cambios.jugador_id = jugadors.id
WHERE  (alineacions.tipo = \'Titular\' OR cambios.tipo = \'Entra\')'.$nombreFiltro;
        $sql .=($actuales)?' AND EXISTS (
SELECT DISTINCT J1.id
FROM alineacions
INNER JOIN jugadors J1 ON alineacions.jugador_id = J1.id
INNER JOIN personas P2 ON J1.persona_id = P2.id
INNER JOIN partidos P1 ON alineacions.partido_id = P1.id
INNER JOIN fechas F1 ON P1.fecha_id = F1.id
INNER JOIN grupos G1 ON G1.id = F1.grupo_id

WHERE G1.torneo_id = '.$torneoId.' AND J1.id = jugadors.id'. $nombreFiltro2.'

)':'';

$sql .=' GROUP BY jugadors.id, jugador, foto, nacionalidad
ORDER BY '.$order.' '.$tipoOrder.', jugados DESC, recibidos ASC';
        $arqueros = DB::select(DB::raw($sql));

        if($request->query('torneoId')) {
            $torneoId = $request->query('torneoId');

        }
        else{
            $torneo=Torneo::orderBy('year','DESC')->orderBy('id','DESC')->first();
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
LEFT JOIN cambios ON alineacions.partido_id = cambios.partido_id AND cambios.jugador_id = jugadors.id
WHERE (alineacions.tipo = \'Titular\' OR cambios.tipo = \'Entra\') AND alineacions.jugador_id = '.$arquero->id.'
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

        if ($request->has('buscarpor')){
            $nombre = $request->get('buscarpor');

            $request->session()->put('nombre_filtro_jugador', $request->get('buscarpor'));

        }
        else{
            $nombre = $request->session()->get('nombre_filtro_jugador');

        }
        $nombreFiltro='';
        $nombreFiltro2='';
        if ($nombre) {
            $nombreFiltro = " AND (personas.apellido LIKE '%$nombre%' OR personas.nombre LIKE '%$nombre%') ";
            $nombreFiltro2 = " AND (P2.apellido LIKE '%$nombre%' OR P2.nombre LIKE '%$nombre%') ";
        }

        if($request->query('torneoId')) {
            $torneoId = $request->query('torneoId');

        }
        else{
            $torneo=Torneo::orderBy('year','DESC')->orderBy('id','DESC')->first();
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

        $sql = 'SELECT jugador_id, "" escudo, foto, nacionalidad, jugador,
       sum(jugados) jugados,

       sum(goles) goles,
       sum(rojas) rojas,
       sum(amarillas) amarillas,
       sum(recibidos) recibidos,
       sum(invictas) invictas, sum(titulos) titulos

from

(SELECT jugadors.id AS jugador_id, personas.foto, personas.nacionalidad,"0" as jugados, personas.name as jugador, CONCAT(personas.apellido,\', \',personas.nombre) completo, "1" as goles, "0" as  amarillas
, "0" as  rojas, "0" as  recibidos, "0" as  invictas, "0" AS jugando, "0" AS titulos
FROM gols
INNER JOIN jugadors ON gols.jugador_id = jugadors.id
INNER JOIN personas ON jugadors.persona_id = personas.id
INNER JOIN partidos ON gols.partido_id = partidos.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id

WHERE gols.tipo <> \'En contra\''. $nombreFiltro;

        $sql .=($actuales)?' AND EXISTS (
SELECT DISTINCT J1.id
FROM alineacions
INNER JOIN jugadors J1 ON alineacions.jugador_id = J1.id
INNER JOIN personas P2 ON J1.persona_id = P2.id
INNER JOIN partidos P1 ON alineacions.partido_id = P1.id
INNER JOIN fechas F1 ON P1.fecha_id = F1.id
INNER JOIN grupos G1 ON G1.id = F1.grupo_id

WHERE G1.torneo_id = '.$torneoId.' AND J1.id = jugadors.id'. $nombreFiltro2.'

)':'';

 $sql .=' UNION ALL
 SELECT jugadors.id AS jugador_id, personas.foto, personas.nacionalidad,"0" as jugados, personas.name as jugador, CONCAT(personas.apellido,\', \',personas.nombre) completo, "0" AS goles, ( case when tipo=\'Amarilla\' then 1 else NULL end) as  amarillas
, ( case when tipo=\'Roja\' or tipo=\'Doble Amarilla\' then 1 else NULL end) as  rojas, "0" as  recibidos, "0" as  invictas, "0" AS jugando, "0" AS titulos
FROM tarjetas
INNER JOIN jugadors ON tarjetas.jugador_id = jugadors.id
INNER JOIN personas ON jugadors.persona_id = personas.id
LEFT JOIN partidos ON tarjetas.partido_id = partidos.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id'. $nombreFiltro;

        $sql .=($actuales)?' WHERE EXISTS (
SELECT DISTINCT J1.id
FROM alineacions
INNER JOIN jugadors J1 ON alineacions.jugador_id = J1.id
INNER JOIN personas P2 ON J1.persona_id = P2.id
INNER JOIN partidos P1 ON alineacions.partido_id = P1.id
INNER JOIN fechas F1 ON P1.fecha_id = F1.id
INNER JOIN grupos G1 ON G1.id = F1.grupo_id

WHERE G1.torneo_id = '.$torneoId.' AND J1.id = jugadors.id'. $nombreFiltro2.'

)':'';


$sql .=' UNION ALL
 SELECT jugadors.id AS jugador_id, personas.foto, personas.nacionalidad,"1" as jugados, personas.name as jugador, CONCAT(personas.apellido,\', \',personas.nombre) completo, "0" AS goles, "0" as  amarillas
, "0" as  rojas, (case when alineacions.equipo_id=partidos.equipol_id then partidos.golesv else partidos.golesl END) AS recibidos,
(case when alineacions.equipo_id=partidos.equipol_id and partidos.golesv = 0 then 1 else CASE when alineacions.equipo_id=partidos.equipov_id and partidos.golesl = 0 THEN 1 ELSE 0 END END) AS invictas, "0" AS jugando, "0" AS titulos
FROM alineacions
INNER JOIN jugadors ON alineacions.jugador_id = jugadors.id AND jugadors.tipoJugador = \'Arquero\'
INNER JOIN personas ON jugadors.persona_id = personas.id
INNER JOIN partidos ON alineacions.partido_id = partidos.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id

WHERE  alineacions.tipo = \'Titular\''. $nombreFiltro;
        $sql .=($actuales)?' AND EXISTS (
SELECT DISTINCT J1.id
FROM alineacions
INNER JOIN jugadors J1 ON alineacions.jugador_id = J1.id
INNER JOIN personas P2 ON J1.persona_id = P2.id
INNER JOIN partidos P1 ON alineacions.partido_id = P1.id
INNER JOIN fechas F1 ON P1.fecha_id = F1.id
INNER JOIN grupos G1 ON G1.id = F1.grupo_id

WHERE G1.torneo_id = '.$torneoId.' AND J1.id = jugadors.id'. $nombreFiltro2.'

)':'';

$sql .= ' UNION ALL
 SELECT jugadors.id AS jugador_id, personas.foto, personas.nacionalidad,"1" as jugados, personas.name as jugador, CONCAT(personas.apellido,\', \',personas.nombre) completo, "0" AS goles, "0" as  amarillas
, "0" as  rojas, "0" AS recibidos,
"0" AS invictas, "0" AS jugando, "0" AS titulos
FROM alineacions
INNER JOIN jugadors ON alineacions.jugador_id = jugadors.id AND jugadors.tipoJugador != \'Arquero\'
INNER JOIN personas ON jugadors.persona_id = personas.id
INNER JOIN partidos ON alineacions.partido_id = partidos.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id
LEFT JOIN cambios ON alineacions.partido_id = cambios.partido_id AND cambios.jugador_id = jugadors.id
WHERE  (alineacions.tipo = \'Titular\' OR cambios.tipo = \'Entra\')'. $nombreFiltro;
        $sql .=($actuales)?' AND EXISTS (
SELECT DISTINCT J1.id
FROM alineacions
INNER JOIN jugadors J1 ON alineacions.jugador_id = J1.id
INNER JOIN personas P2 ON J1.persona_id = P2.id
INNER JOIN partidos P1 ON alineacions.partido_id = P1.id
INNER JOIN fechas F1 ON P1.fecha_id = F1.id
INNER JOIN grupos G1 ON G1.id = F1.grupo_id

WHERE G1.torneo_id = '.$torneoId.' AND J1.id = jugadors.id'. $nombreFiltro2.'

)':'';

$sql .=' UNION ALL
 SELECT jugadors.id AS jugador_id, personas.foto, personas.nacionalidad,"1" as jugados, personas.name as jugador, CONCAT(personas.apellido,\', \',personas.nombre) completo, "0" AS goles, "0" as  amarillas
, "0" as  rojas, "0" AS recibidos,
"0" AS invictas, "0" AS jugando, "0" AS titulos
FROM alineacions
INNER JOIN jugadors ON alineacions.jugador_id = jugadors.id AND jugadors.tipoJugador = \'Arquero\'
INNER JOIN personas ON jugadors.persona_id = personas.id
INNER JOIN partidos ON alineacions.partido_id = partidos.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id
LEFT JOIN cambios ON alineacions.partido_id = cambios.partido_id AND cambios.jugador_id = jugadors.id
WHERE  (cambios.tipo = \'Entra\')'. $nombreFiltro;
        $sql .=($actuales)?' AND EXISTS (
SELECT DISTINCT J1.id
FROM alineacions
INNER JOIN jugadors J1 ON alineacions.jugador_id = J1.id
INNER JOIN personas P2 ON J1.persona_id = P2.id
INNER JOIN partidos P1 ON alineacions.partido_id = P1.id
INNER JOIN fechas F1 ON P1.fecha_id = F1.id
INNER JOIN grupos G1 ON G1.id = F1.grupo_id

WHERE G1.torneo_id = '.$torneoId.' AND J1.id = jugadors.id'. $nombreFiltro2.'

)':'';
        $sql.='UNION ALL
SELECT jugadors.id AS jugador_id, personas.foto, personas.nacionalidad,"0" as jugados, personas.name as jugador, CONCAT(personas.apellido,\', \',personas.nombre) completo, "0" AS goles, "0" as amarillas , "0" as rojas, "0" AS recibidos,
"0" AS invictas, "0" AS jugando, count(DISTINCT posicion_torneos.id) AS titulos
FROM plantilla_jugadors
INNER JOIN jugadors ON plantilla_jugadors.jugador_id = jugadors.id
INNER JOIN personas ON jugadors.persona_id = personas.id
INNER JOIN plantillas ON plantilla_jugadors.plantilla_id = plantillas.id

INNER JOIN grupos ON grupos.id = plantillas.grupo_id
INNER JOIN posicion_torneos ON posicion_torneos.torneo_id=grupos.torneo_id AND posicion_torneos.equipo_id = plantillas.equipo_id AND posicion_torneos.posicion=1
WHERE 1=1 ' . $nombreFiltro;
        $sql .=($actuales)?' AND EXISTS (
SELECT DISTINCT J1.id
FROM alineacions
INNER JOIN jugadors J1 ON alineacions.jugador_id = J1.id
INNER JOIN personas P2 ON J1.persona_id = P2.id
INNER JOIN partidos P1 ON alineacions.partido_id = P1.id
INNER JOIN fechas F1 ON P1.fecha_id = F1.id
INNER JOIN grupos G1 ON G1.id = F1.grupo_id

WHERE G1.torneo_id = '.$torneoId.' AND J1.id = jugadors.id'. $nombreFiltro2.'

)':'';
        $sql .=' GROUP BY jugadors.id,personas.foto,personas.apellido,personas.nombre,personas.nacionalidad';
$sql .=' ) a

group by jugador_id,jugador, foto, nacionalidad
ORDER BY '.$order.' '.$tipoOrder.', jugador ASC';

        $jugadores = DB::select(DB::raw($sql));
        //echo $sql;


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


        $resto=0;
        $torneo_id= $request->query('torneoId');
        $torneo=Torneo::findOrFail($torneo_id);

        $grupos = Grupo::where('torneo_id', '=',$torneo_id)->get();


        $arrgrupos='';
        foreach ($grupos as $grupo2){
            $arrgrupos .=$grupo2->id.',';
        }

        $equipos = Plantilla::with('equipo')->wherein('grupo_id',explode(',', $arrgrupos))->get()->pluck('equipo.nombre', 'equipo_id')->prepend('','');

        $posicionTorneo = PosicionTorneo::with('equipo')->where('torneo_id', '=',$torneo_id)->orderBy('posicion', 'asc')->get();
        //dd($posicionTorneo);
        $posiciones=array();
        $posiciones2=array();
        $arrPosiciones=array();
        if ($posicionTorneo->count() > 0) {
            for ($i = 0; $i < count($posicionTorneo); $i++) {
                $arrPosiciones[$i]=array($posicionTorneo[$i]->equipo_id,$posicionTorneo[$i]->equipo->escudo);
            }
        }
        else{

            $fecha=Fecha::wherein('grupo_id',explode(',', $arrgrupos))->where('numero','=','Final')->first();
            if(!empty($fecha)){
                //$resto=1;
                $partidos=Partido::where('fecha_id','=',"$fecha->id")->get();
                //dd($partidos);
                foreach ($partidos as $partido){
                    if ($partido->golesl>$partido->golesv){
                        $equipo=Equipo::findOrFail($partido->equipol_id);
                        $data = [
                            'equipo_id' => $partido->equipol_id,
                            'foto' => $equipo->escudo
                        ];
                        $posiciones[]=(object) $data;
                        $equipo=Equipo::findOrFail($partido->equipov_id);
                        $data = [
                            'equipo_id' => $partido->equipov_id,
                            'foto' => $equipo->escudo
                        ];
                        $posiciones[]=(object) $data;
                    }
                    elseif ($partido->golesl<$partido->golesv){
                        $equipo=Equipo::findOrFail($partido->equipov_id);
                        $data = [
                            'equipo_id' => $partido->equipov_id,
                            'foto' => $equipo->escudo
                        ];
                        $posiciones[]=(object) $data;
                        $equipo=Equipo::findOrFail($partido->equipol_id);
                        $data = [
                            'equipo_id' => $partido->equipol_id,
                            'foto' => $equipo->escudo
                        ];
                        $posiciones[]=(object) $data;
                    }
                    elseif ($partido->penalesl>$partido->penalesv){
                        $equipo=Equipo::findOrFail($partido->equipol_id);
                        $data = [
                            'equipo_id' => $partido->equipol_id,
                            'foto' => $equipo->escudo
                        ];
                        $posiciones[]=(object) $data;
                        $equipo=Equipo::findOrFail($partido->equipov_id);
                        $data = [
                            'equipo_id' => $partido->equipov_id,
                            'foto' => $equipo->escudo
                        ];
                        $posiciones[]=(object) $data;
                    }
                    else{
                        $equipo=Equipo::findOrFail($partido->equipov_id);
                        $data = [
                            'equipo_id' => $partido->equipov_id,
                            'foto' => $equipo->escudo
                        ];
                        $posiciones[]=(object) $data;
                        $equipo=Equipo::findOrFail($partido->equipol_id);
                        $data = [
                            'equipo_id' => $partido->equipol_id,
                            'foto' => $equipo->escudo
                        ];
                        $posiciones[]=(object) $data;
                    }
                }
            }

                $posiciones2 = DB::select(
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
		 WHERE golesl is not null AND golesv is not null AND grupos.torneo_id = ?
     union all
       select DISTINCT equipos.nombre equipo, golesv, golesl, equipos.escudo foto, fechas.id fecha_id, equipos.id equipo_id
		 from partidos
		 INNER JOIN equipos ON partidos.equipov_id = equipos.id
		 INNER JOIN plantillas ON plantillas.equipo_id = equipos.id
		 INNER JOIN fechas ON partidos.fecha_id = fechas.id
		 INNER JOIN grupos ON fechas.grupo_id = grupos.id
		 WHERE golesl is not null AND golesv is not null AND grupos.torneo_id = ?
) a
group by equipo, foto, equipo_id

order by  jugados desc, puntaje desc, promedio DESC, diferencia DESC, golesl DESC, equipo ASC",
                    [
                        $torneo_id,
                        $torneo_id,
                    ]
                );


            //dd($posiciones2);
            for ($i = 0; $i < count($posiciones); $i++) {
                $arrPosiciones[$i]=array($posiciones[$i]->equipo_id,$posiciones[$i]->foto);
            }
            if (count($posiciones2)>0){
                for ($i = count($posiciones); $i < count($posiciones2); $i++) {
                    $arrPosiciones[$i]=array($posiciones2[$i]->equipo_id,$posiciones2[$i]->foto);
                }
            }


            //dd($arrPosiciones);
        }




        return view('torneos.finalizar', compact('torneo','equipos','arrPosiciones'));
    }


    public function guardarFinalizar(Request $request)
    {
        //


        //dd($request);
        //$this->validate($request,[ 'equipo_id'=>'required',  'grupo_id'=>'required']);
        DB::beginTransaction();

            PosicionTorneo::where('torneo_id',"$request->torneo_id")->delete();


        $ok=1;

        try {

            if($request->equipo)
            {
                foreach($request->equipo as $item=>$v){

                    $data2=array(
                        'torneo_id'=>$request->torneo_id,
                        'equipo_id'=>$request->equipo[$item],
                        'posicion'=>$request->posicion[$item]
                    );
                    try {

                        PosicionTorneo::create($data2);




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
            $respuestaMSJ='Registro actualizado satisfactoriamente';
        }
        else{
            DB::rollback();
            $respuestaID='error';
            $respuestaMSJ=$error;
        }


        return redirect()->route('torneos.show', $request->torneo_id)->with($respuestaID,$respuestaMSJ);
    }

    public function titulos(Request $request)
    {

        $order= ($request->query('order'))?$request->query('order'):'Titulos';
        $tipoOrder= ($request->query('tipoOrder'))?$request->query('tipoOrder'):'DESC';
        $argentinos= ($request->query('argentinos'))?1:0;
        $sql = "
    SELECT id, escudo, nombre, pais,
           SUM(titulos) titulos, SUM(ligas) ligas, SUM(copas) copas, SUM(internacionales) internacionales
    FROM (
        SELECT equipos.id, equipos.nombre, equipos.pais, equipos.escudo, 1 AS titulos, 0 AS ligas, 0 AS copas, 0 AS internacionales
        FROM equipos
        INNER JOIN posicion_torneos ON equipos.id = posicion_torneos.equipo_id AND posicion_torneos.posicion=1
        UNION ALL
        SELECT equipos.id, equipos.nombre, equipos.pais, equipos.escudo, 0 AS titulos, 1 AS ligas, 0 AS copas, 0 AS internacionales
        FROM equipos
        INNER JOIN posicion_torneos ON equipos.id = posicion_torneos.equipo_id AND posicion_torneos.posicion=1
        INNER JOIN torneos ON torneos.id = posicion_torneos.torneo_id AND torneos.tipo = 'Liga' AND torneos.ambito = 'Nacional'
        UNION ALL
        SELECT equipos.id, equipos.nombre, equipos.pais, equipos.escudo, 0 AS titulos, 0 AS ligas, 1 AS copas , 0 AS internacionales
        FROM equipos
        INNER JOIN posicion_torneos ON equipos.id = posicion_torneos.equipo_id AND posicion_torneos.posicion=1
        INNER JOIN torneos ON torneos.id = posicion_torneos.torneo_id AND torneos.tipo = 'Copa' AND torneos.ambito = 'Nacional'
        UNION ALL
        SELECT equipos.id, equipos.nombre, equipos.pais, equipos.escudo, 0 AS titulos, 0 AS ligas, 0 AS copas , 1 AS internacionales
        FROM equipos
        INNER JOIN posicion_torneos ON equipos.id = posicion_torneos.equipo_id AND posicion_torneos.posicion=1
        INNER JOIN torneos ON torneos.id = posicion_torneos.torneo_id AND torneos.ambito = 'Internacional'
    ) a
    WHERE 1=1
";

// 🔹 Si pidió solo argentinos, agregamos el filtro
        if ($argentinos) {
            $sql .= " AND pais = 'Argentina' ";
        }

        $sql .= "
    GROUP BY nombre, pais, escudo, id
    ORDER BY $order $tipoOrder, internacionales DESC, ligas DESC, copas DESC, nombre ASC
";

        $posiciones = DB::select(DB::raw($sql));

        $page = $request->query('page', 1);

        $paginate = 15;

        $offSet = ($page * $paginate) - $paginate;

        $itemsForCurrentPage = array_slice($posiciones, $offSet, $paginate, true);



        $posiciones = new \Illuminate\Pagination\LengthAwarePaginator($itemsForCurrentPage, count($posiciones), $paginate, $page);



        $posiciones->setPath(route('torneos.titulos',array('order'=>$order,'tipoOrder'=>$tipoOrder,'argentinos'=>$argentinos)));


        $i=$offSet+1;


        return view('torneos.titulos', compact('posiciones','i','order','tipoOrder','argentinos'));
    }

    public function plantillas(Request $request)
    {

        $order= ($request->query('order'))?$request->query('order'):'dorsal';
        $tipoOrder= ($request->query('tipoOrder'))?$request->query('tipoOrder'):'ASC';
        $equipo1= $request->query('equipo1');



        $torneo_id= $request->query('torneoId');
        $torneo=Torneo::findOrFail($torneo_id);


        $grupos = Grupo::where('torneo_id', '=',$torneo_id)->get();
        $arrgrupos='';
        foreach ($grupos as $grupo){
            $arrgrupos .=$grupo->id.',';
        }
        $arrgrupos = substr($arrgrupos, 0, -1);//quito última coma

// Obtener la fecha del primer partido del torneo
        $primerPartido = Partido::whereHas('fecha.grupo', function($query) use ($torneo_id) {
            $query->where('torneo_id', $torneo_id);
        })->orderBy('dia', 'asc')->first();

        if ($primerPartido) {
            $fechaPrimerPartido = $primerPartido->dia; // Obtener la fecha del primer partido
        } else {
            $fechaPrimerPartido = now(); // En caso de que no haya partidos, usar la fecha actual como fallback
        }

        //$equipos = Plantilla::with('equipo')->wherein('grupo_id',explode(',', $arrgrupos))->orderBy('equipo.nombre','ASC')->get()->pluck('equipo.nombre', 'equipo_id');
        $equipos = Plantilla::with('equipo')
            ->whereIn('grupo_id', explode(',', $arrgrupos))
            ->join('equipos', 'plantillas.equipo_id', '=', 'equipos.id')
            ->orderBy('equipos.nombre', 'ASC')
            ->get()
            ->pluck('equipo.nombre', 'equipo_id');
        //dd($equipos[0]);

        /*$plantilla=Plantilla::where('equipo_id', '=',$equipo1)->whereIn('grupo_id', explode(',', $arrgrupos))->first();

        $plantillaJugadors =array();
        if (!empty($plantilla)){
            $plantillaJugadors = PlantillaJugador::where('plantilla_id','=',"$plantilla->id")->with('jugador')->orderBy('dorsal','asc')->get();
        }*/
        if (empty($equipo1)){

            $equipo1 = $equipos->keys()->first();
        }

        $e1=Equipo::findOrFail($equipo1);
        $sql = 'SELECT jugador_id, dorsal, foto, nacionalidad, jugador,
       sum(jugados) jugados, tipoJugador, nacimiento,

       sum(goles) goles,
       sum(rojas) rojas,
       sum(amarillas) amarillas,
       sum(recibidos) recibidos,
       sum(invictas) invictas

from

(SELECT jugadors.id AS jugador_id, personas.foto, personas.nacionalidad, personas.nacimiento,"0" as jugados, personas.name as jugador, CONCAT(personas.apellido,\', \',personas.nombre) completo, "1" as goles, "0" as  amarillas
, "0" as  rojas, "0" as  recibidos, "0" as  invictas, alineacions.dorsal, jugadors.tipoJugador
FROM gols
INNER JOIN jugadors ON gols.jugador_id = jugadors.id
INNER JOIN personas ON jugadors.persona_id = personas.id
INNER JOIN partidos ON gols.partido_id = partidos.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id
INNER JOIN alineacions ON alineacions.partido_id = partidos.id AND alineacions.jugador_id = jugadors.id

WHERE gols.tipo <> \'En contra\' AND grupos.torneo_id='.$torneo_id.' AND grupos.id IN ('.$arrgrupos.') AND alineacions.equipo_id='.$equipo1.'

 UNION ALL
 SELECT jugadors.id AS jugador_id, personas.foto, personas.nacionalidad, personas.nacimiento,"0" as jugados, personas.name as jugador, CONCAT(personas.apellido,\', \',personas.nombre) completo, "0" AS goles, ( case when tarjetas.tipo=\'Amarilla\' then 1 else NULL end) as  amarillas
, ( case when tarjetas.tipo=\'Roja\' or tarjetas.tipo=\'Doble Amarilla\' then 1 else NULL end) as  rojas, "0" as  recibidos, "0" as  invictas, alineacions.dorsal, jugadors.tipoJugador
FROM tarjetas
INNER JOIN jugadors ON tarjetas.jugador_id = jugadors.id
INNER JOIN personas ON jugadors.persona_id = personas.id
LEFT JOIN partidos ON tarjetas.partido_id = partidos.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id
INNER JOIN alineacions ON alineacions.partido_id = partidos.id AND alineacions.jugador_id = jugadors.id
WHERE  grupos.torneo_id='.$torneo_id.' AND grupos.id IN ('.$arrgrupos.') AND alineacions.equipo_id='.$equipo1.'

UNION ALL
 SELECT jugadors.id AS jugador_id, personas.foto, personas.nacionalidad, personas.nacimiento,"1" as jugados, personas.name as jugador, CONCAT(personas.apellido,\', \',personas.nombre) completo, "0" AS goles, "0" as  amarillas
, "0" as  rojas, (case when alineacions.equipo_id=partidos.equipol_id then partidos.golesv else partidos.golesl END) AS recibidos,
(case when alineacions.equipo_id=partidos.equipol_id and partidos.golesv = 0 then 1 else CASE when alineacions.equipo_id=partidos.equipov_id and partidos.golesl = 0 THEN 1 ELSE 0 END END) AS invictas, alineacions.dorsal, jugadors.tipoJugador
FROM alineacions
INNER JOIN jugadors ON alineacions.jugador_id = jugadors.id AND jugadors.tipoJugador = \'Arquero\'
INNER JOIN personas ON jugadors.persona_id = personas.id
INNER JOIN partidos ON alineacions.partido_id = partidos.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id

WHERE  alineacions.tipo = \'Titular\'  AND grupos.torneo_id='.$torneo_id.' AND grupos.id IN ('.$arrgrupos.') AND alineacions.equipo_id='.$equipo1.'

UNION ALL
 SELECT jugadors.id AS jugador_id, personas.foto, personas.nacionalidad, personas.nacimiento,"1" as jugados, personas.name as jugador, CONCAT(personas.apellido,\', \',personas.nombre) completo, "0" AS goles, "0" as  amarillas
, "0" as  rojas, "0" AS recibidos,
"0" AS invictas, alineacions.dorsal, jugadors.tipoJugador
FROM alineacions
INNER JOIN jugadors ON alineacions.jugador_id = jugadors.id AND jugadors.tipoJugador != \'Arquero\'
INNER JOIN personas ON jugadors.persona_id = personas.id
INNER JOIN partidos ON alineacions.partido_id = partidos.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id
LEFT JOIN cambios ON alineacions.partido_id = cambios.partido_id AND cambios.jugador_id = jugadors.id
WHERE  (alineacions.tipo = \'Titular\' OR cambios.tipo = \'Entra\')  AND grupos.torneo_id='.$torneo_id.' AND grupos.id IN ('.$arrgrupos.') AND alineacions.equipo_id='.$equipo1.'

UNION ALL
SELECT jugadors.id AS jugador_id, personas.foto, personas.nacionalidad, personas.nacimiento,"1" as jugados, personas.name as jugador, CONCAT(personas.apellido,\', \',personas.nombre) completo, "0" AS goles, "0" as  amarillas
, "0" as  rojas, "0" AS recibidos,
"0" AS invictas, alineacions.dorsal, jugadors.tipoJugador
FROM alineacions
INNER JOIN jugadors ON alineacions.jugador_id = jugadors.id AND jugadors.tipoJugador = \'Arquero\'
INNER JOIN personas ON jugadors.persona_id = personas.id
INNER JOIN partidos ON alineacions.partido_id = partidos.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id
LEFT JOIN cambios ON alineacions.partido_id = cambios.partido_id AND cambios.jugador_id = jugadors.id
WHERE  (cambios.tipo = \'Entra\') AND grupos.torneo_id='.$torneo_id.' AND grupos.id IN ('.$arrgrupos.') AND alineacions.equipo_id='.$equipo1.'
) a

group by jugador_id,jugador, foto, nacionalidad, nacimiento, dorsal, tipoJugador
ORDER BY '.$order.' '.$tipoOrder.',dorsal, jugador ASC';

        $jugadores = DB::select(DB::raw($sql));
        //echo $sql;
        $page = $request->query('page', 1);

        $paginate = 15;

        $offSet = ($page * $paginate) - $paginate;

        $itemsForCurrentPage = array_slice($jugadores, $offSet, $paginate, true);



        $jugadores = new \Illuminate\Pagination\LengthAwarePaginator($itemsForCurrentPage, count($jugadores), $paginate, $page);

        foreach ($jugadores as $jugador){
            $jugadorAux = Jugador::findOrFail($jugador->jugador_id);
            $jugador->edad = $jugadorAux->persona->getAgeAtDate($fechaPrimerPartido);
        }

        $jugadores->setPath(route('torneos.plantillas',  array('torneoId' => $torneo->id,'order'=>$order,'tipoOrder'=>$tipoOrder,'equipo1' => $equipo1)));


        $sql = 'SELECT tecnico, fotoTecnico, nacionalidadTecnico, tecnico_id,
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
    ) prom, "" escudo, "" AS jugando, "" AS titulos
from (
       select  DISTINCT personas.name as tecnico, CONCAT(personas.apellido,\', \',personas.nombre) completo, personas.foto fotoTecnico, personas.nacionalidad nacionalidadTecnico, tecnicos.id tecnico_id, golesl, golesv, equipos.escudo foto, fechas.id fecha_id
		 from partidos
		 INNER JOIN equipos ON partidos.equipol_id = equipos.id
		 INNER JOIN plantillas ON plantillas.equipo_id = equipos.id
		 INNER JOIN fechas ON partidos.fecha_id = fechas.id
		 INNER JOIN grupos ON fechas.grupo_id = grupos.id
		 INNER JOIN partido_tecnicos ON partidos.id = partido_tecnicos.partido_id AND equipos.id = partido_tecnicos.equipo_id
		 INNER JOIN tecnicos ON tecnicos.id = partido_tecnicos.tecnico_id
		 INNER JOIN personas ON personas.id = tecnicos.persona_id
		 WHERE golesl is not null AND golesv is not null AND grupos.torneo_id='.$torneo_id.' AND grupos.id IN ('.$arrgrupos.') AND partido_tecnicos.equipo_id='.$equipo1;


        $sql .=' union all
       select DISTINCT personas.name as tecnico, CONCAT(personas.apellido,\', \',personas.nombre) completo, personas.foto fotoTecnico, personas.nacionalidad nacionalidadTecnico, tecnicos.id tecnico_id, golesv, golesl, equipos.escudo foto, fechas.id fecha_id
		 from partidos
		 INNER JOIN equipos ON partidos.equipov_id = equipos.id
		 INNER JOIN plantillas ON plantillas.equipo_id = equipos.id
		 INNER JOIN fechas ON partidos.fecha_id = fechas.id
		 INNER JOIN grupos ON fechas.grupo_id = grupos.id
		 INNER JOIN partido_tecnicos ON partidos.id = partido_tecnicos.partido_id AND equipos.id = partido_tecnicos.equipo_id
		 INNER JOIN tecnicos ON tecnicos.id = partido_tecnicos.tecnico_id
		 INNER JOIN personas ON personas.id = tecnicos.persona_id
		 WHERE golesl is not null AND golesv is not null AND grupos.torneo_id='.$torneo_id.' AND grupos.id IN ('.$arrgrupos.') AND partido_tecnicos.equipo_id='.$equipo1;

        $sql .=' ) a
group by tecnico, fotoTecnico, nacionalidadTecnico, tecnico_id



        ORDER BY jugados DESC, tecnico ASC';

        $tecnicosEquipo = DB::select(DB::raw($sql));
        foreach ($tecnicosEquipo as $tecnico){
            $tecnicoAux = Tecnico::findOrFail($tecnico->tecnico_id);
            $tecnico->edad = $tecnicoAux->persona->getAgeAtDate($fechaPrimerPartido);
        }
        //dd($tecnicos);
        return view('torneos.plantillas', compact('torneo','equipos','e1','jugadores','order','tipoOrder','tecnicosEquipo'));
    }

    public function dorsal(Request $request)
    {

        $equipo1= $request->query('equipo1');



        $torneo_id= $request->query('torneoId');
        $torneo=Torneo::findOrFail($torneo_id);


        $grupos = Grupo::where('torneo_id', '=',$torneo_id)->get();
        $arrgrupos='';
        foreach ($grupos as $grupo){
            $arrgrupos .=$grupo->id.',';
        }
        $arrgrupos = substr($arrgrupos, 0, -1);//quito última coma

// Obtener la fecha del primer partido del torneo
        $primerPartido = Partido::whereHas('fecha.grupo', function($query) use ($torneo_id) {
            $query->where('torneo_id', $torneo_id);
        })->orderBy('dia', 'asc')->first();

        if ($primerPartido) {
            $fechaPrimerPartido = $primerPartido->dia; // Obtener la fecha del primer partido
        } else {
            $fechaPrimerPartido = now(); // En caso de que no haya partidos, usar la fecha actual como fallback
        }

        $equipos = Plantilla::with('equipo')
            ->whereIn('grupo_id', explode(',', $arrgrupos))
            ->join('equipos', 'plantillas.equipo_id', '=', 'equipos.id')
            ->orderBy('equipos.nombre', 'ASC')
            ->get()
            ->pluck('equipo.nombre', 'equipo_id');

        if (empty($equipo1)){

            $equipo1 = $equipos->keys()->first();
        }
        //dd($equipo1);

        $plantillasL = Plantilla::wherein('grupo_id',explode(',', $arrgrupos))->where('equipo_id','=',$equipo1)->get();



        $arrplantillals='';
        foreach ($plantillasL as $plantillal){
            $arrplantillals .=$plantillal->id.',';
        }

        $e1=Equipo::findOrFail($equipo1);

        $jugadorsL = Jugador::SELECT('jugadors.*',DB::raw("CONCAT(personas.apellido, ' ', personas.nombre, ' (',plantilla_jugadors.dorsal,')') as 'nombre_dorsal'"), 'personas.foto')->Join('plantilla_jugadors','plantilla_jugadors.jugador_id','=','jugadors.id')->Join('personas','personas.id','=','jugadors.persona_id')->wherein('plantilla_id',explode(',', $arrplantillals))->distinct()->get();


        //dd($jugadorsL);
        $jugadorsL = $jugadorsL->pluck('nombre_dorsal','id')->sortBy('apellido')->prepend('','');


        return view('torneos.dorsal', compact('torneo','equipos','e1','jugadorsL'));
    }


    public function guardarDorsal(Request $request)
    {
        //dd($request);
        // Verifica si solo se está seleccionando el equipo
        if ($request->has('equipo1') && !$request->filled('jugador_id')) {

            $equipo1= $request->get('equipo1');



            $torneo_id= $request->get('torneoId');

            $torneo=Torneo::findOrFail($torneo_id);

            //dd($torneo);
            $grupos = Grupo::where('torneo_id', '=',$torneo_id)->get();
            $arrgrupos='';
            foreach ($grupos as $grupo){
                $arrgrupos .=$grupo->id.',';
            }
            $arrgrupos = substr($arrgrupos, 0, -1);//quito última coma

// Obtener la fecha del primer partido del torneo
            $primerPartido = Partido::whereHas('fecha.grupo', function($query) use ($torneo_id) {
                $query->where('torneo_id', $torneo_id);
            })->orderBy('dia', 'asc')->first();

            if ($primerPartido) {
                $fechaPrimerPartido = $primerPartido->dia; // Obtener la fecha del primer partido
            } else {
                $fechaPrimerPartido = now(); // En caso de que no haya partidos, usar la fecha actual como fallback
            }

            $equipos = Plantilla::with('equipo')
                ->whereIn('grupo_id', explode(',', $arrgrupos))
                ->join('equipos', 'plantillas.equipo_id', '=', 'equipos.id')
                ->orderBy('equipos.nombre', 'ASC')
                ->get()
                ->pluck('equipo.nombre', 'equipo_id');

            if (empty($equipo1)){

                $equipo1 = $equipos->keys()->first();
            }
            //dd($equipo1);

            $plantillasL = Plantilla::wherein('grupo_id',explode(',', $arrgrupos))->where('equipo_id','=',$equipo1)->get();



            $arrplantillals='';
            foreach ($plantillasL as $plantillal){
                $arrplantillals .=$plantillal->id.',';
            }

            $e1=Equipo::findOrFail($equipo1);

            $jugadorsL = Jugador::SELECT('jugadors.*',DB::raw("CONCAT(personas.apellido, ' ', personas.nombre, ' (',plantilla_jugadors.dorsal,')') as 'nombre_dorsal'"), 'personas.foto')->Join('plantilla_jugadors','plantilla_jugadors.jugador_id','=','jugadors.id')->Join('personas','personas.id','=','jugadors.persona_id')->wherein('plantilla_id',explode(',', $arrplantillals))->distinct()->get();


            //dd($jugadorsL);
            $jugadorsL = $jugadorsL->pluck('nombre_dorsal','id')->sortBy('apellido')->prepend('','');


            return view('torneos.dorsal', compact('torneo','equipos','e1','jugadorsL'));
        }
        // Validar los datos recibidos
        $validated = $request->validate([
            'dorsal' => 'required|integer',
            'jugador_id' => 'required|integer',
            'equipo1' => 'required|integer',
            'torneoId' => 'required|integer',
        ]);

        // Extraer las variables del request
        $dorsal = $validated['dorsal'];
        $jugador_id = $validated['jugador_id'];
        $equipo_id = $validated['equipo1'];
        $torneo_id = $validated['torneoId'];

        //dd($request);
        //$this->validate($request,[ 'equipo_id'=>'required',  'grupo_id'=>'required']);
        DB::beginTransaction();




        $ok=1;

        try {
            // Actualizar en la tabla plantilla_jugadors
            DB::table('plantilla_jugadors')
                ->join('plantillas', 'plantilla_jugadors.plantilla_id', '=', 'plantillas.id')
                ->join('grupos', 'plantillas.grupo_id', '=', 'grupos.id')
                ->join('torneos', 'grupos.torneo_id', '=', 'torneos.id')
                ->where('plantilla_jugadors.jugador_id', $jugador_id)
                ->where('plantillas.equipo_id', $equipo_id)
                ->where('torneos.id', $torneo_id)
                ->update(['plantilla_jugadors.dorsal' => $dorsal]);

                try {

                    // Actualizar en la tabla alineacions
                    DB::table('alineacions')
                        ->join('partidos', 'alineacions.partido_id', '=', 'partidos.id')
                        ->join('fechas', 'partidos.fecha_id', '=', 'fechas.id')
                        ->join('grupos', 'fechas.grupo_id', '=', 'grupos.id')
                        ->join('torneos', 'grupos.torneo_id', '=', 'torneos.id')
                        ->where('alineacions.jugador_id', $jugador_id)
                        ->where('alineacions.equipo_id', $equipo_id)
                        ->where('torneos.id', $torneo_id)
                        ->update(['alineacions.dorsal' => $dorsal]);




                }catch(QueryException $ex){

                    $error = $ex->getMessage();


                    $ok=0;

                }



        }catch(QueryException $e){
            $error = $e->getMessage();
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


        return redirect()->route('torneos.show', $torneo_id)->with($respuestaID,$respuestaMSJ);
    }

    public function clasificados(Torneo $torneo)
    {
        // Obtener todas las clasificaciones del torneo
        $clasificaciones = $torneo->clasificaciones;

        // Todos los equipos del torneo
        $grupos = $torneo->grupos()->with('plantillas.equipo')->get();
        $equipos = collect();
        foreach ($grupos as $grupo) {
            foreach ($grupo->plantillas as $plantilla) {
                $equipos->put($plantilla->equipo->id, $plantilla->equipo->nombre);
            }
        }

        // Equipos ya asignados por clasificación
        $equiposClasificados = \DB::table('equipo_clasificados')->get()->groupBy('torneo_clasificacion_id');

        return view('torneos.clasificados', compact('torneo', 'clasificaciones', 'equipos', 'equiposClasificados'));
    }

    public function updateClasificados(Request $request, Torneo $torneo)
    {
        $data = $request->input('clasificados', []);

        foreach ($torneo->clasificaciones as $clasificacion) {
            \DB::table('equipo_clasificados')->where('torneo_clasificacion_id', $clasificacion->id)->delete();
            if (!empty($data[$clasificacion->id])) {
                foreach ($data[$clasificacion->id] as $equipo_id) {
                    \DB::table('equipo_clasificados')->insert([
                        'torneo_clasificacion_id' => $clasificacion->id,
                        'equipo_id' => $equipo_id,
                        'torneo_id' => $torneo->id
                    ]);
                }
            }
        }

        return redirect()->route('torneos.clasificados', $torneo->id)->with('success', 'Clasificados actualizados');
    }


}
