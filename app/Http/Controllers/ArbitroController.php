<?php

namespace App\Http\Controllers;

use App\Arbitro;
use App\Persona;
use Carbon\Carbon;
use GuzzleHttp\Client;
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

    public function importarProcess(Request $request)
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
}
