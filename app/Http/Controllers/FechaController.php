<?php

namespace App\Http\Controllers;

use App\Alineacion;
use App\Arbitro;
use App\Cambio;
use App\Equipo;
use App\Fecha;
use App\Gol;
use App\Grupo;
use App\Incidencia;
use App\Jugador;
use App\Partido;
use App\PartidoArbitro;
use App\Penal;
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
use App\Services\HttpHelper;

use Illuminate\Support\Carbon;
use DateTime;

class FechaController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth')->except(['ver','showPublic','detalle','fixture']);
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


        $fechas=Fecha::where('grupo_id','=',"$grupo_id")->where('numero','like',"%$nombre%")->orderBy('orden','ASC')->get();

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
                    $esNeutral = 0;
                    if (isset($request->neutral[$item]) ) {
                        $esNeutral=1;
                    }
                    $bloqueado = 0;
                    if (isset($request->bloquear[$item]) ) {
                        $bloqueado=1;
                    }
                    $data2 = [
                        'fecha_id' => $lastid,
                        'dia' => $request->fecha[$item] . ' ' . $request->hora[$item],
                        'equipol_id' => $request->equipol[$item],
                        'equipov_id' => $request->equipov[$item],
                        'golesl' => $request->golesl[$item],
                        'golesv' => $request->golesv[$item],
                        'orden' => $request->orden[$item],
                        'neutral' => $esNeutral,
                        'bloqueado' => $bloqueado,
                    ];

