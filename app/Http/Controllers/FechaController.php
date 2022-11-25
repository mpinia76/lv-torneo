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
                    Log::info('Fecha: '.$importData[0]);
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
                             Log::info('Equipo NO encontrado: '.$numero.'-'.$dia.'-'.$strEquipoL,[]);
                         }
                        else{
                            $strEquipoV = ($importData[6]);
                            $equipoV = Equipo::where('nombre', 'like', "%$strEquipoV%")->first();

                            if (!$equipoV){
                                Log::info('Equipo NO encontrado: '.$numero.'-'.$dia.'-'.$strEquipoV,[]);
                            }
                            else{
                                /*$strArbitro = utf8_encode($importData[9]);
                                $arrArbitro = explode(" ", $strArbitro);

                                if(count($arrArbitro)>1) {
                                    $arbitro = Arbitro::orwhere('nombre', 'like', "%$arrArbitro[0]%")->orwhere('apellido', 'like', "%$arrArbitro[1]%")->first();
                                }

                                if (!$arbitro){
                                    Log::info('Arbitro NO encontrado: '.$numero.'-'.$dia.'-'.$strArbitro,[]);
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
                                        //$entrenadorL=Tecnico::where('nombre','like',"%$arrEntrenador[0]%")->where('apellido','like',"%$arrEntrenador[1]%")->first();
                                        $entrenadorL=Tecnico::SELECT('tecnicos.*')->Join('personas','personas.id','=','tecnicos.persona_id')->where('nombre','like',"%$arrEntrenador[0]%")->where('apellido','like',"%$arrEntrenador[1]%")->first();
                                        if (!empty($entrenadorL)){
                                            $plantillaTecnico = PartidoTecnico::where('plantilla_id','=',$plantilla->id)->where('tecnico_id','=',$entrenadorL->id)->first();
                                            if (empty($plantillaTecnico)){
                                                Log::info('NO se encontró como entrenador: ' . $equipos[$i - 1]['entrenador'].' del equipo '.$equipos[$i - 1]['equipo'],[]);
                                            }
                                        }
                                        else{
                                            Log::info('NO se encontró al entrenador: ' . $equipos[$i - 1]['entrenador'].' del equipo '.$equipos[$i - 1]['equipo'],[]);
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
                                        //$entrenadorV=Tecnico::where('nombre','like',"%$arrEntrenador[0]%")->where('apellido','like',"%$arrEntrenador[1]%")->first();
                                        $entrenadorV=Tecnico::SELECT('tecnicos.*')->Join('personas','personas.id','=','tecnicos.persona_id')->where('nombre','like',"%$arrEntrenador[0]%")->where('apellido','like',"%$arrEntrenador[1]%")->first();
                                        if (!empty($entrenadorV)){
                                            $plantillaTecnico = PartidoTecnico::where('plantilla_id','=',$plantilla->id)->where('tecnico_id','=',$entrenadorV->id)->first();
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
                $strEquipoURL='belgrano-de-cordoba';
                //$strEquipoURL='belgrano-cordoba';
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
                $strEquipoURL='estudiantes';
                //$strEquipoURL='estudiantes-de-la-plata';
                break;
            case 'Gimnasia (J)':
                $strEquipoURL='gye-jujuy';
                break;
            case 'Gimnasia (LP)':
                $strEquipoURL='gimnasia-de-la-plata';//ultimo
                //$strEquipoURL='gye-la-plata';//viejo
                //$strEquipoURL='gimnasia-y-esgrima-de-la-plata';
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
                $strEquipoURL='instituto-cordoba';

                break;
            case 'Independiente':
                //$strEquipoURL='ca-independiente';
                $strEquipoURL='independiente';
                break;
            case 'Lanús':
                $strEquipoURL='lanus';//ultimo
                //$strEquipoURL='ca-lanus';//viejo
                break;
            case 'Los Andes':
                $strEquipoURL='los-andes-de-lomas-de-zamora';
                break;
            case 'Newell\'s Old Boys':
                $strEquipoURL='newells-old-boys';
                //$strEquipoURL='ca-newells-old-boys';
                break;
            case 'Nueva Chicago':
                $strEquipoURL='nueva-chicago';
                break;
            case 'Olimpo':
                //$strEquipoURL='olimpo';
                $strEquipoURL='olimpo-de-bahia-blanca';
                break;
            case 'Patronato':
                $strEquipoURL='patronato-de-parana';//ultimo
                //$strEquipoURL='patronato';//viejo
                break;
            case 'Quilmes':
                $strEquipoURL='quilmes-ac';
                break;
            case 'Racing Club':
                $strEquipoURL='racing-club';
                //$strEquipoURL='racing-club-de-avellaneda';
                break;
            case 'River Plate':
                $strEquipoURL='river-plate';
                //$strEquipoURL='ca-river-plate';
                break;
            case 'Rosario Central':
                $strEquipoURL='rosario-central';
                //$strEquipoURL='ca-rosario-central';
                break;
            case 'San Lorenzo':
                $strEquipoURL='san-lorenzo';
                //$strEquipoURL='ca-san-lorenzo-de-almagro';
                //$strEquipoURL='san-lorenzo-de-almagro';
                break;
            case 'San Martín (SJ)':
                $strEquipoURL='san-martin-de-san-juan';
                //$strEquipoURL='ca-san-martin';
                break;
            case 'San Martín (Tuc.)':
                $strEquipoURL='san-martin-de-tucuman';//ultimo
                //$strEquipoURL='san-martin-tucuman';//viejo
                break;
            case 'Sarmiento (Junín)':
                $strEquipoURL='sarmiento-de-junin';
                break;
            case 'Talleres (Cba.)':
                $strEquipoURL='talleres-de-cordoba';//nuevo
                //$strEquipoURL='talleres-cordoba';//viejo
                break;
            case 'Temperley':
                //$strEquipoURL='ca-temperley';
                $strEquipoURL='temperley';
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

    public function dameNombreEquipoURL3($strEquipo)
    {
        $strEquipoURL=strtr($strEquipo, " ", "-");
        switch (trim($strEquipo)) {
            case 'Almagro':
                $strEquipoURL='almagro';
                //$strEquipoURL='club-almagro';
                break;
            case 'Arsenal':
                //$strEquipoURL='arsenal-sarandi';//viejo
                $strEquipoURL='arsenal';//ultimo
                //$strEquipoURL='arsenal-fc';
                break;
            case 'Atlético de Rafaela':
                $strEquipoURL='atl-rafaela';
                break;
            case 'Atlético Tucumán':
                $strEquipoURL='atl-tucuman';
                break;
            case 'Argentinos Juniors':
                $strEquipoURL='argentinos';
                break;
            case 'Banfield':
                $strEquipoURL='banfield';//ultimo
                //$strEquipoURL='ca-banfield';//viejo
                break;
            case 'Boca Juniors':
                $strEquipoURL='boca';
                //$strEquipoURL='ca-boca-juniors';
                break;
            case 'Belgrano':
                $strEquipoURL='belgrano';
                //$strEquipoURL='belgrano-cordoba';
                break;
            case 'Central Córdoba (SdE)':
                $strEquipoURL='central-cordoba-sde';
                break;
            case 'Chacarita Juniors':
                $strEquipoURL='chacarita';
                //$strEquipoURL='ca-chacarita-juniors';
                break;
            case 'Colón de Santa Fe':
                $strEquipoURL='colon';//ultimo
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
                $strEquipoURL='estudiantes';
                //$strEquipoURL='estudiantes-de-la-plata';
                break;
            case 'Gimnasia (J)':
                $strEquipoURL='gimnasia-juj';
                break;
            case 'Gimnasia (LP)':
                $strEquipoURL='gimnasia';//ultimo
                //$strEquipoURL='gye-la-plata';//viejo
                //$strEquipoURL='gimnasia-y-esgrima-de-la-plata';
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
                $strEquipoURL='huracan-ta';

                break;
            case 'Instituto de Córdoba':
                $strEquipoURL='instituto';

                break;
            case 'Independiente':
                //$strEquipoURL='ca-independiente';
                $strEquipoURL='independiente';
                break;
            case 'Lanús':
                $strEquipoURL='lanus';//ultimo
                //$strEquipoURL='ca-lanus';//viejo
                break;
            case 'Los Andes':
                $strEquipoURL='los-andes-de-lomas-de-zamora';
                break;
            case 'Newell\'s Old Boys':
                $strEquipoURL='newells';
                //$strEquipoURL='ca-newells-old-boys';
                break;
            case 'Nueva Chicago':
                $strEquipoURL='nueva-chicago';
                break;
            case 'Olimpo':
                //$strEquipoURL='olimpo';
                $strEquipoURL='olimpo';
                break;
            case 'Patronato':
                $strEquipoURL='patronato-de-parana';//ultimo
                //$strEquipoURL='patronato';//viejo
                break;
            case 'Quilmes':
                $strEquipoURL='quilmes';
                break;
            case 'Racing Club':
                $strEquipoURL='racing';
                //$strEquipoURL='racing-club-de-avellaneda';
                break;
            case 'River Plate':
                $strEquipoURL='river';
                //$strEquipoURL='ca-river-plate';
                break;
            case 'Rosario Central':
                $strEquipoURL='rosario-central';
                //$strEquipoURL='ca-rosario-central';
                break;
            case 'San Lorenzo':
                $strEquipoURL='san-lorenzo';
                //$strEquipoURL='ca-san-lorenzo-de-almagro';
                //$strEquipoURL='san-lorenzo-de-almagro';
                break;
            case 'San Martín (SJ)':
                $strEquipoURL='san-martin-sj';
                //$strEquipoURL='ca-san-martin';
                break;
            case 'San Martín (Tuc.)':
                $strEquipoURL='san-martin-t';//ultimo
                //$strEquipoURL='san-martin-tucuman';//viejo
                break;
            case 'Sarmiento (Junín)':
                $strEquipoURL='sarmiento-de-junin';
                break;
            case 'Talleres (Cba.)':
                $strEquipoURL='talleres';//nuevo
                //$strEquipoURL='talleres-cordoba';//viejo
                break;
            case 'Temperley':
                //$strEquipoURL='ca-temperley';
                $strEquipoURL='temperley';
                break;
            case 'Tigre':
                $strEquipoURL='tigre';
                //$strEquipoURL='ca-tigre';
                break;
            case 'Tiro Federal de Rosario':
                $strEquipoURL='tiro-federal';
                break;
            case 'Unión de Santa Fe':
                $strEquipoURL='union';
                break;
            case 'Vélez Sarsfield':
                $strEquipoURL='velez';
                //$strEquipoURL='ca-velez-sarsfield';
                break;
        }
        return $strEquipoURL;
    }

    public function dameNombreEquipoDB($strEquipo)
    {
        //Log::info('Transformar Equipo '.$strEquipo, []);
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

    public function importincidenciasfecha_old(Request $request)
    {
        set_time_limit(0);
        //Log::info('Entraaaaaa', []);
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
            Log::info('Partido ' .$partido->equipol->nombre.' VS '.$partido->equipov->nombre, []);
            Log::info('URL ' .'https://www.resultados-futbol.com/partido/'.$this->dameNombreEquipoURL($strLocal).'/'.$this->dameNombreEquipoURL($strVisitante).'/'.$year, []);
            try {

                $html = HtmlDomParser::file_get_html('https://www.resultados-futbol.com/partido/'.$this->dameNombreEquipoURL($strLocal).'/'.$this->dameNombreEquipoURL($strVisitante).'/'.$year, false, null, 0);



            }
            catch (Exception $ex) {
                $html='';
                $html = HtmlDomParser::file_get_html('https://www.resultados-futbol.com/partido_8385/'.$this->dameNombreEquipoURL($strLocal).'/'.$this->dameNombreEquipoURL($strVisitante).'/'.$year, false, null, 0);
            }

            if ($html){


                $equipos = array();

                //Log::info('Elemento ' . $html,[]);
                $i = 1;
                $goles=0;
                $golesL=0;
                $golesV=0;
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
                            Log::info('OJO!!! falta el jugador en el penal local de ' . $partido->equipol->nombre.' VS '.$partido->equipov->nombre, []);
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
                if (($golesL!=$golesLocales)||($golesV!=$golesVisitantes)) {
                    Log::info('OJO!!! No coincide la cantidad de goles en: ' . $partido->equipol->nombre . ' VS ' . $partido->equipov->nombre . ' -> ' . $golesL.' a '. $golesV. ' - ' . $golesLocales. ' a '.$golesVisitantes, []);
                    /*try {
                        Log::info('Partido ' . $partido->equipol->nombre . ' VS ' . $partido->equipov->nombre, []);
                        Log::info('URL ' . 'https://www.resultados-futbol.com/partido_8377/' . $this->dameNombreEquipoURL($strLocal) . '/' . $this->dameNombreEquipoURL($strVisitante) . '/' . $year, []);
                        $html = HtmlDomParser::file_get_html('https://www.resultados-futbol.com/partido_8377/' . $this->dameNombreEquipoURL($strLocal) . '/' . $this->dameNombreEquipoURL($strVisitante) . '/' . $year, false, null, 0);


                    } catch (Exception $ex) {
                        $html = '';
                    }*/
                    $html = '';
                    if (!$html) {
                        //Log::info('OJO!!! No se encontro la URL ' . 'https://www.resultados-futbol.com/partido_61201/' . $this->dameNombreEquipoURL($strLocal) . '/' . $this->dameNombreEquipoURL($strVisitante) . '/' . $year, []);
                    } else {
                        $equipos = array();

                        //Log::info('Elemento ' . $html,[]);
                        $i = 1;
                        $goles = 0;
                        $golesL = 0;
                        $golesV = 0;
                        $j = 0;
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
                                    $id = explode('-', $li->find('a')[0]->href);
                                    //Log::info('Jugador ' . $j.' -> '.$jugador[0]->plaintext, []);

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
                            //Log::info('Equipo ' . $equipo[0]->plaintext, []);
                            $jugadores = array();
                            foreach ($element->find('ul[class=aligns-list]') as $element2) {
                                $j++;
                                foreach ($element2->find('li') as $li) {
                                    $dorsal = $li->find('small[class=align-dorsal]');
                                    $jugador = $li->find('h5[class=align-player]');
                                    $id = explode('-', $li->find('a')[0]->href);
                                    //Log::info('ID ' . $id[2], []);
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
                        //Log::info('Partido ' . print_r($equipos,true), []);
                        foreach ($html->find('div[class=event-content]') as $element) {

                            $golL = $element->find('span[class=left event_1]');
                            if ($golL) {
                                if ($golL[0]->find('a')) {
                                    $id = explode('-', $golL[0]->find('a')[0]->href);

                                    $minL = explode('</b>', $element->find('span[class=left minutos minutosizq]')[0]);


                                    $equipos[1]['jugadores'][$id[count($id) - 1]]['incidencias'][] = array('Gol', explode('\'', $minL[1])[0]);
                                } else {
                                    Log::info('OJO!!! falta el jugador en el gol local de ' . $partido->equipol->nombre . ' VS ' . $partido->equipov->nombre, []);
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
                                    Log::info('OJO!!! falta el jugador en el penal local de ' . $partido->equipol->nombre . ' VS ' . $partido->equipov->nombre, []);
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
                                    Log::info('OJO!!! falta el jugador en la amarilla local de ' . $partido->equipol->nombre . ' VS ' . $partido->equipov->nombre, []);
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
                                    Log::info('OJO!!! falta el jugador en el gol visitante de ' . $partido->equipol->nombre . ' VS ' . $partido->equipov->nombre, []);
                                }


                            }

                            $entraL = $element->find('span[class=left event_7]');
                            if ($entraL) {
                                if ($entraL[0]->find('a')) {
                                    $id = explode('-', $entraL[0]->find('a')[0]->href);

                                    $minL = explode('</b>', $element->find('span[class=left minutos minutosizq]')[0]);

                                    $equipos[1]['jugadores'][$id[count($id) - 1]]['incidencias'][] = array('Entra', explode('\'', $minL[1])[0]);
                                } else {
                                    Log::info('OJO!!! falta el jugador en la entrada local de ' . $partido->equipol->nombre . ' VS ' . $partido->equipov->nombre, []);
                                }


                            }

                            $golV = $element->find('span[class=right event_1]');
                            if ($golV) {
                                if ($golV[0]->find('a')) {
                                    $id = explode('-', $golV[0]->find('a')[0]->href);

                                    $minL = explode('</b>', $element->find('span[class=right minutos minutosder]')[0]);


                                    $equipos[2]['jugadores'][$id[count($id) - 1]]['incidencias'][] = array('Gol', explode('\'', $minL[1])[0]);
                                } else {
                                    Log::info('OJO!!! falta el jugador en la entrada local de ' . $partido->equipol->nombre . ' VS ' . $partido->equipov->nombre, []);
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
                                    Log::info('OJO!!! falta el jugador en la amarilla visitante de ' . $partido->equipol->nombre . ' VS ' . $partido->equipov->nombre, []);
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
                                    Log::info('OJO!!! falta el jugador en la salida visitante de ' . $partido->equipol->nombre . ' VS ' . $partido->equipov->nombre, []);
                                }


                            }

                            $entraV = $element->find('span[class=right event_7]');
                            if ($entraV) {
                                if ($entraV[0]->find('a')) {
                                    $id = explode('-', $entraV[0]->find('a')[0]->href);

                                    $minL = explode('</b>', $element->find('span[class=right minutos minutosder]')[0]);

                                    $equipos[2]['jugadores'][$id[count($id) - 1]]['incidencias'][] = array('Entra', explode('\'', $minL[1])[0]);
                                } else {
                                    Log::info('OJO!!! falta el jugador en la entrada visitante de ' . $partido->equipol->nombre . ' VS ' . $partido->equipov->nombre, []);
                                }


                            }


                        }
                        if (($golesL!=$golesLocales)||($golesV!=$golesVisitantes)) {
                            Log::info('OJO!!! No coincide la cantidad de goles en: ' . $partido->equipol->nombre . ' VS ' . $partido->equipov->nombre . ' -> ' . $golesL.' a '. $golesV. ' - ' . $golesLocales. ' a '.$golesVisitantes, []);
                        }

                    }
                }
                    foreach ($equipos as $eq) {
                        //Log::info('Equipo  ' . $equipo['equipo'], []);
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
                                        Log::info('OJO!!! con el jugador no está en la plantilla del equipo ' . $strEquipo, []);
                                    }
                                }
                                else{
                                    $plantillaJugador='';
                                    Log::info('OJO!!! No hay plantilla del equipo ' . $strEquipo, []);
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


            try {
                //Log::info('Partido ' .$partido->equipol->nombre.' VS '.$partido->equipov->nombre, []);

                Log::info('URL ' .'https://www.livefutbol.com/cronica/primera-division-'.$years.'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'_2/', []);
                $html2 = HtmlDomParser::file_get_html('https://www.livefutbol.com/cronica/primera-division-'.$years.'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'_2/', false, null, 0);
                if (!$html2){
                    Log::info('OJO!!! URL ' .'https://www.livefutbol.com/cronica/primera-division-'.$years.'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'/', []);
                    $html2 = HtmlDomParser::file_get_html('https://www.livefutbol.com/cronica/primera-division-'.$years.'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'/', false, null, 0);
                }
                $linkArray=array();
                $entrenadoresArray = array();
                $nombreArbitro ='';
                if (!$html2) {
                    Log::info('OJO!!! URL ' .'https://arg.worldfootball.net/cronica/primera-division-'.$years.'-'.strtr($nombreTorneo, " ", "-").'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'/', []);

                    $html2 = HtmlDomParser::file_get_html('https://arg.worldfootball.net/cronica/primera-division-'.$years.'-'.strtr($nombreTorneo, " ", "-").'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'/', false, null, 0);
                    /*Log::info('OJO!!! URL ' .'https://arg.worldfootball.net/cronica/copa-de-la-superliga-'.$years.'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'/', []);

                    $html2 = HtmlDomParser::file_get_html('https://arg.worldfootball.net/cronica/copa-de-la-superliga-'.$years.'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'/', false, null, 0);*/

                }
                if (!$html2) {
                    Log::info('OJO!!! URL ' .'https://arg.worldfootball.net/cronica/copa-de-la-superliga-'.$years.'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'/', []);

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
                                Log::info('OJO!! gol: '.$jugadorGol,[]);
                                $lineaGol = $td->plaintext;
                                if (str_contains($lineaGol, $jugadorGol)) {
                                    $minutoGol = (int) filter_var($lineaGol, FILTER_SANITIZE_NUMBER_INT);
                                    Log::info('OJO!! min: '.$minutoGol,[]);
                                }

                                $incidenciaArray = explode('/', $lineaGol);
                                if (count($incidenciaArray)>1){
                                    $incidenciaGol = $incidenciaArray[1];
                                    Log::info('OJO!! incidencia: '.$incidenciaGol,[]);
                                }
                            }


                        }
                    }
                    if ($tabla==2){
                        Log::info('OJO!! locales:',[]);
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
                                //Log::info('OJO!! linea: ' . $td->plaintext, []);
                                if (trim($td->plaintext)=='Banquillo') {
                                    $suplentes=1;
                                }
                                if ($td->find('span[class=kleine_schrift]')) {
                                    if ($td->find('span[style=font-weight: bold; color: #646464]')) {
                                        if ($suplentes){
                                            $dorsalSuplenteV= $td->find('span[class=kleine_schrift]')[0]->plaintext;
                                            Log::info('OJO!! dorsal suplente: ' . $dorsalSuplenteV, []);
                                        }
                                        else{
                                            $dorsalTitularL = $td->find('span[class=kleine_schrift]')[0]->plaintext;
                                            Log::info('OJO!! dorsal titular: ' . $dorsalTitularL, []);
                                        }

                                    }
                                    elseif ($td->find('span[class=rottext]')) {
                                        if ($suplentes){
                                            $saleSuplenteV = (int) filter_var($td->find('span[class=rottext]')[0]->plaintext, FILTER_SANITIZE_NUMBER_INT);
                                            Log::info('OJO!! sale suplente: ' . $saleSuplenteV, []);
                                        }
                                        else{
                                            $saleTitularL = (int) filter_var($td->find('span[class=rottext]')[0]->plaintext, FILTER_SANITIZE_NUMBER_INT);
                                            Log::info('OJO!! sale titular: ' . $saleTitularL, []);
                                        }

                                    }
                                    elseif ($td->find('span[class=gruentext]')) {
                                        if ($suplentes){
                                            $entraSuplenteV = (int) filter_var($td->find('span[class=gruentext]')[0]->plaintext, FILTER_SANITIZE_NUMBER_INT);
                                            Log::info('OJO!! entra suplente: ' . $td->find('span[class=gruentext]')[0]->plaintext, []);
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
                                            Log::info('OJO!! amarilla titular: ' . $amarillaSuplenteL, []);
                                        }
                                        else{
                                            $amarillaTitularL = $dorsalTitularL . '-' . (int) filter_var($mintutoTarjetaTitularL, FILTER_SANITIZE_NUMBER_INT);;
                                            Log::info('OJO!! amarilla titular: ' . $amarillaTitularL, []);
                                        }

                                    }
                                    if ($td->find('img')[0]->title == 'Roja directa') {
                                        if ($suplentes){
                                            $rojaSuplenteL = $dorsalSuplenteL . '-' . (int) filter_var($mintutoTarjetaSuplenteL, FILTER_SANITIZE_NUMBER_INT);;
                                            Log::info('OJO!! roja titular: ' . $rojaSuplenteL, []);
                                        }
                                        else{
                                            $rojaTitularL = $dorsalTitularL . '-' . (int) filter_var($mintutoTarjetaTitularL, FILTER_SANITIZE_NUMBER_INT);;
                                            Log::info('OJO!! roja titular: ' . $rojaTitularL, []);
                                        }

                                    }
                                    if ($td->find('img')[0]->title == 'Doble amarilla') {
                                        if ($suplentes){
                                            $dobleamarillaSuplenteL = $dorsalSuplenteL . '-' . (int) filter_var($mintutoTarjetaSuplenteL, FILTER_SANITIZE_NUMBER_INT);;
                                            Log::info('OJO!! dobleamarilla titular: ' . $dobleamarillaSuplenteL, []);
                                        }
                                        else{
                                            $dobleamarillaTitularL = $dorsalTitularL . '-' . (int) filter_var($mintutoTarjetaTitularL, FILTER_SANITIZE_NUMBER_INT);;
                                            Log::info('OJO!! dobleamarilla titular: ' . $dobleamarillaTitularL, []);
                                        }

                                    }

                                }

                                if ($td->find('a')) {
                                    if ($suplentes){
                                        $jugadorSuplenteL = $td->find('a')[0]->title;
                                        Log::info('OJO!! suplente: ' . $jugadorSuplenteL, []);
                                    }
                                    else{
                                        $jugadorTitularL = $td->find('a')[0]->title;
                                        Log::info('OJO!! titular: ' . $jugadorTitularL, []);
                                    }


                                }


                            }
                        }
                    }
                    if ($tabla==3){
                        Log::info('OJO!! visitante:',[]);
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
                                //Log::info('OJO!! linea: ' . $td->plaintext, []);
                                if (trim($td->plaintext)=='Banquillo') {
                                    $suplentes=1;
                                }
                                if ($td->find('span[class=kleine_schrift]')) {
                                    if ($td->find('span[style=font-weight: bold; color: #646464]')) {
                                        if ($suplentes){
                                            $dorsalSuplenteV= $td->find('span[class=kleine_schrift]')[0]->plaintext;
                                            Log::info('OJO!! dorsal suplente: ' . $dorsalSuplenteV, []);
                                        }
                                        else{
                                            $dorsalTitularV = $td->find('span[class=kleine_schrift]')[0]->plaintext;
                                            Log::info('OJO!! dorsal titular: ' . $dorsalTitularV, []);
                                        }

                                    }
                                    elseif ($td->find('span[class=rottext]')) {
                                        if ($suplentes){
                                            $saleSuplenteV = (int) filter_var($td->find('span[class=rottext]')[0]->plaintext, FILTER_SANITIZE_NUMBER_INT);
                                            Log::info('OJO!! sale suplente: ' . $saleSuplenteV, []);
                                        }
                                        else{
                                            $saleTitularV = (int) filter_var($td->find('span[class=rottext]')[0]->plaintext, FILTER_SANITIZE_NUMBER_INT);
                                            Log::info('OJO!! sale titular: ' . $saleTitularV, []);
                                        }

                                    }
                                    elseif ($td->find('span[class=gruentext]')) {
                                        if ($suplentes){
                                            $entraSuplenteV = (int) filter_var($td->find('span[class=gruentext]')[0]->plaintext, FILTER_SANITIZE_NUMBER_INT);
                                            Log::info('OJO!! entra suplente: ' . $entraSuplenteV, []);
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
                                            Log::info('OJO!! amarilla titular: ' . $amarillaSuplenteV, []);
                                        }
                                        else{
                                            $amarillaTitularV = $dorsalTitularV . '-' . (int) filter_var($mintutoTarjetaTitularV, FILTER_SANITIZE_NUMBER_INT);;
                                            Log::info('OJO!! amarilla titular: ' . $amarillaTitularV, []);
                                        }

                                    }
                                    if ($td->find('img')[0]->title == 'Roja directa') {
                                        if ($suplentes){
                                            $rojaSuplenteV = $dorsalSuplenteV . '-' . (int) filter_var($mintutoTarjetaSuplenteV, FILTER_SANITIZE_NUMBER_INT);;
                                            Log::info('OJO!! roja titular: ' . $rojaSuplenteV, []);
                                        }
                                        else{
                                            $rojaTitularV = $dorsalTitularV . '-' . (int) filter_var($mintutoTarjetaTitularV, FILTER_SANITIZE_NUMBER_INT);;
                                            Log::info('OJO!! roja titular: ' . $rojaTitularV, []);
                                        }

                                    }
                                    if ($td->find('img')[0]->title == 'Doble amarilla') {
                                        if ($suplentes){
                                            $dobleamarillaSuplenteV = $dorsalSuplenteV . '-' . (int) filter_var($mintutoTarjetaSuplenteV, FILTER_SANITIZE_NUMBER_INT);;
                                            Log::info('OJO!! dobleamarilla titular: ' . $dobleamarillaSuplenteV, []);
                                        }
                                        else{
                                            $dobleamarillaTitularV = $dorsalTitularV . '-' . (int) filter_var($mintutoTarjetaTitularV, FILTER_SANITIZE_NUMBER_INT);;
                                            Log::info('OJO!! dobleamarilla titular: ' . $dobleamarillaTitularV, []);
                                        }

                                    }

                                }

                                if ($td->find('a')) {
                                    if ($suplentes){
                                        $jugadorSuplenteV = $td->find('a')[0]->title;
                                        Log::info('OJO!! suplente: ' . $jugadorSuplenteV, []);
                                    }
                                    else{
                                        $jugadorTitularV = $td->find('a')[0]->title;
                                        Log::info('OJO!! titular: ' . $jugadorTitularV, []);
                                    }


                                }


                            }
                        }
                    }



                    $tabla ++;




                    $entrenadoresArray = explode('Entrenador:', $element->plaintext);
                    if (count($entrenadoresArray)>3){
                        Log::info('OJO!! varios entrenadores: '.$partido->equipol->nombre.' VS '.$partido->equipov->nombre,[]);
                    }
                    if(count($entrenadoresArray)>1){
                        $dtLocal = $entrenadoresArray[1];
                        //Log::info('DT Local: '.utf8_decode($entrenadoresArray[1]), []);
                        if (isset($entrenadoresArray[2])){
                            $dtVisitante = $entrenadoresArray[2];
                        }
                        else{
                            Log::info('OJO!! Falta Entrenador visitante: '.$partido->equipol->nombre.' VS '.$partido->equipov->nombre,[]);
                        }

                        //Log::info('DT Visitante: '.utf8_decode($entrenadoresArray[2]), []);
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
                                            Log::info('Asistente 1: '.$nombreAsistente, []);
                                            $asistente++;
                                        }
                                        else{
                                            $asistente2= $nombreAsistente;
                                            Log::info('Asistente 2: '.$nombreAsistente, []);
                                            $asistente++;
                                        }

                                    }
                                    else{
                                        $nombreArbitro = '';
                                        for ($i = 1; $i < count($linkArray); $i++) {
                                            $nombreArbitro .= ($linkArray[$i]).' ';
                                        }

                                        Log::info('Arbitro: '.$nombreArbitro, []);

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
                    Log::info('OJO!! Arbitro NO encontrado: '.$nombreArbitro.' '.$partido->equipol->nombre.' VS '.$partido->equipov->nombre,[]);
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
                    Log::info('OJO!! Asistente NO encontrado: '.$asistente1.' '.$partido->equipol->nombre.' VS '.$partido->equipov->nombre,[]);
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
                    Log::info('OJO!! Asistente NO encontrado: '.$asistente2.' '.$partido->equipol->nombre.' VS '.$partido->equipov->nombre,[]);
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
                    Log::info('OJO!!! Técnico NO encontrado : ' . trim($dtLocal),[]);
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
                    Log::info('OJO!!! Técnico NO encontrado : ' . trim($dtVisitante),[]);
                }

            }
            else{
                Log::info('OJO!!! No se econtró la URL2 ' , []);
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
        //Log::info('Entraaaaaa', []);
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
            Log::info('Partido ' .$partido->equipol->nombre.' VS '.$partido->equipov->nombre, []);




            try {
                //Log::info('Partido ' .$partido->equipol->nombre.' VS '.$partido->equipov->nombre, []);
                Log::info('OJO!!! URL ' .'https://www.livefutbol.com/cronica/primera-division-'.$years.'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'/', []);
                $html2 = HtmlDomParser::file_get_html('https://www.livefutbol.com/cronica/primera-division-'.$years.'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'/', false, null, 0);
                /*Log::info('OJO!!! URL ' .'https://arg.worldfootball.net/cronica/copa-de-la-superliga-'.$years.'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'/', []);

                $html2 = HtmlDomParser::file_get_html('https://arg.worldfootball.net/cronica/copa-de-la-superliga-'.$years.'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'/', false, null, 0);*/

                if (!$html2){

                    Log::info('OJO!!! URL ' .'https://www.livefutbol.com/cronica/primera-division-'.$years.'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'_2/', []);
                    $html2 = HtmlDomParser::file_get_html('https://www.livefutbol.com/cronica/primera-division-'.$years.'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'_2/', false, null, 0);
                }
                $linkArray=array();
                $entrenadoresArray = array();
                $nombreArbitro ='';
                if (!$html2) {
                    Log::info('OJO!!! URL ' .'https://arg.worldfootball.net/cronica/primera-division-'.$years.'-'.strtr($nombreTorneo, " ", "-").'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'/', []);

                    $html2 = HtmlDomParser::file_get_html('https://arg.worldfootball.net/cronica/primera-division-'.$years.'-'.strtr($nombreTorneo, " ", "-").'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'/', false, null, 0);
                    /*Log::info('OJO!!! URL ' .'https://arg.worldfootball.net/cronica/copa-de-la-superliga-'.$years.'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'/', []);

                    $html2 = HtmlDomParser::file_get_html('https://arg.worldfootball.net/cronica/copa-de-la-superliga-'.$years.'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'/', false, null, 0);*/

                }
                if (!$html2) {
                    Log::info('OJO!!! URL ' .'https://arg.worldfootball.net/cronica/copa-de-la-superliga-'.$years.'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'/', []);

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
                                //Log::info('OJO!! gol: '.$jugadorGol,[]);
                                $lineaGol = $td->plaintext;
                                if (str_contains($lineaGol, $jugadorGol)) {
                                    //$minutoGol = (int) filter_var($lineaGol, FILTER_SANITIZE_NUMBER_INT);
                                    //Log::info('OJO!! gol: '.$lineaGol,[]);
                                    $arrayGol= explode(".",$lineaGol);

                                    $minutoGol = (int) filter_var($arrayGol[0], FILTER_SANITIZE_NUMBER_INT);
                                    if (count($arrayGol)>1){
                                        $adiccion = (int) filter_var($arrayGol[1], FILTER_SANITIZE_NUMBER_INT);
                                        if ($adiccion>0){
                                            Log::info('OJO!! gol addicion: ');
                                            $minutoGol = $minutoGol + $adiccion;
                                        }

                                    }
                                    //Log::info('OJO!! min: '.$minutoGol,[]);
                                }

                                $incidenciaArray = explode('/', $lineaGol);
                                if (count($incidenciaArray)>1){
                                    $incidenciaGol = $incidenciaArray[1];
                                    //Log::info('OJO!! incidencia: '.$incidenciaGol,[]);
                                }
                                $goles++;
                                $golesArray[]=$jugadorGol.'-'.$minutoGol.'-'.$incidenciaGol;
                            }


                        }
                    }
                    if ($tabla==2){

                        $jugadores = array();
                        //Log::info('OJO!! locales:',[]);
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
                                //Log::info('OJO!! linea: ' . $td->plaintext, []);
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
                                                //Log::info('OJO!! dorsal suplente: ' . $dorsalSuplenteL, []);
                                            } else {
                                                $dorsalTitularL = $td->find('span[class=kleine_schrift]')[0]->plaintext;
                                                //Log::info('OJO!! dorsal titular: ' . $dorsalTitularL, []);
                                            }

                                        } elseif ($td->find('span[class=rottext]')) {
                                            if ($suplentes) {
                                                //$saleSuplenteL = (int)filter_var($td->find('span[class=rottext]')[0]->plaintext, FILTER_SANITIZE_NUMBER_INT);
                                                $arraySale= explode("'",$td->find('span[class=rottext]')[0]->plaintext);
                                                $saleSuplenteV = (int) $arraySale[0];
                                                if (count($arraySale)>1){
                                                    $adiccion = (int) $arraySale[1];
                                                    if ($adiccion>0){
                                                        Log::info('OJO!! cambio addicion: ');
                                                        $saleSuplenteV = $saleSuplenteV + $adiccion;
                                                    }

                                                }
                                                //Log::info('OJO!! sale suplente: ' . $saleSuplenteL, []);
                                            } else {
                                                //$saleTitularL = (int)filter_var($td->find('span[class=rottext]')[0]->plaintext, FILTER_SANITIZE_NUMBER_INT);
                                                $arraySale= explode("'",$td->find('span[class=rottext]')[0]->plaintext);
                                                $saleTitularL = (int) $arraySale[0];
                                                if (count($arraySale)>1){
                                                    $adiccion = (int) $arraySale[1];
                                                    if ($adiccion>0) {
                                                        Log::info('OJO!! cambio addicion: ');
                                                        $saleTitularL = $saleTitularL + $adiccion;
                                                    }
                                                }
                                                //Log::info('OJO!! sale titular: ' . $saleTitularL, []);
                                            }

                                        } elseif ($td->find('span[class=gruentext]')) {
                                            if ($suplentes) {
                                                //$entraSuplenteL = (int)filter_var($td->find('span[class=gruentext]')[0]->plaintext, FILTER_SANITIZE_NUMBER_INT);

                                                $arrayEntra= explode("'",$td->find('span[class=gruentext]')[0]->plaintext);
                                                $entraSuplenteL = (int) $arrayEntra[0];
                                                if (count($arrayEntra)>1){
                                                    $adiccion = (int) $arrayEntra[1];
                                                    if ($adiccion>0) {
                                                        Log::info('OJO!! cambio addicion: ');
                                                        $entraSuplenteL = $entraSuplenteL + $adiccion;
                                                    }
                                                }
                                                //Log::info('OJO!! entra suplente: ' . $entraSuplenteL, []);
                                            }


                                        } else {
                                            if ($td->find('span[class=kleine_schrift]')[0]->title != '') {
                                                if ($suplentes) {
                                                    //$mintutoTarjetaSuplenteL = $td->find('span[class=kleine_schrift]')[0]->plaintext;
                                                    //Log::info('OJO!! tarjeta: ' . $td->find('span[class=kleine_schrift]')[0]->plaintext, []);

                                                    $arrayTarjeta= explode("'",$td->find('span[class=kleine_schrift]')[0]->plaintext);
                                                    $mintutoTarjetaSuplenteL = (int) $arrayTarjeta[0];
                                                    if (count($arrayTarjeta)>1){
                                                        $adiccion = (int) $arrayTarjeta[1];
                                                        if ($adiccion>0){
                                                            Log::info('OJO!! tarjeta addicion: ');
                                                            $mintutoTarjetaSuplenteL = $mintutoTarjetaSuplenteL + $adiccion;
                                                        }

                                                    }
                                                } else {
                                                    //$mintutoTarjetaTitularL = $td->find('span[class=kleine_schrift]')[0]->plaintext;
                                                    //Log::info('OJO!! tarjeta: ' . $td->find('span[class=kleine_schrift]')[0]->plaintext, []);
                                                    $arrayTarjeta= explode("'",$td->find('span[class=kleine_schrift]')[0]->plaintext);
                                                    $mintutoTarjetaTitularL = (int) $arrayTarjeta[0];
                                                    if (count($arrayTarjeta)>1){
                                                        $adiccion = (int) $arrayTarjeta[1];
                                                        if ($adiccion>0) {
                                                            Log::info('OJO!! tarjeta addicion: ');
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
                                                //Log::info('OJO!! amarilla suplente: ' . $amarillaSuplenteL, []);
                                            } else {
                                                $amarillaTitularL = (int)filter_var($mintutoTarjetaTitularL, FILTER_SANITIZE_NUMBER_INT);;
                                                //Log::info('OJO!! amarilla titular: ' . $amarillaTitularL, []);
                                            }

                                        }
                                        if ($td->find('img')[0]->title == 'Roja directa') {
                                            if ($suplentes) {
                                                $rojaSuplenteL = (int)filter_var($mintutoTarjetaSuplenteL, FILTER_SANITIZE_NUMBER_INT);;
                                                //Log::info('OJO!! roja suplente: ' . $rojaSuplenteL, []);
                                            } else {
                                                $rojaTitularL = (int)filter_var($mintutoTarjetaTitularL, FILTER_SANITIZE_NUMBER_INT);
                                                //Log::info('OJO!! roja titular: ' . $rojaTitularL, []);
                                            }

                                        }
                                        if ($td->find('img')[0]->title == 'Doble amarilla') {
                                            if ($suplentes) {
                                                $dobleamarillaSuplenteL = (int)filter_var($mintutoTarjetaSuplenteL, FILTER_SANITIZE_NUMBER_INT);;
                                                //Log::info('OJO!! dobleamarilla suplente: ' . $dobleamarillaSuplenteL, []);
                                            } else {
                                                $dobleamarillaTitularL = (int)filter_var($mintutoTarjetaTitularL, FILTER_SANITIZE_NUMBER_INT);;
                                                //Log::info('OJO!! dobleamarilla titular: ' . $dobleamarillaTitularL, []);
                                            }

                                        }

                                    }

                                    if ($td->find('a')) {
                                        if ($suplentes) {
                                            $jugadorSuplenteL = $td->find('a')[0]->title;
                                            //Log::info('OJO!! suplente: ' . $jugadorSuplenteL, []);
                                        } else {
                                            $jugadorTitularL = $td->find('a')[0]->title;
                                            //Log::info('OJO!! titular: ' . $jugadorTitularL, []);
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
                                            //Log::info('OJO!! comparar goles: ' . trim($jugadorTitularL).'=='.trim($jugador).' - '.$golmin, []);
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
                        //Log::info('OJO!! visitante:',[]);
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
                                ////Log::info('OJO!! linea: ' . $td->plaintext, []);
                                if (trim($td->plaintext)=='Banquillo') {
                                    $suplentes=1;
                                }
                                if ($td->find('span[class=kleine_schrift]')) {
                                    if ($td->find('span[style=font-weight: bold; color: #646464]')) {
                                        if ($suplentes){
                                            $dorsalSuplenteV= $td->find('span[class=kleine_schrift]')[0]->plaintext;
                                            //Log::info('OJO!! dorsal suplente: ' . $dorsalSuplenteV, []);
                                        }
                                        else{
                                            $dorsalTitularV = $td->find('span[class=kleine_schrift]')[0]->plaintext;
                                            //Log::info('OJO!! dorsal titular: ' . $dorsalTitularV, []);
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
                                                    Log::info('OJO!! cambio addicion: ');
                                                    $saleSuplenteV = $saleSuplenteV + $adiccion;
                                                }
                                            }
                                            //Log::info('OJO!! sale suplente: ' . $saleSuplenteV, []);
                                        }
                                        else{
                                            //$saleTitularV = (int) filter_var($td->find('span[class=rottext]')[0]->plaintext, FILTER_SANITIZE_NUMBER_INT);


                                            $arraySale= explode("'",$td->find('span[class=rottext]')[0]->plaintext);
                                            $saleTitularV = (int) $arraySale[0];
                                            if (count($arraySale)>1){
                                                $adiccion = (int) $arraySale[1];
                                                if ($adiccion>0) {
                                                    Log::info('OJO!! cambio addicion: ');
                                                    $saleTitularV = $saleTitularV + $adiccion;
                                                }
                                            }
                                            //Log::info('OJO!! sale titular: ' . $saleTitularV, []);
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
                                                    Log::info('OJO!! cambio addicion: ');
                                                    $entraSuplenteV = $entraSuplenteV + $adiccion;
                                                }
                                            }
                                            //Log::info('OJO!! entra suplente: ' . $entraSuplenteV, []);
                                        }


                                    }
                                    else{
                                        if ($td->find('span[class=kleine_schrift]')[0]->title !=''){
                                            if ($suplentes){
                                                //$mintutoTarjetaSuplenteV = $td->find('span[class=kleine_schrift]')[0]->plaintext;
                                                //Log::info('OJO!! tarjeta: ' . $td->find('span[class=kleine_schrift]')[0]->plaintext, []);
                                                $arrayTarjeta= explode("'",$td->find('span[class=kleine_schrift]')[0]->plaintext);
                                                $mintutoTarjetaSuplenteV = (int) $arrayTarjeta[0];
                                                if (count($arrayTarjeta)>1){
                                                    $adiccion = (int) $arrayTarjeta[1];
                                                    if ($adiccion>0) {
                                                        Log::info('OJO!! tarjeta addicion: ');
                                                        $mintutoTarjetaSuplenteV = $mintutoTarjetaSuplenteV + $adiccion;
                                                    }
                                                }
                                            }
                                            else{
                                                //$mintutoTarjetaTitularV = $td->find('span[class=kleine_schrift]')[0]->plaintext;
                                                //Log::info('OJO!! tarjeta: ' . $td->find('span[class=kleine_schrift]')[0]->plaintext, []);
                                                $arrayTarjeta= explode("'",$td->find('span[class=kleine_schrift]')[0]->plaintext);
                                                $mintutoTarjetaTitularV = (int) $arrayTarjeta[0];
                                                if (count($arrayTarjeta)>1){
                                                    $adiccion = (int) $arrayTarjeta[1];
                                                    if ($adiccion>0) {
                                                        Log::info('OJO!! tarjeta addicion: ');
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
                                            //Log::info('OJO!! amarilla suplente: ' . $mintutoTarjetaSuplenteV, []);
                                        }
                                        else{
                                            $amarillaTitularV = (int) filter_var($mintutoTarjetaTitularV, FILTER_SANITIZE_NUMBER_INT);;
                                            //Log::info('OJO!! amarilla titular: ' . $amarillaTitularV, []);
                                        }

                                    }
                                    if ($td->find('img')[0]->title == 'Roja directa') {
                                        if ($suplentes){
                                            $rojaSuplenteV =  (int) filter_var($mintutoTarjetaSuplenteV, FILTER_SANITIZE_NUMBER_INT);;
                                            //Log::info('OJO!! roja titular: ' . $rojaSuplenteV, []);
                                        }
                                        else{
                                            $rojaTitularV =  (int) filter_var($mintutoTarjetaTitularV, FILTER_SANITIZE_NUMBER_INT);;
                                            //Log::info('OJO!! roja titular: ' . $rojaTitularV, []);
                                        }

                                    }
                                    if ($td->find('img')[0]->title == 'Doble amarilla') {
                                        if ($suplentes){
                                            $dobleamarillaSuplenteV = (int) filter_var($mintutoTarjetaSuplenteV, FILTER_SANITIZE_NUMBER_INT);;
                                            //Log::info('OJO!! dobleamarilla titular: ' . $dobleamarillaSuplenteV, []);
                                        }
                                        else{
                                            $dobleamarillaTitularV = (int) filter_var($mintutoTarjetaTitularV, FILTER_SANITIZE_NUMBER_INT);;
                                            //Log::info('OJO!! dobleamarilla titular: ' . $dobleamarillaTitularV, []);
                                        }

                                    }

                                }

                                if ($td->find('a')) {
                                    if ($suplentes){
                                        $jugadorSuplenteV = $td->find('a')[0]->title;
                                        //Log::info('OJO!! suplente: ' . $jugadorSuplenteV, []);
                                    }
                                    else{
                                        $jugadorTitularV = $td->find('a')[0]->title;
                                        //Log::info('OJO!! titular: ' . $jugadorTitularV, []);
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
                                        //Log::info('OJO!! comparar goles: ' . trim($jugador).' - '.$golmin, []);
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
                        Log::info('OJO!! varios entrenadores: '.$partido->equipol->nombre.' VS '.$partido->equipov->nombre,[]);
                    }
                    if(count($entrenadoresArray)>1){
                        $dtLocal = $entrenadoresArray[1];
                        //Log::info('DT Local: '.utf8_decode($entrenadoresArray[1]), []);
                        if (isset($entrenadoresArray[2])){
                            $dtVisitante = $entrenadoresArray[2];
                        }
                        else{
                            Log::info('OJO!! Falta Entrenador visitante: '.$partido->equipol->nombre.' VS '.$partido->equipov->nombre,[]);
                        }

                        //Log::info('DT Visitante: '.utf8_decode($entrenadoresArray[2]), []);
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
                                            Log::info('Asistente 1: '.$nombreAsistente, []);
                                            $asistente++;
                                        }
                                        else{
                                            $asistente2= $nombreAsistente;
                                            Log::info('Asistente 2: '.$nombreAsistente, []);
                                            $asistente++;
                                        }

                                    }
                                    else{
                                        $nombreArbitro = '';
                                        for ($i = 1; $i < count($linkArray); $i++) {
                                            $nombreArbitro .= ($linkArray[$i]).' ';
                                        }

                                        Log::info('Arbitro: '.$nombreArbitro, []);

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
                    Log::info('OJO!! Arbitro NO encontrado: '.$nombreArbitro.' '.$partido->equipol->nombre.' VS '.$partido->equipov->nombre,[]);
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
                    Log::info('OJO!! Asistente NO encontrado: '.$asistente1.' '.$partido->equipol->nombre.' VS '.$partido->equipov->nombre,[]);
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
                    Log::info('OJO!! Asistente NO encontrado: '.$asistente2.' '.$partido->equipol->nombre.' VS '.$partido->equipov->nombre,[]);
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
                    Log::info('OJO!!! Técnico NO encontrado : ' . trim($dtLocal),[]);
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
                    Log::info('OJO!!! Técnico NO encontrado : ' . trim($dtVisitante),[]);
                }
                if (($golesL!=$golesLocales)||($golesV!=$golesVisitantes)) {
                    Log::info('OJO!!! No coincide la cantidad de goles en: ' . $partido->equipol->nombre . ' VS ' . $partido->equipov->nombre . ' -> ' . $golesL.' a '. $golesV. ' - ' . $golesLocales. ' a '.$golesVisitantes, []);
                }
                foreach ($equipos as $eq) {
                    Log::info('Equipo  ' . $eq['equipo'], []);
                    $strEquipo=trim($eq['equipo']);
                    $equipo=Equipo::where('nombre','like',"%$strEquipo%")->first();
                    if (!empty($equipo)){
                        foreach ($eq['jugadores'] as $jugador) {
                            Log::info('Jugador: ' . $jugador['dorsal'] . ' ' . $jugador['nombre'] . ' del equipo ' . $strEquipo, []);
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
                                    Log::info('OJO!!! con el jugador no está en la plantilla del equipo ' . $strEquipo, []);
                                }
                            }
                            else{
                                $plantillaJugador='';
                                Log::info('OJO!!! No hay plantilla del equipo ' . $strEquipo, []);
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
                                    //Log::info('Incidencias Jugador: ' . $jugador['dorsal'] . ' ' . $jugador['nombre'] . ' - '. trim($incidencia[0]) . ' MIN: ' . intval(trim($incidencia[1])), []);
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
                        Log::info('OJO!!! NO se encontró al equipo: ' . $strEquipo,[]);
                    }
                }
            }
            else{
                Log::info('OJO!!! No se econtró la URL2 ' , []);
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

    public function importgolesfecha(Request $request)
    {
        set_time_limit(0);
        //Log::info('Entraaaaaa', []);

        $grupo_id = $request->get('grupoId');

        $grupo=Grupo::findOrFail($grupo_id);


        $fechas=Fecha::where('grupo_id','=',"$grupo_id")->orderBy('numero','ASC')->get();

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
                Log::info('Partido ' . $partido->equipol->nombre . ' VS ' . $partido->equipov->nombre, []);

                $goles=Gol::where('partido_id','=',"$partido->id")->orderBy('minuto','ASC')->get();
                foreach ($goles as $gol) {
                    Log::info('Gol ' . $gol->jugador->persona->nombre.' - '.$gol->jugador->persona->apellido.' - '.$gol->tipo.' - '.$gol->minuto, []);
                    if ($gol->tipo=='Jugada'){
                        $nombre = explode(' ',$gol->jugador->persona->nombre)[0];
                        $apellido = explode(' ',$gol->jugador->persona->apellido)[0];
                        Log::info('Url - http://www.futbol360.com.ar/jugadores/argentina/' . $apellido.'-'.$nombre, []);
                    }
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

    public function importgolesfecha_old(Request $request)
    {
        set_time_limit(0);
        //Log::info('Entraaaaaa', []);

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
                Log::info('Partido ' .$partido->equipol->nombre.' VS '.$partido->equipov->nombre, []);




                try {
                    //Log::info('Partido ' .$partido->equipol->nombre.' VS '.$partido->equipov->nombre, []);

                    /*Log::info('OJO!!! URL ' .'https://www.livefutbol.com/cronica/primera-division-'.$years.'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'/', []);*/
                    /*html2 = HtmlDomParser::file_get_html('https://www.livefutbol.com/cronica/primera-division-'.$years.'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'/', false, null, 0);*/
                    Log::info('OJO!!! URL ' .'http://www.futbol360.com.ar/partidos/argentina/torneo-'.strtolower($grupo->torneo->nombre).'-'.$year.'/'.intval($fecha->numero).'-fecha/'.$this->dameNombreEquipoURL3($strLocal).'-'.$this->dameNombreEquipoURL3($strVisitante).'/inc/partido-'.$this->dameNombreEquipoURL3($strLocal).'-'.$this->dameNombreEquipoURL3($strVisitante).'-'.date('d-m-Y', strtotime($partido->dia)).'.php.inc', []);

                    $html2 = HtmlDomParser::file_get_html('http://www.futbol360.com.ar/partidos/argentina/torneo-'.strtolower($grupo->torneo->nombre).'-'.$year.'/'.intval($fecha->numero).'-fecha/'.$this->dameNombreEquipoURL3($strLocal).'-'.$this->dameNombreEquipoURL3($strVisitante).'/inc/partido-'.$this->dameNombreEquipoURL3($strLocal).'-'.$this->dameNombreEquipoURL3($strVisitante).'-'.date('d-m-Y', strtotime($partido->dia)).'.php.inc', false, null, 0);

                    //Log::info('OJO!! error: '.$html2,[]);
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
                            //Log::info('OJO!! gol: '.$tr->plaintext,[]);

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
                                        Log::info('OJO!! penal: ' . $jugadorArr[count($jugadorArr) - 2], []);
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
                                            // Log::info('OJO!! img: ' . $arrImg[$i], []);
                                            if (str_contains($arrImg[$i], 'iconMatchPenalty.gif')) {

                                                $minuto = str_replace('&#160', '', $arrImgText[$i]);
                                                if (str_contains($minuto, 'st')) {
                                                    $minuto = intval($minuto) + 45;
                                                } else {
                                                    $minuto = intval($minuto);
                                                }

                                                Log::info('OJO!! minuto: ' . $minuto, []);
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
                                                    Log::info('OJO!! no se encontro a : ' . $jugadorGol, []);
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
                                Log::info('OJO!! no estaba: ' . $goles['partido_id'].' - '.$goles['jugador_id'].' - '.$goles['minuto'], []);
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
                    Log::info('OJO!!! No se econtró la URL2 ' , []);
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


        $arbitros=PartidoArbitro::where('partido_id','=',"$partido_id")->get();

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
        //Log::info('Entraaaaaa', []);
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
                    Log::info('Partido ' .$partido->equipol->nombre.' VS '.$partido->equipov->nombre, []);
                    Log::info('URL ' .$url, []);
                    $html = HtmlDomParser::file_get_html($url, false, null, 0);
                }




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
                                    Log::info('OJO!!! con el jugador no está en la plantilla del equipo ' . $strEquipo, []);
                                }
                            }
                            else{
                                $plantillaJugador='';
                                Log::info('OJO!!! No hay plantilla del equipo ' . $strEquipo, []);
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
            /*else{
                $error = 'No se econtró la URL del partido: '.$partido->equipol->nombre.' VS '.$partido->equipov->nombre;
                $ok=0;
                //continue;
            }*/
            $html2='';
            try {
                if ($url2){
                    Log::info('Partido ' .$partido->equipol->nombre.' VS '.$partido->equipov->nombre, []);

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
                                //Log::info('OJO!! gol: '.$jugadorGol,[]);
                                $lineaGol = $td->plaintext;
                                if (str_contains($lineaGol, $jugadorGol)) {
                                    //$minutoGol = (int) filter_var($lineaGol, FILTER_SANITIZE_NUMBER_INT);
                                    //Log::info('OJO!! gol: '.$lineaGol,[]);
                                    $arrayGol= explode(".",$lineaGol);

                                    $minutoGol = (int) filter_var($arrayGol[0], FILTER_SANITIZE_NUMBER_INT);
                                    if (count($arrayGol)>1){
                                        $adiccion = (int) filter_var($arrayGol[1], FILTER_SANITIZE_NUMBER_INT);
                                        if ($adiccion>0){
                                            Log::info('OJO!! gol addicion: ');
                                            $minutoGol = $minutoGol + $adiccion;
                                        }

                                    }
                                    //Log::info('OJO!! min: '.$minutoGol,[]);
                                }

                                $incidenciaArray = explode('/', $lineaGol);
                                if (count($incidenciaArray)>1){
                                    $incidenciaGol = $incidenciaArray[1];
                                    //Log::info('OJO!! incidencia: '.$incidenciaGol,[]);
                                }
                                $goles++;
                                $golesArray[]=$jugadorGol.'-'.$minutoGol.'-'.$incidenciaGol;
                            }


                        }
                    }
                    if ($tabla==2){

                        $jugadores = array();
                        //Log::info('OJO!! locales:',[]);
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
                                //Log::info('OJO!! linea: ' . $td->plaintext, []);
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
                                                //Log::info('OJO!! dorsal suplente: ' . $dorsalSuplenteL, []);
                                            } else {
                                                $dorsalTitularL = $td->find('span[class=kleine_schrift]')[0]->plaintext;
                                                //Log::info('OJO!! dorsal titular: ' . $dorsalTitularL, []);
                                            }

                                        } elseif ($td->find('span[class=rottext]')) {
                                            if ($suplentes) {
                                                //$saleSuplenteL = (int)filter_var($td->find('span[class=rottext]')[0]->plaintext, FILTER_SANITIZE_NUMBER_INT);
                                                $arraySale= explode("'",$td->find('span[class=rottext]')[0]->plaintext);
                                                $saleSuplenteV = (int) $arraySale[0];
                                                if (count($arraySale)>1){
                                                    $adiccion = (int) $arraySale[1];
                                                    if ($adiccion>0){
                                                        Log::info('OJO!! cambio addicion: ');
                                                        $saleSuplenteV = $saleSuplenteV + $adiccion;
                                                    }

                                                }
                                                //Log::info('OJO!! sale suplente: ' . $saleSuplenteL, []);
                                            } else {
                                                //$saleTitularL = (int)filter_var($td->find('span[class=rottext]')[0]->plaintext, FILTER_SANITIZE_NUMBER_INT);
                                                $arraySale= explode("'",$td->find('span[class=rottext]')[0]->plaintext);
                                                $saleTitularL = (int) $arraySale[0];
                                                if (count($arraySale)>1){
                                                    $adiccion = (int) $arraySale[1];
                                                    if ($adiccion>0) {
                                                        Log::info('OJO!! cambio addicion: ');
                                                        $saleTitularL = $saleTitularL + $adiccion;
                                                    }
                                                }
                                                //Log::info('OJO!! sale titular: ' . $saleTitularL, []);
                                            }

                                        } elseif ($td->find('span[class=gruentext]')) {
                                            if ($suplentes) {
                                                //$entraSuplenteL = (int)filter_var($td->find('span[class=gruentext]')[0]->plaintext, FILTER_SANITIZE_NUMBER_INT);

                                                $arrayEntra= explode("'",$td->find('span[class=gruentext]')[0]->plaintext);
                                                $entraSuplenteL = (int) $arrayEntra[0];
                                                if (count($arrayEntra)>1){
                                                    $adiccion = (int) $arrayEntra[1];
                                                    if ($adiccion>0) {
                                                        Log::info('OJO!! cambio addicion: ');
                                                        $entraSuplenteL = $entraSuplenteL + $adiccion;
                                                    }
                                                }
                                                //Log::info('OJO!! entra suplente: ' . $entraSuplenteL, []);
                                            }


                                        } else {
                                            if ($td->find('span[class=kleine_schrift]')[0]->title != '') {
                                                if ($suplentes) {
                                                    //$mintutoTarjetaSuplenteL = $td->find('span[class=kleine_schrift]')[0]->plaintext;
                                                    //Log::info('OJO!! tarjeta: ' . $td->find('span[class=kleine_schrift]')[0]->plaintext, []);

                                                    $arrayTarjeta= explode("'",$td->find('span[class=kleine_schrift]')[0]->plaintext);
                                                    $mintutoTarjetaSuplenteL = (int) $arrayTarjeta[0];
                                                    if (count($arrayTarjeta)>1){
                                                        $adiccion = (int) $arrayTarjeta[1];
                                                        if ($adiccion>0){
                                                            Log::info('OJO!! tarjeta addicion: ');
                                                            $mintutoTarjetaSuplenteL = $mintutoTarjetaSuplenteL + $adiccion;
                                                        }

                                                    }
                                                } else {
                                                    //$mintutoTarjetaTitularL = $td->find('span[class=kleine_schrift]')[0]->plaintext;
                                                    //Log::info('OJO!! tarjeta: ' . $td->find('span[class=kleine_schrift]')[0]->plaintext, []);
                                                    $arrayTarjeta= explode("'",$td->find('span[class=kleine_schrift]')[0]->plaintext);
                                                    $mintutoTarjetaTitularL = (int) $arrayTarjeta[0];
                                                    if (count($arrayTarjeta)>1){
                                                        $adiccion = (int) $arrayTarjeta[1];
                                                        if ($adiccion>0) {
                                                            Log::info('OJO!! tarjeta addicion: ');
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
                                                //Log::info('OJO!! amarilla suplente: ' . $amarillaSuplenteL, []);
                                            } else {
                                                $amarillaTitularL = (int)filter_var($mintutoTarjetaTitularL, FILTER_SANITIZE_NUMBER_INT);;
                                                //Log::info('OJO!! amarilla titular: ' . $amarillaTitularL, []);
                                            }

                                        }
                                        if ($td->find('img')[0]->title == 'Roja directa') {
                                            if ($suplentes) {
                                                $rojaSuplenteL = (int)filter_var($mintutoTarjetaSuplenteL, FILTER_SANITIZE_NUMBER_INT);;
                                                //Log::info('OJO!! roja suplente: ' . $rojaSuplenteL, []);
                                            } else {
                                                $rojaTitularL = (int)filter_var($mintutoTarjetaTitularL, FILTER_SANITIZE_NUMBER_INT);
                                                //Log::info('OJO!! roja titular: ' . $rojaTitularL, []);
                                            }

                                        }
                                        if ($td->find('img')[0]->title == 'Doble amarilla') {
                                            if ($suplentes) {
                                                $dobleamarillaSuplenteL = (int)filter_var($mintutoTarjetaSuplenteL, FILTER_SANITIZE_NUMBER_INT);;
                                                //Log::info('OJO!! dobleamarilla suplente: ' . $dobleamarillaSuplenteL, []);
                                            } else {
                                                $dobleamarillaTitularL = (int)filter_var($mintutoTarjetaTitularL, FILTER_SANITIZE_NUMBER_INT);;
                                                //Log::info('OJO!! dobleamarilla titular: ' . $dobleamarillaTitularL, []);
                                            }

                                        }

                                    }

                                    if ($td->find('a')) {
                                        if ($suplentes) {
                                            $jugadorSuplenteL = $td->find('a')[0]->title;
                                            //Log::info('OJO!! suplente: ' . $jugadorSuplenteL, []);
                                        } else {
                                            $jugadorTitularL = $td->find('a')[0]->title;
                                            //Log::info('OJO!! titular: ' . $jugadorTitularL, []);
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
                                            //Log::info('OJO!! comparar goles: ' . trim($jugadorTitularL).'=='.trim($jugador).' - '.$golmin, []);
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
                        //Log::info('OJO!! visitante:',[]);
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
                                ////Log::info('OJO!! linea: ' . $td->plaintext, []);
                                if (trim($td->plaintext)=='Banquillo') {
                                    $suplentes=1;
                                }
                                if ($td->find('span[class=kleine_schrift]')) {
                                    if ($td->find('span[style=font-weight: bold; color: #646464]')) {
                                        if ($suplentes){
                                            $dorsalSuplenteV= $td->find('span[class=kleine_schrift]')[0]->plaintext;
                                            //Log::info('OJO!! dorsal suplente: ' . $dorsalSuplenteV, []);
                                        }
                                        else{
                                            $dorsalTitularV = $td->find('span[class=kleine_schrift]')[0]->plaintext;
                                            //Log::info('OJO!! dorsal titular: ' . $dorsalTitularV, []);
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
                                                    Log::info('OJO!! cambio addicion: ');
                                                    $saleSuplenteV = $saleSuplenteV + $adiccion;
                                                }
                                            }
                                            //Log::info('OJO!! sale suplente: ' . $saleSuplenteV, []);
                                        }
                                        else{
                                            //$saleTitularV = (int) filter_var($td->find('span[class=rottext]')[0]->plaintext, FILTER_SANITIZE_NUMBER_INT);


                                            $arraySale= explode("'",$td->find('span[class=rottext]')[0]->plaintext);
                                            $saleTitularV = (int) $arraySale[0];
                                            if (count($arraySale)>1){
                                                $adiccion = (int) $arraySale[1];
                                                if ($adiccion>0) {
                                                    Log::info('OJO!! cambio addicion: ');
                                                    $saleTitularV = $saleTitularV + $adiccion;
                                                }
                                            }
                                            //Log::info('OJO!! sale titular: ' . $saleTitularV, []);
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
                                                    Log::info('OJO!! cambio addicion: ');
                                                    $entraSuplenteV = $entraSuplenteV + $adiccion;
                                                }
                                            }
                                            //Log::info('OJO!! entra suplente: ' . $entraSuplenteV, []);
                                        }


                                    }
                                    else{
                                        if ($td->find('span[class=kleine_schrift]')[0]->title !=''){
                                            if ($suplentes){
                                                //$mintutoTarjetaSuplenteV = $td->find('span[class=kleine_schrift]')[0]->plaintext;
                                                //Log::info('OJO!! tarjeta: ' . $td->find('span[class=kleine_schrift]')[0]->plaintext, []);
                                                $arrayTarjeta= explode("'",$td->find('span[class=kleine_schrift]')[0]->plaintext);
                                                $mintutoTarjetaSuplenteV = (int) $arrayTarjeta[0];
                                                if (count($arrayTarjeta)>1){
                                                    $adiccion = (int) $arrayTarjeta[1];
                                                    if ($adiccion>0) {
                                                        Log::info('OJO!! tarjeta addicion: ');
                                                        $mintutoTarjetaSuplenteV = $mintutoTarjetaSuplenteV + $adiccion;
                                                    }
                                                }
                                            }
                                            else{
                                                //$mintutoTarjetaTitularV = $td->find('span[class=kleine_schrift]')[0]->plaintext;
                                                //Log::info('OJO!! tarjeta: ' . $td->find('span[class=kleine_schrift]')[0]->plaintext, []);
                                                $arrayTarjeta= explode("'",$td->find('span[class=kleine_schrift]')[0]->plaintext);
                                                $mintutoTarjetaTitularV = (int) $arrayTarjeta[0];
                                                if (count($arrayTarjeta)>1){
                                                    $adiccion = (int) $arrayTarjeta[1];
                                                    if ($adiccion>0) {
                                                        Log::info('OJO!! tarjeta addicion: ');
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
                                            //Log::info('OJO!! amarilla suplente: ' . $mintutoTarjetaSuplenteV, []);
                                        }
                                        else{
                                            $amarillaTitularV = (int) filter_var($mintutoTarjetaTitularV, FILTER_SANITIZE_NUMBER_INT);;
                                            //Log::info('OJO!! amarilla titular: ' . $amarillaTitularV, []);
                                        }

                                    }
                                    if ($td->find('img')[0]->title == 'Roja directa') {
                                        if ($suplentes){
                                            $rojaSuplenteV =  (int) filter_var($mintutoTarjetaSuplenteV, FILTER_SANITIZE_NUMBER_INT);;
                                            //Log::info('OJO!! roja titular: ' . $rojaSuplenteV, []);
                                        }
                                        else{
                                            $rojaTitularV =  (int) filter_var($mintutoTarjetaTitularV, FILTER_SANITIZE_NUMBER_INT);;
                                            //Log::info('OJO!! roja titular: ' . $rojaTitularV, []);
                                        }

                                    }
                                    if ($td->find('img')[0]->title == 'Doble amarilla') {
                                        if ($suplentes){
                                            $dobleamarillaSuplenteV = (int) filter_var($mintutoTarjetaSuplenteV, FILTER_SANITIZE_NUMBER_INT);;
                                            //Log::info('OJO!! dobleamarilla titular: ' . $dobleamarillaSuplenteV, []);
                                        }
                                        else{
                                            $dobleamarillaTitularV = (int) filter_var($mintutoTarjetaTitularV, FILTER_SANITIZE_NUMBER_INT);;
                                            //Log::info('OJO!! dobleamarilla titular: ' . $dobleamarillaTitularV, []);
                                        }

                                    }

                                }

                                if ($td->find('a')) {
                                    if ($suplentes){
                                        $jugadorSuplenteV = $td->find('a')[0]->title;
                                        //Log::info('OJO!! suplente: ' . $jugadorSuplenteV, []);
                                    }
                                    else{
                                        $jugadorTitularV = $td->find('a')[0]->title;
                                        //Log::info('OJO!! titular: ' . $jugadorTitularV, []);
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
                                        //Log::info('OJO!! comparar goles: ' . trim($jugador).' - '.$golmin, []);
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
                        Log::info('OJO!! varios entrenadores: '.$partido->equipol->nombre.' VS '.$partido->equipov->nombre,[]);
                    }
                    if(count($entrenadoresArray)>1){
                        $dtLocal = $entrenadoresArray[1];
                        //Log::info('DT Local: '.utf8_decode($entrenadoresArray[1]), []);
                        if (isset($entrenadoresArray[2])){
                            $dtVisitante = $entrenadoresArray[2];
                        }
                        else{
                            Log::info('OJO!! Falta Entrenador visitante: '.$partido->equipol->nombre.' VS '.$partido->equipov->nombre,[]);
                        }

                        //Log::info('DT Visitante: '.utf8_decode($entrenadoresArray[2]), []);
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
                                            Log::info('Asistente 1: '.$nombreAsistente, []);
                                            $asistente++;
                                        }
                                        else{
                                            $asistente2= $nombreAsistente;
                                            Log::info('Asistente 2: '.$nombreAsistente, []);
                                            $asistente++;
                                        }

                                    }
                                    else{
                                        $nombreArbitro = '';
                                        for ($i = 1; $i < count($linkArray); $i++) {
                                            $nombreArbitro .= ($linkArray[$i]).' ';
                                        }

                                        Log::info('Arbitro: '.$nombreArbitro, []);

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
                    Log::info('OJO!! Arbitro NO encontrado: ' . $nombreArbitro . ' ' . $partido->equipol->nombre . ' VS ' . $partido->equipov->nombre, []);
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
                    Log::info('OJO!! Asistente NO encontrado: ' . $asistente1 . ' ' . $partido->equipol->nombre . ' VS ' . $partido->equipov->nombre, []);
                } else {
                    $data3 = array(
                        'partido_id' => $partido->id,
                        'arbitro_id' => $arbitro->id,
                        'tipo' => 'Linea 1'
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
                $arrArbitro = explode(' ', $asistente2);
                if (count($arrArbitro) > 1) {
                    //$arbitro2 = Arbitro::where('nombre', 'like', "%$arrArbitro[0]%")->where('apellido', 'like', "%$arrArbitro[1]%")->first();
                    $arbitro2=Arbitro::SELECT('arbitros.*')->Join('personas','personas.id','=','arbitros.persona_id')->where('nombre', 'like', "%$arrArbitro[0]%")->where('apellido', 'like', "%$arrArbitro[1]%")->first();
                }

                if (!$arbitro2) {
                    Log::info('OJO!! Asistente NO encontrado: ' . $asistente2 . ' ' . $partido->equipol->nombre . ' VS ' . $partido->equipov->nombre, []);
                } else {
                    $data3 = array(
                        'partido_id' => $partido->id,
                        'arbitro_id' => $arbitro->id,
                        'tipo' => 'Linea 2'
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
                    Log::info('OJO!!! Técnico NO encontrado : ' . trim($dtLocal),[]);
                }
                $strEntrenador=trim($dtVisitante);
                $arrEntrenador = explode(' ', $strEntrenador);
                //$entrenadorV=Tecnico::where('nombre','like',"%$arrEntrenador[0]%")->where('apellido','like',"%$arrEntrenador[1]%")->first();
                $entrenadorV=Tecnico::SELECT('tecnicos.*')->Join('personas','personas.id','=','tecnicos.persona_id')->where('nombre','like',"%$arrEntrenador[0]%")->where('apellido','like',"%$arrEntrenador[1]%")->first();
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
                    Log::info('OJO!!! Técnico NO encontrado : ' . trim($dtVisitante),[]);
                }
                $golesTotales = $partido->golesl+$partido->golesv;
                $golesLocales = $partido->golesl;
                $golesVisitantes = $partido->golesv;
                if (($golesL!=$golesLocales)||($golesV!=$golesVisitantes)) {
                    Log::info('OJO!!! No coincide la cantidad de goles en: ' . $partido->equipol->nombre . ' VS ' . $partido->equipov->nombre . ' -> ' . $golesL.' a '. $golesV. ' - ' . $golesLocales. ' a '.$golesVisitantes, []);
                }
                foreach ($equipos as $eq) {
                    Log::info('Equipo  ' . $eq['equipo'], []);
                    $strEquipo=trim($eq['equipo']);
                    $equipo=Equipo::where('nombre','like',"%$strEquipo%")->first();
                    if (!empty($equipo)){
                        foreach ($eq['jugadores'] as $jugador) {
                            Log::info('Jugador: ' . $jugador['dorsal'] . ' ' . $jugador['nombre'] . ' del equipo ' . $strEquipo, []);
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
                                    Log::info('OJO!!! con el jugador no está en la plantilla del equipo ' . $strEquipo, []);
                                }
                            }
                            else{
                                $plantillaJugador='';
                                Log::info('OJO!!! No hay plantilla del equipo ' . $strEquipo, []);
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
                                    //Log::info('Incidencias Jugador: ' . $jugador['dorsal'] . ' ' . $jugador['nombre'] . ' - '. trim($incidencia[0]) . ' MIN: ' . intval(trim($incidencia[1])), []);
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
                        Log::info('OJO!!! NO se encontró al equipo: ' . $strEquipo,[]);
                    }
                }

            }
            else{
                Log::info('OJO!!! No se econtró la URL2 ' .$url2, []);
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
