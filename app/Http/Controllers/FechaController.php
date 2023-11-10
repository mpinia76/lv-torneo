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
use App\PartidoTecnico;
use App\Tarjeta;
use App\Tecnico;
use App\Torneo;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use DB;
use Illuminate\Support\Facades\Log;
use Sunra\PhpSimple\HtmlDomParser;
use Excel;

use Response;
use File;

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
        if ($request->has('buscarpor')){
            $nombre = $request->get('buscarpor');

            $request->session()->put('nombre_filtro_fecha', $request->get('buscarpor'));

        }
        else{
            $nombre = $request->session()->get('nombre_filtro_fecha');

        }
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

        $grupos = Grupo::where('torneo_id', '=',$grupo->torneo->id)->get();
        $arrgrupos='';
        foreach ($grupos as $grupo2){
            $arrgrupos .=$grupo2->id.',';
        }

        $equipos = Plantilla::with('equipo')->wherein('grupo_id',explode(',', $arrgrupos))->get()->pluck('equipo.nombre', 'equipo_id')->prepend('','');
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

                    if (($request->penalesl[$item]) && ($request->penalesv[$item])){
                        $data2=array(
                            'fecha_id'=>$lastid,
                            'dia'=>$request->fecha[$item].' '.$request->hora[$item],
                            'equipol_id'=>$request->equipol[$item],
                            'equipov_id'=>$request->equipov[$item],
                            'golesl'=>$request->golesl[$item],
                            'golesv'=>$request->golesv[$item],
                            'penalesl'=>$request->penalesl[$item],
                            'penalesv'=>$request->penalesv[$item]
                        );
                    }
                    else{
                        $data2=array(
                            'fecha_id'=>$lastid,
                            'dia'=>$request->fecha[$item].' '.$request->hora[$item],
                            'equipol_id'=>$request->equipol[$item],
                            'equipov_id'=>$request->equipov[$item],
                            'golesl'=>$request->golesl[$item],
                            'golesv'=>$request->golesv[$item]
                        );
                    }


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

        $grupos = Grupo::where('torneo_id', '=',$grupo->torneo->id)->get();
        $arrgrupos='';
        foreach ($grupos as $grupo2){
            $arrgrupos .=$grupo2->id.',';
        }

        $equipos = Plantilla::with('equipo')->wherein('grupo_id',explode(',', $arrgrupos))->get()->pluck('equipo.nombre', 'equipo_id')->prepend('','');

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
                    if (($request->penalesl[$item]) && ($request->penalesv[$item])){
                        $data2=array(
                            'fecha_id'=>$id,
                            'dia'=>$request->fecha[$item].' '.$request->hora[$item],
                            'equipol_id'=>$request->equipol[$item],
                            'equipov_id'=>$request->equipov[$item],
                            'golesl'=>$request->golesl[$item],
                            'golesv'=>$request->golesv[$item],
                            'penalesl'=>$request->penalesl[$item],
                            'penalesv'=>$request->penalesv[$item]
                        );
                    }
                    else{
                        $data2=array(
                            'fecha_id'=>$id,
                            'dia'=>$request->fecha[$item].' '.$request->hora[$item],
                            'equipol_id'=>$request->equipol[$item],
                            'equipov_id'=>$request->equipov[$item],
                            'golesl'=>$request->golesl[$item],
                            'golesv'=>$request->golesv[$item]
                        );
                    }

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

                while (($filedata = fgetcsv($file, 1000, "|")) !== FALSE) {
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
                    Log::channel('mi_log')->info('Fecha: '.$importData[0]);
                    $golesL = null;
                    $golesV = null;
                     $numero=$importData[0];
                    $grupo_id=intval($importData[1]);
                     if($numero){
                         $dia = $importData[2].' '.$importData[3];
                         $strEquipoL = ($importData[4]);
                         $resultado = $importData[5];
                         $goles = explode(":", $resultado);
                         //Log::debug(print_r($goles),[]);
                         if(count($goles)>1){
                             $golesL = intval($goles[0]);
                             $golesV = intval($goles[1]);
                         }



                         $equipol = Equipo::where('nombre', 'like', "%$strEquipoL%")->first();

                         if (!$equipol){
                             Log::channel('mi_log')->info('Equipo NO encontrado: '.$numero.'-'.$dia.'-'.$strEquipoL,[]);
                         }
                        else{
                            $strEquipoV = ($importData[6]);
                            $equipoV = Equipo::where('nombre', 'like', "%$strEquipoV%")->first();

                            if (!$equipoV){
                                Log::channel('mi_log')->info('Equipo NO encontrado: '.$numero.'-'.$dia.'-'.$strEquipoV,[]);
                            }
                            else{
                                /*$strArbitro = utf8_encode($importData[9]);
                                $arrArbitro = explode(" ", $strArbitro);

                                if(count($arrArbitro)>1) {
                                    $arbitro = Arbitro::orwhere('nombre', 'like', "%$arrArbitro[0]%")->orwhere('apellido', 'like', "%$arrArbitro[1]%")->first();
                                }

                                if (!$arbitro){
                                    Log::channel('mi_log')->info('Arbitro NO encontrado: '.$numero.'-'.$dia.'-'.$strArbitro,[]);
                                }*/
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
                                        /*if (!empty($arbitro)){
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
                                        }*/

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
                                        $grupos = Grupo::where('torneo_id', '=',$grupo->torneo->id)->get();
                                        $arrgrupos='';
                                        foreach ($grupos as $grupo){
                                            $arrgrupos .=$grupo->id.',';
                                        }


                                        $plantillas = Plantilla::wherein('grupo_id',explode(',', $arrgrupos))->where('equipo_id','=',$equipoL->id)->get();

                                        $arrplantillas='';
                                        foreach ($plantillas as $plantilla){
                                            $arrplantillas .=$plantilla->id.',';
                                        }


                                        $plantillaJugador = PlantillaJugador::wherein('plantilla_id',explode(',', $arrplantillas))->distinct()->where('dorsal','=',$jugador['dorsal'])->first();



                                            foreach ($jugador['incidencias'] as $incidencia) {
                                                if (count($incidencia)>1){
                                                    Log::channel('mi_log')->info('Incidencia: ' . trim($incidencia[0]).' MIN: '.intval(trim($incidencia[1])),[]);
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
                                                                Log::channel('mi_log')->info('NO se encontró al jugador: ' . $jugador['dorsal'].' del equipo '.$strEquipoL,[]);
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
                                                            Log::channel('mi_log')->info('NO se encontró al jugador: ' . $jugador['dorsal'].' del equipo '.$strEquipoL,[]);
                                                        }



                                                }


                                            }
                                        }


                                    }
                                    if (trim($equipos[$i - 1]['entrenador'])){
                                        $strEntrenador=trim($equipos[$i - 1]['entrenador']);
                                        $arrEntrenador = explode(' ', $strEntrenador);
                                        //$entrenadorL=Tecnico::where('nombre','like',"%$arrEntrenador[0]%")->where('apellido','like',"%$arrEntrenador[1]%")->first();
                                        $entrenadorL=Tecnico::SELECT('tecnicos.*')->Join('personas','personas.id','=','tecnicos.persona_id')->where('nombre','like',"%$arrEntrenador[0]%")->where('apellido','like',"%$arrEntrenador[1]%")->first();
                                        if (!empty($entrenadorL)){
                                            $plantillaTecnico = PartidoTecnico::where('plantilla_id','=',$plantilla->id)->where('tecnico_id','=',$entrenadorL->id)->first();
                                            if (empty($plantillaTecnico)){
                                                Log::channel('mi_log')->info('NO se encontró como entrenador: ' . $equipos[$i - 1]['entrenador'].' del equipo '.$equipos[$i - 1]['equipo'],[]);
                                            }
                                        }
                                        else{
                                            Log::channel('mi_log')->info('NO se encontró al entrenador: ' . $equipos[$i - 1]['entrenador'].' del equipo '.$equipos[$i - 1]['equipo'],[]);
                                        }
                                    }
                                    foreach ($equipos[$i]['jugadores'] as $jugador) {

                                        $grupos = Grupo::where('torneo_id', '=',$grupo->torneo->id)->get();
                                        $arrgrupos='';
                                        foreach ($grupos as $grupo){
                                            $arrgrupos .=$grupo->id.',';
                                        }

                                        $plantillas = Plantilla::wherein('grupo_id',explode(',', $arrgrupos))->where('equipo_id','=',$equipoV->id)->get();

                                        $arrplantillas='';
                                        foreach ($plantillas as $plantilla){
                                            $arrplantillas .=$plantilla->id.',';
                                        }


                                        $plantillaJugador = PlantillaJugador::wherein('plantilla_id',explode(',', $arrplantillas))->distinct()->where('dorsal','=',$jugador['dorsal'])->first();



                                        foreach ($jugador['incidencias'] as $incidencia) {
                                            if (count($incidencia)>1){
                                                Log::channel('mi_log')->info('Incidencia: ' . trim($incidencia[0]).' MIN: '.intval(trim($incidencia[1])),[]);
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
                                                        Log::channel('mi_log')->info('NO se encontró al jugador: ' . $jugador['dorsal'].' del equipo '.$strEquipoV,[]);
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
                                                        Log::channel('mi_log')->info('NO se encontró al jugador: ' . $jugador['dorsal'].' del equipo '.$strEquipoV,[]);
                                                    }



                                                }


                                            }
                                        }


                                    }
                                    if (trim($equipos[$i]['entrenador'])){
                                        $strEntrenador=trim($equipos[$i]['entrenador']);
                                        $arrEntrenador = explode(' ', $strEntrenador);
                                        //$entrenadorV=Tecnico::where('nombre','like',"%$arrEntrenador[0]%")->where('apellido','like',"%$arrEntrenador[1]%")->first();
                                        $entrenadorV=Tecnico::SELECT('tecnicos.*')->Join('personas','personas.id','=','tecnicos.persona_id')->where('nombre','like',"%$arrEntrenador[0]%")->where('apellido','like',"%$arrEntrenador[1]%")->first();
                                        if (!empty($entrenadorV)){
                                            $plantillaTecnico = PartidoTecnico::where('plantilla_id','=',$plantilla->id)->where('tecnico_id','=',$entrenadorV->id)->first();
                                            if (empty($plantillaTecnico)){
                                                Log::channel('mi_log')->info('NO se encontró como entrenador: ' . $equipos[$i]['entrenador'].' del equipo '.$equipos[$i]['equipo'],[]);
                                            }
                                        }
                                        else{
                                            Log::channel('mi_log')->info('NO se encontró al entrenador: ' . $equipos[$i]['entrenador'].' del equipo '.$equipos[$i]['equipo'],[]);
                                        }
                                    }



                                }
                                else{
                                    Log::channel('mi_log')->info('NO se encontró al partido: ' . $equipos[$i-1]['equipo'].' VS '.$equipos[$i]['equipo'],[]);
                                }
                            }
                            else{
                                Log::channel('mi_log')->info('NO se encontró al equipo: ' . $equipos[$i]['equipo'],[]);
                            }
                        }
                        else{
                            Log::channel('mi_log')->info('NO se encontró al equipo: ' . $equipos[$i-1]['equipo'],[]);
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
            case 'Arsenal':
                $strEquipoURL='Arsenal-Sarandi-Fc';
                break;
            case 'Atlético de Rafaela':
                $strEquipoURL='atletico-rafaela';
                break;
            case 'Atlético Tucumán':
                $strEquipoURL='Atletico-Tucuman';
                break;
            case 'Banfield':
                $strEquipoURL='Ca-Banfield';
                break;
            case 'Boca Juniors':
                $strEquipoURL='Ca-Boca-Juniors';
                break;
            case 'Central Córdoba (SdE)':
                $strEquipoURL='Central-Cordob';
                break;
            case 'Colón de Santa Fe':
                $strEquipoURL='Colon-Santa-Fe';
                break;
            case 'Crucero del Norte':
                $strEquipoURL='Crucero-Norte';
                break;
            case 'Defensa y Justicia':
                $strEquipoURL='Defensa-Justicia';
                break;
            case 'Estudiantes (LP)':
                $strEquipoURL='Estudiantes-Plata';
                break;
            case 'Gimnasia (J)':
                $strEquipoURL='Gimnasia-Jujuy';
                break;
            case 'Gimnasia (LP)':
                $strEquipoURL='Gimnasia-Plata';
                break;
            case 'Godoy Cruz':
                $strEquipoURL='Godoy-Cruz-Antonio-Tomba';
                break;
            case 'Huracán':
                $strEquipoURL='Ca-Huracan';
                break;
            case 'Huracán (Tres Arroyos)':
                $strEquipoURL='Huracan-Tres-A';
                break;
            case 'Instituto de Córdoba':
                $strEquipoURL='Instituto';
                break;
            case 'Lanús':
                $strEquipoURL='Ca-Lanus';
                break;
            case 'Newell\'s Old Boys':
                $strEquipoURL='Newells-Old-Boys';
                break;
            case 'Nueva Chicago':
                $strEquipoURL='Nueva-Chicago';
                break;
            case 'Racing Club':
                $strEquipoURL='Racing-Club-Avellaneda';
                break;
            case 'River Plate':
                $strEquipoURL='Ca-River-Plate';
                break;
            case 'Rosario Central':
                $strEquipoURL='Ca-Rosario-Central';
                break;
            case 'San Martín (SJ)':
                $strEquipoURL='San-Martin-San-Juan';
                break;
            case 'San Martín (Tuc.)':
                $strEquipoURL='San-Martin-Tucuman-Ar';
                break;
            case 'Sarmiento (Junín)':
                $strEquipoURL='sarmiento';
                break;
            case 'Talleres (Cba.)':
                $strEquipoURL='Talleres-Cordoba';
                break;
            case 'Tigre':
                $strEquipoURL='Ca-Tigre';
                break;
            case 'Tiro Federal de Rosario':
                $strEquipoURL='Tiro-Federal';
                break;
            case 'Unión de Santa Fe':
                $strEquipoURL='Union-Santa-Fe';
                break;
            case 'Vélez Sarsfield':
                $strEquipoURL='Ca-Velez-Sarsfield';
                break;

        }
        return $strEquipoURL;
    }

    public function dameNombreEquipoURL2($strEquipo)
    {
        $strEquipoURL=strtr($strEquipo, " ", "-");
        switch (trim($strEquipo)) {
            case 'Almagro':
                $strEquipoURL='almagro';
                //$strEquipoURL='club-almagro';
                break;
            case 'Arsenal':
                //$strEquipoURL='arsenal-sarandi';//viejo
                $strEquipoURL='arsenal-de-sarandi';//ultimo
                //$strEquipoURL='arsenal-fc';
                break;
            case 'Atlético de Rafaela':
                $strEquipoURL='atletico-rafaela';
                break;
            case 'Atlético Tucumán':
                $strEquipoURL='atletico-tucuman';
                break;

            case 'Banfield':
                $strEquipoURL='banfield';//ultimo
                //$strEquipoURL='ca-banfield';//viejo
                break;
            case 'Boca Juniors':
                $strEquipoURL='boca-juniors';
                //$strEquipoURL='ca-boca-juniors';
                break;
            case 'Belgrano':
                $strEquipoURL='belgrano-de-cordoba';//ultimo
                //$strEquipoURL='belgrano-cordoba';//viejo
                break;
            case 'Central Córdoba (SdE)':
                $strEquipoURL='central-cordoba-sde';
                break;
            case 'Chacarita Juniors':
                $strEquipoURL='chacarita-juniors';
                //$strEquipoURL='ca-chacarita-juniors';
                break;
            case 'Colón de Santa Fe':
                $strEquipoURL='colon-de-santa-fe';//ultimo
                //$strEquipoURL='ca-colon';//viejo
                break;
            case 'Crucero del Norte':
                $strEquipoURL='crucero-del-norte';
                break;
            case 'Defensa y Justicia':
                $strEquipoURL='defensa-y-justicia';
                //$strEquipoURL='csyd-defensa-y-justicia';
                break;
            case 'Estudiantes (LP)':
                $strEquipoURL='estudiantes';//ultimo
                //$strEquipoURL='estudiantes-de-la-plata';//viejo
                break;
            case 'Gimnasia (J)':
                $strEquipoURL='gye-jujuy';
                break;
            case 'Gimnasia (LP)':
                $strEquipoURL='gimnasia-de-la-plata';//ultimo
                //$strEquipoURL='gye-la-plata';//viejo
                //$strEquipoURL='gimnasia-y-esgrima-de-la-plata';//viejo
                break;
            case 'Godoy Cruz':
                $strEquipoURL='godoy-cruz';//ultimo
                //$strEquipoURL='cd-godoy-cruz';//viejo
                break;
            case 'Huracán':
                $strEquipoURL='huracan';
                //$strEquipoURL='ca-huracan';
                break;
            case 'Huracán (Tres Arroyos)':
                $strEquipoURL='huracan-de-tres-arroyos';

                break;
            case 'Instituto de Córdoba':
                //$strEquipoURL='instituto-cordoba';
                $strEquipoURL='instituto-de-cordoba';
                break;
            case 'Independiente':
                //$strEquipoURL='ca-independiente';//viejo
                $strEquipoURL='independiente';//ultimo
                break;
            case 'Lanús':
                $strEquipoURL='lanus';//ultimo
                //$strEquipoURL='ca-lanus';//viejo
                break;
            case 'Los Andes':
                $strEquipoURL='los-andes-de-lomas-de-zamora';
                break;
            case 'Newell\'s Old Boys':
                $strEquipoURL='newells-old-boys';//ultimo
                //$strEquipoURL='ca-newells-old-boys';//viejo
                break;
            case 'Nueva Chicago':
                $strEquipoURL='nueva-chicago';
                break;
            case 'Olimpo':
                $strEquipoURL='olimpo';//ultimo
                //$strEquipoURL='olimpo-de-bahia-blanca';//viejo
                break;
            case 'Patronato':
                $strEquipoURL='patronato-de-parana';//ultimo
                //$strEquipoURL='patronato';//viejo
                break;
            case 'Quilmes':
                $strEquipoURL='quilmes-ac';
                break;
            case 'Racing Club':
                $strEquipoURL='racing-club';//ultimo
                //$strEquipoURL='racing-club-de-avellaneda';//viejo
                break;
            case 'River Plate':
                $strEquipoURL='river-plate';
                //$strEquipoURL='ca-river-plate';
                break;
            case 'Rosario Central':
                $strEquipoURL='rosario-central';//ultimo
                //$strEquipoURL='ca-rosario-central';//viejo
                break;
            case 'San Lorenzo':
                $strEquipoURL='san-lorenzo';
                //$strEquipoURL='ca-san-lorenzo-de-almagro';
                //$strEquipoURL='san-lorenzo-de-almagro';
                break;
            case 'San Martín (SJ)':
                $strEquipoURL='san-martin-de-san-juan';//ultimo
                //$strEquipoURL='ca-san-martin';//viejo
                break;
            case 'San Martín (Tuc.)':
                $strEquipoURL='san-martin-de-tucuman';//ultimo
                //$strEquipoURL='san-martin-tucuman';//viejo
                break;
            case 'Sarmiento (Junín)':
                $strEquipoURL='sarmiento-de-junin';
                break;
            case 'Talleres (Cba.)':
                $strEquipoURL='talleres-de-cordoba';//ultimo
                //$strEquipoURL='talleres-cordoba';//viejo
                break;
            case 'Temperley':
                //$strEquipoURL='ca-temperley';//viejo
                $strEquipoURL='temperley';//ultimo
                break;
            case 'Tigre':
                $strEquipoURL='tigre';
                //$strEquipoURL='ca-tigre';
                break;
            case 'Tiro Federal de Rosario':
                $strEquipoURL='tiro-federal-rosario';
                break;
            case 'Unión de Santa Fe':
                $strEquipoURL='union-de-santa-fe';
                break;
            case 'Vélez Sarsfield':
                $strEquipoURL='velez-sarsfield';
                //$strEquipoURL='ca-velez-sarsfield';
                break;
        }
        return $strEquipoURL;
    }
    public function dameNombreEquipoURL2ALT($strEquipo)
    {
        $strEquipoURL=strtr($strEquipo, " ", "-");
        switch (trim($strEquipo)) {
            case 'Almagro':
                $strEquipoURL='almagro';
                //$strEquipoURL='club-almagro';
                break;
            case 'Arsenal':
                $strEquipoURL='arsenal-sarandi';//viejo
                //$strEquipoURL='arsenal-de-sarandi';//ultimo
                //$strEquipoURL='arsenal-fc';
                break;
            case 'Atlético de Rafaela':
                $strEquipoURL='atletico-rafaela';
                break;
            case 'Atlético Tucumán':
                $strEquipoURL='atletico-tucuman';
                break;

            case 'Banfield':
                //$strEquipoURL='banfield';//ultimo
                $strEquipoURL='ca-banfield';//viejo
                break;
            case 'Boca Juniors':
                $strEquipoURL='boca-juniors';
                //$strEquipoURL='ca-boca-juniors';
                break;
            case 'Belgrano':
                //$strEquipoURL='belgrano-de-cordoba';//nuevo
                $strEquipoURL='belgrano-cordoba';//viejo
                break;
            case 'Central Córdoba (SdE)':
                $strEquipoURL='central-cordoba-sde';
                break;
            case 'Chacarita Juniors':
                $strEquipoURL='chacarita-juniors';
                //$strEquipoURL='ca-chacarita-juniors';
                break;
            case 'Colón de Santa Fe':
                //$strEquipoURL='colon-de-santa-fe';//ultimo
                $strEquipoURL='ca-colon';//viejo
                break;
            case 'Crucero del Norte':
                $strEquipoURL='crucero-del-norte';
                break;
            case 'Defensa y Justicia':
                $strEquipoURL='defensa-y-justicia';
                //$strEquipoURL='csyd-defensa-y-justicia';
                break;
            case 'Estudiantes (LP)':
                //$strEquipoURL='estudiantes';//nuevo
                $strEquipoURL='estudiantes-de-la-plata';//viejo
                break;
            case 'Gimnasia (J)':
                $strEquipoURL='gye-jujuy';
                break;
            case 'Gimnasia (LP)':
                //$strEquipoURL='gimnasia-de-la-plata';//ultimo
                $strEquipoURL='gye-la-plata';//viejo
                //$strEquipoURL='gimnasia-y-esgrima-de-la-plata';//viejo
                break;
            case 'Godoy Cruz':
                //$strEquipoURL='godoy-cruz';//ultimo
                $strEquipoURL='cd-godoy-cruz';//viejo
                break;
            case 'Huracán':
                $strEquipoURL='huracan';
                //$strEquipoURL='ca-huracan';
                break;
            case 'Huracán (Tres Arroyos)':
                $strEquipoURL='huracan-de-tres-arroyos';

                break;
            case 'Instituto de Córdoba':
                //$strEquipoURL='instituto-cordoba';
                $strEquipoURL='instituto-de-cordoba';
                break;
            case 'Independiente':
                $strEquipoURL='ca-independiente';//viejo
                //$strEquipoURL='independiente';//nuevo
                break;
            case 'Lanús':
                //$strEquipoURL='lanus';//ultimo
                $strEquipoURL='ca-lanus';//viejo
                break;
            case 'Los Andes':
                $strEquipoURL='los-andes-de-lomas-de-zamora';
                break;
            case 'Newell\'s Old Boys':
                //$strEquipoURL='newells-old-boys';//nuevo
                $strEquipoURL='ca-newells-old-boys';//viejo
                break;
            case 'Nueva Chicago':
                $strEquipoURL='nueva-chicago';
                break;
            case 'Olimpo':
                $strEquipoURL='olimpo';//nuevo
                //$strEquipoURL='olimpo-de-bahia-blanca';//viejo
                break;
            case 'Patronato':
                //$strEquipoURL='patronato-de-parana';//ultimo
                $strEquipoURL='patronato';//viejo
                break;
            case 'Quilmes':
                $strEquipoURL='quilmes-ac';
                break;
            case 'Racing Club':
                //$strEquipoURL='racing-club';//nuevo
                $strEquipoURL='racing-club-de-avellaneda';//viejo
                break;
            case 'River Plate':
                $strEquipoURL='river-plate';
                //$strEquipoURL='ca-river-plate';
                break;
            case 'Rosario Central':
                //$strEquipoURL='rosario-central';//nuevo
                $strEquipoURL='ca-rosario-central';//viejo
                break;
            case 'San Lorenzo':
                $strEquipoURL='san-lorenzo';
                //$strEquipoURL='ca-san-lorenzo-de-almagro';
                //$strEquipoURL='san-lorenzo-de-almagro';
                break;
            case 'San Martín (SJ)':
                //$strEquipoURL='san-martin-de-san-juan';//nuevo
                $strEquipoURL='ca-san-martin';//viejo
                break;
            case 'San Martín (Tuc.)':
                //$strEquipoURL='san-martin-de-tucuman';//ultimo
                $strEquipoURL='san-martin-tucuman';//viejo
                break;
            case 'Sarmiento (Junín)':
                $strEquipoURL='sarmiento-de-junin';
                break;
            case 'Talleres (Cba.)':
                //$strEquipoURL='talleres-de-cordoba';//nuevo
                $strEquipoURL='talleres-cordoba';//viejo
                break;
            case 'Temperley':
                $strEquipoURL='ca-temperley';//viejo
                //$strEquipoURL='temperley';//nuevo
                break;
            case 'Tigre':
                $strEquipoURL='tigre';
                //$strEquipoURL='ca-tigre';
                break;
            case 'Tiro Federal de Rosario':
                $strEquipoURL='tiro-federal-rosario';
                break;
            case 'Unión de Santa Fe':
                $strEquipoURL='union-de-santa-fe';
                break;
            case 'Vélez Sarsfield':
                $strEquipoURL='velez-sarsfield';
                //$strEquipoURL='ca-velez-sarsfield';
                break;
        }
        return $strEquipoURL;
    }
    public function dameIdEquipoURL($strEquipo)
    {
        $strEquipoURL=strtr($strEquipo, " ", "-");
        switch (trim($strEquipo)) {
            case 'Acassuso':
                $strEquipoURL='1791';

                break;
            case 'Agropecuario Argentino':
                $strEquipoURL='2011';

                break;
            case 'Aldosivi':
                $strEquipoURL='158';

                break;
            case 'Alianza de Coronel Moldes':
                $strEquipoURL='1998';

                break;
            case 'All Boys':
                $strEquipoURL='37';

                break;
            case 'Almagro':
                $strEquipoURL='74';

                break;
            case 'Almirante Brown':
                $strEquipoURL='685';

                break;
            case 'Alvarado':
                $strEquipoURL='1236';

                break;
            case 'Altos Hornos Zapla':
                $strEquipoURL='1855';

                break;
            case 'Andino de La Rioja':
                $strEquipoURL='1988';

                break;
            case 'Argentino de Merlo':
                $strEquipoURL='1814';
                break;
            case 'Argentinos Juniors':
                $strEquipoURL='28';
                break;
            case 'Arsenal':

                $strEquipoURL='23';//ultimo

                break;
            case 'Atlanta':
                $strEquipoURL='30';

                break;
            case 'Atlas':
                $strEquipoURL='1771';

                break;
            case 'Atlético de Rafaela':
                $strEquipoURL='26';
                break;
            case 'Atlético Paraná':
                $strEquipoURL='1851';

                break;
            case 'Atlético Policial':
                $strEquipoURL='1826';//ultimo

                break;
            case 'Atlético Tucumán':
                $strEquipoURL='39';
                break;

            case 'Banfield':
                $strEquipoURL='19';//ultimo

                break;
            case 'Barracas Central':
                $strEquipoURL='1834';//ultimo

                break;
            case 'Belgrano':
                $strEquipoURL='12';

                break;
            case 'Boca Juniors':
                $strEquipoURL='3';

                break;
            case 'Boca Unidos':
                $strEquipoURL='1203';

                break;
            case 'Brown de Adrogué':
                $strEquipoURL='1792';

                break;
            case 'Camioneros':
                $strEquipoURL='2162';

                break;
            case 'Cañuelas':
                $strEquipoURL='1778';

                break;
            case 'Central Córdoba (Rosario)':
                $strEquipoURL='1877';
                break;
            case 'Central Ballester':
                $strEquipoURL='1780';
                break;
            case 'Central Córdoba (SdE)':
                $strEquipoURL='1883';
                break;
            case 'Central Norte':
                $strEquipoURL='1868';

                break;
            case 'Chacarita Juniors':
                $strEquipoURL='18';

                break;
            case 'Chaco For Ever':
                $strEquipoURL='1828';

                break;
            case 'Cipolletti':
                $strEquipoURL='1866';
                break;
            case 'Claypole':
                $strEquipoURL='1772';
                break;
            case 'Club Luján':
                $strEquipoURL='1812';
                break;
            case 'Colegiales':
                $strEquipoURL='1815';
                break;
            case 'Colón de Santa Fe':
                $strEquipoURL='16';//ultimo

                break;
            case 'Comunicaciones':
                $strEquipoURL='1835';
                break;
            case 'Crucero del Norte':
                $strEquipoURL='1861';
                break;
            case 'Defensa y Justicia':
                $strEquipoURL='152';

                break;
            case 'Defensores de Belgrano':
                $strEquipoURL='1719';//ultimo

                break;
            case 'Defensores de Pronunciamiento':
                $strEquipoURL='1852';//ultimo

                break;
            case 'Defensores de Villa Ramallo':
                $strEquipoURL='1824';//ultimo

                break;
            case 'Defensores Unidos de Zárate':
                $strEquipoURL='1825';//ultimo

                break;
            case 'Deportivo Armenio':
                $strEquipoURL='1802';

                break;
            case 'Deportivo Español':
                $strEquipoURL='32';

                break;
            case 'Deportivo La Emilia':
                $strEquipoURL='1807';

                break;
            case 'Deportivo Madryn':
                $strEquipoURL='1832';

                break;
            case 'Deportivo Maipú':
                $strEquipoURL='990';

                break;
            case 'Deportivo Merlo':
                $strEquipoURL='1182';

                break;
            case 'Deportivo Morón':
                $strEquipoURL='990';

                break;
            case 'Deportivo Riestra':
                $strEquipoURL='1776';

                break;
            case 'Deportivo Rincón':
                $strEquipoURL='2725';

                break;
            case 'Deportivo Roca':
                $strEquipoURL='1867';

                break;
            case 'Desamparados':
                $strEquipoURL='1720';

                break;
            case 'Dock Sud':
                $strEquipoURL='1799';

                break;
            case 'Douglas Haig':
                $strEquipoURL='1817';

                break;
            case 'El Porvenir':
                $strEquipoURL='156';

                break;
            case 'Estudiantes (BA)':
                $strEquipoURL='1798';

                break;
            case 'Estudiantes de Río Cuarto':
                $strEquipoURL='1844';

                break;
            case 'Estudiantes de San Luis':
                $strEquipoURL='1997';

                break;
            case 'Estudiantes (LP)':
                $strEquipoURL='14';

                break;
            case 'Excursionistas':
                $strEquipoURL='1836';

                break;
            case 'Ferro':
                $strEquipoURL='27';

                break;
            case 'Flandria':
                $strEquipoURL='1805';

                break;
            case 'General Lamadrid':
                $strEquipoURL='1837';
                break;
            case 'Gimnasia (CdU)':
                $strEquipoURL='1847';
                break;
            case 'Gimnasia de Mendoza':
                $strEquipoURL='1859';
                break;
            case 'Gimnasia (J)':
                $strEquipoURL='34';
                break;
            case 'Gimnasia (LP)':
                $strEquipoURL='15';//ultimo

                break;
            case 'Gimnasia y Tiro de Salta':
                $strEquipoURL='36';//ultimo

                break;
            case 'Guaraní Antonio Franco':
                $strEquipoURL='1862';//ultimo

                break;
            case 'Güemes de SdE':
                $strEquipoURL='1995';//ultimo

                break;
            case 'Guillermo Brown':
                $strEquipoURL='1753';//ultimo

                break;
            case 'Godoy Cruz':
                $strEquipoURL='153';//ultimo

                break;

            case 'Guaymallén':
                $strEquipoURL='1860';

                break;
            case 'Huracán':
                $strEquipoURL='8';

                break;
            case 'Huracán de Las Heras':
                $strEquipoURL='1858';

                break;
            case 'Huracán (Tres Arroyos)':
                $strEquipoURL='75';

                break;
            case 'Instituto de Córdoba':
                $strEquipoURL='13';

                break;
            case 'Independiente':

                $strEquipoURL='5';
                break;
            case 'Independiente de Chivilcoy':

                $strEquipoURL='1992';
                break;
            case 'Independiente de Neuquén':

                $strEquipoURL='2004';
                break;
            case 'Independiente Rivadavia':

                $strEquipoURL='684';
                break;
            case 'Jorge Gibson Brown':

                $strEquipoURL='1863';
                break;
            case 'Justo José de Urquiza':

                $strEquipoURL='1811';
                break;
            case 'Juventud Antoniana':

                $strEquipoURL='159';
                break;
            case 'Juventud Unida de Gualeguaychú':

                $strEquipoURL='1850';
                break;
            case 'Juventud Unida Universitario':

                $strEquipoURL='1873';
                break;
            case 'Laferrere':
                $strEquipoURL='1808';//ultimo

                break;
            case 'Lanús':
                $strEquipoURL='20';//ultimo

                break;
            case 'Leandro N. Alem':
                $strEquipoURL='1804';//ultimo

                break;
            case 'Liniers':
                $strEquipoURL='1794';//ultimo

                break;
            case 'Liniers de La Matanza':
                $strEquipoURL='1819';//ultimo

                break;
            case 'Los Andes':
                $strEquipoURL='936';
                break;
            case 'Midland':
                $strEquipoURL='1809';

                break;
            case 'Mitre de SdE':
                $strEquipoURL='1994';

                break;
            case 'Newell\'s Old Boys':
                $strEquipoURL='10';

                break;
            case 'Nueva Chicago':
                $strEquipoURL='25';
                break;
            case 'Olimpo':

                $strEquipoURL='21';
                break;
            case 'Pacífico de Gral. Alvear':
                $strEquipoURL='1990';

                break;
            case 'Patronato':
                $strEquipoURL='1181';

                break;
            case 'Peñarol de Chimbas':
                $strEquipoURL='2027';

                break;
            case 'Platense':
                $strEquipoURL='29';

                break;

            case 'Quilmes':
                $strEquipoURL='24';
                break;
            case 'Racing Club':
                $strEquipoURL='6';

                break;
            case 'Racing de Córdoba':
                $strEquipoURL='33';
                break;
            case 'Racing (Olavarría)':
                $strEquipoURL='1502';
                break;
            case 'Racing (Trelew)':
                $strEquipoURL='1833';

                break;
            case 'Ramón Santamarina':
                $strEquipoURL='1481';
                break;
            case 'Real Pilar':
                $strEquipoURL='2795';
                break;
            case 'Rivadavia de Venado Tuerto':
                $strEquipoURL='2024';

                break;
            case 'River Plate':
                $strEquipoURL='4';

                break;
            case 'Rosario Central':
                $strEquipoURL='9';

                break;
            case 'Sacachispas':
                $strEquipoURL='1838';

                break;
            case 'San Lorenzo':
                $strEquipoURL='7';

                break;
            case 'San Martín de Formosa':
                $strEquipoURL='1853';

                break;
            case 'San Martín (SJ)':
                $strEquipoURL='157';

                break;
            case 'San Martín (Tuc.)':
                $strEquipoURL='38';//ultimo

                break;
            case 'Sansinena de General Cerri':
                $strEquipoURL='2534';//ultimo

                break;
            case 'San Telmo':
                $strEquipoURL='1800';//ultimo

                break;
            case 'Sarmiento (Junín)':
                $strEquipoURL='1474';
                break;
            case 'Sarmiento (Resistencia)':
                $strEquipoURL='1829';

                break;
            case 'Sol de América (Formosa)':
                $strEquipoURL='2001';

                break;
            case 'Sol de Mayo de Viedma':
                $strEquipoURL='2000';

                break;
            case 'Sportivo Barracas':
                $strEquipoURL='1782';

                break;
            case 'Sportivo Belgrano':
                $strEquipoURL='1845';

                break;
            case 'Sportivo Las Parejas':
                $strEquipoURL='2018';

                break;
            case 'Sportivo Italiano':
                $strEquipoURL='1196';

                break;
            case 'Talleres (Cba.)':
                $strEquipoURL='11';//nuevo

                break;
            case 'Talleres de Remedios de Escalada':
                $strEquipoURL='1818';//nuevo

                break;
            case 'Temperley':

                $strEquipoURL='35';
                break;
            case 'Tigre':
                $strEquipoURL='31';

                break;
            case 'Tiro Federal de Rosario':
                $strEquipoURL='149';
                break;
            case 'Tristán Suárez':
                $strEquipoURL='1803';

                break;
            case 'UAI Urquiza':
                $strEquipoURL='1823';

                break;
            case 'Unión de Aconquija':
                $strEquipoURL='2010';
                break;
            case 'Unión de Santa Fe':
                $strEquipoURL='17';
                break;
            case 'Unión de Sunchales':
                $strEquipoURL='1880';
                break;
            case 'Unión de Villa Krause':
                $strEquipoURL='1870';
                break;
            case 'Vélez Sarsfield':
                $strEquipoURL='22';

                break;
            case 'Viale FBC':
                $strEquipoURL='2189';
                break;
            case 'Villa Dálmine':
                $strEquipoURL='1797';
                break;
            case 'Villa Mitre de Bahía Blanca':
                $strEquipoURL='257';
                break;
            case 'Villa San Carlos':
                $strEquipoURL='1796';
                break;
            default:
                Log::channel('mi_log')->info('Ojo!!! falta equipo: '.$strEquipoURL, []);
                break;
        }
        return $strEquipoURL;
    }
    public function dameNombreEquipoURL3($strEquipo)
    {
        $strEquipoURL=strtr($strEquipo, " ", "-");
        $arrEquipo = array();
        switch (trim($strEquipo)) {
            case 'Acassuso':
                $arrEquipo[]='Acassuso';

                break;
            case 'Agropecuario Argentino':
                $arrEquipo[]='agropecuario';

                break;
            case 'Aldosivi':
                $arrEquipo[]='aldosivi';

                break;
            case 'Alianza de Coronel Moldes':
                $arrEquipo[]='alianza-cm';

                break;
            case 'All Boys':
                $arrEquipo[]='all-boys';

                break;
            case 'Almagro':
                $arrEquipo[]='almagro';
                $arrEquipo[]='club-almagro';
                break;
            case 'Almirante Brown':
                $arrEquipo[]='brown';

                break;
            case 'Altos Hornos Zapla':
                $arrEquipo[]='altos-hornos';

                break;
            case 'Alvarado':
                $arrEquipo[]='alvarado';

                break;
            case 'Argentino de Merlo':
                $arrEquipo[]='arg.-de-merlo';

                break;
            case 'Arsenal':
                $arrEquipo[]='arsenal-sarandi';
                $arrEquipo[]='arsenal';
                $arrEquipo[]='arsenal-fc';
                break;
            case 'Atlanta':
                $arrEquipo[]='atlanta';

                break;
            case 'Atlas':
                $arrEquipo[]='atlas-a';

                break;
            case 'Atlético de Rafaela':
                $arrEquipo[]='atl-rafaela';
                break;
            case 'Atlético Paraná':
                $arrEquipo[]='atl-parana';
                break;
            case 'Atlético Policial':
                $arrEquipo[]='policial';
                break;
            case 'Atlético Tucumán':
                $arrEquipo[]='atl-tucuman';
                break;
            case 'Argentinos Juniors':
                $arrEquipo[]='argentinos';
                break;
            case 'Banfield':
                $arrEquipo[]='banfield';
                $arrEquipo[]='ca-banfield';
                break;
            case 'Barracas Central':
                $arrEquipo[]='barracas-cent';
                break;

            case 'Belgrano':
                $arrEquipo[]='belgrano';
                $arrEquipo[]='belgrano-cordoba';
                break;
            case 'Boca Juniors':
                $arrEquipo[]='boca';
                $arrEquipo[]='ca-boca-juniors';
                break;
            case 'Boca Unidos':
                $arrEquipo[]='boca-unidos';

                break;
            case 'Brown de Adrogué':
                $arrEquipo[]='brown';

                break;
            case 'Camioneros':
                $arrEquipo[]='camioneros';

                break;
            case 'Cañuelas':
                $arrEquipo[]='canuelas';

                break;
            case 'Central Ballester':
                $arrEquipo[]='c.-ballester';
                break;
            case 'Central Córdoba (Rosario)':
                $arrEquipo[]='c-cordoba-sf';
                break;
            case 'Central Córdoba (SdE)':
                $arrEquipo[]='central-cordoba-sde';
                break;
            case 'Central Norte':
                $arrEquipo[]='central-norte';
                break;
            case 'Chacarita Juniors':
                $arrEquipo[]='chacarita';
                $arrEquipo[]='ca-chacarita-juniors';
                break;
            case 'Chaco For Ever':
                $arrEquipo[]='chaco-for-ever';

                break;
            case 'Claypole':
                $arrEquipo[]='claypole';

                break;
            case 'Club Luján':
                $arrEquipo[]='lujan';

                break;
            case 'Cipolletti':
                $arrEquipo[]='cipolletti';

                break;
            case 'Colón de Santa Fe':
                $arrEquipo[]='colon';
                $arrEquipo[]='ca-colon';
                break;
            case 'Colegiales':
                $arrEquipo[]='colegiales';
                break;
            case 'Crucero del Norte':
                $arrEquipo[]='crucero-del-norte';
                break;
            case 'Defensa y Justicia':
                $arrEquipo[]='defensa-y-justicia';
                $arrEquipo[]='csyd-defensa-y-justicia';
                $arrEquipo[]='defensa-y-just';
                break;
            case 'Defensores de Belgrano':
                $arrEquipo[]='def-belgrano';

                break;
            case 'Defensores de Pronunciamiento':
                $arrEquipo[]='depro';

                break;
            case 'Defensores de Villa Ramallo':
                $arrEquipo[]='defensores-ram';

                break;
            case 'Defensores Unidos de Zárate':
                $arrEquipo[]='defensores-un.';

                break;

            case 'Deportivo Armenio':
                $arrEquipo[]='dep-armenio';

                break;
            case 'Deportivo Español':
                $arrEquipo[]='social-espanol';

                break;
            case 'Deportivo Madryn':
                $arrEquipo[]='dep.-madryn';
                break;
            case 'Deportivo Maipú':
                $arrEquipo[]='deportivo-maipu';

                break;
            case 'Deportivo Merlo':
                $arrEquipo[]='deportivo-merlo';

                break;
            case 'Deportivo Morón':
                $arrEquipo[]='deportivo-moron';

                break;
            case 'Deportivo Riestra';
                $arrEquipo[]='dep.-riestra';

                break;
            case 'Deportivo Rincón';
                $arrEquipo[]='dep.-rincon';

                break;
            case 'Deportivo Roca';
                $arrEquipo[]='deportivo-roca';

                break;
            case 'Dock Sud':
                $arrEquipo[]='dock-sud';
            case 'Douglas Haig':
                $arrEquipo[]='douglas-haig';

                break;
            case 'Estudiantes (BA)':
                $arrEquipo[]='estudiantes-ba';

                break;
            case 'Estudiantes de Río Cuarto':
                $arrEquipo[]='estudiantes-rio';

                break;
            case 'Estudiantes de San Luis':
                $arrEquipo[]='sp.-estudiantes';

                break;
            case 'Estudiantes (LP)':
                $arrEquipo[]='estudiantes';
                $arrEquipo[]='estudiantes-de-la-plata';
                break;
            case 'Excursionistas':
                $arrEquipo[]='excursionistas';

                break;
            case 'Ferro':
                $arrEquipo[]='Ferro';

                break;
            case 'General Lamadrid':
                $arrEquipo[]='lamadrid';
                break;
            case 'Gimnasia (CdU)':
                $arrEquipo[]='gimnasia-con';
                break;
            case 'Gimnasia de Mendoza':
                $arrEquipo[]='gimnasia-men.';
                break;
            case 'Gimnasia (J)':
                $arrEquipo[]='gimnasia-juj';
                break;
            case 'Gimnasia (LP)':
                $arrEquipo[]='gimnasia';
                $arrEquipo[]='gye-la-plata';
                $arrEquipo[]='gimnasia-y-esgrima-de-la-plata';
                break;
            case 'Gimnasia y Tiro de Salta':
                $arrEquipo[]='gimnasia-y_tiro';
                break;
            case 'Godoy Cruz':
                $arrEquipo[]='godoy-cruz';
                $arrEquipo[]='cd-godoy-cruz';
                break;
            case 'Guaraní Antonio Franco':
                $arrEquipo[]='guarani-a-f';

                break;
            case 'Güemes de SdE':
                $arrEquipo[]='guemes';

                break;
            case 'Guillermo Brown':
                $arrEquipo[]='guillermo-brown';

                break;
            case 'Huracán':
                $arrEquipo[]='huracan';
                $arrEquipo[]='ca-huracan';
                break;
            case 'Huracán de Las Heras':
                $arrEquipo[]='huracan-men';

                break;
            case 'Huracán (Tres Arroyos)':
                $arrEquipo[]='huracan-ta';

                break;
            case 'Instituto de Córdoba':
                $arrEquipo[]='instituto';
                $arrEquipo[]='instituto-de-cordoba';
                break;
            case 'Independiente':
                $arrEquipo[]='ca-independiente';
                $arrEquipo[]='independiente';
                break;
            case 'Independiente de Chivilcoy':
                $arrEquipo[]='indep.-chiv.';

                break;
            case 'Independiente Rivadavia':
                $arrEquipo[]='ind.-rivadavia';

                break;
            case 'Justo José de Urquiza':
                $arrEquipo[]='j.j.-urquiza';

                break;
            case 'Juventud Unida de Gualeguaychú':
                $arrEquipo[]='juv-unida-g';

                break;
            case 'Juventud Unida Universitario':
                $arrEquipo[]='juv-unida-univ';

                break;
            case 'Laferrere':
                $arrEquipo[]='dep.-laferrere';

                break;
            case 'Lanús':
                $arrEquipo[]='lanus';
                $arrEquipo[]='ca-lanus';
                break;
            case 'Leandro N. Alem':
                $arrEquipo[]='alem';

                break;
            case 'Liniers':
                $arrEquipo[]='liniers-bb';

                break;
            case 'Liniers de La Matanza':
                $arrEquipo[]='liniers-ba';

                break;
            case 'Los Andes':
                $arrEquipo[]='los-andes-de-lomas-de-zamora';
                $arrEquipo[]='los-andes';
                break;
            case 'Midland':
                $arrEquipo[]='midland';

                break;
            case 'Mitre de SdE':
                $arrEquipo[]='a.mitre';

                break;
            case 'Newell\'s Old Boys':
                $arrEquipo[]='newells';
                $arrEquipo[]='ca-newells-old-boys';
                break;
            case 'Nueva Chicago':
                $arrEquipo[]='nueva-chicago';
                break;
            case 'Olimpo':
                $arrEquipo[]='olimpo';

                break;
            case 'Pacífico de Gral. Alvear':
                $arrEquipo[]='pacifico';
                break;
            case 'Patronato':
                $arrEquipo[]='patronato-de-parana';
                $arrEquipo[]='patronato';
                break;
            case 'Peñarol de Chimbas':
                $arrEquipo[]='sp.-penarol';
                break;
            case 'Platense':
                $arrEquipo[]='platense';
                break;
            case 'Quilmes':
                $arrEquipo[]='quilmes';
                break;
            case 'Racing Club':
                $arrEquipo[]='racing';
                $arrEquipo[]='racing-club-de-avellaneda';
                break;
            case 'Racing de Córdoba':
                $arrEquipo[]='racing-cordoba';
            case 'Racing (Olavarría)':
                $arrEquipo[]='racing-olav';

                break;
            case 'Racing (Trelew)':
                $arrEquipo[]='racing-tre';

                break;
            case 'Ramón Santamarina':
                $arrEquipo[]='santamarina';

                break;
            case 'Real Pilar':
                $arrEquipo[]='real-pilar';

                break;
            case 'Rivadavia de Venado Tuerto':
                $arrEquipo[]='sp.-rivadavia';

                break;
            case 'River Plate':
                $arrEquipo[]='river';
                $arrEquipo[]='ca-river-plate';
                break;
            case 'Rosario Central':
                $arrEquipo[]='rosario-central';
                $arrEquipo[]='ca-rosario-central';
                break;
            case 'Sacachispas':
                $arrEquipo[]='sacachispas';

                break;
            case 'San Lorenzo':
                $arrEquipo[]='san-lorenzo';
                $arrEquipo[]='ca-san-lorenzo-de-almagro';
                $arrEquipo[]='san-lorenzo-de-almagro';
                break;
            case 'San Martín de Formosa':
                $arrEquipo[]='sp.-san-martin';

                break;
            case 'San Martín (SJ)':
                $arrEquipo[]='san-martin-sj';
                $arrEquipo[]='ca-san-martin';
                break;
            case 'San Martín (Tuc.)':
                $arrEquipo[]='san-martin-t';
                $arrEquipo[]='san-martin-tucuman';
                break;
            case 'Sansinena de General Cerri':
                $arrEquipo[]='sansinena';

                break;
            case 'San Telmo':
                $arrEquipo[]='san-telmo';

                break;
            case 'Sarmiento (Junín)':
                $arrEquipo[]='sarmiento-de-junin';
                $arrEquipo[]='sarmiento-ju';
                break;
            case 'Sarmiento (Resistencia)':
                $arrEquipo[]='sarmiento-re';

                break;
            case 'Sol de Mayo de Viedma':
                $arrEquipo[]='sol-de-mayo';

                break;
            case 'Sportivo Barracas':
                $arrEquipo[]='sp.-barracas';

                break;
            case 'Sportivo Belgrano':
                $arrEquipo[]='sp-belgrano';

                break;
            case 'Sportivo Las Parejas':
                $arrEquipo[]='sp.-las-parejas';

                break;
            case 'Talleres (Cba.)':
                $arrEquipo[]='talleres';
                $arrEquipo[]='talleres-cordoba';
                break;
            case 'Talleres de Remedios de Escalada':
                $arrEquipo[]='talleres-rem.';

                break;
            case 'Temperley':
                $arrEquipo[]='ca-temperley';
                $arrEquipo[]='temperley';
                break;
            case 'Tigre':
                $arrEquipo[]='tigre';
                $arrEquipo[]='ca-tigre';
                break;
            case 'Tiro Federal de Rosario':
                $arrEquipo[]='tiro-federal';
                break;
            case 'Tristán Suárez':
                $arrEquipo[]='tristan-suarez';

                break;
            case 'UAI Urquiza':
                $arrEquipo[]='uai-urquiza';

                break;
            case 'Unión de Aconquija':
                $arrEquipo[]='union-aconquija';
                break;
            case 'Unión de Santa Fe':
                $arrEquipo[]='union';
                break;
            case 'Unión de Sunchales':
                $arrEquipo[]='union-sun.';
                break;
            case 'Vélez Sarsfield':
                $arrEquipo[]='velez';
                $arrEquipo[]='ca-velez-sarsfield';
                break;
            case 'Viale FBC':
                $arrEquipo[]='viale';
                break;
            case 'Villa Mitre de Bahía Blanca':
                $arrEquipo[]='villa-mitre';
                break;
            case 'Villa San Carlos':
                $arrEquipo[]='san-carlos';
                break;
            default:
                Log::channel('mi_log')->info('Ojo!!! no esta: '.$strEquipoURL, []);
                break;
        }
        return $arrEquipo;
    }

    public function dameNombreEquipoDB($strEquipo)
    {
        //Log::channel('mi_log')->info('Transformar Equipo '.$strEquipo, []);
        $strEquipoDB=$strEquipo;
        switch (trim($strEquipo)) {

            case 'Atletico Rafaela':
                $strEquipoDB='Atletico de Rafaela';
                break;
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
            case 'Huracán Tres Arroyos':
                $strEquipoDB='Huracán (Tres Arroyos)';
                break;
            case 'Gimnasia Jujuy':
                $strEquipoDB='Gimnasia (J)';
                break;
            case 'Gimnasia La Plata':
                $strEquipoDB='Gimnasia (LP)';
                break;
            case 'Talleres Córdoba':
                $strEquipoDB='Talleres (Cba.)';
                break;
            case 'Tiro Federal Rosario':
                $strEquipoDB='Tiro Federal de Rosario';
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

    public function dameNombreTorneoURL($strTorneo,$fecha,$year)
    {
        //Log::channel('mi_log')->info($strTorneo.' - '.$fecha.' - '.$year, []);
        $strTorneo=strtr($strTorneo, " ", "-");
        if (strpos($fecha, '32vos') !== false) {
            $fecha = '32-de-final';
        }
        if (strpos($fecha, '16vos') !== false) {
            $fecha = 'dieciseisavos';
        }
        if (strpos($fecha, '8vos') !== false) {
            $fecha = 'octavos';
        }
        if (strpos($fecha, '4tos') !== false) {
            $fecha = 'cuartos';
        }
        if (strpos($fecha, 'Semifinal') !== false) {
            $fecha = 'semifinales';
        }
        if (strpos($fecha, 'Final') !== false) {
            $fecha = 'final';
        }

        if (strpos($strTorneo, 'copa-argentina') !== false) {

            $strTorneoURL='copa-argentina-'.$year.'/'.$fecha;
            switch (trim($year)) {
                case '2012':
                    $strTorneoURL='copa-argentina-2011-12/'.$fecha;

                    break;


                case '2013':
                    if ($fecha=='32-de-final'){
                        $fecha='24-de-final';
                    }
                    $strTorneoURL='copa-argentina-2012-13/'.$fecha;

                    break;
                case '2014':
                    if ($fecha=='32-de-final'){
                        $fecha='segunda-ronda';
                    }
                    $strTorneoURL='copa-argentina-2013-14/'.$fecha;

                    break;
                case '2015':
                    $strTorneoURL='copa-argentina-2014-15/'.$fecha;

                    break;
                case '2016':
                    $strTorneoURL='copa-argentina-2015-16/'.$fecha;

                    break;

            }
        }
        else{
            $strTorneoURL='torneo-'.$strTorneo.'-'.$year.'/'.intval($fecha).'-fecha';
        }
        //Log::channel('mi_log')->info($strTorneoURL, []);
        return $strTorneoURL;
    }

    public function importincidenciasfecha_old(Request $request)
    {
        set_time_limit(0);
        //Log::channel('mi_log')->info('Entraaaaaa', []);
        $id = $request->get('fechaId');
        $fecha=Fecha::findOrFail($id);

        $grupo=Grupo::findOrFail($fecha->grupo->id);

        $arrYear = explode('/', $grupo->torneo->year);
        $years = str_replace('/', '-', $grupo->torneo->year);
        $year = (count($arrYear)>1)?$arrYear[1]:$arrYear[0];
        $partidos=Partido::where('fecha_id','=',"$id")->get();
        $nombreTorneo=$grupo->torneo->nombre;
        $ok=1;
        DB::beginTransaction();
        foreach ($partidos as $partido){
            $strLocal = $partido->equipol->nombre;
            $strVisitante = $partido->equipov->nombre;
            $golesTotales = $partido->golesl+$partido->golesv;
            $golesLocales = $partido->golesl;
            $golesVisitantes = $partido->golesv;
            Log::channel('mi_log')->info('Partido ' .$partido->equipol->nombre.' VS '.$partido->equipov->nombre, []);
            Log::channel('mi_log')->info('URL ' .'https://www.resultados-futbol.com/partido/'.$this->dameNombreEquipoURL($strLocal).'/'.$this->dameNombreEquipoURL($strVisitante).'/'.$year, []);
            try {

                $html = HtmlDomParser::file_get_html('https://www.resultados-futbol.com/partido/'.$this->dameNombreEquipoURL($strLocal).'/'.$this->dameNombreEquipoURL($strVisitante).'/'.$year, false, null, 0);



            }
            catch (Exception $ex) {
                $html='';
                $html = HtmlDomParser::file_get_html('https://www.resultados-futbol.com/partido_8385/'.$this->dameNombreEquipoURL($strLocal).'/'.$this->dameNombreEquipoURL($strVisitante).'/'.$year, false, null, 0);
            }

            if ($html){


                $equipos = array();

                //Log::channel('mi_log')->info('Elemento ' . $html,[]);
                $i = 1;
                $goles=0;
                $golesL=0;
                $golesV=0;
                $j=0;
                foreach ($html->find('div[class=team team1]') as $element) {
                    $equipo = $element->find('h3[class=nteam nteam1]');
                    //Log::channel('mi_log')->info('Equipo ' . $equipo[0]->plaintext, []);


                    $jugadores = array();
                    foreach ($element->find('ul[class=aligns-list]') as $element2) {
                        $j++;
                        foreach ($element2->find('li') as $li) {
                            $dorsal = $li->find('small[class=align-dorsal]');
                            $jugador = $li->find('h5[class=align-player]');
                            $id =  explode('-',$li->find('a')[0]->href);
                            //Log::channel('mi_log')->info('Jugador ' . $j.' -> '.$jugador[0]->plaintext, []);

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
                    //Log::channel('mi_log')->info('Equipo ' . $equipo[0]->plaintext, []);
                    $jugadores = array();
                    foreach ($element->find('ul[class=aligns-list]') as $element2) {
                        $j++;
                        foreach ($element2->find('li') as $li) {
                            $dorsal = $li->find('small[class=align-dorsal]');
                            $jugador = $li->find('h5[class=align-player]');
                            $id =  explode('-',$li->find('a')[0]->href);
                            //Log::channel('mi_log')->info('ID ' . $id[2], []);
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
                //Log::channel('mi_log')->info('Partido ' . print_r($equipos,true), []);
                foreach ($html->find('div[class=event-content]') as $element) {

                    $golL = $element->find('span[class=left event_1]');
                    if ($golL){
                        if($golL[0]->find('a')){
                            $id =  explode('-',$golL[0]->find('a')[0]->href);

                            $minL = explode('</b>',$element->find('span[class=left minutos minutosizq]')[0]);


                            $equipos[1]['jugadores'][$id[count($id) - 1]]['incidencias'][] = array('Gol', explode('\'', $minL[1])[0]);
                        }
                        else{
                            Log::channel('mi_log')->info('OJO!!! falta el jugador en el gol local de ' . $partido->equipol->nombre.' VS '.$partido->equipov->nombre, []);
                        }

                        $golesL++;

                    }

                    $penalL = $element->find('span[class=left event_11]');
                    if ($penalL){
                        if ($penalL[0]->find('a')){
                            $id =  explode('-',$penalL[0]->find('a')[0]->href);
                            $minL = explode('</b>',$element->find('span[class=left minutos minutosizq]')[0]);

                            $equipos[1]['jugadores'][$id[count($id)-1]]['incidencias'][]=array('Penal',explode('\'',$minL[1])[0]);

                        }
                        else{
                            Log::channel('mi_log')->info('OJO!!! falta el jugador en el penal local de ' . $partido->equipol->nombre.' VS '.$partido->equipov->nombre, []);
                        }

                        $golesL++;
                    }

                    $ppL = $element->find('span[class=left event_12]');
                    if ($ppL){
                        $id =  explode('-',$ppL[0]->find('a')[0]->href);

                        $minL = explode('</b>',$element->find('span[class=left minutos minutosizq]')[0]);

                        $equipos[2]['jugadores'][$id[count($id)-1]]['incidencias'][]=array('Gol en propia meta',explode('\'',$minL[1])[0]);
                        $golesL++;
                    }

                    $amarillaL = $element->find('span[class=left event_8]');
                    if ($amarillaL){
                        if ($amarillaL[0]->find('a')){
                            $id =  explode('-',$amarillaL[0]->find('a')[0]->href);

                            $minL = explode('</b>',$element->find('span[class=left minutos minutosizq]')[0]);

                            $equipos[1]['jugadores'][$id[count($id)-1]]['incidencias'][]=array('Tarjeta amarilla',explode('\'',$minL[1])[0]);
                        }
                        else{
                            Log::channel('mi_log')->info('OJO!!! falta el jugador en la amarilla local de ' . $partido->equipol->nombre.' VS '.$partido->equipov->nombre, []);
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
                            Log::channel('mi_log')->info('OJO!!! falta el jugador en el gol visitante de ' . $partido->equipol->nombre.' VS '.$partido->equipov->nombre, []);
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
                            Log::channel('mi_log')->info('OJO!!! falta el jugador en la entrada local de ' . $partido->equipol->nombre.' VS '.$partido->equipov->nombre, []);
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
                            Log::channel('mi_log')->info('OJO!!! falta el jugador en la entrada local de ' . $partido->equipol->nombre.' VS '.$partido->equipov->nombre, []);
                        }

                        $golesV++;
                    }

                    $penalV = $element->find('span[class=right event_11]');
                    if ($penalV){
                        $id =  explode('-',$penalV[0]->find('a')[0]->href);

                        $minL = explode('</b>',$element->find('span[class=right minutos minutosder]')[0]);

                        $equipos[2]['jugadores'][$id[count($id)-1]]['incidencias'][]=array('Penal',explode('\'',$minL[1])[0]);
                        $golesV++;
                    }

                    $ppV = $element->find('span[class=right event_12]');
                    if ($ppV){
                        $id =  explode('-',$ppV[0]->find('a')[0]->href);

                        $minL = explode('</b>',$element->find('span[class=right minutos minutosder]')[0]);

                        $equipos[1]['jugadores'][$id[count($id)-1]]['incidencias'][]=array('Gol en propia meta',explode('\'',$minL[1])[0]);
                        $golesV++;
                    }

                    $amarillaV = $element->find('span[class=right event_8]');
                    if ($amarillaV){
                        if ($amarillaV[0]->find('a')){
                            $id =  explode('-',$amarillaV[0]->find('a')[0]->href);

                            $minL = explode('</b>',$element->find('span[class=right minutos minutosder]')[0]);

                            $equipos[2]['jugadores'][$id[count($id)-1]]['incidencias'][]=array('Tarjeta amarilla',explode('\'',$minL[1])[0]);
                        }
                        else{
                            Log::channel('mi_log')->info('OJO!!! falta el jugador en la amarilla visitante de ' . $partido->equipol->nombre.' VS '.$partido->equipov->nombre, []);
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
                            Log::channel('mi_log')->info('OJO!!! falta el jugador en la salida visitante de ' . $partido->equipol->nombre.' VS '.$partido->equipov->nombre, []);
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
                            Log::channel('mi_log')->info('OJO!!! falta el jugador en la entrada visitante de ' . $partido->equipol->nombre.' VS '.$partido->equipov->nombre, []);
                        }



                    }



                }
                if (($golesL!=$golesLocales)||($golesV!=$golesVisitantes)) {
                    Log::channel('mi_log')->info('OJO!!! No coincide la cantidad de goles en: ' . $partido->equipol->nombre . ' VS ' . $partido->equipov->nombre . ' -> ' . $golesL.' a '. $golesV. ' - ' . $golesLocales. ' a '.$golesVisitantes, []);
                    /*try {
                        Log::channel('mi_log')->info('Partido ' . $partido->equipol->nombre . ' VS ' . $partido->equipov->nombre, []);
                        Log::channel('mi_log')->info('URL ' . 'https://www.resultados-futbol.com/partido_8377/' . $this->dameNombreEquipoURL($strLocal) . '/' . $this->dameNombreEquipoURL($strVisitante) . '/' . $year, []);
                        $html = HtmlDomParser::file_get_html('https://www.resultados-futbol.com/partido_8377/' . $this->dameNombreEquipoURL($strLocal) . '/' . $this->dameNombreEquipoURL($strVisitante) . '/' . $year, false, null, 0);


                    } catch (Exception $ex) {
                        $html = '';
                    }*/
                    $html = '';
                    if (!$html) {
                        //Log::channel('mi_log')->info('OJO!!! No se encontro la URL ' . 'https://www.resultados-futbol.com/partido_61201/' . $this->dameNombreEquipoURL($strLocal) . '/' . $this->dameNombreEquipoURL($strVisitante) . '/' . $year, []);
                    } else {
                        $equipos = array();

                        //Log::channel('mi_log')->info('Elemento ' . $html,[]);
                        $i = 1;
                        $goles = 0;
                        $golesL = 0;
                        $golesV = 0;
                        $j = 0;
                        foreach ($html->find('div[class=team team1]') as $element) {
                            $equipo = $element->find('h3[class=nteam nteam1]');
                            //Log::channel('mi_log')->info('Equipo ' . $equipo[0]->plaintext, []);
                            $jugadores = array();

                            $jugadores = array();
                            foreach ($element->find('ul[class=aligns-list]') as $element2) {
                                $j++;
                                foreach ($element2->find('li') as $li) {
                                    $dorsal = $li->find('small[class=align-dorsal]');
                                    $jugador = $li->find('h5[class=align-player]');
                                    $id = explode('-', $li->find('a')[0]->href);
                                    //Log::channel('mi_log')->info('Jugador ' . $j.' -> '.$jugador[0]->plaintext, []);

                                    $data2 = array(
                                        'dorsal' => $dorsal[0]->plaintext,
                                        'nombre' => $jugador[0]->plaintext,
                                        'tipo' => ($j == 1) ? 'Titular' : 'Suplente',
                                        'incidencias' => array()
                                    );
                                    $jugadores[$id[count($id) - 1]] = $data2;
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
                        $j = 0;
                        foreach ($html->find('div[class=team team2]') as $element) {
                            $equipo = $element->find('h3[class=nteam nteam2]');
                            //Log::channel('mi_log')->info('Equipo ' . $equipo[0]->plaintext, []);
                            $jugadores = array();
                            foreach ($element->find('ul[class=aligns-list]') as $element2) {
                                $j++;
                                foreach ($element2->find('li') as $li) {
                                    $dorsal = $li->find('small[class=align-dorsal]');
                                    $jugador = $li->find('h5[class=align-player]');
                                    $id = explode('-', $li->find('a')[0]->href);
                                    //Log::channel('mi_log')->info('ID ' . $id[2], []);
                                    $data2 = array(
                                        'dorsal' => $dorsal[0]->plaintext,
                                        'nombre' => $jugador[0]->plaintext,
                                        'tipo' => ($j == 1) ? 'Titular' : 'Suplente',
                                        'incidencias' => array()
                                    );
                                    $jugadores[$id[count($id) - 1]] = $data2;
                                }
                                $data = array(

                                    'equipo' => $this->dameNombreEquipoDB($equipo[0]->plaintext),

                                    'jugadores' => $jugadores
                                );
                                $equipos[$i] = $data;

                                //$i++;


                            }
                        }
                        //Log::channel('mi_log')->info('Partido ' . print_r($equipos,true), []);
                        foreach ($html->find('div[class=event-content]') as $element) {

                            $golL = $element->find('span[class=left event_1]');
                            if ($golL) {
                                if ($golL[0]->find('a')) {
                                    $id = explode('-', $golL[0]->find('a')[0]->href);

                                    $minL = explode('</b>', $element->find('span[class=left minutos minutosizq]')[0]);


                                    $equipos[1]['jugadores'][$id[count($id) - 1]]['incidencias'][] = array('Gol', explode('\'', $minL[1])[0]);
                                } else {
                                    Log::channel('mi_log')->info('OJO!!! falta el jugador en el gol local de ' . $partido->equipol->nombre . ' VS ' . $partido->equipov->nombre, []);
                                }

                                $golesL++;

                            }

                            $penalL = $element->find('span[class=left event_11]');
                            if ($penalL) {
                                if ($penalL[0]->find('a')) {
                                    $id = explode('-', $penalL[0]->find('a')[0]->href);
                                    $minL = explode('</b>', $element->find('span[class=left minutos minutosizq]')[0]);

                                    $equipos[1]['jugadores'][$id[count($id) - 1]]['incidencias'][] = array('Penal', explode('\'', $minL[1])[0]);

                                } else {
                                    Log::channel('mi_log')->info('OJO!!! falta el jugador en el penal local de ' . $partido->equipol->nombre . ' VS ' . $partido->equipov->nombre, []);
                                }

                                $golesL++;
                            }

                            $ppL = $element->find('span[class=left event_12]');
                            if ($ppL) {
                                $id = explode('-', $ppL[0]->find('a')[0]->href);

                                $minL = explode('</b>', $element->find('span[class=left minutos minutosizq]')[0]);

                                $equipos[2]['jugadores'][$id[count($id) - 1]]['incidencias'][] = array('Gol en propia meta', explode('\'', $minL[1])[0]);
                                $golesL++;
                            }

                            $amarillaL = $element->find('span[class=left event_8]');
                            if ($amarillaL) {
                                if ($amarillaL[0]->find('a')) {
                                    $id = explode('-', $amarillaL[0]->find('a')[0]->href);

                                    $minL = explode('</b>', $element->find('span[class=left minutos minutosizq]')[0]);

                                    $equipos[1]['jugadores'][$id[count($id) - 1]]['incidencias'][] = array('Tarjeta amarilla', explode('\'', $minL[1])[0]);
                                } else {
                                    Log::channel('mi_log')->info('OJO!!! falta el jugador en la amarilla local de ' . $partido->equipol->nombre . ' VS ' . $partido->equipov->nombre, []);
                                }


                            }

                            $dobleamarillaL = $element->find('span[class=left event_10]');
                            if ($dobleamarillaL) {
                                $id = explode('-', $dobleamarillaL[0]->find('a')[0]->href);

                                $minL = explode('</b>', $element->find('span[class=left minutos minutosizq]')[0]);

                                $equipos[1]['jugadores'][$id[count($id) - 1]]['incidencias'][] = array('Expulsado por doble amarilla', explode('\'', $minL[1])[0]);


                            }

                            $rojaL = $element->find('span[class=left event_9]');
                            if ($rojaL) {
                                $id = explode('-', $rojaL[0]->find('a')[0]->href);

                                $minL = explode('</b>', $element->find('span[class=left minutos minutosizq]')[0]);

                                $equipos[1]['jugadores'][$id[count($id) - 1]]['incidencias'][] = array('Tarjeta roja', explode('\'', $minL[1])[0]);


                            }

                            $saleL = $element->find('span[class=left event_6]');
                            if ($saleL) {
                                if ($saleL[0]->find('a')) {
                                    $id = explode('-', $saleL[0]->find('a')[0]->href);

                                    $minL = explode('</b>', $element->find('span[class=left minutos minutosizq]')[0]);

                                    $equipos[1]['jugadores'][$id[count($id) - 1]]['incidencias'][] = array('Sale', explode('\'', $minL[1])[0]);
                                } else {
                                    Log::channel('mi_log')->info('OJO!!! falta el jugador en el gol visitante de ' . $partido->equipol->nombre . ' VS ' . $partido->equipov->nombre, []);
                                }


                            }

                            $entraL = $element->find('span[class=left event_7]');
                            if ($entraL) {
                                if ($entraL[0]->find('a')) {
                                    $id = explode('-', $entraL[0]->find('a')[0]->href);

                                    $minL = explode('</b>', $element->find('span[class=left minutos minutosizq]')[0]);

                                    $equipos[1]['jugadores'][$id[count($id) - 1]]['incidencias'][] = array('Entra', explode('\'', $minL[1])[0]);
                                } else {
                                    Log::channel('mi_log')->info('OJO!!! falta el jugador en la entrada local de ' . $partido->equipol->nombre . ' VS ' . $partido->equipov->nombre, []);
                                }


                            }

                            $golV = $element->find('span[class=right event_1]');
                            if ($golV) {
                                if ($golV[0]->find('a')) {
                                    $id = explode('-', $golV[0]->find('a')[0]->href);

                                    $minL = explode('</b>', $element->find('span[class=right minutos minutosder]')[0]);


                                    $equipos[2]['jugadores'][$id[count($id) - 1]]['incidencias'][] = array('Gol', explode('\'', $minL[1])[0]);
                                } else {
                                    Log::channel('mi_log')->info('OJO!!! falta el jugador en la entrada local de ' . $partido->equipol->nombre . ' VS ' . $partido->equipov->nombre, []);
                                }

                                $golesV++;
                            }

                            $penalV = $element->find('span[class=right event_11]');
                            if ($penalV) {
                                $id = explode('-', $penalV[0]->find('a')[0]->href);

                                $minL = explode('</b>', $element->find('span[class=right minutos minutosder]')[0]);

                                $equipos[2]['jugadores'][$id[count($id) - 1]]['incidencias'][] = array('Penal', explode('\'', $minL[1])[0]);
                                $golesV++;
                            }

                            $ppV = $element->find('span[class=right event_12]');
                            if ($ppV) {
                                $id = explode('-', $ppV[0]->find('a')[0]->href);

                                $minL = explode('</b>', $element->find('span[class=right minutos minutosder]')[0]);

                                $equipos[1]['jugadores'][$id[count($id) - 1]]['incidencias'][] = array('Gol en propia meta', explode('\'', $minL[1])[0]);
                                $golesV++;
                            }

                            $amarillaV = $element->find('span[class=right event_8]');
                            if ($amarillaV) {
                                if ($amarillaV[0]->find('a')) {
                                    $id = explode('-', $amarillaV[0]->find('a')[0]->href);

                                    $minL = explode('</b>', $element->find('span[class=right minutos minutosder]')[0]);

                                    $equipos[2]['jugadores'][$id[count($id) - 1]]['incidencias'][] = array('Tarjeta amarilla', explode('\'', $minL[1])[0]);
                                } else {
                                    Log::channel('mi_log')->info('OJO!!! falta el jugador en la amarilla visitante de ' . $partido->equipol->nombre . ' VS ' . $partido->equipov->nombre, []);
                                }


                            }

                            $dobleamarillaV = $element->find('span[class=right event_10]');
                            if ($dobleamarillaV) {
                                $id = explode('-', $dobleamarillaV[0]->find('a')[0]->href);

                                $minL = explode('</b>', $element->find('span[class=right minutos minutosder]')[0]);

                                $equipos[2]['jugadores'][$id[count($id) - 1]]['incidencias'][] = array('Expulsado por doble amarilla', explode('\'', $minL[1])[0]);


                            }

                            $rojaV = $element->find('span[class=right event_9]');
                            if ($rojaV) {
                                $id = explode('-', $rojaV[0]->find('a')[0]->href);

                                $minL = explode('</b>', $element->find('span[class=right minutos minutosder]')[0]);

                                $equipos[2]['jugadores'][$id[count($id) - 1]]['incidencias'][] = array('Tarjeta roja', explode('\'', $minL[1])[0]);


                            }

                            $saleV = $element->find('span[class=right event_6]');
                            if ($saleV) {
                                if ($saleV[0]->find('a')) {
                                    $id = explode('-', $saleV[0]->find('a')[0]->href);

                                    $minL = explode('</b>', $element->find('span[class=right minutos minutosder]')[0]);

                                    $equipos[2]['jugadores'][$id[count($id) - 1]]['incidencias'][] = array('Sale', explode('\'', $minL[1])[0]);
                                } else {
                                    Log::channel('mi_log')->info('OJO!!! falta el jugador en la salida visitante de ' . $partido->equipol->nombre . ' VS ' . $partido->equipov->nombre, []);
                                }


                            }

                            $entraV = $element->find('span[class=right event_7]');
                            if ($entraV) {
                                if ($entraV[0]->find('a')) {
                                    $id = explode('-', $entraV[0]->find('a')[0]->href);

                                    $minL = explode('</b>', $element->find('span[class=right minutos minutosder]')[0]);

                                    $equipos[2]['jugadores'][$id[count($id) - 1]]['incidencias'][] = array('Entra', explode('\'', $minL[1])[0]);
                                } else {
                                    Log::channel('mi_log')->info('OJO!!! falta el jugador en la entrada visitante de ' . $partido->equipol->nombre . ' VS ' . $partido->equipov->nombre, []);
                                }


                            }


                        }
                        if (($golesL!=$golesLocales)||($golesV!=$golesVisitantes)) {
                            Log::channel('mi_log')->info('OJO!!! No coincide la cantidad de goles en: ' . $partido->equipol->nombre . ' VS ' . $partido->equipov->nombre . ' -> ' . $golesL.' a '. $golesV. ' - ' . $golesLocales. ' a '.$golesVisitantes, []);
                        }

                    }
                }
                    foreach ($equipos as $eq) {
                        //Log::channel('mi_log')->info('Equipo  ' . $equipo['equipo'], []);
                        $strEquipo=trim($eq['equipo']);
                        $equipo=Equipo::where('nombre','like',"%$strEquipo%")->first();
                        if (!empty($equipo)){
                            foreach ($eq['jugadores'] as $jugador) {
                                $grupos = Grupo::where('torneo_id', '=',$grupo->torneo->id)->get();
                                $arrgrupos='';
                                foreach ($grupos as $grupo){
                                    $arrgrupos .=$grupo->id.',';
                                }

                                $plantillas = Plantilla::wherein('grupo_id',explode(',', $arrgrupos))->where('equipo_id','=',$equipo->id)->get();

                                $arrplantillas='';
                                foreach ($plantillas as $plantilla){
                                    $arrplantillas .=$plantilla->id.',';
                                }




                                if (!empty($plantillas)){
                                    if(!empty($jugador['dorsal'])){

                                        $plantillaJugador = PlantillaJugador::wherein('plantilla_id',explode(',', $arrplantillas))->distinct()->where('dorsal','=',$jugador['dorsal'])->first();
                                    }
                                    else{
                                        $plantillaJugador='';
                                        Log::channel('mi_log')->info('OJO!!! con el jugador no está en la plantilla del equipo ' . $strEquipo, []);
                                    }
                                }
                                else{
                                    $plantillaJugador='';
                                    Log::channel('mi_log')->info('OJO!!! No hay plantilla del equipo ' . $strEquipo, []);
                                }
                                if (!empty($plantillaJugador)) {
                                    $arrApellido = explode(' ', $jugador['nombre']);
                                    $mismoDorsal = 0;
                                    foreach ($arrApellido as $apellido) {
                                        $consultarJugador = Jugador::Join('personas','personas.id','=','jugadors.persona_id')->where('jugadors.id', '=', $plantillaJugador->jugador->id)->where('apellido', 'LIKE', "%$apellido%")->first();
                                        if (!empty($consultarJugador)) {
                                            $mismoDorsal = 1;
                                            continue;
                                        }
                                    }
                                    if (!$mismoDorsal) {
                                        Log::channel('mi_log')->info('OJO!!! con jugador: ' . $jugador['dorsal'] . ' ' . $jugador['nombre'] . ' del equipo ' . $strEquipo, []);
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
                                    Log::channel('mi_log')->info('OJO!!! NO se encontró al jugador: ' . $jugadorMostrar.' del equipo '.$strEquipo,[]);
                                }
                                foreach ($jugador['incidencias'] as $incidencia) {

                                    if (!empty($incidencia)) {
                                        //Log::channel('mi_log')->info('Incidencia: ' . trim($incidencia[0]) . ' MIN: ' . intval(trim($incidencia[1])), []);

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
                            Log::channel('mi_log')->info('OJO!!! NO se encontró al equipo: ' . $strEquipo,[]);
                        }
                    }




            }
            else{
                $error = 'No se econtró la URL del partido: '.$partido->equipol->nombre.' VS '.$partido->equipov->nombre;
                $ok=0;
                continue;
            }


            try {
                //Log::channel('mi_log')->info('Partido ' .$partido->equipol->nombre.' VS '.$partido->equipov->nombre, []);

                Log::channel('mi_log')->info('URL ' .'https://www.livefutbol.com/cronica/primera-division-'.$years.'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'_2/', []);
                $html2 = HtmlDomParser::file_get_html('https://www.livefutbol.com/cronica/primera-division-'.$years.'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'_2/', false, null, 0);
                if (!$html2){
                    Log::channel('mi_log')->info('OJO!!! URL ' .'https://www.livefutbol.com/cronica/primera-division-'.$years.'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'/', []);
                    $html2 = HtmlDomParser::file_get_html('https://www.livefutbol.com/cronica/primera-division-'.$years.'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'/', false, null, 0);
                }
                $linkArray=array();
                $entrenadoresArray = array();
                $nombreArbitro ='';
                if (!$html2) {
                    Log::channel('mi_log')->info('OJO!!! URL ' .'https://arg.worldfootball.net/cronica/primera-division-'.$years.'-'.strtr($nombreTorneo, " ", "-").'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'/', []);

                    $html2 = HtmlDomParser::file_get_html('https://arg.worldfootball.net/cronica/primera-division-'.$years.'-'.strtr($nombreTorneo, " ", "-").'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'/', false, null, 0);
                    /*Log::channel('mi_log')->info('OJO!!! URL ' .'https://arg.worldfootball.net/cronica/copa-de-la-superliga-'.$years.'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'/', []);

                    $html2 = HtmlDomParser::file_get_html('https://arg.worldfootball.net/cronica/copa-de-la-superliga-'.$years.'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'/', false, null, 0);*/

                }
                if (!$html2) {
                    Log::channel('mi_log')->info('OJO!!! URL ' .'https://arg.worldfootball.net/cronica/copa-de-la-superliga-'.$years.'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'/', []);

                    $html2 = HtmlDomParser::file_get_html('https://arg.worldfootball.net/cronica/copa-de-la-superliga'.$years.'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'/', false, null, 0);
                }

            }
            catch (Exception $ex) {
                $html2='';
            }
            $dtLocal ='';
            $dtVisitante ='';
            if ($html2){
                $tabla = 0;
                foreach ($html2->find('table[class=standard_tabelle]') as $element) {

                    if ($tabla==1){
                        foreach ($element->find('td') as $td) {
                            $jugadorGol='';
                            $lineaGol='';
                            $minutoGol='';
                            $incidenciaGol='';
                            if($td->find('a')){
                                $jugadorGol = $td->find('a')[0]->title;
                                Log::channel('mi_log')->info('OJO!! gol: '.$jugadorGol,[]);
                                $lineaGol = $td->plaintext;
                                if (str_contains($lineaGol, $jugadorGol)) {
                                    $minutoGol = (int) filter_var($lineaGol, FILTER_SANITIZE_NUMBER_INT);
                                    Log::channel('mi_log')->info('OJO!! min: '.$minutoGol,[]);
                                }

                                $incidenciaArray = explode('/', $lineaGol);
                                if (count($incidenciaArray)>1){
                                    $incidenciaGol = $incidenciaArray[1];
                                    Log::channel('mi_log')->info('OJO!! incidencia: '.$incidenciaGol,[]);
                                }
                            }


                        }
                    }
                    if ($tabla==2){
                        Log::channel('mi_log')->info('OJO!! locales:',[]);
                        $suplentes=0;
                        foreach ($element->find('tr') as $tr) {
                            $dorsalTitularL = '';
                            $jugadorTitularL = '';
                            $saleTitularL = '';
                            $amarillaTitularL = '';
                            $dobleamarillaTitularL = '';
                            $rojaTitularL = '';
                            $mintutoTarjetaTitularL = '';
                            $dorsalSuplenteL = '';
                            $jugadorSuplenteL = '';
                            $entraSuplenteL = '';
                            $saleSuplenteL = '';
                            $amarillaSuplenteL = '';
                            $dobleamarillaSuplenteL = '';
                            $rojaSuplenteL = '';
                            $mintutoTarjetaSuplenteL = '';
                            foreach ($tr->find('td') as $td) {
                                //Log::channel('mi_log')->info('OJO!! linea: ' . $td->plaintext, []);
                                if (trim($td->plaintext)=='Banquillo') {
                                    $suplentes=1;
                                }
                                if ($td->find('span[class=kleine_schrift]')) {
                                    if ($td->find('span[style=font-weight: bold; color: #646464]')) {
                                        if ($suplentes){
                                            $dorsalSuplenteV= $td->find('span[class=kleine_schrift]')[0]->plaintext;
                                            Log::channel('mi_log')->info('OJO!! dorsal suplente: ' . $dorsalSuplenteV, []);
                                        }
                                        else{
                                            $dorsalTitularL = $td->find('span[class=kleine_schrift]')[0]->plaintext;
                                            Log::channel('mi_log')->info('OJO!! dorsal titular: ' . $dorsalTitularL, []);
                                        }

                                    }
                                    elseif ($td->find('span[class=rottext]')) {
                                        if ($suplentes){
                                            $saleSuplenteV = (int) filter_var($td->find('span[class=rottext]')[0]->plaintext, FILTER_SANITIZE_NUMBER_INT);
                                            Log::channel('mi_log')->info('OJO!! sale suplente: ' . $saleSuplenteV, []);
                                        }
                                        else{
                                            $saleTitularL = (int) filter_var($td->find('span[class=rottext]')[0]->plaintext, FILTER_SANITIZE_NUMBER_INT);
                                            Log::channel('mi_log')->info('OJO!! sale titular: ' . $saleTitularL, []);
                                        }

                                    }
                                    elseif ($td->find('span[class=gruentext]')) {
                                        if ($suplentes){
                                            $entraSuplenteV = (int) filter_var($td->find('span[class=gruentext]')[0]->plaintext, FILTER_SANITIZE_NUMBER_INT);
                                            Log::channel('mi_log')->info('OJO!! entra suplente: ' . $td->find('span[class=gruentext]')[0]->plaintext, []);
                                        }


                                    }
                                    else{
                                        if ($td->find('span[class=kleine_schrift]')[0]->title !=''){
                                            if ($suplentes){
                                                $mintutoTarjetaSuplenteL = $td->find('span[class=kleine_schrift]')[0]->title;
                                            }
                                            else{
                                                $mintutoTarjetaTitularL = $td->find('span[class=kleine_schrift]')[0]->title;
                                            }




                                        }

                                    }
                                }

                                if ($td->find('img')) {
                                    if ($td->find('img')[0]->title == 'Tarjeta amarilla') {
                                        if ($suplentes){
                                            $amarillaSuplenteL = $dorsalSuplenteL . '-' . (int) filter_var($mintutoTarjetaSuplenteL, FILTER_SANITIZE_NUMBER_INT);;
                                            Log::channel('mi_log')->info('OJO!! amarilla titular: ' . $amarillaSuplenteL, []);
                                        }
                                        else{
                                            $amarillaTitularL = $dorsalTitularL . '-' . (int) filter_var($mintutoTarjetaTitularL, FILTER_SANITIZE_NUMBER_INT);;
                                            Log::channel('mi_log')->info('OJO!! amarilla titular: ' . $amarillaTitularL, []);
                                        }

                                    }
                                    if ($td->find('img')[0]->title == 'Roja directa') {
                                        if ($suplentes){
                                            $rojaSuplenteL = $dorsalSuplenteL . '-' . (int) filter_var($mintutoTarjetaSuplenteL, FILTER_SANITIZE_NUMBER_INT);;
                                            Log::channel('mi_log')->info('OJO!! roja titular: ' . $rojaSuplenteL, []);
                                        }
                                        else{
                                            $rojaTitularL = $dorsalTitularL . '-' . (int) filter_var($mintutoTarjetaTitularL, FILTER_SANITIZE_NUMBER_INT);;
                                            Log::channel('mi_log')->info('OJO!! roja titular: ' . $rojaTitularL, []);
                                        }

                                    }
                                    if ($td->find('img')[0]->title == 'Doble amarilla') {
                                        if ($suplentes){
                                            $dobleamarillaSuplenteL = $dorsalSuplenteL . '-' . (int) filter_var($mintutoTarjetaSuplenteL, FILTER_SANITIZE_NUMBER_INT);;
                                            Log::channel('mi_log')->info('OJO!! dobleamarilla titular: ' . $dobleamarillaSuplenteL, []);
                                        }
                                        else{
                                            $dobleamarillaTitularL = $dorsalTitularL . '-' . (int) filter_var($mintutoTarjetaTitularL, FILTER_SANITIZE_NUMBER_INT);;
                                            Log::channel('mi_log')->info('OJO!! dobleamarilla titular: ' . $dobleamarillaTitularL, []);
                                        }

                                    }

                                }

                                if ($td->find('a')) {
                                    if ($suplentes){
                                        $jugadorSuplenteL = $td->find('a')[0]->title;
                                        Log::channel('mi_log')->info('OJO!! suplente: ' . $jugadorSuplenteL, []);
                                    }
                                    else{
                                        $jugadorTitularL = $td->find('a')[0]->title;
                                        Log::channel('mi_log')->info('OJO!! titular: ' . $jugadorTitularL, []);
                                    }


                                }


                            }
                        }
                    }
                    if ($tabla==3){
                        Log::channel('mi_log')->info('OJO!! visitante:',[]);
                        $suplentes=0;
                        foreach ($element->find('tr') as $tr) {
                            $dorsalTitularV = '';
                            $jugadorTitularV = '';
                            $saleTitularV = '';
                            $amarillaTitularV = '';
                            $dobleamarillaTitularV = '';
                            $rojaTitularV = '';
                            $mintutoTarjetaTitularV = '';
                            $dorsalSuplenteV = '';
                            $jugadorSuplenteV = '';
                            $entraSuplenteV = '';
                            $saleSuplenteV = '';
                            $amarillaSuplenteV = '';
                            $dobleamarillaSuplenteV = '';
                            $rojaSuplenteV = '';
                            $mintutoTarjetaSuplenteV = '';
                            foreach ($tr->find('td') as $td) {
                                //Log::channel('mi_log')->info('OJO!! linea: ' . $td->plaintext, []);
                                if (trim($td->plaintext)=='Banquillo') {
                                    $suplentes=1;
                                }
                                if ($td->find('span[class=kleine_schrift]')) {
                                    if ($td->find('span[style=font-weight: bold; color: #646464]')) {
                                        if ($suplentes){
                                            $dorsalSuplenteV= $td->find('span[class=kleine_schrift]')[0]->plaintext;
                                            Log::channel('mi_log')->info('OJO!! dorsal suplente: ' . $dorsalSuplenteV, []);
                                        }
                                        else{
                                            $dorsalTitularV = $td->find('span[class=kleine_schrift]')[0]->plaintext;
                                            Log::channel('mi_log')->info('OJO!! dorsal titular: ' . $dorsalTitularV, []);
                                        }

                                    }
                                    elseif ($td->find('span[class=rottext]')) {
                                        if ($suplentes){
                                            $saleSuplenteV = (int) filter_var($td->find('span[class=rottext]')[0]->plaintext, FILTER_SANITIZE_NUMBER_INT);
                                            Log::channel('mi_log')->info('OJO!! sale suplente: ' . $saleSuplenteV, []);
                                        }
                                        else{
                                            $saleTitularV = (int) filter_var($td->find('span[class=rottext]')[0]->plaintext, FILTER_SANITIZE_NUMBER_INT);
                                            Log::channel('mi_log')->info('OJO!! sale titular: ' . $saleTitularV, []);
                                        }

                                    }
                                    elseif ($td->find('span[class=gruentext]')) {
                                        if ($suplentes){
                                            $entraSuplenteV = (int) filter_var($td->find('span[class=gruentext]')[0]->plaintext, FILTER_SANITIZE_NUMBER_INT);
                                            Log::channel('mi_log')->info('OJO!! entra suplente: ' . $entraSuplenteV, []);
                                        }


                                    }
                                    else{
                                        if ($td->find('span[class=kleine_schrift]')[0]->title !=''){
                                            if ($suplentes){
                                                $mintutoTarjetaSuplenteV = $td->find('span[class=kleine_schrift]')[0]->title;
                                            }
                                            else{
                                                $mintutoTarjetaTitularV = $td->find('span[class=kleine_schrift]')[0]->title;
                                            }




                                        }

                                    }
                                }

                                if ($td->find('img')) {
                                    if ($td->find('img')[0]->title == 'Tarjeta amarilla') {
                                        if ($suplentes){
                                            $amarillaSuplenteV = $dorsalSuplenteV . '-' . (int) filter_var($mintutoTarjetaSuplenteV, FILTER_SANITIZE_NUMBER_INT);;
                                            Log::channel('mi_log')->info('OJO!! amarilla titular: ' . $amarillaSuplenteV, []);
                                        }
                                        else{
                                            $amarillaTitularV = $dorsalTitularV . '-' . (int) filter_var($mintutoTarjetaTitularV, FILTER_SANITIZE_NUMBER_INT);;
                                            Log::channel('mi_log')->info('OJO!! amarilla titular: ' . $amarillaTitularV, []);
                                        }

                                    }
                                    if ($td->find('img')[0]->title == 'Roja directa') {
                                        if ($suplentes){
                                            $rojaSuplenteV = $dorsalSuplenteV . '-' . (int) filter_var($mintutoTarjetaSuplenteV, FILTER_SANITIZE_NUMBER_INT);;
                                            Log::channel('mi_log')->info('OJO!! roja titular: ' . $rojaSuplenteV, []);
                                        }
                                        else{
                                            $rojaTitularV = $dorsalTitularV . '-' . (int) filter_var($mintutoTarjetaTitularV, FILTER_SANITIZE_NUMBER_INT);;
                                            Log::channel('mi_log')->info('OJO!! roja titular: ' . $rojaTitularV, []);
                                        }

                                    }
                                    if ($td->find('img')[0]->title == 'Doble amarilla') {
                                        if ($suplentes){
                                            $dobleamarillaSuplenteV = $dorsalSuplenteV . '-' . (int) filter_var($mintutoTarjetaSuplenteV, FILTER_SANITIZE_NUMBER_INT);;
                                            Log::channel('mi_log')->info('OJO!! dobleamarilla titular: ' . $dobleamarillaSuplenteV, []);
                                        }
                                        else{
                                            $dobleamarillaTitularV = $dorsalTitularV . '-' . (int) filter_var($mintutoTarjetaTitularV, FILTER_SANITIZE_NUMBER_INT);;
                                            Log::channel('mi_log')->info('OJO!! dobleamarilla titular: ' . $dobleamarillaTitularV, []);
                                        }

                                    }

                                }

                                if ($td->find('a')) {
                                    if ($suplentes){
                                        $jugadorSuplenteV = $td->find('a')[0]->title;
                                        Log::channel('mi_log')->info('OJO!! suplente: ' . $jugadorSuplenteV, []);
                                    }
                                    else{
                                        $jugadorTitularV = $td->find('a')[0]->title;
                                        Log::channel('mi_log')->info('OJO!! titular: ' . $jugadorTitularV, []);
                                    }


                                }


                            }
                        }
                    }



                    $tabla ++;




                    $entrenadoresArray = explode('Entrenador:', $element->plaintext);
                    if (count($entrenadoresArray)>3){
                        Log::channel('mi_log')->info('OJO!! varios entrenadores: '.$partido->equipol->nombre.' VS '.$partido->equipov->nombre,[]);
                    }
                    if(count($entrenadoresArray)>1){
                        $dtLocal = $entrenadoresArray[1];
                        //Log::channel('mi_log')->info('DT Local: '.utf8_decode($entrenadoresArray[1]), []);
                        if (isset($entrenadoresArray[2])){
                            $dtVisitante = $entrenadoresArray[2];
                        }
                        else{
                            Log::channel('mi_log')->info('OJO!! Falta Entrenador visitante: '.$partido->equipol->nombre.' VS '.$partido->equipov->nombre,[]);
                        }

                        //Log::channel('mi_log')->info('DT Visitante: '.utf8_decode($entrenadoresArray[2]), []);
                    }
                    else{
                        $asistente=0;
                        $asistente1='';
                        $asistente2='';
                        $arbitro='';
                        $arbitro1='';
                        $arbitro2='';
                        foreach ($element->find('td[class="dunkel"]') as $element2) {

                            foreach ($element2->find('a') as $link) {
                               $linkArray = explode(' ', $link->title);

                                if (($linkArray[0])=='Árbitro'){
                                    if (($linkArray[1])=='asistente'){
                                        $nombreAsistente = '';
                                        for ($i = 2; $i < count($linkArray); $i++) {
                                            $nombreAsistente .= ($linkArray[$i]).' ';
                                        }
                                        if ($asistente==0){
                                            $asistente1= $nombreAsistente;
                                            Log::channel('mi_log')->info('Asistente 1: '.$nombreAsistente, []);
                                            $asistente++;
                                        }
                                        else{
                                            $asistente2= $nombreAsistente;
                                            Log::channel('mi_log')->info('Asistente 2: '.$nombreAsistente, []);
                                            $asistente++;
                                        }

                                    }
                                    else{
                                        $nombreArbitro = '';
                                        for ($i = 1; $i < count($linkArray); $i++) {
                                            $nombreArbitro .= ($linkArray[$i]).' ';
                                        }

                                        Log::channel('mi_log')->info('Arbitro: '.$nombreArbitro, []);

                                        }
                                }


                            }

                        }

                    }

                }
                $arrArbitro = explode(' ', $nombreArbitro);
                $arbitro=0;
                if(count($arrArbitro)>1) {
                    //$arbitro = Arbitro::where('nombre', 'like', "%$arrArbitro[0]%")->where('apellido', 'like', "%$arrArbitro[1]%")->first();
                    $arbitro=Arbitro::SELECT('arbitros.*')->Join('personas','personas.id','=','arbitros.persona_id')->where('apellido', 'like', "%$arrArbitro[1]%")->first();
                }

                if (!$arbitro){
                    Log::channel('mi_log')->info('OJO!! Arbitro NO encontrado: '.$nombreArbitro.' '.$partido->equipol->nombre.' VS '.$partido->equipov->nombre,[]);
                }
                else{
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
                $arrArbitroA1 = explode(' ', $asistente1);
                $arbitro1=0;
                if(count($arrArbitroA1)>1) {
                    //$arbitro1 = Arbitro::where('nombre', 'like', "%$arrArbitroA1[0]%")->where('apellido', 'like', "%$arrArbitroA1[1]%")->first();
                    $arbitro1=Arbitro::SELECT('arbitros.*')->Join('personas','personas.id','=','arbitros.persona_id')->where('nombre', 'like', "%$arrArbitroA1[0]%")->where('apellido', 'like', "%$arrArbitroA1[1]%")->first();
                }

                if (!$arbitro1){
                    Log::channel('mi_log')->info('OJO!! Asistente NO encontrado: '.$asistente1.' '.$partido->equipol->nombre.' VS '.$partido->equipov->nombre,[]);
                }
                else{
                    $data3=array(
                        'partido_id'=>$partido->id,
                        'arbitro_id'=>$arbitro1->id,
                        'tipo'=>'Linea 1'
                    );
                    $partido_arbitro=PartidoArbitro::where('partido_id','=',"$partido->id")->where('arbitro_id','=',"$arbitro1->id")->first();
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
                $arrArbitroA2 = explode(' ', $asistente2);
                $arbitro2=0;
                if(count($arrArbitroA2)>1) {
                    //$arbitro2 = Arbitro::where('nombre', 'like', "%$arrArbitroA2[0]%")->where('apellido', 'like', "%$arrArbitroA2[1]%")->first();
                    $arbitro2=Arbitro::SELECT('arbitros.*')->Join('personas','personas.id','=','arbitros.persona_id')->where('nombre', 'like', "%$arrArbitroA2[0]%")->where('apellido', 'like', "%$arrArbitroA2[1]%")->first();
                }

                if (!$arbitro2){
                    Log::channel('mi_log')->info('OJO!! Asistente NO encontrado: '.$asistente2.' '.$partido->equipol->nombre.' VS '.$partido->equipov->nombre,[]);
                }
                else{
                    $data3=array(
                        'partido_id'=>$partido->id,
                        'arbitro_id'=>$arbitro2->id,
                        'tipo'=>'Linea 2'
                    );
                    $partido_arbitro=PartidoArbitro::where('partido_id','=',"$partido->id")->where('arbitro_id','=',"$arbitro2->id")->first();
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
                $strEntrenador=trim($dtLocal);
                $arrEntrenador = explode(' ', $strEntrenador);
                $entrenadorL='';
                if(count($arrEntrenador)>1){
                    //$entrenadorL=Tecnico::where('nombre','like',"%$arrEntrenador[0]%")->where('apellido','like',"%$arrEntrenador[1]%")->first();
                    $entrenadorL=Tecnico::SELECT('tecnicos.*')->Join('personas','personas.id','=','tecnicos.persona_id')->where('nombre','like',"%$arrEntrenador[0]%")->where('apellido','like',"%$arrEntrenador[1]%")->first();
                }

                if (!empty($entrenadorL)){
                    $data3=array(
                        'partido_id'=>$partido->id,
                        'equipo_id'=>$partido->equipol->id,
                        'tecnico_id'=>$entrenadorL->id
                    );
                    $partido_tecnico=PartidoTecnico::where('partido_id','=',"$partido->id")->where('equipo_id','=',$partido->equipol->id)->where('tecnico_id','=',"$entrenadorL->id")->first();
                    try {
                        if (!empty($partido_tecnico)){

                            $partido_tecnico->update($data3);
                        }
                        else{
                            $partido_tecnico=PartidoTecnico::create($data3);
                        }

                    }catch(QueryException $ex){
                        $error = $ex->getMessage();
                        $ok=0;
                        continue;
                    }
                }
                else{
                    Log::channel('mi_log')->info('OJO!!! Técnico NO encontrado : ' . trim($dtLocal),[]);
                }
                $strEntrenador=trim($dtVisitante);
                $arrEntrenador = explode(' ', $strEntrenador);
                $entrenadorV='';
                if(count($arrEntrenador)>1){
                    //$entrenadorV=Tecnico::where('nombre','like',"%$arrEntrenador[0]%")->where('apellido','like',"%$arrEntrenador[1]%")->first();
                    $entrenadorV=Tecnico::SELECT('tecnicos.*')->Join('personas','personas.id','=','tecnicos.persona_id')->where('nombre','like',"%$arrEntrenador[0]%")->where('apellido','like',"%$arrEntrenador[1]%")->first();
                }

                if (!empty($entrenadorV)){
                    $data3=array(
                        'partido_id'=>$partido->id,
                        'equipo_id'=>$partido->equipov->id,
                        'tecnico_id'=>$entrenadorV->id
                    );
                    $partido_tecnico=PartidoTecnico::where('partido_id','=',"$partido->id")->where('equipo_id','=',$partido->equipov->id)->where('tecnico_id','=',"$entrenadorV->id")->first();
                    try {
                        if (!empty($partido_tecnico)){

                            $partido_tecnico->update($data3);
                        }
                        else{
                            $partido_tecnico=PartidoTecnico::create($data3);
                        }

                    }catch(QueryException $ex){
                        $error = $ex->getMessage();
                        $ok=0;
                        continue;
                    }
                }
                else{
                    Log::channel('mi_log')->info('OJO!!! Técnico NO encontrado : ' . trim($dtVisitante),[]);
                }

            }
            else{
                Log::channel('mi_log')->info('OJO!!! No se econtró la URL2 ' , []);
                /*$error = 'No se econtró la URL2 del partido: '.$partido->equipol->nombre.' VS '.$partido->equipov->nombre;
                $ok=0;
                continue;*/
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
    public function importincidenciasfecha(Request $request)
    {
        set_time_limit(0);
        //Log::channel('mi_log')->info('Entraaaaaa', []);
        $id = $request->get('fechaId');
        $fecha=Fecha::findOrFail($id);

        $grupo=Grupo::findOrFail($fecha->grupo->id);

        $arrYear = explode('/', $grupo->torneo->year);
        $years = str_replace('/', '-', $grupo->torneo->year);
        $year = (count($arrYear)>1)?$arrYear[1]:$arrYear[0];
        $partidos=Partido::where('fecha_id','=',"$id")->get();
        $nombreTorneo=$grupo->torneo->nombre;
        $ok=1;
        DB::beginTransaction();
        foreach ($partidos as $partido){
            $strLocal = $partido->equipol->nombre;
            $strVisitante = $partido->equipov->nombre;
            $golesTotales = $partido->golesl+$partido->golesv;
            $golesLocales = $partido->golesl;
            $golesVisitantes = $partido->golesv;
            Log::channel('mi_log')->info('Partido ' .$partido->equipol->nombre.' VS '.$partido->equipov->nombre, []);




            try {
                //Log::channel('mi_log')->info('Partido ' .$partido->equipol->nombre.' VS '.$partido->equipov->nombre, []);
                Log::channel('mi_log')->info('OJO!!! URL ' .'https://www.livefutbol.com/cronica/primera-division-'.$years.'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'/', []);
                $html2 = HtmlDomParser::file_get_html('https://www.livefutbol.com/cronica/primera-division-'.$years.'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'/', false, null, 0);
                /*Log::channel('mi_log')->info('OJO!!! URL ' .'https://arg.worldfootball.net/cronica/copa-de-la-superliga-'.$years.'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'/', []);

                $html2 = HtmlDomParser::file_get_html('https://arg.worldfootball.net/cronica/copa-de-la-superliga-'.$years.'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'/', false, null, 0);*/

                if (!$html2){

                    Log::channel('mi_log')->info('OJO!!! URL ' .'https://www.livefutbol.com/cronica/primera-division-'.$years.'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'_2/', []);
                    $html2 = HtmlDomParser::file_get_html('https://www.livefutbol.com/cronica/primera-division-'.$years.'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'_2/', false, null, 0);
                }
                $linkArray=array();
                $entrenadoresArray = array();
                $nombreArbitro ='';
                if (!$html2) {
                    Log::channel('mi_log')->info('OJO!!! URL ' .'https://arg.worldfootball.net/cronica/primera-division-'.$years.'-'.strtr($nombreTorneo, " ", "-").'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'/', []);

                    $html2 = HtmlDomParser::file_get_html('https://arg.worldfootball.net/cronica/primera-division-'.$years.'-'.strtr($nombreTorneo, " ", "-").'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'/', false, null, 0);
                    /*Log::channel('mi_log')->info('OJO!!! URL ' .'https://arg.worldfootball.net/cronica/copa-de-la-superliga-'.$years.'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'/', []);

                    $html2 = HtmlDomParser::file_get_html('https://arg.worldfootball.net/cronica/copa-de-la-superliga-'.$years.'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'/', false, null, 0);*/

                }
                if (!$html2) {
                    Log::channel('mi_log')->info('OJO!!! URL ' .'https://arg.worldfootball.net/cronica/copa-de-la-superliga-'.$years.'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'/', []);

                    $html2 = HtmlDomParser::file_get_html('https://arg.worldfootball.net/cronica/copa-de-la-superliga'.$years.'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'/', false, null, 0);
                }

            }
            catch (Exception $ex) {
                $html2='';
            }
            $dtLocal ='';
            $dtVisitante ='';
            if ($html2){

                $goles=0;
                $golesL=0;
                $golesV=0;

                $tabla = 0;
                $equipos = array();
                $tablaIncidencias = 0;
                foreach ($html2->find('table[class=standard_tabelle]') as $element) {




                    if ($tabla==1){
                        $golesArray = array();


                        foreach ($element->find('td') as $td) {
                            $jugadorGol='';
                            $lineaGol='';
                            $minutoGol='';
                            $incidenciaGol='';
                            if($td->find('a')){
                                $jugadorGol = $td->find('a')[0]->title;
                                //Log::channel('mi_log')->info('OJO!! gol: '.$jugadorGol,[]);
                                $lineaGol = $td->plaintext;
                                if (str_contains($lineaGol, $jugadorGol)) {
                                    //$minutoGol = (int) filter_var($lineaGol, FILTER_SANITIZE_NUMBER_INT);
                                    //Log::channel('mi_log')->info('OJO!! gol: '.$lineaGol,[]);
                                    $arrayGol= explode(".",$lineaGol);

                                    $minutoGol = (int) filter_var($arrayGol[0], FILTER_SANITIZE_NUMBER_INT);
                                    if (count($arrayGol)>1){
                                        $adiccion = (int) filter_var($arrayGol[1], FILTER_SANITIZE_NUMBER_INT);
                                        if ($adiccion>0){
                                            Log::channel('mi_log')->info('OJO!! gol addicion: ');
                                            $minutoGol = $minutoGol + $adiccion;
                                        }

                                    }
                                    //Log::channel('mi_log')->info('OJO!! min: '.$minutoGol,[]);
                                }

                                $incidenciaArray = explode('/', $lineaGol);
                                if (count($incidenciaArray)>1){
                                    $incidenciaGol = $incidenciaArray[1];
                                    //Log::channel('mi_log')->info('OJO!! incidencia: '.$incidenciaGol,[]);
                                }
                                $goles++;
                                $golesArray[]=$jugadorGol.'-'.$minutoGol.'-'.$incidenciaGol;
                            }


                        }
                    }
                    if ($tabla==2){

                        $jugadores = array();
                        //Log::channel('mi_log')->info('OJO!! locales:',[]);
                        $suplentes=0;
                        foreach ($element->find('tr') as $tr) {
                            $dorsalTitularL = '';
                            $jugadorTitularL = '';
                            $saleTitularL = '';
                            $amarillaTitularL = '';
                            $dobleamarillaTitularL = '';
                            $rojaTitularL = '';
                            $mintutoTarjetaTitularL = '';
                            $dorsalSuplenteL = '';
                            $jugadorSuplenteL = '';
                            $entraSuplenteL = '';
                            $saleSuplenteL = '';
                            $amarillaSuplenteL = '';
                            $dobleamarillaSuplenteL = '';
                            $rojaSuplenteL = '';
                            $mintutoTarjetaSuplenteL = '';
                            foreach ($tr->find('td') as $td) {
                                //Log::channel('mi_log')->info('OJO!! linea: ' . $td->plaintext, []);
                                if (trim($td->plaintext) == 'Incidencias') {
                                    $tablaIncidencias = 1;
                                }
                                if (!$tablaIncidencias) {
                                    if (trim($td->plaintext) == 'Banquillo') {
                                        $suplentes = 1;

                                    }
                                    if ($td->find('span[class=kleine_schrift]')) {
                                        if ($td->find('span[style=font-weight: bold; color: #646464]')) {
                                            if ($suplentes) {
                                                $dorsalSuplenteL = $td->find('span[class=kleine_schrift]')[0]->plaintext;
                                                //Log::channel('mi_log')->info('OJO!! dorsal suplente: ' . $dorsalSuplenteL, []);
                                            } else {
                                                $dorsalTitularL = $td->find('span[class=kleine_schrift]')[0]->plaintext;
                                                //Log::channel('mi_log')->info('OJO!! dorsal titular: ' . $dorsalTitularL, []);
                                            }

                                        } elseif ($td->find('span[class=rottext]')) {
                                            if ($suplentes) {
                                                //$saleSuplenteL = (int)filter_var($td->find('span[class=rottext]')[0]->plaintext, FILTER_SANITIZE_NUMBER_INT);
                                                $arraySale= explode("'",$td->find('span[class=rottext]')[0]->plaintext);
                                                $saleSuplenteV = (int) $arraySale[0];
                                                if (count($arraySale)>1){
                                                    $adiccion = (int) $arraySale[1];
                                                    if ($adiccion>0){
                                                        Log::channel('mi_log')->info('OJO!! cambio addicion: ');
                                                        $saleSuplenteV = $saleSuplenteV + $adiccion;
                                                    }

                                                }
                                                //Log::channel('mi_log')->info('OJO!! sale suplente: ' . $saleSuplenteL, []);
                                            } else {
                                                //$saleTitularL = (int)filter_var($td->find('span[class=rottext]')[0]->plaintext, FILTER_SANITIZE_NUMBER_INT);
                                                $arraySale= explode("'",$td->find('span[class=rottext]')[0]->plaintext);
                                                $saleTitularL = (int) $arraySale[0];
                                                if (count($arraySale)>1){
                                                    $adiccion = (int) $arraySale[1];
                                                    if ($adiccion>0) {
                                                        Log::channel('mi_log')->info('OJO!! cambio addicion: ');
                                                        $saleTitularL = $saleTitularL + $adiccion;
                                                    }
                                                }
                                                //Log::channel('mi_log')->info('OJO!! sale titular: ' . $saleTitularL, []);
                                            }

                                        } elseif ($td->find('span[class=gruentext]')) {
                                            if ($suplentes) {
                                                //$entraSuplenteL = (int)filter_var($td->find('span[class=gruentext]')[0]->plaintext, FILTER_SANITIZE_NUMBER_INT);

                                                $arrayEntra= explode("'",$td->find('span[class=gruentext]')[0]->plaintext);
                                                $entraSuplenteL = (int) $arrayEntra[0];
                                                if (count($arrayEntra)>1){
                                                    $adiccion = (int) $arrayEntra[1];
                                                    if ($adiccion>0) {
                                                        Log::channel('mi_log')->info('OJO!! cambio addicion: ');
                                                        $entraSuplenteL = $entraSuplenteL + $adiccion;
                                                    }
                                                }
                                                //Log::channel('mi_log')->info('OJO!! entra suplente: ' . $entraSuplenteL, []);
                                            }


                                        } else {
                                            if ($td->find('span[class=kleine_schrift]')[0]->title != '') {
                                                if ($suplentes) {
                                                    //$mintutoTarjetaSuplenteL = $td->find('span[class=kleine_schrift]')[0]->plaintext;
                                                    //Log::channel('mi_log')->info('OJO!! tarjeta: ' . $td->find('span[class=kleine_schrift]')[0]->plaintext, []);

                                                    $arrayTarjeta= explode("'",$td->find('span[class=kleine_schrift]')[0]->plaintext);
                                                    $mintutoTarjetaSuplenteL = (int) $arrayTarjeta[0];
                                                    if (count($arrayTarjeta)>1){
                                                        $adiccion = (int) $arrayTarjeta[1];
                                                        if ($adiccion>0){
                                                            Log::channel('mi_log')->info('OJO!! tarjeta addicion: ');
                                                            $mintutoTarjetaSuplenteL = $mintutoTarjetaSuplenteL + $adiccion;
                                                        }

                                                    }
                                                } else {
                                                    //$mintutoTarjetaTitularL = $td->find('span[class=kleine_schrift]')[0]->plaintext;
                                                    //Log::channel('mi_log')->info('OJO!! tarjeta: ' . $td->find('span[class=kleine_schrift]')[0]->plaintext, []);
                                                    $arrayTarjeta= explode("'",$td->find('span[class=kleine_schrift]')[0]->plaintext);
                                                    $mintutoTarjetaTitularL = (int) $arrayTarjeta[0];
                                                    if (count($arrayTarjeta)>1){
                                                        $adiccion = (int) $arrayTarjeta[1];
                                                        if ($adiccion>0) {
                                                            Log::channel('mi_log')->info('OJO!! tarjeta addicion: ');
                                                            $mintutoTarjetaTitularL = $mintutoTarjetaTitularL + $adiccion;
                                                        }
                                                    }
                                                }


                                            }

                                        }
                                    }

                                    if ($td->find('img')) {
                                        if ($td->find('img')[0]->title == 'Tarjeta amarilla') {
                                            if ($suplentes) {

                                                $amarillaSuplenteL = (int)filter_var($mintutoTarjetaSuplenteL, FILTER_SANITIZE_NUMBER_INT);;
                                                //Log::channel('mi_log')->info('OJO!! amarilla suplente: ' . $amarillaSuplenteL, []);
                                            } else {
                                                $amarillaTitularL = (int)filter_var($mintutoTarjetaTitularL, FILTER_SANITIZE_NUMBER_INT);;
                                                //Log::channel('mi_log')->info('OJO!! amarilla titular: ' . $amarillaTitularL, []);
                                            }

                                        }
                                        if ($td->find('img')[0]->title == 'Roja directa') {
                                            if ($suplentes) {
                                                $rojaSuplenteL = (int)filter_var($mintutoTarjetaSuplenteL, FILTER_SANITIZE_NUMBER_INT);;
                                                //Log::channel('mi_log')->info('OJO!! roja suplente: ' . $rojaSuplenteL, []);
                                            } else {
                                                $rojaTitularL = (int)filter_var($mintutoTarjetaTitularL, FILTER_SANITIZE_NUMBER_INT);
                                                //Log::channel('mi_log')->info('OJO!! roja titular: ' . $rojaTitularL, []);
                                            }

                                        }
                                        if ($td->find('img')[0]->title == 'Doble amarilla') {
                                            if ($suplentes) {
                                                $dobleamarillaSuplenteL = (int)filter_var($mintutoTarjetaSuplenteL, FILTER_SANITIZE_NUMBER_INT);;
                                                //Log::channel('mi_log')->info('OJO!! dobleamarilla suplente: ' . $dobleamarillaSuplenteL, []);
                                            } else {
                                                $dobleamarillaTitularL = (int)filter_var($mintutoTarjetaTitularL, FILTER_SANITIZE_NUMBER_INT);;
                                                //Log::channel('mi_log')->info('OJO!! dobleamarilla titular: ' . $dobleamarillaTitularL, []);
                                            }

                                        }

                                    }

                                    if ($td->find('a')) {
                                        if ($suplentes) {
                                            $jugadorSuplenteL = $td->find('a')[0]->title;
                                            //Log::channel('mi_log')->info('OJO!! suplente: ' . $jugadorSuplenteL, []);
                                        } else {
                                            $jugadorTitularL = $td->find('a')[0]->title;
                                            //Log::channel('mi_log')->info('OJO!! titular: ' . $jugadorTitularL, []);
                                        }


                                    }
                                }


                            }
                            if (!$tablaIncidencias) {
                                if (($jugadorTitularL) || ($jugadorSuplenteL)) {
                                    $incidenciasT = array();
                                    $incidenciasS = array();
                                    if ($saleTitularL) {
                                        $incidenciasT[] = array('Sale', $saleTitularL);
                                    }
                                    if ($amarillaTitularL) {
                                        $incidenciasT[] = array('Tarjeta amarilla', $amarillaTitularL);
                                    }
                                    if ($dobleamarillaTitularL) {
                                        $incidenciasT[] = array('Expulsado por doble amarilla', $dobleamarillaTitularL);
                                    }
                                    if ($rojaTitularL) {
                                        $incidenciasT[] = array('Tarjeta roja', $rojaTitularL);
                                    }

                                    if (!empty($golesArray)) {
                                        foreach ($golesArray as $golmin) {
                                            //Log::channel('mi_log')->info('OJO!! comparar goles: ' . trim($jugadorTitularL).'=='.trim($jugador).' - '.$golmin, []);
                                            $incGol = explode('-', $golmin);
                                            if (trim($jugadorTitularL) == trim($incGol[0])) {

                                                $minGol = $incGol[1];
                                                $incidenciaGol = '';
                                                if (!empty($incGol[2])) {
                                                    $incidenciaGol = $incGol[2];
                                                }
                                                if (!$incidenciaGol) {
                                                    $incidenciasT[] = array('Gol', $minGol);
                                                    $golesL++;
                                                } else {
                                                    if (str_contains($incidenciaGol, 'cabeza')) {
                                                        $incidenciasT[] = array('Cabeza', $minGol);
                                                        $golesL++;
                                                    }
                                                    if (str_contains($incidenciaGol, 'penalti')) {
                                                        $incidenciasT[] = array('Penal', $minGol);
                                                        $golesL++;
                                                    }
                                                    if (str_contains($incidenciaGol, 'propia puerta')) {
                                                        $incidenciasT[] = array('Gol en propia meta', $minGol);
                                                        $golesV++;
                                                    }
                                                    if (str_contains($incidenciaGol, 'tiro libre')) {
                                                        $incidenciasT[] = array('Tiro libre', $minGol);
                                                        $golesL++;
                                                    }
                                                    if (str_contains($incidenciaGol, 'derecha')) {
                                                        $incidenciasT[] = array('Gol', $minGol);
                                                        $golesL++;
                                                    }
                                                    if (str_contains($incidenciaGol, 'izquierda')) {
                                                        $incidenciasT[] = array('Gol', $minGol);
                                                        $golesL++;
                                                    }
                                                }


                                            }
                                            if (trim($jugadorSuplenteL) == trim($incGol[0])) {

                                                $minGol = $incGol[1];
                                                $incidenciaGol = '';
                                                if (!empty($incGol[2])) {
                                                    $incidenciaGol = $incGol[2];
                                                }
                                                if (!$incidenciaGol) {
                                                    $incidenciasS[] = array('Gol', $minGol);
                                                    $golesL++;
                                                } else {
                                                    if (str_contains($incidenciaGol, 'cabeza')) {
                                                        $incidenciasS[] = array('Cabeza', $minGol);
                                                        $golesL++;
                                                    }
                                                    if (str_contains($incidenciaGol, 'penalti')) {
                                                        $incidenciasS[] = array('Penal', $minGol);
                                                        $golesL++;
                                                    }
                                                    if (str_contains($incidenciaGol, 'propia puerta')) {
                                                        $incidenciasS[] = array('Gol en propia meta', $minGol);
                                                        $golesV++;
                                                    }
                                                    if (str_contains($incidenciaGol, 'tiro libre')) {
                                                        $incidenciasS[] = array('Tiro libre', $minGol);
                                                        $golesL++;
                                                    }
                                                    if (str_contains($incidenciaGol, 'derecha')) {
                                                        $incidenciasS[] = array('Gol', $minGol);
                                                        $golesL++;
                                                    }
                                                    if (str_contains($incidenciaGol, 'izquierda')) {
                                                        $incidenciasS[] = array('Gol', $minGol);
                                                        $golesL++;
                                                    }
                                                }

                                            }


                                        }
                                    }
                                    if ($amarillaSuplenteL) {
                                        $incidenciasS[] = array('Tarjeta amarilla', $amarillaSuplenteL);
                                    }
                                    if ($dobleamarillaSuplenteL) {
                                        $incidenciasS[] = array('Expulsado por doble amarilla', $dobleamarillaSuplenteL);
                                    }
                                    if ($rojaSuplenteL) {
                                        $incidenciasS[] = array('Tarjeta roja', $rojaSuplenteL);
                                    }
                                    if ($saleSuplenteL) {
                                        $incidenciasS[] = array('Sale', $saleSuplenteL);
                                    }
                                    if ($entraSuplenteL) {
                                        $incidenciasS[] = array('Entra', $entraSuplenteL);
                                    }

                                    if ($suplentes) {
                                        $data2 = array(
                                            'dorsal' => trim($dorsalSuplenteL),
                                            'nombre' => trim($jugadorSuplenteL),
                                            'tipo' => 'Suplente',
                                            'incidencias' => $incidenciasS
                                        );
                                    } else {
                                        $data2 = array(
                                            'dorsal' => trim($dorsalTitularL),
                                            'nombre' => trim($jugadorTitularL),
                                            'tipo' => 'Titular',
                                            'incidencias' => $incidenciasT
                                        );
                                    }
                                    if (!empty($data2)) {
                                        $jugadores[] = $data2;
                                    }
                                }




                            }
                        }
                        $data = array(

                            'equipo' => $partido->equipol->nombre,

                            'jugadores' => $jugadores
                        );
                        if (!$tablaIncidencias) {
                            if (!empty($data)){
                                $equipos[] = $data;
                            }

                        }
                    }


                    if ($tabla==3){
                        $jugadores = array();
                        //Log::channel('mi_log')->info('OJO!! visitante:',[]);
                        $suplentes=0;
                        foreach ($element->find('tr') as $tr) {
                            $dorsalTitularV = '';
                            $jugadorTitularV = '';
                            $saleTitularV = '';
                            $amarillaTitularV = '';
                            $dobleamarillaTitularV = '';
                            $rojaTitularV = '';
                            $mintutoTarjetaTitularV = '';
                            $dorsalSuplenteV = '';
                            $jugadorSuplenteV = '';
                            $entraSuplenteV = '';
                            $saleSuplenteV = '';
                            $amarillaSuplenteV = '';
                            $dobleamarillaSuplenteV = '';
                            $rojaSuplenteV = '';
                            $mintutoTarjetaSuplenteV = '';
                            foreach ($tr->find('td') as $td) {
                                ////Log::channel('mi_log')->info('OJO!! linea: ' . $td->plaintext, []);
                                if (trim($td->plaintext)=='Banquillo') {
                                    $suplentes=1;
                                }
                                if ($td->find('span[class=kleine_schrift]')) {
                                    if ($td->find('span[style=font-weight: bold; color: #646464]')) {
                                        if ($suplentes){
                                            $dorsalSuplenteV= $td->find('span[class=kleine_schrift]')[0]->plaintext;
                                            //Log::channel('mi_log')->info('OJO!! dorsal suplente: ' . $dorsalSuplenteV, []);
                                        }
                                        else{
                                            $dorsalTitularV = $td->find('span[class=kleine_schrift]')[0]->plaintext;
                                            //Log::channel('mi_log')->info('OJO!! dorsal titular: ' . $dorsalTitularV, []);
                                        }

                                    }
                                    elseif ($td->find('span[class=rottext]')) {
                                        if ($suplentes){
                                            //$saleSuplenteV = (int) filter_var($td->find('span[class=rottext]')[0]->plaintext, FILTER_SANITIZE_NUMBER_INT);
                                            $arraySale= explode("'",$td->find('span[class=rottext]')[0]->plaintext);
                                            $saleSuplenteV = (int) $arraySale[0];
                                            if (count($arraySale)>1){
                                                $adiccion = (int) $arraySale[1];
                                                if ($adiccion>0) {
                                                    Log::channel('mi_log')->info('OJO!! cambio addicion: ');
                                                    $saleSuplenteV = $saleSuplenteV + $adiccion;
                                                }
                                            }
                                            //Log::channel('mi_log')->info('OJO!! sale suplente: ' . $saleSuplenteV, []);
                                        }
                                        else{
                                            //$saleTitularV = (int) filter_var($td->find('span[class=rottext]')[0]->plaintext, FILTER_SANITIZE_NUMBER_INT);


                                            $arraySale= explode("'",$td->find('span[class=rottext]')[0]->plaintext);
                                            $saleTitularV = (int) $arraySale[0];
                                            if (count($arraySale)>1){
                                                $adiccion = (int) $arraySale[1];
                                                if ($adiccion>0) {
                                                    Log::channel('mi_log')->info('OJO!! cambio addicion: ');
                                                    $saleTitularV = $saleTitularV + $adiccion;
                                                }
                                            }
                                            //Log::channel('mi_log')->info('OJO!! sale titular: ' . $saleTitularV, []);
                                        }

                                    }
                                    elseif ($td->find('span[class=gruentext]')) {
                                        if ($suplentes){
                                            //$entraSuplenteV = (int) filter_var($td->find('span[class=gruentext]')[0]->plaintext, FILTER_SANITIZE_NUMBER_INT);

                                            $arrayEntra= explode("'",$td->find('span[class=gruentext]')[0]->plaintext);
                                            $entraSuplenteV = (int) $arrayEntra[0];
                                            if (count($arrayEntra)>1){
                                                $adiccion = (int) $arrayEntra[1];
                                                if ($adiccion>0) {
                                                    Log::channel('mi_log')->info('OJO!! cambio addicion: ');
                                                    $entraSuplenteV = $entraSuplenteV + $adiccion;
                                                }
                                            }
                                            //Log::channel('mi_log')->info('OJO!! entra suplente: ' . $entraSuplenteV, []);
                                        }


                                    }
                                    else{
                                        if ($td->find('span[class=kleine_schrift]')[0]->title !=''){
                                            if ($suplentes){
                                                //$mintutoTarjetaSuplenteV = $td->find('span[class=kleine_schrift]')[0]->plaintext;
                                                //Log::channel('mi_log')->info('OJO!! tarjeta: ' . $td->find('span[class=kleine_schrift]')[0]->plaintext, []);
                                                $arrayTarjeta= explode("'",$td->find('span[class=kleine_schrift]')[0]->plaintext);
                                                $mintutoTarjetaSuplenteV = (int) $arrayTarjeta[0];
                                                if (count($arrayTarjeta)>1){
                                                    $adiccion = (int) $arrayTarjeta[1];
                                                    if ($adiccion>0) {
                                                        Log::channel('mi_log')->info('OJO!! tarjeta addicion: ');
                                                        $mintutoTarjetaSuplenteV = $mintutoTarjetaSuplenteV + $adiccion;
                                                    }
                                                }
                                            }
                                            else{
                                                //$mintutoTarjetaTitularV = $td->find('span[class=kleine_schrift]')[0]->plaintext;
                                                //Log::channel('mi_log')->info('OJO!! tarjeta: ' . $td->find('span[class=kleine_schrift]')[0]->plaintext, []);
                                                $arrayTarjeta= explode("'",$td->find('span[class=kleine_schrift]')[0]->plaintext);
                                                $mintutoTarjetaTitularV = (int) $arrayTarjeta[0];
                                                if (count($arrayTarjeta)>1){
                                                    $adiccion = (int) $arrayTarjeta[1];
                                                    if ($adiccion>0) {
                                                        Log::channel('mi_log')->info('OJO!! tarjeta addicion: ');
                                                        $mintutoTarjetaTitularV = $mintutoTarjetaTitularV + $adiccion;
                                                    }
                                                }
                                            }




                                        }

                                    }
                                }

                                if ($td->find('img')) {
                                    if ($td->find('img')[0]->title == 'Tarjeta amarilla') {
                                        if ($suplentes){
                                            $amarillaSuplenteV = (int) filter_var($mintutoTarjetaSuplenteV, FILTER_SANITIZE_NUMBER_INT);;
                                            //Log::channel('mi_log')->info('OJO!! amarilla suplente: ' . $mintutoTarjetaSuplenteV, []);
                                        }
                                        else{
                                            $amarillaTitularV = (int) filter_var($mintutoTarjetaTitularV, FILTER_SANITIZE_NUMBER_INT);;
                                            //Log::channel('mi_log')->info('OJO!! amarilla titular: ' . $amarillaTitularV, []);
                                        }

                                    }
                                    if ($td->find('img')[0]->title == 'Roja directa') {
                                        if ($suplentes){
                                            $rojaSuplenteV =  (int) filter_var($mintutoTarjetaSuplenteV, FILTER_SANITIZE_NUMBER_INT);;
                                            //Log::channel('mi_log')->info('OJO!! roja titular: ' . $rojaSuplenteV, []);
                                        }
                                        else{
                                            $rojaTitularV =  (int) filter_var($mintutoTarjetaTitularV, FILTER_SANITIZE_NUMBER_INT);;
                                            //Log::channel('mi_log')->info('OJO!! roja titular: ' . $rojaTitularV, []);
                                        }

                                    }
                                    if ($td->find('img')[0]->title == 'Doble amarilla') {
                                        if ($suplentes){
                                            $dobleamarillaSuplenteV = (int) filter_var($mintutoTarjetaSuplenteV, FILTER_SANITIZE_NUMBER_INT);;
                                            //Log::channel('mi_log')->info('OJO!! dobleamarilla titular: ' . $dobleamarillaSuplenteV, []);
                                        }
                                        else{
                                            $dobleamarillaTitularV = (int) filter_var($mintutoTarjetaTitularV, FILTER_SANITIZE_NUMBER_INT);;
                                            //Log::channel('mi_log')->info('OJO!! dobleamarilla titular: ' . $dobleamarillaTitularV, []);
                                        }

                                    }

                                }

                                if ($td->find('a')) {
                                    if ($suplentes){
                                        $jugadorSuplenteV = $td->find('a')[0]->title;
                                        //Log::channel('mi_log')->info('OJO!! suplente: ' . $jugadorSuplenteV, []);
                                    }
                                    else{
                                        $jugadorTitularV = $td->find('a')[0]->title;
                                        //Log::channel('mi_log')->info('OJO!! titular: ' . $jugadorTitularV, []);
                                    }


                                }


                            }
                            if ($jugadorTitularV || $jugadorSuplenteV){
                                $incidenciasT = array();
                                $incidenciasS = array();
                                if ($saleTitularV){
                                    $incidenciasT[]=array('Sale', $saleTitularV);
                                }
                                if ($amarillaTitularV){
                                    $incidenciasT[]=array('Tarjeta amarilla', $amarillaTitularV);
                                }
                                if ($dobleamarillaTitularV){
                                    $incidenciasT[]=array('Expulsado por doble amarilla', $dobleamarillaTitularV);
                                }
                                if ($rojaTitularV){
                                    $incidenciasT[]=array('Tarjeta roja', $rojaTitularV);
                                }

                                if (!empty($golesArray)){
                                    foreach ($golesArray as $golmin){
                                        //Log::channel('mi_log')->info('OJO!! comparar goles: ' . trim($jugador).' - '.$golmin, []);
                                        $incGol = explode('-',$golmin);
                                        if (trim($jugadorTitularV)==trim($incGol[0])){


                                            $minGol = $incGol[1];
                                            $incidenciaGol='';
                                            if (!empty($incGol[2])){
                                                $incidenciaGol = $incGol[2];
                                            }

                                            if (!$incidenciaGol){
                                                $incidenciasT[]=array('Gol', $minGol);
                                                $golesV++;
                                            }
                                            else{
                                                if (str_contains($incidenciaGol,'cabeza')) {
                                                    $incidenciasT[]=array('Cabeza', $minGol);
                                                    $golesV++;
                                                }
                                                if (str_contains($incidenciaGol,'penalti')) {

                                                    $incidenciasT[]=array('Penal', $minGol);
                                                    $golesV++;
                                                }
                                                if (str_contains($incidenciaGol,'propia puerta')) {
                                                    $incidenciasT[]=array('Gol en propia meta', $minGol);
                                                    $golesL++;
                                                }
                                                if (str_contains($incidenciaGol,'tiro libre')) {
                                                    $incidenciasT[]=array('Tiro libre', $minGol);
                                                    $golesV++;
                                                }
                                                if (str_contains($incidenciaGol, 'derecha')) {
                                                    $incidenciasT[] = array('Gol', $minGol);
                                                    $golesV++;
                                                }
                                                if (str_contains($incidenciaGol, 'izquierda')) {
                                                    $incidenciasT[] = array('Gol', $minGol);
                                                    $golesV++;
                                                }
                                            }


                                        }
                                        if (trim($jugadorSuplenteV)==trim($incGol[0])){

                                            $minGol = $incGol[1];
                                            $incidenciaGol='';
                                            if (!empty($incGol[2])){
                                                $incidenciaGol = $incGol[2];
                                            }
                                            if (!$incidenciaGol){
                                                $incidenciasS[]=array('Gol', $minGol);
                                                $golesV++;
                                            }
                                            else{
                                                if (str_contains($incidenciaGol,'cabeza')) {
                                                    $incidenciasS[]=array('Cabeza', $minGol);
                                                    $golesV++;
                                                }
                                                if (str_contains($incidenciaGol,'penalti')) {
                                                    $incidenciasS[]=array('Penal', $minGol);
                                                    $golesV++;
                                                }
                                                if (str_contains($incidenciaGol,'propia puerta')) {
                                                    $incidenciasS[]=array('Gol en propia meta', $minGol);
                                                    $golesL++;
                                                }
                                                if (str_contains($incidenciaGol,'tiro libre')) {
                                                    $incidenciasS[]=array('Tiro libre', $minGol);
                                                    $golesV++;
                                                }
                                                if (str_contains($incidenciaGol, 'derecha')) {
                                                    $incidenciasS[] = array('Gol', $minGol);
                                                    $golesV++;
                                                }
                                                if (str_contains($incidenciaGol, 'izquierda')) {
                                                    $incidenciasS[] = array('Gol', $minGol);
                                                    $golesV++;
                                                }
                                            }


                                        }



                                    }
                                }
                                if ($amarillaSuplenteV){
                                    $incidenciasS[]=array('Tarjeta amarilla', $amarillaSuplenteV);
                                }
                                if ($dobleamarillaSuplenteV){
                                    $incidenciasS[]=array('Expulsado por doble amarilla', $dobleamarillaSuplenteV);
                                }
                                if ($rojaSuplenteV){
                                    $incidenciasS[]=array('Tarjeta roja', $rojaSuplenteV);
                                }
                                if ($saleSuplenteV){
                                    $incidenciasS[]=array('Sale', $saleSuplenteV);
                                }
                                if ($entraSuplenteV){
                                    $incidenciasS[]=array('Entra', $entraSuplenteV);
                                }

                                if ($suplentes){
                                    $data2 = array(
                                        'dorsal' => trim($dorsalSuplenteV),
                                        'nombre' => trim($jugadorSuplenteV),
                                        'tipo' => 'Suplente',
                                        'incidencias' =>$incidenciasS
                                    );
                                }
                                else{
                                    $data2 = array(
                                        'dorsal' => trim($dorsalTitularV),
                                        'nombre' => trim($jugadorTitularV),
                                        'tipo' => 'Titular',
                                        'incidencias' => $incidenciasT
                                    );
                                }
                                if (!empty($data2)){
                                    $jugadores[]=$data2;
                                }
                            }



                        }
                        $data = array(

                            'equipo' => $partido->equipov->nombre,

                            'jugadores' => $jugadores
                        );
                        if (!empty($data)){
                            $equipos[] = $data;
                        }

                    }


                    if (!$tablaIncidencias){
                        $tabla ++;

                    }

                    $tablaIncidencias=0;



                    $entrenadoresArray = explode('Entrenador:', $element->plaintext);
                    if (count($entrenadoresArray)>3){
                        Log::channel('mi_log')->info('OJO!! varios entrenadores: '.$partido->equipol->nombre.' VS '.$partido->equipov->nombre,[]);
                    }
                    if(count($entrenadoresArray)>1){
                        $dtLocal = $entrenadoresArray[1];
                        //Log::channel('mi_log')->info('DT Local: '.utf8_decode($entrenadoresArray[1]), []);
                        if (isset($entrenadoresArray[2])){
                            $dtVisitante = $entrenadoresArray[2];
                        }
                        else{
                            Log::channel('mi_log')->info('OJO!! Falta Entrenador visitante: '.$partido->equipol->nombre.' VS '.$partido->equipov->nombre,[]);
                        }

                        //Log::channel('mi_log')->info('DT Visitante: '.utf8_decode($entrenadoresArray[2]), []);
                    }
                    else{
                        $asistente=0;
                        $asistente1='';
                        $asistente2='';
                        $arbitro='';
                        $arbitro1='';
                        $arbitro2='';
                        foreach ($element->find('td[class="dunkel"]') as $element2) {

                            foreach ($element2->find('a') as $link) {
                                $linkArray = explode(' ', $link->title);

                                if (($linkArray[0])=='Árbitro'){
                                    if (($linkArray[1])=='asistente'){
                                        $nombreAsistente = '';
                                        for ($i = 2; $i < count($linkArray); $i++) {
                                            $nombreAsistente .= ($linkArray[$i]).' ';
                                        }
                                        if ($asistente==0){
                                            $asistente1= $nombreAsistente;
                                            Log::channel('mi_log')->info('Asistente 1: '.$nombreAsistente, []);
                                            $asistente++;
                                        }
                                        else{
                                            $asistente2= $nombreAsistente;
                                            Log::channel('mi_log')->info('Asistente 2: '.$nombreAsistente, []);
                                            $asistente++;
                                        }

                                    }
                                    else{
                                        $nombreArbitro = '';
                                        for ($i = 1; $i < count($linkArray); $i++) {
                                            $nombreArbitro .= ($linkArray[$i]).' ';
                                        }

                                        Log::channel('mi_log')->info('Arbitro: '.$nombreArbitro, []);

                                    }
                                }


                            }

                        }

                    }

                }

                $arrArbitro = explode(' ', $nombreArbitro);
                $arbitro=0;
                if(count($arrArbitro)>1) {
                    //$arbitro = Arbitro::where('nombre', 'like', "%$arrArbitro[0]%")->where('apellido', 'like', "%$arrArbitro[1]%")->first();
                    $arbitro=Arbitro::SELECT('arbitros.*')->Join('personas','personas.id','=','arbitros.persona_id')->where('apellido', 'like', "%$arrArbitro[1]%")->first();
                }

                if (!$arbitro){
                    Log::channel('mi_log')->info('OJO!! Arbitro NO encontrado: '.$nombreArbitro.' '.$partido->equipol->nombre.' VS '.$partido->equipov->nombre,[]);
                }
                else{
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
                $arrArbitroA1 = explode(' ', $asistente1);
                $arbitro1=0;
                if(count($arrArbitroA1)>1) {
                    //$arbitro1 = Arbitro::where('nombre', 'like', "%$arrArbitroA1[0]%")->where('apellido', 'like', "%$arrArbitroA1[1]%")->first();
                    $arbitro1=Arbitro::SELECT('arbitros.*')->Join('personas','personas.id','=','arbitros.persona_id')->where('nombre', 'like', "%$arrArbitroA1[0]%")->where('apellido', 'like', "%$arrArbitroA1[1]%")->first();
                }

                if (!$arbitro1){
                    Log::channel('mi_log')->info('OJO!! Asistente NO encontrado: '.$asistente1.' '.$partido->equipol->nombre.' VS '.$partido->equipov->nombre,[]);
                }
                else{
                    $data3=array(
                        'partido_id'=>$partido->id,
                        'arbitro_id'=>$arbitro1->id,
                        'tipo'=>'Linea 1'
                    );
                    $partido_arbitro=PartidoArbitro::where('partido_id','=',"$partido->id")->where('arbitro_id','=',"$arbitro1->id")->first();
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
                $arrArbitroA2 = explode(' ', $asistente2);
                $arbitro2=0;
                if(count($arrArbitroA2)>1) {
                    //$arbitro2 = Arbitro::where('nombre', 'like', "%$arrArbitroA2[0]%")->where('apellido', 'like', "%$arrArbitroA2[1]%")->first();
                    $arbitro2=Arbitro::SELECT('arbitros.*')->Join('personas','personas.id','=','arbitros.persona_id')->where('nombre', 'like', "%$arrArbitroA2[0]%")->where('apellido', 'like', "%$arrArbitroA2[1]%")->first();
                }

                if (!$arbitro2){
                    Log::channel('mi_log')->info('OJO!! Asistente NO encontrado: '.$asistente2.' '.$partido->equipol->nombre.' VS '.$partido->equipov->nombre,[]);
                }
                else{
                    $data3=array(
                        'partido_id'=>$partido->id,
                        'arbitro_id'=>$arbitro2->id,
                        'tipo'=>'Linea 2'
                    );
                    $partido_arbitro=PartidoArbitro::where('partido_id','=',"$partido->id")->where('arbitro_id','=',"$arbitro2->id")->first();
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
                $strEntrenador=trim($dtLocal);
                $arrEntrenador = explode(' ', $strEntrenador);
                $entrenadorL='';
                if(count($arrEntrenador)>1){
                    //$entrenadorL=Tecnico::where('nombre','like',"%$arrEntrenador[0]%")->where('apellido','like',"%$arrEntrenador[1]%")->first();
                    $entrenadorL=Tecnico::SELECT('tecnicos.*')->Join('personas','personas.id','=','tecnicos.persona_id')->where('nombre','like',"%$arrEntrenador[0]%")->where('apellido','like',"%$arrEntrenador[1]%")->first();
                }

                if (!empty($entrenadorL)){
                    $data3=array(
                        'partido_id'=>$partido->id,
                        'equipo_id'=>$partido->equipol->id,
                        'tecnico_id'=>$entrenadorL->id
                    );
                    $partido_tecnico=PartidoTecnico::where('partido_id','=',"$partido->id")->where('equipo_id','=',$partido->equipol->id)->where('tecnico_id','=',"$entrenadorL->id")->first();
                    try {
                        if (!empty($partido_tecnico)){

                            $partido_tecnico->update($data3);
                        }
                        else{
                            $partido_tecnico=PartidoTecnico::create($data3);
                        }

                    }catch(QueryException $ex){
                        $error = $ex->getMessage();
                        $ok=0;
                        continue;
                    }
                }
                else{
                    Log::channel('mi_log')->info('OJO!!! Técnico NO encontrado : ' . trim($dtLocal),[]);
                }
                $strEntrenador=trim($dtVisitante);
                $arrEntrenador = explode(' ', $strEntrenador);
                $entrenadorV='';
                if(count($arrEntrenador)>1){
                    //$entrenadorV=Tecnico::where('nombre','like',"%$arrEntrenador[0]%")->where('apellido','like',"%$arrEntrenador[1]%")->first();
                    $entrenadorV=Tecnico::SELECT('tecnicos.*')->Join('personas','personas.id','=','tecnicos.persona_id')->where('nombre','like',"%$arrEntrenador[0]%")->where('apellido','like',"%$arrEntrenador[1]%")->first();
                }

                if (!empty($entrenadorV)){
                    $data3=array(
                        'partido_id'=>$partido->id,
                        'equipo_id'=>$partido->equipov->id,
                        'tecnico_id'=>$entrenadorV->id
                    );
                    $partido_tecnico=PartidoTecnico::where('partido_id','=',"$partido->id")->where('equipo_id','=',$partido->equipov->id)->where('tecnico_id','=',"$entrenadorV->id")->first();
                    try {
                        if (!empty($partido_tecnico)){

                            $partido_tecnico->update($data3);
                        }
                        else{
                            $partido_tecnico=PartidoTecnico::create($data3);
                        }

                    }catch(QueryException $ex){
                        $error = $ex->getMessage();
                        $ok=0;
                        continue;
                    }
                }
                else{
                    Log::channel('mi_log')->info('OJO!!! Técnico NO encontrado : ' . trim($dtVisitante),[]);
                }
                if (($golesL!=$golesLocales)||($golesV!=$golesVisitantes)) {
                    Log::channel('mi_log')->info('OJO!!! No coincide la cantidad de goles en: ' . $partido->equipol->nombre . ' VS ' . $partido->equipov->nombre . ' -> ' . $golesL.' a '. $golesV. ' - ' . $golesLocales. ' a '.$golesVisitantes, []);
                }
                foreach ($equipos as $eq) {
                    Log::channel('mi_log')->info('Equipo  ' . $eq['equipo'], []);
                    $strEquipo=trim($eq['equipo']);
                    $equipo=Equipo::where('nombre','like',"%$strEquipo%")->first();
                    if (!empty($equipo)){
                        foreach ($eq['jugadores'] as $jugador) {
                            Log::channel('mi_log')->info('Jugador: ' . $jugador['dorsal'] . ' ' . $jugador['nombre'] . ' del equipo ' . $strEquipo, []);
                            $grupos = Grupo::where('torneo_id', '=',$grupo->torneo->id)->get();
                            $arrgrupos='';
                            foreach ($grupos as $grupo){
                                $arrgrupos .=$grupo->id.',';
                            }

                            $plantillas = Plantilla::wherein('grupo_id',explode(',', $arrgrupos))->where('equipo_id','=',$equipo->id)->get();

                            $arrplantillas='';
                            foreach ($plantillas as $plantilla){
                                $arrplantillas .=$plantilla->id.',';
                            }




                            if (!empty($plantillas)){

                                if(!empty($jugador['dorsal'])){

                                    $plantillaJugador = PlantillaJugador::wherein('plantilla_id',explode(',', $arrplantillas))->distinct()->where('dorsal','=',$jugador['dorsal'])->first();
                                }
                                else{
                                    //print_r($jugador);
                                    $plantillaJugador='';
                                    Log::channel('mi_log')->info('OJO!!! con el jugador no está en la plantilla del equipo ' . $strEquipo, []);
                                }
                            }
                            else{
                                $plantillaJugador='';
                                Log::channel('mi_log')->info('OJO!!! No hay plantilla del equipo ' . $strEquipo, []);
                            }
                            if (!empty($plantillaJugador)) {
                                $arrApellido = explode(' ', $jugador['nombre']);
                                $mismoDorsal = 0;
                                foreach ($arrApellido as $apellido) {
                                    $consultarJugador = Jugador::Join('personas','personas.id','=','jugadors.persona_id')->where('jugadors.id', '=', $plantillaJugador->jugador->id)->where('apellido', 'LIKE', "%$apellido%")->first();
                                    if (!empty($consultarJugador)) {
                                        $mismoDorsal = 1;
                                        continue;
                                    }
                                }
                                if (!$mismoDorsal) {
                                    Log::channel('mi_log')->info('OJO!!! con jugador: ' . $jugador['dorsal'] . ' ' . $jugador['nombre'] . ' del equipo ' . $strEquipo, []);
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
                                Log::channel('mi_log')->info('OJO!!! NO se encontró al jugador: ' . $jugadorMostrar.' del equipo '.$strEquipo,[]);
                            }

                            foreach ($jugador['incidencias'] as $incidencia) {

                                if (!empty($incidencia)) {
                                    //Log::channel('mi_log')->info('Incidencia: ' . trim($incidencia[0]) . ' MIN: ' . intval(trim($incidencia[1])), []);
                                    //Log::channel('mi_log')->info('Incidencias Jugador: ' . $jugador['dorsal'] . ' ' . $jugador['nombre'] . ' - '. trim($incidencia[0]) . ' MIN: ' . intval(trim($incidencia[1])), []);
                                    $tipogol='';
                                    switch (trim($incidencia[0])) {
                                        case 'Gol':
                                            $tipogol='Jugada';
                                            break;
                                        case 'Penal':
                                            $tipogol='Penal';
                                            break;
                                        case 'Tiro libre':
                                            $tipogol='Tiro libre';
                                            break;
                                        case 'Cabeza':
                                            $tipogol='Cabeza';
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
                        Log::channel('mi_log')->info('OJO!!! NO se encontró al equipo: ' . $strEquipo,[]);
                    }
                }
            }
            else{
                Log::channel('mi_log')->info('OJO!!! No se econtró la URL2 ' , []);
                /*$error = 'No se econtró la URL2 del partido: '.$partido->equipol->nombre.' VS '.$partido->equipov->nombre;
                $ok=0;
                continue;*/
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
    function sanear_string($string)
    {

        $string = trim($string);

$string = str_replace(
        array('á', 'à', 'ä', 'â', 'ª', 'Á', 'À', 'Â', 'Ä'),
        array('a', 'a', 'a', 'a', 'a', 'A', 'A', 'A', 'A'),
        $string
);

$string = str_replace(
        array('é', 'è', 'ë', 'ê', 'É', 'È', 'Ê', 'Ë'),
        array('e', 'e', 'e', 'e', 'E', 'E', 'E', 'E'),
        $string
);

$string = str_replace(
        array('í', 'ì', 'ï', 'î', 'Í', 'Ì', 'Ï', 'Î'),
        array('i', 'i', 'i', 'i', 'I', 'I', 'I', 'I'),
        $string
);

$string = str_replace(
        array('ó', 'ò', 'ö', 'ô', 'Ó', 'Ò', 'Ö', 'Ô'),
        array('o', 'o', 'o', 'o', 'O', 'O', 'O', 'O'),
        $string
);

$string = str_replace(
        array('ú', 'ù', 'ü', 'û', 'Ú', 'Ù', 'Û', 'Ü'),
        array('u', 'u', 'u', 'u', 'U', 'U', 'U', 'U'),
        $string
);

$string = str_replace(
        array('ñ', 'Ñ', 'ç', 'Ç'),
        array('n', 'N', 'c', 'C',),
        $string
);

//Esta parte se encarga de eliminar cualquier caracter extraño
$string = str_replace(
        array("\\", "¨", "º", "~",
             "#", "@", "|", "!", "\"",
             "·", "$", "%", "&", "/",
             "(", ")", "?", "'", "¡",
             "¿", "[", "^", "<code>", "]",
             "+", "}", "{", "¨", "´",
             ">", "< ", ";", ",", ":",
             ".", " "),
'',
$string
);


return $string;
}
    public function importgolesfecha(Request $request)
    {
        set_time_limit(0);
        //Log::channel('mi_log')->info('Entraaaaaa', []);

        $grupo_id = $request->get('grupoId');

        $grupo=Grupo::findOrFail($grupo_id);


        $fechas=Fecha::where('grupo_id','=',"$grupo_id")->orderBy('numero','ASC')->get();

        // these are the headers for the csv file.
        $headers = array(
            'Content-Type' => 'application/vnd.ms-excel; charset=utf-8',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Content-Disposition' => 'attachment; filename='.$grupo->torneo->nombre.'_'.str_replace('/','_',$grupo->torneo->year).'_goles.csv',
            'Expires' => '0',
            'Pragma' => 'public',
        );


        //I am storing the csv file in public >> files folder. So that why I am creating files folder
        if (!File::exists(public_path()."/files")) {
            File::makeDirectory(public_path() . "/files");
        }

        //creating the download file
        $filename =  public_path('files/'.$grupo->torneo->nombre.'_'.str_replace('/','_',$grupo->torneo->year).'_goles.csv');
        $handle = fopen($filename, 'w');

        //adding the first row
        fputcsv($handle, [
            'Torneo', 'fecha','Partido', 'Jugador','Gol','Observaciones','URL'
        ], "|");




        /*$fecha=Fecha::findOrFail($id);

        $grupo=Grupo::findOrFail($fecha->grupo->id);*/
        DB::beginTransaction();
        foreach ($fechas as $fecha) {
            $id = $fecha->id;
            $arrYear = explode('/', $grupo->torneo->year);
            $years = str_replace('/', '-', $grupo->torneo->year);
            $year = (count($arrYear) > 1) ? $arrYear[1] : $arrYear[0];
            $partidos = Partido::where('fecha_id', '=', "$id")->get();
            $nombreTorneo = $grupo->torneo->nombre;
            $ok = 1;
            Log::channel('mi_log')->info('Fecha ' . $fecha->numero, []);
            foreach ($partidos as $partido) {
                $strLocal = $partido->equipol->nombre;
                $strVisitante = $partido->equipov->nombre;
                $golesTotales = $partido->golesl + $partido->golesv;
                $golesLocales = $partido->golesl;
                $golesVisitantes = $partido->golesv;
                Log::channel('mi_log')->info('Partido ' . $partido->equipol->nombre . ' VS ' . $partido->equipov->nombre, []);

                $goles=Gol::where('partido_id','=',"$partido->id")->orderBy('minuto','ASC')->get();
                $jugadorGolArray = array();
                foreach ($goles as $gol) {
                    Log::channel('mi_log')->info('Gol ' . $gol->jugador->persona->nombre.' - '.$gol->jugador->persona->apellido.' - '.$gol->tipo.' - '.$gol->minuto, []);
                    $alineacion=Alineacion::where('partido_id','=',"$partido->id")->where('jugador_id','=',$gol->jugador->id)->first();
                    if (!empty($alineacion)) {
                        Log::channel('mi_log')->info('OJO!!! - juega en: '.$alineacion->equipo->nombre, []);
                        $juegaEn=$alineacion->equipo->nombre;
                    }
                    if ($gol->tipo=='Jugada'){
                        $arrayNombre=explode(' ',$gol->jugador->persona->nombre);
                        $arrayApellido=explode(' ',$gol->jugador->persona->apellido);
                        $nombre = $arrayNombre[0];
                        $apellido = $arrayApellido[0];
                        $nombre2 = '';
                        if (count($arrayNombre)>1){
                            $nombre2 = $arrayNombre[1];
                        }
                        $apellido2 = '';
                        if (count($arrayApellido)>1){
                            $apellido2 = $arrayApellido[1];
                        }
                        try {
                            $urlJugador = 'http://www.futbol360.com.ar/jugadores/' . strtolower($this->sanear_string(str_replace(' ','-',$gol->jugador->persona->nacionalidad))).'/' . strtolower($this->sanear_string($apellido)).'-'.strtolower($this->sanear_string($nombre));
                            Log::channel('mi_log')->info('OJO!!! - '.$urlJugador, []);

                            $html2 = HtmlDomParser::file_get_html($urlJugador, false, null, 0);


                        }
                        catch (Exception $ex) {

                            $html2='';
                        }
                        if (!$html2){
                            try {
                                if ($nombre2) {
                                    $urlJugador = 'http://www.futbol360.com.ar/jugadores/' . strtolower($this->sanear_string(str_replace(' ', '-', $gol->jugador->persona->nacionalidad))) . '/' . strtolower($this->sanear_string($apellido)) . '-' . strtolower($this->sanear_string($nombre)) . '-' . strtolower($this->sanear_string($nombre2));
                                    Log::channel('mi_log')->info('OJO!!! - '.$urlJugador, []);

                                    $html2 = HtmlDomParser::file_get_html($urlJugador, false, null, 0);
                                }

                            }
                            catch (Exception $ex) {

                                $html2='';
                            }
                        }
                        if (!$html2){
                            try {
                                if ($nombre2) {
                                    $urlJugador = 'http://www.futbol360.com.ar/jugadores/' . strtolower($this->sanear_string(str_replace(' ', '-', $gol->jugador->persona->nacionalidad))) . '/' . strtolower($this->sanear_string($apellido)) . '-' . strtolower($this->sanear_string($nombre2));
                                    Log::channel('mi_log')->info('OJO!!! - '.$urlJugador, []);

                                    $html2 = HtmlDomParser::file_get_html($urlJugador, false, null, 0);
                                }

                            }
                            catch (Exception $ex) {

                                $html2='';
                            }
                        }
                        if (!$html2){
                            try {
                                if ($apellido2){
                                    $urlJugador = 'http://www.futbol360.com.ar/jugadores/' . strtolower($this->sanear_string(str_replace(' ','-',$gol->jugador->persona->nacionalidad))).'/' . strtolower($this->sanear_string($apellido)).'-'. strtolower($this->sanear_string($apellido2)).'-'.strtolower($this->sanear_string($nombre));
                                    Log::channel('mi_log')->info('OJO!!! - '.$urlJugador, []);

                                    $html2 = HtmlDomParser::file_get_html($urlJugador, false, null, 0);

                                }



                            }
                            catch (Exception $ex) {

                                $html2='';
                            }
                        }
                        if (!$html2){
                            try {
                                switch ($gol->jugador->id){
                                    case '3890':
                                        $nombre3='diaz-daniel-6231';
                                        break;
                                    case '152':
                                        $nombre3='rodriguez-maxi';
                                        break;
                                    case '2255':
                                        $nombre3='diaz-daniel-848';
                                        break;
                                    case '3278':
                                        $nombre3='barros-schelotto-gmo.';
                                        break;
                                    case '4606':
                                        $nombre3='barros-schelotto-gvo.';
                                        break;
                                    case '4673':
                                        $nombre3='gonzalez-claudio-164';
                                        break;
                                    case '1999':
                                        $nombre3='vuoso-matias';
                                        break;
                                    case '660':
                                        $nombre3='ponzio-leo';
                                        break;
                                    case '2732':
                                        $nombre3='alvarez-cristian-43680';
                                        break;
                                    case '2725':
                                        $nombre3='alvarez-cristian-1390';
                                        break;
                                    case '2355':
                                        $nombre3='alvarez-cristian-3360';
                                        break;
                                    case '4463':
                                        $nombre3='gonzalez-cesar-585';
                                        break;
                                    case '4662':
                                        $nombre3='mas-leo';
                                        break;
                                    case '2862':
                                        $nombre3='morel-rodriguez';
                                        break;
                                    case '3084':
                                        $nombre3='diaz-rodrigo-540';
                                        break;
                                    case '3614':
                                        $nombre3='diaz-rodrigo-26650';
                                        break;
                                    case '2664':
                                        $nombre3='benitez-leandro-705';
                                        break;
                                    case '4649':
                                        $nombre3='iarley';
                                        break;
                                    case '4275':
                                        $nombre3='piriz-alvez-enrique';
                                        break;
                                    case '4629':
                                        $nombre3='ojeda-martin-905';
                                        break;
                                    case '4191':
                                        $nombre3='guglielminpietro';
                                        break;
                                    case '4529':
                                        $nombre3='vannieuwenhoven';
                                        break;
                                    case '2060':
                                        $nombre3='torres-diego-720';
                                        break;
                                    case '3303':
                                        $nombre3='mendoza-franco-689';
                                        break;
                                    case '4025':
                                        $nombre3='fernandes-francou';
                                        break;
                                    case '3688':
                                        $nombre3='diaz-cristian-2825';
                                        break;
                                    case '4047':
                                        $nombre3='garcia-javier-2871';
                                        break;
                                    case '2432':
                                        $nombre3='cabrera-nicolas-733';
                                        break;
                                    case '696':
                                        $nombre3='alvarez-pablo-790';
                                        break;
                                    case '3543':
                                        $nombre3='morales-neuman';
                                        break;
                                    case '3496':
                                        $nombre3='gomez-alejandro-2529';
                                        break;
                                    case '3514':
                                        $nombre3='rios-andres-3629';
                                        break;
                                    case '2411':
                                        $nombre3='manzanelli-cesar';
                                        break;
                                    case '4054':
                                        $nombre3='marioni-bruno';
                                        break;
                                    case '936':
                                        $nombre3='rios-andres-12899';
                                        break;
                                    case '1846':
                                        $nombre3='rios-andres-12899';
                                        break;
                                    case '3277':
                                        $nombre3='gonzalez-cesar-2356';
                                        break;
                                    case '2368':
                                        $nombre3='chavez-cristian-2271';
                                        break;
                                    case '2847':
                                        $nombre3='aguirre-martin-8722';
                                        break;
                                    case '1585':
                                        $nombre3='vega-daniel-6139';
                                        break;
                                    case '2914':
                                        $nombre3='quiroga-facundo-1955';
                                        break;
                                    case '633':
                                        $nombre3='chavez-cristian-20705';
                                        break;
                                    case '3411':
                                        $nombre3='rodriguez-diego-24600';
                                        break;
                                    case '546':
                                        $nombre3='morales-diego-10240';
                                        break;
                                    case '3354':
                                        $nombre3='morales-diego-10240';
                                        break;
                                    case '3094':
                                        $nombre3='roberval-raul';
                                        break;
                                    case '2982':
                                        $nombre3='pio-emanuel';
                                        break;
                                    case '2904':
                                        $nombre3='montoya-munoz';
                                        break;
                                    case '2198':
                                        $nombre3='alvarez-balanta';
                                        break;
                                    case '579':
                                        $nombre3='godoy-fernando-13131';
                                        break;
                                    case '2498':
                                        $nombre3='diaz-cristian-26904';
                                        break;
                                    case '182':
                                        $nombre3='rodriguez-diego-25934';
                                        break;
                                    case '560':
                                        $nombre3='camacho-washington';
                                        break;
                                    case '2019':
                                        $nombre3='montiel-dirego';
                                        break;
                                    case '1885':
                                        $nombre3='gonzalez-leandro-26622';
                                        break;
                                    case '2454':
                                        $nombre3='funes-mori-ramiro';
                                        break;
                                    case '1931':
                                        $nombre3='de-la-fuente-fernando';
                                        break;
                                    case '258':
                                        $nombre3='millo-federico';
                                        break;
                                    case '153':
                                        $nombre3='luis-leal';
                                        break;
                                    case '49':
                                        $nombre3='de-la-cruz-nicolas';
                                        break;
                                    case '720':
                                        $nombre3='de-la-fuente-hernan';
                                        break;
                                    case '229':
                                        $nombre3='de-la-vega-pedro';
                                        break;
                                    case '437':
                                        $nombre3='galvan-brian';
                                        break;
                                    case '481':
                                        $nombre3='/guilherme-parede';
                                        break;
                                    case '1772':
                                        $nombre3='/ulariaga-nahuel';
                                        break;
                                    case '513':
                                        $nombre3='/ortega-francisco-75465';
                                        break;
                                    case '473':
                                        $nombre3='/de-los-santos-matias-44783';
                                        break;
                                    case '5412':
                                        $nombre3='/castrillon-byron';
                                        break;
                                    case '1788':
                                        $nombre3='/puch-ignacio';
                                        break;
                                    case '3144':
                                        $nombre3='/mosquera-jherso';
                                        break;
                                    case '723':
                                        $nombre3='/benedeto-dario';
                                        break;
                                    case '6275':
                                        $nombre3='/carabelli-jeremias';
                                        break;
                                    case '1760':
                                        $nombre3='/sanchez-brian';
                                        break;
                                    case '3096':
                                        $nombre3='/martinez-diego-3449';
                                        break;
                                    default:
                                        $nombre3='';
                                        break;
                                }
                                $urlJugador = 'http://www.futbol360.com.ar/jugadores/' . strtolower($this->sanear_string(str_replace(' ','-',$gol->jugador->persona->nacionalidad))).'/' .$nombre3;
                                Log::channel('mi_log')->info('OJO!!! - '.$urlJugador, []);

                                $html2 = HtmlDomParser::file_get_html($urlJugador, false, null, 0);


                            }
                            catch (Exception $ex) {

                                $html2='';
                            }
                        }
                        if ($html2){
                            $scripts = $html2->find('script');
                            $id_jugador = '';
                            foreach($scripts as $s) {
                                if (strpos($s->innertext, 'id_player:') !== false) {
                                    $script_array = explode('id_player', $s->innertext);
                                    $id_jugador = trim(str_replace(':','',explode('}', $script_array[1])[0]));
                                    Log::channel('mi_log')->info('OJO!! Id jugador: '.$id_jugador,[]);

                                    //$token = $script_array[1];
                                    break;
                                }
                            }
                            if (!$id_jugador){
                                foreach ($html2->find('div[class=playerStats]') as $element) {
                                    if (!$id_jugador) {
                                        foreach ($element->find('table[class=tableStandard]') as $table) {
                                            if (!$id_jugador) {

                                                foreach ($table->find('tr') as $tr) {

                                                    if (!$id_jugador) {
                                                        foreach ($tr->find('td') as $td) {
                                                            if (!$id_jugador) {

                                                                foreach ($td->find('a') as $a) {
                                                                    if (str_contains($a->href, 'item=player&id=')) {
                                                                        $arrIdJugador = explode('item=player&id=', $a->href);
                                                                        $id_jugador = intval($arrIdJugador[1]);
                                                                        Log::channel('mi_log')->info('OJO!! ALT Id jugador: ' . $id_jugador, []);
                                                                        break;
                                                                    }
                                                                }
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                            if ($id_jugador){
                                if ($juegaEn==$strLocal){
                                    $juegaContra=$strVisitante;
                                }
                                if ($juegaEn==$strVisitante){
                                    $juegaContra=$strLocal;
                                }
                                try {
                                    $urlCabeza ='http://www.futbol360.com.ar/detalles/matches-goals.php?item=player&id='.$id_jugador.'&id_team_for='.$this->dameIdEquipoURL($juegaEn).'&id_team_against='.$this->dameIdEquipoURL($juegaContra).'&id_season=0&search_category=head';
                                    Log::channel('mi_log')->info('OJO!!! - '.$urlCabeza, []);

                                    $htmlCabeza = HtmlDomParser::file_get_html($urlCabeza, false, null, 0);

                                    //Log::channel('mi_log')->info('OJO!! URL cabeza: '.$htmlCabeza,[]);




                                }
                                catch (Exception $ex) {
                                    $htmlCabeza='';
                                }
                                if ($htmlCabeza){
                                    foreach ($htmlCabeza->find('div[id=matchesTable]') as $element) {
                                        foreach ($element->find('table[class=tableStandard]') as $table) {
                                            foreach ($table->find('tr') as $tr) {
                                                if (trim($tr->plaintext)!='No hay resultados') {
                                                    foreach ($tr->find('th') as $th) {
                                                        foreach ($th->find('a') as $a) {

                                                           $urlEncontrada=0;
                                                           foreach ($this->dameNombreEquipoURL3($strLocal) as $local3){
                                                               foreach ($this->dameNombreEquipoURL3($strVisitante) as $visitante3){
                                                                   if (($a->href=='/partidos/argentina/'.$this->dameNombreTorneoURL(strtolower($grupo->torneo->nombre),$fecha->numero,$year).'/'.$local3.'-'.$visitante3.'/')||($a->href=='/partidos/argentina/'.$this->dameNombreTorneoURL(strtolower($grupo->torneo->nombre),$fecha->numero,$year).'/'.$visitante3.'-'.$local3.'/')){
                                                                       $urlEncontrada=1;
                                                                       Log::channel('mi_log')->info('OJO!! encontro gol cabeza: ' . $a->href, []);
                                                                       $data3 = array(
                                                                           'partido_id' => $partido->id,
                                                                           'jugador_id' => $gol->jugador->id,
                                                                           'minuto' => $gol->minuto,

                                                                           'tipo' => 'Cabeza',
                                                                           'url' => $urlCabeza,
                                                                       );
                                                                       $jugadorGolArray[$gol->jugador->id][]=$data3;
                                                                   }
                                                               }
                                                           }

                                                            if(!$urlEncontrada){
                                                                Log::channel('mi_log')->info('no esta cabeza: ' . $a->href, []);
                                                            }



                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                                else{
                                    fputcsv($handle, [

                                        utf8_decode($grupo->torneo->nombre.' '.$grupo->torneo->year),
                                        utf8_decode($fecha->numero),
                                        utf8_decode($partido->equipol->nombre . ' VS ' . $partido->equipov->nombre),
                                        utf8_decode($gol->jugador->persona->nombre.' - '.$gol->jugador->persona->apellido),
                                        utf8_decode($gol->tipo.' - '.$gol->minuto),
                                        utf8_decode('No se econtró la URL de cabezas'),
                                        $urlCabeza
                                    ], "|");
                                    Log::channel('mi_log')->info('OJO!!! No se econtró la URL de cabezas' , []);
                                }
                                try {
                                    $urlLibres = 'http://www.futbol360.com.ar/detalles/matches-goals.php?item=player&id='.$id_jugador.'&id_team_for='.$this->dameIdEquipoURL($juegaEn).'&id_team_against='.$this->dameIdEquipoURL($juegaContra).'&id_season=0&search_category=free_shot';
                                    Log::channel('mi_log')->info('OJO!!! - '.$urlLibres, []);

                                    $htmlLibre = HtmlDomParser::file_get_html($urlLibres, false, null, 0);

                                    //Log::channel('mi_log')->info('OJO!! URL Libre: '.$htmlLibre,[]);




                                }
                                catch (Exception $ex) {
                                    $htmlLibre='';
                                }
                                if ($htmlLibre){
                                    foreach ($htmlLibre->find('div[id=matchesTable]') as $element) {
                                        foreach ($element->find('table[class=tableStandard]') as $table) {
                                            foreach ($table->find('tr') as $tr) {
                                                if (trim($tr->plaintext)!='No hay resultados') {
                                                    foreach ($tr->find('th') as $th) {
                                                        foreach ($th->find('a') as $a) {
                                                            $urlEncontrada=0;
                                                            foreach ($this->dameNombreEquipoURL3($strLocal) as $local3){
                                                                foreach ($this->dameNombreEquipoURL3($strVisitante) as $visitante3){
                                                                    if (($a->href=='/partidos/argentina/'.$this->dameNombreTorneoURL(strtolower($grupo->torneo->nombre),$fecha->numero,$year).'/'.$local3.'-'.$visitante3.'/')||($a->href=='/partidos/argentina/'.$this->dameNombreTorneoURL(strtolower($grupo->torneo->nombre),$fecha->numero,$year).'/'.$visitante3.'-'.$local3.'/')){
                                                                        $urlEncontrada=1;
                                                                        Log::channel('mi_log')->info('OJO!! encontro gol tiro libre: ' . $a->href, []);
                                                                        $data3 = array(
                                                                            'partido_id' => $partido->id,
                                                                            'jugador_id' => $gol->jugador->id,
                                                                            'minuto' => $gol->minuto,

                                                                            'tipo' => 'Tiro Libre',
                                                                            'url' => $urlLibres,
                                                                        );

                                                                        $jugadorGolArray[$gol->jugador->id][]=$data3;
                                                                    }
                                                                }
                                                            }

                                                            if(!$urlEncontrada){
                                                                Log::channel('mi_log')->info('no esta libres: ' . $a->href, []);
                                                            }

                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                                else{
                                    fputcsv($handle, [

                                        utf8_decode($grupo->torneo->nombre.' '.$grupo->torneo->year),
                                        utf8_decode($fecha->numero),
                                        utf8_decode($partido->equipol->nombre . ' VS ' . $partido->equipov->nombre),
                                        utf8_decode($gol->jugador->persona->nombre.' - '.$gol->jugador->persona->apellido),
                                        utf8_decode($gol->tipo.' - '.$gol->minuto),
                                        utf8_decode('No se econtró la URL de tiros libres'),
                                        $urlLibres
                                    ], "|");
                                    Log::channel('mi_log')->info('OJO!!! No se econtró la URL de tiros libres' , []);
                                }
                                try {
                                    $urlPenales = 'http://www.futbol360.com.ar/detalles/matches-goals.php?item=player&id='.$id_jugador.'&id_team_for='.$this->dameIdEquipoURL($juegaEn).'&id_team_against='.$this->dameIdEquipoURL($juegaContra).'&id_season=0&search_category=penal_converted';
                                    Log::channel('mi_log')->info('OJO!!! - '.$urlPenales, []);

                                    $htmlPenal = HtmlDomParser::file_get_html($urlPenales, false, null, 0);

                                    //Log::channel('mi_log')->info('OJO!! URL Penal: '.$htmlPenal,[]);




                                }
                                catch (Exception $ex) {
                                    $htmlPenal='';
                                }
                                if ($htmlPenal){
                                    foreach ($htmlPenal->find('div[id=matchesTable]') as $element) {
                                        foreach ($element->find('table[class=tableStandard]') as $table) {
                                            foreach ($table->find('tr') as $tr) {
                                                if (trim($tr->plaintext)!='No hay resultados') {
                                                    foreach ($tr->find('th') as $th) {
                                                        foreach ($th->find('a') as $a) {
                                                            $urlEncontrada=0;
                                                            foreach ($this->dameNombreEquipoURL3($strLocal) as $local3){
                                                                foreach ($this->dameNombreEquipoURL3($strVisitante) as $visitante3){
                                                                    if (($a->href=='/partidos/argentina/'.$this->dameNombreTorneoURL(strtolower($grupo->torneo->nombre),$fecha->numero,$year).'/'.$local3.'-'.$visitante3.'/')||($a->href=='/partidos/argentina/'.$this->dameNombreTorneoURL(strtolower($grupo->torneo->nombre),$fecha->numero,$year).'/'.$visitante3.'-'.$local3.'/')){
                                                                        $urlEncontrada=1;
                                                                        Log::channel('mi_log')->info('OJO!! encontro gol de penal: ' . $a->href, []);
                                                                        $data3 = array(
                                                                            'partido_id' => $partido->id,
                                                                            'jugador_id' => $gol->jugador->id,
                                                                            'minuto' => $gol->minuto,

                                                                            'tipo' => 'Penal',
                                                                            'url' => $urlPenales,
                                                                        );

                                                                        $jugadorGolArray[$gol->jugador->id][]=$data3;
                                                                    }
                                                                }
                                                            }

                                                            if(!$urlEncontrada){
                                                                Log::channel('mi_log')->info('no esta penal: ' . $a->href, []);
                                                            }


                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                                else{
                                    fputcsv($handle, [

                                        utf8_decode($grupo->torneo->nombre.' '.$grupo->torneo->year),
                                        utf8_decode($fecha->numero),
                                        utf8_decode($partido->equipol->nombre . ' VS ' . $partido->equipov->nombre),
                                        utf8_decode($gol->jugador->persona->nombre.' - '.$gol->jugador->persona->apellido),
                                        utf8_decode($gol->tipo.' - '.$gol->minuto),
                                        utf8_decode('No se econtró la URL de penales'),
                                        $urlPenales
                                    ], "|");
                                    Log::channel('mi_log')->info('OJO!!! No se econtró la URL de penales' , []);
                                }
                            }

                        }
                        else{
                            fputcsv($handle, [

                                utf8_decode($grupo->torneo->nombre.' '.$grupo->torneo->year),
                                utf8_decode($fecha->numero),
                                utf8_decode($partido->equipol->nombre . ' VS ' . $partido->equipov->nombre),
                                utf8_decode($gol->jugador->persona->nombre.' - '.$gol->jugador->persona->apellido),
                                utf8_decode($gol->tipo.' - '.$gol->minuto),
                                utf8_decode('No se econtró la URL del jugador'),
                                $urlJugador
                            ], "|");
                            Log::channel('mi_log')->info('OJO!!! No se econtró la URL del jugador' , []);
                        }
                    }
                }
                foreach ($jugadorGolArray as $key => $item){

                    $jugador=Jugador::findOrFail($key);
                    if (count($item)>1){
                        Log::channel('mi_log')->info('OJO!!! más de un gol de '.$key , []);
                        foreach ($item as $value){
                            fputcsv($handle, [

                                utf8_decode($grupo->torneo->nombre.' '.$grupo->torneo->year),
                                utf8_decode($fecha->numero),
                                utf8_decode($partido->equipol->nombre . ' VS ' . $partido->equipov->nombre),
                                utf8_decode($jugador->persona->nombre.' - '.$jugador->persona->apellido),
                                utf8_decode($value['tipo'].' - '.$value['minuto']),
                                utf8_decode('más de un gol'),
                                $value['url']
                            ], "|");
                            Log::channel('mi_log')->info(' => '.$value['tipo'].' - '.$value['minuto'] , []);
                        }

                    }
                    else{

                        Log::channel('mi_log')->info('OJO!!! un solo gol de: '.$key.' => '.$item[0]['tipo'].' - '.$item[0]['minuto'] , []);
                        $golesJugador = Gol::where('partido_id', '=', $item[0]['partido_id'])->where('jugador_id', '=', $key)->get();
                        if (count($golesJugador)==1) {


                                fputcsv($handle, [

                                    utf8_decode($grupo->torneo->nombre.' '.$grupo->torneo->year),
                                    utf8_decode($fecha->numero),
                                    utf8_decode($partido->equipol->nombre . ' VS ' . $partido->equipov->nombre),
                                    utf8_decode($jugador->persona->nombre.' - '.$jugador->persona->apellido),
                                    utf8_decode($item[0]['tipo'].' - '.$item[0]['minuto']),
                                    utf8_decode('Gol actualizado'),
                                    $item[0]['url']
                                ], "|");
                                $dataGol = array(
                                    'partido_id' => $item[0]['partido_id'],
                                    'jugador_id' => $key,
                                    'minuto' => $item[0]['minuto'],

                                    'tipo' => $item[0]['tipo']
                                );
                                try {
                                    $golesJugador[0]->update($dataGol);
                                } catch (QueryException $ex) {
                                    $error = $ex->getMessage();
                                    $ok = 0;
                                    continue;
                                }
                            }
                        else{
                            fputcsv($handle, [

                                utf8_decode($grupo->torneo->nombre.' '.$grupo->torneo->year),
                                utf8_decode($fecha->numero),
                                utf8_decode($partido->equipol->nombre . ' VS ' . $partido->equipov->nombre),
                                utf8_decode($jugador->persona->nombre.' - '.$jugador->persona->apellido),
                                utf8_decode($item[0]['tipo'].' - '.$item[0]['minuto']),
                                utf8_decode('Tiene goles de otro tipo'),
                                $item[0]['url']
                            ], "|");
                        }




                    }

                }
            }
        }

        fclose($handle);
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

    public function importgolesfecha_old(Request $request)
    {
        set_time_limit(0);
        //Log::channel('mi_log')->info('Entraaaaaa', []);

        $grupo_id = $request->get('grupoId');

        $grupo=Grupo::findOrFail($grupo_id);


        $fechas=Fecha::where('grupo_id','=',"$grupo_id")->orderBy('numero','ASC')->get();

        /*$fecha=Fecha::findOrFail($id);

        $grupo=Grupo::findOrFail($fecha->grupo->id);*/
        DB::beginTransaction();
        foreach ($fechas as $fecha){
            $id=$fecha->id;
            $arrYear = explode('/', $grupo->torneo->year);
            $years = str_replace('/', '-', $grupo->torneo->year);
            $year = (count($arrYear)>1)?$arrYear[1]:$arrYear[0];
            $partidos=Partido::where('fecha_id','=',"$id")->get();
            $nombreTorneo=$grupo->torneo->nombre;
            $ok=1;

            foreach ($partidos as $partido){
                $strLocal = $partido->equipol->nombre;
                $strVisitante = $partido->equipov->nombre;
                $golesTotales = $partido->golesl+$partido->golesv;
                $golesLocales = $partido->golesl;
                $golesVisitantes = $partido->golesv;
                Log::channel('mi_log')->info('Partido ' .$partido->equipol->nombre.' VS '.$partido->equipov->nombre, []);




                try {
                    //Log::channel('mi_log')->info('Partido ' .$partido->equipol->nombre.' VS '.$partido->equipov->nombre, []);

                    /*Log::channel('mi_log')->info('OJO!!! URL ' .'https://www.livefutbol.com/cronica/primera-division-'.$years.'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'/', []);*/
                    /*html2 = HtmlDomParser::file_get_html('https://www.livefutbol.com/cronica/primera-division-'.$years.'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'/', false, null, 0);*/
                    foreach ($this->dameNombreEquipoURL3($strLocal) as $local3){
                        foreach ($this->dameNombreEquipoURL3($strVisitante) as $visitante3){
                            Log::channel('mi_log')->info('OJO!!! URL ' .'http://www.futbol360.com.ar/partidos/argentina/torneo-'.strtolower($grupo->torneo->nombre).'-'.$year.'/'.intval($fecha->numero).'-fecha/'.$local3.'-'.$visitante3.'/inc/partido-'.$local3.'-'.$visitante3.'-'.date('d-m-Y', strtotime($partido->dia)).'.php.inc', []);

                            $htmlAux = HtmlDomParser::file_get_html('http://www.futbol360.com.ar/partidos/argentina/torneo-'.strtolower($grupo->torneo->nombre).'-'.$year.'/'.intval($fecha->numero).'-fecha/'.$local3.'-'.$visitante3.'/inc/partido-'.$local3.'-'.$visitante3.'-'.date('d-m-Y', strtotime($partido->dia)).'.php.inc', false, null, 0);
                            if ($htmlAux){
                                $html2=$htmlAux;
                            }
                        }
                    }


                    //Log::channel('mi_log')->info('OJO!! error: '.$html2,[]);
                    $linkArray=array();



                }
                catch (Exception $ex) {
                    $html2='';
                }
                $dtLocal ='';
                $dtVisitante ='';
                if ($html2){
                    $golesArray= array();
                    foreach ($html2->find('table[class=matchRecord]') as $element) {

                        foreach ($element->find('tr') as $tr) {
                            //Log::channel('mi_log')->info('OJO!! gol: '.$tr->plaintext,[]);

                            if (str_contains($tr, 'iconMatchPenalty.gif')) {
                                $arrTh = array();
                                foreach ($tr->find('th') as $th) {


                                    $arrTh[] = $th;

                                }
                                $arrTd = array();
                                $tdAnt = '';
                                foreach ($tr->find('td') as $td) {
                                    //$jugadorGol = $td->find('a')[0]->href;


                                    if (str_contains($td, 'iconMatchPenalty.gif')) {
                                        $jugadorArr = explode('/', $tdAnt->find('a')[0]->href);
                                        Log::channel('mi_log')->info('OJO!! penal: ' . $jugadorArr[count($jugadorArr) - 2], []);
                                        $jugadorGol = str_replace('-', ' ', $jugadorArr[count($jugadorArr) - 2]);
                                        $arrImg = array();
                                        foreach ($td->find('img') as $img) {
                                            $arrImg[] = $img->src;


                                        }
                                        $arrImgText = array();
                                        foreach ($td->find('img text') as $img) {
                                            $arrImgText[] = $img->plaintext;


                                        }
                                        for ($i = 0; $i < count($arrImg); $i++) {
                                            // Log::channel('mi_log')->info('OJO!! img: ' . $arrImg[$i], []);
                                            if (str_contains($arrImg[$i], 'iconMatchPenalty.gif')) {

                                                $minuto = str_replace('&#160', '', $arrImgText[$i]);
                                                if (str_contains($minuto, 'st')) {
                                                    $minuto = intval($minuto) + 45;
                                                } else {
                                                    $minuto = intval($minuto);
                                                }

                                                Log::channel('mi_log')->info('OJO!! minuto: ' . $minuto, []);
                                                $arrJugador = explode(' ', $jugadorGol);
                                                //$entrenadorV=Tecnico::where('nombre','like',"%$arrEntrenador[0]%")->where('apellido','like',"%$arrEntrenador[1]%")->first();
                                                $jugadors = Jugador::SELECT('jugadors.*')->Join('personas', 'personas.id', '=', 'jugadors.persona_id')->where('nombre', 'like', "%$arrJugador[1]%")->where('apellido', 'like', "%$arrJugador[0]%")->get();
                                                $alineacion='';

                                                foreach ($jugadors as $jug){

                                                    $alineacion=Alineacion::where('partido_id','=',"$partido->id")->where('jugador_id','=',$jug->id)->first();
                                                    if (!empty($alineacion)) {
                                                        break;
                                                    }
                                                }
                                                if (!empty($alineacion)) {
                                                    $data3 = array(
                                                        'partido_id' => $partido->id,
                                                        'jugador_id' => $alineacion->jugador->id,
                                                        'minuto' => $minuto,

                                                        'tipo' => 'Penal'
                                                    );
                                                    $golesArray[] = $data3;
                                                }
                                                else{
                                                    Log::channel('mi_log')->info('OJO!! no se encontro a : ' . $jugadorGol, []);
                                                }
                                            }
                                        }
                                    }
                                    $tdAnt = $td;
                                    $arrTd[] = $td;
                                    //$lineaGol = $td->plaintext;
                                }
                            }

                        }


                    }

                    foreach ($golesArray as $goles){
                        $gol = Gol::where('partido_id', '=', $goles['partido_id'])->where('jugador_id', '=', $goles['jugador_id'])->where('minuto', '=', $goles['minuto'])->first();
                        try {
                            if (!empty($gol)) {

                                $gol->update($goles);
                            } else {
                                Log::channel('mi_log')->info('OJO!! no estaba: ' . $goles['partido_id'].' - '.$goles['jugador_id'].' - '.$goles['minuto'], []);
                                $gol = Gol::create($goles);
                            }


                        } catch (QueryException $ex) {
                            $error = $ex->getMessage();
                            $ok = 0;
                            continue;
                        }
                    }

                }
                else{
                    Log::channel('mi_log')->info('OJO!!! No se econtró la URL2 ' , []);
                    /*$error = 'No se econtró la URL2 del partido: '.$partido->equipol->nombre.' VS '.$partido->equipov->nombre;
                    $ok=0;
                    continue;*/
                }



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


    public function controlarbitrosfecha(Request $request)
    {
        set_time_limit(0);
        //Log::channel('mi_log')->info('Entraaaaaa', []);

        $grupo_id = $request->get('grupoId');

        $grupo=Grupo::findOrFail($grupo_id);


        $fechas=Fecha::where('grupo_id','=',"$grupo_id")->orderBy('numero','ASC')->get();

        // these are the headers for the csv file.
        $headers = array(
            'Content-Type' => 'application/vnd.ms-excel; charset=utf-8',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Content-Disposition' => 'attachment; filename='.str_replace(' ','_',$grupo->torneo->nombre).'_'.str_replace('/','_',$grupo->torneo->year).'_arbitros.csv',
            'Expires' => '0',
            'Pragma' => 'public',
        );


        //I am storing the csv file in public >> files folder. So that why I am creating files folder
        if (!File::exists(public_path()."/files")) {
            File::makeDirectory(public_path() . "/files");
        }

        //creating the download file
        $filename =  public_path('files/'.str_replace(' ','_',$grupo->torneo->nombre).'_'.str_replace('/','_',$grupo->torneo->year).'_arbitros.csv');
        $handle = fopen($filename, 'w');

        //adding the first row
        fputcsv($handle, [
            'Torneo', 'fecha','Partido','Cant', 'Arbitro', 'Linea 1', 'Linea 2','URL'
        ], "|");




        /*$fecha=Fecha::findOrFail($id);

        $grupo=Grupo::findOrFail($fecha->grupo->id);*/
        DB::beginTransaction();
        foreach ($fechas as $fecha) {
            $id = $fecha->id;
            $arrYear = explode('/', $grupo->torneo->year);
            $years = str_replace('/', '-', $grupo->torneo->year);
            $year = (count($arrYear) > 1) ? $arrYear[1] : $arrYear[0];
            $partidos = Partido::where('fecha_id', '=', "$id")->get();
            $nombreTorneo = $grupo->torneo->nombre;
            $ok = 1;

            foreach ($partidos as $partido) {
                $strLocal = $partido->equipol->nombre;
                $strVisitante = $partido->equipov->nombre;
                $golesTotales = $partido->golesl + $partido->golesv;
                $golesLocales = $partido->golesl;
                $golesVisitantes = $partido->golesv;
                Log::channel('mi_log')->info('Partido ' . $partido->equipol->nombre . ' VS ' . $partido->equipov->nombre, []);
                $arbitros = PartidoArbitro::where('partido_id', '=', "$partido->id")->get();
                $url='';


                if(count($arbitros)!=3){


                    try {
                        $url = 'https://www.livefutbol.com/cronica/primera-division-'.$years.'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'/';
                        Log::channel('mi_log')->info('OJO!!! URL ' .$url, []);
                        $html2 = HtmlDomParser::file_get_html($url, false, null, 0);
                        /*Log::channel('mi_log')->info('OJO!!! URL ' .'https://arg.worldfootball.net/cronica/copa-de-la-superliga-'.$years.'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'/', []);

                        $html2 = HtmlDomParser::file_get_html('https://arg.worldfootball.net/cronica/copa-de-la-superliga-'.$years.'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'/', false, null, 0);*/

                        if (!$html2){
                            $url = 'https://www.livefutbol.com/cronica/primera-division-'.$years.'-'.$this->dameNombreEquipoURL2ALT($strLocal).'-'.$this->dameNombreEquipoURL2ALT($strVisitante).'/';
                            Log::channel('mi_log')->info('OJO!!! URL ' .$url, []);
                            $html2 = HtmlDomParser::file_get_html($url, false, null, 0);
                        }
                        if (!$html2){
                            $url = 'https://www.livefutbol.com/cronica/primera-division-'.$years.'-'.$this->dameNombreEquipoURL2ALT($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'/';
                            Log::channel('mi_log')->info('OJO!!! URL ' .$url, []);
                            $html2 = HtmlDomParser::file_get_html($url, false, null, 0);
                        }
                        if (!$html2){
                            $url = 'https://www.livefutbol.com/cronica/primera-division-'.$years.'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2ALT($strVisitante).'/';
                            Log::channel('mi_log')->info('OJO!!! URL ' .$url, []);
                            $html2 = HtmlDomParser::file_get_html($url, false, null, 0);
                        }
                        if (!$html2){
                            $url ='https://www.livefutbol.com/cronica/primera-division-'.$years.'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'_2/';
                            Log::channel('mi_log')->info('OJO!!! URL ' .$url, []);
                            $html2 = HtmlDomParser::file_get_html($url, false, null, 0);
                        }
                        $linkArray=array();
                        $entrenadoresArray = array();
                        $nombreArbitro ='';
                        if (!$html2) {
                            $url ='https://www.livefutbol.com/cronica/primera-division-'.$years.'-'.strtr($nombreTorneo, " ", "-").'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'/';
                            Log::channel('mi_log')->info('OJO!!! URL ' .$url, []);

                            $html2 = HtmlDomParser::file_get_html($url, false, null, 0);


                        }
                        if (!$html2) {
                            $url ='https://www.livefutbol.com/cronica/primera-division-'.$years.'-'.strtr($nombreTorneo, " ", "-").'-'.$this->dameNombreEquipoURL2ALT($strLocal).'-'.$this->dameNombreEquipoURL2ALT($strVisitante).'/';
                            Log::channel('mi_log')->info('OJO!!! URL ' .$url, []);

                            $html2 = HtmlDomParser::file_get_html($url, false, null, 0);


                        }
                        if (!$html2) {
                            $url ='https://www.livefutbol.com/cronica/primera-division-'.$years.'-'.strtr($nombreTorneo, " ", "-").'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2ALT($strVisitante).'/';
                            Log::channel('mi_log')->info('OJO!!! URL ' .$url, []);

                            $html2 = HtmlDomParser::file_get_html($url, false, null, 0);


                        }
                        if (!$html2) {
                            $url ='https://www.livefutbol.com/cronica/primera-division-'.$years.'-'.strtr($nombreTorneo, " ", "-").'-'.$this->dameNombreEquipoURL2ALT($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'/';
                            Log::channel('mi_log')->info('OJO!!! URL ' .$url, []);

                            $html2 = HtmlDomParser::file_get_html($url, false, null, 0);


                        }
                        if (!$html2) {
                            $url ='https://www.livefutbol.com/cronica/copa-de-la-superliga-'.$years.'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'/';
                            Log::channel('mi_log')->info('OJO!!! URL ' .$url, []);

                            $html2 = HtmlDomParser::file_get_html($url, false, null, 0);
                        }
                        if (!$html2) {
                            $url ='https://www.livefutbol.com/cronica/copa-de-la-superliga-'.$years.'-'.$this->dameNombreEquipoURL2ALT($strLocal).'-'.$this->dameNombreEquipoURL2ALT($strVisitante).'/';
                            Log::channel('mi_log')->info('OJO!!! URL ' .$url, []);

                            $html2 = HtmlDomParser::file_get_html($url, false, null, 0);
                        }
                        if (!$html2) {
                            $url ='https://www.livefutbol.com/cronica/copa-de-la-superliga-'.$years.'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2ALT($strVisitante).'/';
                            Log::channel('mi_log')->info('OJO!!! URL ' .$url, []);

                            $html2 = HtmlDomParser::file_get_html($url, false, null, 0);
                        }
                        if (!$html2) {
                            $url ='https://www.livefutbol.com/cronica/copa-de-la-superliga-'.$years.'-'.$this->dameNombreEquipoURL2ALT($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'/';
                            Log::channel('mi_log')->info('OJO!!! URL ' .$url, []);

                            $html2 = HtmlDomParser::file_get_html($url, false, null, 0);
                        }

                    }
                    catch (Exception $ex) {
                        $html2='';
                    }
                    if ($html2){
                        if(count($arbitros)>3){
                            Log::channel('mi_log')->info('OJO!!! mas de 3 jueces', []);
                            try {

                                PartidoArbitro::where('partido_id',"$partido->id")->delete();
                            }
                            catch(QueryException $ex){
                                $error = $ex->getMessage();
                                $ok=0;

                            }
                        }
                        $asistente=0;
                        $asistente1='';
                        $asistente2='';
                        $arbitro='';
                        $arbitro1='';
                        $arbitro2='';
                        foreach ($html2->find('table[class=standard_tabelle]') as $element) {
                            foreach ($element->find('td[class="dunkel"]') as $element2) {

                                foreach ($element2->find('a') as $link) {
                                    $linkArray = explode(' ', $link->title);

                                    if (($linkArray[0]) == 'Árbitro') {
                                        if (($linkArray[1]) == 'asistente') {
                                            $nombreAsistente = '';
                                            for ($i = 2; $i < count($linkArray); $i++) {
                                                $nombreAsistente .= ($linkArray[$i]) . ' ';
                                            }
                                            if ($asistente == 0) {
                                                $asistente1 = $nombreAsistente;
                                                Log::channel('mi_log')->info('Asistente 1: ' . $nombreAsistente, []);
                                                $asistente++;
                                            } else {
                                                $asistente2 = $nombreAsistente;
                                                Log::channel('mi_log')->info('Asistente 2: ' . $nombreAsistente, []);
                                                $asistente++;
                                            }

                                        } else {
                                            $nombreArbitro = '';
                                            for ($i = 1; $i < count($linkArray); $i++) {
                                                $nombreArbitro .= ($linkArray[$i]) . ' ';
                                            }

                                            Log::channel('mi_log')->info('Arbitro: ' . $nombreArbitro, []);

                                        }
                                    }


                                }

                            }
                            if ($nombreArbitro){
                                $arrArbitro = explode(' ', $nombreArbitro);
                                $arbitro=0;
                                if(count($arrArbitro)>1) {
                                    //$arbitro = Arbitro::where('nombre', 'like', "%$arrArbitro[0]%")->where('apellido', 'like', "%$arrArbitro[1]%")->first();
                                    $arbitro=Arbitro::SELECT('arbitros.*')->Join('personas','personas.id','=','arbitros.persona_id')->where('apellido', 'like', "%$arrArbitro[1]%")->first();
                                }

                                if (!$arbitro){
                                    Log::channel('mi_log')->info('OJO!! Arbitro NO encontrado: '.$nombreArbitro.' '.$partido->equipol->nombre.' VS '.$partido->equipov->nombre,[]);
                                }
                                else{
                                    $data3=array(
                                        'partido_id'=>$partido->id,
                                        'arbitro_id'=>$arbitro->id,
                                        'tipo'=>'Principal'
                                    );
                                    $partido_arbitro=PartidoArbitro::where('partido_id','=',"$partido->id")->where('arbitro_id','=',"$arbitro->id")->first();
                                    try {
                                        if (!empty($partido_arbitro)){
                                            //Log::channel('mi_log')->info('OJO!! lo encuentra',[]);
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
                            }
                            if($asistente1){
                                $arrArbitroA1 = explode(' ', $asistente1);
                                $arbitro1=0;
                                if(count($arrArbitroA1)>1) {
                                    //$arbitro1 = Arbitro::where('nombre', 'like', "%$arrArbitroA1[0]%")->where('apellido', 'like', "%$arrArbitroA1[1]%")->first();
                                    $arbitro1=Arbitro::SELECT('arbitros.*')->Join('personas','personas.id','=','arbitros.persona_id')->where('nombre', 'like', "%$arrArbitroA1[0]%")->where('apellido', 'like', "%$arrArbitroA1[1]%")->first();
                                }

                                if (!$arbitro1){
                                    Log::channel('mi_log')->info('OJO!! Asistente NO encontrado: '.$asistente1.' '.$partido->equipol->nombre.' VS '.$partido->equipov->nombre,[]);
                                }
                                else{
                                    $data3=array(
                                        'partido_id'=>$partido->id,
                                        'arbitro_id'=>$arbitro1->id,
                                        'tipo'=>'Linea 1'
                                    );
                                    $partido_arbitro=PartidoArbitro::where('partido_id','=',"$partido->id")->where('arbitro_id','=',"$arbitro1->id")->first();
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
                            }
                            if($asistente2){
                                $arrArbitroA2 = explode(' ', $asistente2);
                                $arbitro2=0;
                                if(count($arrArbitroA2)>1) {
                                    //$arbitro2 = Arbitro::where('nombre', 'like', "%$arrArbitroA2[0]%")->where('apellido', 'like', "%$arrArbitroA2[1]%")->first();
                                    $arbitro2=Arbitro::SELECT('arbitros.*')->Join('personas','personas.id','=','arbitros.persona_id')->where('nombre', 'like', "%$arrArbitroA2[0]%")->where('apellido', 'like', "%$arrArbitroA2[1]%")->first();
                                }

                                if (!$arbitro2){
                                    Log::channel('mi_log')->info('OJO!! Asistente NO encontrado: '.$asistente2.' '.$partido->equipol->nombre.' VS '.$partido->equipov->nombre,[]);
                                }
                                else{
                                    $data3=array(
                                        'partido_id'=>$partido->id,
                                        'arbitro_id'=>$arbitro2->id,
                                        'tipo'=>'Linea 2'
                                    );
                                    $partido_arbitro=PartidoArbitro::where('partido_id','=',"$partido->id")->where('arbitro_id','=',"$arbitro2->id")->first();
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
                            }

                        }
                    }
                    fputcsv($handle, [

                        utf8_decode($grupo->torneo->nombre . ' ' . $grupo->torneo->year),
                        utf8_decode($fecha->numero),
                        utf8_decode($partido->equipol->nombre . ' VS ' . $partido->equipov->nombre),
                        count($arbitros),
                        utf8_decode( $nombreArbitro),
                        utf8_decode($asistente1),
                        utf8_decode($asistente2),
                        $url
                    ], "|");
                    //Log::channel('mi_log')->info('OJO!!! No se econtró la URL de cabezas', []);
                }

            }
        }

        fclose($handle);
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

        if($request->query('torneoId')) {
            $torneo_id = $request->query('torneoId');
            $torneo = Torneo::findOrFail($torneo_id);
        }
        else{
            $torneo=Torneo::orderBy('year','DESC')->first();
            $torneo_id = $torneo->id;
        }

        $request->session()->put('nombreTorneo', $torneo->nombre.' '.$torneo->year);
        $request->session()->put('codigoTorneo', $torneo_id);

        $grupos = Grupo::where('torneo_id', '=',$torneo_id)->get();

        $arrgrupos='';
        foreach ($grupos as $grupo){
            $arrgrupos .=$grupo->id.',';
        }

        $fechaNumero= $request->query('fechaNumero');

        if (empty($fechaNumero)){
            //$fechaNumero = '01';
            $ultimaFecha=Fecha::wherein('grupo_id',explode(',', $arrgrupos))->orderBy('numero','desc')->get();
            $fechaNumero = $ultimaFecha[0]->numero;
        }

        $fechas=Fecha::wherein('grupo_id',explode(',', $arrgrupos))->where('numero','=',$fechaNumero)->get();
        $arrfechas='';
        foreach ($fechas as $fecha){
            $arrfechas .=$fecha->id.',';
        }
        $partidos=Partido::wherein('fecha_id',explode(',', $arrfechas))->orderBy('dia','ASC')->get();

        $fechas=Fecha::select('numero')->distinct()->wherein('grupo_id',explode(',', $arrgrupos))->orderBy('numero','ASC')->get();

        //dd($fechas);

        //print_r($fechas);

        return view('fechas.ver', compact('fechas','torneo','partidos','fecha'));
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



        $tecnicosL = PartidoTecnico::where('partido_id','=',$partido_id)->where('equipo_id','=',$partido->equipol->id)->get();
        $tecnicosV = PartidoTecnico::where('partido_id','=',$partido_id)->where('equipo_id','=',$partido->equipov->id)->get();


        $arbitros=PartidoArbitro::where('partido_id','=',"$partido_id")->orderBy('tipo','ASC')->get();

        //dd($partido->fecha->grupo->torneo->nombre);
        return view('fechas.detalle', compact('goles','partido', 'tarjetas','cambios','titularesL','suplentesL','titularesV','suplentesV','tecnicosL','tecnicosV','arbitros'));
        //
    }

    public function importarPartido(Request $request)
    {
        $partido_id= $request->query('partidoId');
        $partido=Partido::findOrFail($partido_id);

        //
        return view('fechas.importarPartido', compact('partido'));
    }

    public function importarPartidoProcess(Request $request)
    {
        set_time_limit(0);
        //Log::channel('mi_log')->info('Entraaaaaa', []);
        $partido_id = $request->get('partido_id');
        $url = $request->get('url');
        $url2 = $request->get('url2');
        $partido=Partido::findOrFail($partido_id);

        $fecha = Fecha::findOrFail($partido->fecha->id);

        $grupo=Grupo::findOrFail($fecha->grupo->id);


        $ok=1;
        DB::beginTransaction();

            $strLocal = $partido->equipol->nombre;
            $strVisitante = $partido->equipov->nombre;
            $golesTotales = $partido->golesl+$partido->golesv;
            $html='';
            try {
                if ($url){
                    Log::channel('mi_log')->info('Partido ' .$partido->equipol->nombre.' VS '.$partido->equipov->nombre, []);
                    Log::channel('mi_log')->info('URL ' .$url, []);
                    $html = HtmlDomParser::file_get_html($url, false, null, 0);
                }




            }
            catch (Exception $ex) {
                $html='';
            }

            if ($html){


                $equipos = array();

                //Log::channel('mi_log')->info('Elemento ' . $html,[]);
                $i = 1;
                $goles=0;
                $j=0;
                foreach ($html->find('div[class=team team1]') as $element) {
                    $equipo = $element->find('h3[class=nteam nteam1]');
                    //Log::channel('mi_log')->info('Equipo ' . $equipo[0]->plaintext, []);
                    $jugadores = array();

                    $jugadores = array();
                    foreach ($element->find('ul[class=aligns-list]') as $element2) {
                        $j++;
                        foreach ($element2->find('li') as $li) {
                            $dorsal = $li->find('small[class=align-dorsal]');
                            $jugador = $li->find('h5[class=align-player]');
                            $id =  explode('-',$li->find('a')[0]->href);
                            //Log::channel('mi_log')->info('Jugador ' . $j.' -> '.$jugador[0]->plaintext, []);

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
                    //Log::channel('mi_log')->info('Equipo ' . $equipo[0]->plaintext, []);
                    $jugadores = array();
                    foreach ($element->find('ul[class=aligns-list]') as $element2) {
                        $j++;
                        foreach ($element2->find('li') as $li) {
                            $dorsal = $li->find('small[class=align-dorsal]');
                            $jugador = $li->find('h5[class=align-player]');
                            $id =  explode('-',$li->find('a')[0]->href);
                            //Log::channel('mi_log')->info('ID ' . $id[2], []);
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
                //Log::channel('mi_log')->info('Partido ' . print_r($equipos,true), []);
                foreach ($html->find('div[class=event-content]') as $element) {

                    $golL = $element->find('span[class=left event_1]');
                    if ($golL){
                        if($golL[0]->find('a')){
                            $id =  explode('-',$golL[0]->find('a')[0]->href);

                            $minL = explode('</b>',$element->find('span[class=left minutos minutosizq]')[0]);


                            $equipos[1]['jugadores'][$id[count($id) - 1]]['incidencias'][] = array('Gol', explode('\'', $minL[1])[0]);
                        }
                        else{
                            Log::channel('mi_log')->info('OJO!!! falta el jugador en el gol local de ' . $partido->equipol->nombre.' VS '.$partido->equipov->nombre, []);
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
                            Log::channel('mi_log')->info('OJO!!! falta el jugador en el penal local de ' . $partido->equipol->nombre.' VS '.$partido->equipov->nombre, []);
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
                            Log::channel('mi_log')->info('OJO!!! falta el jugador en la amarilla local de ' . $partido->equipol->nombre.' VS '.$partido->equipov->nombre, []);
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
                            Log::channel('mi_log')->info('OJO!!! falta el jugador en el gol visitante de ' . $partido->equipol->nombre.' VS '.$partido->equipov->nombre, []);
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
                            Log::channel('mi_log')->info('OJO!!! falta el jugador en la entrada local de ' . $partido->equipol->nombre.' VS '.$partido->equipov->nombre, []);
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
                            Log::channel('mi_log')->info('OJO!!! falta el jugador en la entrada local de ' . $partido->equipol->nombre.' VS '.$partido->equipov->nombre, []);
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
                            Log::channel('mi_log')->info('OJO!!! falta el jugador en la amarilla visitante de ' . $partido->equipol->nombre.' VS '.$partido->equipov->nombre, []);
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
                            Log::channel('mi_log')->info('OJO!!! falta el jugador en la salida visitante de ' . $partido->equipol->nombre.' VS '.$partido->equipov->nombre, []);
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
                            Log::channel('mi_log')->info('OJO!!! falta el jugador en la entrada visitante de ' . $partido->equipol->nombre.' VS '.$partido->equipov->nombre, []);
                        }



                    }



                }
                if ($goles!=$golesTotales){
                    Log::channel('mi_log')->info('OJO!!! No coincide la cantidad de goles en: '.$partido->equipol->nombre.' VS '.$partido->equipov->nombre.' -> '.$goles.' - '.$golesTotales,[]);
                }

                foreach ($equipos as $eq) {
                    //Log::channel('mi_log')->info('Equipo  ' . $equipo['equipo'], []);
                    $strEquipo=trim($eq['equipo']);
                    $equipo=Equipo::where('nombre','like',"%$strEquipo%")->first();
                    if (!empty($equipo)){
                        foreach ($eq['jugadores'] as $jugador) {
                            $grupos = Grupo::where('torneo_id', '=',$grupo->torneo->id)->get();
                            $arrgrupos='';
                            foreach ($grupos as $grupo){
                                $arrgrupos .=$grupo->id.',';
                            }

                            $plantillas = Plantilla::wherein('grupo_id',explode(',', $arrgrupos))->where('equipo_id','=',$equipo->id)->get();

                            $arrplantillas='';
                            foreach ($plantillas as $plantilla){
                                $arrplantillas .=$plantilla->id.',';
                            }

                            if (!empty($plantillas)){
                                if(!empty($jugador['dorsal'])){
                                $plantillaJugador = PlantillaJugador::wherein('plantilla_id',explode(',', $arrplantillas))->distinct()->where('dorsal','=',$jugador['dorsal'])->first();
                                }
                                else{
                                    $plantillaJugador='';
                                    Log::channel('mi_log')->info('OJO!!! con el jugador no está en la plantilla del equipo ' . $strEquipo, []);
                                }
                            }
                            else{
                                $plantillaJugador='';
                                Log::channel('mi_log')->info('OJO!!! No hay plantilla del equipo ' . $strEquipo, []);
                            }
                            if (!empty($plantillaJugador)) {
                                $arrApellido = explode(' ', $jugador['nombre']);
                                $mismoDorsal = 0;
                                foreach ($arrApellido as $apellido) {
                                    $consultarJugador = Jugador::Join('personas','personas.id','=','jugadors.persona_id')->where('jugadors.id', '=', $plantillaJugador->jugador->id)->where('apellido', 'LIKE', "%$apellido%")->first();
                                    if (!empty($consultarJugador)) {
                                        $mismoDorsal = 1;
                                        continue;
                                    }
                                }
                                if (!$mismoDorsal) {
                                    Log::channel('mi_log')->info('OJO!!! con jugador: ' . $jugador['dorsal'] . ' ' . $jugador['nombre'] . ' del equipo ' . $strEquipo, []);
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
                                Log::channel('mi_log')->info('OJO!!! NO se encontró al jugador: ' . $jugadorMostrar.' del equipo '.$strEquipo,[]);

                                //Log::channel('mi_log')->info(print_r($jugador), []);
                                $arrApellido = explode(' ', $jugador['nombre']);
                                $mismoDorsal = 0;

                                foreach ($arrApellido as $apellido) {
                                    $consultarJugador = Jugador::Join('personas','personas.id','=','jugadors.persona_id')->Join('plantilla_jugadors','jugadors.id','=','plantilla_jugadors.jugador_id')->wherein('plantilla_jugadors.plantilla_id',explode(',', $arrplantillas))->where('apellido', 'LIKE', "%$apellido%")->first();
                                    //Log::channel('mi_log')->info(json_encode($consultarJugador), []);
                                    if (!empty($consultarJugador)) {
                                        $mismoDorsal = 1;
                                        //Log::channel('mi_log')->info($consultarJugador['tipoJugador'], []);
                                        break;
                                    }
                                }
                                if ($mismoDorsal) {
                                    $jugador_id = $consultarJugador['jugador_id'];
                                    switch ($consultarJugador['tipoJugador']) {
                                        case 'Arquero':
                                            $orden = 0;
                                            break;
                                        case 'Defensor':
                                            $orden = 1;
                                            break;
                                        case 'Medio':
                                            $orden = 2;
                                            break;
                                        case 'Delantero':
                                            $orden = 3;
                                            break;

                                    }
                                    $alineaciondata = array(
                                        'partido_id' => $partido->id,
                                        'jugador_id' => $jugador_id,
                                        'equipo_id' => $equipo->id,
                                        'dorsal' => $consultarJugador['dorsal'],
                                        'tipo' => $jugador['tipo'],
                                        'orden' => $orden
                                    );
                                    $alineacion = Alineacion::where('partido_id', '=', $partido->id)->where('jugador_id', '=', $jugador_id)->first();

                                    try {
                                        if (!empty($alineacion)) {

                                            $alineacion->update($alineaciondata);
                                        } else {
                                            $alineacion = Alineacion::create($alineaciondata);
                                        }
                                        Log::channel('mi_log')->info('OJO!! verificar que sea correcto: '.$consultarJugador['apellido'].', '.$consultarJugador['nombre'].' dorsal '.$consultarJugador['dorsal'], []);

                                    } catch (QueryException $ex) {
                                        $error = $ex->getMessage();
                                        $ok = 0;
                                        continue;
                                    }
                                }
                            }
                            foreach ($jugador['incidencias'] as $incidencia) {

                                if (!empty($incidencia)) {
                                    //Log::channel('mi_log')->info('Incidencia: ' . trim($incidencia[0]) . ' MIN: ' . intval(trim($incidencia[1])), []);

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
                        Log::channel('mi_log')->info('OJO!!! NO se encontró al equipo: ' . $strEquipo,[]);
                    }
                }




            }
            /*else{
                $error = 'No se econtró la URL del partido: '.$partido->equipol->nombre.' VS '.$partido->equipov->nombre;
                $ok=0;
                //continue;
            }*/
            $html2='';
            try {
                if ($url2){
                    Log::channel('mi_log')->info('Partido ' .$partido->equipol->nombre.' VS '.$partido->equipov->nombre, []);

                    $html2 = HtmlDomParser::file_get_html($url2, false, null, 0);
                }


            }
            catch (Exception $ex) {
                $html2='';
            }
            if ($html2) {
                $goles=0;
                $golesL=0;
                $golesV=0;

                $tabla = 0;
                $equipos = array();
                $tablaIncidencias = 0;
                foreach ($html2->find('table[class=standard_tabelle]') as $element) {




                    if ($tabla==1){
                        $golesArray = array();


                        foreach ($element->find('td') as $td) {
                            $jugadorGol='';
                            $lineaGol='';
                            $minutoGol='';
                            $incidenciaGol='';
                            if($td->find('a')){
                                $jugadorGol = $td->find('a')[0]->title;
                                Log::channel('mi_log')->info('OJO!! gol: '.$jugadorGol,[]);
                                $lineaGol = $td->plaintext;
                                if (str_contains($lineaGol, $jugadorGol)) {
                                    //$minutoGol = (int) filter_var($lineaGol, FILTER_SANITIZE_NUMBER_INT);
                                    //Log::channel('mi_log')->info('OJO!! gol: '.$lineaGol,[]);
                                    $arrayGol= explode(".",$lineaGol);

                                    $minutoGol = (int) filter_var($arrayGol[0], FILTER_SANITIZE_NUMBER_INT);
                                    if (count($arrayGol)>1){
                                        $adiccion = (int) filter_var($arrayGol[1], FILTER_SANITIZE_NUMBER_INT);
                                        if ($adiccion>0){
                                            Log::channel('mi_log')->info('OJO!! gol addicion: ');
                                            $minutoGol = $minutoGol + $adiccion;
                                        }

                                    }
                                    //Log::channel('mi_log')->info('OJO!! min: '.$minutoGol,[]);
                                }

                                $incidenciaArray = explode('/', $lineaGol);
                                if (count($incidenciaArray)>1){
                                    $incidenciaGol = $incidenciaArray[1];
                                    //Log::channel('mi_log')->info('OJO!! incidencia: '.$incidenciaGol,[]);
                                }
                                $goles++;
                                $golesArray[]=$jugadorGol.'-'.$minutoGol.'-'.$incidenciaGol;
                            }


                        }
                    }
                    if ($tabla==2){

                        $jugadores = array();
                        //Log::channel('mi_log')->info('OJO!! locales:',[]);
                        $suplentes=0;
                        foreach ($element->find('tr') as $tr) {
                            $dorsalTitularL = '';
                            $jugadorTitularL = '';
                            $saleTitularL = '';
                            $amarillaTitularL = '';
                            $dobleamarillaTitularL = '';
                            $rojaTitularL = '';
                            $mintutoTarjetaTitularL = '';
                            $dorsalSuplenteL = '';
                            $jugadorSuplenteL = '';
                            $entraSuplenteL = '';
                            $saleSuplenteL = '';
                            $amarillaSuplenteL = '';
                            $dobleamarillaSuplenteL = '';
                            $rojaSuplenteL = '';
                            $mintutoTarjetaSuplenteL = '';
                            foreach ($tr->find('td') as $td) {
                                Log::channel('mi_log')->info('OJO!! linea: ' . $td->plaintext, []);
                                if (trim($td->plaintext) == 'Incidencias') {
                                    $tablaIncidencias = 1;
                                }
                                if (trim($td->plaintext) == 'Tanda de penaltis') {
                                    $tablaIncidencias = 1;
                                }
                                if (!$tablaIncidencias) {
                                    if (trim($td->plaintext) == 'Banquillo') {
                                        $suplentes = 1;

                                    }
                                    if ($td->find('span[class=kleine_schrift]')) {
                                        if ($td->find('span[style=font-weight: bold; color: #646464]')) {
                                            if ($suplentes) {
                                                $dorsalSuplenteL = $td->find('span[class=kleine_schrift]')[0]->plaintext;
                                                //Log::channel('mi_log')->info('OJO!! dorsal suplente: ' . $dorsalSuplenteL, []);
                                            } else {
                                                $dorsalTitularL = $td->find('span[class=kleine_schrift]')[0]->plaintext;
                                                //Log::channel('mi_log')->info('OJO!! dorsal titular: ' . $dorsalTitularL, []);
                                            }

                                        } elseif ($td->find('span[class=rottext]')) {
                                            if ($suplentes) {
                                                //$saleSuplenteL = (int)filter_var($td->find('span[class=rottext]')[0]->plaintext, FILTER_SANITIZE_NUMBER_INT);
                                                $arraySale= explode("'",$td->find('span[class=rottext]')[0]->plaintext);
                                                $saleSuplenteV = (int) $arraySale[0];
                                                if (count($arraySale)>1){
                                                    $adiccion = (int) $arraySale[1];
                                                    if ($adiccion>0){
                                                        Log::channel('mi_log')->info('OJO!! cambio addicion: ');
                                                        $saleSuplenteV = $saleSuplenteV + $adiccion;
                                                    }

                                                }
                                                //Log::channel('mi_log')->info('OJO!! sale suplente: ' . $saleSuplenteL, []);
                                            } else {
                                                //$saleTitularL = (int)filter_var($td->find('span[class=rottext]')[0]->plaintext, FILTER_SANITIZE_NUMBER_INT);
                                                $arraySale= explode("'",$td->find('span[class=rottext]')[0]->plaintext);
                                                $saleTitularL = (int) $arraySale[0];
                                                if (count($arraySale)>1){
                                                    $adiccion = (int) $arraySale[1];
                                                    if ($adiccion>0) {
                                                        Log::channel('mi_log')->info('OJO!! cambio addicion: ');
                                                        $saleTitularL = $saleTitularL + $adiccion;
                                                    }
                                                }
                                                //Log::channel('mi_log')->info('OJO!! sale titular: ' . $saleTitularL, []);
                                            }

                                        } elseif ($td->find('span[class=gruentext]')) {
                                            if ($suplentes) {
                                                //$entraSuplenteL = (int)filter_var($td->find('span[class=gruentext]')[0]->plaintext, FILTER_SANITIZE_NUMBER_INT);

                                                $arrayEntra= explode("'",$td->find('span[class=gruentext]')[0]->plaintext);
                                                $entraSuplenteL = (int) $arrayEntra[0];
                                                if (count($arrayEntra)>1){
                                                    $adiccion = (int) $arrayEntra[1];
                                                    if ($adiccion>0) {
                                                        Log::channel('mi_log')->info('OJO!! cambio addicion: ');
                                                        $entraSuplenteL = $entraSuplenteL + $adiccion;
                                                    }
                                                }
                                                //Log::channel('mi_log')->info('OJO!! entra suplente: ' . $entraSuplenteL, []);
                                            }


                                        } else {
                                            if ($td->find('span[class=kleine_schrift]')[0]->title != '') {
                                                if ($suplentes) {
                                                    //$mintutoTarjetaSuplenteL = $td->find('span[class=kleine_schrift]')[0]->plaintext;
                                                    //Log::channel('mi_log')->info('OJO!! tarjeta: ' . $td->find('span[class=kleine_schrift]')[0]->plaintext, []);

                                                    $arrayTarjeta= explode("'",$td->find('span[class=kleine_schrift]')[0]->plaintext);
                                                    $mintutoTarjetaSuplenteL = (int) $arrayTarjeta[0];
                                                    if (count($arrayTarjeta)>1){
                                                        $adiccion = (int) $arrayTarjeta[1];
                                                        if ($adiccion>0){
                                                            Log::channel('mi_log')->info('OJO!! tarjeta addicion: ');
                                                            $mintutoTarjetaSuplenteL = $mintutoTarjetaSuplenteL + $adiccion;
                                                        }

                                                    }
                                                } else {
                                                    //$mintutoTarjetaTitularL = $td->find('span[class=kleine_schrift]')[0]->plaintext;
                                                    //Log::channel('mi_log')->info('OJO!! tarjeta: ' . $td->find('span[class=kleine_schrift]')[0]->plaintext, []);
                                                    $arrayTarjeta= explode("'",$td->find('span[class=kleine_schrift]')[0]->plaintext);
                                                    $mintutoTarjetaTitularL = (int) $arrayTarjeta[0];
                                                    if (count($arrayTarjeta)>1){
                                                        $adiccion = (int) $arrayTarjeta[1];
                                                        if ($adiccion>0) {
                                                            Log::channel('mi_log')->info('OJO!! tarjeta addicion: ');
                                                            $mintutoTarjetaTitularL = $mintutoTarjetaTitularL + $adiccion;
                                                        }
                                                    }
                                                }


                                            }

                                        }
                                    }

                                    if ($td->find('img')) {
                                        if ($td->find('img')[0]->title == 'Tarjeta amarilla') {
                                            if ($suplentes) {

                                                $amarillaSuplenteL = (int)filter_var($mintutoTarjetaSuplenteL, FILTER_SANITIZE_NUMBER_INT);;
                                                //Log::channel('mi_log')->info('OJO!! amarilla suplente: ' . $amarillaSuplenteL, []);
                                            } else {
                                                $amarillaTitularL = (int)filter_var($mintutoTarjetaTitularL, FILTER_SANITIZE_NUMBER_INT);;
                                                //Log::channel('mi_log')->info('OJO!! amarilla titular: ' . $amarillaTitularL, []);
                                            }

                                        }
                                        if ($td->find('img')[0]->title == 'Roja directa') {
                                            if ($suplentes) {
                                                $rojaSuplenteL = (int)filter_var($mintutoTarjetaSuplenteL, FILTER_SANITIZE_NUMBER_INT);;
                                                //Log::channel('mi_log')->info('OJO!! roja suplente: ' . $rojaSuplenteL, []);
                                            } else {
                                                $rojaTitularL = (int)filter_var($mintutoTarjetaTitularL, FILTER_SANITIZE_NUMBER_INT);
                                                //Log::channel('mi_log')->info('OJO!! roja titular: ' . $rojaTitularL, []);
                                            }

                                        }
                                        if ($td->find('img')[0]->title == 'Doble amarilla') {
                                            if ($suplentes) {
                                                $dobleamarillaSuplenteL = (int)filter_var($mintutoTarjetaSuplenteL, FILTER_SANITIZE_NUMBER_INT);;
                                                //Log::channel('mi_log')->info('OJO!! dobleamarilla suplente: ' . $dobleamarillaSuplenteL, []);
                                            } else {
                                                $dobleamarillaTitularL = (int)filter_var($mintutoTarjetaTitularL, FILTER_SANITIZE_NUMBER_INT);;
                                                //Log::channel('mi_log')->info('OJO!! dobleamarilla titular: ' . $dobleamarillaTitularL, []);
                                            }

                                        }

                                    }

                                    if ($td->find('a')) {
                                        if ($suplentes) {
                                            $jugadorSuplenteL = $td->find('a')[0]->title;
                                            //Log::channel('mi_log')->info('OJO!! suplente: ' . $jugadorSuplenteL, []);
                                        } else {
                                            $jugadorTitularL = $td->find('a')[0]->title;
                                            //Log::channel('mi_log')->info('OJO!! titular: ' . $jugadorTitularL, []);
                                        }


                                    }
                                }


                            }
                            if (!$tablaIncidencias) {
                                if (($jugadorTitularL) || ($jugadorSuplenteL)) {
                                    $incidenciasT = array();
                                    $incidenciasS = array();
                                    if ($saleTitularL) {
                                        $incidenciasT[] = array('Sale', $saleTitularL);
                                    }
                                    if ($amarillaTitularL) {
                                        $incidenciasT[] = array('Tarjeta amarilla', $amarillaTitularL);
                                    }
                                    if ($dobleamarillaTitularL) {
                                        $incidenciasT[] = array('Expulsado por doble amarilla', $dobleamarillaTitularL);
                                    }
                                    if ($rojaTitularL) {
                                        $incidenciasT[] = array('Tarjeta roja', $rojaTitularL);
                                    }

                                    if (!empty($golesArray)) {
                                        foreach ($golesArray as $golmin) {
                                            //Log::channel('mi_log')->info('OJO!! comparar goles: ' . trim($jugadorTitularL).'=='.trim($jugador).' - '.$golmin, []);
                                            $incGol = explode('-', $golmin);
                                            if (trim($jugadorTitularL) == trim($incGol[0])) {

                                                $minGol = $incGol[1];
                                                $incidenciaGol = '';
                                                if (!empty($incGol[2])) {
                                                    $incidenciaGol = $incGol[2];
                                                }
                                                if (!$incidenciaGol) {
                                                    $incidenciasT[] = array('Gol', $minGol);
                                                    $golesL++;
                                                } else {
                                                    if (str_contains($incidenciaGol, 'cabeza')) {
                                                        $incidenciasT[] = array('Cabeza', $minGol);
                                                        $golesL++;
                                                    }
                                                    if (str_contains($incidenciaGol, 'penalti')) {
                                                        $incidenciasT[] = array('Penal', $minGol);
                                                        $golesL++;
                                                    }
                                                    if (str_contains($incidenciaGol, 'propia puerta')) {
                                                        $incidenciasT[] = array('Gol en propia meta', $minGol);
                                                        $golesV++;
                                                    }
                                                    if (str_contains($incidenciaGol, 'tiro libre')) {
                                                        $incidenciasT[] = array('Tiro libre', $minGol);
                                                        $golesL++;
                                                    }
                                                    if (str_contains($incidenciaGol, 'derecha')) {
                                                        $incidenciasT[] = array('Gol', $minGol);
                                                        $golesL++;
                                                    }
                                                    if (str_contains($incidenciaGol, 'izquierda')) {
                                                        $incidenciasT[] = array('Gol', $minGol);
                                                        $golesL++;
                                                    }
                                                }


                                            }
                                            if (trim($jugadorSuplenteL) == trim($incGol[0])) {

                                                $minGol = $incGol[1];
                                                $incidenciaGol = '';
                                                if (!empty($incGol[2])) {
                                                    $incidenciaGol = $incGol[2];
                                                }
                                                if (!$incidenciaGol) {
                                                    $incidenciasS[] = array('Gol', $minGol);
                                                    $golesL++;
                                                } else {
                                                    if (str_contains($incidenciaGol, 'cabeza')) {
                                                        $incidenciasS[] = array('Cabeza', $minGol);
                                                        $golesL++;
                                                    }
                                                    if (str_contains($incidenciaGol, 'penalti')) {
                                                        $incidenciasS[] = array('Penal', $minGol);
                                                        $golesL++;
                                                    }
                                                    if (str_contains($incidenciaGol, 'propia puerta')) {
                                                        $incidenciasS[] = array('Gol en propia meta', $minGol);
                                                        $golesV++;
                                                    }
                                                    if (str_contains($incidenciaGol, 'tiro libre')) {
                                                        $incidenciasS[] = array('Tiro libre', $minGol);
                                                        $golesL++;
                                                    }
                                                    if (str_contains($incidenciaGol, 'derecha')) {
                                                        $incidenciasS[] = array('Gol', $minGol);
                                                        $golesL++;
                                                    }
                                                    if (str_contains($incidenciaGol, 'izquierda')) {
                                                        $incidenciasS[] = array('Gol', $minGol);
                                                        $golesL++;
                                                    }
                                                }

                                            }


                                        }
                                    }
                                    if ($amarillaSuplenteL) {
                                        $incidenciasS[] = array('Tarjeta amarilla', $amarillaSuplenteL);
                                    }
                                    if ($dobleamarillaSuplenteL) {
                                        $incidenciasS[] = array('Expulsado por doble amarilla', $dobleamarillaSuplenteL);
                                    }
                                    if ($rojaSuplenteL) {
                                        $incidenciasS[] = array('Tarjeta roja', $rojaSuplenteL);
                                    }
                                    if ($saleSuplenteL) {
                                        $incidenciasS[] = array('Sale', $saleSuplenteL);
                                    }
                                    if ($entraSuplenteL) {
                                        $incidenciasS[] = array('Entra', $entraSuplenteL);
                                    }

                                    if ($suplentes) {
                                        $data2 = array(
                                            'dorsal' => trim($dorsalSuplenteL),
                                            'nombre' => trim($jugadorSuplenteL),
                                            'tipo' => 'Suplente',
                                            'incidencias' => $incidenciasS
                                        );
                                    } else {
                                        $data2 = array(
                                            'dorsal' => trim($dorsalTitularL),
                                            'nombre' => trim($jugadorTitularL),
                                            'tipo' => 'Titular',
                                            'incidencias' => $incidenciasT
                                        );
                                    }
                                    if (!empty($data2)) {
                                        $jugadores[] = $data2;
                                    }
                                }




                            }
                        }
                        $data = array(

                            'equipo' => $partido->equipol->nombre,

                            'jugadores' => $jugadores
                        );
                        if (!$tablaIncidencias) {
                            if (!empty($data)){
                                $equipos[] = $data;
                            }

                        }
                    }


                    if ($tabla==3){
                        $jugadores = array();
                        //Log::channel('mi_log')->info('OJO!! visitante:',[]);
                        $suplentes=0;
                        foreach ($element->find('tr') as $tr) {
                            $dorsalTitularV = '';
                            $jugadorTitularV = '';
                            $saleTitularV = '';
                            $amarillaTitularV = '';
                            $dobleamarillaTitularV = '';
                            $rojaTitularV = '';
                            $mintutoTarjetaTitularV = '';
                            $dorsalSuplenteV = '';
                            $jugadorSuplenteV = '';
                            $entraSuplenteV = '';
                            $saleSuplenteV = '';
                            $amarillaSuplenteV = '';
                            $dobleamarillaSuplenteV = '';
                            $rojaSuplenteV = '';
                            $mintutoTarjetaSuplenteV = '';
                            foreach ($tr->find('td') as $td) {
                                ////Log::channel('mi_log')->info('OJO!! linea: ' . $td->plaintext, []);
                                if (trim($td->plaintext)=='Banquillo') {
                                    $suplentes=1;
                                }
                                if ($td->find('span[class=kleine_schrift]')) {
                                    if ($td->find('span[style=font-weight: bold; color: #646464]')) {
                                        if ($suplentes){
                                            $dorsalSuplenteV= $td->find('span[class=kleine_schrift]')[0]->plaintext;
                                            //Log::channel('mi_log')->info('OJO!! dorsal suplente: ' . $dorsalSuplenteV, []);
                                        }
                                        else{
                                            $dorsalTitularV = $td->find('span[class=kleine_schrift]')[0]->plaintext;
                                            //Log::channel('mi_log')->info('OJO!! dorsal titular: ' . $dorsalTitularV, []);
                                        }

                                    }
                                    elseif ($td->find('span[class=rottext]')) {
                                        if ($suplentes){
                                            //$saleSuplenteV = (int) filter_var($td->find('span[class=rottext]')[0]->plaintext, FILTER_SANITIZE_NUMBER_INT);
                                            $arraySale= explode("'",$td->find('span[class=rottext]')[0]->plaintext);
                                            $saleSuplenteV = (int) $arraySale[0];
                                            if (count($arraySale)>1){
                                                $adiccion = (int) $arraySale[1];
                                                if ($adiccion>0) {
                                                    Log::channel('mi_log')->info('OJO!! cambio addicion: ');
                                                    $saleSuplenteV = $saleSuplenteV + $adiccion;
                                                }
                                            }
                                            //Log::channel('mi_log')->info('OJO!! sale suplente: ' . $saleSuplenteV, []);
                                        }
                                        else{
                                            //$saleTitularV = (int) filter_var($td->find('span[class=rottext]')[0]->plaintext, FILTER_SANITIZE_NUMBER_INT);


                                            $arraySale= explode("'",$td->find('span[class=rottext]')[0]->plaintext);
                                            $saleTitularV = (int) $arraySale[0];
                                            if (count($arraySale)>1){
                                                $adiccion = (int) $arraySale[1];
                                                if ($adiccion>0) {
                                                    Log::channel('mi_log')->info('OJO!! cambio addicion: ');
                                                    $saleTitularV = $saleTitularV + $adiccion;
                                                }
                                            }
                                            //Log::channel('mi_log')->info('OJO!! sale titular: ' . $saleTitularV, []);
                                        }

                                    }
                                    elseif ($td->find('span[class=gruentext]')) {
                                        if ($suplentes){
                                            //$entraSuplenteV = (int) filter_var($td->find('span[class=gruentext]')[0]->plaintext, FILTER_SANITIZE_NUMBER_INT);

                                            $arrayEntra= explode("'",$td->find('span[class=gruentext]')[0]->plaintext);
                                            $entraSuplenteV = (int) $arrayEntra[0];
                                            if (count($arrayEntra)>1){
                                                $adiccion = (int) $arrayEntra[1];
                                                if ($adiccion>0) {
                                                    Log::channel('mi_log')->info('OJO!! cambio addicion: ');
                                                    $entraSuplenteV = $entraSuplenteV + $adiccion;
                                                }
                                            }
                                            //Log::channel('mi_log')->info('OJO!! entra suplente: ' . $entraSuplenteV, []);
                                        }


                                    }
                                    else{
                                        if ($td->find('span[class=kleine_schrift]')[0]->title !=''){
                                            if ($suplentes){
                                                //$mintutoTarjetaSuplenteV = $td->find('span[class=kleine_schrift]')[0]->plaintext;
                                                //Log::channel('mi_log')->info('OJO!! tarjeta: ' . $td->find('span[class=kleine_schrift]')[0]->plaintext, []);
                                                $arrayTarjeta= explode("'",$td->find('span[class=kleine_schrift]')[0]->plaintext);
                                                $mintutoTarjetaSuplenteV = (int) $arrayTarjeta[0];
                                                if (count($arrayTarjeta)>1){
                                                    $adiccion = (int) $arrayTarjeta[1];
                                                    if ($adiccion>0) {
                                                        Log::channel('mi_log')->info('OJO!! tarjeta addicion: ');
                                                        $mintutoTarjetaSuplenteV = $mintutoTarjetaSuplenteV + $adiccion;
                                                    }
                                                }
                                            }
                                            else{
                                                //$mintutoTarjetaTitularV = $td->find('span[class=kleine_schrift]')[0]->plaintext;
                                                //Log::channel('mi_log')->info('OJO!! tarjeta: ' . $td->find('span[class=kleine_schrift]')[0]->plaintext, []);
                                                $arrayTarjeta= explode("'",$td->find('span[class=kleine_schrift]')[0]->plaintext);
                                                $mintutoTarjetaTitularV = (int) $arrayTarjeta[0];
                                                if (count($arrayTarjeta)>1){
                                                    $adiccion = (int) $arrayTarjeta[1];
                                                    if ($adiccion>0) {
                                                        Log::channel('mi_log')->info('OJO!! tarjeta addicion: ');
                                                        $mintutoTarjetaTitularV = $mintutoTarjetaTitularV + $adiccion;
                                                    }
                                                }
                                            }




                                        }

                                    }
                                }

                                if ($td->find('img')) {
                                    if ($td->find('img')[0]->title == 'Tarjeta amarilla') {
                                        if ($suplentes){
                                            $amarillaSuplenteV = (int) filter_var($mintutoTarjetaSuplenteV, FILTER_SANITIZE_NUMBER_INT);;
                                            //Log::channel('mi_log')->info('OJO!! amarilla suplente: ' . $mintutoTarjetaSuplenteV, []);
                                        }
                                        else{
                                            $amarillaTitularV = (int) filter_var($mintutoTarjetaTitularV, FILTER_SANITIZE_NUMBER_INT);;
                                            //Log::channel('mi_log')->info('OJO!! amarilla titular: ' . $amarillaTitularV, []);
                                        }

                                    }
                                    if ($td->find('img')[0]->title == 'Roja directa') {
                                        if ($suplentes){
                                            $rojaSuplenteV =  (int) filter_var($mintutoTarjetaSuplenteV, FILTER_SANITIZE_NUMBER_INT);;
                                            //Log::channel('mi_log')->info('OJO!! roja titular: ' . $rojaSuplenteV, []);
                                        }
                                        else{
                                            $rojaTitularV =  (int) filter_var($mintutoTarjetaTitularV, FILTER_SANITIZE_NUMBER_INT);;
                                            //Log::channel('mi_log')->info('OJO!! roja titular: ' . $rojaTitularV, []);
                                        }

                                    }
                                    if ($td->find('img')[0]->title == 'Doble amarilla') {
                                        if ($suplentes){
                                            $dobleamarillaSuplenteV = (int) filter_var($mintutoTarjetaSuplenteV, FILTER_SANITIZE_NUMBER_INT);;
                                            //Log::channel('mi_log')->info('OJO!! dobleamarilla titular: ' . $dobleamarillaSuplenteV, []);
                                        }
                                        else{
                                            $dobleamarillaTitularV = (int) filter_var($mintutoTarjetaTitularV, FILTER_SANITIZE_NUMBER_INT);;
                                            //Log::channel('mi_log')->info('OJO!! dobleamarilla titular: ' . $dobleamarillaTitularV, []);
                                        }

                                    }

                                }

                                if ($td->find('a')) {
                                    if ($suplentes){
                                        $jugadorSuplenteV = $td->find('a')[0]->title;
                                        //Log::channel('mi_log')->info('OJO!! suplente: ' . $jugadorSuplenteV, []);
                                    }
                                    else{
                                        $jugadorTitularV = $td->find('a')[0]->title;
                                        //Log::channel('mi_log')->info('OJO!! titular: ' . $jugadorTitularV, []);
                                    }


                                }


                            }
                            if ($jugadorTitularV || $jugadorSuplenteV){
                                $incidenciasT = array();
                                $incidenciasS = array();
                                if ($saleTitularV){
                                    $incidenciasT[]=array('Sale', $saleTitularV);
                                }
                                if ($amarillaTitularV){
                                    $incidenciasT[]=array('Tarjeta amarilla', $amarillaTitularV);
                                }
                                if ($dobleamarillaTitularV){
                                    $incidenciasT[]=array('Expulsado por doble amarilla', $dobleamarillaTitularV);
                                }
                                if ($rojaTitularV){
                                    $incidenciasT[]=array('Tarjeta roja', $rojaTitularV);
                                }

                                if (!empty($golesArray)){
                                    foreach ($golesArray as $golmin){
                                        //Log::channel('mi_log')->info('OJO!! comparar goles: ' . trim($jugador).' - '.$golmin, []);
                                        $incGol = explode('-',$golmin);
                                        if (trim($jugadorTitularV)==trim($incGol[0])){


                                            $minGol = $incGol[1];
                                            $incidenciaGol='';
                                            if (!empty($incGol[2])){
                                                $incidenciaGol = $incGol[2];
                                            }

                                            if (!$incidenciaGol){
                                                $incidenciasT[]=array('Gol', $minGol);
                                                $golesV++;
                                            }
                                            else{
                                                if (str_contains($incidenciaGol,'cabeza')) {
                                                    $incidenciasT[]=array('Cabeza', $minGol);
                                                    $golesV++;
                                                }
                                                if (str_contains($incidenciaGol,'penalti')) {

                                                    $incidenciasT[]=array('Penal', $minGol);
                                                    $golesV++;
                                                }
                                                if (str_contains($incidenciaGol,'propia puerta')) {
                                                    $incidenciasT[]=array('Gol en propia meta', $minGol);
                                                    $golesL++;
                                                }
                                                if (str_contains($incidenciaGol,'tiro libre')) {
                                                    $incidenciasT[]=array('Tiro libre', $minGol);
                                                    $golesV++;
                                                }
                                                if (str_contains($incidenciaGol, 'derecha')) {
                                                    $incidenciasT[] = array('Gol', $minGol);
                                                    $golesV++;
                                                }
                                                if (str_contains($incidenciaGol, 'izquierda')) {
                                                    $incidenciasT[] = array('Gol', $minGol);
                                                    $golesV++;
                                                }
                                            }


                                        }
                                        if (trim($jugadorSuplenteV)==trim($incGol[0])){

                                            $minGol = $incGol[1];
                                            $incidenciaGol='';
                                            if (!empty($incGol[2])){
                                                $incidenciaGol = $incGol[2];
                                            }
                                            if (!$incidenciaGol){
                                                $incidenciasS[]=array('Gol', $minGol);
                                                $golesV++;
                                            }
                                            else{
                                                if (str_contains($incidenciaGol,'cabeza')) {
                                                    $incidenciasS[]=array('Cabeza', $minGol);
                                                    $golesV++;
                                                }
                                                if (str_contains($incidenciaGol,'penalti')) {
                                                    $incidenciasS[]=array('Penal', $minGol);
                                                    $golesV++;
                                                }
                                                if (str_contains($incidenciaGol,'propia puerta')) {
                                                    $incidenciasS[]=array('Gol en propia meta', $minGol);
                                                    $golesL++;
                                                }
                                                if (str_contains($incidenciaGol,'tiro libre')) {
                                                    $incidenciasS[]=array('Tiro libre', $minGol);
                                                    $golesV++;
                                                }
                                                if (str_contains($incidenciaGol, 'derecha')) {
                                                    $incidenciasS[] = array('Gol', $minGol);
                                                    $golesV++;
                                                }
                                                if (str_contains($incidenciaGol, 'izquierda')) {
                                                    $incidenciasS[] = array('Gol', $minGol);
                                                    $golesV++;
                                                }
                                            }


                                        }



                                    }
                                }
                                if ($amarillaSuplenteV){
                                    $incidenciasS[]=array('Tarjeta amarilla', $amarillaSuplenteV);
                                }
                                if ($dobleamarillaSuplenteV){
                                    $incidenciasS[]=array('Expulsado por doble amarilla', $dobleamarillaSuplenteV);
                                }
                                if ($rojaSuplenteV){
                                    $incidenciasS[]=array('Tarjeta roja', $rojaSuplenteV);
                                }
                                if ($saleSuplenteV){
                                    $incidenciasS[]=array('Sale', $saleSuplenteV);
                                }
                                if ($entraSuplenteV){
                                    $incidenciasS[]=array('Entra', $entraSuplenteV);
                                }

                                if ($suplentes){
                                    $data2 = array(
                                        'dorsal' => trim($dorsalSuplenteV),
                                        'nombre' => trim($jugadorSuplenteV),
                                        'tipo' => 'Suplente',
                                        'incidencias' =>$incidenciasS
                                    );
                                }
                                else{
                                    $data2 = array(
                                        'dorsal' => trim($dorsalTitularV),
                                        'nombre' => trim($jugadorTitularV),
                                        'tipo' => 'Titular',
                                        'incidencias' => $incidenciasT
                                    );
                                }
                                if (!empty($data2)){
                                    $jugadores[]=$data2;
                                }
                            }



                        }
                        $data = array(

                            'equipo' => $partido->equipov->nombre,

                            'jugadores' => $jugadores
                        );
                        if (!empty($data)){
                            $equipos[] = $data;
                        }

                    }


                    if (!$tablaIncidencias){
                        $tabla ++;

                    }

                    $tablaIncidencias=0;


                    $entrenadoresArray = explode('Entrenador:', $element->plaintext);
                    if (count($entrenadoresArray)>3){
                        Log::channel('mi_log')->info('OJO!! varios entrenadores: '.$partido->equipol->nombre.' VS '.$partido->equipov->nombre,[]);
                    }
                    if(count($entrenadoresArray)>1){
                        $dtLocal = $entrenadoresArray[1];
                        //Log::channel('mi_log')->info('DT Local: '.utf8_decode($entrenadoresArray[1]), []);
                        if (isset($entrenadoresArray[2])){
                            $dtVisitante = $entrenadoresArray[2];
                        }
                        else{
                            Log::channel('mi_log')->info('OJO!! Falta Entrenador visitante: '.$partido->equipol->nombre.' VS '.$partido->equipov->nombre,[]);
                            $dtVisitante ='';
                        }

                        //Log::channel('mi_log')->info('DT Visitante: '.utf8_decode($entrenadoresArray[2]), []);
                    }
                    else{
                        /*$dtLocal ='';
                        $dtVisitante ='';*/
                        $asistente=0;
                        $asistente1='';
                        $asistente2='';
                        $arbitro='';
                        $arbitro1='';
                        $arbitro2='';
                        $nombreArbitro = '';
                        foreach ($element->find('td[class="dunkel"]') as $element2) {

                            foreach ($element2->find('a') as $link) {
                                $linkArray = explode(' ', $link->title);

                                if (($linkArray[0])=='Árbitro'){
                                    if (($linkArray[1])=='asistente'){
                                        $nombreAsistente = '';
                                        for ($i = 2; $i < count($linkArray); $i++) {
                                            $nombreAsistente .= ($linkArray[$i]).' ';
                                        }
                                        if ($asistente==0){
                                            $asistente1= $nombreAsistente;
                                            Log::channel('mi_log')->info('Asistente 1: '.$nombreAsistente, []);
                                            $asistente++;
                                        }
                                        else{
                                            $asistente2= $nombreAsistente;
                                            Log::channel('mi_log')->info('Asistente 2: '.$nombreAsistente, []);
                                            $asistente++;
                                        }

                                    }
                                    else{
                                        //$nombreArbitro = '';
                                        for ($i = 1; $i < count($linkArray); $i++) {
                                            $nombreArbitro .= ($linkArray[$i]).' ';
                                        }

                                        Log::channel('mi_log')->info('Arbitro: '.$nombreArbitro, []);

                                    }
                                }


                            }

                        }

                    }

                }
                $arrArbitro = explode(' ', $nombreArbitro);
                if (count($arrArbitro) > 1) {
                    //$arbitro = Arbitro::where('nombre', 'like', "%$arrArbitro[0]%")->where('apellido', 'like', "%$arrArbitro[1]%")->first();
                    $arbitro=Arbitro::SELECT('arbitros.*')->Join('personas','personas.id','=','arbitros.persona_id')->where('nombre', 'like', "%$arrArbitro[0]%")->where('apellido', 'like', "%$arrArbitro[1]%")->first();
                }

                if (!$arbitro) {
                    Log::channel('mi_log')->info('OJO!! Arbitro NO encontrado: ' . $nombreArbitro . ' ' . $partido->equipol->nombre . ' VS ' . $partido->equipov->nombre, []);
                } else {
                    $data3 = array(
                        'partido_id' => $partido->id,
                        'arbitro_id' => $arbitro->id,
                        'tipo' => 'Principal'
                    );
                    $partido_arbitro = PartidoArbitro::where('partido_id', '=', "$partido->id")->where('arbitro_id', '=', "$arbitro->id")->first();
                    try {
                        if (!empty($partido_arbitro)) {

                            $partido_arbitro->update($data3);
                        } else {
                            $partido_arbitro = PartidoArbitro::create($data3);
                        }

                    } catch (QueryException $ex) {
                        $error = $ex->getMessage();
                        $ok = 0;
                        //continue;
                    }
                }
                $arrArbitro = explode(' ', $asistente1);
                if (count($arrArbitro) > 1) {
                    //$arbitro1 = Arbitro::where('nombre', 'like', "%$arrArbitro[0]%")->where('apellido', 'like', "%$arrArbitro[1]%")->first();
                    $arbitro1=Arbitro::SELECT('arbitros.*')->Join('personas','personas.id','=','arbitros.persona_id')->where('nombre', 'like', "%$arrArbitro[0]%")->where('apellido', 'like', "%$arrArbitro[1]%")->first();
                }

                if (!$arbitro1) {
                    Log::channel('mi_log')->info('OJO!! Asistente NO encontrado: ' . $asistente1 . ' ' . $partido->equipol->nombre . ' VS ' . $partido->equipov->nombre, []);
                } else {
                    $data3 = array(
                        'partido_id' => $partido->id,
                        'arbitro_id' => $arbitro1->id,
                        'tipo' => 'Linea 1'
                    );
                    $partido_arbitro = PartidoArbitro::where('partido_id', '=', "$partido->id")->where('arbitro_id', '=', "$arbitro1->id")->first();
                    try {
                        if (!empty($partido_arbitro)) {

                            $partido_arbitro->update($data3);
                        } else {
                            $partido_arbitro = PartidoArbitro::create($data3);
                        }

                    } catch (QueryException $ex) {
                        $error = $ex->getMessage();
                        $ok = 0;
                        //continue;
                    }
                }
                $arrArbitro = explode(' ', $asistente2);
                if (count($arrArbitro) > 1) {
                    //$arbitro2 = Arbitro::where('nombre', 'like', "%$arrArbitro[0]%")->where('apellido', 'like', "%$arrArbitro[1]%")->first();
                    $arbitro2=Arbitro::SELECT('arbitros.*')->Join('personas','personas.id','=','arbitros.persona_id')->where('nombre', 'like', "%$arrArbitro[0]%")->where('apellido', 'like', "%$arrArbitro[1]%")->first();
                }

                if (!$arbitro2) {
                    Log::channel('mi_log')->info('OJO!! Asistente NO encontrado: ' . $asistente2 . ' ' . $partido->equipol->nombre . ' VS ' . $partido->equipov->nombre, []);
                } else {
                    $data3 = array(
                        'partido_id' => $partido->id,
                        'arbitro_id' => $arbitro2->id,
                        'tipo' => 'Linea 2'
                    );
                    $partido_arbitro = PartidoArbitro::where('partido_id', '=', "$partido->id")->where('arbitro_id', '=', "$arbitro2->id")->first();
                    try {
                        if (!empty($partido_arbitro)) {

                            $partido_arbitro->update($data3);
                        } else {
                            $partido_arbitro = PartidoArbitro::create($data3);
                        }

                    } catch (QueryException $ex) {
                        $error = $ex->getMessage();
                        $ok = 0;
                        //continue;
                    }
                }
                $strEntrenador = trim($dtLocal);
                if (!empty($strEntrenador)) {

                    $arrEntrenador = explode(' ', $strEntrenador);
                    //$entrenadorL = Tecnico::where('nombre', 'like', "%$arrEntrenador[0]%")->where('apellido', 'like', "%$arrEntrenador[1]%")->first();
                    $entrenadorL=Tecnico::SELECT('tecnicos.*')->Join('personas','personas.id','=','tecnicos.persona_id')->where('nombre', 'like', "%$arrEntrenador[0]%")->where('apellido', 'like', "%$arrEntrenador[1]%")->first();
                }
                if (!empty($entrenadorL)){
                    $data3=array(
                        'partido_id'=>$partido->id,
                        'equipo_id'=>$partido->equipol->id,
                        'tecnico_id'=>$entrenadorL->id
                    );
                    $partido_tecnico=PartidoTecnico::where('partido_id','=',"$partido->id")->where('equipo_id','=',$partido->equipol->id)->where('tecnico_id','=',"$entrenadorL->id")->first();
                    try {
                        if (!empty($partido_tecnico)){

                            $partido_tecnico->update($data3);
                        }
                        else{
                            $partido_tecnico=PartidoTecnico::create($data3);
                        }

                    }catch(QueryException $ex){
                        $error = $ex->getMessage();
                        $ok=0;
                        //continue;
                    }
                }
                else{
                    Log::channel('mi_log')->info('OJO!!! Técnico NO encontrado : ' . trim($dtLocal),[]);
                }
                $entrenadorV='';
                if ($dtVisitante){
                    $strEntrenador=trim($dtVisitante);
                    $arrEntrenador = explode(' ', $strEntrenador);
                    $entrenadorV=Tecnico::SELECT('tecnicos.*')->Join('personas','personas.id','=','tecnicos.persona_id')->where('nombre','like',"%$arrEntrenador[0]%")->where('apellido','like',"%$arrEntrenador[1]%")->first();
                }

                if (!empty($entrenadorV)){
                    $data3=array(
                        'partido_id'=>$partido->id,
                        'equipo_id'=>$partido->equipov->id,
                        'tecnico_id'=>$entrenadorV->id
                    );
                    $partido_tecnico=PartidoTecnico::where('partido_id','=',"$partido->id")->where('equipo_id','=',$partido->equipov->id)->where('tecnico_id','=',"$entrenadorV->id")->first();
                    try {
                        if (!empty($partido_tecnico)){

                            $partido_tecnico->update($data3);
                        }
                        else{
                            $partido_tecnico=PartidoTecnico::create($data3);
                        }

                    }catch(QueryException $ex){
                        $error = $ex->getMessage();
                        $ok=0;
                        //continue;
                    }
                }
                else{
                    Log::channel('mi_log')->info('OJO!!! Técnico NO encontrado : ' . trim($dtVisitante),[]);
                }
                $golesTotales = $partido->golesl+$partido->golesv;
                $golesLocales = $partido->golesl;
                $golesVisitantes = $partido->golesv;
                if (($golesL!=$golesLocales)||($golesV!=$golesVisitantes)) {
                    Log::channel('mi_log')->info('OJO!!! No coincide la cantidad de goles en: ' . $partido->equipol->nombre . ' VS ' . $partido->equipov->nombre . ' -> ' . $golesL.' a '. $golesV. ' - ' . $golesLocales. ' a '.$golesVisitantes, []);
                }
                foreach ($equipos as $eq) {
                    Log::channel('mi_log')->info('Equipo  ' . $eq['equipo'], []);
                    $strEquipo=trim($eq['equipo']);
                    $equipo=Equipo::where('nombre','like',"%$strEquipo%")->first();
                    if (!empty($equipo)){
                        foreach ($eq['jugadores'] as $jugador) {
                            //Log::channel('mi_log')->info(json_encode($jugador), []);
                            $jugador_id =0;
                            Log::channel('mi_log')->info('Jugador: ' . $jugador['dorsal'] . ' ' . $jugador['nombre'] . ' del equipo ' . $strEquipo, []);
                            $grupos = Grupo::where('torneo_id', '=',$grupo->torneo->id)->get();
                            $arrgrupos='';
                            foreach ($grupos as $grupo){
                                $arrgrupos .=$grupo->id.',';
                            }

                            $plantillas = Plantilla::wherein('grupo_id',explode(',', $arrgrupos))->where('equipo_id','=',$equipo->id)->get();

                            $arrplantillas='';
                            foreach ($plantillas as $plantilla){
                                $arrplantillas .=$plantilla->id.',';
                            }




                            if (!empty($plantillas)){

                                if(!empty($jugador['dorsal'])){

                                    $plantillaJugador = PlantillaJugador::wherein('plantilla_id',explode(',', $arrplantillas))->distinct()->where('dorsal','=',$jugador['dorsal'])->first();
                                }
                                else{
                                    //print_r($jugador);
                                    $plantillaJugador='';
                                    Log::channel('mi_log')->info('OJO!!! con el jugador no está en la plantilla del equipo ' . $strEquipo, []);
                                }
                            }
                            else{
                                $plantillaJugador='';
                                Log::channel('mi_log')->info('OJO!!! No hay plantilla del equipo ' . $strEquipo, []);
                            }
                            if (!empty($plantillaJugador)) {
                                $jugador_id = $plantillaJugador->jugador->id;
                                $arrApellido = explode(' ', $jugador['nombre']);
                                $mismoDorsal = 0;
                                foreach ($arrApellido as $apellido) {
                                    $consultarJugador = Jugador::Join('personas','personas.id','=','jugadors.persona_id')->where('jugadors.id', '=', $jugador_id)->where('apellido', 'LIKE', "%$apellido%")->first();
                                    if (!empty($consultarJugador)) {
                                        $mismoDorsal = 1;
                                        continue;
                                    }
                                }
                                if (!$mismoDorsal) {
                                    Log::channel('mi_log')->info('OJO!!! con jugador: ' . $jugador['dorsal'] . ' ' . $jugador['nombre'] . ' del equipo ' . $strEquipo, []);
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
                                    'jugador_id' => $jugador_id,
                                    'equipo_id' => $equipo->id,
                                    'dorsal' =>  $jugador['dorsal'],
                                    'tipo' => $jugador['tipo'],
                                    'orden' => $orden
                                );
                                $alineacion = Alineacion::where('partido_id', '=', $partido->id)->where('jugador_id', '=', $jugador_id)->first();
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
                                Log::channel('mi_log')->info('OJO!!! NO se encontró al jugador: ' . $jugadorMostrar.' del equipo '.$strEquipo,[]);

                                $arrApellido = explode(' ', $jugador['nombre']);
                                $mismoDorsal = 0;
                                foreach ($arrApellido as $apellido) {
                                    $consultarJugador = Jugador::Join('personas','personas.id','=','jugadors.persona_id')->Join('plantilla_jugadors','jugadors.id','=','plantilla_jugadors.jugador_id')->wherein('plantilla_jugadors.plantilla_id',explode(',', $arrplantillas))->where('apellido', 'LIKE', "%$apellido%")->first();
                                    //Log::channel('mi_log')->info(json_encode($consultarJugador), []);
                                    if (!empty($consultarJugador)) {
                                        $mismoDorsal = 1;
                                        //Log::channel('mi_log')->info($consultarJugador['tipoJugador'], []);
                                        break;
                                    }
                                }
                                if ($mismoDorsal) {
                                    $jugador_id = $consultarJugador['jugador_id'];
                                    switch ($consultarJugador['tipoJugador']) {
                                        case 'Arquero':
                                            $orden = 0;
                                            break;
                                        case 'Defensor':
                                            $orden = 1;
                                            break;
                                        case 'Medio':
                                            $orden = 2;
                                            break;
                                        case 'Delantero':
                                            $orden = 3;
                                            break;

                                    }
                                    $alineaciondata = array(
                                        'partido_id' => $partido->id,
                                        'jugador_id' => $jugador_id,
                                        'equipo_id' => $equipo->id,
                                        'dorsal' => $consultarJugador['dorsal'],
                                        'tipo' => $jugador['tipo'],
                                        'orden' => $orden
                                    );
                                    $alineacion = Alineacion::where('partido_id', '=', $partido->id)->where('jugador_id', '=', $jugador_id)->first();
                                    try {
                                        if (!empty($alineacion)) {

                                            $alineacion->update($alineaciondata);
                                        } else {
                                            $alineacion = Alineacion::create($alineaciondata);
                                        }
                                        Log::channel('mi_log')->info('OJO!! verificar que sea correcto: '.$consultarJugador['apellido'].', '.$consultarJugador['nombre'].' dorsal '.$consultarJugador['dorsal'], []);

                                    } catch (QueryException $ex) {
                                        $error = $ex->getMessage();
                                        $ok = 0;
                                        continue;
                                    }
                                }
                            }

                            foreach ($jugador['incidencias'] as $incidencia) {

                                if (!empty($incidencia)) {
                                    Log::channel('mi_log')->info('Incidencia: ' . trim($incidencia[0]) . ' MIN: ' . intval(trim($incidencia[1])), []);
                                    Log::channel('mi_log')->info('Incidencias Jugador: ' . $jugador['dorsal'] . ' ' . $jugador['nombre'] . ' - '. trim($incidencia[0]) . ' MIN: ' . intval(trim($incidencia[1])), []);
                                    $tipogol='';
                                    switch (trim($incidencia[0])) {
                                        case 'Gol':
                                            $tipogol='Jugada';
                                            break;
                                        case 'Penal':
                                            $tipogol='Penal';
                                            break;
                                        case 'Tiro libre':
                                            $tipogol='Tiro libre';
                                            break;
                                        case 'Cabeza':
                                            $tipogol='Cabeza';
                                            break;
                                        case 'Gol en propia meta':
                                            $tipogol='En Contra';
                                            break;

                                    }
                                    if ($tipogol){
                                        //if (!empty($plantillaJugador)) {
                                            $goldata = array(
                                                'partido_id' => $partido->id,
                                                'jugador_id' => $jugador_id,
                                                'minuto' => intval(trim($incidencia[1])),
                                                'tipo' => $tipogol
                                            );
                                            $gol = Gol::where('partido_id', '=', $partido->id)->where('jugador_id', '=', $jugador_id)->where('minuto', '=', intval(trim($incidencia[1])))->first();
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
                                        //}

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
                                        //if (!empty($plantillaJugador)) {
                                            $tarjeadata=array(
                                                'partido_id'=>$partido->id,
                                                'jugador_id'=>$jugador_id,
                                                'minuto'=>intval(trim($incidencia[1])),
                                                'tipo'=>$tipotarjeta
                                            );
                                            $tarjeta=Tarjeta::where('partido_id','=',$partido->id)->where('jugador_id','=',$jugador_id)->where('minuto','=',intval(trim($incidencia[1])))->first();
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
                                        //}




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
                                        //if (!empty($plantillaJugador)) {
                                            $cambiodata=array(
                                                'partido_id'=>$partido->id,
                                                'jugador_id'=>$jugador_id,
                                                'minuto'=>intval(trim($incidencia[1])),
                                                'tipo'=>$tipocambio
                                            );
                                            $cambio=Cambio::where('partido_id','=',$partido->id)->where('jugador_id','=',$jugador_id)->where('minuto','=',intval(trim($incidencia[1])))->first();
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
                                        //}




                                    }
                                }

                            }
                        }
                    }
                    else{
                        Log::channel('mi_log')->info('OJO!!! NO se encontró al equipo: ' . $strEquipo,[]);
                    }
                }

            }
            else{
                Log::channel('mi_log')->info('OJO!!! No se econtró la URL2 ' .$url2, []);
                /*$error = 'No se econtró la URL2 del partido: '.$partido->equipol->nombre.' VS '.$partido->equipov->nombre;
                $ok=0;
                continue;*/
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
        return redirect()->route('fechas.show', $partido->fecha->id)->with($respuestaID,$respuestaMSJ);

        //return view('fechas.index', compact('grupo'));
    }




}