// Agregar penales solo si fueron enviados
                    if (isset($request->penalesl[$item]) && isset($request->penalesv[$item])) {
                        $data2['penalesl'] = $request->penalesl[$item];
                        $data2['penalesv'] = $request->penalesv[$item];
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
            \App\Services\PlayoffHelper::actualizarCruces($fecha->grupo->id);
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
        //dd($request);
        //
        $this->validate($request,[ 'numero'=>'required',  'grupo_id'=>'required']);
        DB::beginTransaction();

        if($request->partido_id){
            Partido::where('fecha_id',"$id")->whereNotIn('id', $request->partido_id)->delete();
        }

        $fecha=fecha::find($id);
        $ok=1;
        try {
            $fecha->update([
                'numero'     => $request->numero,
                'grupo_id'   => $request->grupo_id,
                'url_nombre' => $request->url_nombre,
                'orden'      => $request->orden_fecha, // 👈 distinto nombre
            ]);
            //Partido::where('fecha_id', '=', "$id")->delete();
            if (is_array($request->fecha) && count($request->fecha) > 0)
            {
                foreach($request->fecha as $item=>$v){

                    $esNeutral = 0;
                    if (isset($request->neutral[$item]) ) {
                        $esNeutral=1;
                    }

                    $bloqueado = 0;
                    if (isset($request->bloquear[$item]) ) {
                        $bloqueado=1;
                    }

                    $data2 = [
                        'fecha_id' => $id,
                        'dia' => $request->fecha[$item] . ' ' . $request->hora[$item],
                        'equipol_id' => $request->equipol[$item],
                        'equipov_id' => $request->equipov[$item],
                        'golesl' => $request->golesl[$item],
                        'golesv' => $request->golesv[$item],
                        'orden' => $request->orden[$item],
                        'neutral' => (int) $esNeutral,
                        'bloquear' => (int) $bloqueado
                    ];

                    // Agregar penales solo si fueron enviados
                    if (isset($request->penalesl[$item]) && isset($request->penalesv[$item])) {
                        $data2['penalesl'] = $request->penalesl[$item];
                        $data2['penalesv'] = $request->penalesv[$item];
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
            else{
                // Eliminar primero los partidos asociados
                Partido::where('fecha_id', $id)->delete();
                $fecha->delete();
            }
        }catch(Exception $e){
            //if email or phone exist before in db redirect with error messages
            $ok=0;
        }
        if ($ok){
            DB::commit();
           \App\Services\PlayoffHelper::actualizarCruces($fecha->grupo->id);
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

        $grupos = Grupo::where('torneo_id', $grupo->torneo->id)
            ->pluck('nombre', 'id'); // Obtiene los nombres con los IDs como clave

        //
        return view('fechas.import', compact('grupo','grupos'));
    }

    public function importincidencias(Request $request)
    {
        $grupo_id= $request->query('grupoId');
        $grupo=Grupo::findOrFail($grupo_id);

        //
        return view('fechas.importincidencias', compact('grupo'));
    }

    public function formatearMarcador($marcador)
    {
        // Expresión regular para capturar los goles en tiempo reglamentario, los goles de los dos tiempos y los penales si están presentes
        $pattern = '/(\d+):(\d+)\s?\((\d+):(\d+)(?:,\s?(\d+):(\d+))?(?:,\s?(\d+):(\d+))?\)\s?(pn\.)?$/';

        //Log::debug("Marcador recibido: " . $marcador);

        // Intentamos hacer el match con la cadena
        $matches = [];
        preg_match($pattern, $marcador, $matches);

        if (empty($matches)) {
            //Log::debug("No se encontró un marcador válido.");
            return null;
        }

        //Log::debug(print_r($matches, true));

        // Buscamos la posición de 'pn.' en el arreglo de coincidencias
        $indicePn = array_search('pn.', $matches, true);

        // Si no hay penales
        if ($indicePn === false) {
            // Si no hay penales, el marcador final es el de tiempo reglamentario (índices 1 y 2)
            $gl = (int) $matches[1]; // Goles Local
            $gv = (int) $matches[2]; // Goles Visitante
            $pl = $pv = null; // No hay goles de penales
            //Log::debug("sin penales gl: " . $gl. ' gv: ' . $gv. ' pl: ' . $pl. ' pv: ' . $pv);
        } else {
            // Si hay penales, tomamos los goles de los penales (índices 1 y 2) y el marcador final es el último
            $gl = (int) $matches[5]; // Goles Local
            $gv = (int) $matches[6]; // Goles Visitante
            $pl = (int) $matches[1]; // Goles en penales Local
            $pv = (int) $matches[2]; // Goles en penales Visitante
            //Log::debug("con penales gl: " . $gl. ' gv: ' . $gv. ' pl: ' . $pl. ' pv: ' . $pv);
            // Si hay prórroga, el marcador final se toma de los goles del último tiempo (índices 5 y 6, o 3 y 4 si no hay segundo tiempo)
            if (isset($matches[7]) && isset($matches[8])) {
                // Si hay goles de segundo tiempo, los últimos goles son los del segundo tiempo
                $gl = (int) $matches[7];
                $gv = (int) $matches[8];
                //Log::debug("con prorroga gl: " . $gl. ' gv: ' . $gv. ' pl: ' . $pl. ' pv: ' . $pv);
            }
        }

        return [
            'gl' => $gl, // Marcador final después de tiempos reglamentarios o penales
            'gv' => $gv,
            'pl' => $pl, // Goles en penales (si hay)
            'pv' => $pv
        ];
    }





    public function importprocess_new(Request $request)
    {
        $url2 = $request->get('url2');
        $ok=1;
        DB::beginTransaction();
        $error='';
        $success='';
        $html2='';
        try {
            if ($url2){


                //$html2 = HtmlDomParser::file_get_html($url2, false, null, 0);
                $html2 =  HttpHelper::getHtmlContent($url2);
            }


        }
        catch (Exception $ex) {
            $html2='';
        }
        if ($html2) {
            // Crea un nuevo DOMDocument
            $dom = new \DOMDocument();

            // Evita advertencias de HTML mal formado
            libxml_use_internal_errors(true);

            // Carga el HTML en el DOMDocument
            $dom->loadHTML($html2);

            // Restaura la gestión de errores de libxml
            libxml_clear_errors();

            // Intenta cargar el HTML recuperado
            if ($dom->loadHTML($html2)) {
                $xpath = new \DOMXPath($dom);

                // Buscar el `option` que tiene el atributo `selected`
                $selectedOption = $xpath->query('//select[@name="phase"]/option[@selected]');

                if ($selectedOption->length > 0) {
                    $numero = trim($selectedOption[0]->textContent);


                } else {
                    // Buscar el `option` que tiene el atributo `selected`
                    $selectedOption = $xpath->query('//select[@name="runde"]/option[@selected]');
                    $numero = trim($selectedOption[0]->textContent);
                }
                $matches = $xpath->query('//table[@class="standard_tabelle"]');

                $pattern = '/([IV]):\s*(\d{2}\.\d{2}\.\d{4})\s*(\d{2}:\d{2})/'; // Expresión regular para extraer las fechas

// Array para almacenar los partidos
                $partidos = [];


                foreach ($matches as $match) {
                    // Obtener todas las filas dentro de la tabla
                    $rows = $match->getElementsByTagName('tr');

                    foreach ($rows as $row) {
                        // Obtener todas las celdas dentro de la fila
                        $cols = $row->getElementsByTagName('td');

                        if ($cols->length > 0) { // Verifica que haya datos en la fila

                            if ($cols->length == 7) { // Fila con información del partido (equipos y fecha/hora)
                                if (trim($cols[0]->textContent)!=''){
                                    $fecha = trim($cols[0]->textContent);
                                    list($dia, $mes, $anio) = explode('.', $fecha);

// Crear la variable para la fecha en el formato "yyyy-mm-dd"
                                    $fechaFormateada = "$anio-$mes-$dia";
                                }
                                $hora = trim($cols[1]->textContent);
                                $equipo1 = trim($cols[2]->textContent);
                                $equipo2 = trim($cols[4]->textContent);
                                $marcador = $this->formatearMarcador(trim($cols[5]->textContent));

// Combinar fecha y hora
                                $fechaHora = DateTime::createFromFormat('Y-m-d H:i', $fechaFormateada . ' ' . $hora);

// Si no es de arg.worldfootball.net, restamos 4 horas
                                if (strpos($url2, 'arg.worldfootball.net') === false && $fechaHora) {
                                    $fechaHora->modify('-5 hours');
                                }

// Almacenar el partido con la nueva fecha y hora (ya modificada si corresponde)
                                $partidos[] = [
                                    'fecha' => $fechaHora ? $fechaHora->format('Y-m-d') : $fechaFormateada,
                                    'hora' => $fechaHora ? $fechaHora->format('H:i') : $hora,
                                    'equipo1' => $equipo1,
                                    'equipo2' => $equipo2,
                                    'marcador' => $marcador,
                                ];
                            }
                            if ($cols->length == 6) { // Fila con información del partido (equipos y fecha/hora)

                                $equipo1 = trim($cols[1]->textContent);
                                $equipo2 = trim($cols[3]->textContent);
                                $marcador = $this->formatearMarcador(trim($cols[4]->textContent));


                                // Almacenar el partido con la fecha y equipos
                                $partidos[] = [

                                    'equipo1' => $equipo1,
                                    'equipo2' => $equipo2,
                                    'marcador' => $marcador,
                                ];
                            }
                            else{
                                foreach ($cols as $col) {
                                    $textoColumna = trim($col->textContent);

                                    if (strpos($textoColumna, 'I:') === 0) {
                                        $fechaHora = substr($textoColumna, 3);
                                        $partes = explode(' ', $fechaHora);
                                        $fecha = $partes[0] ?? null;
                                        $hora = $partes[1] ?? '00:00';

                                        list($dia, $mes, $anio) = explode('.', $fecha);
                                        $fechaFormateada = "$anio-$mes-$dia";

                                        $fechaHoraObj = DateTime::createFromFormat('Y-m-d H:i', $fechaFormateada . ' ' . $hora);

                                        if (strpos($url2, 'https://arg.worldfootball.net/') === false && $fechaHoraObj) {
                                            $fechaHoraObj->modify('-5 hours');
                                        }

                                        $partidos[count($partidos) - 2]['fecha'] = $fechaHoraObj ? $fechaHoraObj->format('Y-m-d') : $fechaFormateada;
                                        $partidos[count($partidos) - 2]['hora'] = $fechaHoraObj ? $fechaHoraObj->format('H:i') : $hora;
                                    }

                                    if (strpos($textoColumna, 'V:') === 0) {
                                        $fechaHora = substr($textoColumna, 3);
                                        $partes = explode(' ', $fechaHora);
                                        $fecha = $partes[0] ?? null;
                                        $hora = $partes[1] ?? '00:00';

                                        list($dia, $mes, $anio) = explode('.', $fecha);
                                        $fechaFormateada = "$anio-$mes-$dia";

                                        $fechaHoraObj = DateTime::createFromFormat('Y-m-d H:i', $fechaFormateada . ' ' . $hora);

                                        if (strpos($url2, 'https://arg.worldfootball.net/') === false && $fechaHoraObj) {
                                            $fechaHoraObj->modify('-5 hours');
                                        }

                                        $partidos[count($partidos) - 1]['fecha'] = $fechaHoraObj ? $fechaHoraObj->format('Y-m-d') : $fechaFormateada;
                                        $partidos[count($partidos) - 1]['hora'] = $fechaHoraObj ? $fechaHoraObj->format('H:i') : $hora;
                                    }

                                }
                            }
                        }
                    }
                }

                $golesL = null;
                $golesV = null;
                /*echo "<pre>";
                print_r($partidos);
                echo "</pre>";*/
                //$grupo_id=intval($importData[1]);
                $grupoId = $request->get('grupoSelect_id');
                if($numero){
                    foreach ($partidos as $index => $partido) {
                        $dia =$partido['fecha'].' '.$partido['hora'];
                        $strEquipoL = trim($partido['equipo1']);
                        /*$golesL = intval($partido['marcador']['gl']);
                        $golesV = intval($partido['marcador']['gv']);*/
                        $golesL = isset($partido['marcador']['gl']) ? ($partido['marcador']['gl'] === null ? null : intval($partido['marcador']['gl'])) : null;
                        $golesV = isset($partido['marcador']['gv']) ? ($partido['marcador']['gv'] === null ? null : intval($partido['marcador']['gv'])) : null;
                        $penalesL = isset($partido['marcador']['pl']) ? ($partido['marcador']['pl'] === null ? null : intval($partido['marcador']['pl'])) : null;
                        $penalesV = isset($partido['marcador']['pv']) ? ($partido['marcador']['pv'] === null ? null : intval($partido['marcador']['pv'])) : null;

                        $equipol = Equipo::where('nombre', 'like', "%$strEquipoL%")->get();

                        if ($equipol->isEmpty()) {
                            //Log::channel('mi_log')->info('Equipo NO encontrado: '.$numero.'-'.$dia.'-'.$strEquipoL,[]);
                            $error .='Equipo NO encontrado: '.$numero.'-'.$dia.'-'.$strEquipoL.'<br>';
                            $ok=0;
                        }
                        else{

                            $grupo=Grupo::findOrFail($grupoId);
                            $grupos = Grupo::where('torneo_id', '=', $grupo->torneo->id)->pluck('id')->toArray();
                            $torneo=Torneo::findOrFail($grupo->torneo->id);
                            $plantilla = Plantilla::whereIn('grupo_id', $grupos)
                                ->whereIn('equipo_id', $equipol->pluck('id')->toArray())
                                ->first();


                            if (!$plantilla) {

                                $error .='Equipo sin plantilla: '.$strEquipoL.'<br>';
                                $ok=0;
                            }
                            else{
                                $idLocal = $plantilla->equipo->id;
                                //$grupo_id = $plantilla->grupo->id;
                                $grupo_id = $grupoId;
                                $strEquipoV = trim($partido['equipo2']);
                                //$equipoV = Equipo::where('nombre', 'like', "%$strEquipoV%")->first();
                                $equipoV = Equipo::where('nombre', 'like', "%$strEquipoV%")->get();

                                if ($equipoV->isEmpty()) {
                                    //Log::channel('mi_log')->info('Equipo NO encontrado: '.$numero.'-'.$dia.'-'.$strEquipoV,[]);
                                    $error .='Equipo NO encontrado: '.$numero.'-'.$dia.'-'.$strEquipoV.'<br>';
                                    $ok=0;
                                }
                                else{
                                    // Solo calcular la jornada si $numero contiene "Grupo"
                                    $fechaNumero = $numero;
                                    if (str_contains($numero, 'Grupo')) {

                                        // Obtener la cantidad de equipos en el grupo
                                        $cantidadEquipos = Plantilla::where('grupo_id', $grupo_id)->count();


                                        if ($cantidadEquipos > 0) {
                                            // En cada jornada juegan la mitad de los equipos
                                            $partidosPorJornada = $cantidadEquipos / 2;

                                            // Si la cantidad de equipos es impar, ajustar
                                            if ($cantidadEquipos % 2 != 0) {
                                                $partidosPorJornada = ($cantidadEquipos - 1) / 2;
                                            }

                                            $fechaNumero = intval($index / $partidosPorJornada) + 1;

                                        }
                                    }
                                    //$plantilla = Plantilla::wherein('grupo_id',explode(',', $arrgrupos))->where('equipo_id','=',$equipoV->id)->first();

                                    $plantilla = Plantilla::whereIn('grupo_id', $grupos)
                                        ->whereIn('equipo_id', $equipoV->pluck('id')->toArray())
                                        ->first();


                                    //dd($plantilla);
                                    if (!$plantilla) {

                                        $error .='Equipo sin plantilla: '.$strEquipoV.'<br>';
                                        $ok=0;
                                    }
                                    else{
                                        $idVisitante = $plantilla->equipo->id;
                                        $nro = str_replace('. Jornada', '', $fechaNumero); // Elimina ". Jornada"
                                        $nro= str_pad($nro, 2, "0", STR_PAD_LEFT);

                                        $fecha=Fecha::where('grupo_id','=',"$grupo_id")->where('numero','=',"$nro")->first();

                                        try {

                                            if(!$fecha){

                                                $data1=array(

                                                    'numero'=>$nro,
                                                    'grupo_id'=>$grupo_id
                                                );
                                                //Log::debug(print_r($data1),[]);
                                                //Log::debug(print_r($data1, true));
                                                $fecha = fecha::create($data1);


                                            }
                                            $lastid=$fecha->id;


                                            $data2=array(
                                                'fecha_id'=>$lastid,
                                                'dia'=>$dia,
                                                'equipol_id'=>$idLocal,
                                                'equipov_id'=>$idVisitante,
                                                'golesl'=>$golesL,
                                                'golesv'=>$golesV,
                                                'penalesl'=>$penalesL,
                                                'penalesv'=>$penalesV,
                                                'neutral' => $torneo->neutral,
                                            );
                                            $guardarPartido = true;
                                            $verificado=$request->get('verificado');
                                            if ($verificado) {
                                                $guardarPartido = false;
                                                // Verificar si ambos equipos están en el mismo grupo
                                                $plantillaLocalGrupo = Plantilla::where('grupo_id', $grupo_id)
                                                    ->where('equipo_id', $idLocal)
                                                    ->exists();

                                                $plantillaVisitanteGrupo = Plantilla::where('grupo_id', $grupo_id)
                                                    ->where('equipo_id', $idVisitante)
                                                    ->exists();

                                                if ($plantillaLocalGrupo && $plantillaVisitanteGrupo) {
                                                    //Log::channel('mi_log')->info('Equipos del grupo: ' . $strEquipoL . ' - ' . $strEquipoV, []);
                                                    // ✅ Caso 1: ambos equipos son del grupo actual → se guarda normalmente
                                                    $guardarPartido = true;
                                                } elseif (
                                                    ($plantillaLocalGrupo && !$plantillaVisitanteGrupo) ||
                                                    (!$plantillaLocalGrupo && $plantillaVisitanteGrupo)
                                                ) {
                                                    //Log::channel('mi_log')->info('1 equipo del grupo: ' . $strEquipoL . ' - ' . $strEquipoV, []);
                                                    // ✅ Solo uno de los dos es del grupo
                                                    $partidoExistente = Partido::whereDate('dia', $dia)
                                                        ->where(function ($q) use ($idLocal, $idVisitante) {
                                                            $q->where(function ($q2) use ($idLocal, $idVisitante) {
                                                                $q2->where('equipol_id', $idLocal)
                                                                    ->where('equipov_id', $idVisitante);
                                                            })
                                                                ->orWhere(function ($q2) use ($idLocal, $idVisitante) {
                                                                    $q2->where('equipol_id', $idVisitante)
                                                                        ->where('equipov_id', $idLocal);
                                                                });
                                                        })
                                                        ->exists();

                                                    if (!$partidoExistente) {
                                                        $guardarPartido = true;
                                                    }
                                                }
                                            }
                                            if ($guardarPartido) {
                                                //Log::debug(print_r($data2, true));
                                                $partido = Partido::where('fecha_id', '=', "$lastid")->where('equipol_id', '=', "$idLocal")->where('equipov_id', '=', "$idVisitante")->first();
                                                try {
                                                    if (!empty($partido)) {

                                                        $partido->update($data2);
                                                    } else {
                                                        $partido = Partido::create($data2);
                                                    }


                                                } catch (QueryException $ex) {
                                                    if ($ex->errorInfo[1] == 1062) { // Código de error para "Duplicate entry"
                                                        $success .= "Equipo repetido en partido " . $strEquipoL . ' - ' . $strEquipoV . "<br>";
                                                        $ok = 1;
                                                    } else {
                                                        $error = $ex->getMessage();
                                                        $ok = 0;
                                                    }

                                                    continue;
                                                }
                                            }
                                            else{
                                                $success .= "No se guardo el partido " . $strEquipoL . ' - ' . $strEquipoV . "<br>";
                                            }



                                        }catch(Exception $e){

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



            }
        }
        if ($ok){



            DB::commit();
            $respuestaID='success';
            $respuestaMSJ=$success;
        }
        else{
            DB::rollback();
            $respuestaID='error';
            $respuestaMSJ=$error;
        }

        //
        return redirect()->route('fechas.index', array('grupoId' => $grupoId))->with($respuestaID,$respuestaMSJ);
    }

    public function importprocess(Request $request)
    {

        set_time_limit(0);


        $url2 = $request->get('url2');

        if ($url2){
            return $this->importprocess_new($request);
        }



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

                while (($filedata = fgetcsv($file, 1000, ";")) !== FALSE) {
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
                $success='';
                foreach($importData_arr as $importData){
                    //Log::channel('mi_log')->info('Fecha: '.$importData[0]);
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
                             //Log::channel('mi_log')->info('Equipo NO encontrado: '.$numero.'-'.$dia.'-'.$strEquipoL,[]);
                             $success .='Equipo NO encontrado: '.$numero.'-'.$dia.'-'.$strEquipoL.'<br>';
                         }
                        else{
                            $strEquipoV = ($importData[6]);
                            $equipoV = Equipo::where('nombre', 'like', "%$strEquipoV%")->first();

                            if (!$equipoV){
                                //Log::channel('mi_log')->info('Equipo NO encontrado: '.$numero.'-'.$dia.'-'.$strEquipoV,[]);
                                $success .='Equipo NO encontrado: '.$numero.'-'.$dia.'-'.$strEquipoL.'<br>';
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
                                        $error .= $ex->getMessage();
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
            $respuestaMSJ=$success;
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
                                                    //Log::channel('mi_log')->info('Incidencia: ' . trim($incidencia[0]).' MIN: '.intval(trim($incidencia[1])),[]);
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
                                                                //Log::channel('mi_log')->info('NO se encontró al jugador: ' . $jugador['dorsal'].' del equipo '.$strEquipoL,[]);
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
                                                            //Log::channel('mi_log')->info('NO se encontró al jugador: ' . $jugador['dorsal'].' del equipo '.$strEquipoL,[]);
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
                                                //Log::channel('mi_log')->info('NO se encontró como entrenador: ' . $equipos[$i - 1]['entrenador'].' del equipo '.$equipos[$i - 1]['equipo'],[]);
                                            }
                                        }
                                        else{
                                            //Log::channel('mi_log')->info('NO se encontró al entrenador: ' . $equipos[$i - 1]['entrenador'].' del equipo '.$equipos[$i - 1]['equipo'],[]);
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
                                                //Log::channel('mi_log')->info('Incidencia: ' . trim($incidencia[0]).' MIN: '.intval(trim($incidencia[1])),[]);
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
                                                        //Log::channel('mi_log')->info('NO se encontró al jugador: ' . $jugador['dorsal'].' del equipo '.$strEquipoV,[]);
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
                                                        //Log::channel('mi_log')->info('NO se encontró al jugador: ' . $jugador['dorsal'].' del equipo '.$strEquipoV,[]);
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
                                                //Log::channel('mi_log')->info('NO se encontró como entrenador: ' . $equipos[$i]['entrenador'].' del equipo '.$equipos[$i]['equipo'],[]);
                                            }
                                        }
                                        else{
                                            //Log::channel('mi_log')->info('NO se encontró al entrenador: ' . $equipos[$i]['entrenador'].' del equipo '.$equipos[$i]['equipo'],[]);
                                        }
                                    }



                                }
                                else{
                                    //Log::channel('mi_log')->info('NO se encontró al partido: ' . $equipos[$i-1]['equipo'].' VS '.$equipos[$i]['equipo'],[]);
                                }
                            }
                            else{
                                //Log::channel('mi_log')->info('NO se encontró al equipo: ' . $equipos[$i]['equipo'],[]);
                            }
                        }
                        else{
                            //Log::channel('mi_log')->info('NO se encontró al equipo: ' . $equipos[$i-1]['equipo'],[]);
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
            case 'Athletico Paranaense':
                $strEquipoURL='paranaense';
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
        //$strEquipoURL=strtr($strEquipo, " ", "-");
        $strEquipoURL='';
        $equipo = Equipo::where('nombre', 'like', "%$strEquipo%")->first();
        //dd($equipo);
        if($equipo->url_id){
            $strEquipoURL=$equipo->url_id;
        }
        else{
            //Log::channel('mi_log')->info('Ojo!!! falta equipo: '.$strEquipo, []);
            return false;
        }
        /*switch (trim($strEquipo)) {
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
            case 'América de Cali':
                $strEquipoURL='116';

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
            case 'Athletico Paranaense':
                $strEquipoURL='115';

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
            case 'Centro Español':
                $strEquipoURL='1785';

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
            case 'Club Ciudad de Bolívar':
                $strEquipoURL='3140';
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
            case 'Defensores de Cambaceres':
                $strEquipoURL='1801';//ultimo

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
            case 'Independiente Medellín':

                $strEquipoURL='114';
                break;
            case 'Independiente Rivadavia':

                $strEquipoURL='684';
                break;
            case 'Ituzaingó':

                $strEquipoURL='1200';
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
            case 'Libertad':
                $strEquipoURL='65';//ultimo

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
            case 'Puerto Nuevo':
                $strEquipoURL='1983';

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
            case 'São Paulo':
                $strEquipoURL='46';
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
            case 'The Strongest':
                $strEquipoURL='51';
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
            case 'Universidad de Chile':
                $strEquipoURL='113';
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
            case 'Yupanqui':
                $strEquipoURL='1783';
                break;
            default:
                Log::channel('mi_log')->info('Ojo!!! falta equipo: '.$strEquipoURL, []);
                break;
        }*/
        return $strEquipoURL;
    }
    public function dameNombreEquipoURL3($strEquipo)
    {
        //$strEquipoURL=strtr($strEquipo, " ", "-");
        //Log::channel('mi_log')->info('Equipo: '.$strEquipo, []);
        $equipo = Equipo::where('nombre', 'like', "%$strEquipo%")->first();
        $arrEquipo = array();
        if($equipo->url_nombre){
            //Log::channel('mi_log')->info('URL Equipo: '.$equipo->url_nombre, []);
            $arrEquipo=explode(',',$equipo->url_nombre);

            //Log::info('Contenido del array: ' . print_r($arrEquipo, true));
        }
        else{
            //Log::channel('mi_log')->info('Ojo!!! no esta: '.$strEquipo, []);
            return false;
        }

        /*switch (trim($strEquipo)) {
            case 'Acassuso':
                $arrEquipo[]='acassuso';

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
            case 'América de Cali':
                $arrEquipo[]='america-de-cali';

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
                $arrEquipo[]='c-ballester';
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
            case 'Centro Español':
                $arrEquipo[]='centro-español';
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
            case 'Club Ciudad de Bolívar':
                $arrEquipo[]='cd-de-bolivar';

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
            case 'Defensores de Cambaceres':
                $arrEquipo[]='cambaceres';

                break;
            case 'Defensores de Pronunciamiento':
                $arrEquipo[]='depro';

                break;
            case 'Defensores de Villa Ramallo':
                $arrEquipo[]='defensores-ram';

                break;
            case 'Defensores Unidos de Zárate':
                $arrEquipo[]='defensores-un.';
                $arrEquipo[]='defensores-un';
                break;

            case 'Deportivo Armenio':
                $arrEquipo[]='dep-armenio';

                break;
            case 'Deportivo Español':
                $arrEquipo[]='social-espanol';

                break;
            case 'Deportivo Madryn':
                $arrEquipo[]='dep.-madryn';
                $arrEquipo[]='dep-madryn';
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
                $arrEquipo[]='dep-riestra';
                break;
            case 'Deportivo Rincón';
                $arrEquipo[]='dep.-rincon';
                $arrEquipo[]='dep-rincon';
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
                $arrEquipo[]='sp-estudiantes';
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
                $arrEquipo[]='gimnasia-men';
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
                $arrEquipo[]='gimnasia-y-tiro';
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
            case 'Independiente Medellín':

                $arrEquipo[]='independ.-m';
                break;
            case 'Independiente de Chivilcoy':
                $arrEquipo[]='indep.-chiv.';
                $arrEquipo[]='indep-chiv';
                break;
            case 'Independiente Rivadavia':
                $arrEquipo[]='ind.-rivadavia';
                $arrEquipo[]='ind-rivadavia';
                break;
            case 'Ituzaingó':
                $arrEquipo[]='ituzaingo';

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
                $arrEquipo[]='dep-laferrere';
                break;
            case 'Lanús':
                $arrEquipo[]='lanus';
                $arrEquipo[]='ca-lanus';
                break;
            case 'Leandro N. Alem':
                $arrEquipo[]='alem';

                break;
            case 'Libertad':
                $arrEquipo[]='libertad';

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
                $arrEquipo[]='sp-penarol';
                break;
            case 'Platense':
                $arrEquipo[]='platense';
                break;
            case 'Puerto Nuevo':
                $arrEquipo[]='puerto-nuevo';
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
                $arrEquipo[]='sp-rivadavia';
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
                $arrEquipo[]='sp-san-martin';
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
            case 'São Paulo':
                $arrEquipo[]='san-pablo';

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
                $arrEquipo[]='sp-barracas';
                break;
            case 'Sportivo Belgrano':
                $arrEquipo[]='sp-belgrano';

                break;
            case 'Sportivo Las Parejas':
                $arrEquipo[]='sp.-las-parejas';
                $arrEquipo[]='sp-las-parejas';
                break;
            case 'Talleres (Cba.)':
                $arrEquipo[]='talleres';
                $arrEquipo[]='talleres-cordoba';
                break;
            case 'Talleres de Remedios de Escalada':
                $arrEquipo[]='talleres-rem.';
                $arrEquipo[]='talleres-rem';
                break;
            case 'Temperley':
                $arrEquipo[]='ca-temperley';
                $arrEquipo[]='temperley';
                break;
            case 'The Strongest':
                $arrEquipo[]='the-strongest';

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
                $arrEquipo[]='union-sun';
                break;
            case 'Universidad de Chile':
                $arrEquipo[]='univ.-de-chile';
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
            case 'Yupanqui':
                $arrEquipo[]='yupanqui';
                break;
            default:
                Log::channel('mi_log')->info('Ojo!!! no esta: '.$strEquipoURL, []);
                break;
        }*/
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

    public function dameNombreTorneoURL($strTorneo,$fecha,$anexo='')
    {
        //Log::channel('mi_log')->info($strTorneo.' - '.$fecha.' - '.$year, []);
        /*$strTorneo=strtr($strTorneo, " ", "-");
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
        if (strpos($fecha, '1er Ronda') !== false) {
            $fecha = 'primera-fase';

        }*/
        if ($anexo){
            $fecha .= '-'.$anexo;
        }
        /*if (strpos($strTorneo, 'copa-argentina') !== false) {

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
                case '2017':
                    $strTorneoURL='copa-argentina-2017/'.$fecha;

                    break;
                case '2018':
                    $strTorneoURL='copa-argentina-2018/'.$fecha;

                    break;
                case '2019':
                    $strTorneoURL='copa-argentina-2019/'.$fecha;

                    break;
                case '2021':
                    $strTorneoURL='copa-argentina-2020/'.$fecha;

                    break;
                case '2022':
                    $strTorneoURL='copa-argentina-2022/'.$fecha;

                    break;
                case '2023':
                    $strTorneoURL='copa-argentina-2023/'.$fecha;

                    break;

            }
        }
        elseif (strpos($strTorneo, 'copa-de-la-superliga') !== false) {
            $strTorneoURL='copa-de-la-superliga-argentina-'.$year.'/'.$fecha;
            switch (trim($year)) {
                case '2020':
                    $strTorneoURL = 'copa-argentina-2011-12/' . $fecha;

                    break;
            }
        }
        else{
            $strTorneoURL='torneo-'.$strTorneo.'-'.$year.'/'.intval($fecha).'-fecha';
        }*/
        $strTorneoURL=$strTorneo.'/'.$fecha;
        //Log::channel('mi_log')->info($strTorneoURL, []);
        return $strTorneoURL;
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
            //Log::channel('mi_log')->info('Partido ' .$partido->equipol->nombre.' VS '.$partido->equipov->nombre, []);




            try {
                //Log::channel('mi_log')->info('Partido ' .$partido->equipol->nombre.' VS '.$partido->equipov->nombre, []);
                //Log::channel('mi_log')->info('OJO!!! URL ' .'https://www.livefutbol.com/cronica/primera-division-'.$years.'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'/', []);
                $html2 = HtmlDomParser::file_get_html('https://www.livefutbol.com/cronica/primera-division-'.$years.'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'/', false, null, 0);
                /*Log::channel('mi_log')->info('OJO!!! URL ' .'https://arg.worldfootball.net/cronica/copa-de-la-superliga-'.$years.'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'/', []);

                $html2 = HtmlDomParser::file_get_html('https://arg.worldfootball.net/cronica/copa-de-la-superliga-'.$years.'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'/', false, null, 0);*/

                if (!$html2){

                    //Log::channel('mi_log')->info('OJO!!! URL ' .'https://www.livefutbol.com/cronica/primera-division-'.$years.'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'_2/', []);
                    $html2 = HtmlDomParser::file_get_html('https://www.livefutbol.com/cronica/primera-division-'.$years.'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'_2/', false, null, 0);
                }
                $linkArray=array();
                $entrenadoresArray = array();
                $nombreArbitro ='';
                if (!$html2) {
                    //Log::channel('mi_log')->info('OJO!!! URL ' .'https://arg.worldfootball.net/cronica/primera-division-'.$years.'-'.strtr($nombreTorneo, " ", "-").'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'/', []);

                    $html2 = HtmlDomParser::file_get_html('https://arg.worldfootball.net/cronica/primera-division-'.$years.'-'.strtr($nombreTorneo, " ", "-").'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'/', false, null, 0);
                    /*Log::channel('mi_log')->info('OJO!!! URL ' .'https://arg.worldfootball.net/cronica/copa-de-la-superliga-'.$years.'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'/', []);

                    $html2 = HtmlDomParser::file_get_html('https://arg.worldfootball.net/cronica/copa-de-la-superliga-'.$years.'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'/', false, null, 0);*/

                }
                if (!$html2) {
                    //Log::channel('mi_log')->info('OJO!!! URL ' .'https://arg.worldfootball.net/cronica/copa-de-la-superliga-'.$years.'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'/', []);

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
                                            //Log::channel('mi_log')->info('OJO!! gol addicion: ');
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
                                                        //Log::channel('mi_log')->info('OJO!! cambio addicion: ');
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
                                                        //Log::channel('mi_log')->info('OJO!! cambio addicion: ');
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
                                                        //Log::channel('mi_log')->info('OJO!! cambio addicion: ');
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
                                                            //Log::channel('mi_log')->info('OJO!! tarjeta addicion: ');
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
                                                            //Log::channel('mi_log')->info('OJO!! tarjeta addicion: ');
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

                                                $amarillaSuplenteL = ($mintutoTarjetaSuplenteL)?(int)filter_var($mintutoTarjetaSuplenteL, FILTER_SANITIZE_NUMBER_INT):-1;
                                                //Log::channel('mi_log')->info('OJO!! amarilla suplente: ' . $amarillaSuplenteL, []);
                                            } else {
                                                $amarillaTitularL = ($mintutoTarjetaTitularL)?(int)filter_var($mintutoTarjetaTitularL, FILTER_SANITIZE_NUMBER_INT):-1;
                                                //Log::channel('mi_log')->info('OJO!! amarilla titular: ' . $amarillaTitularL, []);
                                            }

                                        }
                                        if ($td->find('img')[0]->title == 'Roja directa') {
                                            if ($suplentes) {
                                                $rojaSuplenteL = ($mintutoTarjetaSuplenteL)?(int)filter_var($mintutoTarjetaSuplenteL, FILTER_SANITIZE_NUMBER_INT):-1;
                                                //Log::channel('mi_log')->info('OJO!! roja suplente: ' . $rojaSuplenteL, []);
                                            } else {
                                                $rojaTitularL = ($mintutoTarjetaTitularL)?(int)filter_var($mintutoTarjetaTitularL, FILTER_SANITIZE_NUMBER_INT):-1;
                                                //Log::channel('mi_log')->info('OJO!! roja titular: ' . $rojaTitularL, []);
                                            }

                                        }
                                        if ($td->find('img')[0]->title == 'Doble amarilla') {
                                            if ($suplentes) {
                                                $dobleamarillaSuplenteL = ($mintutoTarjetaSuplenteL)?(int)filter_var($mintutoTarjetaSuplenteL, FILTER_SANITIZE_NUMBER_INT):-1;
                                                //Log::channel('mi_log')->info('OJO!! dobleamarilla suplente: ' . $dobleamarillaSuplenteL, []);
                                            } else {
                                                $dobleamarillaTitularL = ($mintutoTarjetaTitularL)?(int)filter_var($mintutoTarjetaTitularL, FILTER_SANITIZE_NUMBER_INT):-1;
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
                                        $incidenciasT[] = array('Tarjeta amarilla', ($amarillaTitularL==-1)?'':$amarillaTitularL);
                                    }
                                    if ($dobleamarillaTitularL) {
                                        $incidenciasT[] = array('Expulsado por doble amarilla', ($dobleamarillaTitularL==-1)?'':$dobleamarillaTitularL);
                                    }
                                    if ($rojaTitularL) {
                                        $incidenciasT[] = array('Tarjeta roja', ($rojaTitularL==-1)?'':$rojaTitularL);
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
                                                    //Log::channel('mi_log')->info('OJO!! cambio addicion: ');
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
                                                    //Log::channel('mi_log')->info('OJO!! cambio addicion: ');
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
                                                    //Log::channel('mi_log')->info('OJO!! cambio addicion: ');
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
                                                        //Log::channel('mi_log')->info('OJO!! tarjeta addicion: ');
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
                                                        //Log::channel('mi_log')->info('OJO!! tarjeta addicion: ');
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
                                            $amarillaSuplenteV = ($mintutoTarjetaSuplenteV)?(int) filter_var($mintutoTarjetaSuplenteV, FILTER_SANITIZE_NUMBER_INT):-1;
                                            //Log::channel('mi_log')->info('OJO!! amarilla suplente: ' . $mintutoTarjetaSuplenteV, []);
                                        }
                                        else{
                                            $amarillaTitularV = ($mintutoTarjetaTitularV)?(int) filter_var($mintutoTarjetaTitularV, FILTER_SANITIZE_NUMBER_INT):-1;
                                            //Log::channel('mi_log')->info('OJO!! amarilla titular: ' . $amarillaTitularV, []);
                                        }

                                    }
                                    if ($td->find('img')[0]->title == 'Roja directa') {
                                        if ($suplentes){
                                            $rojaSuplenteV =  ($mintutoTarjetaSuplenteV)?(int) filter_var($mintutoTarjetaSuplenteV, FILTER_SANITIZE_NUMBER_INT):-1;
                                            //Log::channel('mi_log')->info('OJO!! roja titular: ' . $rojaSuplenteV, []);
                                        }
                                        else{
                                            $rojaTitularV =  ($mintutoTarjetaTitularV)?(int) filter_var($mintutoTarjetaTitularV, FILTER_SANITIZE_NUMBER_INT):-1;
                                            //Log::channel('mi_log')->info('OJO!! roja titular: ' . $rojaTitularV, []);
                                        }

                                    }
                                    if ($td->find('img')[0]->title == 'Doble amarilla') {
                                        if ($suplentes){
                                            $dobleamarillaSuplenteV = ($mintutoTarjetaSuplenteV)?(int) filter_var($mintutoTarjetaSuplenteV, FILTER_SANITIZE_NUMBER_INT):-1;
                                            //Log::channel('mi_log')->info('OJO!! dobleamarilla titular: ' . $dobleamarillaSuplenteV, []);
                                        }
                                        else{
                                            $dobleamarillaTitularV = ($mintutoTarjetaTitularV)?(int) filter_var($mintutoTarjetaTitularV, FILTER_SANITIZE_NUMBER_INT):-1;
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
                                    $incidenciasT[]=array('Tarjeta amarilla', ($amarillaTitularV==-1)?'':$amarillaTitularV);
                                }
                                if ($dobleamarillaTitularV){
                                    $incidenciasT[]=array('Expulsado por doble amarilla', ($dobleamarillaTitularV==-1)?'':$dobleamarillaTitularV);
                                }
                                if ($rojaTitularV){
                                    $incidenciasT[]=array('Tarjeta roja', ($rojaTitularV==-1)?'':$rojaTitularV);
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
                        //Log::channel('mi_log')->info('OJO!! varios entrenadores: '.$partido->equipol->nombre.' VS '.$partido->equipov->nombre,[]);
                    }
                    if(count($entrenadoresArray)>1){
                        $dtLocal = $entrenadoresArray[1];
                        //Log::channel('mi_log')->info('DT Local: '.utf8_decode($entrenadoresArray[1]), []);
                        if (isset($entrenadoresArray[2])){
                            $dtVisitante = $entrenadoresArray[2];
                        }
                        else{
                            //Log::channel('mi_log')->info('OJO!! Falta Entrenador visitante: '.$partido->equipol->nombre.' VS '.$partido->equipov->nombre,[]);
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
                                            //Log::channel('mi_log')->info('Asistente 1: '.$nombreAsistente, []);
                                            $asistente++;
                                        }
                                        else{
                                            $asistente2= $nombreAsistente;
                                            //Log::channel('mi_log')->info('Asistente 2: '.$nombreAsistente, []);
                                            $asistente++;
                                        }

                                    }
                                    else{
                                        $nombreArbitro = '';
                                        for ($i = 1; $i < count($linkArray); $i++) {
                                            $nombreArbitro .= ($linkArray[$i]).' ';
                                        }

                                        //Log::channel('mi_log')->info('Arbitro: '.$nombreArbitro, []);

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
                    //Log::channel('mi_log')->info('OJO!! Arbitro NO encontrado: '.$nombreArbitro.' '.$partido->equipol->nombre.' VS '.$partido->equipov->nombre,[]);
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
                    //Log::channel('mi_log')->info('OJO!! Asistente NO encontrado: '.$asistente1.' '.$partido->equipol->nombre.' VS '.$partido->equipov->nombre,[]);
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
                    //Log::channel('mi_log')->info('OJO!! Asistente NO encontrado: '.$asistente2.' '.$partido->equipol->nombre.' VS '.$partido->equipov->nombre,[]);
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
                    //Log::channel('mi_log')->info('OJO!!! Técnico NO encontrado : ' . trim($dtLocal),[]);
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
                    //Log::channel('mi_log')->info('OJO!!! Técnico NO encontrado : ' . trim($dtVisitante),[]);
                }
                if (($golesL!=$golesLocales)||($golesV!=$golesVisitantes)) {
                    //Log::channel('mi_log')->info('OJO!!! No coincide la cantidad de goles en: ' . $partido->equipol->nombre . ' VS ' . $partido->equipov->nombre . ' -> ' . $golesL.' a '. $golesV. ' - ' . $golesLocales. ' a '.$golesVisitantes, []);
                }
                foreach ($equipos as $eq) {
                    //Log::channel('mi_log')->info('Equipo  ' . $eq['equipo'], []);
                    $strEquipo=trim($eq['equipo']);
                    $equipo=Equipo::where('nombre','like',"%$strEquipo%")->first();
                    if (!empty($equipo)){
                        foreach ($eq['jugadores'] as $jugador) {
                            //Log::channel('mi_log')->info('Jugador: ' . $jugador['dorsal'] . ' ' . $jugador['nombre'] . ' del equipo ' . $strEquipo, []);
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
                                    //Log::channel('mi_log')->info('OJO!!! con el jugador no está en la plantilla del equipo ' . $strEquipo, []);
                                }
                            }
                            else{
                                $plantillaJugador='';
                                //Log::channel('mi_log')->info('OJO!!! No hay plantilla del equipo ' . $strEquipo, []);
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
                                    //Log::channel('mi_log')->info('OJO!!! con jugador: ' . $jugador['dorsal'] . ' ' . $jugador['nombre'] . ' del equipo ' . $strEquipo, []);
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
                                //Log::channel('mi_log')->info('OJO!!! NO se encontró al jugador: ' . $jugadorMostrar.' del equipo '.$strEquipo,[]);
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
                        //Log::channel('mi_log')->info('OJO!!! NO se encontró al equipo: ' . $strEquipo,[]);
                    }
                }
            }
            else{
                //Log::channel('mi_log')->info('OJO!!! No se econtró la URL2 ' , []);
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

private function normalizarMinuto(string $texto): int
{
    $texto = trim($texto);

    preg_match('/(\d+)/', $texto, $matches);

    if (!isset($matches[1])) {
        return 0;
    }

    $minuto = (int)$matches[1];

    // Segundo tiempo
    if (stripos($texto, 'st') !== false || stripos($texto, '2T') !== false) {
        $minuto += 45;
    }

    // Tiempo extra (ej. 45+2)
    if (preg_match('/(\d+)\+(\d+)/', $texto, $extra)) {
        $minuto = (int)$extra[1] + (int)$extra[2];
    }

    return $minuto;
}

    /**
     * Divide un slug de partido en dos equipos y devuelve los modelos Equipo correspondientes.
     *
     * @param string $urlPartido Slug del partido, ej: "nacional-potosi-independiente"
     * @param \Illuminate\Database\Eloquent\Collection $plantillas Plantillas del grupo (contienen equipos)
     * @return array|null [Equipo $local, Equipo $visitante] o null si no encuentra
     */
    function splitEquiposFromUrlToModels($urlPartido, $slugsDB) {
        $parts = explode('-', $urlPartido);
        $totalParts = count($parts);



        // Probar todas las divisiones posibles
        for ($i = 1; $i < $totalParts; $i++) {
            $localSlug = implode('-', array_slice($parts, 0, $i));
            $visitanteSlug = implode('-', array_slice($parts, $i));

            if (isset($slugsDB[$localSlug]) && isset($slugsDB[$visitanteSlug])) {
                return [$slugsDB[$localSlug], $slugsDB[$visitanteSlug]];
            }
        }

        // Probar inversa
        for ($i = 1; $i < $totalParts; $i++) {
            $visitanteSlug = implode('-', array_slice($parts, 0, $i));
            $localSlug = implode('-', array_slice($parts, $i));

            if (isset($slugsDB[$localSlug]) && isset($slugsDB[$visitanteSlug])) {
                return [$slugsDB[$localSlug], $slugsDB[$visitanteSlug]];
            }
        }

        return null; // no encontró combinación
    }





    public function importpenalesfecha(Request $request)
{
    set_time_limit(0);

    $grupo_id = $request->get('grupoId');
    $grupo = Grupo::findOrFail($grupo_id);

    $plantillas = Plantilla::where('grupo_id','=',$grupo_id)->get();
    // Crear array de slugs de los equipos existentes, sólo si equipo existe
    $slugsDB = [];
    foreach ($plantillas as $plantilla) {
        if ($plantilla->equipo) {
            $slug = strtolower($plantilla->equipo->url_nombre);
            $slugsDB[$slug] = $plantilla->equipo;
        }
    }
    $ok=1;
    $error='';
    $success='';
    $jugadorPenalArray = array();
    foreach ($plantillas as $plantilla) {
        foreach ($plantilla->jugadores as $plantilla_jugador) {
            $jugador = $plantilla_jugador->jugador;
            $arrayNombre = explode(' ', $jugador->persona->nombre);
            $arrayApellido = explode(' ', $jugador->persona->apellido);
            $nombre = $arrayNombre[0];
            $apellido = $arrayApellido[0];
            $nombre2 = $arrayNombre[1] ?? '';
            $apellido2 = $arrayApellido[1] ?? '';

            $html2 = '';
            $slugJugador = ''; // Aquí guardamos el slug final

            $intentos = [
                strtolower($this->sanear_string($apellido)) . '-' . strtolower($this->sanear_string($nombre)),
                strtolower($this->sanear_string($apellido)) . '-' . strtolower($this->sanear_string($nombre)) . '-' . strtolower($this->sanear_string($nombre2)),
                strtolower($this->sanear_string($apellido)) . '-' . strtolower($this->sanear_string($nombre2)),
                strtolower($this->sanear_string($apellido)) . '-' . strtolower($this->sanear_string($apellido2)) . '-' . strtolower($this->sanear_string($nombre)),
                $jugador->url_nombre
            ];

            foreach ($intentos as $slug) {
                if (!$slug) continue;

                $urlJugador = 'http://www.futbol360.com.ar/jugadores/' . strtolower($this->sanear_string(str_replace(' ', '-', $jugador->persona->nacionalidad))) . '/' . $slug;

                try {
                    $html2 = HttpHelper::getHtmlContent($urlJugador);
                    if ($html2) {
                        $slugJugador = $slug; // Guardamos el slug válido
                        break; // Salimos del bucle, ya tenemos el jugador correcto
                    }
                } catch (Exception $ex) {
                    $html2 = '';
                }
            }
            if ($html2) {
                // Crear un nuevo DOMDocument y cargar el HTML
                $dom = new \DOMDocument();
                libxml_use_internal_errors(true); // Suprimir errores de análisis HTML
                $dom->loadHTML($html2);
                libxml_clear_errors();

                // Crear un nuevo objeto XPath
                $xpath = new \DOMXPath($dom);

                // Intentar encontrar el id_jugador dentro de los <script>
                $id_jugador = '';
                $scriptNodes = $xpath->query('//script');

                foreach ($scriptNodes as $script) {
                    if (strpos($script->textContent, 'id_player:') !== false) {
                        $script_array = explode('id_player', $script->textContent);
                        $id_jugador = trim(str_replace(':', '', explode('}', $script_array[1])[0]));
                        //Log::channel('mi_log')->info('OJO!! Id jugador: ' . $id_jugador, []);
                        //$success .='Id jugador: ' . $id_jugador.'<br>';
                        break;
                    }
                }

                // Si no se encontró en los scripts, buscar en la clase playerStats
                if (!$id_jugador) {
                    // Buscar el div con clase 'playerStats'
                    $playerStatsNodes = $xpath->query('//div[contains(@class, "playerStats")]');

                    foreach ($playerStatsNodes as $div) {
                        // Buscar las tablas dentro del div
                        $tables = $xpath->query('.//table[contains(@class, "tableStandard")]', $div);

                        foreach ($tables as $table) {
                            // Buscar las filas dentro de la tabla
                            $rows = $xpath->query('.//tr', $table);

                            foreach ($rows as $row) {
                                // Buscar las celdas dentro de las filas
                                $cells = $xpath->query('.//td', $row);

                                foreach ($cells as $cell) {
                                    // Buscar los enlaces dentro de las celdas
                                    $links = $xpath->query('.//a[contains(@href, "item=player&id=")]', $cell);

                                    foreach ($links as $link) {
                                        $href = $link->getAttribute('href');
                                        if (strpos($href, 'item=player&id=') !== false) {
                                            $arrIdJugador = explode('item=player&id=', $href);
                                            $id_jugador = intval($arrIdJugador[1]);
                                            //Log::channel('mi_log')->info('OJO!! ALT Id jugador: ' . $id_jugador, []);
                                            //$success .='ALT Id jugador: ' . $id_jugador.'<br>';
                                            break 4; // Salir de los bucles anidados
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                if ($id_jugador) {


                    try {

                        $urlErrado = "http://www.futbol360.com.ar/detalles/matches-penalties.php?item=player&id={$id_jugador}&id_team=0&id_season=0&search_category=penal_failed";

                        $htmlErrado = HttpHelper::getHtmlContent($urlErrado);


                    } catch (Exception $ex) {
                        $htmlErrado = '';
                    }
                    if ($htmlErrado) {

                        // Crear un nuevo DOMDocument y cargar el HTML
                        $dom = new \DOMDocument();
                        libxml_use_internal_errors(true); // Suprimir errores de análisis HTML
                        $dom->loadHTML($htmlErrado);
                        libxml_clear_errors();

                        // Crear un nuevo objeto XPath
                        $xpath = new \DOMXPath($dom);

                        // Buscar el div con id 'matchesTable'
                        $matchesTableNodes = $xpath->query('//div[@id="matchesTable"]');

                        foreach ($matchesTableNodes as $div) {
                            // Buscar las tablas con clase 'tableStandard'
                            $tables = $xpath->query('.//table[contains(@class, "tableStandard")]', $div);

                            foreach ($tables as $table) {
                                // Buscar las filas de la tabla
                                $rows = $xpath->query('.//tr', $table);

                                foreach ($rows as $row) {
                                    // Verificar que el contenido de la fila no sea "No hay resultados"
                                    if (trim($row->textContent) != 'No hay resultados') {
                                        // Buscar los encabezados de la fila (th)
                                        //Log::channel('mi_log')->info('encontro errado', []);
                                        $cols = $xpath->query('./th', $row);
                                        //Log::channel('mi_log')->info('Errado col - '.$cols->length.' - '.$slugJugador, []);
                                        if ($cols->length >= 5) {
                                            //$equipoLocal = trim($cols->item(0)->textContent);
                                            $partidoLink = $xpath->query('.//a', $cols->item(1))->item(0)->getAttribute('href');
                                            /*$equipoVisitante = trim($cols->item(2)->textContent);
                                            $fechaPartido = trim($cols->item(3)->textContent);*/
                                            $torneoLink = $xpath->query('.//a', $cols->item(4))->item(0)->getAttribute('href');
                                            $torneoTexto = trim($cols->item(4)->textContent);

                                            // 🔎 filtro: comparar contra url_nombre del torneo en tu DB
                                            $torneoGrupoUrl = strtolower($grupo->torneo->url_nombre);
                                            if (strpos(strtolower($torneoLink), $torneoGrupoUrl) === false) {
                                                continue; // no pertenece al torneo actual, lo salteamos
                                            }
                                            $parts = explode('/', $partidoLink);
                                            //Log::channel('mi_log')->info('Link '.$partidoLink, []);
                                            $urlFecha    = $parts[4] ?? null; // ej: 32-de-final
                                            $urlPartido  = $parts[5] ?? null; // ej: river-cd-de-bolivar
                                            //Log::channel('mi_log')->info('Fecha '.$urlFecha, []);
                                            //Log::channel('mi_log')->info('Partido '.$urlPartido, []);
                                            if ($urlFecha && $urlPartido) {

                                                $fecha = Fecha::where('url_nombre', $urlFecha)->where('grupo_id', $grupo_id)->first();

                                                if (!empty($fecha)) {
                                                    // Equipos (separados por "-")
                                                    $result = $this->splitEquiposFromUrlToModels($urlPartido, $slugsDB) ;

                                                    if ($result) {
                                                        [$equipoLocal, $equipoVisitante] = $result;

                                                    } else {
                                                        $success .= "<span style='color:red'>No se pudo encontrar el partido con slug: {$urlPartido}</span><br>";
                                                    }
                                                    if ($equipoLocal && $equipoVisitante) {
                                                        // Buscar partido exacto en la fecha
                                                        $partido = Partido::where('fecha_id', $fecha->id)
                                                            ->where('equipol_id', $equipoLocal->id)
                                                            ->where('equipov_id', $equipoVisitante->id)
                                                            ->first();
                                                        if (!$partido) {
                                                            // Probar invertido
                                                            $partidoInvertido = Partido::where('fecha_id', $fecha->id)
                                                                ->where('equipol_id', $equipoVisitante->id)
                                                                ->where('equipov_id', $equipoLocal->id)
                                                                ->first();

                                                            if ($partidoInvertido && $partidoInvertido->neutral == 1) {
                                                                $partido = $partidoInvertido;
                                                            }
                                                        }
                                                        if ($partido) {
                                                            // Si pasa el filtro, procesamos
                                                            $partidoUrl = "http://www.futbol360.com.ar" . $partidoLink;
                                                            //$success .= "✔ Partido encontrado en DB: {$equipoLocal->nombre} vs {$equipoVisitante->nombre}, ID={$partido->id}<br>";
                                                            $htmlPartido = HttpHelper::getHtmlContent($partidoUrl);

                                                            if ($htmlPartido) {
                                                                //Log::channel('mi_log')->info('Partido: ' . $partidoUrl, []);

                                                                // Crear un nuevo DOMDocument y cargar el HTML
                                                                $dom = new \DOMDocument();
                                                                libxml_use_internal_errors(true);
                                                                $dom->loadHTML($htmlPartido);
                                                                libxml_clear_errors();

                                                                // Crear objeto XPath
                                                                $xpath = new \DOMXPath($dom);

                                                                // Buscar todas las filas <tr>
                                                                $rows = $xpath->query('//tr');

                                                                foreach ($rows as $r) {
                                                                    // Buscar todos los <a> que son jugadores
                                                                    $playerLinks = [];
                                                                    foreach ($xpath->query('.//a', $r) as $a) {
                                                                        $href = $a->getAttribute('href');
                                                                        if (strpos($href, '/jugadores/') !== false) {
                                                                            $playerLinks[] = $a;
                                                                        }
                                                                    }

                                                                    $recordTds = $xpath->query('.//td[contains(@class, "record")]', $r);

                                                                    foreach ($playerLinks as $i => $linkNode) {
                                                                        $jugadorSlugWeb = trim(explode('/', $linkNode->getAttribute('href'))[3] ?? '');
                                                                        //Log::channel('mi_log')->info('Link: ' . $jugadorSlugWeb, []);
                                                                        //Log::channel('mi_log')->info('Jugador DB: ' . $slugJugador, []);
                                                                        if ($jugadorSlugWeb === $slugJugador) {
                                                                            $recordTd = $recordTds->item($i);
                                                                            if ($recordTd && strpos($recordTd->textContent, 'PenaltyFailed') !== false) {
                                                                                $texto = $recordTd->textContent;
                                                                                $minuto = $this->normalizarMinuto($texto);
                                                                                $jugadorPenalArray[$jugador->id][] = [
                                                                                    'partido_id' => $partido->id,
                                                                                    'jugador_id' => $jugador->id,
                                                                                    'minuto' => $minuto,
                                                                                    'tipo' => 'Errado',
                                                                                    'url' => $partidoUrl
                                                                                ];

                                                                                /*Log::channel('mi_log')->info(
                                                                                    'Erró penal en '.$torneoTexto.' ('.$equipoLocal.' vs '.$equipoVisitante.') el '.$fecha->numero.' → '.$partidoUrl,
                                                                                    []
                                                                                );*/

                                                                                $success .= "<span style='color:green'>Erró penal en $torneoTexto ($equipoLocal vs $equipoVisitante) el $fecha->numero → $partidoUrl</span><br>";
                                                                            }
                                                                        }
                                                                    }
                                                                }
                                                            }

                                                        } else {
                                                            $success .= "<span style='color:red'>No se encontró  partido en DB para {$fecha->id} - { $equipoLocal->id} - {$equipoVisitante->id}</span><br>";
                                                        }
                                                    }
                                                }
                                                else{
                                                    $success .= '<span style="color:red">Falta fecha: ' . $urlFecha . '</span><br>';
                                                }

                                            }

                                        }
                                    }


                                }

                            }
                        }
                    } else {

                        $success .= '<span style="color:red">No se econtró la URL de errados ' . $urlErrado . '</span><br>';
                    }
                    try {

                        $urlAtajado = "http://www.futbol360.com.ar/detalles/matches-penalties.php?item=player&id={$id_jugador}&id_team=0&id_season=0&search_category=penal_stopped";

                        $htmlAtajado = HttpHelper::getHtmlContent($urlAtajado);


                    } catch (Exception $ex) {
                        $htmlAtajado = '';
                    }
                    if ($htmlAtajado) {

                        // Crear un nuevo DOMDocument y cargar el HTML
                        $dom = new \DOMDocument();
                        libxml_use_internal_errors(true); // Suprimir errores de análisis HTML
                        $dom->loadHTML($htmlAtajado);
                        libxml_clear_errors();

                        // Crear un nuevo objeto XPath
                        $xpath = new \DOMXPath($dom);

                        // Buscar el div con id 'matchesTable'
                        $matchesTableNodes = $xpath->query('//div[@id="matchesTable"]');

                        foreach ($matchesTableNodes as $div) {
                            // Buscar las tablas con clase 'tableStandard'
                            $tables = $xpath->query('.//table[contains(@class, "tableStandard")]', $div);

                            foreach ($tables as $table) {
                                // Buscar las filas de la tabla
                                $rows = $xpath->query('.//tr', $table);

                                foreach ($rows as $row) {
                                    // Verificar que el contenido de la fila no sea "No hay resultados"
                                    if (trim($row->textContent) != 'No hay resultados') {
                                        // Buscar los encabezados de la fila (th)
                                        //Log::channel('mi_log')->info('encontro Atajado', []);
                                        $cols = $xpath->query('./th', $row);
                                        //Log::channel('mi_log')->info('Atajado col - '.$cols->length.' - '.$slugJugador, []);
                                        if ($cols->length >= 5) {
                                            //$equipoLocal = trim($cols->item(0)->textContent);
                                            $partidoLink = $xpath->query('.//a', $cols->item(1))->item(0)->getAttribute('href');
                                            /*$equipoVisitante = trim($cols->item(2)->textContent);
                                            $fechaPartido = trim($cols->item(3)->textContent);*/
                                            $torneoLink = $xpath->query('.//a', $cols->item(4))->item(0)->getAttribute('href');
                                            $torneoTexto = trim($cols->item(4)->textContent);

                                            // 🔎 filtro: comparar contra url_nombre del torneo en tu DB
                                            $torneoGrupoUrl = strtolower($grupo->torneo->url_nombre);
                                            if (strpos(strtolower($torneoLink), $torneoGrupoUrl) === false) {
                                                continue; // no pertenece al torneo actual, lo salteamos
                                            }
                                            $parts = explode('/', $partidoLink);
                                            //Log::channel('mi_log')->info('Link '.$partidoLink, []);
                                            $urlFecha    = $parts[4] ?? null; // ej: 32-de-final
                                            $urlPartido  = $parts[5] ?? null; // ej: river-cd-de-bolivar
                                            //Log::channel('mi_log')->info('Fecha '.$urlFecha, []);
                                            //Log::channel('mi_log')->info('Partido '.$urlPartido, []);

                                            if ($urlFecha && $urlPartido) {

                                                $fecha = Fecha::where('url_nombre', $urlFecha)->where('grupo_id', $grupo_id)->first();

                                                if (!empty($fecha)) {
                                                    // Equipos (separados por "-")
                                                    $result = $this->splitEquiposFromUrlToModels($urlPartido, $slugsDB) ;

                                                    if ($result) {
                                                        [$equipoLocal, $equipoVisitante] = $result;

                                                    } else {
                                                        $success .= "<span style='color:red'>No se pudo encontrar el partido con slug: {$urlPartido}</span><br>";
                                                    }

                                                    if ($equipoLocal && $equipoVisitante) {
                                                        // Buscar partido exacto en la fecha
                                                        $partido = Partido::where('fecha_id', $fecha->id)
                                                            ->where('equipol_id', $equipoLocal->id)
                                                            ->where('equipov_id', $equipoVisitante->id)
                                                            ->first();
                                                        if (!$partido) {
                                                            // Probar invertido
                                                            $partidoInvertido = Partido::where('fecha_id', $fecha->id)
                                                                ->where('equipol_id', $equipoVisitante->id)
                                                                ->where('equipov_id', $equipoLocal->id)
                                                                ->first();

                                                            if ($partidoInvertido && $partidoInvertido->neutral == 1) {
                                                                $partido = $partidoInvertido;
                                                            }
                                                        }
                                                        if ($partido) {
                                                            // Si pasa el filtro, procesamos
                                                            $partidoUrl = "http://www.futbol360.com.ar" . $partidoLink;
                                                            //$success .= "✔ Partido encontrado en DB: {$equipoLocal->nombre} vs {$equipoVisitante->nombre}, ID={$partido->id}<br>";
                                                            $htmlPartido = HttpHelper::getHtmlContent($partidoUrl);

                                                            if ($htmlPartido) {
                                                                //Log::channel('mi_log')->info('Partido: ' . $partidoUrl, []);

                                                                // Crear un nuevo DOMDocument y cargar el HTML
                                                                $dom = new \DOMDocument();
                                                                libxml_use_internal_errors(true);
                                                                $dom->loadHTML($htmlPartido);
                                                                libxml_clear_errors();

                                                                // Crear objeto XPath
                                                                $xpath = new \DOMXPath($dom);

                                                                // Buscar todas las filas <tr>
                                                                $rows = $xpath->query('//tr');

                                                                foreach ($rows as $r) {
                                                                    // Buscar todos los <a> que son jugadores
                                                                    $playerLinks = [];
                                                                    foreach ($xpath->query('.//a', $r) as $a) {
                                                                        $href = $a->getAttribute('href');
                                                                        if (strpos($href, '/jugadores/') !== false) {
                                                                            $playerLinks[] = $a;
                                                                        }
                                                                    }

                                                                    $recordTds = $xpath->query('.//td[contains(@class, "record")]', $r);

                                                                    foreach ($playerLinks as $i => $linkNode) {
                                                                        $jugadorSlugWeb = trim(explode('/', $linkNode->getAttribute('href'))[3] ?? '');
                                                                        //Log::channel('mi_log')->info('Link: ' . $jugadorSlugWeb, []);
                                                                        //Log::channel('mi_log')->info('Jugador DB: ' . $slugJugador, []);
                                                                        if ($jugadorSlugWeb === $slugJugador) {
                                                                            $recordTd = $recordTds->item($i);
                                                                            if ($recordTd && strpos($recordTd->textContent, 'PenaltyFailed') !== false) {
                                                                                $texto = $recordTd->textContent;
                                                                                $minuto = $this->normalizarMinuto($texto);
                                                                                $jugadorPenalArray[$jugador->id][] = [
                                                                                    'partido_id' => $partido->id,
                                                                                    'jugador_id' => $jugador->id,
                                                                                    'minuto' => $minuto,
                                                                                    'tipo' => 'Atajado',
                                                                                    'url' => $partidoUrl
                                                                                ];

                                                                                /*Log::channel('mi_log')->info(
                                                                                    'Le atajaron penal en '.$torneoTexto.' ('.$equipoLocal.' vs '.$equipoVisitante.') el '.$fecha->numero.' → '.$partidoUrl,
                                                                                    []
                                                                                );*/

                                                                                $success .= "<span style='color:green'>Le atajaron penal en $torneoTexto ($equipoLocal vs $equipoVisitante) el $fecha->numero → $partidoUrl</span><br>";
                                                                            }
                                                                        }
                                                                    }
                                                                }
                                                            }

                                                            // acá ya podés usar $partido->id para grabar el penal errado
                                                        } else {
                                                            $success .= "<span style='color:red'>No se encontró  partido en DB para {$fecha->id} - { $equipoLocal->id} - {$equipoVisitante->id}</span><br>";
                                                        }
                                                    }
                                                }
                                                else{
                                                    $success .= '<span style="color:red">Falta fecha: ' . $urlFecha . '</span><br>';
                                                }

                                            }

                                        }
                                    }


                                }

                            }
                        }
                    } else {

                        $success .= '<span style="color:red">No se econtró la URL de Atajados ' . $urlAtajado . '</span><br>';
                    }
                    try {

                        $urlAtajo = "http://www.futbol360.com.ar/detalles/matches-penalties.php?item=player&id={$id_jugador}&search_category=penal_stopped&received=1";

                        $htmlAtajo = HttpHelper::getHtmlContent($urlAtajo);


                    } catch (Exception $ex) {
                        $htmlAtajo = '';
                    }
                    if ($htmlAtajo) {

                        // Crear un nuevo DOMDocument y cargar el HTML
                        $dom = new \DOMDocument();
                        libxml_use_internal_errors(true); // Suprimir errores de análisis HTML
                        $dom->loadHTML($htmlAtajo);
                        libxml_clear_errors();

                        // Crear un nuevo objeto XPath
                        $xpath = new \DOMXPath($dom);

                        // Buscar el div con id 'matchesTable'
                        $matchesTableNodes = $xpath->query('//div[@id="matchesTable"]');

                        foreach ($matchesTableNodes as $div) {
                            // Buscar las tablas con clase 'tableStandard'
                            $tables = $xpath->query('.//table[contains(@class, "tableStandard")]', $div);

                            foreach ($tables as $table) {
                                // Buscar las filas de la tabla
                                $rows = $xpath->query('.//tr', $table);

                                foreach ($rows as $row) {
                                    // Verificar que el contenido de la fila no sea "No hay resultados"
                                    if (trim($row->textContent) != 'No hay resultados') {
                                        // Buscar los encabezados de la fila (th)
                                        //Log::channel('mi_log')->info('encontro Atajado', []);
                                        $cols = $xpath->query('./th', $row);
                                        //Log::channel('mi_log')->info('Atajó col - '.$cols->length.' - '.$slugJugador, []);
                                        if ($cols->length >= 5) {
                                            //$equipoLocal = trim($cols->item(0)->textContent);
                                            $partidoLink = $xpath->query('.//a', $cols->item(1))->item(0)->getAttribute('href');
                                            /*$equipoVisitante = trim($cols->item(2)->textContent);
                                            $fechaPartido = trim($cols->item(3)->textContent);*/
                                            $torneoLink = $xpath->query('.//a', $cols->item(4))->item(0)->getAttribute('href');
                                            $torneoTexto = trim($cols->item(4)->textContent);

                                            // 🔎 filtro: comparar contra url_nombre del torneo en tu DB
                                            $torneoGrupoUrl = strtolower($grupo->torneo->url_nombre);
                                            if (strpos(strtolower($torneoLink), $torneoGrupoUrl) === false) {
                                                continue; // no pertenece al torneo actual, lo salteamos
                                            }
                                            $parts = explode('/', $partidoLink);
                                            //Log::channel('mi_log')->info('Link '.$partidoLink, []);
                                            $urlFecha    = $parts[4] ?? null; // ej: 32-de-final
                                            $urlPartido  = $parts[5] ?? null; // ej: river-cd-de-bolivar
                                            //Log::channel('mi_log')->info('Fecha '.$urlFecha, []);
                                            //Log::channel('mi_log')->info('Partido '.$urlPartido, []);

                                            if ($urlFecha && $urlPartido) {

                                                $fecha = Fecha::where('url_nombre', $urlFecha)->where('grupo_id', $grupo_id)->first();

                                                if (!empty($fecha)) {
                                                    $result = $this->splitEquiposFromUrlToModels($urlPartido, $slugsDB) ;

                                                    if ($result) {
                                                        [$equipoLocal, $equipoVisitante] = $result;

                                                    } else {
                                                        $success .= "<span style='color:red'>No se pudo encontrar el partido con slug: {$urlPartido}</span><br>";
                                                    }

                                                    if ($equipoLocal && $equipoVisitante) {
                                                        // Buscar partido exacto en la fecha
                                                        $partido = Partido::where('fecha_id', $fecha->id)
                                                            ->where('equipol_id', $equipoLocal->id)
                                                            ->where('equipov_id', $equipoVisitante->id)
                                                            ->first();
                                                        if (!$partido) {
                                                            // Probar invertido
                                                            $partidoInvertido = Partido::where('fecha_id', $fecha->id)
                                                                ->where('equipol_id', $equipoVisitante->id)
                                                                ->where('equipov_id', $equipoLocal->id)
                                                                ->first();

                                                            if ($partidoInvertido && $partidoInvertido->neutral == 1) {
                                                                $partido = $partidoInvertido;
                                                            }
                                                        }
                                                        if ($partido) {
                                                            // Si pasa el filtro, procesamos
                                                            $partidoUrl = "http://www.futbol360.com.ar" . $partidoLink;
                                                            //$success .= "✔ Partido encontrado en DB: {$equipoLocal->nombre} vs {$equipoVisitante->nombre}, ID={$partido->id}<br>";
                                                            $htmlPartido = HttpHelper::getHtmlContent($partidoUrl);

                                                            if ($htmlPartido) {
                                                                //Log::channel('mi_log')->info('Partido: ' . $partidoUrl, []);

                                                                // Crear un nuevo DOMDocument y cargar el HTML
                                                                $dom = new \DOMDocument();
                                                                libxml_use_internal_errors(true);
                                                                $dom->loadHTML($htmlPartido);
                                                                libxml_clear_errors();

                                                                // Crear objeto XPath
                                                                $xpath = new \DOMXPath($dom);

                                                                // Buscar todas las filas <tr>
                                                                $rows = $xpath->query('//tr');

                                                                foreach ($rows as $r) {
                                                                    // Buscar todos los <a> que son jugadores
                                                                    $playerLinks = [];
                                                                    foreach ($xpath->query('.//a', $r) as $a) {
                                                                        $href = $a->getAttribute('href');
                                                                        if (strpos($href, '/jugadores/') !== false) {
                                                                            $playerLinks[] = $a;
                                                                        }
                                                                    }

                                                                    $recordTds = $xpath->query('.//td[contains(@class, "record")]', $r);

                                                                    foreach ($playerLinks as $i => $linkNode) {
                                                                        $jugadorSlugWeb = trim(explode('/', $linkNode->getAttribute('href'))[3] ?? '');
                                                                        //Log::channel('mi_log')->info('Link: ' . $jugadorSlugWeb, []);
                                                                        //Log::channel('mi_log')->info('Jugador DB: ' . $slugJugador, []);
                                                                        if ($jugadorSlugWeb === $slugJugador) {
                                                                            $recordTd = $recordTds->item($i);
                                                                            if ($recordTd && strpos($recordTd->textContent, 'PenaltyFailed') !== false) {
                                                                                $texto = $recordTd->textContent;
                                                                                $minuto = $this->normalizarMinuto($texto);
                                                                                $jugadorPenalArray[$jugador->id][] = [
                                                                                    'partido_id' => $partido->id,
                                                                                    'jugador_id' => $jugador->id,
                                                                                    'minuto' => $minuto,
                                                                                    'tipo' => 'Atajó',
                                                                                    'url' => $partidoUrl
                                                                                ];

                                                                                /*Log::channel('mi_log')->info(
                                                                                    'Atajó penal en '.$torneoTexto.' ('.$equipoLocal.' vs '.$equipoVisitante.') el '.$fecha->numero.' → '.$partidoUrl,
                                                                                    []
                                                                                );*/

                                                                                $success .= "<span style='color:green'>Atajó penal en $torneoTexto ($equipoLocal vs $equipoVisitante) el $fecha->numero → $partidoUrl</span><br>";
                                                                            }
                                                                        }
                                                                    }
                                                                }
                                                            }

                                                            // acá ya podés usar $partido->id para grabar el penal errado
                                                        } else {
                                                            $success .= "<span style='color:red'>No se encontró  partido en DB para {$fecha->id} - { $equipoLocal->id} - {$equipoVisitante->id}</span><br>";
                                                        }
                                                    }
                                                }
                                                else{
                                                    $success .= '<span style="color:red">Falta fecha: ' . $urlFecha . '</span><br>';
                                                }

                                            }

                                        }
                                    }


                                }

                            }
                        }
                    } else {

                        $success .= '<span style="color:red">No se econtró la URL de Atajó ' . $urlAtajo . '</span><br>';
                    }
                } else {
                    $success .= '<span style="color:red">No se econtró la URL del jugador ' . $jugador->persona->nombre . ' ' . $jugador->persona->apellido . '</span><br>';
                }
            }
        }
    }
    foreach ($jugadorPenalArray as $jugadorId => $penales) {
        foreach ($penales as $penal) {
            $existe = Penal::where('partido_id', $penal['partido_id'])
                ->where('jugador_id', $jugadorId)
                ->where('tipo', $penal['tipo'])
                ->where('minuto', $penal['minuto'])
                ->exists();

            if ($existe) {
                // Si ya está, no hacemos nada
                continue;
            }
            try {
                Penal::create([
                    'partido_id' => $penal['partido_id'],
                    'jugador_id' => $jugadorId,
                    'minuto' => $penal['minuto'],
                    'tipo' => $penal['tipo']
                ]);

                //$success .= '<span style="color:green">Penal errado agregado: ' . $jugador->persona->nombre . ' - ' . $minuto . '\'</span><br>';
            } catch (QueryException $ex) {
                $error = $ex->getMessage();
                $ok = 0;
                continue;
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
        $respuestaMSJ = $error . '<br>' . $success;
    }

    //
    return redirect()->route('fechas.index', array('grupoId' => $grupo->id))->with($respuestaID, $respuestaMSJ);
}


    public function importgolesfecha(Request $request)
    {
        set_time_limit(0);
        //Log::channel('mi_log')->info('Entraaaaaa', []);

        $grupo_id = $request->get('grupoId');

        $grupo = Grupo::findOrFail($grupo_id);


        $fechas = Fecha::where('grupo_id', '=', "$grupo_id")->orderBy('numero', 'ASC')->get();

        // these are the headers for the csv file.
        /*$headers = array(
            'Content-Type' => 'application/vnd.ms-excel; charset=utf-8',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Content-Disposition' => 'attachment; filename='.$grupo->torneo->nombre.'_'.str_replace('/','_',$grupo->torneo->year).'_goles.csv',
            'Expires' => '0',
            'Pragma' => 'public',
        );*/


        //I am storing the csv file in public >> files folder. So that why I am creating files folder
        /*if (!File::exists(public_path()."/files")) {
            File::makeDirectory(public_path() . "/files");
        }

        //creating the download file
        $filename = 'files/'.$grupo->torneo->nombre.'_'.str_replace('/','_',$grupo->torneo->year).'_goles.csv';
        $filepath = public_path($filename); // Ruta física en el servidor
        $handle = fopen($filepath, 'w');

// Añadir la primera fila al CSV
        fputcsv($handle, [
            'Torneo', 'Fecha', 'Partido', 'Jugador', 'Gol', 'Observaciones', 'URL'
        ], "|");




        // Crear un enlace para descargar el archivo
        $downloadLink = '<a href="'.asset($filename).'" download>Descargar CSV</a><br>';*/

// Agregar el link de descarga a $success
        //$success = '<span style="color:cyan">El archivo CSV ha sido generado. ' . $downloadLink.'</span>';
        $success = '';
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
            $sigo = 1;
            //Log::channel('mi_log')->info('Fecha ' . $fecha->numero, []);
            foreach ($partidos as $partido) {
                $strLocal = $partido->equipol->nombre;
                $strVisitante = $partido->equipov->nombre;
                if (!$this->dameIdEquipoURL($strLocal)) {
                    $success .= '<span style="color:red">Falta equipo: ' . $strLocal . '</span><br>';
                    $sigo = 0;
                }
                if (!$this->dameIdEquipoURL($strVisitante)) {
                    $success .= '<span style="color:red">Falta equipo: ' . $strVisitante . '</span><br>';
                    $sigo = 0;
                }
                if (!$this->dameNombreEquipoURL3($strLocal)) {
                    $success .= '<span style="color:red">No está: ' . $strLocal . '</span><br>';
                    $sigo = 0;
                }
                if (!$this->dameNombreEquipoURL3($strVisitante)) {
                    $success .= '<span style="color:red">No está: ' . $strVisitante . '</span><br>';
                    $sigo = 0;
                }
                if ($sigo) {
                    $golesTotales = $partido->golesl + $partido->golesv;
                    $golesLocales = $partido->golesl;
                    $golesVisitantes = $partido->golesv;
                    //Log::channel('mi_log')->info('Partido ' . $partido->equipol->nombre . ' VS ' . $partido->equipov->nombre, []);
                    $success .= '<span style="color:orange">Partido ' . $partido->equipol->nombre . ' VS ' . $partido->equipov->nombre . ' - ' . $fecha->numero . '</span><br>';
                    $goles = Gol::where('partido_id', '=', "$partido->id")->orderBy('minuto', 'ASC')->get();

                    $jugadorGolArray = array();

                    foreach ($goles as $gol) {
                        //Log::channel('mi_log')->info('Gol ' . $gol->jugador->persona->nombre.' - '.$gol->jugador->persona->apellido.' - '.$gol->tipo.' - '.$gol->minuto, []);
                        //$success .='Gol ' . $gol->jugador->persona->nombre.' - '.$gol->jugador->persona->apellido.' - '.$gol->tipo.' - '.$gol->minuto.'<br>';
                        $alineacion = Alineacion::where('partido_id', '=', "$partido->id")->where('jugador_id', '=', $gol->jugador->id)->first();
                        if (!empty($alineacion)) {
                            //Log::channel('mi_log')->info('OJO!!! - juega en: '.$alineacion->equipo->nombre, []);
                            $juegaEn = $alineacion->equipo->nombre;
                        }

                        if ($gol->tipo == 'Jugada') {

                            $arrayNombre = explode(' ', $gol->jugador->persona->nombre);
                            $arrayApellido = explode(' ', $gol->jugador->persona->apellido);
                            $nombre = $arrayNombre[0];
                            $apellido = $arrayApellido[0];
                            $nombre2 = '';
                            if (count($arrayNombre) > 1) {
                                $nombre2 = $arrayNombre[1];
                            }
                            $apellido2 = '';
                            if (count($arrayApellido) > 1) {
                                $apellido2 = $arrayApellido[1];
                            }
                            try {
                                $urlJugador = 'http://www.futbol360.com.ar/jugadores/' . strtolower($this->sanear_string(str_replace(' ', '-', $gol->jugador->persona->nacionalidad))) . '/' . strtolower($this->sanear_string($apellido)) . '-' . strtolower($this->sanear_string($nombre));
                                //Log::channel('mi_log')->info('OJO!!! - '.$urlJugador, []);

                                //$html2 = HtmlDomParser::file_get_html($urlJugador, false, null, 0);
                                $html2 = HttpHelper::getHtmlContent($urlJugador);

                            } catch (Exception $ex) {

                                $html2 = '';
                            }
                            if (!$html2) {
                                try {
                                    if ($nombre2) {
                                        $urlJugador = 'http://www.futbol360.com.ar/jugadores/' . strtolower($this->sanear_string(str_replace(' ', '-', $gol->jugador->persona->nacionalidad))) . '/' . strtolower($this->sanear_string($apellido)) . '-' . strtolower($this->sanear_string($nombre)) . '-' . strtolower($this->sanear_string($nombre2));
                                        //Log::channel('mi_log')->info('OJO!!! - '.$urlJugador, []);

                                        //$html2 = HtmlDomParser::file_get_html($urlJugador, false, null, 0);
                                        $html2 = HttpHelper::getHtmlContent($urlJugador);
                                    }

                                } catch (Exception $ex) {

                                    $html2 = '';
                                }
                            }
                            if (!$html2) {
                                try {
                                    if ($nombre2) {
                                        $urlJugador = 'http://www.futbol360.com.ar/jugadores/' . strtolower($this->sanear_string(str_replace(' ', '-', $gol->jugador->persona->nacionalidad))) . '/' . strtolower($this->sanear_string($apellido)) . '-' . strtolower($this->sanear_string($nombre2));
                                        //Log::channel('mi_log')->info('OJO!!! - '.$urlJugador, []);

                                        //$html2 = HtmlDomParser::file_get_html($urlJugador, false, null, 0);
                                        $html2 = HttpHelper::getHtmlContent($urlJugador);
                                    }

                                } catch (Exception $ex) {

                                    $html2 = '';
                                }
                            }
                            if (!$html2) {
                                try {
                                    if ($apellido2) {
                                        $urlJugador = 'http://www.futbol360.com.ar/jugadores/' . strtolower($this->sanear_string(str_replace(' ', '-', $gol->jugador->persona->nacionalidad))) . '/' . strtolower($this->sanear_string($apellido)) . '-' . strtolower($this->sanear_string($apellido2)) . '-' . strtolower($this->sanear_string($nombre));
                                        //Log::channel('mi_log')->info('OJO!!! - '.$urlJugador, []);

                                        //$html2 = HtmlDomParser::file_get_html($urlJugador, false, null, 0);
                                        $html2 = HttpHelper::getHtmlContent($urlJugador);

                                    }


                                } catch (Exception $ex) {

                                    $html2 = '';
                                }
                            }
                            if (!$html2) {
                                try {

                                    $nombre3 = $gol->jugador->url_nombre;
                                    $urlJugador = 'http://www.futbol360.com.ar/jugadores/' . strtolower($this->sanear_string(str_replace(' ', '-', $gol->jugador->persona->nacionalidad))) . '/' . $nombre3;
                                    //Log::channel('mi_log')->info('OJO!!! - '.$urlJugador, []);

                                    //$html2 = HtmlDomParser::file_get_html($urlJugador, false, null, 0);
                                    $html2 = HttpHelper::getHtmlContent($urlJugador);
                                    if (!$html2) {
                                        $urlJugador = 'http://www.futbol360.com.ar/jugadores/' . $nombre3;
                                        //Log::channel('mi_log')->info('OJO!!! - '.$urlJugador, []);

                                        //$html2 = HtmlDomParser::file_get_html($urlJugador, false, null, 0);
                                        $html2 = HttpHelper::getHtmlContent($urlJugador);
                                    }

                                } catch (Exception $ex) {

                                    $html2 = '';
                                }
                            }
                            if ($html2) {
                                // Crear un nuevo DOMDocument y cargar el HTML
                                $dom = new \DOMDocument();
                                libxml_use_internal_errors(true); // Suprimir errores de análisis HTML
                                $dom->loadHTML($html2);
                                libxml_clear_errors();

                                // Crear un nuevo objeto XPath
                                $xpath = new \DOMXPath($dom);

                                // Intentar encontrar el id_jugador dentro de los <script>
                                $id_jugador = '';
                                $scriptNodes = $xpath->query('//script');

                                foreach ($scriptNodes as $script) {
                                    if (strpos($script->textContent, 'id_player:') !== false) {
                                        $script_array = explode('id_player', $script->textContent);
                                        $id_jugador = trim(str_replace(':', '', explode('}', $script_array[1])[0]));
                                        //Log::channel('mi_log')->info('OJO!! Id jugador: ' . $id_jugador, []);
                                        //$success .='Id jugador: ' . $id_jugador.'<br>';
                                        break;
                                    }
                                }

                                // Si no se encontró en los scripts, buscar en la clase playerStats
                                if (!$id_jugador) {
                                    // Buscar el div con clase 'playerStats'
                                    $playerStatsNodes = $xpath->query('//div[contains(@class, "playerStats")]');

                                    foreach ($playerStatsNodes as $div) {
                                        // Buscar las tablas dentro del div
                                        $tables = $xpath->query('.//table[contains(@class, "tableStandard")]', $div);

                                        foreach ($tables as $table) {
                                            // Buscar las filas dentro de la tabla
                                            $rows = $xpath->query('.//tr', $table);

                                            foreach ($rows as $row) {
                                                // Buscar las celdas dentro de las filas
                                                $cells = $xpath->query('.//td', $row);

                                                foreach ($cells as $cell) {
                                                    // Buscar los enlaces dentro de las celdas
                                                    $links = $xpath->query('.//a[contains(@href, "item=player&id=")]', $cell);

                                                    foreach ($links as $link) {
                                                        $href = $link->getAttribute('href');
                                                        if (strpos($href, 'item=player&id=') !== false) {
                                                            $arrIdJugador = explode('item=player&id=', $href);
                                                            $id_jugador = intval($arrIdJugador[1]);
                                                            //Log::channel('mi_log')->info('OJO!! ALT Id jugador: ' . $id_jugador, []);
                                                            //$success .='ALT Id jugador: ' . $id_jugador.'<br>';
                                                            break 4; // Salir de los bucles anidados
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                                if ($id_jugador) {
                                    $strTorneoFecha = $this->dameNombreTorneoURL(strtolower($grupo->torneo->url_nombre), $fecha->url_nombre);
                                    $strTorneoFechaIda = $this->dameNombreTorneoURL(strtolower($grupo->torneo->url_nombre), $fecha->url_nombre, 'ida');

                                    $strTorneoFechaVuelta = $this->dameNombreTorneoURL(strtolower($grupo->torneo->url_nombre), $fecha->url_nombre, 'vuelta');
                                    $strTorneoFechaA = $this->dameNombreTorneoURL(strtolower($grupo->torneo->url_nombre), $fecha->url_nombre, 'a');
                                    $strTorneoFechaB = $this->dameNombreTorneoURL(strtolower($grupo->torneo->url_nombre), $fecha->url_nombre, 'b');
                                    $strTorneoFechaC = $this->dameNombreTorneoURL(strtolower($grupo->torneo->url_nombre), $fecha->url_nombre, 'c');
                                    $strTorneoFechaD = $this->dameNombreTorneoURL(strtolower($grupo->torneo->url_nombre), $fecha->url_nombre, 'd');
                                    $strTorneoFechaE = $this->dameNombreTorneoURL(strtolower($grupo->torneo->url_nombre), $fecha->url_nombre, 'e');
                                    $strTorneoFechaF = $this->dameNombreTorneoURL(strtolower($grupo->torneo->url_nombre), $fecha->url_nombre, 'f');
                                    if ($juegaEn == $strLocal) {
                                        $juegaContra = $strVisitante;
                                    }
                                    if ($juegaEn == $strVisitante) {
                                        $juegaContra = $strLocal;
                                    }
                                    try {

                                        $urlCabeza = 'http://www.futbol360.com.ar/detalles/matches-goals.php?item=player&id=' . $id_jugador . '&id_team_for=' . $this->dameIdEquipoURL($juegaEn) . '&id_team_against=' . $this->dameIdEquipoURL($juegaContra) . '&id_season=0&search_category=head';
                                        //Log::channel('mi_log')->info('OJO!!! - '.$urlCabeza, []);

                                        //$htmlCabeza = HtmlDomParser::file_get_html($urlCabeza, false, null, 0);
                                        $htmlCabeza = HttpHelper::getHtmlContent($urlCabeza);
                                        //Log::channel('mi_log')->info('OJO!! URL cabeza: '.$htmlCabeza,[]);

                                        //$success .='Url cabeza: ' . $urlCabeza.'<br>';


                                    } catch (Exception $ex) {
                                        $htmlCabeza = '';
                                    }
                                    if ($htmlCabeza) {

                                        // Crear un nuevo DOMDocument y cargar el HTML
                                        $dom = new \DOMDocument();
                                        libxml_use_internal_errors(true); // Suprimir errores de análisis HTML
                                        $dom->loadHTML($htmlCabeza);
                                        libxml_clear_errors();

                                        // Crear un nuevo objeto XPath
                                        $xpath = new \DOMXPath($dom);

                                        // Buscar el div con id 'matchesTable'
                                        $matchesTableNodes = $xpath->query('//div[@id="matchesTable"]');

                                        foreach ($matchesTableNodes as $div) {
                                            // Buscar las tablas con clase 'tableStandard'
                                            $tables = $xpath->query('.//table[contains(@class, "tableStandard")]', $div);

                                            foreach ($tables as $table) {
                                                // Buscar las filas de la tabla
                                                $rows = $xpath->query('.//tr', $table);

                                                foreach ($rows as $row) {
                                                    // Verificar que el contenido de la fila no sea "No hay resultados"
                                                    if (trim($row->textContent) != 'No hay resultados') {
                                                        // Buscar los encabezados de la fila (th)
                                                        $headerCells = $xpath->query('.//th', $row);

                                                        foreach ($headerCells as $th) {
                                                            // Buscar los enlaces dentro del th
                                                            $links = $xpath->query('.//a', $th);

                                                            foreach ($links as $link) {
                                                                $urlEncontrada = 0;
                                                                $href = $link->getAttribute('href');


                                                                // Comparar la URL con las generadas por dameNombreEquipoURL3 y dameNombreTorneoURL
                                                                foreach ($this->dameNombreEquipoURL3($strLocal) as $local3) {
                                                                    foreach ($this->dameNombreEquipoURL3($strVisitante) as $visitante3) {
                                                                        // Comparar las posibles combinaciones de URLs
                                                                        if ((
                                                                                strpos($href, $strTorneoFecha . '/' . $local3 . '-' . $visitante3 . '/') !== false
                                                                            ) || (
                                                                                strpos($href, $strTorneoFecha . '/' . $visitante3 . '-' . $local3 . '/') !== false
                                                                            ) || (
                                                                                strpos($href, $strTorneoFechaIda . '/' . $local3 . '-' . $visitante3 . '/') !== false
                                                                            ) || (
                                                                                strpos($href, $strTorneoFechaIda . '/' . $visitante3 . '-' . $local3 . '/') !== false
                                                                            ) || (
                                                                                strpos($href, $strTorneoFechaVuelta . '/' . $local3 . '-' . $visitante3 . '/') !== false
                                                                            ) || (
                                                                                strpos($href, $strTorneoFechaVuelta . '/' . $visitante3 . '-' . $local3 . '/') !== false
                                                                            ) || (
                                                                                strpos($href, $strTorneoFechaA . '/' . $local3 . '-' . $visitante3 . '/') !== false
                                                                            ) || (
                                                                                strpos($href, $strTorneoFechaA . '/' . $visitante3 . '-' . $local3 . '/') !== false
                                                                            ) || (
                                                                                strpos($href, $strTorneoFechaB . '/' . $local3 . '-' . $visitante3 . '/') !== false
                                                                            ) || (
                                                                                strpos($href, $strTorneoFechaB . '/' . $visitante3 . '-' . $local3 . '/') !== false
                                                                            ) || (
                                                                                strpos($href, $strTorneoFechaC . '/' . $local3 . '-' . $visitante3 . '/') !== false
                                                                            ) || (
                                                                                strpos($href, $strTorneoFechaC . '/' . $visitante3 . '-' . $local3 . '/') !== false
                                                                            ) || (
                                                                                strpos($href, $strTorneoFechaD . '/' . $local3 . '-' . $visitante3 . '/') !== false
                                                                            ) || (
                                                                                strpos($href, $strTorneoFechaD . '/' . $visitante3 . '-' . $local3 . '/') !== false
                                                                            ) || (
                                                                                strpos($href, $strTorneoFechaE . '/' . $local3 . '-' . $visitante3 . '/') !== false
                                                                            ) || (
                                                                                strpos($href, $strTorneoFechaE . '/' . $visitante3 . '-' . $local3 . '/') !== false
                                                                            ) || (
                                                                                strpos($href, $strTorneoFechaF . '/' . $local3 . '-' . $visitante3 . '/') !== false
                                                                            ) || (
                                                                                strpos($href, $strTorneoFechaF . '/' . $visitante3 . '-' . $local3 . '/') !== false
                                                                            )
                                                                        ) {
                                                                            $urlEncontrada = 1;
                                                                            //Log::channel('mi_log')->info('OJO!! encontró gol cabeza: ' . $href, []);
                                                                            $success .= '<span style="color:green">Encontró gol cabeza: ' . $href . '</span><br>';

                                                                            // Crear el array de datos para el jugador y el gol
                                                                            $data3 = array(
                                                                                'partido_id' => $partido->id,
                                                                                'jugador_id' => $gol->jugador->id,
                                                                                'minuto' => $gol->minuto,
                                                                                'tipo' => 'Cabeza',
                                                                                'url' => $urlCabeza,
                                                                            );
                                                                            $jugadorGolArray[$gol->jugador->id][] = $data3;
                                                                        }
                                                                    }
                                                                }


                                                                // Si no se encontró la URL, registrar en el log
                                                                /*if (!$urlEncontrada) {
                                                                    //Log::channel('mi_log')->info('no está cabeza: ' . $href, []);
                                                                }*/
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    } else {
                                        /*fputcsv($handle, [

                                            utf8_decode($grupo->torneo->nombre.' '.$grupo->torneo->year),
                                            utf8_decode($fecha->numero),
                                            utf8_decode($partido->equipol->nombre . ' VS ' . $partido->equipov->nombre),
                                            utf8_decode($gol->jugador->persona->nombre.' - '.$gol->jugador->persona->apellido),
                                            utf8_decode($gol->tipo.' - '.$gol->minuto),
                                            utf8_decode('No se econtró la URL de cabezas'),
                                            $urlCabeza
                                        ], "|");*/
                                        //Log::channel('mi_log')->info('OJO!!! No se econtró la URL de cabezas' , []);
                                        $success .= '<span style="color:red">No se econtró la URL de cabezas ' . $urlCabeza . '</span><br>';
                                    }
                                    try {
                                        $urlLibres = 'http://www.futbol360.com.ar/detalles/matches-goals.php?item=player&id=' . $id_jugador . '&id_team_for=' . $this->dameIdEquipoURL($juegaEn) . '&id_team_against=' . $this->dameIdEquipoURL($juegaContra) . '&id_season=0&search_category=free_shot';
                                        //Log::channel('mi_log')->info('OJO!!! - '.$urlLibres, []);

                                        //$htmlLibre = HtmlDomParser::file_get_html($urlLibres, false, null, 0);
                                        $htmlLibre = HttpHelper::getHtmlContent($urlLibres);
                                        //Log::channel('mi_log')->info('OJO!! URL Libre: '.$htmlLibre,[]);

                                        //$success .='Url tiro libre: ' . $urlLibres.'<br>';


                                    } catch (Exception $ex) {
                                        $htmlLibre = '';
                                    }
                                    if ($htmlLibre) {
                                        // Crear un nuevo DOMDocument y cargar el HTML
                                        $dom = new \DOMDocument();
                                        libxml_use_internal_errors(true); // Suprimir errores de análisis HTML
                                        $dom->loadHTML($htmlLibre);
                                        libxml_clear_errors();

                                        // Crear un nuevo objeto XPath
                                        $xpath = new \DOMXPath($dom);

                                        // Buscar el div con id 'matchesTable'
                                        $matchesTableNodes = $xpath->query('//div[@id="matchesTable"]');

                                        foreach ($matchesTableNodes as $div) {
                                            // Buscar las tablas con clase 'tableStandard'
                                            $tables = $xpath->query('.//table[contains(@class, "tableStandard")]', $div);

                                            foreach ($tables as $table) {
                                                // Buscar las filas de la tabla
                                                $rows = $xpath->query('.//tr', $table);

                                                foreach ($rows as $row) {
                                                    // Verificar que el contenido de la fila no sea "No hay resultados"
                                                    if (trim($row->textContent) != 'No hay resultados') {
                                                        // Buscar los encabezados de la fila (th)
                                                        $headerCells = $xpath->query('.//th', $row);

                                                        foreach ($headerCells as $th) {
                                                            // Buscar los enlaces dentro del th
                                                            $links = $xpath->query('.//a', $th);

                                                            foreach ($links as $link) {
                                                                $urlEncontrada = 0;
                                                                $href = $link->getAttribute('href');

                                                                // Comparar la URL con las generadas por dameNombreEquipoURL3 y dameNombreTorneoURL
                                                                foreach ($this->dameNombreEquipoURL3($strLocal) as $local3) {
                                                                    foreach ($this->dameNombreEquipoURL3($strVisitante) as $visitante3) {
                                                                        // Comparar las posibles combinaciones de URLs
                                                                        if ((
                                                                                strpos($href, $strTorneoFecha . '/' . $local3 . '-' . $visitante3 . '/') !== false
                                                                            ) || (
                                                                                strpos($href, $strTorneoFecha . '/' . $visitante3 . '-' . $local3 . '/') !== false
                                                                            ) || (
                                                                                strpos($href, $strTorneoFechaIda . '/' . $local3 . '-' . $visitante3 . '/') !== false
                                                                            ) || (
                                                                                strpos($href, $strTorneoFechaIda . '/' . $visitante3 . '-' . $local3 . '/') !== false
                                                                            ) || (
                                                                                strpos($href, $strTorneoFechaVuelta . '/' . $local3 . '-' . $visitante3 . '/') !== false
                                                                            ) || (
                                                                                strpos($href, $strTorneoFechaVuelta . '/' . $visitante3 . '-' . $local3 . '/') !== false
                                                                            ) || (
                                                                                strpos($href, $strTorneoFechaA . '/' . $local3 . '-' . $visitante3 . '/') !== false
                                                                            ) || (
                                                                                strpos($href, $strTorneoFechaA . '/' . $visitante3 . '-' . $local3 . '/') !== false
                                                                            ) || (
                                                                                strpos($href, $strTorneoFechaB . '/' . $local3 . '-' . $visitante3 . '/') !== false
                                                                            ) || (
                                                                                strpos($href, $strTorneoFechaB . '/' . $visitante3 . '-' . $local3 . '/') !== false
                                                                            ) || (
                                                                                strpos($href, $strTorneoFechaC . '/' . $local3 . '-' . $visitante3 . '/') !== false
                                                                            ) || (
                                                                                strpos($href, $strTorneoFechaC . '/' . $visitante3 . '-' . $local3 . '/') !== false
                                                                            ) || (
                                                                                strpos($href, $strTorneoFechaD . '/' . $local3 . '-' . $visitante3 . '/') !== false
                                                                            ) || (
                                                                                strpos($href, $strTorneoFechaD . '/' . $visitante3 . '-' . $local3 . '/') !== false
                                                                            ) || (
                                                                                strpos($href, $strTorneoFechaE . '/' . $local3 . '-' . $visitante3 . '/') !== false
                                                                            ) || (
                                                                                strpos($href, $strTorneoFechaE . '/' . $visitante3 . '-' . $local3 . '/') !== false
                                                                            ) || (
                                                                                strpos($href, $strTorneoFechaF . '/' . $local3 . '-' . $visitante3 . '/') !== false
                                                                            ) || (
                                                                                strpos($href, $strTorneoFechaF . '/' . $visitante3 . '-' . $local3 . '/') !== false
                                                                            )
                                                                        ) {
                                                                            $urlEncontrada = 1;
                                                                            //Log::channel('mi_log')->info('OJO!! encontró gol tiro libre: ' . $href, []);
                                                                            $success .= '<span style="color:green">Encontró gol tiro libre: ' . $href . '</span><br>';

                                                                            // Crear el array de datos para el jugador y el gol
                                                                            $data3 = array(
                                                                                'partido_id' => $partido->id,
                                                                                'jugador_id' => $gol->jugador->id,
                                                                                'minuto' => $gol->minuto,
                                                                                'tipo' => 'Tiro Libre',
                                                                                'url' => $urlLibres,
                                                                            );
                                                                            $jugadorGolArray[$gol->jugador->id][] = $data3;
                                                                        }
                                                                    }
                                                                }

                                                                // Si no se encontró la URL, registrar en el log
                                                                if (!$urlEncontrada) {
                                                                    //Log::channel('mi_log')->info('no está libres: ' . $href, []);
                                                                }
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    } else {
                                        /*fputcsv($handle, [

                                            utf8_decode($grupo->torneo->nombre.' '.$grupo->torneo->year),
                                            utf8_decode($fecha->numero),
                                            utf8_decode($partido->equipol->nombre . ' VS ' . $partido->equipov->nombre),
                                            utf8_decode($gol->jugador->persona->nombre.' - '.$gol->jugador->persona->apellido),
                                            utf8_decode($gol->tipo.' - '.$gol->minuto),
                                            utf8_decode('No se econtró la URL de tiros libres'),
                                            $urlLibres
                                        ], "|");*/
                                        //Log::channel('mi_log')->info('OJO!!! No se econtró la URL de tiros libres' , []);
                                        $success .= '<span style="color:red">No se econtró la URL de tiros libres ' . $urlLibres . '</span><br>';
                                    }
                                    try {
                                        $urlPenales = 'http://www.futbol360.com.ar/detalles/matches-goals.php?item=player&id=' . $id_jugador . '&id_team_for=' . $this->dameIdEquipoURL($juegaEn) . '&id_team_against=' . $this->dameIdEquipoURL($juegaContra) . '&id_season=0&search_category=penal_converted';
                                        //Log::channel('mi_log')->info('OJO!!! - '.$urlPenales, []);

                                        //$htmlPenal = HtmlDomParser::file_get_html($urlPenales, false, null, 0);
                                        $htmlPenal = HttpHelper::getHtmlContent($urlPenales);
                                        //Log::channel('mi_log')->info('OJO!! URL Penal: '.$htmlPenal,[]);

                                        //$success .='Url penal: ' . $urlPenales.'<br>';


                                    } catch (Exception $ex) {
                                        $htmlPenal = '';
                                    }
                                    if ($htmlPenal) {
                                        // Crear un nuevo DOMDocument y cargar el HTML
                                        $dom = new \DOMDocument();
                                        libxml_use_internal_errors(true); // Suprimir errores de análisis HTML
                                        $dom->loadHTML($htmlPenal);
                                        libxml_clear_errors();

                                        // Crear un nuevo objeto XPath
                                        $xpath = new \DOMXPath($dom);

                                        // Buscar el div con id 'matchesTable'
                                        $matchesTableNodes = $xpath->query('//div[@id="matchesTable"]');

                                        foreach ($matchesTableNodes as $div) {
                                            // Buscar las tablas con clase 'tableStandard'
                                            $tables = $xpath->query('.//table[contains(@class, "tableStandard")]', $div);

                                            foreach ($tables as $table) {
                                                // Buscar las filas de la tabla
                                                $rows = $xpath->query('.//tr', $table);

                                                foreach ($rows as $row) {
                                                    // Verificar que el contenido de la fila no sea "No hay resultados"
                                                    if (trim($row->textContent) != 'No hay resultados') {
                                                        // Buscar los encabezados de la fila (th)
                                                        $headerCells = $xpath->query('.//th', $row);

                                                        foreach ($headerCells as $th) {
                                                            // Buscar los enlaces dentro del th
                                                            $links = $xpath->query('.//a', $th);

                                                            foreach ($links as $link) {
                                                                $urlEncontrada = 0;
                                                                $href = $link->getAttribute('href');
                                                                //Log::channel('mi_log')->info('OJO!! URL penal: ' . $href, []);
                                                                // Comparar la URL con las generadas por dameNombreEquipoURL3 y dameNombreTorneoURL
                                                                foreach ($this->dameNombreEquipoURL3($strLocal) as $local3) {
                                                                    foreach ($this->dameNombreEquipoURL3($strVisitante) as $visitante3) {
                                                                        // Comparar las posibles combinaciones de URLs
                                                                        //Log::channel('mi_log')->info('OJO!! URL penal con equipos: ' . $strTorneoFecha . '/' . $local3 . '-' . $visitante3 . '/', []);
                                                                        if ((
                                                                                strpos($href, $strTorneoFecha . '/' . $local3 . '-' . $visitante3 . '/') !== false
                                                                            ) || (
                                                                                strpos($href, $strTorneoFecha . '/' . $visitante3 . '-' . $local3 . '/') !== false
                                                                            ) || (
                                                                                strpos($href, $strTorneoFechaIda . '/' . $local3 . '-' . $visitante3 . '/') !== false
                                                                            ) || (
                                                                                strpos($href, $strTorneoFechaIda . '/' . $visitante3 . '-' . $local3 . '/') !== false
                                                                            ) || (
                                                                                strpos($href, $strTorneoFechaVuelta . '/' . $local3 . '-' . $visitante3 . '/') !== false
                                                                            ) || (
                                                                                strpos($href, $strTorneoFechaVuelta . '/' . $visitante3 . '-' . $local3 . '/') !== false
                                                                            ) || (
                                                                                strpos($href, $strTorneoFechaA . '/' . $local3 . '-' . $visitante3 . '/') !== false
                                                                            ) || (
                                                                                strpos($href, $strTorneoFechaA . '/' . $visitante3 . '-' . $local3 . '/') !== false
                                                                            ) || (
                                                                                strpos($href, $strTorneoFechaB . '/' . $local3 . '-' . $visitante3 . '/') !== false
                                                                            ) || (
                                                                                strpos($href, $strTorneoFechaB . '/' . $visitante3 . '-' . $local3 . '/') !== false
                                                                            ) || (
                                                                                strpos($href, $strTorneoFechaC . '/' . $local3 . '-' . $visitante3 . '/') !== false
                                                                            ) || (
                                                                                strpos($href, $strTorneoFechaC . '/' . $visitante3 . '-' . $local3 . '/') !== false
                                                                            ) || (
                                                                                strpos($href, $strTorneoFechaD . '/' . $local3 . '-' . $visitante3 . '/') !== false
                                                                            ) || (
                                                                                strpos($href, $strTorneoFechaD . '/' . $visitante3 . '-' . $local3 . '/') !== false
                                                                            ) || (
                                                                                strpos($href, $strTorneoFechaE . '/' . $local3 . '-' . $visitante3 . '/') !== false
                                                                            ) || (
                                                                                strpos($href, $strTorneoFechaE . '/' . $visitante3 . '-' . $local3 . '/') !== false
                                                                            ) || (
                                                                                strpos($href, $strTorneoFechaF . '/' . $local3 . '-' . $visitante3 . '/') !== false
                                                                            ) || (
                                                                                strpos($href, $strTorneoFechaF . '/' . $visitante3 . '-' . $local3 . '/') !== false
                                                                            )

                                                                        ) {
                                                                            $urlEncontrada = 1;
                                                                            //Log::channel('mi_log')->info('OJO!! encontró gol de penal: ' . $href, []);
                                                                            $success .= '<span style="color:green">Encontró gol de penal: ' . $href . '</span><br>';

                                                                            // Crear el array de datos para el jugador y el gol
                                                                            $data3 = array(
                                                                                'partido_id' => $partido->id,
                                                                                'jugador_id' => $gol->jugador->id,
                                                                                'minuto' => $gol->minuto,
                                                                                'tipo' => 'Penal',
                                                                                'url' => $urlPenales,
                                                                            );
                                                                            $jugadorGolArray[$gol->jugador->id][] = $data3;
                                                                        }
                                                                    }
                                                                }

                                                                // Si no se encontró la URL, registrar en el log
                                                                if (!$urlEncontrada) {
                                                                    //Log::channel('mi_log')->info('no está penal: ' . $href, []);
                                                                }
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    } else {
                                        /*fputcsv($handle, [

                                            utf8_decode($grupo->torneo->nombre.' '.$grupo->torneo->year),
                                            utf8_decode($fecha->numero),
                                            utf8_decode($partido->equipol->nombre . ' VS ' . $partido->equipov->nombre),
                                            utf8_decode($gol->jugador->persona->nombre.' - '.$gol->jugador->persona->apellido),
                                            utf8_decode($gol->tipo.' - '.$gol->minuto),
                                            utf8_decode('No se econtró la URL de penales'),
                                            $urlPenales
                                        ], "|");*/
                                        //Log::channel('mi_log')->info('OJO!!! No se econtró la URL de penales' , []);
                                        $success .= '<span style="color:red">No se econtró la URL de penales ' . $urlPenales . '</span><br>';
                                    }

                                } else {
                                    $success .= '<span style="color:red">No se econtró la URL del jugador ' . $gol->jugador->persona->nombre . ' ' . $gol->jugador->persona->apellido . '</span><br>';
                                }


                            } else {
                                /*fputcsv($handle, [

                                    utf8_decode($grupo->torneo->nombre.' '.$grupo->torneo->year),
                                    utf8_decode($fecha->numero),
                                    utf8_decode($partido->equipol->nombre . ' VS ' . $partido->equipov->nombre),
                                    utf8_decode($gol->jugador->persona->nombre.' - '.$gol->jugador->persona->apellido),
                                    utf8_decode($gol->tipo.' - '.$gol->minuto),
                                    utf8_decode('No se econtró la URL del jugador'),
                                    $urlJugador
                                ], "|");*/
                                //Log::channel('mi_log')->info('OJO!!! No se econtró la URL del jugador' , []);
                                $success .= '<span style="color:red">No se econtró la URL del jugador ' . $urlJugador . '</span><br>';
                            }
                            // }
                        }
                        foreach ($jugadorGolArray as $key => $item) {

                            $jugador = Jugador::findOrFail($key);
                            if (count($item) > 1) {
                                //Log::channel('mi_log')->info('OJO!!! más de un gol de '.$key , []);
                                $success .= '<span style="color:red">Más de un gol de ' . $key . '</span><br>';
                                /*foreach ($item as $value){
                                    fputcsv($handle, [

                                        utf8_decode($grupo->torneo->nombre.' '.$grupo->torneo->year),
                                        utf8_decode($fecha->numero),
                                        utf8_decode($partido->equipol->nombre . ' VS ' . $partido->equipov->nombre),
                                        utf8_decode($jugador->persona->nombre.' - '.$jugador->persona->apellido),
                                        utf8_decode($value['tipo'].' - '.$value['minuto']),
                                        utf8_decode('más de un gol'),
                                        $value['url']
                                    ], "|");
                                    //Log::channel('mi_log')->info(' => '.$value['tipo'].' - '.$value['minuto'] , []);
                                }*/

                            } else {

                                //Log::channel('mi_log')->info('OJO!!! un solo gol de: '.$key.' => '.$item[0]['tipo'].' - '.$item[0]['minuto'] , []);
                                //$success .= '<span style="color:green">Un solo gol de: ' . $key . ' => ' . $item[0]['tipo'] . ' - ' . $item[0]['minuto'] . '</span><br>';
                                $golesJugador = Gol::where('partido_id', '=', $item[0]['partido_id'])->where('jugador_id', '=', $key)->get();
                                if (count($golesJugador) == 1) {


                                    /*fputcsv($handle, [

                                        utf8_decode($grupo->torneo->nombre.' '.$grupo->torneo->year),
                                        utf8_decode($fecha->numero),
                                        utf8_decode($partido->equipol->nombre . ' VS ' . $partido->equipov->nombre),
                                        utf8_decode($jugador->persona->nombre.' - '.$jugador->persona->apellido),
                                        utf8_decode($item[0]['tipo'].' - '.$item[0]['minuto']),
                                        utf8_decode('Gol actualizado'),
                                        $item[0]['url']
                                    ], "|");*/
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
                                /*else{
                                    fputcsv($handle, [

                                        utf8_decode($grupo->torneo->nombre.' '.$grupo->torneo->year),
                                        utf8_decode($fecha->numero),
                                        utf8_decode($partido->equipol->nombre . ' VS ' . $partido->equipov->nombre),
                                        utf8_decode($jugador->persona->nombre.' - '.$jugador->persona->apellido),
                                        utf8_decode($item[0]['tipo'].' - '.$item[0]['minuto']),
                                        utf8_decode('Tiene goles de otro tipo'),
                                        $item[0]['url']
                                    ], "|");
                                }*/


                            }

                        }


                    }

                }
            }
        }
            //fclose($handle);
            if ($ok) {


                DB::commit();
                $respuestaID = 'success';
                $respuestaMSJ = $success;
            } else {
                DB::rollback();
                $respuestaID = 'error';
                $respuestaMSJ = $error . '<br>' . $success;
            }

            //
            return redirect()->route('fechas.index', array('grupoId' => $fecha->grupo->id))->with($respuestaID, $respuestaMSJ);

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
                //Log::channel('mi_log')->info('Partido ' . $partido->equipol->nombre . ' VS ' . $partido->equipov->nombre, []);
                $arbitros = PartidoArbitro::where('partido_id', '=', "$partido->id")->get();
                $url='';


                if(count($arbitros)!=3){


                    try {
                        $url = 'https://www.livefutbol.com/cronica/primera-division-'.$years.'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'/';
                        //Log::channel('mi_log')->info('OJO!!! URL ' .$url, []);
                        $html2 = HtmlDomParser::file_get_html($url, false, null, 0);
                        /*Log::channel('mi_log')->info('OJO!!! URL ' .'https://arg.worldfootball.net/cronica/copa-de-la-superliga-'.$years.'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'/', []);

                        $html2 = HtmlDomParser::file_get_html('https://arg.worldfootball.net/cronica/copa-de-la-superliga-'.$years.'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'/', false, null, 0);*/

                        if (!$html2){
                            $url = 'https://www.livefutbol.com/cronica/primera-division-'.$years.'-'.$this->dameNombreEquipoURL2ALT($strLocal).'-'.$this->dameNombreEquipoURL2ALT($strVisitante).'/';
                            //Log::channel('mi_log')->info('OJO!!! URL ' .$url, []);
                            $html2 = HtmlDomParser::file_get_html($url, false, null, 0);
                        }
                        if (!$html2){
                            $url = 'https://www.livefutbol.com/cronica/primera-division-'.$years.'-'.$this->dameNombreEquipoURL2ALT($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'/';
                            //Log::channel('mi_log')->info('OJO!!! URL ' .$url, []);
                            $html2 = HtmlDomParser::file_get_html($url, false, null, 0);
                        }
                        if (!$html2){
                            $url = 'https://www.livefutbol.com/cronica/primera-division-'.$years.'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2ALT($strVisitante).'/';
                            //Log::channel('mi_log')->info('OJO!!! URL ' .$url, []);
                            $html2 = HtmlDomParser::file_get_html($url, false, null, 0);
                        }
                        if (!$html2){
                            $url ='https://www.livefutbol.com/cronica/primera-division-'.$years.'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'_2/';
                            //Log::channel('mi_log')->info('OJO!!! URL ' .$url, []);
                            $html2 = HtmlDomParser::file_get_html($url, false, null, 0);
                        }
                        $linkArray=array();
                        $entrenadoresArray = array();
                        $nombreArbitro ='';
                        if (!$html2) {
                            $url ='https://www.livefutbol.com/cronica/primera-division-'.$years.'-'.strtr($nombreTorneo, " ", "-").'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'/';
                            //Log::channel('mi_log')->info('OJO!!! URL ' .$url, []);

                            $html2 = HtmlDomParser::file_get_html($url, false, null, 0);


                        }
                        if (!$html2) {
                            $url ='https://www.livefutbol.com/cronica/primera-division-'.$years.'-'.strtr($nombreTorneo, " ", "-").'-'.$this->dameNombreEquipoURL2ALT($strLocal).'-'.$this->dameNombreEquipoURL2ALT($strVisitante).'/';
                            //Log::channel('mi_log')->info('OJO!!! URL ' .$url, []);

                            $html2 = HtmlDomParser::file_get_html($url, false, null, 0);


                        }
                        if (!$html2) {
                            $url ='https://www.livefutbol.com/cronica/primera-division-'.$years.'-'.strtr($nombreTorneo, " ", "-").'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2ALT($strVisitante).'/';
                            //Log::channel('mi_log')->info('OJO!!! URL ' .$url, []);

                            $html2 = HtmlDomParser::file_get_html($url, false, null, 0);


                        }
                        if (!$html2) {
                            $url ='https://www.livefutbol.com/cronica/primera-division-'.$years.'-'.strtr($nombreTorneo, " ", "-").'-'.$this->dameNombreEquipoURL2ALT($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'/';
                            //Log::channel('mi_log')->info('OJO!!! URL ' .$url, []);

                            $html2 = HtmlDomParser::file_get_html($url, false, null, 0);


                        }
                        if (!$html2) {
                            $url ='https://www.livefutbol.com/cronica/copa-de-la-superliga-'.$years.'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'/';
                            //Log::channel('mi_log')->info('OJO!!! URL ' .$url, []);

                            $html2 = HtmlDomParser::file_get_html($url, false, null, 0);
                        }
                        if (!$html2) {
                            $url ='https://www.livefutbol.com/cronica/copa-de-la-superliga-'.$years.'-'.$this->dameNombreEquipoURL2ALT($strLocal).'-'.$this->dameNombreEquipoURL2ALT($strVisitante).'/';
                            //Log::channel('mi_log')->info('OJO!!! URL ' .$url, []);

                            $html2 = HtmlDomParser::file_get_html($url, false, null, 0);
                        }
                        if (!$html2) {
                            $url ='https://www.livefutbol.com/cronica/copa-de-la-superliga-'.$years.'-'.$this->dameNombreEquipoURL2($strLocal).'-'.$this->dameNombreEquipoURL2ALT($strVisitante).'/';
                            Log::channel('mi_log')->info('OJO!!! URL ' .$url, []);

                            $html2 = HtmlDomParser::file_get_html($url, false, null, 0);
                        }
                        if (!$html2) {
                            $url ='https://www.livefutbol.com/cronica/copa-de-la-superliga-'.$years.'-'.$this->dameNombreEquipoURL2ALT($strLocal).'-'.$this->dameNombreEquipoURL2($strVisitante).'/';
                            //Log::channel('mi_log')->info('OJO!!! URL ' .$url, []);

                            $html2 = HtmlDomParser::file_get_html($url, false, null, 0);
                        }

                    }
                    catch (Exception $ex) {
                        $html2='';
                    }
                    if ($html2){
                        if(count($arbitros)>3){
                            //Log::channel('mi_log')->info('OJO!!! mas de 3 jueces', []);
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
                                                //Log::channel('mi_log')->info('Asistente 1: ' . $nombreAsistente, []);
                                                $asistente++;
                                            } else {
                                                $asistente2 = $nombreAsistente;
                                                //Log::channel('mi_log')->info('Asistente 2: ' . $nombreAsistente, []);
                                                $asistente++;
                                            }

                                        } else {
                                            $nombreArbitro = '';
                                            for ($i = 1; $i < count($linkArray); $i++) {
                                                $nombreArbitro .= ($linkArray[$i]) . ' ';
                                            }

                                            //Log::channel('mi_log')->info('Arbitro: ' . $nombreArbitro, []);

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
                                    //Log::channel('mi_log')->info('OJO!! Arbitro NO encontrado: '.$nombreArbitro.' '.$partido->equipol->nombre.' VS '.$partido->equipov->nombre,[]);
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
                                    //Log::channel('mi_log')->info('OJO!! Asistente NO encontrado: '.$asistente1.' '.$partido->equipol->nombre.' VS '.$partido->equipov->nombre,[]);
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
                                    //Log::channel('mi_log')->info('OJO!! Asistente NO encontrado: '.$asistente2.' '.$partido->equipol->nombre.' VS '.$partido->equipov->nombre,[]);
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
        // Buscar torneo
        if ($request->query('torneoId')) {
            $torneo_id = $request->query('torneoId');
            $torneo = Torneo::findOrFail($torneo_id);
        } else {
            $torneo = Torneo::orderBy('year', 'DESC')->first();
            $torneo_id = $torneo->id;
        }

        // Guardar info del torneo en sesión
        $request->session()->put('nombreTorneo', $torneo->nombre . ' ' . $torneo->year);
        $request->session()->put('escudoTorneo', $torneo->escudo);
        $request->session()->put('codigoTorneo', $torneo_id);

        $grupos = Grupo::where('torneo_id', $torneo_id)->get();

        // Limpiar sesiones
        $request->session()->forget(['sessionAcumulado', 'sessionPosiciones', 'sessionPromedios', 'sessionPaenza']);

        $arrgrupos = '';
        foreach ($grupos as $grupo) {
            if ($grupo->acumulado) {
                $request->session()->put('sessionAcumulado', 1);
            }
            if ($grupo->posiciones) {
                $request->session()->put('sessionPosiciones', 1);
                if (count($grupos) == 1) {
                    $request->session()->put('sessionPaenza', 1);
                }
            }
            if ($grupo->promedios) {
                $request->session()->put('sessionPromedios', 1);
            }
            $arrgrupos .= $grupo->id . ',';
        }

        // 🚀 Cambiamos numero por orden
        $fechaOrden = $request->query('fechaOrden');

        if (empty($fechaOrden)) {
            $ultimaFecha = Fecha::whereIn('grupo_id', explode(',', $arrgrupos))
                ->orderBy('orden', 'desc')
                ->orderBy('id', 'desc')
                ->first();
            $fechaOrden = $ultimaFecha->orden;
        }

        $fechas = Fecha::whereIn('grupo_id', explode(',', $arrgrupos))
            ->where('orden', '=', $fechaOrden)
            ->get();

        $fecha = $fechas->first(); // La fecha actual

        // Partidos de esta fecha
        $arrfechas = $fechas->pluck('id')->toArray();
        $partidos = Partido::whereIn('fecha_id', $arrfechas)
            ->orderBy('dia', 'ASC')
            ->get();

        // Agrupar partidos por eliminatoria
        $partidosAgrupados = $partidos->groupBy(function ($partido) {
            return min($partido->equipol_id, $partido->equipov_id) . '-' . max($partido->equipol_id, $partido->equipov_id);
        });

        // Todas las fechas disponibles del torneo (para la navegación)
        $fechas = Fecha::select('orden', 'numero')
            ->distinct()
            ->whereIn('grupo_id', explode(',', $arrgrupos))
            ->orderBy('orden', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        // Determinar si hay partidos de ida y vuelta
        $hayIdaVuelta = $partidosAgrupados->contains(function ($partidos) {
            return $partidos->count() > 1;
        });

        return view('fechas.ver', compact('fechas', 'torneo', 'partidos', 'fecha', 'partidosAgrupados', 'hayIdaVuelta'));
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

        $incidencias=Incidencia::where('partido_id',$partido_id)->paginate();

        //dd($partido->fecha->grupo->torneo->nombre);
        return view('fechas.detalle', compact('goles','partido', 'tarjetas','cambios','titularesL','suplentesL','titularesV','suplentesV','tecnicosL','tecnicosV','arbitros','incidencias'));
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
        //$url = $request->get('url');
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

        $error='';
        $success='';
        $html2='';
        try {
            if ($url2){
                //Log::channel('mi_log')->info('Partido ' .$partido->equipol->nombre.' VS '.$partido->equipov->nombre, []);

                //$html2 = HtmlDomParser::file_get_html($url2, false, null, 0);
                $html2 = HttpHelper::getHtmlContent($url2);
                // Ejecutar el script Node.js y capturar la salida
                //$scriptPath = escapeshellarg(base_path('scripts/scrape.js'));
                //$escapedUrl = escapeshellarg($url2);

                // Ejecutar el script Node.js con ambas partes escapadas
                //$html2 = shell_exec("node $scriptPath $escapedUrl 2>&1");

                /*$endpoint = 'https://scrape-prod.up.railway.app/scrape?url=' . urlencode($url2);
                $html2 = file_get_contents($endpoint);*/


                // Log parcial del HTML para debug
                //Log::channel('mi_log')->debug("HTML capturado: " . substr($html2, 0, 5000));
            }


        }
        catch (Exception $ex) {
            //Log::channel('mi_log')->error('Error en la ejecución del scraper: ' . $ex->getMessage());
            $html2='';
        }
        if ($html2) {
            $dtLocal ='';
            $dtVisitante ='';
            $goles=0;
            $golesL=0;
            $golesV=0;

            $tabla = 0;
            $equipos = array();
            $tablaIncidencias = 0;
            // Crea un nuevo DOMDocument
            $dom = new \DOMDocument();

            // Evita advertencias de HTML mal formado
            libxml_use_internal_errors(true);

            // Carga el HTML en el DOMDocument
            $dom->loadHTML($html2);

            // Restaura la gestión de errores de libxml
            libxml_clear_errors();

            // Intenta cargar el HTML recuperado
            if ($dom->loadHTML($html2)) {
                $xpath = new \DOMXPath($dom);

                // Buscar la tabla con clase 'standard_tabelle'
                $tables = $xpath->query('//table[@class="standard_tabelle"]');

                foreach ($tables as $table) {
                    //Log::channel('mi_log')->info('OJO!! tabla: ' . $table->textContent, []);
                    if ($tabla == 1) {
                        $golesArray = array();


                        // Obtener todas las celdas <td> dentro de la tabla
                        $tds = $xpath->query('.//td', $table);

                        foreach ($tds as $td) {
                            $jugadorGol = '';
                            $lineaGol = '';
                            $minutoGol = '';
                            $incidenciaGol = '';
                            // Buscar un elemento <a> dentro del <td>
                            $aElement = $xpath->query('.//a', $td);

                            if ($aElement->length > 0) {
                                // Procesar el enlace y el contenido
                                $jugadorGol = trim($aElement->item(0)->nodeValue);
                                //Log::channel('mi_log')->info('OJO!! gol: ' . $jugadorGol, []);
                                // Obtener el contenido del <td> completo (similar a $td->plaintext)
                                $lineaGol = trim($td->nodeValue);
                                if (str_contains($lineaGol, $jugadorGol)) {
                                    //$minutoGol = (int) filter_var($lineaGol, FILTER_SANITIZE_NUMBER_INT);
                                    //Log::channel('mi_log')->info('OJO!! gol: '.$lineaGol,[]);
                                    $arrayGol = explode(".", $lineaGol);

                                    $minutoGol = (int)filter_var($arrayGol[0], FILTER_SANITIZE_NUMBER_INT);
                                    if (count($arrayGol) > 1) {
                                        $adiccion = (int)filter_var($arrayGol[1], FILTER_SANITIZE_NUMBER_INT);
                                        if ($adiccion > 0) {
                                            //Log::channel('mi_log')->info('OJO!! gol addicion: ');
                                            $minutoGol = $minutoGol + $adiccion;
                                        }

                                    }
                                    //Log::channel('mi_log')->info('OJO!! min: '.$minutoGol,[]);
                                }

                                $incidenciaArray = explode('/', $lineaGol);
                                if (count($incidenciaArray) > 1) {
                                    $incidenciaGol = $incidenciaArray[1];
                                    //Log::channel('mi_log')->info('OJO!! incidencia: '.$incidenciaGol,[]);
                                }
                                $goles++;
                                $golesArray[] = $jugadorGol . '-' . $minutoGol . '-' . $incidenciaGol;
                            }


                        }
                    }
                    if ($tabla == 2) {

                        $jugadores = array();
                        //Log::channel('mi_log')->info('OJO!! locales:',[]);
                        $suplentes = 0;
                        $trs = $xpath->query('.//tr', $table);

                        foreach ($trs as $tr) {
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
                            // Consulta todos los elementos <td> en el <tr>
                            $tds = $xpath->query('.//td', $tr);
                            foreach ($tds as $td) {
                                //Log::channel('mi_log')->info('OJO!! linea: ' . $td->textContent, []);

                                if (trim($td->textContent) == 'Incidencias' || trim($td->textContent) == 'Tanda de penaltis') {
                                    $tablaIncidencias = 1;
                                }

                                if (!$tablaIncidencias) {
                                    if (trim($td->textContent) == 'Banquillo') {
                                        $suplentes = 1;
                                    }

                                    // Consulta elementos <span> dentro del <td>
                                    $kleine_schrift = $xpath->query('.//span[contains(@class, "kleine_schrift")]', $td);
                                    if ($kleine_schrift->length > 0) {
                                        if ($xpath->query('.//span[@style="font-weight: bold; color: #646464"]', $td)->length > 0) {
                                            if ($suplentes) {
                                                $dorsalSuplenteL = $kleine_schrift[0]->textContent;
                                            } else {
                                                $dorsalTitularL = $kleine_schrift[0]->textContent;
                                            }
                                        } elseif ($xpath->query('.//span[contains(@class, "rottext")]', $td)->length > 0) {
                                            if ($suplentes) {
                                                $arraySale = explode("'", $xpath->query('.//span[contains(@class, "rottext")]', $td)[0]->textContent);
                                                $saleSuplenteL = (int)$arraySale[0];
                                                if (count($arraySale) > 1) {
                                                    $adiccion = (int)$arraySale[1];
                                                    if ($adiccion > 0) {
                                                        //Log::channel('mi_log')->info('OJO!! cambio addicion: ');
                                                        $saleSuplenteL += $adiccion;
                                                    }
                                                }
                                            } else {
                                                $arraySale = explode("'", $xpath->query('.//span[contains(@class, "rottext")]', $td)[0]->textContent);
                                                $saleTitularL = (int)$arraySale[0];
                                                if (count($arraySale) > 1) {
                                                    $adiccion = (int)$arraySale[1];
                                                    if ($adiccion > 0) {
                                                        //Log::channel('mi_log')->info('OJO!! cambio addicion: ');
                                                        $saleTitularL += $adiccion;
                                                    }
                                                }
                                            }
                                        } elseif ($xpath->query('.//span[contains(@class, "gruentext")]', $td)->length > 0) {
                                            if ($suplentes) {
                                                $arrayEntra = explode("'", $xpath->query('.//span[contains(@class, "gruentext")]', $td)[0]->textContent);
                                                $entraSuplenteL = (int)$arrayEntra[0];
                                                if (count($arrayEntra) > 1) {
                                                    $adiccion = (int)$arrayEntra[1];
                                                    if ($adiccion > 0) {
                                                        //Log::channel('mi_log')->info('OJO!! cambio addicion: ');
                                                        $entraSuplenteL += $adiccion;
                                                    }
                                                }
                                            }
                                        } else {
                                            if ($kleine_schrift[0]->getAttribute('title') != '') {
                                                if ($suplentes) {
                                                    $arrayTarjeta = explode("'", $kleine_schrift[0]->textContent);
                                                    $mintutoTarjetaSuplenteL = (int)$arrayTarjeta[0];
                                                    if (count($arrayTarjeta) > 1) {
                                                        $adiccion = (int)$arrayTarjeta[1];
                                                        if ($adiccion > 0) {
                                                            //Log::channel('mi_log')->info('OJO!! tarjeta addicion: ');
                                                            $mintutoTarjetaSuplenteL += $adiccion;
                                                        }
                                                    }
                                                } else {
                                                    $arrayTarjeta = explode("'", $kleine_schrift[0]->textContent);
                                                    $mintutoTarjetaTitularL = (int)$arrayTarjeta[0];
                                                    if (count($arrayTarjeta) > 1) {
                                                        $adiccion = (int)$arrayTarjeta[1];
                                                        if ($adiccion > 0) {
                                                            //Log::channel('mi_log')->info('OJO!! tarjeta addicion: ');
                                                            $mintutoTarjetaTitularL += $adiccion;
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }

                                    // Consulta imágenes
                                    $imgs = $xpath->query('.//img', $td);
                                    if ($imgs->length > 0) {
                                        foreach ($imgs as $img) {
                                            if ($img->getAttribute('title') == 'Tarjeta amarilla') {
                                                if ($suplentes) {
                                                    $amarillaSuplenteL = ($mintutoTarjetaSuplenteL) ? (int)filter_var($mintutoTarjetaSuplenteL, FILTER_SANITIZE_NUMBER_INT) : -1;
                                                } else {
                                                    $amarillaTitularL = ($mintutoTarjetaTitularL) ? (int)filter_var($mintutoTarjetaTitularL, FILTER_SANITIZE_NUMBER_INT) : -1;
                                                }
                                            }
                                            if ($img->getAttribute('title') == 'Roja directa') {
                                                if ($suplentes) {
                                                    $rojaSuplenteL = ($mintutoTarjetaSuplenteL) ? (int)filter_var($mintutoTarjetaSuplenteL, FILTER_SANITIZE_NUMBER_INT) : -1;
                                                } else {
                                                    $rojaTitularL = ($mintutoTarjetaTitularL) ? (int)filter_var($mintutoTarjetaTitularL, FILTER_SANITIZE_NUMBER_INT) : -1;
                                                }
                                            }
                                            if ($img->getAttribute('title') == 'Doble amarilla') {
                                                if ($suplentes) {
                                                    $dobleamarillaSuplenteL = ($mintutoTarjetaSuplenteL) ? (int)filter_var($mintutoTarjetaSuplenteL, FILTER_SANITIZE_NUMBER_INT) : -1;
                                                } else {
                                                    $dobleamarillaTitularL = ($mintutoTarjetaTitularL) ? (int)filter_var($mintutoTarjetaTitularL, FILTER_SANITIZE_NUMBER_INT) : -1;
                                                }
                                            }
                                        }

                                    }

                                    // Consulta enlaces
                                    $links = $xpath->query('.//a', $td);
                                    if ($links->length > 0) {
                                        if ($suplentes) {
                                            $jugadorSuplenteL = $links[0]->getAttribute('title');
                                        } else {
                                            $jugadorTitularL = $links[0]->getAttribute('title');
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
                                        $incidenciasT[] = array('Tarjeta amarilla', ($amarillaTitularL == -1) ? '' : $amarillaTitularL);
                                    }
                                    if ($dobleamarillaTitularL) {
                                        $incidenciasT[] = array('Expulsado por doble amarilla', ($dobleamarillaTitularL == -1) ? '' : $dobleamarillaTitularL);
                                    }
                                    if ($rojaTitularL) {
                                        $incidenciasT[] = array('Tarjeta roja', ($rojaTitularL == -1) ? '' : $rojaTitularL);
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
                            if (!empty($data)) {
                                $equipos[] = $data;
                            }

                        }
                    }


                    if ($tabla == 3) {
                        $jugadores = array();
                        //Log::channel('mi_log')->info('OJO!! visitante:',[]);
                        $suplentes = 0;
                        $trs = $xpath->query('.//tr', $table);

                        foreach ($trs as $tr) {
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
                            // Consulta todos los elementos <td> en el <tr>
                            $tds = $xpath->query('.//td', $tr);
                            foreach ($tds as $td) {// Consulta todos los elementos <td> en el <tr>

                                ////Log::channel('mi_log')->info('OJO!! linea: ' . $td->plaintext, []);
                                if (trim($td->textContent) == 'Banquillo') {
                                    $suplentes = 1;
                                }
                                // Consulta elementos <span> dentro del <td>
                                $kleine_schrift = $xpath->query('.//span[contains(@class, "kleine_schrift")]', $td);
                                if ($kleine_schrift->length > 0) {
                                    if ($xpath->query('.//span[@style="font-weight: bold; color: #646464"]', $td)->length > 0) {
                                        if ($suplentes) {
                                            $dorsalSuplenteV = $kleine_schrift[0]->textContent;
                                        } else {
                                            $dorsalTitularV = $kleine_schrift[0]->textContent;
                                        }
                                    } elseif ($xpath->query('.//span[contains(@class, "rottext")]', $td)->length > 0) {
                                        if ($suplentes) {

                                            $arraySale = explode("'", $xpath->query('.//span[contains(@class, "rottext")]', $td)[0]->textContent);
                                            $saleSuplenteV = (int)$arraySale[0];
                                            if (count($arraySale) > 1) {
                                                $adiccion = (int)$arraySale[1];
                                                if ($adiccion > 0) {
                                                    //Log::channel('mi_log')->info('OJO!! cambio addicion: ');
                                                    $saleSuplenteV = $saleSuplenteV + $adiccion;
                                                }
                                            }
                                            //Log::channel('mi_log')->info('OJO!! sale suplente: ' . $saleSuplenteV, []);
                                        } else {
                                            //$saleTitularV = (int) filter_var($td->find('span[class=rottext]')[0]->plaintext, FILTER_SANITIZE_NUMBER_INT);

                                            $arraySale = explode("'", $xpath->query('.//span[contains(@class, "rottext")]', $td)[0]->textContent);

                                            $saleTitularV = (int)$arraySale[0];
                                            if (count($arraySale) > 1) {
                                                $adiccion = (int)$arraySale[1];
                                                if ($adiccion > 0) {
                                                    //Log::channel('mi_log')->info('OJO!! cambio addicion: ');
                                                    $saleTitularV = $saleTitularV + $adiccion;
                                                }
                                            }
                                            //Log::channel('mi_log')->info('OJO!! sale titular: ' . $saleTitularV, []);
                                        }

                                    } elseif ($xpath->query('.//span[contains(@class, "gruentext")]', $td)->length > 0) {
                                        if ($suplentes) {

                                            $arrayEntra = explode("'", $xpath->query('.//span[contains(@class, "gruentext")]', $td)[0]->textContent);
                                            $entraSuplenteV = (int)$arrayEntra[0];
                                            if (count($arrayEntra) > 1) {
                                                $adiccion = (int)$arrayEntra[1];
                                                if ($adiccion > 0) {
                                                    //Log::channel('mi_log')->info('OJO!! cambio addicion: ');
                                                    $entraSuplenteV = $entraSuplenteV + $adiccion;
                                                }
                                            }
                                            //Log::channel('mi_log')->info('OJO!! entra suplente: ' . $entraSuplenteV, []);
                                        }


                                    } else {
                                        if ($kleine_schrift[0]->getAttribute('title') != '') {
                                            if ($suplentes) {
                                                $arrayTarjeta = explode("'", $kleine_schrift[0]->textContent);
                                                $mintutoTarjetaSuplenteV = (int)$arrayTarjeta[0];
                                                if (count($arrayTarjeta) > 1) {
                                                    $adiccion = (int)$arrayTarjeta[1];
                                                    if ($adiccion > 0) {
                                                        //Log::channel('mi_log')->info('OJO!! tarjeta addicion: ');
                                                        $mintutoTarjetaSuplenteV = $mintutoTarjetaSuplenteV + $adiccion;
                                                    }
                                                }
                                            } else {

                                                $arrayTarjeta = explode("'", $kleine_schrift[0]->textContent);
                                                $mintutoTarjetaTitularV = (int)$arrayTarjeta[0];
                                                if (count($arrayTarjeta) > 1) {
                                                    $adiccion = (int)$arrayTarjeta[1];
                                                    if ($adiccion > 0) {
                                                        //Log::channel('mi_log')->info('OJO!! tarjeta addicion: ');
                                                        $mintutoTarjetaTitularV = $mintutoTarjetaTitularV + $adiccion;
                                                    }
                                                }
                                            }


                                        }

                                    }
                                }

                                // Consulta imágenes
                                $imgs = $xpath->query('.//img', $td);
                                if ($imgs->length > 0) {
                                    foreach ($imgs as $img) {
                                        if ($img->getAttribute('title') == 'Tarjeta amarilla') {
                                            if ($suplentes) {
                                                $amarillaSuplenteV = ($mintutoTarjetaSuplenteV) ? (int)filter_var($mintutoTarjetaSuplenteV, FILTER_SANITIZE_NUMBER_INT) : -1;
                                                //Log::channel('mi_log')->info('OJO!! amarilla suplente: ' . $mintutoTarjetaSuplenteV, []);
                                            } else {
                                                $amarillaTitularV = ($mintutoTarjetaTitularV) ? (int)filter_var($mintutoTarjetaTitularV, FILTER_SANITIZE_NUMBER_INT) : -1;
                                                //Log::channel('mi_log')->info('OJO!! amarilla titular: ' . $amarillaTitularV, []);
                                            }

                                        }
                                        if ($img->getAttribute('title') == 'Roja directa') {
                                            if ($suplentes) {
                                                $rojaSuplenteV = ($mintutoTarjetaSuplenteV) ? (int)filter_var($mintutoTarjetaSuplenteV, FILTER_SANITIZE_NUMBER_INT) : -1;
                                                //Log::channel('mi_log')->info('OJO!! roja titular: ' . $rojaSuplenteV, []);
                                            } else {
                                                $rojaTitularV = ($mintutoTarjetaTitularV) ? (int)filter_var($mintutoTarjetaTitularV, FILTER_SANITIZE_NUMBER_INT) : -1;
                                                //Log::channel('mi_log')->info('OJO!! roja titular: ' . $rojaTitularV, []);
                                            }

                                        }
                                        if ($img->getAttribute('title') == 'Doble amarilla') {
                                            if ($suplentes) {
                                                $dobleamarillaSuplenteV = ($mintutoTarjetaSuplenteV) ? (int)filter_var($mintutoTarjetaSuplenteV, FILTER_SANITIZE_NUMBER_INT) : -1;
                                                //Log::channel('mi_log')->info('OJO!! dobleamarilla titular: ' . $dobleamarillaSuplenteV, []);
                                            } else {
                                                $dobleamarillaTitularV = ($mintutoTarjetaTitularV) ? (int)filter_var($mintutoTarjetaTitularV, FILTER_SANITIZE_NUMBER_INT) : -1;
                                                //Log::channel('mi_log')->info('OJO!! dobleamarilla titular: ' . $dobleamarillaTitularV, []);
                                            }

                                        }
                                    }


                                }

                                // Consulta enlaces
                                $links = $xpath->query('.//a', $td);
                                if ($links->length > 0) {
                                    if ($suplentes) {
                                        $jugadorSuplenteV = $links[0]->getAttribute('title');
                                    } else {
                                        $jugadorTitularV = $links[0]->getAttribute('title');
                                    }


                                }


                            }
                            if ($jugadorTitularV || $jugadorSuplenteV) {
                                $incidenciasT = array();
                                $incidenciasS = array();
                                if ($saleTitularV) {
                                    $incidenciasT[] = array('Sale', $saleTitularV);
                                }
                                if ($amarillaTitularV) {
                                    $incidenciasT[] = array('Tarjeta amarilla', ($amarillaTitularV == -1) ? '' : $amarillaTitularV);
                                }
                                if ($dobleamarillaTitularV) {
                                    $incidenciasT[] = array('Expulsado por doble amarilla', ($dobleamarillaTitularV == -1) ? '' : $dobleamarillaTitularV);
                                }
                                if ($rojaTitularV) {
                                    $incidenciasT[] = array('Tarjeta roja', ($rojaTitularV == -1) ? '' : $rojaTitularV);
                                }

                                if (!empty($golesArray)) {
                                    foreach ($golesArray as $golmin) {
                                        //Log::channel('mi_log')->info('OJO!! comparar goles: ' . trim($jugador).' - '.$golmin, []);
                                        $incGol = explode('-', $golmin);
                                        if (trim($jugadorTitularV) == trim($incGol[0])) {


                                            $minGol = $incGol[1];
                                            $incidenciaGol = '';
                                            if (!empty($incGol[2])) {
                                                $incidenciaGol = $incGol[2];
                                            }

                                            if (!$incidenciaGol) {
                                                $incidenciasT[] = array('Gol', $minGol);
                                                $golesV++;
                                            } else {
                                                if (str_contains($incidenciaGol, 'cabeza')) {
                                                    $incidenciasT[] = array('Cabeza', $minGol);
                                                    $golesV++;
                                                }
                                                if (str_contains($incidenciaGol, 'penalti')) {

                                                    $incidenciasT[] = array('Penal', $minGol);
                                                    $golesV++;
                                                }
                                                if (str_contains($incidenciaGol, 'propia puerta')) {
                                                    $incidenciasT[] = array('Gol en propia meta', $minGol);
                                                    $golesL++;
                                                }
                                                if (str_contains($incidenciaGol, 'tiro libre')) {
                                                    $incidenciasT[] = array('Tiro libre', $minGol);
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
                                        if (trim($jugadorSuplenteV) == trim($incGol[0])) {

                                            $minGol = $incGol[1];
                                            $incidenciaGol = '';
                                            if (!empty($incGol[2])) {
                                                $incidenciaGol = $incGol[2];
                                            }
                                            if (!$incidenciaGol) {
                                                $incidenciasS[] = array('Gol', $minGol);
                                                $golesV++;
                                            } else {
                                                if (str_contains($incidenciaGol, 'cabeza')) {
                                                    $incidenciasS[] = array('Cabeza', $minGol);
                                                    $golesV++;
                                                }
                                                if (str_contains($incidenciaGol, 'penalti')) {
                                                    $incidenciasS[] = array('Penal', $minGol);
                                                    $golesV++;
                                                }
                                                if (str_contains($incidenciaGol, 'propia puerta')) {
                                                    $incidenciasS[] = array('Gol en propia meta', $minGol);
                                                    $golesL++;
                                                }
                                                if (str_contains($incidenciaGol, 'tiro libre')) {
                                                    $incidenciasS[] = array('Tiro libre', $minGol);
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
                                if ($amarillaSuplenteV) {
                                    $incidenciasS[] = array('Tarjeta amarilla', $amarillaSuplenteV);
                                }
                                if ($dobleamarillaSuplenteV) {
                                    $incidenciasS[] = array('Expulsado por doble amarilla', $dobleamarillaSuplenteV);
                                }
                                if ($rojaSuplenteV) {
                                    $incidenciasS[] = array('Tarjeta roja', $rojaSuplenteV);
                                }
                                if ($saleSuplenteV) {
                                    $incidenciasS[] = array('Sale', $saleSuplenteV);
                                }
                                if ($entraSuplenteV) {
                                    $incidenciasS[] = array('Entra', $entraSuplenteV);
                                }

                                if ($suplentes) {
                                    $data2 = array(
                                        'dorsal' => trim($dorsalSuplenteV),
                                        'nombre' => trim($jugadorSuplenteV),
                                        'tipo' => 'Suplente',
                                        'incidencias' => $incidenciasS
                                    );
                                } else {
                                    $data2 = array(
                                        'dorsal' => trim($dorsalTitularV),
                                        'nombre' => trim($jugadorTitularV),
                                        'tipo' => 'Titular',
                                        'incidencias' => $incidenciasT
                                    );
                                }
                                if (!empty($data2)) {
                                    $jugadores[] = $data2;
                                }
                            }


                        }
                        $data = array(

                            'equipo' => $partido->equipov->nombre,

                            'jugadores' => $jugadores
                        );
                        if (!empty($data)) {
                            $equipos[] = $data;
                        }

                    }


                    if (!$tablaIncidencias) {
                        $tabla++;

                    }

                    $tablaIncidencias = 0;


                    $entrenadoresArray = explode('Entrenador:', $table->textContent);
                    if (count($entrenadoresArray) > 3) {
                        //Log::channel('mi_log')->info('OJO!! varios entrenadores: ' . $partido->equipol->nombre . ' VS ' . $partido->equipov->nombre, []);
                        $success .= 'Varios entrenadores <br>';

                    }
                    if (count($entrenadoresArray) > 1) {
                        $segundoLocalArray = explode('Segundo entrenador:', $entrenadoresArray[1]);
                        if (count($segundoLocalArray) > 1) {
                            $dtLocal = $segundoLocalArray[0];
                        } else {
                            $dtLocal = $entrenadoresArray[1];
                        }

                        //Log::channel('mi_log')->info('DT Local: '.utf8_decode($entrenadoresArray[1]), []);
                        if (isset($entrenadoresArray[2])) {
                            $segundoVisitanteArray = explode('Segundo entrenador:', $entrenadoresArray[2]);
                            if (count($segundoVisitanteArray) > 1) {
                                $dtVisitante = $segundoVisitanteArray[0];
                            } else {
                                $dtVisitante = $entrenadoresArray[2];
                            }
                        } else {
                            //Log::channel('mi_log')->info('OJO!! Falta Entrenador visitante: ' . $partido->equipol->nombre . ' VS ' . $partido->equipov->nombre, []);
                            $success .= 'Falta un entrenador <br>';
                            $dtVisitante = '';
                        }

                        //Log::channel('mi_log')->info('DT Visitante: '.utf8_decode($entrenadoresArray[2]), []);
                    } else {
                        /*$dtLocal ='';
                        $dtVisitante ='';*/
                        $asistente = 0;
                        $asistente1 = '';
                        $asistente2 = '';
                        $arbitro = '';
                        $arbitro1 = '';
                        $arbitro2 = '';
                        $nombreArbitro = '';
                        // Seleccionar los elementos 'td' con la clase 'dunkel'
                        $elements = $xpath->query('//td[@class="dunkel"]');

                        foreach ($elements as $element2) {
                            // Encontrar enlaces dentro de cada 'td' seleccionado
                            $links = $xpath->query('.//a', $element2);

                            foreach ($links as $link) {
                                $linkArray = explode(' ', $link->getAttribute('title'));

                                if (($linkArray[0]) == 'Árbitro') {
                                    if (($linkArray[1]) == 'asistente') {
                                        $nombreAsistente = '';
                                        for ($i = 2; $i < count($linkArray); $i++) {
                                            $nombreAsistente .= ($linkArray[$i]) . ' ';
                                        }
                                        if ($asistente == 0) {
                                            $asistente1 = $nombreAsistente;
                                            //Log::channel('mi_log')->info('Asistente 1: ' . $nombreAsistente, []);
                                            $asistente++;
                                        } else {
                                            $asistente2 = $nombreAsistente;
                                            //Log::channel('mi_log')->info('Asistente 2: ' . $nombreAsistente, []);
                                            $asistente++;
                                        }

                                    } else {
                                        $nombreArbitro = ''; // Asegúrate de inicializar la variable
                                        for ($i = 1; $i < count($linkArray); $i++) {
                                            $nombreArbitro .= ($linkArray[$i]) . ' ';
                                        }

                                        //Log::channel('mi_log')->info('Arbitro: ' . $nombreArbitro, []);
                                    }
                                }
                            }
                        }

                    }

                }
                if (!empty($nombreArbitro)) {
                    $partesNombre = explode(' ', $nombreArbitro);

                    // Realizar la consulta buscando coincidencias en nombre y apellido
                    $arbitro = Arbitro::join('personas', 'personas.id', '=', 'arbitros.persona_id')
                        ->where(function ($query) use ($partesNombre) {
                            foreach ($partesNombre as $parte) {
                                $query->where(function ($query) use ($parte) {
                                    $query->where('personas.nombre', 'LIKE', "%$parte%")
                                        ->orWhere('personas.apellido', 'LIKE', "%$parte%")
                                        ->orWhere('personas.name', 'LIKE', "%$parte%");
                                });
                            }
                        })
                        ->select('arbitros.id as arbitro_id', 'personas.*') // Seleccionar arbitro_id y todos los campos de personas
                        ->first();
                }


                if (!$arbitro) {
                    //Log::channel('mi_log')->info('OJO!! Arbitro NO encontrado: ' . $nombreArbitro . ' ' . $partido->equipol->nombre . ' VS ' . $partido->equipov->nombre, []);
                    $success .= 'Arbitro NO encontrado: ' . $nombreArbitro . '<br>';
                } else {
                    $data3 = array(
                        'partido_id' => $partido->id,
                        'arbitro_id' => $arbitro->arbitro_id,
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

                if (!empty($asistente1)) {
                    $partesNombre = explode(' ', $asistente1);

                    // Realizar la consulta buscando coincidencias en nombre y apellido
                    $arbitro1 = Arbitro::join('personas', 'personas.id', '=', 'arbitros.persona_id')
                        ->where(function ($query) use ($partesNombre) {
                            foreach ($partesNombre as $parte) {
                                $query->where(function ($query) use ($parte) {
                                    $query->where('personas.nombre', 'LIKE', "%$parte%")
                                        ->orWhere('personas.apellido', 'LIKE', "%$parte%")
                                        ->orWhere('personas.name', 'LIKE', "%$parte%");
                                });
                            }
                        })
                        ->select('arbitros.id as arbitro_id', 'personas.*') // Seleccionar arbitro_id y todos los campos de personas
                        ->first();
                }



                if (!$arbitro1) {
                    //Log::channel('mi_log')->info('OJO!! Asistente NO encontrado: ' . $asistente1 . ' ' . $partido->equipol->nombre . ' VS ' . $partido->equipov->nombre, []);
                    $success .= 'Asistente NO encontrado: ' . $asistente1 . '<br>';
                } else {
                    $data3 = array(
                        'partido_id' => $partido->id,
                        'arbitro_id' => $arbitro1->arbitro_id,
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

                if (!empty($asistente2)) {
                    $partesNombre = explode(' ', $asistente2);

                    // Realizar la consulta buscando coincidencias en nombre y apellido
                    $arbitro2 = Arbitro::join('personas', 'personas.id', '=', 'arbitros.persona_id')
                        ->where(function ($query) use ($partesNombre) {
                            foreach ($partesNombre as $parte) {
                                $query->where(function ($query) use ($parte) {
                                    $query->where('personas.nombre', 'LIKE', "%$parte%")
                                        ->orWhere('personas.apellido', 'LIKE', "%$parte%")
                                        ->orWhere('personas.name', 'LIKE', "%$parte%");
                                });
                            }
                        })
                        ->select('arbitros.id as arbitro_id', 'personas.*') // Seleccionar arbitro_id y todos los campos de personas
                        ->first();
                }



                if (!$arbitro2) {
                    //Log::channel('mi_log')->info('OJO!! Asistente NO encontrado: ' . $asistente2 . ' ' . $partido->equipol->nombre . ' VS ' . $partido->equipov->nombre, []);
                    $success .= 'Asistente NO encontrado: ' . $asistente2 . '<br>';
                } else {
                    $data3 = array(
                        'partido_id' => $partido->id,
                        'arbitro_id' => $arbitro2->arbitro_id,
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

                    //$arrEntrenador = explode(' ', $strEntrenador);

                    $partesNombre = explode(' ', $strEntrenador);

                    // Realizar la consulta buscando coincidencias en nombre y apellido
                    $entrenadorL = Tecnico::join('personas', 'personas.id', '=', 'tecnicos.persona_id')
                        ->where(function ($query) use ($partesNombre) {
                            foreach ($partesNombre as $parte) {
                                $query->where(function ($query) use ($parte) {
                                    $query->where('personas.nombre', 'LIKE', "%$parte%")
                                        ->orWhere('personas.apellido', 'LIKE', "%$parte%")
                                        ->orWhere('personas.name', 'LIKE', "%$parte%");
                                });
                            }
                        })
                        ->select('tecnicos.id as tecnico_id', 'personas.*') // Seleccionar tecnico_id y todos los campos de personas
                        ->first();

                }
                //dd($entrenadorL);
                if (!empty($entrenadorL)) {
                    $data3 = array(
                        'partido_id' => $partido->id,
                        'equipo_id' => $partido->equipol->id,
                        'tecnico_id' => $entrenadorL->tecnico_id
                    );
                    $partido_tecnico = PartidoTecnico::where('equipo_id', '=', $partido->equipol->id)->where('tecnico_id', '=', "$entrenadorL->tecnico_id")->first();
                    if (empty($partido_tecnico)) {
                        //Log::channel('mi_log')->info('OJO!!! Nunca lo dirigio : ' . trim($dtLocal), []);
                        $success .= 'Nunca lo dirigio : ' . trim($dtLocal) . '<br>';
                    }
                    $partido_tecnico = PartidoTecnico::where('partido_id', '=', "$partido->id")->where('equipo_id', '=', $partido->equipol->id)->where('tecnico_id', '=', "$entrenadorL->id")->first();
                    try {
                        if (!empty($partido_tecnico)) {

                            $partido_tecnico->update($data3);
                        } else {
                            $partido_tecnico = PartidoTecnico::create($data3);
                        }

                    } catch (QueryException $ex) {
                        $error = $ex->getMessage();
                        $ok = 0;
                        //continue;
                    }
                } else {
                    //Log::channel('mi_log')->info('OJO!!! Técnico NO encontrado : ' . trim($dtLocal), []);
                    $success .= 'Técnico NO encontrado : ' . trim($dtLocal) . '<br>';
                }
                $entrenadorV = '';
                if ($dtVisitante) {
                    $strEntrenador = trim($dtVisitante);

                    $partesNombre = explode(' ', $strEntrenador);

                    // Realizar la consulta buscando coincidencias en nombre y apellido
                    $entrenadorV = Tecnico::join('personas', 'personas.id', '=', 'tecnicos.persona_id')
                        ->where(function ($query) use ($partesNombre) {
                            foreach ($partesNombre as $parte) {
                                $query->where(function ($query) use ($parte) {
                                    $query->where('personas.nombre', 'LIKE', "%$parte%")
                                        ->orWhere('personas.apellido', 'LIKE', "%$parte%")
                                        ->orWhere('personas.name', 'LIKE', "%$parte%");
                                });
                            }
                        })
                        ->select('tecnicos.id as tecnico_id', 'personas.*') // Seleccionar tecnico_id y todos los campos de personas
                        ->first();


                }

                if (!empty($entrenadorV)) {
                    $data3 = array(
                        'partido_id' => $partido->id,
                        'equipo_id' => $partido->equipov->id,
                        'tecnico_id' => $entrenadorV->tecnico_id
                    );
                    $partido_tecnico = PartidoTecnico::where('equipo_id', '=', $partido->equipov->id)->where('tecnico_id', '=', "$entrenadorV->tecnico_id")->first();
                    if (empty($partido_tecnico)) {
                        //Log::channel('mi_log')->info('OJO!!! Nunca lo dirigio : ' . trim($dtVisitante), []);
                        $success .= 'Nunca lo dirigio : ' . trim($dtVisitante) . '<br>';
                    }
                    $partido_tecnico = PartidoTecnico::where('partido_id', '=', "$partido->id")->where('equipo_id', '=', $partido->equipov->id)->where('tecnico_id', '=', "$entrenadorV->id")->first();
                    try {
                        if (!empty($partido_tecnico)) {

                            $partido_tecnico->update($data3);
                        } else {
                            $partido_tecnico = PartidoTecnico::create($data3);
                        }

                    } catch (QueryException $ex) {
                        $error = $ex->getMessage();
                        $ok = 0;
                        //continue;
                    }
                } else {
                    //Log::channel('mi_log')->info('OJO!!! Técnico NO encontrado : ' . trim($dtVisitante), []);
                    $success .= 'Técnico NO encontrado : ' . trim($dtVisitante) . '<br>';
                }
                $golesTotales = $partido->golesl + $partido->golesv;
                $golesLocales = $partido->golesl;
                $golesVisitantes = $partido->golesv;
                if (($golesL != $golesLocales) || ($golesV != $golesVisitantes)) {
                    //Log::channel('mi_log')->info('OJO!!! No coincide la cantidad de goles en: ' . $partido->equipol->nombre . ' VS ' . $partido->equipov->nombre . ' -> ' . $golesL . ' a ' . $golesV . ' - ' . $golesLocales . ' a ' . $golesVisitantes, []);
                    $success .= 'No coincide la cantidad de goles  -> ' . $golesL . ' a ' . $golesV . ' - ' . $golesLocales . ' a ' . $golesVisitantes . '<br>';
                }
                foreach ($equipos as $eq) {
                    //Log::channel('mi_log')->info('Equipo  ' . $eq['equipo'], []);
                    $strEquipo = trim($eq['equipo']);
                    $equipo = Equipo::where('nombre', 'like', "%$strEquipo%")->first();
                    if (!empty($equipo)) {
                        // Detectar dorsales duplicados en el equipo
                        $dorsales = [];
                        $dorsalesRepetidos = [];

                        foreach ($eq['jugadores'] as $jugador) {
                            $dorsal = isset($jugador['dorsal']) ? trim($jugador['dorsal']) : '';

                            // Ignorar si el dorsal está vacío
                            if ($dorsal === '') {
                                continue;
                            }

                            if (isset($dorsales[$dorsal])) {
                                // Si ya existe, agregamos al actual como repetido
                                // Si es la primera repetición, también agregamos al primero registrado
                                if (!isset($dorsalesRepetidos[$dorsal])) {
                                    $dorsalesRepetidos[$dorsal] = [$dorsales[$dorsal]];
                                }
                                $dorsalesRepetidos[$dorsal][] = $jugador['nombre'];
                            } else {
                                $dorsales[$dorsal] = $jugador['nombre'];
                            }
                        }

                        if (!empty($dorsalesRepetidos)) {
                            foreach ($dorsalesRepetidos as $dorsal => $jugadores) {
                                $success .= 'DORSAL REPETIDO en equipo ' . $strEquipo . ': dorsal ' . $dorsal . ' usado por ' . implode(', ', $jugadores) . '<br>';
                            }
                        }

                        foreach ($eq['jugadores'] as $jugador) {
                            //Log::channel('mi_log')->info(json_encode($jugador), []);
                            $jugador_id = 0;
                            //Log::channel('mi_log')->info('Jugador: ' . $jugador['dorsal'] . ' ' . $jugador['nombre'] . ' del equipo ' . $strEquipo, []);
                            $grupos = Grupo::where('torneo_id', '=', $grupo->torneo->id)->get();
                            $arrgrupos = '';
                            foreach ($grupos as $grupo) {
                                $arrgrupos .= $grupo->id . ',';
                            }

                            $plantillas = Plantilla::wherein('grupo_id', explode(',', $arrgrupos))->where('equipo_id', '=', $equipo->id)->get();

                            $arrplantillas = '';
                            foreach ($plantillas as $plantilla) {
                                $arrplantillas .= $plantilla->id . ',';
                            }


                            if (!empty($plantillas)) {

                                if (!empty($jugador['dorsal'])) {

                                    $plantillaJugador = PlantillaJugador::wherein('plantilla_id', explode(',', $arrplantillas))->distinct()->where('dorsal', '=', $jugador['dorsal'])->first();
                                    $sinDorsal = ' OJO!!! ';
                                } else {
                                    //print_r($jugador);
                                    $plantillaJugador = '';
                                    //Log::channel('mi_log')->info('OJO!!! con el jugador no está en la plantilla del equipo ' . $strEquipo, []);
                                    $sinDorsal = ' es  ' . $jugador['nombre'] ;
                                }
                            } else {
                                $plantillaJugador = '';
                                //Log::channel('mi_log')->info('OJO!!! No hay plantilla del equipo ' . $strEquipo, []);
                                $error .= 'No hay plantilla del equipo ' . $strEquipo . '<br>';
                                $ok = 0;
                            }
                            if (!empty($plantillaJugador)) {
                                $jugador_id = $plantillaJugador->jugador->id;
                                $partesNombre = explode(' ', $jugador['nombre']);
                                $mismoDorsal = 0;
                                // Realizar la consulta buscando coincidencias en nombre y apellido
                                $consultarJugador = Jugador::join('personas', 'personas.id', '=', 'jugadors.persona_id')
                                    ->where('jugadors.id', '=', $jugador_id)
                                    ->where(function ($query) use ($partesNombre) {
                                        foreach ($partesNombre as $parte) {
                                            $query->where(function ($query) use ($parte) {
                                                $query->where('personas.nombre', 'LIKE', "%$parte%")
                                                    ->orWhere('personas.apellido', 'LIKE', "%$parte%")
                                                    ->orWhere('personas.name', 'LIKE', "%$parte%");
                                            });
                                        }
                                    })
                                    ->first();
                                if (!empty($consultarJugador)) {
                                    $mismoDorsal = 1;
                                    //Log::channel('mi_log')->info($consultarJugador['tipoJugador'], []);
                                }

                                if (!$mismoDorsal) {
                                    //Log::channel('mi_log')->info('OJO!!! con jugador: ' . $jugador['dorsal'] . ' ' . $jugador['nombre'] . ' del equipo ' . $strEquipo, []);
                                    $success .= 'Problema con jugador: ' . $jugador['dorsal'] . ' ' . $jugador['nombre'] . ' del equipo ' . $strEquipo . '<br>';
                                }
                                switch ($plantillaJugador->jugador->tipoJugador) {
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
                                    'dorsal' => $jugador['dorsal'],
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
                            } else {
                                $jugadorMostrar = (!empty($jugador['dorsal'])) ? $jugador['dorsal'] : '';
                                //Log::channel('mi_log')->info('OJO!!! NO se encontró al jugador: ' . $jugadorMostrar . ' del equipo ' . $strEquipo, []);
                                //$success .='NO se encontró al jugador: ' . $jugadorMostrar.' del equipo '.$strEquipo . '<br>';
                                $partesNombre = explode(' ', $jugador['nombre']);
                                $mismoDorsal = 0;
                                // Realizar la consulta buscando coincidencias en nombre y apellido
                                $consultarJugador = Jugador::join('personas', 'personas.id', '=', 'jugadors.persona_id')
                                    ->join('plantilla_jugadors', 'jugadors.id', '=', 'plantilla_jugadors.jugador_id')
                                    ->wherein('plantilla_jugadors.plantilla_id', explode(',', $arrplantillas))
                                    ->where(function ($query) use ($partesNombre) {
                                        foreach ($partesNombre as $parte) {
                                            $query->where(function ($query) use ($parte) {
                                                $query->where('personas.nombre', 'LIKE', "%$parte%")
                                                    ->orWhere('personas.apellido', 'LIKE', "%$parte%")
                                                    ->orWhere('personas.name', 'LIKE', "%$parte%");
                                            });
                                        }
                                    })
                                    ->first();

                                if (!empty($consultarJugador)) {
                                    $mismoDorsal = 1;
                                    //Log::channel('mi_log')->info($consultarJugador['tipoJugador'], []);
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
                                        //Log::channel('mi_log')->info('OJO!! verificar que sea correcto: ' . $consultarJugador['apellido'] . ', ' . $consultarJugador['nombre'] . ' dorsal ' . $consultarJugador['dorsal'], []);
                                        $success .= $consultarJugador['dorsal'] . ' - ' . $consultarJugador['apellido'] . ', ' . $consultarJugador['nombre'] . $sinDorsal . ' - equipo ' . $strEquipo. '<br>';

                                    } catch (QueryException $ex) {
                                        $error = $ex->getMessage();
                                        $ok = 0;
                                        continue;
                                    }
                                } else {

                                    $error .= 'NO se encontró al jugador: ' . $jugador['dorsal'] . ' ' . $jugador['nombre'] . ' del equipo ' . $strEquipo . '<br>';
                                    $ok = 0;
                                    continue;
                                }
                            }

                            foreach ($jugador['incidencias'] as $incidencia) {

                                if (!empty($incidencia)) {
                                    //Log::channel('mi_log')->info('Incidencia: ' . trim($incidencia[0]) . ' MIN: ' . intval(trim($incidencia[1])), []);
                                    //Log::channel('mi_log')->info('Incidencias Jugador: ' . $jugador['dorsal'] . ' ' . $jugador['nombre'] . ' - ' . trim($incidencia[0]) . ' MIN: ' . intval(trim($incidencia[1])), []);
                                    $tipogol = '';
                                    switch (trim($incidencia[0])) {
                                        case 'Gol':
                                            $tipogol = 'Jugada';
                                            break;
                                        case 'Penal':
                                            $tipogol = 'Penal';
                                            break;
                                        case 'Tiro libre':
                                            $tipogol = 'Tiro libre';
                                            break;
                                        case 'Cabeza':
                                            $tipogol = 'Cabeza';
                                            break;
                                        case 'Gol en propia meta':
                                            $tipogol = 'En Contra';
                                            break;

                                    }
                                    if ($tipogol) {
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
                                            if ($ex->errorInfo[1] == 1452) {
                                                // Error de integridad de clave externa
                                                // Aquí puedes agregar un mensaje de error personalizado o redirigir a una página de error
                                                // También puedes registrar el error en un archivo de registro para su análisis posterior
                                                //Log::error('Error de integridad de clave externa: ' . $e->getMessage());

                                                $error .= 'Jugador no cargado: ' . $jugador['dorsal'] . ' ' . $jugador['nombre'] . ' - ' . trim($incidencia[0]) . ' MIN: ' . intval(trim($incidencia[1])) . ' en el equipo ' . $eq['equipo'] . '<br>';;


                                            } else {
                                                // Otro tipo de error de base de datos
                                                // Puedes manejarlo de acuerdo a tus necesidades
                                                $error = $ex->getMessage();
                                            }
                                            $ok = 0;
                                            continue;
                                        }
                                        //}

                                    }
                                    $tipotarjeta = '';
                                    switch (trim($incidencia[0])) {
                                        case 'Tarjeta amarilla':
                                            $tipotarjeta = 'Amarilla';
                                            break;
                                        case 'Expulsado por doble amarilla':
                                            $tipotarjeta = 'Doble Amarilla';
                                            break;
                                        case 'Tarjeta roja':
                                            $tipotarjeta = 'Roja';
                                            break;
                                    }
                                    if ($tipotarjeta) {
                                        //if (!empty($plantillaJugador)) {
                                        $tarjeadata = array(
                                            'partido_id' => $partido->id,
                                            'jugador_id' => $jugador_id,
                                            'minuto' => intval(trim($incidencia[1])),
                                            'tipo' => $tipotarjeta
                                        );
                                        $tarjeta = Tarjeta::where('partido_id', '=', $partido->id)->where('jugador_id', '=', $jugador_id)->where('minuto', '=', intval(trim($incidencia[1])))->first();
                                        try {
                                            if (!empty($tarjeta)) {

                                                $tarjeta->update($tarjeadata);
                                            } else {
                                                $tarjeta = Tarjeta::create($tarjeadata);
                                            }


                                        } catch (QueryException $ex) {
                                            if ($ex->errorInfo[1] == 1452) {
                                                // Error de integridad de clave externa
                                                // Aquí puedes agregar un mensaje de error personalizado o redirigir a una página de error
                                                // También puedes registrar el error en un archivo de registro para su análisis posterior
                                                //Log::error('Error de integridad de clave externa: ' . $e->getMessage());

                                                $error .= 'Jugador no cargado: ' . $jugador['dorsal'] . ' ' . $jugador['nombre'] . ' - ' . trim($incidencia[0]) . ' MIN: ' . intval(trim($incidencia[1])) . ' en el equipo ' . $eq['equipo'] . '<br>';


                                            } else {
                                                // Otro tipo de error de base de datos
                                                // Puedes manejarlo de acuerdo a tus necesidades
                                                $error = $ex->getMessage();
                                            }

                                            $ok = 0;
                                            continue;
                                        }
                                        //}


                                    }
                                    $tipocambio = '';
                                    switch (trim($incidencia[0])) {
                                        case 'Sale':
                                            $tipocambio = 'Sale';
                                            break;
                                        case 'Entra':
                                            $tipocambio = 'Entra';
                                            break;

                                    }
                                    if ($tipocambio) {
                                        //if (!empty($plantillaJugador)) {
                                        $cambiodata = array(
                                            'partido_id' => $partido->id,
                                            'jugador_id' => $jugador_id,
                                            'minuto' => intval(trim($incidencia[1])),
                                            'tipo' => $tipocambio
                                        );
                                        $cambio = Cambio::where('partido_id', '=', $partido->id)->where('jugador_id', '=', $jugador_id)->where('minuto', '=', intval(trim($incidencia[1])))->first();
                                        try {
                                            if (!empty($cambio)) {

                                                $cambio->update($cambiodata);
                                            } else {
                                                $cambio = Cambio::create($cambiodata);
                                            }


                                        } catch (QueryException $ex) {
                                            if ($ex->errorInfo[1] == 1452) {
                                                // Error de integridad de clave externa
                                                // Aquí puedes agregar un mensaje de error personalizado o redirigir a una página de error
                                                // También puedes registrar el error en un archivo de registro para su análisis posterior
                                                //Log::error('Error de integridad de clave externa: ' . $e->getMessage());

                                                $error .= 'Jugador no cargado: ' . $jugador['dorsal'] . ' ' . $jugador['nombre'] . ' - ' . trim($incidencia[0]) . ' MIN: ' . intval(trim($incidencia[1])) . ' en el equipo ' . $eq['equipo'] . '<br>';


                                            } else {
                                                // Otro tipo de error de base de datos
                                                // Puedes manejarlo de acuerdo a tus necesidades
                                                $error = $ex->getMessage();
                                            }
                                            $ok = 0;
                                            continue;
                                        }
                                        //}


                                    }
                                }

                            }
                        }
                    } else {
                        //Log::channel('mi_log')->info('OJO!!! NO se encontró al equipo: ' . $strEquipo, []);
                        $ok = 0;
                        $error .= 'NO se encontró al equipo: ' . $strEquipo . '<br>';
                    }
                }


            }
        }
        else{
            //Log::channel('mi_log')->info('OJO!!! No se econtró la URL2 ' .$url2, []);
            $ok=0;
            $error .='No se econtró la URL2 ' .$url2. '<br>';
            /*
            continue;*/
        }



        //$ok=0;
        if ($ok){



            DB::commit();
            $respuestaID='success';
            $respuestaMSJ=$success;
        }
        else{
            DB::rollback();
            $respuestaID='error';
            $respuestaMSJ=$error.'<br>'.$success;
        }

        //
        return redirect()->route('fechas.show', $partido->fecha->id)->with($respuestaID,$respuestaMSJ);

        //return view('fechas.index', compact('grupo'));
    }




    public function fixture(Request $request)
    {

        if($request->query('dia')) {
            $dia = $request->query('dia');
        }
        else{
            $dia = date('Y-m-d');

        }


        $diaFormat = Carbon::createFromFormat('Y-m-d', $dia)->startOfDay();
        $partidos = Partido::whereDate('dia', $diaFormat)->orderBy('dia', 'ASC')->get();

        //dd($partidos);
        // Agrupar partidos por eliminatoria
        $partidosAgrupados = $partidos->groupBy(function ($partido) {
            return min($partido->equipol_id, $partido->equipov_id) . '-' . max($partido->equipol_id, $partido->equipov_id);
        });


        //$fechas=Fecha::select('numero')->distinct()->wherein('grupo_id',explode(',', $arrgrupos))->orderBy('numero','ASC')->get();

        // Determinar si hay partidos de ida y vuelta
        $hayIdaVuelta = $partidosAgrupados->contains(function ($partidos) {
            return $partidos->count() > 1;
        });


        // Obtener el torneo de cada grupo de partidos agrupados y agregarlo como atributo adicional
        foreach ($partidosAgrupados as $grupo) {

            $partido = $grupo->first(); // Tomamos el primer partido del grupo para obtener el torneo
            //dd($partido);
            $torneo = $partido->fecha->grupo->torneo;
            //dd($torneo);
            $grupo->torneo = $torneo;
        }
        //dd($partidosAgrupados);

        //print_r($fechas);

        return view('fechas.fixture', compact('partidos','partidosAgrupados','hayIdaVuelta','dia'));
    }

}
