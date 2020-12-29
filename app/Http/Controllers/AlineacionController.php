<?php

namespace App\Http\Controllers;

use App\Alineacion;
use App\Cambio;
use App\Jugador;
use App\Partido;
use App\Plantilla;
use App\PlantillaJugador;
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

        $plantillaL = Plantilla::where('torneo_id','=',$torneo_id)->where('equipo_id','=',$partido->equipol->id)->first();

        $jugadorsL = PlantillaJugador::where('plantilla_id','=',$plantillaL->id)->with('jugador')->get();

        $jugadorsL = $jugadorsL->pluck('jugador.full_name','jugador_id')->sortBy('jugador.apellido')->prepend('','');

        $plantillaV = Plantilla::where('torneo_id','=',$torneo_id)->where('equipo_id','=',$partido->equipov->id)->first();


        $jugadorsV = PlantillaJugador::where('plantilla_id','=',$plantillaV->id)->with('jugador')->get();

        $jugadorsV = $jugadorsV->pluck('jugador.full_name','jugador_id')->sortBy('jugador.apellido')->prepend('','');


        //dd($partido->fecha->grupo->torneo->nombre);
        return view('alineaciones.index', compact('titularesL','suplentesL', 'titularesV','suplentesV','partido','jugadorsL', 'jugadorsV'));
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
                    $error = $ex->getMessage();
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
                    $error = $ex->getMessage();
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
                    $error = $ex->getMessage();
                    $ok=0;
                    continue;
                }


            }

        }
        try {
            Alineacion::where('partido_id',"$partido_id")->whereNotIn('id', explode(',', $noBorrar))->delete();
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
