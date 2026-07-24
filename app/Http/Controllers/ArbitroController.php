<?php

namespace App\Http\Controllers;

use App\Arbitro;
use App\Persona;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\TransferStats;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use DB;
use App\Services\HttpHelper;
use Illuminate\Support\Facades\Log;

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

        if ($request->has('buscarpor')){
            $nombre = $request->get('buscarpor');

            $request->session()->put('nombre_filtro_arbitro', $request->get('buscarpor'));

        }
        else{
            $nombre = $request->session()->get('nombre_filtro_arbitro');

        }

        // Página actual
        $page = $request->get('page', session('arbitros_page', 1));
        $request->session()->put('arbitros_page', $page);

        //$arbitros=Arbitro::where('nombre','like',"%$nombre%")->orWhere('apellido','like',"%$nombre%")->orWhere('email','like',"%$nombre%")->orWhere(DB::raw('TIMESTAMPDIFF(YEAR,nacimiento,CURDATE())'),'=',"$nombre")->orderBy('apellido','ASC')->paginate();

        $arbitros=Arbitro::SELECT('arbitros.*','personas.name','personas.nombre','personas.apellido','personas.nacimiento','personas.fallecimiento','personas.email','personas.foto')->Join('personas','personas.id','=','arbitros.persona_id')->where('nombre','like',"%$nombre%")->orWhere('apellido','like',"%$nombre%")->orWhere('name','like',"%$nombre%")->orWhere(DB::raw('TIMESTAMPDIFF(YEAR,nacimiento,CURDATE())'),'=',"$nombre")->orderBy('apellido','ASC')->paginate(15, ['*'], 'page', $page);

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
        $this->validate($request,[ 'name'=>'required','nombre'=>'required', 'apellido'=>'required','foto' => 'image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048']);


        if ($files = $request->file('foto')) {
            $image = $request->file('foto');
            $name = time().'.'.$image->getClientOriginalExtension();
            $destinationPath = public_path('/images');
            $image->move($destinationPath, $name);



            $insert['foto'] = "$name";
        }

        $insert['name'] = $request->get('name');
        $insert['nombre'] = $request->get('nombre');
        $insert['apellido'] = $request->get('apellido');
        $insert['email'] = $request->get('email');
        $insert['telefono'] = $request->get('telefono');
        $insert['ciudad'] = $request->get('ciudad');
        $insert['nacionalidad'] = $request->get('nacionalidad');
        $insert['observaciones'] = $request->get('observaciones');
        /*$insert['tipoDocumento'] = $request->get('tipoDocumento');
        $insert['documento'] = $request->get('documento');*/
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
        $this->validate($request,[ 'name'=>'required','nombre'=>'required', 'apellido'=>'required','foto' => 'image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048']);


        if ($files = $request->file('foto')) {
            $image = $request->file('foto');
            $name = time().'.'.$image->getClientOriginalExtension();
            $destinationPath = public_path('/images');
            $image->move($destinationPath, $name);



            $update['foto'] = "$name";
        }

        $update['name'] = $request->get('name');
        $update['nombre'] = $request->get('nombre');
        $update['apellido'] = $request->get('apellido');
        $update['email'] = $request->get('email');
        $update['telefono'] = $request->get('telefono');
        $update['ciudad'] = $request->get('ciudad');
        $update['nacionalidad'] = $request->get('nacionalidad');
        $update['observaciones'] = $request->get('observaciones');
        /*$update['tipoDocumento'] = $request->get('tipoDocumento');
        $update['documento'] = $request->get('documento');*/
        $update['nacimiento'] = $request->get('nacimiento');
        $update['fallecimiento'] = $request->get('fallecimiento');
        $update['verificado'] = $request->get('verificado');


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
        $persona = Persona::find($arbitro->persona_id);
        // Verificar si la persona tiene una foto y eliminarla del servidor
        if ($persona->foto && file_exists(public_path('images/' . $persona->foto))) {
            unlink(public_path('images/' . $persona->foto)); // Eliminar la foto del servidor
        }
        $persona->delete();
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

    public function importar(Request $request)
    {


        //
        return view('arbitros.importar');
    }

    public function importarProcess_old(Request $request)
    {
        set_time_limit(0);

        $url = $request->get('url');
        $ok = 1;
        DB::beginTransaction();
        $success='';
        $html = '';
        try {
            if ($url) {
                // Obtener el contenido de la URL
                //$htmlContent = file_get_contents($url);
                $htmlContent =  HttpHelper::getHtmlContent($url);
            //Log::channel('mi_log')->debug("HTML capturado: " . substr($htmlContent, 0, 5000));
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

        if ($htmlContent) {

            // Buscar el div con id 'sidebar'
            $sidebarDiv = $xpath->query('//div[@class="sidebar"]')->item(0);

            if ($sidebarDiv) {
                // Seleccionar el div con id 'previewArea' que contiene la imagen
                $fotoDiv = $xpath->query('.//div[@class="data"]/img', $sidebarDiv);

                if ($fotoDiv->length > 0) {

                    //$imageUrl = $fotoDiv[0]->getAttribute('data-cfsrc');
                    $imageUrl = $fotoDiv[0]->getAttribute('src');
                    //Log::info('URL imagen capturada:', ['url' => $imageUrl]);

                    //dd($imageUrl);
                    //Log::info('Foto: ' . $imageUrl, []);
                }


                $name = null;
                $nombre = '';
                $apellido = '';
                $nacimiento = '';
                $fallecimiento = '';
                $ciudad = '';
                $nacionalidad = '';

                $nameNode = $xpath->query('.//div[@class="head"]/h2', $sidebarDiv)->item(0);
                $name = $nameNode ? trim($nameNode->nodeValue) : null;


                // 3. Obtener las filas de la tabla
                $rows = $xpath->query('.//table[@class="standard_tabelle yellow"]//tr', $sidebarDiv);

                foreach ($rows as $row) {
                    $td1 = $xpath->query('.//td[1]', $row)->item(0);
                    $label = trim($td1 ? $td1->textContent : '');

                    $valueCell = $xpath->query('.//td[2]', $row)->item(0);
                    $value = trim($valueCell ? $valueCell->textContent : '');

                    //echo $label . ' - ' . $value . '<br>';
                    // Agregar los datos a la persona según el título (dt) encontrado
                    switch ($label) {
                        case 'Nombre completo:':

                            $apellido = $value;
                            break;

                        case 'Fecha de nacimiento:':
                            if (!empty($value)) {
                                $nacimiento = Carbon::createFromFormat('d.m.Y', $value)->format('Y-m-d');
                            }
                            break;

                        case 'Fecha de fallecimiento:':
                            if (!empty($value)) {
                                $fallecimiento = Carbon::createFromFormat('d.m.Y', $value)->format('Y-m-d');
                            }
                            break;
                        case 'Lugar de nacimiento:':
                            $ciudad = $value;
                            break;
                        case 'Nacionalidad:':
                            $nacionalidad = $value;
                            break;

                        default:
                            // Manejar otros datos según sea necesario
                            break;
                    }
                }
            }

            // Descarga y guarda la imagen si no es el avatar por defecto
            if (!empty($imageUrl) && !str_contains($imageUrl, 'avatar-player.jpg')) {
                try {
                    $client = new Client();

                    //$response = $client->get($imageUrl);
                    // Intentar obtener la imagen con reintentos y asegurarnos de que Guzzle lanza excepciones en caso de error HTTP
                    $response = $client->get($imageUrl, [
                        'http_errors' => false,  // No lanzar excepción en errores HTTP (como 404)
                        'timeout' => 10, // Tiempo máximo de espera
                    ]);

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
                        $success .='Foto no subida: ' . $fotoDiv[0]->getAttribute('alt').'<br>';
                    }
                } catch (RequestException $e) {
                    // Capturar la excepción y continuar con el flujo
                    Log::error('Error al intentar obtener la imagen: ' . $e->getMessage(), []);
                    $insert['foto'] = null;
                }
            } else {
                Log::info('No tiene foto: ' . $imageUrl, []);
                $success .='No tiene foto: ' . $imageUrl.'<br>';
            }
            //dd($name,$imageUrl,$apellido,$nacimiento,$fallecimiento,$ciudad,$nacionalidad);
            // Insertar los datos de la persona
            if ($name) {
                $insert['name'] = $name;
            } else {
                Log::info('Falta el name', []);
                $success .='Falta el name <br>';
            }
            /*if ($nombre) {
                $insert['nombre'] = $nombre;
            } else {
                Log::info('Falta el nombre', []);
                $success .='Falta el nombre <br>';
            }*/

            if ($apellido) {
                $insert['apellido'] = $apellido;
            } else {
                Log::info('Falta el apellido', []);
                $success .='Falta el apellido <br>';
            }

            if ($ciudad) {
                $insert['ciudad'] = $ciudad;
            }
            if ($nacionalidad) {
                $insert['nacionalidad'] = $nacionalidad;
            } else {
                Log::info('Falta la nacionalidad', []);
                $success .='Falta la nacionalidad <br>';
            }

            if ($nacimiento) {
                $insert['nacimiento'] = $nacimiento;
            } else {
                Log::info('Falta la fecha de nacimiento', []);
                $success .='Falta la fecha de nacimiento <br>';
            }
            if ($fallecimiento) {
                $insert['fallecimiento'] = $fallecimiento;
            }


            $request->session()->put('nombre_filtro_arbitro', $apellido);
            //log::info(print_r($insert, true));
            try {
                $persona = Persona::create($insert);
                $persona->arbitro()->create($insert);
            } catch (QueryException $ex) {
                try {
                    $persona = Persona::where('nombre', '=', $insert['nombre'])
                        ->where('apellido', '=', $insert['apellido'])
                        ->where('nacimiento', '=', $insert['nacimiento'])
                        ->first();

                    if (!empty($persona)) {
                        if (!empty($persona->nacionalidad)) {
                            unset($insert['nacionalidad']);
                        }
                        $persona->update($insert);
                        $persona->arbitro()->create($insert);
                    }
                } catch (QueryException $ex) {
                    //$ok = 0;
                    $errorCode = $ex->errorInfo[1];

                    if ($errorCode == 1062) {
                        $success .= 'Arbitro repetido';
                    }
                }
            }
        } else {
            Log::info('No se encontró la URL: ' . $url, []);
            $error = 'No se encontró la URL: ' . $url;
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

        return redirect()->route('arbitros.index')->with($respuestaID, $respuestaMSJ);
    }

    public function importarProcess(Request $request)
    {
        set_time_limit(0);

        // Si se completó el input de Transfermarkt (url2), usamos la API tmapi
        // (igual que en JugadorController/TecnicoController): la página HTML la bloquea Cloudflare.
        $url2 = $request->get('url2');
        if ($url2) {
            return $this->importarProcess_new($request);
        }

        $url = $request->get('url');
        $ok = 1;
        DB::beginTransaction();
        $success='';
        $html = '';
        $error = '';
        $htmlContent = '';
        $xpath = null;
        try {
            if ($url) {
                // Obtener el contenido de la URL
                //$htmlContent = file_get_contents($url);
                $htmlContent =  HttpHelper::getHtmlContent($url);
                //Log::channel('mi_log')->debug("HTML capturado: " . substr($htmlContent, 0, 5000));

                // Si la descarga falló o vino vacía (ej: Cloudflare bloqueó el fetch),
                // no intentar parsear: loadHTML('') dispara un ErrorException.
                if (empty($htmlContent)) {
                    Log::warning('importarProcess: contenido HTML vacío para la URL: ' . $url);
                    $htmlContent = '';
                } else {
                    // Crear un nuevo DOMDocument
                    $dom = new \DOMDocument();
                    libxml_use_internal_errors(true); // Suprimir errores de análisis HTML
                    $dom->loadHTML($htmlContent);
                    libxml_clear_errors();

                    // Crear un nuevo objeto XPath
                    $xpath = new \DOMXPath($dom);
                }
            }
        } catch (Exception $ex) {
            $html = '';
        }

        if ($htmlContent) {

            // Buscar el bloque lateral donde están los datos
            $sidebar = $xpath->query('//aside[@id="hs-sidebar"]')->item(0);

            $datos = [
                'nombre' => '',
                'cumpleanos' => '',
                'nacido_en' => '',
                'nacionalidad' => '',
                'peso' => ''
            ];

            if ($sidebar) {
                $imgNode = $xpath->query('.//div[contains(@class, "person-image")]//img', $sidebar)->item(0);
                $imageUrl = $imgNode ? $imgNode->getAttribute('src') : null;
                if ($imageUrl) {
                    try {
                        $finalUrl = null;
                        $client = new Client([
                            'allow_redirects' => true,
                            'timeout' => 10,
                            'on_stats' => function (TransferStats $stats) use (&$finalUrl) {
                                $finalUrl = (string) $stats->getEffectiveUri(); // URL final después de redirecciones
                            },
                        ]);

                        $response = $client->get($imageUrl);

                        if ($response->getStatusCode() === 200) {
                            // Si termina en /0.png → es genérica
                            if (str_contains($finalUrl, '/0.png')) {
                                $imageUrl = null;
                            }
                        } else {
                            $imageUrl = null;
                        }
                    } catch (\Exception $e) {
                        Log::warning("Error al verificar imagen: " . $e->getMessage());
                        $imageUrl = null;
                    }
                }

                $datos['foto'] = $imageUrl;
                // Buscar todos los pares dt/dd dentro del aside
                $dtNodes = $xpath->query('.//dt', $sidebar);
                foreach ($dtNodes as $dtNode) {
                    $label = trim($dtNode->textContent);
                    $ddNode = $dtNode->nextSibling;

                    // Buscar el siguiente dd (ignorando nodos vacíos o texto)
                    while ($ddNode && $ddNode->nodeName !== 'dd') {
                        $ddNode = $ddNode->nextSibling;
                    }

                    $value = $ddNode ? trim($ddNode->textContent) : '';


                    switch (mb_strtolower($label)) {
                        case 'nombre':
                            $datos['nombre'] = $value;
                            break;
                        case 'cumpleaños':
                            $datos['cumpleanos'] = $value;
                            break;
                        case 'nacido en':
                            $datos['nacido_en'] = $value;
                            break;
                        case 'nacionalidad':
                            $datos['nacionalidad'] = $value;
                            break;
                        case 'peso':
                            $datos['peso'] = $value;
                            break;
                    }
                }
            }

            //dd($datos);

            // Descarga y guarda la imagen si no es el avatar por defecto
            if (!empty($imageUrl) && !str_contains($imageUrl, 'avatar-player.jpg')) {
                try {
                    $client = new Client();

                    //$response = $client->get($imageUrl);
                    // Intentar obtener la imagen con reintentos y asegurarnos de que Guzzle lanza excepciones en caso de error HTTP
                    $response = $client->get($imageUrl, [
                        'http_errors' => false,  // No lanzar excepción en errores HTTP (como 404)
                        'timeout' => 10, // Tiempo máximo de espera
                    ]);

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
                        //Log::info('URL de la foto: ' . $localFilePath, []);
                        $insert['foto'] = "$nombreArchivo.$extension";

                        file_put_contents($localFilePath, $imageData);
                        //Log::info('Foto subida', []);
                    } else {
                        //Log::info('Foto no subida: ' . $imageUrl, []);
                        $success .='Foto no subida: ' . $imageUrl.'<br>';
                    }
                } catch (RequestException $e) {
                    // Capturar la excepción y continuar con el flujo
                    Log::error('Error al intentar obtener la imagen: ' . $e->getMessage(), []);
                    $insert['foto'] = null;
                }
            } else {
                Log::info('No tiene foto: ' . $imageUrl, []);
                $success .='No tiene foto: ' . $imageUrl.'<br>';
            }
            // ---------------------------------------------------
            // 🧾 Asignar variables a tu estructura de inserción
            // ---------------------------------------------------
            $name = $datos['nombre'];
            $apellido = $datos['nombre']; // o separar nombre/apellido si querés
            $ciudad = $datos['nacido_en'];
            $nacionalidad = $datos['nacionalidad'];
            $nacimiento = null;
            $fallecimiento = null;

            if (!empty($datos['cumpleanos'])) {
                try {
                    // Limpiar la fecha (quedarse solo con la parte antes del "|")
                    $fechaLimpia = trim(explode('|', $datos['cumpleanos'])[0]);

                    $nacimiento = Carbon::createFromFormat('d.m.Y', $fechaLimpia)->format('Y-m-d');

                } catch (\Exception $e) {
                    Log::warning("Fecha inválida: " . $datos['cumpleanos']);
                }
            }
            //dd($name,$imageUrl,$apellido,$nacimiento,$fallecimiento,$ciudad,$nacionalidad);
            // Insertar los datos de la persona
            if ($name) {
                $insert['name'] = $name;
            } else {
                Log::info('Falta el name', []);
                $success .='Falta el name <br>';
            }
            /*if ($nombre) {
                $insert['nombre'] = $nombre;
            } else {
                Log::info('Falta el nombre', []);
                $success .='Falta el nombre <br>';
            }*/

            if ($apellido) {
                $insert['apellido'] = $apellido;
            } else {
                Log::info('Falta el apellido', []);
                $success .='Falta el apellido <br>';
            }

            if ($ciudad) {
                $insert['ciudad'] = $ciudad;
            }
            if ($nacionalidad) {
                $insert['nacionalidad'] = $nacionalidad;
            } else {
                Log::info('Falta la nacionalidad', []);
                $success .='Falta la nacionalidad <br>';
            }

            if ($nacimiento) {
                $insert['nacimiento'] = $nacimiento;
            } else {
                Log::info('Falta la fecha de nacimiento', []);
                $success .='Falta la fecha de nacimiento <br>';
            }
            if ($fallecimiento) {
                $insert['fallecimiento'] = $fallecimiento;
            }


            $request->session()->put('nombre_filtro_arbitro', $apellido);
            //log::info(print_r($insert, true));
            try {
                $persona = Persona::create($insert);
                $persona->arbitro()->create($insert);
            } catch (QueryException $ex) {
                try {
                    $persona = Persona::where('nombre', '=', $insert['nombre'])
                        ->where('apellido', '=', $insert['apellido'])
                        ->where('nacimiento', '=', $insert['nacimiento'])
                        ->first();

                    if (!empty($persona)) {
                        if (!empty($persona->nacionalidad)) {
                            unset($insert['nacionalidad']);
                        }
                        $persona->update($insert);
                        $persona->arbitro()->create($insert);
                    }
                } catch (QueryException $ex) {
                    //$ok = 0;
                    $errorCode = $ex->errorInfo[1];

                    if ($errorCode == 1062) {
                        $success .= 'Arbitro repetido';
                    }
                }
            }
        } else {
            Log::info('No se encontró la URL: ' . $url, []);
            $error = 'No se encontró la URL: ' . $url;
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

        return redirect()->route('arbitros.index')->with($respuestaID, $respuestaMSJ);
    }

    /**
     * Importar un árbitro desde Transfermarkt vía la API interna
     * tmapi.transfermarkt.technology (NO scraping de HTML, que Cloudflare bloquea).
     *
     * La URL del perfil es del tipo:  .../perfil/profil/schiedsrichter/{id}
     * y el {id} del /schiedsrichter/ es el mismo refereeId que usa la API en /referee/{id}.
     * Es el equivalente para árbitros de TecnicoController::importarProcess_new().
     */
    public function importarProcess_new(Request $request)
    {
        set_time_limit(0);
        $url = $request->get('url2');
        $ok = 1;
        DB::beginTransaction();
        $success = '';
        $error   = '';

        $base  = 'https://tmapi.transfermarkt.technology';
        $datos = null;

        // El id del árbitro aparece como /schiedsrichter/{id} en la URL pública y como
        // refereeId en la API: /referee/{id}.
        if ($url && preg_match('#/(?:schiedsrichter|referee)/(\d+)#', $url, $mId)) {
            $refereeId = $mId[1];
            $resp = HttpHelper::getJson("{$base}/referee/{$refereeId}");
            if (!empty($resp['data'])) {
                $datos = $resp['data'];
            }
        }

        if (!$datos) {
            Log::info('Import TM (Arbitro): no se pudo obtener el perfil desde tmapi para: ' . $url, []);
        }

        if ($datos) {

            $insert = [];

            // ── Nombre / apellido (misma estrategia que en jugador/DT) ─────
            $nameField   = trim($datos['name'] ?? '');
            $shortName   = trim($datos['shortName'] ?? '');
            $passport    = trim($datos['nationalityDetails']['passportName'] ?? '');
            $displayName = trim($datos['displayName'] ?? '');

            $completo = $passport !== '' ? $passport
                      : ($displayName !== '' ? $displayName : $nameField);

            $anclaApellido = $shortName !== ''
                ? trim(preg_replace('/^(\p{Lu}\p{Ll}?\.\s*)+/u', '', $shortName))
                : '';

            $nombre   = '';
            $apellido = '';

            if ($anclaApellido !== '' && $completo !== '') {
                $primerApellido = preg_split('/\s+/', $anclaApellido)[0];
                $palabras = preg_split('/\s+/', $completo);

                $norm = function ($s) {
                    if (function_exists('iconv')) {
                        $conv = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
                        if ($conv !== false) { $s = $conv; }
                    }
                    return mb_strtolower(trim($s ?? ''));
                };
                $idx = null;
                foreach ($palabras as $i => $p) {
                    if ($norm($p) === $norm($primerApellido)) { $idx = $i; break; }
                }

                if ($idx !== null && $idx > 0) {
                    $nombre   = trim(implode(' ', array_slice($palabras, 0, $idx)));
                    $apellido = trim(implode(' ', array_slice($palabras, $idx)));
                }
            }

            // Fallback: separar por la última palabra, arrastrando partículas al apellido.
            if ($apellido === '') {
                $baseSplit = $completo !== '' ? $completo : $nameField;
                $palabras  = preg_split('/\s+/', trim($baseSplit));
                if (count($palabras) >= 2) {
                    $particulas = ['de','da','do','dos','das','del','della','di','la','las','los',
                                   'van','von','der','den','du','le','bin','al'];
                    $apellido = array_pop($palabras);
                    while (!empty($palabras) && in_array(mb_strtolower(end($palabras)), $particulas, true)) {
                        $apellido = array_pop($palabras) . ' ' . $apellido;
                    }
                    $nombre   = implode(' ', $palabras);
                } else {
                    $nombre   = $baseSplit;
                    $apellido = '';
                }
            }

            // Campo "name" mostrado: shortName; si no hay, name; si no, nombre+apellido.
            $name = $shortName !== '' ? $shortName : $nameField;
            if ($name === '') { $name = trim($nombre . ' ' . $apellido); }

            // Fecha de nacimiento / fallecimiento (ya vienen en Y-m-d).
            // El endpoint /referee/{id} las trae planas (dateOfBirth), no dentro de lifeDates.
            $nacimiento = null;
            $rawNac = $datos['dateOfBirth'] ?? ($datos['lifeDates']['dateOfBirth'] ?? null);
            if ($rawNac) {
                try { $nacimiento = Carbon::parse($rawNac)->format('Y-m-d'); }
                catch (\Exception $e) { $nacimiento = null; }
            }
            $fallecimiento = null;
            $rawFall = $datos['dateOfDeath'] ?? ($datos['lifeDates']['dateOfDeath'] ?? null);
            if ($rawFall) {
                try { $fallecimiento = Carbon::parse($rawFall)->format('Y-m-d'); }
                catch (\Exception $e) { $fallecimiento = null; }
            }

            // Lugar de nacimiento (el endpoint de árbitros no lo trae; queda null).
            $ciudad = trim($datos['placeOfBirth'] ?? ($datos['birthPlaceDetails']['placeOfBirth'] ?? '')) ?: null;

            // Nacionalidad: la API da solo el ID -> lo resolvemos con la tabla de JugadorController.
            // Árbitros: nationalities.nationalityId (plano). Jugadores/DT: nationalityDetails.nationalities.nationalityId.
            $nacionalidad = null;
            $nacId = (int)($datos['nationalities']['nationalityId']
                ?? ($datos['nationalityDetails']['nationalities']['nationalityId'] ?? 0));
            if ($nacId) {
                $nacionalidad = \App\Http\Controllers\JugadorController::paisesTM()[$nacId] ?? null;
                if ($nacionalidad === null) {
                    Log::warning('Import TM (Arbitro): nationalityId sin mapear = ' . $nacId . ' (Arbitro: ' . $name . ')', []);
                    $success .= '⚠️ Nacionalidad no reconocida (código de país Transfermarkt: ' . $nacId
                        . '). Cargala manualmente o pasá este código para agregarlo.<br>';
                }
            }

            // Foto de perfil.
            $imageUrl = trim($datos['portraitUrl'] ?? '') ?: null;

            // Descarga y guarda la imagen si no es el avatar por defecto.
            if (!empty($imageUrl) && filter_var($imageUrl, FILTER_VALIDATE_URL) && !str_contains($imageUrl, 'default.jpg')) {
                try {
                    $client = new Client();
                    $response = $client->get($imageUrl, ['http_errors' => false, 'timeout' => 10]);

                    if ($response->getStatusCode() === 200) {
                        $imageData = $response->getBody()->getContents();
                        $parsedUrl = parse_url($imageUrl);
                        $pathInfo = pathinfo($parsedUrl['path']);
                        $nombreArchivo = $pathInfo['filename'];
                        $extension = $pathInfo['extension'] ?? 'jpg';

                        if (strrchr($nombreArchivo, '.') === '.') {
                            $nombreArchivo = substr($nombreArchivo, 0, -1);
                        }

                        $localFilePath = public_path('images/') . $nombreArchivo . '.' . $extension;
                        $insert['foto'] = "$nombreArchivo.$extension";

                        file_put_contents($localFilePath, $imageData);
                        Log::info('Foto subida (Arbitro): ' . $localFilePath, []);
                    } else {
                        Log::info('Foto no subida (HTTP ' . $response->getStatusCode() . '): ' . $imageUrl, []);
                        $success .= 'Foto no subida<br>';
                    }
                } catch (\Exception $e) {
                    Log::error('Error al obtener la imagen del Arbitro: ' . $e->getMessage(), []);
                    $insert['foto'] = null;
                }
            } else {
                Log::info('Arbitro sin foto: ' . $imageUrl, []);
                $success .= 'No tiene foto<br>';
            }

            // ── Armar el insert ────────────────────────────────────────────
            if ($name) {
                $insert['name'] = trim($name);
            } else {
                Log::info('Falta el name (Arbitro)', []);
                $success .= 'Falta el name <br>';
            }
            if ($nombre) {
                $insert['nombre'] = trim($nombre);
            }
            if ($apellido) {
                $insert['apellido'] = trim($apellido);
            } else {
                Log::info('Falta el apellido (Arbitro)', []);
                $success .= 'Falta el apellido <br>';
            }
            if ($ciudad) {
                $insert['ciudad'] = trim($ciudad);
            }
            if ($nacionalidad) {
                $insert['nacionalidad'] = preg_replace('/^[\pZ\pC]+|[\pZ\pC]+$/u', '', $nacionalidad);
            } else {
                Log::info('Falta la nacionalidad (Arbitro)', []);
                $success .= 'Falta la nacionalidad <br>';
            }
            if ($nacimiento) {
                $insert['nacimiento'] = trim($nacimiento);
            } else {
                Log::info('Falta la fecha de nacimiento (Arbitro)', []);
                $success .= 'Falta la fecha de nacimiento <br>';
            }
            if ($fallecimiento) {
                $insert['fallecimiento'] = trim($fallecimiento);
            }

            $request->session()->put('nombre_filtro_arbitro', $apellido ?: $name);

            if (empty($insert['name']) || empty($insert['apellido'])) {
                // No se pudo extraer la info del árbitro (página bloqueada o estructura cambiada).
                $ok = 0;
                $error = 'No se pudieron extraer los datos del árbitro desde la URL. '
                    . 'Verificá que Transfermarkt no esté bloqueando el acceso.';
                Log::warning('Import TM (Arbitro): sin datos suficientes para guardar. Claves recibidas: '
                    . implode(', ', array_keys($datos)), []);
            } else {
                // Guardamos también la URL de Transfermarkt en la persona.
                $insert['transfermarkt_url'] = $url;
                try {
                    $persona = Persona::create($insert);
                    $persona->arbitro()->create($insert);
                } catch (QueryException $ex) {
                    try {
                        $persona = Persona::where('nombre', '=', $insert['nombre'] ?? null)
                            ->where('apellido', '=', $insert['apellido'])
                            ->where('nacimiento', '=', $insert['nacimiento'] ?? null)
                            ->first();

                        if (!empty($persona)) {
                            if (!empty($persona->nacionalidad)) {
                                unset($insert['nacionalidad']);
                            }
                            $persona->update($insert);
                            $persona->arbitro()->create($insert);
                        }
                    } catch (QueryException $ex) {
                        $errorCode = $ex->errorInfo[1];
                        if ($errorCode == 1062) {
                            $error = 'Arbitro repetido';
                        }
                    }
                }
            }
        } else {
            $ok = 0;
            Log::info('No se encontró la URL (Arbitro): ' . $url, []);
            $error = 'No se pudo obtener el perfil del árbitro desde Transfermarkt. Revisá la URL (debe contener /schiedsrichter/{id}).';
        }

        if ($ok) {
            DB::commit();
            $respuestaID = 'success';
            $respuestaMSJ = $success;
        } else {
            DB::rollback();
            $respuestaID = 'error';
            $respuestaMSJ = $error . '<br>' . $success;
        }

        return redirect()->route('arbitros.index')->with($respuestaID, $respuestaMSJ);
    }

    public function reasignar($id)
    {
        $arbitro=Arbitro::findOrFail($id);
        $arbitros = Arbitro::SELECT('arbitros.*','personas.nombre','personas.apellido','personas.nacimiento','personas.fallecimiento','personas.foto')->JOIN('personas','personas.id','=','arbitros.persona_id')->orderBy('apellido', 'asc')->orderBy('nombre', 'asc')->get();
        //dd($arbitros);
        $arbitros = $arbitros->pluck('persona.full_name', 'id')->prepend('','');

        return view('arbitros.reasignar', compact('arbitro','arbitros'));
    }

    public function guardarReasignar(Request $request)
    {
        // Validar los datos de entrada
        $request->validate([
            'arbitroId' => 'required|integer|exists:arbitros,id',
            'arbitro_id' => 'required|integer|exists:arbitros,id|different:arbitroId',
        ]);

        $arbitroActual = $request->input('arbitroId');
        $arbitroNuevo = $request->input('arbitro_id');

        try {
            // Inicia una transacción para garantizar que todas las actualizaciones se completen
            DB::beginTransaction();

            // Actualizar en las tablas necesarias

            DB::update('UPDATE partido_arbitros SET arbitro_id = ? WHERE arbitro_id = ?', [$arbitroNuevo, $arbitroActual]);

            $arbitro = Arbitro::find($arbitroActual);
            $persona = Persona::find($arbitro->persona_id);
            $arbitro->delete();
// Verificar si la persona tiene una foto y eliminarla del servidor
            if ($persona->foto && file_exists(public_path('images/' . $persona->foto))) {
                //unlink(public_path('images/' . $persona->foto)); // Eliminar la foto del servidor
            }
            $persona->delete();
            // Confirmar la transacción
            DB::commit();

            // Redirigir con un mensaje de éxito
            return redirect()->route('jugadores.verificarPersonas')->with('success', 'Arbitro reasignado exitosamente.');
        } catch (\Exception $e) {
            Log::info('Error: ' . $e->getMessage(), []);
            // Revertir los cambios si hay algún error
            DB::rollBack();

            // Regresar con un mensaje de error
            return redirect()->back()->withErrors(['error' => 'Hubo un problema al reasignar el arbitro.']);
        }
    }
}
