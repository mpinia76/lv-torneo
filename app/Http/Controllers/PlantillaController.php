<?php

namespace App\Http\Controllers;

use App\Grupo;
use App\Persona;
use App\PlantillaJugador;
use App\PartidoTecnico;
use App\Torneo;
use App\Plantilla;
use App\Jugador;
use App\Tecnico;
use App\Equipo;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Http\Request;

use Illuminate\Database\QueryException;

use DB;
use Illuminate\Support\Facades\Log;

class PlantillaController extends Controller
{

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $grupo_id= $request->query('grupoId');
        //$nombre = $request->get('buscarpor');
        if ($request->has('buscarpor')){
            $nombre = $request->get('buscarpor');

            $request->session()->put('nombre_filtro_plantilla', $request->get('buscarpor'));

        }
        else{
            $nombre = $request->session()->get('nombre_filtro_plantilla');

        }
        $grupo=Grupo::findOrFail($grupo_id);


        $plantillas=Plantilla::with('equipo')->where('grupo_id','=',"$grupo_id")->whereHas('equipo', function($query) use ($nombre){
            if($nombre){
                $query->where('nombre', 'LIKE', "%$nombre%");
            }
        })->get()->sortBy(function($query){
            return $query->equipo->nombre;
        });





        //dd($plantillas);

        return view('plantillas.index', compact('plantillas','grupo'));
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

        $jugadors = Jugador::SELECT('jugadors.*','personas.nombre','personas.apellido','personas.nacimiento','personas.fallecimiento','personas.foto')->Join('personas','personas.id','=','jugadors.persona_id')->orderBy('personas.apellido', 'asc')->orderBy('personas.nombre', 'asc')->get();
        $jugadors = $jugadors->pluck('full_name', 'id')->prepend('','');

        $equipos = Equipo::orderBy('nombre', 'asc')->get();
        $equipos = $equipos->pluck('nombre', 'id')->prepend('','');


