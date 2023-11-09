<?php

namespace App\Http\Controllers;

use App\Alineacion;
use App\Cambio;
use App\Grupo;
use App\Jugador;
use App\Partido;
use App\PartidoTecnico;
use App\Plantilla;
use App\PlantillaJugador;
use App\Tecnico;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use DB;

class AlineacionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $partido_id= $request->query('partidoId');

        $partido=Partido::findOrFail($partido_id);


        $titularesL=Alineacion::where('partido_id','=',"$partido_id")->where('equipo_id','=',$partido->equipol->id)->where('tipo','=',"Titular")->orderBy('orden', 'asc')->get();

        $suplentesL=Alineacion::where('partido_id','=',"$partido_id")->where('equipo_id','=',$partido->equipol->id)->where('tipo','=',"Suplente")->orderBy('orden', 'asc')->get();

        $titularesV=Alineacion::where('partido_id','=',"$partido_id")->where('equipo_id','=',$partido->equipov->id)->where('tipo','=',"Titular")->orderBy('orden', 'asc')->get();

        $suplentesV=Alineacion::where('partido_id','=',"$partido_id")->where('equipo_id','=',$partido->equipov->id)->where('tipo','=',"Suplente")->orderBy('orden', 'asc')->get();

        $torneo_id = $partido->fecha->grupo->torneo->id;

        $grupos = Grupo::where('torneo_id', '=',$torneo_id)->get();
        $arrgrupos='';
        foreach ($grupos as $grupo){
            $arrgrupos .=$grupo->id.',';
        }

        $plantillasL = Plantilla::wherein('grupo_id',explode(',', $arrgrupos))->where('equipo_id','=',$partido->equipol->id)->get();



        $arrplantillals='';
        foreach ($plantillasL as $plantillal){
            $arrplantillals .=$plantillal->id.',';
        }



        //$jugadorsL = PlantillaJugador::wherein('plantilla_id',explode(',', $arrplantillals))->distinct()->with('jugador')->get();

        //$jugadorsL = PlantillaJugador::SELECT('jugadors.id','personas.nombre','personas.apellido','personas.nacimiento','personas.fallecimiento','personas.foto')->Join('jugadors','plantilla_jugadors.jugador_id','=','jugadors.id')->Join('personas','personas.id','=','jugadors.persona_id')->wherein('plantilla_id',explode(',', $arrplantillals))->distinct()->get();

        $jugadorsL = Jugador::SELECT('jugadors.*',DB::raw("CONCAT(personas.apellido, ' ', personas.nombre, ' (',plantilla_jugadors.dorsal,')') as 'nombre_dorsal'"), 'personas.foto')->Join('plantilla_jugadors','plantilla_jugadors.jugador_id','=','jugadors.id')->Join('personas','personas.id','=','jugadors.persona_id')->wherein('plantilla_id',explode(',', $arrplantillals))->distinct()->get();


        //dd($jugadorsL);
            $jugadorsL = $jugadorsL->pluck('nombre_dorsal','id')->sortBy('apellido')->prepend('','');

        $plantillasV = Plantilla::wherein('grupo_id',explode(',', $arrgrupos))->where('equipo_id','=',$partido->equipov->id)->get();

        $arrplantillavs='';
        foreach ($plantillasV as $plantillav){
            $arrplantillavs .=$plantillav->id.',';
        }



        /*$jugadorsV = PlantillaJugador::wherein('plantilla_id',explode(',', $arrplantillavs))->distinct()->with('jugador')->get();

        $jugadorsV = $jugadorsV->pluck('jugador.full_name','jugador_id')->sortBy('jugador.apellido')->prepend('','');*/

        $jugadorsV = Jugador::SELECT('jugadors.*',DB::raw("CONCAT(personas.apellido, ' ', personas.nombre, ' (',plantilla_jugadors.dorsal,')') as 'nombre_dorsal'"), 'personas.foto')->Join('plantilla_jugadors','plantilla_jugadors.jugador_id','=','jugadors.id')->Join('personas','personas.id','=','jugadors.persona_id')->wherein('plantilla_id',explode(',', $arrplantillavs))->distinct()->get();

        $jugadorsV = $jugadorsV->pluck('nombre_dorsal','id')->sortBy('apellido')->prepend('','');

        $tecnicos = Tecnico::SELECT('tecnicos.*','personas.nombre','personas.apellido','personas.nacimiento','personas.fallecimiento','personas.foto')->JOIN('personas','personas.id','=','tecnicos.persona_id')->orderBy('apellido', 'asc')->orderBy('nombre', 'asc')->get();
        //dd($tecnicos);
        $tecnicos = $tecnicos->pluck('persona.full_name', 'id')->prepend('','');

        $partidoTecnicosL = PartidoTecnico::where('partido_id','=',"$partido_id")->where('equipo_id','=',$partido->equipol->id)->get();

        $partidoTecnicosV = PartidoTecnico::where('partido_id','=',"$partido_id")->where('equipo_id','=',$partido->equipov->id)->get();




        //dd($partido->fecha->grupo->torneo->nombre);
        return view('alineaciones.index', compact('titularesL','suplentesL', 'titularesV','suplentesV','partido','jugadorsL', 'jugadorsV','tecnicos','partidoTecnicosL','partidoTecnicosV'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
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

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $partido_id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $partido_id)
    {
        $partido=Partido::findOrFail($partido_id);
        DB::beginTransaction();
        $ok=1;
        $noBorrar='';
        $noBorrarTecnicos='';
        if(!empty($request->titularl))
        {
            if($request->titularl_id){
                foreach ($request->titularl_id as $id){
                    $noBorrar .=$id.',';
                }


            }

            foreach($request->titularl as $item=>$v){
                $jugador_id = $request->titularl[$item];
                $consultarJugador = Jugador::where('id', '=', $jugador_id)->first();
                switch ($consultarJugador->tipoJugador) {
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
                $data2=array(
                    'partido_id'=>$partido_id,
                    'equipo_id'=>$partido->equipol->id,
                    'jugador_id'=>$jugador_id,
                    'dorsal'=>$request->dorsaltitularl[$item],
                    'orden'=>$orden,
                    'tipo'=>'Titular'
                );
                try {
                    if (!empty($request->titularl_id[$item])){
                        $data2['id']=$request->titularl_id[$item];
                        $alineacion=Alineacion::find($request->titularl_id[$item]);
                        $alineacion->update($data2);
                    }
                    else{
                        $alineacion=Alineacion::create($data2);
                    }
                    $noBorrar .=$alineacion->id.',';
                }catch(QueryException $ex){
                    if ($ex->errorInfo[1] === 1062) {
                        if (strpos($ex->errorInfo[2], 'partido_id_equipo_id_dorsal') !== false) {
                            $consultarAlineacion=Alineacion::where('partido_id','=',"$partido_id")->where('equipo_id','=',$partido->equipol->id)->where('dorsal', '=', $request->dorsaltitularl[$item])->first();
                            $jugadorRepetido = Jugador::where('id', '=', $consultarAlineacion->jugador_id)->first();
                            $error = "El dorsal ".$request->dorsaltitularl[$item]." ya lo usa ".$jugadorRepetido->persona->apellido.", ".$jugadorRepetido->persona->nombre." en ".$partido->equipol->nombre;
                        } elseif (strpos($ex->errorInfo[2], 'partido_id_jugador_id') !== false) {
                            $error = "Jugador repetido: ".$consultarJugador->persona->apellido.", ".$consultarJugador->persona->nombre." dorsal ".$request->dorsaltitularl[$item];
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
        if(!empty($request->suplentel))
        {
            if($request->suplentel_id){
                foreach ($request->suplentel_id as $id){
                    $noBorrar .=$id.',';
                }

            }

            foreach($request->suplentel as $item=>$v){
                $jugador_id = $request->suplentel[$item];
                $consultarJugador = Jugador::where('id', '=', $jugador_id)->first();
                switch ($consultarJugador->tipoJugador) {
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
                $data2=array(
                    'partido_id'=>$partido_id,
                    'equipo_id'=>$partido->equipol->id,
                    'jugador_id'=>$jugador_id,
                    'dorsal'=>$request->dorsalsuplentel[$item],
                    'orden'=>$orden,
                    'tipo'=>'Suplente'
                );
                try {
                    if (!empty($request->suplentel_id[$item])){
                        $data2['id']=$request->suplentel_id[$item];
                        $alineacion=Alineacion::find($request->suplentel_id[$item]);
                        $alineacion->update($data2);
                    }
                    else{
                        $alineacion=Alineacion::create($data2);
                    }
                    $noBorrar .=$alineacion->id.',';
                }catch(QueryException $ex){
                    if ($ex->errorInfo[1] === 1062) {
                        if (strpos($ex->errorInfo[2], 'partido_id_equipo_id_dorsal') !== false) {
                        $consultarAlineacion=Alineacion::where('partido_id','=',"$partido_id")->where('equipo_id','=',$partido->equipol->id)->where('dorsal', '=', $request->dorsalsuplentel[$item])->first();
                        $jugadorRepetido = Jugador::where('id', '=', $consultarAlineacion->jugador_id)->first();
                        $error = "El dorsal ".$request->dorsalsuplentel[$item]." ya lo usa ".$jugadorRepetido->persona->apellido.", ".$jugadorRepetido->persona->nombre." en ".$partido->equipol->nombre;
                        } elseif (strpos($ex->errorInfo[2], 'partido_id_jugador_id') !== false) {
                            $error = "Jugador repetido: ".$consultarJugador->persona->apellido.", ".$consultarJugador->persona->nombre." dorsal ".$request->dorsalsuplentel[$item];
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

        if(!empty($request->tecnicoL))
            {
                if($request->partidoTecnicoL_id){
                    foreach ($request->partidoTecnicoL_id as $id){
                        $noBorrarTecnicos .=$id.',';
                    }

                }

                foreach($request->tecnicoL as $item=>$v){

                    $data2=array(
                        'partido_id'=>$partido_id,
                        'equipo_id'=>$partido->equipol->id,
                        'tecnico_id'=>$request->tecnicoL[$item]
                    );
                    try {
                        if (!empty($request->partidoTecnicoL_id[$item])){
                            $data2['id']=$request->partidoTecnicoL_id[$item];
                            $partidoTecnico=PartidoTecnico::find($request->partidoTecnicoL_id[$item]);
                            $partidoTecnico->update($data2);
                        }
                        else{
                            $partidoTecnico=PartidoTecnico::create($data2);
                        }

                        $noBorrarTecnicos .=$partidoTecnico->id.',';

                    }catch(QueryException $ex){
                        $error = $ex->getMessage();
                        $ok=0;
                        continue;
                    }
                }
            }
        if(!empty($request->titularv))
        {
            if($request->titularv_id){
                foreach ($request->titularv_id as $id){
                    $noBorrar .=$id.',';
                }

            }

            foreach($request->titularv as $item=>$v){
                $jugador_id = $request->titularv[$item];
                $consultarJugador = Jugador::where('id', '=', $jugador_id)->first();
                switch ($consultarJugador->tipoJugador) {
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
                $data2=array(
                    'partido_id'=>$partido_id,
                    'equipo_id'=>$partido->equipov->id,
                    'jugador_id'=>$jugador_id,
                    'dorsal'=>$request->dorsaltitularv[$item],
                    'orden'=>$orden,
                    'tipo'=>'Titular'
                );
                try {
                    if (!empty($request->titularv_id[$item])){
                        $data2['id']=$request->titularv_id[$item];
                        $alineacion=Alineacion::find($request->titularv_id[$item]);
                        $alineacion->update($data2);
                    }
                    else{
                        $alineacion=Alineacion::create($data2);
                    }
                    $noBorrar .=$alineacion->id.',';
                }catch(QueryException $ex){
                    if ($ex->errorInfo[1] === 1062) {
                        if (strpos($ex->errorInfo[2], 'partido_id_equipo_id_dorsal') !== false) {
                            $consultarAlineacion=Alineacion::where('partido_id','=',"$partido_id")->where('equipo_id','=',$partido->equipov->id)->where('dorsal', '=', $request->dorsaltitularv[$item])->first();
                            $jugadorRepetido = Jugador::where('id', '=', $consultarAlineacion->jugador_id)->first();
                            $error = "El dorsal ".$request->dorsaltitularv[$item]." ya lo usa ".$jugadorRepetido->persona->apellido.", ".$jugadorRepetido->persona->nombre." en ".$partido->equipol->nombre;
                        } elseif (strpos($ex->errorInfo[2], 'partido_id_jugador_id') !== false) {
                            $error = "Jugador repetido: ".$consultarJugador->persona->apellido.", ".$consultarJugador->persona->nombre." dorsal ".$request->dorsaltitularv[$item];
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
        if(!empty($request->suplentev))
        {
            if($request->suplentev_id){
                foreach ($request->suplentev_id as $id){
                    $noBorrar .=$id.',';
                }

            }

            foreach($request->suplentev as $item=>$v){
                $jugador_id = $request->suplentev[$item];
                $consultarJugador = Jugador::where('id', '=', $jugador_id)->first();
                switch ($consultarJugador->tipoJugador) {
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
                $data2=array(
                    'partido_id'=>$partido_id,
                    'equipo_id'=>$partido->equipov->id,
                    'jugador_id'=>$jugador_id,
                    'dorsal'=>$request->dorsalsuplentev[$item],
                    'orden'=>$orden,
                    'tipo'=>'Suplente'
                );
                try {
                    if (!empty($request->suplentev_id[$item])){
                        $data2['id']=$request->suplentev_id[$item];
                        $alineacion=Alineacion::find($request->suplentev_id[$item]);
                        $alineacion->update($data2);
                    }
                    else{
                        $alineacion=Alineacion::create($data2);
                    }
                    $noBorrar .=$alineacion->id.',';
                }catch(QueryException $ex){
                    if ($ex->errorInfo[1] === 1062) {
                        if (strpos($ex->errorInfo[2], 'partido_id_equipo_id_dorsal') !== false) {
                            $consultarAlineacion=Alineacion::where('partido_id','=',"$partido_id")->where('equipo_id','=',$partido->equipov->id)->where('dorsal', '=', $request->dorsalsuplentev[$item])->first();
                            $jugadorRepetido = Jugador::where('id', '=', $consultarAlineacion->jugador_id)->first();
                            $error = "El dorsal ".$request->dorsalsuplentev[$item]." ya lo usa ".$jugadorRepetido->persona->apellido.", ".$jugadorRepetido->persona->nombre." en ".$partido->equipol->nombre;
                        } elseif (strpos($ex->errorInfo[2], 'partido_id_jugador_id') !== false) {
                            $error = "Jugador repetido: ".$consultarJugador->persona->apellido.", ".$consultarJugador->persona->nombre." dorsal ".$request->dorsalsuplentev[$item];
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
        if(!empty($request->tecnicoV))
        {
            if($request->partidoTecnicoV_id){
                foreach ($request->partidoTecnicoV_id as $id){
                    $noBorrarTecnicos .=$id.',';
                }

            }

            foreach($request->tecnicoV as $item=>$v){

                $data2=array(
                    'partido_id'=>$partido_id,
                    'equipo_id'=>$partido->equipov->id,
                    'tecnico_id'=>$request->tecnicoV[$item]
                );
                try {
                    if (!empty($request->partidoTecnicoV_id[$item])){
                        $data2['id']=$request->partidoTecnicoV_id[$item];
                        $partidoTecnico=PartidoTecnico::find($request->partidoTecnicoV_id[$item]);
                        $partidoTecnico->update($data2);
                    }
                    else{
                        $partidoTecnico=PartidoTecnico::create($data2);
                    }

                    $noBorrarTecnicos .=$partidoTecnico->id.',';



                }catch(QueryException $ex){
                    $error = $ex->getMessage();
                    $ok=0;
                    continue;
                }
            }
        }
        try {
            Alineacion::where('partido_id',"$partido_id")->whereNotIn('id', explode(',', $noBorrar))->delete();
            PartidoTecnico::where('partido_id',"$partido_id")->whereNotIn('id', explode(',', $noBorrarTecnicos))->delete();
        }
        catch(QueryException $ex){
            $error = $ex->getMessage();
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

        return redirect()->route('fechas.show', $partido->fecha->id)->with($respuestaID,$respuestaMSJ);
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
}
