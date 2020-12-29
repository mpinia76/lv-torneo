<?php

namespace App\Http\Controllers;

use App\Alineacion;
use App\Arbitro;
use App\Cambio;
use App\Equipo;
use App\Fecha;
use App\Gol;
use App\Grupo;
use App\Jugador;
use App\Partido;
use App\PartidoArbitro;
use App\Plantilla;
use App\PlantillaJugador;
use App\PlantillaTecnico;
use App\Tarjeta;
use App\Tecnico;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use DB;
use Illuminate\Support\Facades\Log;
use Sunra\PhpSimple\HtmlDomParser;

class FechaController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth')->except(['ver','showPublic','detalle']);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $grupo_id= $request->query('grupoId');
        $nombre = $request->get('buscarpor');
        $grupo=Grupo::findOrFail($grupo_id);


        $fechas=Fecha::where('grupo_id','=',"$grupo_id")->where('numero','like',"%$nombre%")->orderBy('numero','ASC')->get();

        //dd($fechas);

        //print_r($fechas);

        return view('fechas.index', compact('fechas','grupo'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        $grupo_id= $request->query('grupoId');
        $grupo=Grupo::findOrFail($grupo_id);

        //$equipos=Equipo::all();

        $equipos = Plantilla::with('equipo')->get()->pluck('equipo.nombre', 'equipo_id')->prepend('','');
        //
        return view('fechas.create', compact('grupo','equipos'));
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
        $this->validate($request,[ 'numero'=>'required',  'grupo_id'=>'required']);
        DB::beginTransaction();
        $ok=1;
        try {
            $fecha = fecha::create($request->all());
            //$fecha=$request->all();
            $lastid=$fecha->id;
            if(count($request->fecha) > 0)
            {
                foreach($request->fecha as $item=>$v){

                    $data2=array(
                        'fecha_id'=>$lastid,
                        'dia'=>$request->fecha[$item],
                        'equipol_id'=>$request->equipol[$item],
                        'equipov_id'=>$request->equipov[$item],
                        'golesl'=>$request->golesl[$item],
                        'golesv'=>$request->golesv[$item]
                    );

                    try {
                        Partido::create($data2);
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



        return redirect()->route('fechas.index', array('grupoId' => $fecha->grupo->id))->with($respuestaID,$respuestaMSJ);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $fecha=Fecha::findOrFail($id);

        $grupo=Grupo::findOrFail($fecha->grupo->id);



        return view('fechas.show', compact('grupo','fecha'));
    }

    public function showPublic(Request $request)
    {
        $id= $request->query('fechaId');

        $fecha=Fecha::findOrFail($id);

        $grupo=Grupo::findOrFail($fecha->grupo->id);



        return view('fechas.showPublic', compact('grupo','fecha'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {

        $fecha=Fecha::findOrFail($id);

        $grupo=Grupo::findOrFail($fecha->grupo->id);



        $equipos = Plantilla::with('equipo')->get()->pluck('equipo.nombre', 'equipo_id')->prepend('','');

        return view('fechas.edit', compact('grupo','equipos','fecha'));
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
        $this->validate($request,[ 'numero'=>'required',  'grupo_id'=>'required']);
        DB::beginTransaction();

        $fecha=fecha::find($id);
        $ok=1;
        try {
            $fecha->update($request->all());
            //Partido::where('fecha_id', '=', "$id")->delete();
            if(count($request->fecha) > 0)
            {
                foreach($request->fecha as $item=>$v){

                    $data2=array(
                        'fecha_id'=>$id,
                        'dia'=>$request->fecha[$item],
                        'equipol_id'=>$request->equipol[$item],
                        'equipov_id'=>$request->equipov[$item],
                        'golesl'=>$request->golesl[$item],
                        'golesv'=>$request->golesv[$item]
                    );
                    try {
                        if (!empty($request->partido_id[$item])){
                            $data2['id']=$request->partido_id[$item];
                            $partido=Partido::find($request->partido_id[$item]);
                            $partido->update($data2);
                        }
                        else{
                            Partido::create($data2);
                        }
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


        return redirect()->route('fechas.index', array('grupoId' => $fecha->grupo->id))->with($respuestaID,$respuestaMSJ);

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $fecha = fecha::find($id);
        $grupo_id = $fecha->grupo->id;
        //$fecha->grupos()->delete();
        $fecha->delete();
        return redirect()->route('fechas.index', array('grupoId' => $grupo_id))->with('success','Registro eliminado satisfactoriamente');
    }


    public function import(Request $request)
    {
        $grupo_id= $request->query('grupoId');
        $grupo=Grupo::findOrFail($grupo_id);

        //
        return view('fechas.import', compact('grupo'));
    }

    public function importincidencias(Request $request)
    {
        $grupo_id= $request->query('grupoId');
        $grupo=Grupo::findOrFail($grupo_id);

        //
        return view('fechas.importincidencias', compact('grupo'));
    }

    public function importprocess(Request $request)
    {

        set_time_limit(0);

        $grupo_id = $request->get('grupo_id');

        $file = $request->file('archivoCSV');

        // File Details
        $filename = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $tempPath = $file->getRealPath();
        $fileSize = $file->getSize();
        $mimeType = $file->getMimeType();


        // Valid File Extensions
        $valid_extension = array("csv");

        // 2MB in Bytes
        $maxFileSize = 2097152;

        // Check file extension
        if(in_array(strtolower($extension),$valid_extension)){

            // Check file size
            if($fileSize <= $maxFileSize){

                // File upload location
                $location = 'uploads';

                // Upload file
                $file->move($location,$filename);

                // Import CSV to Database
                $filepath = public_path($location."/".$filename);

                // Reading file
                $file = fopen($filepath,"r");

                $importData_arr = array();
                $i = 0;

                while (($filedata = fgetcsv($file, 1000, ",")) !== FALSE) {
                    $num = count($filedata );

                    // Skip first row (Remove below comment if you want to skip the first row)
                    /*if($i == 0){
                       $i++;
                       continue;
                    }*/
                    for ($c=0; $c < $num; $c++) {
                        $importData_arr[$i][] = $filedata [$c];
                    }
                    $i++;
                }
                fclose($file);
                //print_r($importData_arr);
                // Insert to MySQL database
                DB::beginTransaction();
                $ok=1;
                foreach($importData_arr as $importData){
                    $golesL = null;
                    $golesV = null;
                     $numero=$importData[0];
                     if($numero){
                         $dia = $importData[2].' '.$importData[3];
                         $strEquipoL = utf8_encode($importData[4]);
                         $resultado = $importData[5];
                         $goles = explode("-", $resultado);
                         //Log::debug(print_r($goles),[]);
                         if(count($goles)>1){
                             $golesL = $goles[0];
                             $golesV = $goles[1];
                         }



                         $equipol = Equipo::where('nombre', 'like', "%$strEquipoL%")->first();

                         if (!$equipol){
                             Log::info('Equipo NO encontrado: '.$numero.'-'.$dia.'-'.$strEquipoL,[]);
                         }
                        else{
                            $strEquipoV = utf8_encode($importData[6]);
                            $equipoV = Equipo::where('nombre', 'like', "%$strEquipoV%")->first();

                            if (!$equipoV){
                                Log::info('Equipo NO encontrado: '.$numero.'-'.$dia.'-'.$strEquipoV,[]);
                            }
                            else{
                                $strArbitro = utf8_encode($importData[9]);
                                $arrArbitro = explode(" ", $strArbitro);

                                if(count($arrArbitro)>1) {
                                    $arbitro = Arbitro::orwhere('nombre', 'like', "%$arrArbitro[0]%")->orwhere('apellido', 'like', "%$arrArbitro[1]%")->first();
                                }

                                if (!$arbitro){
                                    Log::info('Arbitro NO encontrado: '.$numero.'-'.$dia.'-'.$strArbitro,[]);
                                }
                                $nro= str_pad($numero, 2, "0", STR_PAD_LEFT);
                                $fecha=Fecha::where('grupo_id','=',"$grupo_id")->where('numero','=',"$nro")->first();

                                try {

                                    if(!$fecha){

                                        $data1=array(

                                            'numero'=>$nro,
                                            'grupo_id'=>$grupo_id
                                        );

                                        $fecha = fecha::create($data1);


                                    }
                                    $lastid=$fecha->id;


                                    $data2=array(
                                        'fecha_id'=>$lastid,
                                        'dia'=>$dia,
                                        'equipol_id'=>$equipol->id,
                                        'equipov_id'=>$equipoV->id,
                                        'golesl'=>$golesL,
                                        'golesv'=>$golesV
                                    );
                                    $partido=Partido::where('fecha_id','=',"$lastid")->where('equipol_id','=',"$equipol->id")->where('equipov_id','=',"$equipoV->id")->first();
                                    try {
                                        if (!empty($partido)){

                                            $partido->update($data2);
                                        }
                                        else{
                                            $partido=Partido::create($data2);
                                        }
                                        if (!empty($arbitro)){
                                            $data3=array(
                                                'partido_id'=>$partido->id,
                                                'arbitro_id'=>$arbitro->id,
                                                'tipo'=>'Principal'
                                            );
                                            $partido_arbitro=PartidoArbitro::where('partido_id','=',"$partido->id")->where('arbitro_id','=',"$arbitro->id")->first();
                                            try {
                                                if (!empty($partido_arbitro)){

                                                    $partido_arbitro->update($data3);
                                                }
                                                else{
                                                    $partido_arbitro=PartidoArbitro::create($data3);
                                                }

                                            }catch(QueryException $ex){
                                                $error = $ex->getMessage();
                                                $ok=0;
                                                continue;
                                            }
                                        }

                                    }catch(QueryException $ex){
                                        $error = $ex->getMessage();
                                        $ok=0;
                                        continue;
                                    }



                                }catch(Exception $e){
                                    //if email or phone exist before in db redirect with error messages
                                    $error = $ex->getMessage();
                                    $ok=0;
                                    continue;
                                }

                            }




                        }




                     }
                }


            }else{


                $error='Archivo demasiado grande. El archivo debe ser menor que 2MB.';
                $ok=0;

            }

        }else{

            $error='Extensión de archivo no válida.';
            $ok=0;

        }

        if ($ok){



            DB::commit();
            $respuestaID='success';
            $respuestaMSJ='Importación exitosa. (ver log)';
        }
        else{
            DB::rollback();
            $respuestaID='error';
            $respuestaMSJ=$error;
        }

        //
        return redirect()->route('fechas.index', array('grupoId' => $grupo_id))->with($respuestaID,$respuestaMSJ);
    }

    public function importincidenciasprocess(Request $request)
    {
        set_time_limit(0);

        $grupo_id = $request->get('grupo_id');
        $grupo=Grupo::findOrFail($grupo_id);

        $file = $request->file('archivoHTML');

        // File Details
        $filename = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $tempPath = $file->getRealPath();
        $fileSize = $file->getSize();
        $mimeType = $file->getMimeType();


        // Valid File Extensions
        $valid_extension = array("html");

        // 2MB in Bytes
        $maxFileSize = 2097152;

        // Check file extension
        if(in_array(strtolower($extension),$valid_extension)) {

            // Check file size
            if($fileSize <= $maxFileSize){

                // File upload location
                $location = 'uploads';

                // Upload file
                $file->move($location,$filename);

                // Import CSV to Database
                $filepath = public_path($location."/".$filename);


                $html = HtmlDomParser::file_get_html($filepath, false, null, 0);

                $ok=1;
                DB::beginTransaction();

                $equipos = array();


               foreach ($html->find('div[class=matchstats-lineup]') as $element) {

                    $equipo = utf8_encode($element->first_child()->plaintext);
                    $entrenador = utf8_encode($element->find('dd')[0]->plaintext);
                    $jugadores = array();
                    foreach ($element->find('li') as $li) { //get all <li>-elements as array
                        $dorsal = $li->find('span[class=jersey]');
                        $incidencias = array();
                        foreach ($li->find('img') as $img) {
                            $arrInc = explode('-', $img->title);



                            $incidencias[] = $arrInc;


                        }
                        $data2 = array(

                            'dorsal' => $dorsal[0]->plaintext,
                            'incidencias' => $incidencias
                        );
                        $jugadores[]=$data2;
                    }
                    $data = array(

                        'equipo' => $equipo,
                        'entrenador'=>$entrenador,
                        'jugadores' => $jugadores
                    );
                    $equipos[$i] = $data;
                    $i++;


                }

                for ($i = 1; $i <= count($equipos); $i++) {
                    if ($i % 2 == 0) {
                        $strEquipoL=trim($equipos[$i - 1]['equipo']);
                        $equipoL=Equipo::where('nombre','like',"%$strEquipoL%")->first();
                        if (!empty($equipoL)){
                            $strEquipoV=trim($equipos[$i]['equipo']);
                            $equipoV=Equipo::where('nombre','like',"%$strEquipoV%")->first();
                            if (!empty($equipoV)){
                                $fechas = Fecha::where('grupo_id', '=',$grupo_id)->get();
                                $arrfechas='';
                                foreach ($fechas as $fecha){
                                    $arrfechas .=$fecha->id.',';
                                }
                                $partido=Partido::wherein('fecha_id',explode(',', $arrfechas))->where('equipol_id','=',"$equipoL->id")->where('equipov_id','=',"$equipoV->id")->first();
                                if (!empty($partido)){
                                    foreach ($equipos[$i - 1]['jugadores'] as $jugador) {

                                        $plantilla = Plantilla::where('torneo_id','=',$grupo->torneo->id)->where('equipo_id','=',$equipoL->id)->first();
                                        $plantillaJugador = PlantillaJugador::where('plantilla_id','=',$plantilla->id)->where('dorsal','=',$jugador['dorsal'])->first();

                                            foreach ($jugador['incidencias'] as $incidencia) {
                                                if (count($incidencia)>1){
                                                    Log::info('Incidencia: ' . trim($incidencia[0]).' MIN: '.intval(trim($incidencia[1])),[]);
                                                    $tipogol='';
                                                    switch (trim($incidencia[0])) {
                                                        case 'Penal':
                                                            $tipogol='Penal';
                                                            break;
                                                        case 'Gol':
                                                            $tipogol='Jugada';
                                                            break;
                                                        case 'Gol en propia meta':
                                                            $tipogol='En Contra';
                                                            break;
                                                    }
                                                    if ($tipogol){
                                                        if (!empty($plantillaJugador)) {
                                                            $goldata = array(
                                                                'partido_id' => $partido->id,
                                                                'jugador_id' => $plantillaJugador->jugador->id,
                                                                'minuto' => intval(trim($incidencia[1])),
                                                                'tipo' => $tipogol
                                                            );
                                                            $gol = Gol::where('partido_id', '=', $partido->id)->where('jugador_id', '=', $plantillaJugador->jugador->id)->where('minuto', '=', intval(trim($incidencia[1])))->first();
                                                            try {
                                                                if (!empty($gol)) {

                                                                    $gol->update($goldata);
                                                                } else {
                                                                    $gol = Gol::create($goldata);
                                                                }


                                                            } catch (QueryException $ex) {
                                                                $error = $ex->getMessage();
                                                                $ok = 0;
                                                                continue;
                                                            }
                                                        }
                                                        else{
                                                                Log::info('NO se encontró al jugador: ' . $jugador['dorsal'].' del equipo '.$strEquipoL,[]);
                                                            }
                                                    }
                                                    $tipotarjeta='';
                                                    switch (trim($incidencia[0])) {
                                                        case 'Tarjeta amarilla':
                                                            $tipotarjeta='Amarilla';
                                                            break;
                                                        case 'Expulsado por doble amarilla':
                                                            $tipotarjeta='Doble Amarilla';
                                                            break;
                                                        case 'Tarjeta roja':
                                                            $tipotarjeta='Roja';
                                                            break;
                                                    }
                                                    if ($tipotarjeta){
                                                        if (!empty($plantillaJugador)) {
                                                            $tarjeadata=array(
                                                                'partido_id'=>$partido->id,
                                                                'jugador_id'=>$plantillaJugador->jugador->id,
                                                                'minuto'=>intval(trim($incidencia[1])),
                                                                'tipo'=>$tipotarjeta
                                                            );
                                                            $tarjeta=Tarjeta::where('partido_id','=',$partido->id)->where('jugador_id','=',$plantillaJugador->jugador->id)->where('minuto','=',intval(trim($incidencia[1])))->first();
                                                            try {
                                                                if (!empty($tarjeta)){

                                                                    $tarjeta->update($tarjeadata);
                                                                }
                                                                else{
                                                                    $tarjeta=Tarjeta::create($tarjeadata);
                                                                }


                                                            }catch(QueryException $ex){
                                                                $error = $ex->getMessage();
                                                                $ok=0;
                                                                continue;
                                                            }
                                                        }
                                                        else{
                                                            Log::info('NO se encontró al jugador: ' . $jugador['dorsal'].' del equipo '.$strEquipoL,[]);
                                                        }



                                                }


                                            }
                                        }


                                    }
                                    if (trim($equipos[$i - 1]['entrenador'])){
                                        $strEntrenador=trim($equipos[$i - 1]['entrenador']);
                                        $arrEntrenador = explode(' ', $strEntrenador);
                                        $entrenadorL=Tecnico::where('nombre','like',"%$arrEntrenador[0]%")->where('apellido','like',"%$arrEntrenador[1]%")->first();
                                        if (!empty($entrenadorL)){
                                            $plantillaTecnico = PlantillaTecnico::where('plantilla_id','=',$plantilla->id)->where('tecnico_id','=',$entrenadorL->id)->first();
                                            if (empty($plantillaTecnico)){
                                                Log::info('NO se encontró como entrenador: ' . $equipos[$i - 1]['entrenador'].' del equipo '.$equipos[$i - 1]['equipo'],[]);
                                            }
                                        }
                                        else{
                                            Log::info('NO se encontró al entrenador: ' . $equipos[$i - 1]['entrenador'].' del equipo '.$equipos[$i - 1]['equipo'],[]);
                                        }
                                    }
                                    foreach ($equipos[$i]['jugadores'] as $jugador) {

                                        $plantilla = Plantilla::where('torneo_id','=',$grupo->torneo->id)->where('equipo_id','=',$equipoV->id)->first();
                                        $plantillaJugador = PlantillaJugador::where('plantilla_id','=',$plantilla->id)->where('dorsal','=',$jugador['dorsal'])->first();

                                        foreach ($jugador['incidencias'] as $incidencia) {
                                            if (count($incidencia)>1){
                                                Log::info('Incidencia: ' . trim($incidencia[0]).' MIN: '.intval(trim($incidencia[1])),[]);
                                                $tipogol='';
                                                switch (trim($incidencia[0])) {
                                                    case 'Penal':
                                                        $tipogol='Penal';
                                                        break;
                                                    case 'Gol':
                                                        $tipogol='Jugada';
                                                        break;
                                                    case 'Gol en propia meta':
                                                        $tipogol='En Contra';
                                                        break;
                                                }
                                                if ($tipogol){
                                                    if (!empty($plantillaJugador)) {
                                                        $goldata = array(
                                                            'partido_id' => $partido->id,
                                                            'jugador_id' => $plantillaJugador->jugador->id,
                                                            'minuto' => intval(trim($incidencia[1])),
                                                            'tipo' => $tipogol
                                                        );
                                                        $gol = Gol::where('partido_id', '=', $partido->id)->where('jugador_id', '=', $plantillaJugador->jugador->id)->where('minuto', '=', intval(trim($incidencia[1])))->first();
                                                        try {
                                                            if (!empty($gol)) {

                                                                $gol->update($goldata);
                                                            } else {
                                                                $gol = Gol::create($goldata);
                                                            }


                                                        } catch (QueryException $ex) {
                                                            $error = $ex->getMessage();
                                                            $ok = 0;
                                                            continue;
                                                        }
                                                    }
                                                    else{
                                                        Log::info('NO se encontró al jugador: ' . $jugador['dorsal'].' del equipo '.$strEquipoV,[]);
                                                    }
                                                }
                                                $tipotarjeta='';
                                                switch (trim($incidencia[0])) {
                                                    case 'Tarjeta amarilla':
                                                        $tipotarjeta='Amarilla';
                                                        break;
                                                    case 'Expulsado por doble amarilla':
                                                        $tipotarjeta='Doble Amarilla';
                                                        break;
                                                    case 'Tarjeta roja':
                                                        $tipotarjeta='Roja';
                                                        break;
                                                }
                                                if ($tipotarjeta){
                                                    if (!empty($plantillaJugador)) {
                                                        $tarjeadata=array(
                                                            'partido_id'=>$partido->id,
                                                            'jugador_id'=>$plantillaJugador->jugador->id,
                                                            'minuto'=>intval(trim($incidencia[1])),
                                                            'tipo'=>$tipotarjeta
                                                        );
                                                        $tarjeta=Tarjeta::where('partido_id','=',$partido->id)->where('jugador_id','=',$plantillaJugador->jugador->id)->where('minuto','=',intval(trim($incidencia[1])))->first();
                                                        try {
                                                            if (!empty($tarjeta)){

                                                                $tarjeta->update($tarjeadata);
                                                            }
                                                            else{
                                                                $tarjeta=Tarjeta::create($tarjeadata);
                                                            }


                                                        }catch(QueryException $ex){
                                                            $error = $ex->getMessage();
                                                            $ok=0;
                                                            continue;
                                                        }
                                                    }
                                                    else{
                                                        Log::info('NO se encontró al jugador: ' . $jugador['dorsal'].' del equipo '.$strEquipoV,[]);
                                                    }



                                                }


                                            }
                                        }


                                    }
                                    if (trim($equipos[$i]['entrenador'])){
                                        $strEntrenador=trim($equipos[$i]['entrenador']);
                                        $arrEntrenador = explode(' ', $strEntrenador);
                                        $entrenadorV=Tecnico::where('nombre','like',"%$arrEntrenador[0]%")->where('apellido','like',"%$arrEntrenador[1]%")->first();
                                        if (!empty($entrenadorV)){
                                            $plantillaTecnico = PlantillaTecnico::where('plantilla_id','=',$plantilla->id)->where('tecnico_id','=',$entrenadorV->id)->first();
                                            if (empty($plantillaTecnico)){
                                                Log::info('NO se encontró como entrenador: ' . $equipos[$i]['entrenador'].' del equipo '.$equipos[$i]['equipo'],[]);
                                            }
                                        }
                                        else{
                                            Log::info('NO se encontró al entrenador: ' . $equipos[$i]['entrenador'].' del equipo '.$equipos[$i]['equipo'],[]);
                                        }
                                    }



                                }
                                else{
                                    Log::info('NO se encontró al partido: ' . $equipos[$i-1]['equipo'].' VS '.$equipos[$i]['equipo'],[]);
                                }
                            }
                            else{
                                Log::info('NO se encontró al equipo: ' . $equipos[$i]['equipo'],[]);
                            }
                        }
                        else{
                            Log::info('NO se encontró al equipo: ' . $equipos[$i-1]['equipo'],[]);
                        }



                    }
                }
            }else{


                $error='Archivo demasiado grande. El archivo debe ser menor que 2MB.';
                $ok=0;

            }
        }else{

            $error='Extensión de archivo no válida.';
            $ok=0;
        }

        if ($ok){



            DB::commit();
            $respuestaID='success';
            $respuestaMSJ='Importación exitosa. (ver log)';
        }
        else{
            DB::rollback();
            $respuestaID='error';
            $respuestaMSJ=$error;
        }

        //
        return redirect()->route('fechas.index', array('grupoId' => $grupo_id))->with($respuestaID,$respuestaMSJ);
    }

    public function dameNombreEquipoURL($strEquipo)
    {
        $strEquipoURL=strtr($strEquipo, " ", "-");
        switch (trim($strEquipo)) {
            case 'Racing Club':
                $strEquipoURL='Racing-Club-Avellaneda';
                break;
            case 'Estudiantes (LP)':
                $strEquipoURL='Estudiantes-Plata';
                break;
            case 'Defensa y Justicia':
                $strEquipoURL='Defensa-Justicia';
                break;
            case 'Central Córdoba (SdE)':
                $strEquipoURL='Central-Cordob';
                break;
            case 'Unión de Santa Fe':
                $strEquipoURL='Union-Santa-Fe';
                break;
            case 'Vélez Sarsfield':
                $strEquipoURL='Ca-Velez-Sarsfield';
                break;
            case 'Lanús':
                $strEquipoURL='Ca-Lanus';
                break;
            case 'Rosario Central':
                $strEquipoURL='Ca-Rosario-Central';
                break;
            case 'Arsenal':
                $strEquipoURL='Arsenal-Sarandi-Fc';
                break;
            case 'Atlético Tucumán':
                $strEquipoURL='Atletico-Tucuman';
                break;
            case 'River Plate':
                $strEquipoURL='Ca-River-Plate';
                break;
            case 'Banfield':
                $strEquipoURL='Ca-Banfield';
                break;
            case 'Huracán':
                $strEquipoURL='Ca-Huracan';
                break;
            case 'Boca Juniors':
                $strEquipoURL='Ca-Boca-Juniors';
                break;
            case 'Gimnasia (LP)':
                $strEquipoURL='Gimnasia-Plata';
                break;
            case 'Newell\'s Old Boys':
                $strEquipoURL='Newells-Old-Boys';
                break;
            case 'Godoy Cruz':
                $strEquipoURL='Godoy-Cruz-Antonio-Tomba';
                break;
            case 'Talleres (Cba.)':
                $strEquipoURL='Talleres-Cordoba';
                break;
            case 'Colón de Santa Fe':
                $strEquipoURL='Colon-Santa-Fe';
                break;
            case 'San Martín (Tuc.)':
                $strEquipoURL='San-Martin-Tucuman-Ar';
                break;
            case 'Tigre':
                $strEquipoURL='Ca-Tigre';
                break;
            case 'San Martín (SJ)':
                $strEquipoURL='San-Martin-San-Juan';
                break;
        }
        return $strEquipoURL;
    }

    public function dameNombreEquipoDB($strEquipo)
    {
        //Log::info('Transformar Equipo '.$strEquipo, []);
        $strEquipoDB=$strEquipo;
        switch (trim($strEquipo)) {
            case 'Estudiantes La Plata':
                $strEquipoDB='Estudiantes (LP)';
                break;
            case 'Central Córdoba SdE':
                $strEquipoDB='Central Córdoba (SdE)';
                break;
            case 'Unión Santa Fe':
                $strEquipoDB='Unión de Santa Fe';
                break;
            case 'Arsenal de Sarandí':
                $strEquipoDB='Arsenal';
                break;
            case 'Atl.Tucumán':
                $strEquipoDB='Atlético Tucumán';
                break;
            case 'CA Huracán':
                $strEquipoDB='Huracán';
                break;
            case 'Gimnasia La Plata':
                $strEquipoDB='Gimnasia (LP)';
                break;
            case 'Talleres Córdoba':
                $strEquipoDB='Talleres (Cba.)';
                break;
            case 'San Martín Tucumán':
                $strEquipoDB='San Martín (Tuc.)';
                break;
            case 'San Martín San Juan':
                $strEquipoDB='San Martín (SJ)';
                break;

        }
        return $strEquipoDB;
    }

    public function importincidenciasfecha(Request $request)
    {
        set_time_limit(0);
        //Log::info('Entraaaaaa', []);
        $id = $request->get('fechaId');
        $fecha=Fecha::findOrFail($id);

        $grupo=Grupo::findOrFail($fecha->grupo->id);

        $arrYear = explode('/', $grupo->torneo->year);

        $year = (count($arrYear)>1)?$arrYear[1]:$arrYear[0];
        $partidos=Partido::where('fecha_id','=',"$id")->get();
        $ok=1;
        DB::beginTransaction();
        foreach ($partidos as $partido){
            $strLocal = $partido->equipol->nombre;
            $strVisitante = $partido->equipov->nombre;
            $golesTotales = $partido->golesl+$partido->golesv;
            try {
                Log::info('Partido ' .$partido->equipol->nombre.' VS '.$partido->equipov->nombre, []);

                $html = HtmlDomParser::file_get_html('https://www.resultados-futbol.com/partido/'.$this->dameNombreEquipoURL($strLocal).'/'.$this->dameNombreEquipoURL($strVisitante).'/'.$year, false, null, 0);

            }
            catch (Exception $ex) {
                $html='';
            }

            if ($html){


                $equipos = array();

                //Log::info('Elemento ' . $html,[]);
                $i = 1;
                $goles=0;
                $j=0;
                foreach ($html->find('div[class=team team1]') as $element) {
                    $equipo = $element->find('h3[class=nteam nteam1]');
                    //Log::info('Equipo ' . $equipo[0]->plaintext, []);
                    $jugadores = array();

                    $jugadores = array();
                    foreach ($element->find('ul[class=aligns-list]') as $element2) {
                        $j++;
                        foreach ($element2->find('li') as $li) {
                            $dorsal = $li->find('small[class=align-dorsal]');
                            $jugador = $li->find('h5[class=align-player]');
                            $id =  explode('-',$li->find('a')[0]->href);
                            //Log::info('Jugador ' . $j.' -> '.$jugador[0]->plaintext, []);

                            $data2 = array(
                                'dorsal' => $dorsal[0]->plaintext,
                                'nombre'=>$jugador[0]->plaintext,
                                'tipo' => ($j==1)?'Titular':'Suplente',
                                'incidencias' => array()
                            );
                            $jugadores[$id[count($id)-1]]=$data2;
                        }
                        $data = array(

                            'equipo' => $this->dameNombreEquipoDB($equipo[0]->plaintext),

                            'jugadores' => $jugadores
                        );
                        $equipos[$i] = $data;

                        //$i++;


                    }
                }
                $i = 2;
                $j=0;
                foreach ($html->find('div[class=team team2]') as $element) {
                    $equipo = $element->find('h3[class=nteam nteam2]');
                    //Log::info('Equipo ' . $equipo[0]->plaintext, []);
                    $jugadores = array();
                    foreach ($element->find('ul[class=aligns-list]') as $element2) {
                        $j++;
                        foreach ($element2->find('li') as $li) {
                            $dorsal = $li->find('small[class=align-dorsal]');
                            $jugador = $li->find('h5[class=align-player]');
                            $id =  explode('-',$li->find('a')[0]->href);
                            //Log::info('ID ' . $id[2], []);
                            $data2 = array(
                                'dorsal' => $dorsal[0]->plaintext,
                                'nombre'=>$jugador[0]->plaintext,
                                'tipo' => ($j==1)?'Titular':'Suplente',
                                'incidencias' => array()
                            );
                            $jugadores[$id[count($id)-1]]=$data2;
                        }
                        $data = array(

                            'equipo' => $this->dameNombreEquipoDB($equipo[0]->plaintext),

                            'jugadores' => $jugadores
                        );
                        $equipos[$i] = $data;

                        //$i++;


                    }
                }
                //Log::info('Partido ' . print_r($equipos,true), []);
                foreach ($html->find('div[class=event-content]') as $element) {

                    $golL = $element->find('span[class=left event_1]');
                    if ($golL){
                        if($golL[0]->find('a')){
                            $id =  explode('-',$golL[0]->find('a')[0]->href);

                            $minL = explode('</b>',$element->find('span[class=left minutos minutosizq]')[0]);


                            $equipos[1]['jugadores'][$id[count($id) - 1]]['incidencias'][] = array('Gol', explode('\'', $minL[1])[0]);
                        }
                        else{
                            Log::info('OJO!!! falta el jugador en el gol local de ' . $partido->equipol->nombre.' VS '.$partido->equipov->nombre, []);
                        }

                        $goles++;

                    }

                    $penalL = $element->find('span[class=left event_11]');
                    if ($penalL){
                        if ($penalL[0]->find('a')){
                            $id =  explode('-',$penalL[0]->find('a')[0]->href);
                            $minL = explode('</b>',$element->find('span[class=left minutos minutosizq]')[0]);

                            $equipos[1]['jugadores'][$id[count($id)-1]]['incidencias'][]=array('Penal',explode('\'',$minL[1])[0]);

                        }
                        else{
                            Log::info('OJO!!! falta el jugador en el penal local de ' . $partido->equipol->nombre.' VS '.$partido->equipov->nombre, []);
                        }

                        $goles++;
                    }

                    $ppL = $element->find('span[class=left event_12]');
                    if ($ppL){
                        $id =  explode('-',$ppL[0]->find('a')[0]->href);

                        $minL = explode('</b>',$element->find('span[class=left minutos minutosizq]')[0]);

                        $equipos[2]['jugadores'][$id[count($id)-1]]['incidencias'][]=array('Gol en propia meta',explode('\'',$minL[1])[0]);
                        $goles++;
                    }

                    $amarillaL = $element->find('span[class=left event_8]');
                    if ($amarillaL){
                        if ($amarillaL[0]->find('a')){
                            $id =  explode('-',$amarillaL[0]->find('a')[0]->href);

                            $minL = explode('</b>',$element->find('span[class=left minutos minutosizq]')[0]);

                            $equipos[1]['jugadores'][$id[count($id)-1]]['incidencias'][]=array('Tarjeta amarilla',explode('\'',$minL[1])[0]);
                        }
                        else{
                            Log::info('OJO!!! falta el jugador en la amarilla local de ' . $partido->equipol->nombre.' VS '.$partido->equipov->nombre, []);
                        }



                    }

                    $dobleamarillaL = $element->find('span[class=left event_10]');
                    if ($dobleamarillaL){
                        $id =  explode('-',$dobleamarillaL[0]->find('a')[0]->href);

                        $minL = explode('</b>',$element->find('span[class=left minutos minutosizq]')[0]);

                        $equipos[1]['jugadores'][$id[count($id)-1]]['incidencias'][]=array('Expulsado por doble amarilla',explode('\'',$minL[1])[0]);


                    }

                    $rojaL = $element->find('span[class=left event_9]');
                    if ($rojaL){
                        $id =  explode('-',$rojaL[0]->find('a')[0]->href);

                        $minL = explode('</b>',$element->find('span[class=left minutos minutosizq]')[0]);

                        $equipos[1]['jugadores'][$id[count($id)-1]]['incidencias'][]=array('Tarjeta roja',explode('\'',$minL[1])[0]);


                    }

                    $saleL = $element->find('span[class=left event_6]');
                    if ($saleL){
                        if ($saleL[0]->find('a')){
                            $id =  explode('-',$saleL[0]->find('a')[0]->href);

                            $minL = explode('</b>',$element->find('span[class=left minutos minutosizq]')[0]);

                            $equipos[1]['jugadores'][$id[count($id)-1]]['incidencias'][]=array('Sale',explode('\'',$minL[1])[0]);
                        }
                        else{
                            Log::info('OJO!!! falta el jugador en el gol visitante de ' . $partido->equipol->nombre.' VS '.$partido->equipov->nombre, []);
                        }



                    }

                    $entraL = $element->find('span[class=left event_7]');
                    if ($entraL){
                        if ($entraL[0]->find('a')){
                            $id =  explode('-',$entraL[0]->find('a')[0]->href);

                            $minL = explode('</b>',$element->find('span[class=left minutos minutosizq]')[0]);

                            $equipos[1]['jugadores'][$id[count($id)-1]]['incidencias'][]=array('Entra',explode('\'',$minL[1])[0]);
                        }
                        else{
                            Log::info('OJO!!! falta el jugador en la entrada local de ' . $partido->equipol->nombre.' VS '.$partido->equipov->nombre, []);
                        }



                    }

                    $golV = $element->find('span[class=right event_1]');
                    if ($golV){
                        if ($golV[0]->find('a')){
                            $id =  explode('-',$golV[0]->find('a')[0]->href);

                            $minL = explode('</b>',$element->find('span[class=right minutos minutosder]')[0]);


                            $equipos[2]['jugadores'][$id[count($id)-1]]['incidencias'][]=array('Gol',explode('\'',$minL[1])[0]);
                        }
                        else{
                            Log::info('OJO!!! falta el jugador en la entrada local de ' . $partido->equipol->nombre.' VS '.$partido->equipov->nombre, []);
                        }

                        $goles++;
                    }

                    $penalV = $element->find('span[class=right event_11]');
                    if ($penalV){
                        $id =  explode('-',$penalV[0]->find('a')[0]->href);

                        $minL = explode('</b>',$element->find('span[class=right minutos minutosder]')[0]);

                        $equipos[2]['jugadores'][$id[count($id)-1]]['incidencias'][]=array('Penal',explode('\'',$minL[1])[0]);
                        $goles++;
                    }

                    $ppV = $element->find('span[class=right event_12]');
                    if ($ppV){
                        $id =  explode('-',$ppV[0]->find('a')[0]->href);

                        $minL = explode('</b>',$element->find('span[class=right minutos minutosder]')[0]);

                        $equipos[1]['jugadores'][$id[count($id)-1]]['incidencias'][]=array('Gol en propia meta',explode('\'',$minL[1])[0]);
                        $goles++;
                    }

                    $amarillaV = $element->find('span[class=right event_8]');
                    if ($amarillaV){
                        if ($amarillaV[0]->find('a')){
                            $id =  explode('-',$amarillaV[0]->find('a')[0]->href);

                            $minL = explode('</b>',$element->find('span[class=right minutos minutosder]')[0]);

                            $equipos[2]['jugadores'][$id[count($id)-1]]['incidencias'][]=array('Tarjeta amarilla',explode('\'',$minL[1])[0]);
                        }
                        else{
                            Log::info('OJO!!! falta el jugador en la amarilla visitante de ' . $partido->equipol->nombre.' VS '.$partido->equipov->nombre, []);
                        }


                    }

                    $dobleamarillaV = $element->find('span[class=right event_10]');
                    if ($dobleamarillaV){
                        $id =  explode('-',$dobleamarillaV[0]->find('a')[0]->href);

                        $minL = explode('</b>',$element->find('span[class=right minutos minutosder]')[0]);

                        $equipos[2]['jugadores'][$id[count($id)-1]]['incidencias'][]=array('Expulsado por doble amarilla',explode('\'',$minL[1])[0]);


                    }

                    $rojaV = $element->find('span[class=right event_9]');
                    if ($rojaV){
                        $id =  explode('-',$rojaV[0]->find('a')[0]->href);

                        $minL = explode('</b>',$element->find('span[class=right minutos minutosder]')[0]);

                        $equipos[2]['jugadores'][$id[count($id)-1]]['incidencias'][]=array('Tarjeta roja',explode('\'',$minL[1])[0]);


                    }

                    $saleV = $element->find('span[class=right event_6]');
                    if ($saleV){
                        if ($saleV[0]->find('a')){
                            $id =  explode('-',$saleV[0]->find('a')[0]->href);

                            $minL = explode('</b>',$element->find('span[class=right minutos minutosder]')[0]);

                            $equipos[2]['jugadores'][$id[count($id)-1]]['incidencias'][]=array('Sale',explode('\'',$minL[1])[0]);
                        }
                        else{
                            Log::info('OJO!!! falta el jugador en la salida visitante de ' . $partido->equipol->nombre.' VS '.$partido->equipov->nombre, []);
                        }



                    }

                    $entraV = $element->find('span[class=right event_7]');
                    if ($entraV){
                        if ($entraV[0]->find('a')){
                            $id =  explode('-',$entraV[0]->find('a')[0]->href);

                            $minL = explode('</b>',$element->find('span[class=right minutos minutosder]')[0]);

                            $equipos[2]['jugadores'][$id[count($id)-1]]['incidencias'][]=array('Entra',explode('\'',$minL[1])[0]);
                        }
                        else{
                            Log::info('OJO!!! falta el jugador en la entrada visitante de ' . $partido->equipol->nombre.' VS '.$partido->equipov->nombre, []);
                        }



                    }



                }
                if ($goles!=$golesTotales){
                    Log::info('OJO!!! No coincide la cantidad de goles en: '.$partido->equipol->nombre.' VS '.$partido->equipov->nombre.' -> '.$goles.' - '.$golesTotales,[]);
                }

                    foreach ($equipos as $eq) {
                        //Log::info('Equipo  ' . $equipo['equipo'], []);
                        $strEquipo=trim($eq['equipo']);
                        $equipo=Equipo::where('nombre','like',"%$strEquipo%")->first();
                        if (!empty($equipo)){
                            foreach ($eq['jugadores'] as $jugador) {
                                $plantilla = Plantilla::where('torneo_id','=',$grupo->torneo->id)->where('equipo_id','=',$equipo->id)->first();
                                if (!empty($plantilla)){
                                    if(!empty($jugador['dorsal'])){
                                        $plantillaJugador = PlantillaJugador::where('plantilla_id','=',$plantilla->id)->where('dorsal','=',$jugador['dorsal'])->first();
                                    }
                                    else{
                                        $plantillaJugador='';
                                        Log::info('OJO!!! con el jugador no está en la pantilla del equipo ' . $strEquipo, []);
                                    }
                                }
                                else{
                                    $plantillaJugador='';
                                    Log::info('OJO!!! No hay pantilla del equipo ' . $strEquipo, []);
                                }
                                if (!empty($plantillaJugador)) {
                                    $arrApellido = explode(' ', $jugador['nombre']);
                                    $mismoDorsal = 0;
                                    foreach ($arrApellido as $apellido) {
                                        $consultarJugador = Jugador::where('id', '=', $plantillaJugador->jugador->id)->where('apellido', 'LIKE', "%$apellido%")->first();
                                        if (!empty($consultarJugador)) {
                                            $mismoDorsal = 1;
                                            continue;
                                        }
                                    }
                                    if (!$mismoDorsal) {
                                        Log::info('OJO!!! con jugador: ' . $jugador['dorsal'] . ' ' . $jugador['nombre'] . ' del equipo ' . $strEquipo, []);
                                    }
                                    switch ($plantillaJugador->jugador->tipoJugador) {
                                        case 'Arquero':
                                            $orden=0;
                                            break;
                                        case 'Defensor':
                                            $orden=1;
                                            break;
                                        case 'Medio':
                                            $orden=2;
                                            break;
                                        case 'Delantero':
                                            $orden=3;
                                            break;

                                    }
                                    $alineaciondata = array(
                                        'partido_id' => $partido->id,
                                        'jugador_id' => $plantillaJugador->jugador->id,
                                        'equipo_id' => $equipo->id,
                                        'dorsal' =>  $jugador['dorsal'],
                                        'tipo' => $jugador['tipo'],
                                        'orden' => $orden
                                    );
                                    $alineacion = Alineacion::where('partido_id', '=', $partido->id)->where('jugador_id', '=', $plantillaJugador->jugador->id)->first();
                                    try {
                                        if (!empty($alineacion)) {

                                            $alineacion->update($alineaciondata);
                                        } else {
                                            $alineacion = Alineacion::create($alineaciondata);
                                        }


                                    } catch (QueryException $ex) {
                                        $error = $ex->getMessage();
                                        $ok = 0;
                                        continue;
                                    }
                                }
                                else{
                                    $jugadorMostrar = (!empty($jugador['dorsal']))?$jugador['dorsal']:'';
                                    Log::info('OJO!!! NO se encontró al jugador: ' . $jugadorMostrar.' del equipo '.$strEquipo,[]);
                                }
                                foreach ($jugador['incidencias'] as $incidencia) {

                                    if (!empty($incidencia)) {
                                        //Log::info('Incidencia: ' . trim($incidencia[0]) . ' MIN: ' . intval(trim($incidencia[1])), []);

                                        $tipogol='';
                                        switch (trim($incidencia[0])) {
                                            case 'Penal':
                                                $tipogol='Penal';
                                                break;
                                            case 'Gol':
                                                $tipogol='Jugada';
                                                break;
                                            case 'Gol en propia meta':
                                                $tipogol='En Contra';
                                                break;
                                        }
                                        if ($tipogol){
                                            if (!empty($plantillaJugador)) {
                                                $goldata = array(
                                                    'partido_id' => $partido->id,
                                                    'jugador_id' => $plantillaJugador->jugador->id,
                                                    'minuto' => intval(trim($incidencia[1])),
                                                    'tipo' => $tipogol
                                                );
                                                $gol = Gol::where('partido_id', '=', $partido->id)->where('jugador_id', '=', $plantillaJugador->jugador->id)->where('minuto', '=', intval(trim($incidencia[1])))->first();
                                                try {
                                                    if (!empty($gol)) {

                                                        $gol->update($goldata);
                                                    } else {
                                                        $gol = Gol::create($goldata);
                                                    }


                                                } catch (QueryException $ex) {
                                                    $error = $ex->getMessage();
                                                    $ok = 0;
                                                    continue;
                                                }
                                            }

                                        }
                                        $tipotarjeta='';
                                        switch (trim($incidencia[0])) {
                                            case 'Tarjeta amarilla':
                                                $tipotarjeta='Amarilla';
                                                break;
                                            case 'Expulsado por doble amarilla':
                                                $tipotarjeta='Doble Amarilla';
                                                break;
                                            case 'Tarjeta roja':
                                                $tipotarjeta='Roja';
                                                break;
                                        }
                                        if ($tipotarjeta){
                                            if (!empty($plantillaJugador)) {
                                                $tarjeadata=array(
                                                    'partido_id'=>$partido->id,
                                                    'jugador_id'=>$plantillaJugador->jugador->id,
                                                    'minuto'=>intval(trim($incidencia[1])),
                                                    'tipo'=>$tipotarjeta
                                                );
                                                $tarjeta=Tarjeta::where('partido_id','=',$partido->id)->where('jugador_id','=',$plantillaJugador->jugador->id)->where('minuto','=',intval(trim($incidencia[1])))->first();
                                                try {
                                                    if (!empty($tarjeta)){

                                                        $tarjeta->update($tarjeadata);
                                                    }
                                                    else{
                                                        $tarjeta=Tarjeta::create($tarjeadata);
                                                    }


                                                }catch(QueryException $ex){
                                                    $error = $ex->getMessage();
                                                    $ok=0;
                                                    continue;
                                                }
                                            }




                                        }
                                        $tipocambio='';
                                        switch (trim($incidencia[0])) {
                                            case 'Sale':
                                                $tipocambio='Sale';
                                                break;
                                            case 'Entra':
                                                $tipocambio='Entra';
                                                break;

                                        }
                                        if ($tipocambio){
                                            if (!empty($plantillaJugador)) {
                                                $cambiodata=array(
                                                    'partido_id'=>$partido->id,
                                                    'jugador_id'=>$plantillaJugador->jugador->id,
                                                    'minuto'=>intval(trim($incidencia[1])),
                                                    'tipo'=>$tipocambio
                                                );
                                                $cambio=Cambio::where('partido_id','=',$partido->id)->where('jugador_id','=',$plantillaJugador->jugador->id)->where('minuto','=',intval(trim($incidencia[1])))->first();
                                                try {
                                                    if (!empty($cambio)){

                                                        $cambio->update($cambiodata);
                                                    }
                                                    else{
                                                        $cambio=Cambio::create($cambiodata);
                                                    }


                                                }catch(QueryException $ex){
                                                    $error = $ex->getMessage();
                                                    $ok=0;
                                                    continue;
                                                }
                                            }




                                        }
                                    }

                                }
                            }
                        }
                        else{
                            Log::info('OJO!!! NO se encontró al equipo: ' . $strEquipo,[]);
                        }
                    }




            }
            else{
                $error = 'No se econtró la URL del partido: '.$partido->equipol->nombre.' VS '.$partido->equipov->nombre;
                $ok=0;
                continue;
            }




        }

        if ($ok){



            DB::commit();
            $respuestaID='success';
            $respuestaMSJ='Importación exitosa. (ver log)';
        }
        else{
            DB::rollback();
            $respuestaID='error';
            $respuestaMSJ=$error;
        }

        //
        return redirect()->route('fechas.index', array('grupoId' => $fecha->grupo->id))->with($respuestaID,$respuestaMSJ);

        //return view('fechas.index', compact('grupo'));
    }


    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function ver(Request $request)
    {
        $grupo_id= $request->query('grupoId');
        $nombre = $request->get('buscarpor');
        $grupo=Grupo::findOrFail($grupo_id);


        $fechas=Fecha::where('grupo_id','=',"$grupo_id")->where('numero','like',"%$nombre%")->orderBy('numero','ASC')->get();

        //dd($fechas);

        //print_r($fechas);

        return view('fechas.ver', compact('fechas','grupo'));
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function detalle(Request $request)
    {
        $partido_id= $request->query('partidoId');

        $partido=Partido::findOrFail($partido_id);

        $goles=Gol::where('partido_id','=',"$partido_id")->orderBy('minuto','ASC')->get();


        $titularesL=Alineacion::where('partido_id','=',"$partido_id")->where('equipo_id','=',$partido->equipol->id)->where('tipo','=',"Titular")->orderBy('orden', 'asc')->get();

        $suplentesL=Alineacion::where('partido_id','=',"$partido_id")->where('equipo_id','=',$partido->equipol->id)->where('tipo','=',"Suplente")->orderBy('orden', 'asc')->get();

        $titularesV=Alineacion::where('partido_id','=',"$partido_id")->where('equipo_id','=',$partido->equipov->id)->where('tipo','=',"Titular")->orderBy('orden', 'asc')->get();

        $suplentesV=Alineacion::where('partido_id','=',"$partido_id")->where('equipo_id','=',$partido->equipov->id)->where('tipo','=',"Suplente")->orderBy('orden', 'asc')->get();

        $torneo_id = $partido->fecha->grupo->torneo->id;



        $tarjetas=Tarjeta::where('partido_id','=',"$partido_id")->orderBy('minuto','ASC')->get();

        $cambios=Cambio::where('partido_id','=',"$partido_id")->orderBy('minuto','ASC')->get();

        //dd($partido->fecha->grupo->torneo->nombre);
        return view('fechas.detalle', compact('goles','partido', 'tarjetas','cambios','titularesL','suplentesL','titularesV','suplentesV'));
        //
    }

}
