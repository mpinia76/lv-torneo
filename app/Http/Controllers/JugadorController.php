<?php

namespace App\Http\Controllers;

use App\Alineacion;
use App\Fecha;
use App\Grupo;
use App\Jugador;
use App\Equipo;
use App\Gol;
use App\Cambio;
use App\Tarjeta;
use App\Partido;
use App\PartidoTecnico;
use App\Persona;
use App\PosicionTorneo;
use App\Tecnico;
use App\Titulo;
use App\Torneo;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Sunra\PhpSimple\HtmlDomParser;
use DB;
use GuzzleHttp\Client;
use Carbon\Carbon;
use App\Services\HttpHelper;
use Illuminate\Support\Facades\Cache;

use Illuminate\Pagination\LengthAwarePaginator;
class JugadorController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth')->except(['ver','jugados','goles','tarjetas','titulos','penals']);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {


        $data = $request->all();
        if ($request->has('buscarpor')){
            $nombre = $request->get('buscarpor');

            $request->session()->put('nombre_filtro_jugador', $request->get('buscarpor'));

        }
        else{
            $nombre = $request->session()->get('nombre_filtro_jugador');

        }

        // Página actual
        $page = $request->get('page', session('jugadores_page', 1));
        $request->session()->put('jugadores_page', $page);


        //$jugadores=Jugador::where('nombre','like',"%$nombre%")->orWhere('apellido','like',"%$nombre%")->orWhere('email','like',"%$nombre%")->orWhere('tipoJugador','like',"%$nombre%")->orWhere(DB::raw('TIMESTAMPDIFF(YEAR,nacimiento,CURDATE())'),'=',"$nombre")->orderBy('apellido','ASC')->paginate();

        $jugadores=Jugador::SELECT('jugadors.*','personas.name','personas.nombre','personas.apellido','personas.nacimiento','personas.fallecimiento','personas.ciudad','personas.nacionalidad','personas.foto')->Join('personas','personas.id','=','jugadors.persona_id')->where('nombre','like',"%$nombre%")->orWhere('apellido','like',"%$nombre%")->orWhere('name','like',"%$nombre%")->orWhere('tipoJugador','like',"%$nombre%")->orWhere(DB::raw('TIMESTAMPDIFF(YEAR,nacimiento,CURDATE())'),'=',"$nombre")->orderBy('apellido','ASC')->orderBy('nombre','ASC')->paginate(15, ['*'], 'page', $page)
            ->appends($data);

        //$jugadores=Jugador::where('persona_id','like',"%4914%")->paginate();

        //dd($jugadores);
        //
        //$jugadores=Jugador::orderBy('apellido','ASC')->paginate(2);
        //return view('Jugador.index',compact('jugadores'));
        //$jugadores = Jugador::all();
        return view('jugadores.index', compact('jugadores','jugadores', 'data'));
    }

    private function obtenerUrlJugador(Persona $persona, $alineacion = null)
    {
        $sanear = function ($txt) {
            return strtolower($this->sanear_string(str_replace(' ', '-', $txt)));
        };

        $nombreParts = explode(' ', trim($persona->nombre));
        $apellidoParts = explode(' ', trim($persona->apellido));

        // Detectar si nombre o apellido son compuestos
        $nombreCompleto = implode(' ', $nombreParts);
        $apellidoCompleto = implode(' ', $apellidoParts);

        $nombre = $nombreParts[0] ?? '';
        $nombre2 = $nombreParts[1] ?? '';
        $apellido = $apellidoParts[0] ?? '';
        $apellido2 = $apellidoParts[1] ?? '';

        //Log::info('Cache get', ['key' => $cacheKey, 'url' => $cachedUrl]);

        $html2 = null;

        if (!$html2) {
            // Función local para limpiar nombres
            $sanear = function ($txt) {
                return strtolower($this->sanear_string(str_replace(' ', '-', $txt)));
            };

            $intentos = array_filter([
                $sanear($persona->name),
                $sanear($apellidoCompleto) . '-' . $sanear($nombre),
                $sanear($nombre) . '-' . $sanear($apellidoCompleto),
                $sanear($apellido) . '-' . $sanear($nombreCompleto),
                $sanear($nombreCompleto) . '-' . $sanear($apellido),
                $sanear($apellido) . '-' . $sanear($nombre),
                $sanear($nombre) . '-' . $sanear($apellido),
                $sanear($apellido) . '-' . $sanear($nombre2),
                $sanear($persona->apellido),
                $sanear($persona->nombre),
            ]);

            $nacionalidadSlug = $sanear($persona->nacionalidad);

            $urls = [];
            foreach ($intentos as $slug) {
                $urls[] = "http://www.futbol360.com.ar/jugadores/{$nacionalidadSlug}/{$slug}";
                $urls[] = "http://www.futbol360.com.ar/jugadores/{$slug}";
            }

            foreach ($urls as $urlJugador) {
                $html = HttpHelper::getHtmlContent($urlJugador);

                if ($html) {
                    return [
                        'url'  => $urlJugador,
                        'html' => $html,
                    ];
                }
            }
        }

        return null;
    }

    private function obtenerNombreDesdeHtml($html)
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);

        $node = $xpath->query('//div[contains(@class,"route")]')->item(0);
        if (!$node) return null;

        $partes = array_map('trim', explode('/', $node->textContent));
        $nombreCrudo = end($partes);

        if (!$nombreCrudo) {
            return null;
        }

        // 🔹 Normalizar espacios
        $nombreCrudo = preg_replace('/\s+/', ' ', trim($nombreCrudo));

        // 🔹 Separar palabras
        $tokens = explode(' ', $nombreCrudo);

        // Si hay al menos 2 palabras, asumimos "Apellido Nombre"
        if (count($tokens) >= 2) {
            $apellido = array_shift($tokens);   // primero
            $nombre   = implode(' ', $tokens);  // resto
            return trim($nombre . ' ' . $apellido);
        }

        return $nombreCrudo;
    }



    public function nameCompletoNoVerificado(Request $request)
    {
        $data = $request->all();

        $jugadores = Jugador::select(
            'jugadors.*',
            'personas.id as persona_id',
            'personas.name',
            'personas.nombre',
            'personas.apellido',
            'personas.nacimiento',
            'personas.fallecimiento',
            'personas.ciudad',
            'personas.nacionalidad',
            'personas.foto',
            'personas.verificado'
        )
            ->join('personas', 'personas.id', '=', 'jugadors.persona_id')

            // name = nombre + apellido
            ->whereRaw("TRIM(personas.name) = TRIM(CONCAT(personas.nombre, ' ', personas.apellido))")

            // no verificados
            ->where(function ($q) {
                $q->where('personas.verificado', 0)
                    ->orWhereNull('personas.verificado');
            })

            ->orderBy('personas.apellido', 'ASC')
            ->orderBy('personas.nombre', 'ASC')
            ->paginate(15)
            ->appends($data);

        /**
         * 🔥 ACÁ agregamos el scraping de sugerencia
         *    SIN tocar persona
         */
        foreach ($jugadores as $jugador) {

            $persona = Persona::find($jugador->persona_id);

            $resultado = $this->obtenerUrlJugador($persona);

            if (!$resultado) {
                $jugador->nombre_sugerido = null;
                $jugador->url_sugerida = null;
                continue;
            }

            $nombreWeb = $this->obtenerNombreDesdeHtml($resultado['html']);

            $jugador->nombre_sugerido = $nombreWeb;
            $jugador->url_sugerida    = $resultado['url'];

            // ⚠️ no se guarda nada en DB
        }

        return view('jugadores.name_largo_verificar', compact('jugadores', 'data'));
    }

    function sanear_string($string)
    {

        $string = trim($string);

        $string = str_replace(
            array('á', 'à', 'ä', 'â', 'ã', 'ª', 'Á', 'À', 'Â', 'Ä'),
            array('a', 'a', 'a', 'a' , 'a', 'a', 'A', 'A', 'A', 'A'),
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

// Esta parte elimina cualquier carácter extraño, pero conserva guiones
        $string = preg_replace('/[^A-Za-z0-9\-]/', '', $string);



        return $string;
    }

    public function confirmarNombreLargo(Request $request, $personaId)
    {
        $persona = Persona::findOrFail($personaId);

        $persona->name = $request->name;
        $persona->verificado = 1;
        $persona->save();

        return back()->with('status', 'Nombre confirmado');
    }



    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {

        if ($request->get('plantillaId')){
            $plantilla_id = $request->get('plantillaId');
            $vista =view('jugadores.create', compact('plantilla_id'));
        }
        elseif($request->get('torneoId')){
            $torneo_id = $request->get('torneoId');
            $vista =view('jugadores.create', compact('torneo_id'));
        }
        else {
            $vista =view('jugadores.create');
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
        //
        //Log::info(print_r($request->file(), true));

        $this->validate($request,[ 'tipoJugador'=>'required','name'=>'required','nombre'=>'required', 'apellido'=>'required','foto' => 'image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048']);


        if ($files = $request->file('foto')) {
            $image = $request->file('foto');
            $name = time().'.'.$image->getClientOriginalExtension();
            $destinationPath = public_path('/images');
            $image->move($destinationPath, $name);


            /*$destinationPath = 'public/image/'; // upload path
            $profileImage = date('YmdHis') . "." . $files->getClientOriginalExtension();
            Log::info($profileImage);
            $files->move($destinationPath, $profileImage);*/
            $insert['foto'] = "$name";
        }
        $insert['name'] = $request->get('name');
        $insert['nombre'] = $request->get('nombre');
        $insert['apellido'] = $request->get('apellido');
        $insert['email'] = $request->get('email');
        $insert['telefono'] = $request->get('telefono');
        $insert['ciudad'] = $request->get('ciudad');
        $insert['nacionalidad'] = $request->get('nacionalidad');
        $insert['altura'] = $request->get('altura');
        $insert['peso'] = $request->get('peso');
        $insert['observaciones'] = $request->get('observaciones');
        $insert['tipoDocumento'] = $request->get('tipoDocumento');
        $insert['documento'] = $request->get('documento');
        $insert['nacimiento'] = $request->get('nacimiento');
        $insert['fallecimiento'] = $request->get('fallecimiento');

        $insert['tipoJugador'] = $request->get('tipoJugador');

        $insert['pie'] = $request->get('pie');
        $insert['url_nombre'] = $request->get('url_nombre');



        try {
            $persona = Persona::create($insert);
            $persona->jugador()->create($insert);

            $respuestaID='success';
            $respuestaMSJ='Registro creado satisfactoriamente';
        }catch(QueryException $ex){

            try {
                $persona = Persona::where('nombre','=',$insert['nombre'])->Where('apellido','=',$insert['apellido'])->Where('nacimiento','=',$insert['nacimiento'])->first();
                if (!empty($persona)){
                    $persona->update($insert);
                    $persona->jugador()->create($insert);
                    $respuestaID='success';
                    $respuestaMSJ='Registro creado satisfactoriamente - existe como tecnico';
                }
            }catch(QueryException $ex){

                $respuestaID='error';
                $errorCode = $ex->errorInfo[1];

                if ($errorCode == 1062) {
                    $respuestaMSJ='Jugador repetido';
                }
                //$respuestaMSJ=$ex->getMessage();

            }


        }

        if($request->get('plantilla_id')){
            $plantilla_id = $request->get('plantilla_id');
            $redirect = redirect()->route('plantillas.edit',[$plantilla_id])->with($respuestaID,$respuestaMSJ);

        }
        elseif($request->get('torneo_id')){
            $redirect = redirect()->route('plantillas.create', ['grupoId' => $request->get('grupo_id')])->with($respuestaID,$respuestaMSJ);
        }
        else{
            $redirect = redirect()->route('jugadores.index')->with($respuestaID,$respuestaMSJ);
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
        $jugador=Jugador::findOrFail($id);
        return view('jugadores.show', compact('jugador','jugador'));
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
        $jugador=jugador::findOrFail($id);

        return view('jugadores.edit', compact('jugador','jugador'));
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
        $this->validate($request,[ 'tipoJugador'=>'required','name'=>'required','nombre'=>'required', 'apellido'=>'required','foto' => 'image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048']);


        if ($files = $request->file('foto')) {
            $image = $request->file('foto');
            $name = time().'.'.$image->getClientOriginalExtension();
            $destinationPath = public_path('/images');
            $image->move($destinationPath, $name);


            /*$destinationPath = 'public/image/'; // upload path
            $profileImage = date('YmdHis') . "." . $files->getClientOriginalExtension();
            Log::info($profileImage);
            $files->move($destinationPath, $profileImage);*/
            $update['foto'] = "$name";
        }

        $update['name'] = $request->get('name');
        $update['nombre'] = $request->get('nombre');
        $update['apellido'] = $request->get('apellido');
        $update['email'] = $request->get('email');
        $update['telefono'] = $request->get('telefono');
        $update['ciudad'] = $request->get('ciudad');
        $update['nacionalidad'] = $request->get('nacionalidad');
        $update['altura'] = $request->get('altura');
        $update['peso'] = $request->get('peso');
        $update['observaciones'] = $request->get('observaciones');
        $update['tipoDocumento'] = $request->get('tipoDocumento');
        $update['documento'] = $request->get('documento');
        $update['nacimiento'] = $request->get('nacimiento');
        $update['fallecimiento'] = $request->get('fallecimiento');
        $update['verificado'] = $request->get('verificado');

        $updateJ['tipoJugador'] = $request->get('tipoJugador');

        $updateJ['pie'] = $request->get('pie');
        $updateJ['url_nombre'] = $request->get('url_nombre');




        $jugador=jugador::find($id);
        $jugador->update($updateJ);
        $jugador->persona()->update($update);
        // 💡 Invalida la cache del slug del jugador
        Cache::forget('slug_jugador_' . $jugador->id);

        return redirect()->route('jugadores.index')->with('success','Registro actualizado satisfactoriamente');

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $jugador = Jugador::find($id);

        $jugador->delete();
        $persona = Persona::find($jugador->persona_id);
        // Verificar si la persona tiene una foto y eliminarla del servidor
        if ($persona->foto && file_exists(public_path('images/' . $persona->foto))) {
            unlink(public_path('images/' . $persona->foto)); // Eliminar la foto del servidor
        }
        $persona->delete();
        return redirect()->route('jugadores.index')->with('success','Registro eliminado satisfactoriamente');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function ver(Request $request)
    {
        $id= $request->query('jugadorId');
        $jugador=Jugador::findOrFail($id);



        $sql = 'SELECT torneos.id as idTorneo, CONCAT(torneos.nombre," ",torneos.year) AS nombreTorneo, "" AS escudo, "0" AS jugados, "0" AS goles, "0" AS amarillas, "0" AS rojas, "0" AS errados, "0" AS atajados, "0" recibidos, "0" invictas, torneos.tipo, torneos.ambito, torneos.escudo as escudoTorneo
FROM torneos INNER JOIN grupos ON torneos.id = grupos.torneo_id
INNER JOIN plantillas ON grupos.id = plantillas.grupo_id
INNER JOIN plantilla_jugadors ON plantillas.id = plantilla_jugadors.plantilla_id
WHERE plantilla_jugadors.jugador_id = '.$id.'
GROUP BY torneos.id, torneos.nombre,torneos.year, torneos.tipo, torneos.ambito
ORDER BY torneos.year DESC, torneos.id DESC';




        $torneosJugador = DB::select(DB::raw($sql));
        $titulosJugadorCopa=0;
        $titulosJugadorLiga=0;
        $titulosJugadorInternacional=0;
        foreach ($torneosJugador as $torneo){

            // GRUPOS
            $arrgrupos = Grupo::where('torneo_id', $torneo->idTorneo)
                ->pluck('id')
                ->implode(',');

            // FECHAS
            $arrfechas = Fecha::whereIn('grupo_id', explode(',', $arrgrupos))
                ->pluck('id')
                ->implode(',');

            $arrpartidos = Partido::whereIn('fecha_id', explode(',', $arrfechas))
                ->pluck('id')
                ->implode(',');


            $posicionTorneo = PosicionTorneo::where('torneo_id', '=',$torneo->idTorneo)->where('posicion', '=',1)->first();

            if(!empty($posicionTorneo)){
                //if ($posicionTorneo->posicion == 1){

                $alineacion = Alineacion::whereIn('partido_id', explode(',', $arrpartidos))->where('equipo_id','=',$posicionTorneo->equipo_id)->where('jugador_id','=',$id)->first();




                //print_r($partidoTecnico);
                if(!empty($alineacion)) {
                    //if ((stripos($torneo->nombreTorneo, 'Copa') !== false)||(stripos($torneo->nombreTorneo, 'Trofeo') !== false)) {
                    if ($torneo->ambito == 'Nacional'){
                        if ($torneo->tipo == 'Copa') {
                            $titulosJugadorCopa++;
                        } else {
                            $titulosJugadorLiga++;
                        }
                    }
                    else{
                        $titulosJugadorInternacional++;
                    }
                }
                //}
            }



            $sqlEscudos='SELECT DISTINCT escudo, equipo_id, equipos.nombre
FROM equipos
INNER JOIN alineacions ON equipos.id = alineacions.equipo_id
INNER JOIN partidos ON partidos.id = alineacions.partido_id
WHERE alineacions.jugador_id = '.$id.' AND alineacions.partido_id IN ('.$arrpartidos.')
ORDER BY partidos.dia ASC';



            $escudos = DB::select(DB::raw($sqlEscudos));


            foreach ($escudos as $escudo){
                $strPosicion='';
                $posicionTorneo = PosicionTorneo::where('torneo_id', '=',$torneo->idTorneo)->where('equipo_id', '=',$escudo->equipo_id)->first();

                if(!empty($posicionTorneo)){

                    $alineacion = Alineacion::whereIn('partido_id', explode(',', $arrpartidos))->where('equipo_id','=',$posicionTorneo->equipo_id)->where('jugador_id','=',$id)->first();




                    //print_r($partidoTecnico);
                    if(!empty($alineacion)) {
                        $strPosicion = (!empty($posicionTorneo)) ? (
                        ($posicionTorneo->posicion == 1) ?
                            '<img id="original" src="' . asset('images/campeon.png') . '" height="20"> Campeón' :
                            (($posicionTorneo->posicion == 2) ? '<img id="original" src="' . asset('images/subcampeon.png') . '" height="20">Subcampeón' : $posicionTorneo->posicion)
                        ) : '';
                    }

                }

                $torneo->escudo .= $escudo->escudo.'_'.$escudo->equipo_id.'_'.$strPosicion.'_'.$escudo->nombre.',';
                //$torneo->escudo .= $escudo->escudo.'_'.$escudo->equipo_id.',';
            }

            $sqlTitular="SELECT alineacions.jugador_id, COUNT(alineacions.jugador_id) as jugados
FROM torneos t2 INNER JOIN grupos g2 ON t2.id = g2.torneo_id
INNER JOIN fechas ON fechas.grupo_id = g2.id
INNER JOIN partidos ON partidos.fecha_id = fechas.id
INNER JOIN alineacions ON alineacions.partido_id = partidos.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id
WHERE alineacions.tipo = 'Titular' AND grupos.torneo_id=".$torneo->idTorneo." AND grupos.id IN (".$arrgrupos.") AND alineacions.jugador_id = ".$id. " GROUP BY alineacions.jugador_id";

            //echo $sql3;

            $jugados = DB::select(DB::raw($sqlTitular));


            foreach ($jugados as $jugado){

                $torneo->jugados += $jugado->jugados;
            }

            $sql4="SELECT cambios.jugador_id, COUNT(cambios.jugador_id)  as jugados
FROM torneos t2 INNER JOIN grupos g2 ON t2.id = g2.torneo_id
INNER JOIN fechas ON fechas.grupo_id = g2.id
INNER JOIN partidos ON partidos.fecha_id = fechas.id
INNER JOIN cambios ON cambios.partido_id = partidos.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id
WHERE cambios.tipo = 'Entra' AND grupos.torneo_id=".$torneo->idTorneo." AND grupos.id IN (".$arrgrupos.") AND cambios.jugador_id = ".$id. " GROUP BY cambios.jugador_id";



            $jugados = DB::select(DB::raw($sql4));


            foreach ($jugados as $jugado){

                $torneo->jugados += $jugado->jugados;
            }

            $sqlGoles = 'SELECT COUNT(gols.id) goles
FROM gols
INNER JOIN partidos ON gols.partido_id = partidos.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id

WHERE gols.tipo <> \'En contra\' AND grupos.torneo_id='.$torneo->idTorneo.' AND grupos.id IN ('.$arrgrupos.') AND gols.jugador_id = '.$id;




            $goleadores = DB::select(DB::raw($sqlGoles));

            foreach ($goleadores as $gol){

                $torneo->goles += $gol->goles;
            }

            $sqlTarjetas = 'SELECT count( case when tipo=\'Amarilla\' then 1 else NULL end) as  amarillas
, count( case when tipo=\'Roja\' or tipo=\'Doble Amarilla\' then 1 else NULL end) as  rojas
FROM tarjetas

INNER JOIN partidos ON tarjetas.partido_id = partidos.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id

WHERE  grupos.torneo_id='.$torneo->idTorneo.' AND grupos.id IN ('.$arrgrupos.') AND tarjetas.jugador_id = '.$id;


            $tarjetas = DB::select(DB::raw($sqlTarjetas));

            foreach ($tarjetas as $tarjeta){
                //Log::info('Tarjetas: '.$torneo->amarillas.' -> '.$tarjeta->amarillas);
                $torneo->amarillas += $tarjeta->amarillas;
                $torneo->rojas += $tarjeta->rojas;
            }

            $sqlPenals = 'SELECT count( case when tipo=\'Atajó\' then 1 else NULL end) as  atajados
, count( case when tipo=\'Errado\' or tipo=\'Atajado\' then 1 else NULL end) as  errados
FROM penals

INNER JOIN partidos ON penals.partido_id = partidos.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id

WHERE  grupos.torneo_id='.$torneo->idTorneo.' AND grupos.id IN ('.$arrgrupos.') AND penals.jugador_id = '.$id;


            $penals = DB::select(DB::raw($sqlPenals));

            foreach ($penals as $penal){
                //Log::info('Penals: '.$torneo->amarillas.' -> '.$penal->amarillas);
                $torneo->errados += $penal->errados;
                $torneo->atajados += $penal->atajados;
            }

            $sqlArqueros = 'SELECT case when alineacions.equipo_id=partidos.equipol_id then partidos.golesv else partidos.golesl END AS recibidos,
case when alineacions.equipo_id=partidos.equipol_id and partidos.golesv = 0 then 1 else CASE when alineacions.equipo_id=partidos.equipov_id and partidos.golesl = 0 THEN 1 ELSE 0 END END AS invictas, personas.foto, "" escudo
FROM alineacions
INNER JOIN jugadors ON alineacions.jugador_id = jugadors.id AND jugadors.tipoJugador = \'Arquero\'
INNER JOIN partidos ON alineacions.partido_id = partidos.id
INNER JOIN personas ON jugadors.persona_id = personas.id
INNER JOIN fechas ON partidos.fecha_id = fechas.id
INNER JOIN grupos ON grupos.id = fechas.grupo_id

WHERE  alineacions.tipo = \'Titular\'  AND grupos.torneo_id='.$torneo->idTorneo.' AND grupos.id IN ('.$arrgrupos.') AND jugadors.id = '.$id;


            $arqueros = DB::select(DB::raw($sqlArqueros));

            foreach ($arqueros as $arquero){

                $torneo->recibidos += $arquero->recibidos;
                $torneo->invictas += $arquero->invictas;
            }

        }

        $tecnico = Tecnico::where('persona_id', '=', $jugador->persona_id)->first();
        $sql = 'SELECT torneos.id as idTorneo, CONCAT(torneos.nombre," ",torneos.year) AS nombreTorneo, "" AS escudo, "0" AS jugados, "0" AS ganados, "0" AS perdidos, "0" AS empatados, "0" AS favor, "0" AS contra, "0" AS puntaje, "0" as porcentaje, tecnicos.id as idTecnico, torneos.tipo, torneos.ambito, torneos.escudo as escudoTorneo
FROM torneos INNER JOIN grupos ON torneos.id = grupos.torneo_id
INNER JOIN fechas ON grupos.id = fechas.grupo_id
INNER JOIN partidos ON fechas.id = partidos.fecha_id
INNER JOIN partido_tecnicos ON partidos.id = partido_tecnicos.partido_id
INNER JOIN tecnicos ON tecnicos.id = partido_tecnicos.tecnico_id
WHERE tecnicos.persona_id = '.$jugador->persona_id.'
GROUP BY torneos.id, torneos.nombre,torneos.year, tecnicos.id, torneos.tipo, torneos.ambito
ORDER BY torneos.year DESC';




        $torneosTecnico = DB::select(DB::raw($sql));

        $titulosTecnicoCopa=0;
        $titulosTecnicoLiga=0;
        $titulosTecnicoInternacional=0;
        foreach ($torneosTecnico as $torneo){
            // GRUPOS
            $arrgrupos = Grupo::where('torneo_id', $torneo->idTorneo)
                ->pluck('id')
                ->implode(',');

            // FECHAS
            $arrfechas = Fecha::whereIn('grupo_id', explode(',', $arrgrupos))
                ->pluck('id')
                ->implode(',');

            $arrpartidos = Partido::whereIn('fecha_id', explode(',', $arrfechas))
                ->pluck('id')
                ->implode(',');

            $posicionTorneo = PosicionTorneo::where('torneo_id', '=',$torneo->idTorneo)->where('posicion', '=',1)->first();

            if(!empty($posicionTorneo)){
                //if ($posicionTorneo->posicion == 1){
                $ultimoPartido = Partido::whereIn('fecha_id', explode(',', $arrfechas))
                    ->where(function ($query) use ($posicionTorneo) {
                        $query->where('equipol_id', $posicionTorneo->equipo_id)
                            ->orWhere('equipov_id', $posicionTorneo->equipo_id);
                    })
                    ->orderBy('dia', 'DESC')
                    ->first();
                $tecnico = Tecnico::where('persona_id', '=', $jugador->persona_id)->first();
                $partidoTecnico = PartidoTecnico::where('partido_id','=',"$ultimoPartido->id")->where('equipo_id','=',$posicionTorneo->equipo_id)->where('tecnico_id','=',$tecnico->id)->first();
                //print_r($partidoTecnico);
                if(!empty($partidoTecnico)) {
                    //if ((stripos($torneo->nombreTorneo, 'Copa') !== false)||(stripos($torneo->nombreTorneo, 'Trofeo') !== false)) {
                    if ($torneo->ambito == 'Nacional'){
                        if ($torneo->tipo == 'Copa') {
                            $titulosTecnicoCopa++;
                        } else {
                            $titulosTecnicoLiga++;
                        }
                    }
                    else{
                        $titulosTecnicoInternacional++;
                    }
                }
                //}
            }

            $sqlEscudos='SELECT DISTINCT escudo, equipo_id, equipos.nombre
FROM equipos
INNER JOIN partido_tecnicos ON equipos.id = partido_tecnicos.equipo_id
INNER JOIN partidos ON partidos.id = partido_tecnicos.partido_id
INNER JOIN tecnicos ON tecnicos.id = partido_tecnicos.tecnico_id
WHERE tecnicos.persona_id = '.$jugador->persona_id.' AND partido_tecnicos.partido_id IN ('.$arrpartidos.')
ORDER BY partidos.dia ASC';



            $escudos = DB::select(DB::raw($sqlEscudos));


            foreach ($escudos as $escudo){
                $strPosicion='';
                $posicionTorneo = PosicionTorneo::where('torneo_id', '=',$torneo->idTorneo)->where('equipo_id', '=',$escudo->equipo_id)->first();

                if(!empty($posicionTorneo)){
                    //if ($posicionTorneo->posicion == 1){
                    $ultimoPartido = Partido::whereIn('fecha_id', explode(',', $arrfechas))
                        ->where(function ($query) use ($posicionTorneo) {
                            $query->where('equipol_id', $posicionTorneo->equipo_id)
                                ->orWhere('equipov_id', $posicionTorneo->equipo_id);
                        })
                        ->orderBy('dia', 'DESC')
                        ->first();

                    $partidoTecnico = PartidoTecnico::where('partido_id','=',"$ultimoPartido->id")->where('equipo_id','=',$posicionTorneo->equipo_id)->where('tecnico_id','=',$tecnico->id)->first();

                    if(!empty($partidoTecnico)) {
                        $strPosicion = (!empty($posicionTorneo)) ? (
                        ($posicionTorneo->posicion == 1) ?
                            '<img id="original" src="' . asset('images/campeon.png') . '" height="20"> Campeón' :
                            (($posicionTorneo->posicion == 2) ? '<img id="original" src="' . asset('images/subcampeon.png') . '" height="20">Subcampeón' : $posicionTorneo->posicion)
                        ) : '';
                    }

                }

                $torneo->escudo .= $escudo->escudo.'_'.$escudo->equipo_id.'_'.$strPosicion.'_'.$escudo->nombre.',';

            }

            $sqlJugados="SELECT count(*)  as jugados, count(case when golesl > golesv then 1 end) ganados,
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
    ), '%') porcentaje
    from (
       select  DISTINCT tecnicos.id tecnico_id, golesl, golesv, fechas.id fecha_id
		 from partidos
		 INNER JOIN equipos ON partidos.equipol_id = equipos.id
		 INNER JOIN plantillas ON plantillas.equipo_id = equipos.id
		 INNER JOIN fechas ON partidos.fecha_id = fechas.id
		 INNER JOIN grupos ON fechas.grupo_id = grupos.id
		 INNER JOIN partido_tecnicos ON partidos.id = partido_tecnicos.partido_id AND equipos.id = partido_tecnicos.equipo_id
		 INNER JOIN tecnicos ON tecnicos.id = partido_tecnicos.tecnico_id
		 WHERE golesl is not null AND golesv is not null AND grupos.torneo_id=".$torneo->idTorneo." AND grupos.id IN (".$arrgrupos.") AND tecnicos.persona_id = ".$jugador->persona_id."
     union all
       select DISTINCT tecnicos.id tecnico_id, golesv, golesl, fechas.id fecha_id
		 from partidos
		 INNER JOIN equipos ON partidos.equipov_id = equipos.id
		 INNER JOIN plantillas ON plantillas.equipo_id = equipos.id
		 INNER JOIN fechas ON partidos.fecha_id = fechas.id
		 INNER JOIN grupos ON fechas.grupo_id = grupos.id
		 INNER JOIN partido_tecnicos ON partidos.id = partido_tecnicos.partido_id AND equipos.id = partido_tecnicos.equipo_id
		 INNER JOIN tecnicos ON tecnicos.id = partido_tecnicos.tecnico_id
		 WHERE golesl is not null AND golesv is not null AND grupos.torneo_id=".$torneo->idTorneo." AND grupos.id IN (".$arrgrupos.") AND tecnicos.persona_id = ".$jugador->persona_id."
) a
group by tecnico_id
";

            //echo $sql3;

            $jugados = DB::select(DB::raw($sqlJugados));


            foreach ($jugados as $jugado){

                $torneo->jugados = $jugado->jugados;
                $torneo->ganados = $jugado->ganados;
                $torneo->empatados = $jugado->empatados;
                $torneo->perdidos = $jugado->perdidos;
                $torneo->favor = $jugado->golesl;
                $torneo->contra = $jugado->golesv;
                $torneo->puntaje = $jugado->puntaje;
                $torneo->porcentaje = $jugado->porcentaje;
            }
        }

        // --- Inicializa array para no duplicar conteos por mismo torneo ---
        $countedTorneos = []; // guardará ids de torneos ya contados para este técnico

// --- 1) (Opcional) si ya contás torneos desde $torneosTecnico dejalo tal cual ---
// Aquí asumes que ya corriste tu foreach($torneosTecnico as $torneo) y actualizaste:
/// $titulosTecnicoCopa, $titulosTecnicoLiga, $titulosTecnicoInternacional

// --- 2) Ahora procesamos los "titulos" manuales (tabla titulos) ---
        $titulosExtras = Titulo::with('torneos')->get(); // podés filtrar por rango de años si querés

        foreach ($titulosExtras as $titulo) {
            // el equipo del título
            $equipoId = $titulo->equipo_id;

            // recorremos cada torneo asociado a este título (pivot titulo_torneos)
            foreach ($titulo->torneos as $torneoRelacionado) {

                // evitamos contar el mismo torneo más de una vez
                if (in_array($torneoRelacionado->id, $countedTorneos)) {
                    continue;
                }

                // buscamos el ÚLTIMO partido DEL EQUIPO dentro de ese torneo
                $ultimoPartidoEquipo = Partido::whereHas('fecha.grupo', function($q) use ($torneoRelacionado) {
                    $q->where('torneo_id', $torneoRelacionado->id);
                })
                    ->where(function ($q) use ($equipoId) {
                        $q->where('equipol_id', $equipoId)
                            ->orWhere('equipov_id', $equipoId);
                    })
                    ->orderBy('dia', 'DESC')
                    ->first();

                // si no hay partido del equipo en ese torneo, no contamos
                if (empty($ultimoPartidoEquipo)) {
                    continue;
                }

                // verificamos si el técnico dirigió ese partido para ese equipo
                $dirigio = PartidoTecnico::where('partido_id', $ultimoPartidoEquipo->id)
                    ->where('tecnico_id', $id)          // $id = id del técnico actual en tu método
                    ->where('equipo_id', $equipoId)
                    ->exists();

                if ($dirigio) {
                    // contamos según tipo/ambito del torneo relacionado
                    if ($torneoRelacionado->ambito == 'Nacional') {
                        if ($torneoRelacionado->tipo == 'Copa') {
                            $titulosTecnicoCopa++;
                        } else {
                            $titulosTecnicoLiga++;
                        }
                    } else {
                        $titulosTecnicoInternacional++;
                    }

                    // marcamos que ya contamos este torneo (para evitar duplicados)
                    $countedTorneos[] = $torneoRelacionado->id;

                    // (Opcional) podés agregar este torneo a un listado para mostrar luego
                    //$torneosTitulos[] = $torneoRelacionado;
                }


                // === CALCULAR PARTIDOS DEL TORNEO RELACIONADO ===

// GRUPOS
                $arrgrupos = Grupo::where('torneo_id', $torneoRelacionado->id)
                    ->pluck('id')
                    ->toArray();

// FECHAS
                $arrfechas = Fecha::whereIn('grupo_id', $arrgrupos)
                    ->pluck('id')
                    ->toArray();

// PARTIDOS
                $arrpartidos = Partido::whereIn('fecha_id', $arrfechas)
                    ->pluck('id')
                    ->toArray();


                $consultarJugador = Jugador::where('persona_id', '=', $jugador->persona_id)->first();
                $alineacion = Alineacion::whereIn('partido_id', $arrpartidos)
                    ->where('equipo_id', $equipoId)
                    ->where('jugador_id', $consultarJugador->id)
                    ->first();



                //print_r($partidoTecnico);
                if(!empty($alineacion)) {
                    //if ((stripos($torneo->nombreTorneo, 'Copa') !== false)||(stripos($torneo->nombreTorneo, 'Trofeo') !== false)) {
                    if ($torneo->ambito == 'Nacional') {
                        if ($torneo->tipo == 'Copa') {
                            $titulosJugadorCopa++;
                        } else {
                            $titulosJugadorLiga++;
                        }
                    } else {
                        $titulosJugadorInternacional++;
                    }
                }


            }
        }

        return view('jugadores.ver', compact('jugador','torneosJugador','torneosTecnico','titulosTecnicoLiga','titulosTecnicoCopa','titulosJugadorLiga','titulosJugadorCopa','titulosJugadorInternacional','titulosTecnicoInternacional','tecnico'));
    }

    public function jugados(Request $request)
    {
        $id = $request->query('jugadorId');
        $jugador = Jugador::findOrFail($id);

        $idTorneo = $request->query('torneoId') ?? '';
        $torneo = $idTorneo ? Torneo::findOrFail($idTorneo) : null;

        $tipo = $request->query('tipo') ?? '';

        // --- Estadísticas globales (jugados, ganados, empatados, perdidos) ---
        $sql = "
        SELECT
            COUNT(*) as jugados,
            COUNT(CASE WHEN golesl > golesv THEN 1 END) as ganados,
            COUNT(CASE WHEN golesv > golesl THEN 1 END) as perdidos,
            COUNT(CASE WHEN golesl = golesv THEN 1 END) as empatados
        FROM (
            SELECT alineacions.jugador_id, golesl, golesv
            FROM partidos
            INNER JOIN equipos ON partidos.equipol_id = equipos.id
            INNER JOIN alineacions ON partidos.id = alineacions.partido_id AND equipos.id = alineacions.equipo_id
            LEFT JOIN cambios ON cambios.partido_id = partidos.id AND cambios.jugador_id = alineacions.jugador_id
            INNER JOIN fechas ON partidos.fecha_id = fechas.id
            INNER JOIN grupos ON fechas.grupo_id = grupos.id
            WHERE golesl IS NOT NULL AND golesv IS NOT NULL
              AND ((alineacions.tipo = 'Titular' AND alineacions.jugador_id = $id)
                OR (cambios.tipo = 'Entra' AND cambios.jugador_id = $id))
              " . ($idTorneo ? " AND grupos.torneo_id = $idTorneo" : "") . "

            UNION ALL

            SELECT alineacions.jugador_id, golesv AS golesl, golesl AS golesv
            FROM partidos
            INNER JOIN equipos ON partidos.equipov_id = equipos.id
            INNER JOIN alineacions ON partidos.id = alineacions.partido_id AND equipos.id = alineacions.equipo_id
            LEFT JOIN cambios ON cambios.partido_id = partidos.id AND cambios.jugador_id = alineacions.jugador_id
            INNER JOIN fechas ON partidos.fecha_id = fechas.id
            INNER JOIN grupos ON fechas.grupo_id = grupos.id
            WHERE golesl IS NOT NULL AND golesv IS NOT NULL
              AND ((alineacions.tipo = 'Titular' AND alineacions.jugador_id = $id)
                OR (cambios.tipo = 'Entra' AND cambios.jugador_id = $id))
              " . ($idTorneo ? " AND grupos.torneo_id = $idTorneo" : "") . "
        ) a
    ";

        $jugados = DB::selectOne(DB::raw($sql));

        $totalJugados   = $jugados->jugados ?? 0;
        $totalGanados   = $jugados->ganados ?? 0;
        $totalEmpatados = $jugados->empatados ?? 0;
        $totalPerdidos  = $jugados->perdidos ?? 0;

        // --- Listado de partidos ---
        $sqlPartidos = "
        SELECT
            torneos.nombre AS nombreTorneo,
            torneos.escudo AS escudoTorneo,
            torneos.year,
            fechas.numero,
            partidos.dia,
            e1.id AS equipol_id,
            e1.escudo AS fotoLocal,
            e1.nombre AS local,
            e2.id AS equipov_id,
            e2.escudo AS fotoVisitante,
            e2.nombre AS visitante,
            partidos.golesl,
            partidos.golesv,
            partidos.penalesl,
            partidos.penalesv,
            partidos.id as partido_id,
            alineacions.equipo_id as equipoJugador
        FROM partidos
        INNER JOIN equipos e1 ON partidos.equipol_id = e1.id
        INNER JOIN equipos e2 ON partidos.equipov_id = e2.id
        INNER JOIN fechas ON partidos.fecha_id = fechas.id
        INNER JOIN grupos ON fechas.grupo_id = grupos.id
        INNER JOIN torneos ON grupos.torneo_id = torneos.id
        INNER JOIN alineacions ON alineacions.partido_id = partidos.id
        LEFT JOIN cambios ON cambios.partido_id = partidos.id AND cambios.jugador_id = alineacions.jugador_id
        WHERE ((alineacions.tipo = 'Titular' AND alineacions.jugador_id = $id)
            OR (cambios.tipo = 'Entra' AND cambios.jugador_id = $id))
            " . ($idTorneo ? " AND grupos.torneo_id = $idTorneo" : "") . "
    ";

        // Filtros por tipo
        if ($tipo === 'Ganados') {
            $sqlPartidos .= " AND ((alineacions.equipo_id = e1.id AND partidos.golesl > partidos.golesv)
                          OR (alineacions.equipo_id = e2.id AND partidos.golesv > partidos.golesl))";
        } elseif ($tipo === 'Empatados') {
            $sqlPartidos .= " AND partidos.golesl = partidos.golesv";
        } elseif ($tipo === 'Perdidos') {
            $sqlPartidos .= " AND ((alineacions.equipo_id = e1.id AND partidos.golesl < partidos.golesv)
                          OR (alineacions.equipo_id = e2.id AND partidos.golesv < partidos.golesl))";
        }

        $sqlPartidos .= " ORDER BY partidos.dia DESC";

        // Usar paginación nativa
        $partidos = DB::table(DB::raw("($sqlPartidos) as sub"))
            ->paginate(15)
            ->appends($request->query());

        return view('jugadores.jugados', compact(
            'jugador',
            'torneo',
            'totalJugados',
            'totalGanados',
            'totalEmpatados',
            'totalPerdidos',
            'partidos',
            'tipo'
        ));
    }


    public function goles(Request $request)
    {
        $id = $request->query('jugadorId');
        $jugador = Jugador::findOrFail($id);

        $idTorneo = $request->query('torneoId') ?? '';
        $torneo = $idTorneo ? Torneo::findOrFail($idTorneo) : null;

        $tipo = $request->query('tipo') ?? '';

        // Estadísticas totales del jugador
        $sqlStats = "
        SELECT
            COUNT(gols.id) AS totalTodos,
            COUNT(CASE WHEN tipo = 'Jugada' THEN 1 END) AS totalJugada,
            COUNT(CASE WHEN tipo = 'Cabeza' THEN 1 END) AS totalCabeza,
            COUNT(CASE WHEN tipo = 'Penal' THEN 1 END) AS totalPenal,
            COUNT(CASE WHEN tipo = 'Tiro Libre' THEN 1 END) AS totalTiroLibre
        FROM gols
        INNER JOIN jugadors ON gols.jugador_id = jugadors.id
        INNER JOIN partidos ON gols.partido_id = partidos.id
        INNER JOIN fechas ON partidos.fecha_id = fechas.id
        INNER JOIN grupos ON fechas.grupo_id = grupos.id
        WHERE gols.tipo <> 'En contra'
          AND jugadors.id = :jugadorId
          " . ($idTorneo ? " AND grupos.torneo_id = :torneoId" : "") . "
    ";

        $bindings = ['jugadorId' => $id];
        if ($idTorneo) $bindings['torneoId'] = $idTorneo;

        $golStats = DB::selectOne(DB::raw($sqlStats), $bindings);

        $totalTodos = $golStats->totalTodos ?? 0;
        $totalJugada = $golStats->totalJugada ?? 0;
        $totalCabeza = $golStats->totalCabeza ?? 0;
        $totalPenal = $golStats->totalPenal ?? 0;
        $totalTiroLibre = $golStats->totalTiroLibre ?? 0;

        // Lista de partidos donde hizo goles
        $sqlPartidos = "
        SELECT DISTINCT
            torneos.nombre AS nombreTorneo,
            torneos.escudo AS escudoTorneo,
            torneos.year,
            fechas.numero,
            partidos.dia,
            e1.id AS equipol_id,
            e1.escudo AS fotoLocal,
            e1.nombre AS local,
            e2.id AS equipov_id,
            e2.escudo AS fotoVisitante,
            e2.nombre AS visitante,
            partidos.golesl,
            partidos.golesv,
            partidos.penalesl,
            partidos.penalesv,
            partidos.id AS partido_id,
            gols.tipo AS tipoGol
        FROM partidos
        INNER JOIN equipos e1 ON partidos.equipol_id = e1.id
        INNER JOIN equipos e2 ON partidos.equipov_id = e2.id
        INNER JOIN fechas ON partidos.fecha_id = fechas.id
        INNER JOIN grupos ON fechas.grupo_id = grupos.id
        INNER JOIN torneos ON grupos.torneo_id = torneos.id
        INNER JOIN alineacions ON alineacions.partido_id = partidos.id
        INNER JOIN gols ON gols.partido_id = partidos.id AND gols.jugador_id = alineacions.jugador_id
        WHERE alineacions.jugador_id = :jugadorId
          " . ($idTorneo ? " AND grupos.torneo_id = :torneoId" : "") . "
          " . ($tipo ? " AND gols.tipo = :tipoGol" : "") . "
        ORDER BY partidos.dia DESC
    ";

        $bindingsPartidos = ['jugadorId' => $id];
        if ($idTorneo) $bindingsPartidos['torneoId'] = $idTorneo;
        if ($tipo) $bindingsPartidos['tipoGol'] = $tipo;

        $partidosRaw = DB::select(DB::raw($sqlPartidos), $bindingsPartidos);

        // Paginación manual
        $page = $request->query('page', 1);
        $paginate = 15;
        $offSet = ($page * $paginate) - $paginate;
        $itemsForCurrentPage = array_slice($partidosRaw, $offSet, $paginate, true);

        $partidos = new \Illuminate\Pagination\LengthAwarePaginator(
            $itemsForCurrentPage,
            count($partidosRaw),
            $paginate,
            $page
        );

        // Mantener query params en la paginación
        $arrayParam = ['jugadorId' => $id];
        if ($idTorneo) $arrayParam['torneoId'] = $idTorneo;
        if ($tipo) $arrayParam['tipo'] = $tipo;

        $partidos->setPath(route('jugadores.goles', $arrayParam));

        return view('jugadores.goles', compact(
            'jugador',
            'torneo',
            'totalTodos',
            'totalJugada',
            'totalCabeza',
            'totalPenal',
            'totalTiroLibre',
            'partidos',
            'tipo'
        ));
    }


    public function tarjetas(Request $request)
    {
        $id = $request->query('jugadorId');
        $jugador = Jugador::findOrFail($id);

        $idTorneo = $request->query('torneoId') ?? '';
        $torneo = $idTorneo ? Torneo::findOrFail($idTorneo) : null;

        $tipo = $request->query('tipo') ?? '';

        // Estadísticas totales del jugador
        $sqlStats = "
        SELECT
            COUNT(CASE WHEN tipo = 'Amarilla' THEN 1 END) AS totalAmarillas,
            COUNT(CASE WHEN tipo = 'Roja' OR tipo = 'Doble Amarilla' THEN 1 END) AS totalRojas
        FROM tarjetas
        INNER JOIN jugadors ON tarjetas.jugador_id = jugadors.id
        INNER JOIN partidos ON tarjetas.partido_id = partidos.id
        INNER JOIN fechas ON partidos.fecha_id = fechas.id
        INNER JOIN grupos ON fechas.grupo_id = grupos.id
        WHERE jugadors.id = :jugadorId
        " . ($idTorneo ? " AND grupos.torneo_id = :torneoId" : "") . "
    ";

        $bindingsStats = ['jugadorId' => $id];
        if ($idTorneo) $bindingsStats['torneoId'] = $idTorneo;

        $tarjetaStats = DB::selectOne(DB::raw($sqlStats), $bindingsStats);

        $totalAmarillas = $tarjetaStats->totalAmarillas ?? 0;
        $totalRojas = $tarjetaStats->totalRojas ?? 0;
        $totalTodos = $totalAmarillas + $totalRojas;

        // Lista de partidos donde recibió tarjetas
        $sqlPartidos = "
        SELECT DISTINCT
            torneos.nombre AS nombreTorneo,
            torneos.escudo AS escudoTorneo,
            torneos.year,
            fechas.numero,
            partidos.dia,
            e1.id AS equipol_id,
            e1.escudo AS fotoLocal,
            e1.nombre AS local,
            e2.id AS equipov_id,
            e2.escudo AS fotoVisitante,
            e2.nombre AS visitante,
            partidos.golesl,
            partidos.golesv,
            partidos.penalesl,
            partidos.penalesv,
            partidos.id AS partido_id,
            tarjetas.tipo AS tipoTarjeta
        FROM partidos
        INNER JOIN equipos e1 ON partidos.equipol_id = e1.id
        INNER JOIN equipos e2 ON partidos.equipov_id = e2.id
        INNER JOIN fechas ON partidos.fecha_id = fechas.id
        INNER JOIN grupos ON fechas.grupo_id = grupos.id
        INNER JOIN torneos ON grupos.torneo_id = torneos.id
        INNER JOIN alineacions ON alineacions.partido_id = partidos.id
        INNER JOIN tarjetas ON tarjetas.partido_id = partidos.id AND tarjetas.jugador_id = alineacions.jugador_id
        WHERE alineacions.jugador_id = :jugadorId
        " . ($idTorneo ? " AND grupos.torneo_id = :torneoId" : "") . "
        " . ($tipo ? " AND tarjetas.tipo = :tipoTarjeta" : "") . "
        ORDER BY partidos.dia DESC
    ";

        $bindingsPartidos = ['jugadorId' => $id];
        if ($idTorneo) $bindingsPartidos['torneoId'] = $idTorneo;
        if ($tipo) $bindingsPartidos['tipoTarjeta'] = $tipo;

        $partidosRaw = DB::select(DB::raw($sqlPartidos), $bindingsPartidos);

        // Paginación manual
        $page = $request->query('page', 1);
        $paginate = 15;
        $offSet = ($page * $paginate) - $paginate;
        $itemsForCurrentPage = array_slice($partidosRaw, $offSet, $paginate, true);

        $partidos = new \Illuminate\Pagination\LengthAwarePaginator(
            $itemsForCurrentPage,
            count($partidosRaw),
            $paginate,
            $page
        );

        // Mantener query params en la paginación
        $arrayParam = ['jugadorId' => $id];
        if ($idTorneo) $arrayParam['torneoId'] = $idTorneo;
        if ($tipo) $arrayParam['tipo'] = $tipo;

        $partidos->setPath(route('jugadores.tarjetas', $arrayParam));

        return view('jugadores.tarjetas', compact(
            'jugador',
            'torneo',
            'totalTodos',
            'totalRojas',
            'totalAmarillas',
            'partidos',
            'tipo'
        ));
    }

    public function penals(Request $request)
    {
        $id = $request->query('jugadorId');
        $jugador = Jugador::findOrFail($id);

        $idTorneo = $request->query('torneoId') ?? '';
        $torneo = $idTorneo ? Torneo::findOrFail($idTorneo) : null;

        $tipo = $request->query('tipo') ?? '';

        // Estadísticas totales del jugador
        $sqlStats = "
        SELECT
            COUNT(CASE WHEN p.tipo = 'Errado' THEN 1 END) AS totalErrados,
            COUNT(CASE WHEN p.tipo = 'Atajado' THEN 1 END) AS totalAtajados,
            COUNT(CASE WHEN p.tipo = 'Atajo' THEN 1 END) AS totalAtajos,
            COUNT(CASE WHEN p.tipo = 'Convirtieron' THEN 1 END) AS totalConvirtieron,
            (
                SELECT COUNT(*)
                FROM gols g
                INNER JOIN jugadors j2 ON g.jugador_id = j2.id
                INNER JOIN partidos pa2 ON g.partido_id = pa2.id
                INNER JOIN fechas f2 ON pa2.fecha_id = f2.id
                INNER JOIN grupos gr2 ON f2.grupo_id = gr2.id
                WHERE g.tipo = 'Penal'
                  AND j2.id = :jugadorIdGol
                  " . ($idTorneo ? " AND gr2.torneo_id = :torneoIdGol" : "") . "
            ) AS totalConvertidos
        FROM penals p
        INNER JOIN jugadors j ON p.jugador_id = j.id
        INNER JOIN partidos pa ON p.partido_id = pa.id
        INNER JOIN fechas f ON pa.fecha_id = f.id
        INNER JOIN grupos gr ON f.grupo_id = gr.id
        WHERE j.id = :jugadorIdPenal
        " . ($idTorneo ? " AND gr.torneo_id = :torneoIdPenal" : "") . "
    ";

        $bindingsStats = ['jugadorIdPenal' => $id, 'jugadorIdGol' => $id];
        if ($idTorneo) {
            $bindingsStats['torneoIdPenal'] = $idTorneo;
            $bindingsStats['torneoIdGol']   = $idTorneo;
        }

        $penalStats = DB::selectOne(DB::raw($sqlStats), $bindingsStats);

        $totalConvertidos = $penalStats->totalConvertidos ?? 0;
        $totalErrados     = $penalStats->totalErrados ?? 0;
        $totalAtajados    = $penalStats->totalAtajados ?? 0;
        $totalAtajos      = $penalStats->totalAtajos ?? 0;
        $totalConvirtieron = $penalStats->totalConvirtieron ?? 0;
        $totalTodos       = $totalErrados + $totalAtajados + $totalConvertidos;
        $totalTodosArquero       = $totalAtajos + $totalConvirtieron;


        $bindingsPartidos = ['jugadorId' => $id];
        $sqlPartidos = "
SELECT DISTINCT
    t.nombre AS nombreTorneo,
    t.escudo AS escudoTorneo,
    t.year,
    f.numero,
    pa.dia,
    e1.id AS equipol_id,
    e1.escudo AS fotoLocal,
    e1.nombre AS local,
    e2.id AS equipov_id,
    e2.escudo AS fotoVisitante,
    e2.nombre AS visitante,
    pa.golesl,
    pa.golesv,
    pa.penalesl,
    pa.penalesv,
    pa.id AS partido_id,
    CASE
        WHEN p.id IS NOT NULL THEN p.tipo
        WHEN g.id IS NOT NULL THEN 'Convertido'
    END AS tipoPenal
FROM partidos pa
INNER JOIN equipos e1 ON pa.equipol_id = e1.id
INNER JOIN equipos e2 ON pa.equipov_id = e2.id
INNER JOIN fechas f ON pa.fecha_id = f.id
INNER JOIN grupos gr ON f.grupo_id = gr.id
INNER JOIN torneos t ON gr.torneo_id = t.id
INNER JOIN alineacions a ON a.partido_id = pa.id AND a.jugador_id = :jugadorId
LEFT JOIN penals p ON p.partido_id = pa.id AND p.jugador_id = a.jugador_id
LEFT JOIN gols g ON g.partido_id = pa.id AND g.jugador_id = a.jugador_id AND g.tipo = 'Penal'
WHERE (p.id IS NOT NULL OR g.id IS NOT NULL)
";

        if ($idTorneo) {
            $sqlPartidos .= " AND gr.torneo_id = :torneoId";
            $bindingsPartidos['torneoId'] = $idTorneo;
        }

        if ($tipo && $tipo !== 'Convertido') {
            $sqlPartidos .= " AND p.tipo = :tipoPenal";
            $bindingsPartidos['tipoPenal'] = $tipo;
        } elseif ($tipo === 'Convertido') {
            $sqlPartidos .= " AND g.id IS NOT NULL";
        }

        $sqlPartidos .= " ORDER BY pa.dia DESC";
        Log::error("Sql: " . $sqlPartidos);

        $partidosRaw = DB::select(DB::raw($sqlPartidos), $bindingsPartidos);




        // Paginación manual
        $page = $request->query('page', 1);
        $paginate = 15;
        $offSet = ($page * $paginate) - $paginate;
        $itemsForCurrentPage = array_slice($partidosRaw, $offSet, $paginate, true);

        $partidos = new \Illuminate\Pagination\LengthAwarePaginator(
            $itemsForCurrentPage,
            count($partidosRaw),
            $paginate,
            $page
        );

        $arrayParam = ['jugadorId' => $id];
        if ($idTorneo) $arrayParam['torneoId'] = $idTorneo;
        if ($tipo) $arrayParam['tipo'] = $tipo;

        $partidos->setPath(route('jugadores.penals', $arrayParam));

        return view('jugadores.penals', compact(
            'jugador',
            'torneo',
            'totalTodos',
            'totalTodosArquero',
            'totalAtajados',
            'totalConvirtieron',
            'totalErrados',
            'totalConvertidos',
            'totalAtajos',
            'partidos',
            'tipo'
        ));
    }



    public function importar(Request $request)
    {


        //
        return view('jugadores.importar');
    }



    public function importarProcess_new(Request $request)
    {
        set_time_limit(0);
        $url = $request->get('url2');
        $ok = 1;
        DB::beginTransaction();
        $success='';
        $html = '';
        try {
            if ($url) {
                // Obtener el contenido de la URL
                //$htmlContent = file_get_contents($url);
                $htmlContent =  HttpHelper::getHtmlContent($url);
                // Crear un nuevo DOMDocument
                $dom = new \DOMDocument();
                libxml_use_internal_errors(true); // Suprimir errores de análisis HTML
                $dom->loadHTML($htmlContent);
                libxml_clear_errors();

                // Crear un nuevo objeto XPath
                $xpath = new \DOMXPath($dom);
            }
        } catch (Exception $ex) {
            $htmlContent = '';
        }
        //Log::info('HTML: ' . print_r($xpath, true), []);


        if ($htmlContent) {

            $name = null;
            $nombre = '';
            $apellido = '';
            $nacimiento = '';
            $fallecimiento = '';
            $ciudad = '';
            $nacionalidad = '';
            $tipo = '';
            $altura = '';
            $peso = '';

            // Obtener el elemento h1 con la clase específica
            $dtElements = $xpath->query('//h1[@class="data-header__headline-wrapper"]');

            if ($dtElements->length > 0) {
                $h1Element = $dtElements->item(0);

                // Obtener el apellido desde el <strong>
                $strongElement = $xpath->query('.//strong', $h1Element);

                if ($strongElement->length > 0) {
                    $apellido = trim($strongElement->item(0)->textContent);

                    // Obtener el nombre eliminando el dorsal y el apellido
                    $fullText = trim($h1Element->textContent);

                    // Eliminar el número de camiseta (# y dígitos)
                    $fullText = preg_replace('/^#\d+\s*/', '', $fullText);

                    // Remover el apellido para obtener solo el nombre
                    $nombre = trim(str_replace($apellido, '', $fullText));
                    $name = $nombre.' '.$apellido;
                } else {
                    $nombre = trim($fullText);
                    $name = trim($fullText);
                    $apellido = '';
                }
            }
            // Obtener la URL de la imagen (src)
            $imgElements = $xpath->query('//img[@class="data-header__profile-image"]');

            if ($imgElements->length > 0) {
                $imageUrl = $imgElements->item(0)->getAttribute('src'); // Obtener el atributo 'src' de la imagen
            } else {
                $imageUrl = null; // Si no se encuentra la imagen
            }
            // Extraer la fecha de nacimiento
            $nacimiento = $xpath->query('//span[contains(text(), "F. Nacim./Edad:")]/following-sibling::span[1]/a');
            $nacimiento = $nacimiento->length > 0 ? trim($nacimiento->item(0)->textContent) : null;

            // Si la fecha de nacimiento contiene la edad entre paréntesis, la eliminamos
            if ($nacimiento) {
                // Usamos una expresión regular para quitar la edad entre paréntesis
                $nacimiento = preg_replace('/\s?\(\d+\)$/', '', $nacimiento);

                // Asegurarse de que no haya caracteres adicionales y convertir la fecha
                try {
                    $nacimiento = Carbon::createFromFormat('d/m/Y', $nacimiento)->format('Y-m-d'); // Formato Y-m-d
                } catch (\Carbon\Exceptions\InvalidFormatException $e) {
                    // Si el formato no es válido, imprimir un error o manejar el caso
                    Log::error("Fecha de nacimiento no válida: " . $nacimiento);
                    $nacimiento = null; // O tomar un valor por defecto si es necesario
                }
            }

            // Extraer lugar de nacimiento
            $ciudad = $xpath->query('//span[contains(text(), "Lugar de nac.:")]/following-sibling::span[1]');
            $ciudad = $ciudad->length > 0 ? trim($ciudad->item(0)->textContent) : null;

            // Extraer nacionalidad
            $nacionalidad = $xpath->query('//span[contains(text(), "Nacionalidad:")]/following-sibling::span[1]');
            $nacionalidad = $nacionalidad->length > 0 ? trim($nacionalidad->item(0)->textContent) : null;

            // Extraer fecha de fallecimiento
            $fallecimiento = $xpath->query('//span[contains(text(), "Fecha de fallecimiento:")]/following-sibling::span[1]');
            $fallecimiento = $fallecimiento->length > 0 ? trim($fallecimiento->item(0)->textContent) : null;

            // Asegurarse de que no haya caracteres adicionales en la fecha y convertirla con Carbon
            if ($fallecimiento) {
                // Usamos una expresión regular para quitar la edad entre paréntesis en la fecha de fallecimiento
                $fallecimiento = preg_replace('/\s?\(\d+\)$/', '', $fallecimiento);

                try {
                    $fallecimiento = Carbon::createFromFormat('d.m.Y', $fallecimiento)->format('Y-m-d'); // Formato Y-m-d
                } catch (\Carbon\Exceptions\InvalidFormatException $e) {
                    // Si el formato no es válido, imprimir un error o manejar el caso
                    Log::error("Fecha de fallecimiento no válida: " . $fallecimiento);
                    $fallecimiento = null; // O tomar un valor por defecto si es necesario
                }
            }


            // Extraer altura
            $altura = $xpath->query('//span[contains(text(), "Altura:")]/following-sibling::span[1]');
            $altura = $altura->length > 0 ? trim($altura->item(0)->textContent) : null;
            if ($altura) {
                // Eliminar unidades de medida (como "m" o "cm") y limpiar espacios extra
                $altura = preg_replace('/\s?m|cm/', '', $altura); // Eliminar 'm' o 'cm'
                $altura = trim($altura); // Eliminar espacios adicionales

                // Convertir a decimal, si el valor es numérico
                if (is_numeric($altura)) {
                    $altura = (float)$altura; // Convertir a decimal
                }
                else{
                    $altura = null;
                }
            }



            // Extraer posición
            $posicion = $xpath->query('//span[contains(text(), "Posición:")]/following-sibling::span[1]');
            $posicion = $posicion->length > 0 ? trim($posicion->item(0)->textContent) : null;
            $arrPosicion = explode("-", $posicion);
            $tipo = trim($arrPosicion[0]);
            switch ($tipo) {
                case 'Portero':
                    $tipo = 'Arquero';
                    break;
                case 'Defensa':
                    $tipo = 'Defensor';
                    break;
                case 'Medio campo':
                    $tipo = 'Medio';
                    break;
                case 'Delantero':
                    $tipo = 'Delantero';
                    break;
                default:
                    $tipo = null;
                    break;
            }
            // Extraer pie
            $pie = $xpath->query('//span[contains(text(), "Pie:")]/following-sibling::span[1]');
            $pie = $pie->length > 0 ? trim($pie->item(0)->textContent) : null;

            switch (trim($pie)) {
                case 'Derecho':
                    $pie = 'Derecha';
                    break;
                case 'Izquierdo':
                    $pie = 'Izquierda';
                    break;
                case 'Ambidiestro':
                    $pie = 'Ambas';
                    break;
                default:
                    $pie = null;
                    break;

            }

            Log::info('Nacimiento: ' . $nacimiento.' - '.$fallecimiento.' - '.$ciudad.' - '.$nacionalidad.' - '.$altura.' - '.$tipo.' - '.$pie, []);

            // Descarga y guarda la imagen si no es el avatar por defecto
            if (!str_contains($imageUrl, 'default.jpg')) {
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
                    $success .='Foto no subida: ' . $fotoDiv[0]->getAttribute('alt').'<br>';
                }
            } else {
                Log::info('No tiene foto: ' . $imageUrl, []);
                $success .='No tiene foto: ' . $imageUrl.'<br>';
            }

            // Insertar los datos de la persona

            if ($name) {
                $insert['name'] = trim($name);
            } else {
                Log::info('Falta el name', []);
                $success .='Falta el name <br>';
            }

            if ($nombre) {
                $insert['nombre'] = trim($nombre);
            } else {
                Log::info('Falta el nombre', []);
                $success .='Falta el nombre <br>';
            }



            if ($apellido) {
                $insert['apellido'] = trim($apellido);
            } else {
                Log::info('Falta el apellido', []);
                $success .='Falta el apellido <br>';
            }

            if ($ciudad) {
                $insert['ciudad'] = trim($ciudad);
            }
            if ($nacionalidad) {
                $insert['nacionalidad'] = preg_replace('/^[\pZ\pC]+|[\pZ\pC]+$/u', '', $nacionalidad);
            } else {
                Log::info('Falta la nacionalidad', []);
                $success .='Falta la nacionalidad <br>';
            }
            if ($altura) {
                $insert['altura'] = trim($altura);
            }
            if ($peso) {
                $insert['peso'] = trim($peso);
            }
            if ($nacimiento) {
                $insert['nacimiento'] = trim($nacimiento);
            } else {
                Log::info('Falta la fecha de nacimiento', []);
                $success .='Falta la fecha de nacimiento <br>';
            }
            if ($fallecimiento) {
                $insert['fallecimiento'] = trim($fallecimiento);
            }
            if ($tipo) {
                $insert['tipoJugador'] = trim($tipo);
            } else {
                Log::info('Falta el tipo de jugador', []);
                $success .='Falta el tipo de jugador <br>';
            }
            if ($pie) {
                $insert['pie'] = trim($pie);
            }

            $request->session()->put('nombre_filtro_jugador', $apellido);

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
                        if (!empty($persona->nacionalidad)) {
                            unset($insert['nacionalidad']);
                        }
                        $persona->update($insert);
                        $persona->jugador()->create($insert);
                    }
                } catch (QueryException $ex) {
                    //$ok = 0;
                    $errorCode = $ex->errorInfo[1];

                    if ($errorCode == 1062) {
                        $error = 'Jugador repetido';
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

        return redirect()->route('jugadores.index')->with($respuestaID, $respuestaMSJ);
    }

    public function importarProcess(Request $request)
    {
        set_time_limit(0);
        $url2 = $request->get('url2');
        if ($url2){
            return $this->importarProcess_new($request);
        }
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
                // Crear un nuevo DOMDocument
                if (!empty($htmlContent)) {
                    $dom = new \DOMDocument();
                    libxml_use_internal_errors(true); // Suprimir errores de análisis HTML
                    $dom->loadHTML($htmlContent);
                    libxml_clear_errors();

                    $xpath = new \DOMXPath($dom);
                } else {
                    // Podés loguear o seguir normalmente
                    $ok=0;
                    $error = 'HTML vacío para la URL: '.$url;
                }
            }
        } catch (Exception $ex) {
            $html = '';
        }

        if ($htmlContent) {
            // Seleccionar el div con id 'previewArea' que contiene la imagen
            $fotoDiv = $xpath->query('//div[@id="previewArea"]/img');
            if ($fotoDiv->length > 0) {
                $imageUrl = $fotoDiv[0]->getAttribute('src');
                //Log::info('Foto: ' . $imageUrl, []);
            }

            // Seleccionar todos los dt y dd dentro de div.contentitem
            $dtElements = $xpath->query('//div[@class="contentitem"]/dl/dt');
            $ddElements = $xpath->query('//div[@class="contentitem"]/dl/dd');

            $name = null;
            $nombre = '';
            $apellido = '';
            $nacimiento = '';
            $fallecimiento = '';
            $ciudad = '';
            $nacionalidad = '';
            $tipo = '';
            $altura = '';
            $peso = '';

            for ($i = 0; $i < $dtElements->length; $i++) {
                $dtText = trim($dtElements[$i]->textContent);
                $ddText = trim($ddElements[$i]->textContent);

                // Agregar los datos a la persona según el título (dt) encontrado
                switch ($dtText) {
                    case 'Nombre':

                        if (empty($name)) {
                            $name = $ddText; // Guarda solo la primera aparición
                        }
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

            // Insertar los datos de la persona
            if ($name) {
                $insert['name'] = $name;
            } else {
                Log::info('Falta el name', []);
                $success .='Falta el name <br>';
            }
            if ($nombre) {
                $insert['nombre'] = $nombre;
            } else {
                Log::info('Falta el nombre', []);
                $success .='Falta el nombre <br>';
            }

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
            if ($altura) {
                $insert['altura'] = $altura;
            }
            if ($peso) {
                $insert['peso'] = $peso;
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
            if ($tipo) {
                $insert['tipoJugador'] = $tipo;
            } else {
                Log::info('Falta el tipo de jugador', []);
                $success .='Falta el tipo de jugador <br>';
            }

            $request->session()->put('nombre_filtro_jugador', $apellido);
            //log::info(print_r($insert, true));
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
                        if (!empty($persona->nacionalidad)) {
                            unset($insert['nacionalidad']);
                        }

                        $persona->update($insert);
                        $persona->jugador()->create($insert);
                    }
                } catch (QueryException $ex) {
                    //$ok = 0;
                    $errorCode = $ex->errorInfo[1];

                    if ($errorCode == 1062) {
                        $success .= 'Jugador repetido';
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

        return redirect()->route('jugadores.index')->with($respuestaID, $respuestaMSJ);
    }


    public function importarProcess_old(Request $request)
    {
        set_time_limit(0);
        //Log::info('Entraaaaaa', []);

        $url = $request->get('url');
        $ok=1;
        DB::beginTransaction();



        $html='';
        try {
            if ($url){


                $html = HtmlDomParser::file_get_html($url, false, null, 0);
            }


        }
        catch (Exception $ex) {
            $html='';
        }
        if ($html){
            foreach ($html->find('div[id=previewArea]') as $element) {
                $fotoDiv = $element->find('img');

                //$foto = $fotoDiv->src;
                Log::info('Foto ' . $fotoDiv[0]->src, []);
                $imageUrl = $fotoDiv[0]->src; // Obtén la URL de la imagen desde la solicitud


            }

            $dtElements = $html->find('div.contentitem dl dt');
            $ddElements = $html->find('div.contentitem dl dd');
            $name='';
            $nombre='';
            $apellido='';
            $nacimiento='';
            $fallecimiento='';
            $ciudad='';
            $nacionalidad='';
            $tipo='';
            $altura='';
            $peso='';
            for ($i = 0; $i < count($dtElements); $i++) {
                $dtText = trim($dtElements[$i]->plaintext);
                $ddText = trim($ddElements[$i]->plaintext);

                // Agregar los datos a la persona según el título (dt) encontrado
                switch ($dtText) {

                    case 'Nombre':
                        if (empty($name)) {
                            $name = $ddText; // Guarda solo la primera aparición
                        }
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
                        $ciudad = trim($ddText);

                        break;
                    case 'Nacionalidad':
                        $nacionalidad =$ddText;
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
                            $altura=  $matches[0];
                        }
                        $altura = number_format($altura / 100, 2);

                        break;
                    case 'Peso':
                        if (preg_match('/\d+/', $ddText, $matches)) {
                            $peso=  $matches[0];
                        }

                        break;

                    default:
                        // Manejar otros datos según sea necesario
                        break;
                }
            }

            if (!str_contains($imageUrl, 'avatar-player.jpg')) {

                // Utiliza GuzzleHTTP para realizar una solicitud GET a la URL de la imagen
                $client = new Client();
                $response = $client->get($imageUrl);

                // Verifica si la solicitud fue exitosa
                if ($response->getStatusCode() === 200) {
                    $imageData = $response->getBody()->getContents();

                    // Parsea la URL para obtener la parte de la ruta
                    $parsedUrl = parse_url($imageUrl);
                    $pathInfo = pathinfo($parsedUrl['path']);

                    $nombreArchivo = $pathInfo['filename'];
                    // Obtiene la extensión del archivo
                    $extension = $pathInfo['extension'];
                    if (strrchr($nombreArchivo, '.') === '.') {
                        $nombreArchivo = substr($nombreArchivo, 0, -1);
                    }
                    // Define la ubicación donde deseas guardar la imagen en tu PC
                    $localFilePath = public_path('images/') . $nombreArchivo.'.'.$extension;
                    Log::info('Ojo!! url foto: ' . $localFilePath, []);
                    $insert['foto'] = "$nombreArchivo.$extension";
                    // Guarda la imagen en tu sistema de archivos local
                    file_put_contents($localFilePath, $imageData);

                    // Puedes retornar una respuesta de éxito u otra lógica según tus necesidades
                    Log::info('Foto subida', []);
                } else {
                    // Maneja el caso en que la descarga de la imagen no fue exitosa
                    Log::info('Ojo!! Foto no subida: ' . $fotoDiv[0]->alt, []);
                }
            }
            else{
                Log::info('OJO!!! no tiene foto: ' .$imageUrl, []);
            }
            if ($name){
                $insert['name'] = $name;
            }
            else{
                Log::info('OJO!!! falta el name', []);
            }
            if ($nombre){
                $insert['nombre'] = $nombre;
            }
            else{
                Log::info('OJO!!! falta el nombre', []);
            }

            if ($apellido){
                $insert['apellido'] = $apellido;
            }
            else{
                Log::info('OJO!!! falta el apellido', []);
            }
            if ($ciudad){
                $insert['ciudad'] = $ciudad;
            }
            if ($nacionalidad){
                $insert['nacionalidad'] = $nacionalidad;
            }
            else{
                Log::info('OJO!!! falta la nacionalidad', []);
            }
            if ($altura){
                $insert['altura'] = $altura;
            }
            if ($peso){
                $insert['peso'] = $peso;
            }
            if ($nacimiento){
                $insert['nacimiento'] = $nacimiento;
            }
            else{
                Log::info('OJO!!! falta el nacimiento', []);
            }
            if ($fallecimiento){
                $insert['fallecimiento'] = $fallecimiento;
            }

            if ($tipo){
                $insert['tipoJugador'] = $tipo;
            }
            else{
                Log::info('OJO!!! falta el tipo', []);
            }




            $request->session()->put('nombre_filtro_jugador', $apellido);



        try {

            $persona = Persona::create($insert);
            $persona->jugador()->create($insert);


        }catch(QueryException $ex){

            try {
                $persona = Persona::where('nombre','=',$insert['nombre'])->Where('apellido','=',$insert['apellido'])->Where('nacimiento','=',$insert['nacimiento'])->first();
                if (!empty($persona)){
                    $persona->update($insert);
                    $persona->jugador()->create($insert);

                }
            }catch(QueryException $ex){

                $ok=0;
                $errorCode = $ex->errorInfo[1];

                if ($errorCode == 1062) {
                    $error='Jugador repetido';
                }


            }


        }

        }
        else{
            Log::info('OJO!!! No se econtró la URL' .$url, []);

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
        return redirect()->route('jugadores.index')->with($respuestaID,$respuestaMSJ);

        //return view('fechas.index', compact('grupo'));
    }

    public function titulos(Request $request)
    {
        $id = $request->query('jugadorId');
        $jugador = Jugador::findOrFail($id);

        // Títulos como jugador
        $torneosJugador = DB::table('torneos')
            ->join('grupos', 'torneos.id', '=', 'grupos.torneo_id')
            ->join('plantillas', 'grupos.id', '=', 'plantillas.grupo_id')
            ->join('plantilla_jugadors', 'plantillas.id', '=', 'plantilla_jugadors.plantilla_id')
            ->join('posicion_torneos', function ($join) {
                $join->on('torneos.id', '=', 'posicion_torneos.torneo_id')
                    ->on('plantillas.equipo_id', '=', 'posicion_torneos.equipo_id')
                    ->where('posicion_torneos.posicion', '=', 1);
            })
            ->where('plantilla_jugadors.jugador_id', $id)
            ->select(
                'torneos.id as idTorneo',
                DB::raw('CONCAT(torneos.nombre," ",torneos.year) AS nombreTorneo'),
                'torneos.escudo AS escudoTorneo',
                'torneos.tipo',
                'torneos.ambito',
                'torneos.year'
            )
            ->orderByDesc('torneos.year')
            ->get();

        $titulosJugadorCopa = 0;
        $titulosJugadorLiga = 0;
        $titulosJugadorInternacional = 0;

        foreach ($torneosJugador as $torneo) {
            // Verifico si el jugador participó en algún partido del torneo campeón
            $alineacion = Alineacion::whereHas('partido', function($query) use ($torneo) {
                $query->whereHas('fecha.grupo', function($q) use ($torneo) {
                    $q->where('torneo_id', $torneo->idTorneo);
                });
            })
                ->where('equipo_id', function($q) use ($torneo) {
                    $q->select('equipo_id')
                        ->from('posicion_torneos')
                        ->where('torneo_id', $torneo->idTorneo)
                        ->where('posicion', 1)
                        ->limit(1);
                })
                ->where('jugador_id', $id)
                ->exists();

            if ($alineacion) {
                if ($torneo->ambito === 'Nacional') {
                    $torneo->tipo === 'Copa' ? $titulosJugadorCopa++ : $titulosJugadorLiga++;
                } else {
                    $titulosJugadorInternacional++;
                }
            }

            // Escudos y posición en torneo
            $escudos = Equipo::select('escudo','id as equipo_id','nombre')->whereHas('alineacions', function($q) use ($id, $torneo) {
                $q->where('jugador_id', $id)
                    ->whereHas('partido.fecha.grupo', function($query) use ($torneo) {
                        $query->where('torneo_id', $torneo->idTorneo);
                    });
            })->get();

            $torneo->escudo = '';
            foreach ($escudos as $escudo) {
                $posicion = PosicionTorneo::where('torneo_id', $torneo->idTorneo)
                    ->where('equipo_id', $escudo->equipo_id)
                    ->first();
                $strPosicion = '';
                if ($posicion) {
                    $strPosicion = $posicion->posicion == 1 ? '<img src="'.asset('images/campeon.png').'" height="20"> Campeón' :
                        ($posicion->posicion == 2 ? '<img src="'.asset('images/subcampeon.png').'" height="20"> Subcampeón' : $posicion->posicion);
                }
                $torneo->escudo .= $escudo->escudo.'_'.$escudo->equipo_id.'_'.$strPosicion.'_'.$escudo->nombre.',';
            }

            // Partidos jugados, goles, tarjetas y estadísticas de arquero
            $partidosIds = Partido::whereHas('fecha.grupo', function($q) use ($torneo) {
                $q->where('torneo_id', $torneo->idTorneo);
            })->pluck('id');

            $torneo->jugados = Alineacion::where('jugador_id', $id)
                ->where('tipo', 'Titular')
                ->whereIn('partido_id', $partidosIds)
                ->count();

            $torneo->jugados  += Cambio::where('jugador_id', $id)
                ->where('tipo', 'Entra')
                ->whereIn('partido_id', $partidosIds)
                ->count();

            $torneo->goles = Gol::whereIn('partido_id', $partidosIds)
                ->where('jugador_id', $id)
                ->where('tipo', '<>', 'En contra')
                ->count();

            $tarjetas = Tarjeta::whereIn('partido_id', $partidosIds)
                ->where('jugador_id', $id)
                ->selectRaw("COUNT(CASE WHEN tipo='Amarilla' THEN 1 END) as amarillas,
                        COUNT(CASE WHEN tipo='Roja' OR tipo='Doble Amarilla' THEN 1 END) as rojas")
                ->first();

            $torneo->amarillas = $tarjetas->amarillas ?? 0;
            $torneo->rojas = $tarjetas->rojas ?? 0;

            if ($jugador->tipoJugador === 'Arquero') {
                $arqueros = Alineacion::whereIn('partido_id', $partidosIds)
                    ->where('jugador_id', $id)
                    ->where('tipo', 'Titular')
                    ->with('partido')
                    ->get();

                $torneo->recibidos = 0;
                $torneo->invictas = 0;
                foreach ($arqueros as $arquero) {
                    $torneo->recibidos += $arquero->partido->goles_recibidos_por_equipo($arquero->equipo_id);
                    $torneo->invictas += $arquero->partido->fue_invicto($arquero->equipo_id) ? 1 : 0;
                }
            }
        }

        // Títulos como técnico
        $torneosTecnico = DB::table('torneos')
            ->join('grupos','torneos.id','=','grupos.torneo_id')
            ->join('fechas','grupos.id','=','fechas.grupo_id')
            ->join('partidos','fechas.id','=','partidos.fecha_id')
            ->join('partido_tecnicos','partidos.id','=','partido_tecnicos.partido_id')
            ->join('tecnicos','tecnicos.id','=','partido_tecnicos.tecnico_id')
            ->where('tecnicos.persona_id', $jugador->persona_id)
            ->select('torneos.id as idTorneo','torneos.nombre','torneos.year','torneos.tipo','torneos.ambito')
            ->orderByDesc('torneos.year')
            ->get();

        $titulosTecnicoCopa = 0;
        $titulosTecnicoLiga = 0;
        $titulosTecnicoInternacional = 0;

        foreach ($torneosTecnico as $torneo) {
            $posicionTorneo = PosicionTorneo::where('torneo_id',$torneo->idTorneo)->where('posicion',1)->first();
            if ($posicionTorneo) {
                $ultimoPartido = Partido::whereHas('fecha.grupo', function($q) use ($torneo) {
                    $q->where('torneo_id', $torneo->idTorneo);
                })->orderByDesc('dia')->first();

                $partidoTecnico = PartidoTecnico::where('partido_id', $ultimoPartido->id)
                    ->where('tecnico_id', Tecnico::where('persona_id', $jugador->persona_id)->value('id'))
                    ->where('equipo_id', $posicionTorneo->equipo_id)
                    ->first();

                if ($partidoTecnico) {
                    if ($torneo->ambito === 'Nacional') {
                        $torneo->tipo === 'Copa' ? $titulosTecnicoCopa++ : $titulosTecnicoLiga++;
                    } else {
                        $titulosTecnicoInternacional++;
                    }
                }
            }
        }

        // --------------------------------------------------
// TÍTULOS EXTRAS (tabla titulos + titulo_torneos)
// --------------------------------------------------

        $countedTorneos = [];
        $titulosExtras = Titulo::with('torneos')->get();

        foreach ($titulosExtras as $tituloExtra) {

            $equipoId = $tituloExtra->equipo_id;

            $torneosIds = $tituloExtra->torneos->pluck('id')->toArray();

            if (empty($torneosIds)) {
                continue;
            }


            // buscar último partido del EQUIPO campeón en CUALQUIERA de esos torneos
            $ultimoPartidoEquipo = Partido::whereHas('fecha.grupo', function($q) use ($torneosIds) {
                $q->whereIn('torneo_id', $torneosIds);
            })
                ->where(function ($q) use ($equipoId) {
                    $q->where('equipol_id', $equipoId)
                        ->orWhere('equipov_id', $equipoId);
                })
                ->orderBy('dia', 'DESC')
                ->first();

            if (!$ultimoPartidoEquipo) {
                continue;
            }



            $tituloExtra->nombreTorneo = $tituloExtra->nombre.' '.$tituloExtra->year;
            $tituloExtra->year = $tituloExtra->year;
            $tituloExtra->escudo = $tituloExtra->equipo->escudo.'_'.$tituloExtra->equipo_id.'_<img src="'.asset('images/campeon.png').'" height="20"> Campeón_'.$tituloExtra->equipo->nombre.',';
            // -----------------------------
            // COMO TÉCNICO
            // -----------------------------
            $tecnicoId = Tecnico::where('persona_id', $jugador->persona_id)->value('id');

            if (!empty($tecnicoId)) {

                $dirigio = PartidoTecnico::where('partido_id', $ultimoPartidoEquipo->id)
                    ->where('tecnico_id', $tecnicoId)
                    ->where('equipo_id', $equipoId)
                    ->exists();

                if ($dirigio) {

                    $torneosTecnico->push($tituloExtra);



                        if ($tituloExtra->ambito == 'Nacional') {
                            if ($tituloExtra->tipo == 'Copa') {
                                $titulosTecnicoCopa++;
                            } else {
                                $titulosTecnicoLiga++;
                            }
                        } else {
                            $titulosTecnicoInternacional++;
                        }

                    $countedTorneos = array_merge($countedTorneos, $torneosIds);

                }
            }

            // -----------------------------
            // COMO JUGADOR
            // -----------------------------

            // busco jugador asociado a la persona
            $jugadorAsociado = Jugador::where('persona_id', $jugador->persona_id)->first();

            if ($jugadorAsociado) {

                $jugo = Alineacion::where('jugador_id', $jugadorAsociado->id)
                    ->where('equipo_id', $equipoId)
                    ->whereHas('partido.fecha.grupo', function($q) use ($torneosIds) {
                        $q->whereIn('torneo_id', $torneosIds);
                    })
                    ->exists();
//dd($jugo);
                if ($jugo) {

                    $partidosIds = Partido::whereHas('fecha.grupo', function($q) use ($torneosIds) {
                        $q->whereIn('torneo_id', $torneosIds);
                    })
                        ->where(function($q) use ($equipoId) {
                            $q->where('equipol_id', $equipoId)->orWhere('equipov_id', $equipoId);
                        })
                        ->pluck('id');

                    $tituloExtra->jugados = Alineacion::where('jugador_id', $jugadorAsociado->id)
                            ->where('tipo','Titular')
                            ->whereIn('partido_id', $partidosIds)->count()
                        + Cambio::where('jugador_id', $jugadorAsociado->id)
                            ->where('tipo','Entra')
                            ->whereIn('partido_id', $partidosIds)->count();

                    $tituloExtra->goles = Gol::where('jugador_id', $jugadorAsociado->id)
                        ->whereIn('partido_id', $partidosIds)
                        ->where('tipo','<>','En contra')
                        ->count();

                    $tarjetas = Tarjeta::where('jugador_id', $jugadorAsociado->id)
                        ->whereIn('partido_id', $partidosIds)
                        ->selectRaw("COUNT(CASE WHEN tipo='Amarilla' THEN 1 END) as amarillas,
                         COUNT(CASE WHEN tipo='Roja' OR tipo='Doble Amarilla' THEN 1 END) as rojas")
                        ->first();

                    $tituloExtra->amarillas = $tarjetas->amarillas ?? 0;
                    $tituloExtra->rojas = $tarjetas->rojas ?? 0;

                    // Arqueros
                    if ($jugador->tipoJugador === 'Arquero') {
                        $arqueros = Alineacion::where('jugador_id', $jugadorAsociado->id)
                            ->where('tipo','Titular')
                            ->whereIn('partido_id', $partidosIds)
                            ->with('partido')
                            ->get();

                        $tituloExtra->recibidos = 0;
                        $tituloExtra->invictas = 0;
                        foreach ($arqueros as $arquero) {
                            $tituloExtra->recibidos += $arquero->partido->goles_recibidos_por_equipo($arquero->equipo_id);
                            $tituloExtra->invictas += $arquero->partido->fue_invicto($arquero->equipo_id) ? 1 : 0;
                        }
                    }

                    $torneosJugador->push($tituloExtra);
                    if ($tituloExtra->ambito == 'Nacional') {
                            if ($tituloExtra->tipo == 'Copa') $titulosJugadorCopa++;
                            else $titulosJugadorLiga++;
                        } else {
                            $titulosJugadorInternacional++;
                        }


                    $countedTorneos = array_merge($countedTorneos, $torneosIds);
                }
            }


        }

        // Ordenar torneos por year descendente
        $torneosJugador = $torneosJugador->sortByDesc('year')->values();
        $torneosTecnico = $torneosTecnico->sortByDesc('year')->values();



        return view('jugadores.titulos', compact(
            'jugador',
            'torneosJugador',
            'torneosTecnico',
            'titulosTecnicoLiga',
            'titulosTecnicoCopa',
            'titulosJugadorLiga',
            'titulosJugadorCopa',
            'titulosJugadorInternacional',
            'titulosTecnicoInternacional'
        ));
    }


    private function sonApellidosSimilares($apellido1, $apellido2)
    {
        $apellido1 = strtolower($apellido1);
        $apellido2 = strtolower($apellido2);

        // Verificar si uno de los apellidos es subcadena del otro
        if (strpos($apellido1, $apellido2) !== false || strpos($apellido2, $apellido1) !== false) {
            return true;
        }

        // Usar similar_text para comparar la similitud de los apellidos
        similar_text($apellido1, $apellido2, $percent);
        return $percent > 80; // Ajusta este umbral según tus necesidades
    }

    private function sonSimilaresPorNombreYApellido($persona1, $persona2)
    {
        $apellidos1 = explode(' ', strtolower($persona1->apellido));
        $apellidos2 = explode(' ', strtolower($persona2->apellido));

        $nombres1 = explode(' ', strtolower($persona1->nombre));
        $nombres2 = explode(' ', strtolower($persona2->nombre));

        // Verificar si tienen al menos un apellido en común
        $coincideApellido = !empty(array_intersect($apellidos1, $apellidos2));

        // Verificar si tienen al menos un nombre en común
        $coincideNombre = !empty(array_intersect($nombres1, $nombres2));

        return $coincideApellido && $coincideNombre;
    }

    public function verificarPersonas_new(Request $request)
    {
        set_time_limit(0);
        $verificados = $request->query('verificados') ? 1 : 0;
        $total = $request->query('total') ? 1 : 0;

        // Personas a verificar
        $personasQuery = Persona::orderBy('apellido', 'ASC');
        if (!$verificados) {
            $personasQuery->where('verificado', false);
        }

        $personas = $personasQuery->paginate(50);

        // IDs de las personas de la página
        $idsPersonas = $personas->pluck('id')->toArray();

        // Traer todos los pares de similares en una sola consulta
        $similaresQuery = DB::table('personas as p1')
            ->join('personas as p2', function ($join) use ($idsPersonas) {
                $join->on('p1.id', '!=', 'p2.id')
                    ->whereIn('p1.id', $idsPersonas);
            })
            ->whereRaw("p1.nombre LIKE CONCAT('%', p2.nombre, '%')")
            ->whereRaw("p1.apellido LIKE CONCAT('%', p2.apellido, '%')")
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('personas_verificadas')
                    ->whereRaw('(persona_id = p1.id AND simil_id = p2.id) OR (persona_id = p2.id AND simil_id = p1.id)');
            })
            ->select('p1.*', 'p2.id as simil_id')
            ->get();

        // Construir colección de Eloquent con todos los similares
        $personasSimilares = collect();
        foreach ($similaresQuery as $fila) {
            $persona = Persona::find($fila->id);
            $persona->simil_id = $fila->simil_id;
            $personasSimilares->push($persona);
        }

        // Personas sin nombre/apellido
        $personasSinNombreApellido = Persona::whereNull('nombre')
            ->orWhere('nombre', '')
            ->orWhereNull('apellido')
            ->orWhere('apellido', '')
            ->orderBy('apellido', 'ASC')
            ->get();

        // Personas sin bandera
        $personasSinBandera = $personas->filter(function ($persona) {
            $nacionalidadSinAcentos = str_replace(
                ['á','é','í','ó','ú','ñ','Á','É','Í','Ó','Ú','Ñ'],
                ['a','e','i','o','u','n','A','E','I','O','U','N'],
                $persona->nacionalidad
            );
            $path = public_path('images/' . $nacionalidadSinAcentos . '.gif');
            return !file_exists($path);
        });

        return view('jugadores.verificarPersona', [
            'verificados' => $verificados,
            'total' => $total,
            'similaresNombreApellido' => $personasSimilares,
            'personas' => $personas,
            'personasSinNombreApellido' => $personasSinNombreApellido,
            'personasSinBandera' => $personasSinBandera
        ]);
    }


    public function verificarPersonas(Request $request)
        {
            set_time_limit(0); // Aumentamos tiempo solo para pruebas
            $verificados= ($request->query('verificados'))?1:0;
            $total= ($request->query('total'))?1:0;
            // Obtener todas las personas de la base de datos
            /*if ($verificados){
                $personas = Persona::orderBy('apellido','ASC')->get();
            }
            else{
                $personas = Persona::where('verificado', false)->orderBy('apellido','ASC')->get();
            }*/

            $personas = Persona::orderBy('apellido','ASC')->paginate(1000);

            // Separar personas con y sin fecha de nacimiento
            /*$personasConFechaNacimiento = $personas->filter(function ($persona) {
                return !is_null($persona->nacimiento);
            });*/

            /*$personasSinFechaNacimiento = $personas->filter(function ($persona) {
                return is_null($persona->nacimiento);
            });*/

            /*$personasSinFoto = $personas->filter(function ($persona) {
                return is_null($persona->foto);
            });*/
            $personasSinFoto =array();
            // Agrupar personas por fecha de nacimiento
            //$personasPorFechaNacimiento = $personasConFechaNacimiento->groupBy('nacimiento');

            // Colección para almacenar personas con apellidos similares
            //$resultados = collect();

            // Verificar personas con la misma fecha de nacimiento y apellidos similares
            /*foreach ($personasPorFechaNacimiento as $grupo) {
                foreach ($grupo as $persona) {
                    $similares = $grupo->filter(function ($item) use ($persona) {
                        // Verificar si el apellido de la persona actual es similar al de las otras personas
                        return $item->id !== $persona->id && $this->sonApellidosSimilares($item->apellido, $persona->apellido);
                    });

                    if ($similares->isNotEmpty()) {
                        // Si hay personas con apellido similar, agregar la persona actual y los similares a los resultados
                        $resultados = $resultados->merge([$persona])->merge($similares);
                    }
                }
            }

            // Eliminar duplicados
            $resultados = $resultados->unique('id');

            // Aplicar paginación manual
            $pagina = request()->input('page', 1);
            $porPagina = 50; // Define cuántos elementos mostrar por página
            $paginadosResultados = new LengthAwarePaginator(
                $resultados->forPage($pagina, $porPagina),
                $resultados->count(),
                $porPagina,
                $pagina,
                ['path' => request()->url(),  'query' => ['verificados' => $verificados, 'total' => $total] ]// ⬅ Agregar checkboxes en paginación]
            );*/

            /*$existenSimilares = Persona::where(function ($query) use ($personas) {
                foreach ($personas as $persona) {
                    $query->orWhere(function ($q) use ($persona) {
                        $q->where('apellido', 'LIKE', '%' . $persona->apellido . '%')
                            ->where('nombre', 'LIKE', '%' . $persona->nombre . '%')
                            ->where('id', '!=', $persona->id);
                    });
                }
            })
                ->whereNotExists(function ($query) use ($personas) {
                    $query->select(DB::raw(1))
                        ->from('personas_verificadas')
                        ->where(function ($q) use ($personas) {
                            foreach ($personas as $persona) {
                                $q->orWhereRaw(
                                    '(persona_id = personas.id AND simil_id = ?) OR (persona_id = ? AND simil_id = personas.id)',
                                    [$persona->id, $persona->id]
                                );
                            }
                        });
                })
                ->exists(); // Devuelve true si hay al menos un similar*/

            // Filtrar las personas con nombres y apellidos similares
            $personasSimilares = collect();

            //if ($existenSimilares) {



            foreach ($personas as $persona) {
                $nombreCompleto1 = strtolower(trim($persona->nombre . ' ' . $persona->apellido));
                $nombreCompleto2 = strtolower(trim($persona->apellido . ' ' . $persona->nombre));

                $similares = Persona::where('id', '!=', $persona->id)
                    ->where(function ($query) use ($nombreCompleto1, $nombreCompleto2) {
                        $query->whereRaw("LOWER(CONCAT(nombre, ' ', apellido)) LIKE ?", ["%$nombreCompleto1%"])
                            ->orWhereRaw("LOWER(CONCAT(nombre, ' ', apellido)) LIKE ?", ["%$nombreCompleto2%"]);
                    })
                    ->whereNotExists(function ($query) use ($persona) {
                        $query->select(DB::raw(1))
                            ->from('personas_verificadas')
                            ->whereRaw('(persona_id = personas.id AND simil_id = ?) OR (persona_id = ? AND simil_id = personas.id)', [$persona->id, $persona->id]);
                    })
                    ->get();

                if ($similares->isNotEmpty()) {
                    $personasSimilares = $personasSimilares->merge([$persona]);
                    $similares = $similares->map(function ($simil) use ($persona) {
                        $simil->simil_id = $persona->id;
                        return $simil;
                    });
                    $personasSimilares = $personasSimilares->merge($similares);
                }
            }

            // Eliminar duplicados de la colección de resultados
                //$personasSimilares = $personasSimilares->unique('id');
            /*}
            else{
                $personas = Persona::where('nombre','=','anacleto')->orderBy('apellido','ASC')->paginate(50);
            }*/

            // Obtener personas sin nombre y/o sin apellido
            $personasSinNombreApellido = Persona::whereNull('nombre')
                ->orWhere('nombre', '')
                ->orWhereNull('apellido')
                ->orWhere('apellido', '')
                ->orderBy('apellido', 'ASC')
                ->get();

            // Filtrar las que no tienen bandera (no existe la imagen)
            $personasSinBandera = $personas->filter(function ($persona) {
                $nacionalidadSinAcentos = str_replace(
                    ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'Á', 'É', 'Í', 'Ó', 'Ú', 'Ñ'],
                    ['a', 'e', 'i', 'o', 'u', 'n', 'A', 'E', 'I', 'O', 'U', 'N'],
                    $persona->nacionalidad
                );

                $path = public_path('images/' . $nacionalidadSinAcentos . '.gif');
                return !file_exists($path); // Solo las que no tienen bandera
            });


            return view('jugadores.verificarPersona', [ 'verificados' => $verificados,'total' => $total,'similaresNombreApellido' => $personasSimilares, 'personas' => $personas, 'personasSinNombreApellido' => $personasSinNombreApellido, 'personasSinBandera' => $personasSinBandera]);
        }


    public function verificarSimilitud(Request $request)
    {
        $persona_id = $request->input('persona_id');
        $simil_id = $request->input('simil_id');

        // Verificar si la similitud ya existe en la tabla personas
        $persona = \DB::table('personas_verificadas')
            ->where('persona_id', $persona_id)
            ->where('simil_id', $simil_id)
            ->first();

        // Si no existe, crear la similitud
        if (!$persona) {
            \DB::table('personas_verificadas')->insert([
                'persona_id' => $persona_id,
                'simil_id' => $simil_id,
            ]);
        }

        return redirect()->back()->with('success', 'Verificado correctamente');
    }

    public function reasignar($id)
    {
        $jugador=Jugador::findOrFail($id);

        return view('jugadores.reasignar', compact('jugador'));
    }

    public function guardarReasignar(Request $request)
    {
        // Validar los datos de entrada
        $request->validate([
            'jugadorId' => 'required|integer|exists:jugadors,id',
            'reasignarId' => 'required|integer|exists:jugadors,id|different:jugadorId',
        ]);

        $jugadorActual = $request->input('jugadorId');
        $jugadorNuevo = $request->input('reasignarId');

        try {
            // Inicia una transacción para garantizar que todas las actualizaciones se completen
            DB::beginTransaction();

            // Actualizar en las tablas necesarias
            DB::update('UPDATE alineacions SET jugador_id = ? WHERE jugador_id = ?', [$jugadorNuevo, $jugadorActual]);
            DB::update('UPDATE plantilla_jugadors SET jugador_id = ? WHERE jugador_id = ?', [$jugadorNuevo, $jugadorActual]);
            DB::update('UPDATE gols SET jugador_id = ? WHERE jugador_id = ?', [$jugadorNuevo, $jugadorActual]);
            DB::update('UPDATE cambios SET jugador_id = ? WHERE jugador_id = ?', [$jugadorNuevo, $jugadorActual]);
            DB::update('UPDATE tarjetas SET jugador_id = ? WHERE jugador_id = ?', [$jugadorNuevo, $jugadorActual]);
            DB::update('UPDATE penals SET jugador_id = ? WHERE jugador_id = ?', [$jugadorNuevo, $jugadorActual]);

            $jugador = Jugador::find($jugadorActual);
            $persona = Persona::find($jugador->persona_id);
            $jugador->delete();
// Verificar si la persona tiene una foto y eliminarla del servidor
            if ($persona->foto && file_exists(public_path('images/' . $persona->foto))) {
                //unlink(public_path('images/' . $persona->foto)); // Eliminar la foto del servidor
            }
            $persona->delete();
            // Confirmar la transacción
            DB::commit();

            // Redirigir con un mensaje de éxito
            return redirect()->route('jugadores.verificarPersonas')->with('success', 'Jugador reasignado exitosamente.');
        } catch (\Exception $e) {
            Log::info('Error: ' . $e->getMessage(), []);
            // Revertir los cambios si hay algún error
            DB::rollBack();

            // Regresar con un mensaje de error
            return redirect()->back()->withErrors(['error' => 'Hubo un problema al reasignar el jugador.']);
        }
    }


    public function verificarNombreApellidoSimple()
    {
        $cantidad = Persona::where(function ($q) {
            $q->where('verificado', 0)
                ->orWhereNull('verificado');
        })
            ->whereRaw("TRIM(name) = TRIM(CONCAT(nombre, ' ', apellido))")
            ->whereRaw("LENGTH(TRIM(name)) - LENGTH(REPLACE(TRIM(name), ' ', '')) = 1")
            ->update(['verificado' => 1]);

        return redirect()
            ->back()
            ->with('success', "Se verificaron automáticamente {$cantidad} personas.");
    }

    public function confirmarNombresLargos(Request $request)
    {
        $seleccionados = $request->input('personas', []);

        if (empty($seleccionados)) {
            return back()->with('status', 'No se seleccionó ningún registro.');
        }

        foreach ($seleccionados as $personaId => $nombre) {
            Persona::where('id', $personaId)->update([
                'name'       => $nombre,
                'verificado' => 1,
            ]);
        }

        return back()->with('status', 'Nombres confirmados correctamente.');
    }


}