        /*$tecnicos = Tecnico::orderBy('apellido', 'asc')->orderBy('nombre', 'asc')->get();
        $tecnicos = $tecnicos->pluck('full_name', 'id')->prepend('','');*/
        //
        return view('plantillas.create', compact('grupo','jugadors','equipos'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->validate($request,[ 'equipo_id'=>'required',  'grupo_id'=>'required']);
        DB::beginTransaction();
        $ok=1;
        try {
            $plantilla = plantilla::create($request->all());

            $lastid=$plantilla->id;
            if(count($request->jugador) > 0)
            {
                foreach($request->jugador as $item=>$v){

                    $data2=array(
                        'plantilla_id'=>$lastid,
                        'jugador_id'=>$request->jugador[$item],
                        'dorsal'=>$request->dorsal[$item]
                    );
                    try {
                        PlantillaJugador::create($data2);
                    }catch(QueryException $ex){
                        $error = $ex->getMessage();
                        $ok=0;
                        continue;
                    }
                }
            }
            /*if(count($request->tecnico) > 0)
            {
                foreach($request->tecnico as $item=>$v){

                    $data2=array(
                        'plantilla_id'=>$lastid,
                        'tecnico_id'=>$request->tecnico[$item]
                    );
                    try {
                        PlantillaTecnico::create($data2);
                    }catch(QueryException $ex){
                        $error = $ex->getMessage();
                        $ok=0;
                        continue;
                    }
                }
            }*/
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

        return redirect()->route('plantillas.index', array('grupoId' => $plantilla->grupo->id))->with($respuestaID,$respuestaMSJ);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    public function search(Request $request)
    {
        /*$cities = City::where('name', 'LIKE', '%'.$request->input('term', '').'%')
            ->get(['id', 'name as text']);*/
        $search = $request->search;
        $search = trim($search);
        $search = preg_replace('/\s+/', ' ', $search);
        $search = htmlspecialchars($search, ENT_QUOTES, 'UTF-8');

        $jugadors = Jugador::select('jugadors.*', 'personas.nombre', 'personas.apellido', 'personas.nacimiento', 'personas.fallecimiento', 'personas.foto')
            ->join('personas', 'personas.id', '=', 'jugadors.persona_id')
            ->where(function ($query) use ($search) {
                $query->where('apellido', 'LIKE', "%$search%")
                    ->orWhere('nombre', 'LIKE', "%$search%");
            })
            ->orderBy('personas.apellido', 'asc')
            ->orderBy('personas.nombre', 'asc')
            ->get();

        $response = array();
        foreach($jugadors as $jugador){
            $response[] = array(
                "id"=>$jugador->id,
                "text"=>$jugador->full_name_age_tipo,
                "foto" => $jugador->foto ? url('images/'.$jugador->foto) : url('images/sin_foto.png') // Agregar URL de la foto o una imagen predeterminada si no hay foto
            );
        }

        //return ['results' => $jugadors];
        return response()->json($response);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $plantilla=Plantilla::findOrFail($id);

        $grupo=Grupo::findOrFail($plantilla->grupo->id);

        $plantillaJugadors = PlantillaJugador::where('plantilla_id','=',"$id")->orderBy('dorsal','asc')->get();

        $arrplantillajugador='';
        foreach ($plantillaJugadors as $plantillaJugador){
            $arrplantillajugador .=$plantillaJugador->jugador->id.',';
        }

        //$jugadors = Jugador::orderBy('apellido', 'asc')->orderBy('nombre', 'asc')->get();
        //$jugadors = Jugador::SELECT('jugadors.*','personas.nombre','personas.apellido','personas.nacimiento','personas.fallecimiento','personas.foto')->Join('personas','personas.id','=','jugadors.persona_id')->orderBy('personas.apellido', 'asc')->orderBy('personas.nombre', 'asc')->get();

        $jugadors = Jugador::SELECT('jugadors.*','personas.nombre','personas.apellido','personas.nacimiento','personas.fallecimiento','personas.foto')->Join('personas','personas.id','=','jugadors.persona_id')->wherein('jugadors.id',explode(',', $arrplantillajugador))->orderBy('personas.apellido', 'asc')->orderBy('personas.nombre', 'asc')->get();

        $jugadors = $jugadors->pluck('persona.full_name_age', 'id')->prepend('','');


        $equipos = Equipo::orderBy('nombre', 'asc')->get();
        $equipos = $equipos->pluck('nombre', 'id')->prepend('','');

        /*$plantillaTecnicos = PlantillaTecnico::where('plantilla_id','=',"$id")->get();


        $tecnicos = Tecnico::orderBy('apellido', 'asc')->orderBy('nombre', 'asc')->get();
        $tecnicos = $tecnicos->pluck('full_name', 'id')->prepend('','');*/

        return view('plantillas.edit', compact('jugadors','grupo','equipos','plantilla', 'plantillaJugadors'));
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


        //dd($request->plantillajugador_id);
        $this->validate($request,[ 'equipo_id'=>'required',  'grupo_id'=>'required']);
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


        return redirect()->route('plantillas.index', array('grupoId' => $plantilla->grupo->id))->with($respuestaID,$respuestaMSJ);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    public function importar(Request $request)
    {

        $plantilla=Plantilla::findOrFail($request->query('plantillaId'));

        $grupo=Grupo::findOrFail($plantilla->grupo->id);
        //
        return view('plantillas.importar',compact('plantilla','grupo'));
    }
    function getHtmlContent($url) {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Para seguir redirecciones

        // Opcional: Si necesitas establecer un timeout
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Timeout de 30 segundos

        $response = curl_exec($ch);

        // Maneja errores de cURL
        if (curl_errno($ch)) {
            Log::channel('mi_log')->error('Error en cURL: ' . curl_error($ch));
            return false;
        }

        // Verificar si $response es falso, lo que indica un fallo en la ejecución
        if ($response === false) {
            Log::channel('mi_log')->error('Fallo en la solicitud cURL para la URL: ' . $url);
            curl_close($ch);
            return false;
        }

        // Obtener el código de estado HTTP
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        //dd($httpCode);
        curl_close($ch);
        // Controlar el código 404
        if ($httpCode == 404) {
            Log::channel('mi_log')->warning('Página no encontrada (404) para la URL: ' . $url);
            return false;
        }


        return $response;
    }
    public function importarProcess(Request $request)
    {
        //dd($request);
        set_time_limit(0);
        $url = $request->get('url');
        $id = $request->get('plantilla_id');
        $ok = 1;
        DB::beginTransaction();
        $success='';
        $html = '';
        try {
            if ($url) {
                // Obtener el contenido de la URL
                //$htmlContent = file_get_contents($url);
                $htmlContent = $this->getHtmlContent($url);
                // Crear un nuevo DOMDocument
                $dom = new \DOMDocument();
                libxml_use_internal_errors(true); // Suprimir errores de análisis HTML
                $dom->loadHTML($htmlContent);
                libxml_clear_errors();

                // Crear un nuevo objeto XPath
                $xpath = new \DOMXPath($dom);
            }
        } catch (Exception $ex) {
            $html = '';
        }
        //dd($htmlContent);
        if ($htmlContent) {


            // Seleccionar todos los dt y dd dentro de div.contentitem
            // Seleccionar todos los <tr> con itemprop="employee"
            $trElements = $xpath->query('//tr[@itemprop="employee"]');

            // Recorrer cada <tr> para obtener el href y el dorsal
            foreach ($trElements as $tr) {
                // Obtener el enlace del jugador
                $thElement = $xpath->query('.//th/a', $tr)->item(0);
                $href = $thElement ? $thElement->getAttribute('href') : null;
                $urlJugador = $href ? 'https://www.resultados-futbol.com' . $href : null;

                // Obtener el número del dorsal
                $tdNumElement = $xpath->query('.//td[@class="num"]', $tr)->item(0);
                $dorsal = $tdNumElement ? trim($tdNumElement->textContent) : null;


                try {
                    if ($urlJugador) {
                        // Obtener el contenido de la URL

                        $htmlContentJugador = $this->getHtmlContent($urlJugador);
                        // Crear un nuevo DOMDocument
                        $domJugador = new \DOMDocument();
                        libxml_use_internal_errors(true); // Suprimir errores de análisis HTML
                        $domJugador->loadHTML($htmlContentJugador);
                        libxml_clear_errors();

                        // Crear un nuevo objeto XPath
                        $xpathJugador = new \DOMXPath($domJugador);
                    }
                } catch (Exception $ex) {
                    $html = '';
                }

                if ($htmlContentJugador) {
                    // Seleccionar el div con id 'previewArea' que contiene la imagen
                    $fotoDiv = $xpathJugador->query('//div[@id="previewArea"]/img');
                    if ($fotoDiv->length > 0) {
                        $imageUrl = $fotoDiv[0]->getAttribute('src');
                        //Log::info('Foto: ' . $imageUrl, []);
                    }

                    // Seleccionar todos los dt y dd dentro de div.contentitem
                    $dtElements = $xpathJugador->query('//div[@class="contentitem"]/dl/dt');
                    $ddElements = $xpathJugador->query('//div[@class="contentitem"]/dl/dd');

                    $nombre = '';
                    $apellido = '';
                    $nacimiento = '';
                    $fallecimiento = '';
                    $ciudad = '';
                    $nacionalidad = '';
                    $tipo = '';
                    $altura = '';
                    $peso = '';

                    for ($iJugador = 0; $iJugador < $dtElements->length; $iJugador++) {
                        $dtText = trim($dtElements[$iJugador]->textContent);
                        $ddText = trim($ddElements[$iJugador]->textContent);

                        // Agregar los datos a la persona según el título (dt) encontrado
                        switch ($dtText) {
                            case 'Nombre':
                                $nombre = $ddText;
                                break;
                            case 'Apellidos':
                                $apellido = $ddText;
                                break;
                            case 'Fecha de nacimiento':
                                $nacimiento = Carbon::createFromFormat('d-m-Y', $ddText)->format('Y-m-d');
                                break;
                            case 'Fecha de fallecimiento':
                                $fallecimiento = Carbon::createFromFormat('d-m-Y', $ddText)->format('Y-m-d');
                                break;
                            case 'Lugar de nacimiento':
                                $ciudad = $ddText;
                                break;
                            case 'Nacionalidad':
                                $nacionalidad = $ddText;
                                break;
                            case 'Demarcación':
                                switch ($ddText) {
                                    case 'Portero':
                                        $tipo = 'Arquero';
                                        break;
                                    case 'Defensa':
                                        $tipo = 'Defensor';
                                        break;
                                    case 'Centrocampista':
                                        $tipo = 'Medio';
                                        break;
                                    case 'Delantero':
                                        $tipo = 'Delantero';
                                        break;
                                }
                                break;
                            case 'Altura':
                                if (preg_match('/\d+/', $ddText, $matches)) {
                                    $altura = number_format($matches[0] / 100, 2);
                                }
                                break;
                            case 'Peso':
                                if (preg_match('/\d+/', $ddText, $matches)) {
                                    $peso = $matches[0];
                                }
                                break;
                            default:
                                // Manejar otros datos según sea necesario
                                break;
                        }
                    }

                    // Descarga y guarda la imagen si no es el avatar por defecto
                    if (!str_contains($imageUrl, 'avatar-player.jpg')) {
                        $client = new Client();
                        $response = $client->get($imageUrl);

                        if ($response->getStatusCode() === 200) {
                            $imageData = $response->getBody()->getContents();
                            $parsedUrl = parse_url($imageUrl);
                            $pathInfo = pathinfo($parsedUrl['path']);
                            $nombreArchivo = $pathInfo['filename'];
                            $extension = $pathInfo['extension'];

                            if (strrchr($nombreArchivo, '.') === '.') {
                                $nombreArchivo = substr($nombreArchivo, 0, -1);
                            }

                            // Define la ubicación donde deseas guardar la imagen en tu sistema de archivos
                            $localFilePath = public_path('images/') . $nombreArchivo . '.' . $extension;
                            Log::info('URL de la foto: ' . $localFilePath, []);
                            $insert['foto'] = "$nombreArchivo.$extension";

                            file_put_contents($localFilePath, $imageData);
                            Log::info('Foto subida', []);
                        } else {
                            Log::info('Foto no subida: ' . $fotoDiv[0]->getAttribute('alt'), []);
                            $insert['foto'] =null;
                            //$success .= 'Foto no subida: ' . $fotoDiv[0]->getAttribute('alt') . '<br>';
                        }
                    } else {
                        Log::info('No tiene foto: ' . $imageUrl, []);
                        $insert['foto'] =null;
                        //$success .= 'No tiene foto: ' . $imageUrl . '<br>';
                    }

                    // Insertar los datos de la persona
                    if ($nombre) {
                        $insert['nombre'] = $nombre;
                    } else {
                        Log::info('Falta el nombre', []);
                        $insert['nombre'] = null;
                        //$success .= 'Falta el nombre <br>';
                    }

                    if ($apellido) {
                        $insert['apellido'] = $apellido;
                    } else {
                        Log::info('Falta el apellido', []);
                        $insert['apellido'] = null;
                        //$success .= 'Falta el apellido <br>';
                    }

                    if ($ciudad) {
                        $insert['ciudad'] = $ciudad;
                    }else {
                        Log::info('Falta la ciudad', []);
                        $insert['ciudad'] = null;
                    }
                    if ($nacionalidad) {
                        $insert['nacionalidad'] = $nacionalidad;
                    } else {
                        Log::info('Falta la nacionalidad', []);
                        $insert['nacionalidad'] = null;
                    }
                    if ($altura) {
                        $insert['altura'] = $altura;
                    }
                    else {
                        Log::info('Falta la altura', []);
                        $insert['altura'] = null;
                    }
                    if ($peso) {
                        $insert['peso'] = $peso;
                    }else {
                        Log::info('Falta el peso', []);
                        $insert['peso'] = null;
                    }
                    if ($nacimiento) {
                        $insert['nacimiento'] = $nacimiento;
                    } else {
                        Log::info('Falta la fecha de nacimiento', []);
                        $insert['nacimiento'] = null;
                    }
                    if ($fallecimiento) {
                        $insert['fallecimiento'] = $fallecimiento;
                    }else {
                        Log::info('Falta la fecha de fallecimiento', []);
                        $insert['fallecimiento'] = null;
                    }
                    if ($tipo) {
                        $insert['tipoJugador'] = $tipo;
                    } else {
                        Log::info('Falta el tipo de jugador', []);
                        $insert['tipoJugador'] = null;
                    }
                    Log::info('Contenido de insert: ' . json_encode($insert));


                    try {
                        $persona = Persona::create($insert);
                        $persona->jugador()->create($insert);
                    } catch (QueryException $ex) {
                        try {
                            $persona = Persona::where('nombre', '=', $insert['nombre'])
                                ->where('apellido', '=', $insert['apellido'])
                                ->where('nacimiento', '=', $insert['nacimiento'])
                                ->first();

                            if (!empty($persona)) {
                                $persona->update($insert);
                                $persona->jugador()->create($insert);
                            }
                        } catch (QueryException $ex) {
                            //$ok = 0;
                            $errorCode = $ex->errorInfo[1];

                            if ($errorCode == 1062) {
                                //$error = 'Jugador repetido';
                            }
                        }
                    }
                } else {
                    Log::info('No se encontró la URL: ' . $urlJugador, []);
                    //$error = 'No se encontró la URL: ' . $urlJugador;
                }

                $data2=array(
                    'plantilla_id'=>$id,
                    'jugador_id'=>$persona->jugador->id,
                    'dorsal'=>$dorsal
                );
                try {
                    Log::info('Contenido de data: ' . json_encode($data2));
                        PlantillaJugador::create($data2);




                }catch(QueryException $ex){
                    if ($ex->errorInfo[1] === 1062) {
                        if (strpos($ex->errorInfo[2], 'plantilla_id_dorsal') !== false) {
                            $consultarPlantilla=PlantillaJugador::where('plantilla_id',"$id")->where('dorsal', $dorsal)->first();
                            $jugadorRepetido = Jugador::where('id', '=', $consultarPlantilla->jugador_id)->first();
                            $success .= "El dorsal ".$dorsal." ya lo usa ".$jugadorRepetido->persona->apellido.", ".$jugadorRepetido->persona->nombre. '<br>';
                        } elseif (strpos($ex->errorInfo[2], 'plantilla_id_jugador_id') !== false) {
                            $jugadorRepetido = Jugador::where('id', '=', $persona->jugador->id)->first();
                            $success .= "Jugador repetido: ".$jugadorRepetido->persona->apellido.", ".$jugadorRepetido->persona->nombre. '<br>';
                        } else {
                            $error = $ex->getMessage();
                        }
                    } else {
                        $error = $ex->getMessage();
                    }

                    /*$ok=0;
                    continue;*/
                }

            }

        }

        if ($ok) {

            DB::commit();
            $respuestaID = 'success';
            $respuestaMSJ = $success;
        } else {
            DB::rollback();
            $respuestaID = 'error';
            $respuestaMSJ=$error.'<br>'.$success;
        }


            //return redirect()->route('plantillas.edit', array('id' => $id))->with($respuestaID,$respuestaMSJ);
        return redirect()->route('plantillas.edit', ['plantilla' => $id])->with($respuestaID, $respuestaMSJ);
    }

    public function import(Request $request)
    {
        $grupo_id= $request->query('grupoId');
        $grupo=Grupo::findOrFail($grupo_id);

        $equipos = Equipo::orderBy('nombre', 'asc')->get();
        $equipos = $equipos->pluck('nombre', 'id')->prepend('','');


        $torneos=Torneo:: orderBy('year','DESC')->get();
        $torneosAnteriores = $torneos->pluck('full_name', 'id')->prepend('','');

        //
        return view('plantillas.import', compact('grupo','equipos','torneosAnteriores'));
    }

    public function importprocess(Request $request)
    {

        set_time_limit(0);

        $this->validate($request,[ 'equipo_id'=>'required',  'torneo_id'=>'required']);

        $grupo_id = $request->get('grupo_id');
        $equipo_id = $request->get('equipo_id');
        $torneo_id = $request->get('torneo_id');

        $grupos = Grupo::where('torneo_id', '=',$torneo_id)->get();
        $arrgrupos='';
        foreach ($grupos as $grupo){
            $arrgrupos .=$grupo->id.',';
        }




        $plantillas = Plantilla::wherein('grupo_id',explode(',', $arrgrupos))->wherein('equipo_id',[$equipo_id])->get();
        $arrPlantillas='';
        foreach ($plantillas as $plantilla){
            $arrPlantillas .=$plantilla->id.',';
        }

        $plantillaJugadors = PlantillaJugador::wherein('plantilla_id',explode(',', $arrPlantillas))->get();

        DB::beginTransaction();
        $ok=1;
        try {
            $data1=array(
                'equipo_id'=>$equipo_id,
                'grupo_id'=>$grupo_id
            );
            $plantilla = plantilla::create($data1);

            $lastid=$plantilla->id;

            foreach ($plantillaJugadors as $plantillaJugador){

                    $data2=array(
                        'plantilla_id'=>$lastid,
                        'jugador_id'=>$plantillaJugador->jugador->id,
                        'dorsal'=>$plantillaJugador->dorsal
                    );
                    try {
                        PlantillaJugador::create($data2);
                    }catch(QueryException $ex){
                        $error = $ex->getMessage();
                        $ok=0;
                        continue;
                    }
                }


        }catch(Exception $e){
            $error =  $ex->getMessage();
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
        return redirect()->route('plantillas.index', array('grupoId' => $grupo_id))->with($respuestaID,$respuestaMSJ);
    }

    /*
  AJAX request
  */
    public function getJugadors(Request $request){

        $search = $request->search;





        $jugadors = Jugador::orderBy('apellido', 'asc')->orderBy('nombre', 'asc')->get();
        $jugadors = $jugadors->pluck('full_name', 'id')->prepend('','');
        if($search == ''){
            $jugadors = Jugador::orderBy('apellido', 'asc')->orderBy('nombre', 'asc')->limit(5)->get();
        }else{
            $jugadors = Jugador::orderBy('apellido', 'asc')->orderBy('nombre', 'asc')->where('name', 'like', '%' .$search . '%')->limit(5)->get();
        }

        $response = array();
        foreach($jugadors as $jugador){
            $response[] = array(
                "id"=>$jugador->id,
                "text"=>$jugador->name
            );
        }

        return response()->json($response);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function controlar(Request $request)
    {


        $grupo_id= $request->query('grupoId');
        $grupo=Grupo::findOrFail($grupo_id);
        $torneo_id= $grupo->torneo->id;
        /*$torneo=Torneo::findOrFail($torneo_id);
        $arrMetodo = array();*/

        $jugadores = DB::table('plantilla_jugadors AS pj')
            ->select('pj.id', 'p.foto', 'p.nombre','j.tipoJugador', 'p.apellido', 'pj.dorsal', 'e.escudo', 'e.nombre as equipo')
            ->join('plantillas AS pl', 'pl.id', '=', 'pj.plantilla_id')
            ->join('jugadors AS j', 'j.id', '=', 'pj.jugador_id')
            ->join('personas AS p', 'p.id', '=', 'j.persona_id')
            ->join('equipos AS e', 'e.id', '=', 'pl.equipo_id')
            ->join('grupos AS g', 'g.id', '=', 'pl.grupo_id')
            ->join('torneos AS t', 't.id', '=', 'g.torneo_id')
            ->where('t.id', '=', $torneo_id)
            ->whereNotExists(function ($query) use ($torneo_id) {
                $query->select(DB::raw(1))
                    ->from('torneos AS t2')
                    ->join('grupos AS g2', 't2.id', '=', 'g2.torneo_id')
                    ->join('fechas', 'fechas.grupo_id', '=', 'g2.id')
                    ->join('partidos', 'partidos.fecha_id', '=', 'fechas.id')
                    ->join('alineacions', 'alineacions.partido_id', '=', 'partidos.id')
                    ->where('t2.id', '=', $torneo_id)
                    ->whereRaw('alineacions.equipo_id = pl.equipo_id')
                    ->whereRaw('pj.jugador_id = alineacions.jugador_id');
            })
            ->orderBy('e.nombre') // Ordenar por nombre del equipo
            ->orderBy('apellido') // Luego ordenar por apellido
            ->orderBy('nombre') // Y finalmente ordenar por nombre
            ->paginate();

        // Agrega el parámetro 'grupoId' a la paginación
        $jugadores->appends(['grupoId' => $grupo_id]);

        //dd($jugadores);
        //echo $sql;
        $i=1;
        return view('plantillas.controlar', compact('jugadores','i','grupo_id'));
    }

    public function eliminarJugador($id)
    {
        // Lógica para eliminar un jugador por su ID
        PlantillaJugador::destroy($id);

        return redirect()->back()->with('success', 'Jugador eliminado de la plantilla.');
    }

    public function eliminarJugadoresSeleccionados(Request $request)
    {
        $grupoId = $request->input('grupoId');
        $jugadorIds = $request->input('jugador_ids');

        if (!empty($jugadorIds)) {
            PlantillaJugador::whereIn('id', $jugadorIds)->delete();
            return redirect()->back()->with('success', 'Los jugadores seleccionados han sido eliminados correctamente.');
        } else {
            return redirect()->back()->with('error', 'No se seleccionaron jugadores para eliminar.');
        }
    }




}
