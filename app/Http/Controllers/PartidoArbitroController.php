<?php

namespace App\Http\Controllers;

use App\Partido;
use App\PartidoArbitro;
use Illuminate\Http\Request;
use DB;

class PartidoArbitroController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
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
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $partido_id)
    {

        $partido=Partido::findOrFail($partido_id);
        $noBorrar='';
        $ok=1;
        $respuestaID='';
        $respuestaMSJ='';
        DB::beginTransaction();
        if (!empty($request->arbitro)) {
            if (count($request->arbitro) > 0) {
                if ($request->partidoarbitro_id) {
                    foreach ($request->partidoarbitro_id as $id){
                        $noBorrar .=$id.',';
                    }

                }

                foreach ($request->arbitro as $item => $v) {

                    $data2 = array(
                        'partido_id' => $partido_id,
                        'arbitro_id' => $request->arbitro[$item],

                        'tipo' => $request->tipo[$item]
                    );
                    try {
                        if (!empty($request->partidoarbitro_id[$item])) {
                            $data2['id'] = $request->partidoarbitro_id[$item];
                            $partidoarbitro = PartidoArbitro::find($request->partidoarbitro_id[$item]);
                            $partidoarbitro->update($data2);
                        } else {
                            $partidoarbitro = Partidoarbitro::create($data2);
                        }
                        $noBorrar .=$partidoarbitro->id.',';
                    }catch(QueryException $ex){
                        $error = $ex->getMessage();
                        $ok=0;
                        continue;
                    }

                }

            }
        }
        try {
            Partidoarbitro::where('partido_id',"$partido_id")->whereNotIn('id', explode(',', $noBorrar))->delete();


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
