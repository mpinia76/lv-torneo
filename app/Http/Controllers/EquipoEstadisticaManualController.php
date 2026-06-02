<?php

namespace App\Http\Controllers;

use App\EquipoEstadisticaManual;
use App\Equipo;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use DB;

class EquipoEstadisticaManualController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($EquipoId)
    {

    }

    public function indexPorEquipo($equipoId)
    {
        $equipo = Equipo::findOrFail($equipoId);

        $stats = EquipoEstadisticaManual::with('equipo')
            ->where('equipo_id', $equipoId)
            ->paginate(20);

        return view('equipo_estadisticas.index', compact('stats', 'equipo'));
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function createPorEquipo($equipoId)
    {
        $equipo = Equipo::findOrFail($equipoId);



        return view('equipo_estadisticas.create', compact('equipo') );
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if ($files = $request->file('escudoTmp')) {
            $image = $request->file('escudoTmp');
            $name = time().'.'.$image->getClientOriginalExtension();
            $destinationPath = public_path('/images');
            $image->move($destinationPath, $name);



            $request->merge(['torneo_logo' => $name]);
        }
        DB::beginTransaction();
        $ok=1;
        try {
            EquipoEstadisticaManual::create($request->all());
        }catch(QueryException $ex){
            //if email or phone exist before in db redirect with error messages

            $ok=0;
            $errorCode = $ex->errorInfo[1];

            if ($errorCode == 1062) {
                $error='Ya tiene estadísticas para ese equipo en ese torneo';
            }
            else{
                $error = $ex->getMessage();
            }
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


        return redirect()->route('equipo-estadisticas.indexPorEquipo', $request->equipo_id)->with($respuestaID,$respuestaMSJ);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\EquipoEstadisticaManual  $equipoEstadisticaManual
     * @return \Illuminate\Http\Response
     */
    public function show(EquipoEstadisticaManual $equipoEstadisticaManual)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\EquipoEstadisticaManual  $equipoEstadisticaManual
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $stat = EquipoEstadisticaManual::findOrFail($id);
        $equipo = $stat->equipo;


        return view('equipo_estadisticas.edit', compact('stat', 'equipo'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\EquipoEstadisticaManual  $equipoEstadisticaManual
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $stat = EquipoEstadisticaManual::findOrFail($id);


        if ($files = $request->file('escudoTmp')) {
            $image = $request->file('escudoTmp');
            $name = time().'.'.$image->getClientOriginalExtension();
            $destinationPath = public_path('/images');
            $image->move($destinationPath, $name);



            $request->merge(['torneo_logo' => $name]);
        }
        DB::beginTransaction();
        $ok=1;
        try {
            $stat->update($request->all());
        }catch(QueryException $ex){
            //if email or phone exist before in db redirect with error messages

            $ok=0;
            $errorCode = $ex->errorInfo[1];

            if ($errorCode == 1062) {
                $error='Ya tiene estadísticas para ese equipo en ese torneo';
            }
            else{
                $error = $ex->getMessage();
            }
        }
        if ($ok){
            DB::commit();
            $respuestaID='success';
            $respuestaMSJ='Registro modificado satisfactoriamente';
        }
        else{
            DB::rollback();
            $respuestaID='error';
            $respuestaMSJ=$error;
        }

        return redirect()->route('equipo-estadisticas.indexPorEquipo', $request->equipo_id)->with($respuestaID,$respuestaMSJ);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\EquipoEstadisticaManual  $equipoEstadisticaManual
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        EquipoEstadisticaManual::destroy($id);
        return back();
    }

    public function storeMasivo(Request $request)
    {
        $equipoId = $request->equipo_id;
        $torneos  = $request->input('torneos', []);

        $guardados = 0; $salteados = 0; $conAuto = 0;
        $errores = []; $resultados = [];

        // Manual tournaments already loaded for this team (duplicate guard).
        $existentes = EquipoEstadisticaManual::where('equipo_id', $equipoId)
            ->pluck('torneo_nombre')
            ->map(function ($v) {
                return (string) \Str::of($v)->lower()->ascii()->replaceMatches('/\s+/', ' ')->trim();
            })
            ->flip()->toArray();

        // Automatic tournaments -> only warn, never block.
        $automaticos = \App\Torneo::all()
            ->map(function ($t) {
                return (string) \Str::of(($t->nombre ?? '') . ' ' . ($t->year ?? ''))
                    ->lower()->ascii()->replaceMatches('/\s+/', ' ')->trim();
            })
            ->filter()->flip()->toArray();

        foreach ($torneos as $i => $t) {

            $torneoNombre = trim($t['torneo_nombre'] ?? '');
            if ($torneoNombre === '') {
                $resultados[] = ['i' => $i, 'ok' => false, 'motivo' => 'sin_nombre'];
                $salteados++;
                continue;
            }

            $key = (string) \Str::of($torneoNombre)->lower()->ascii()
                ->replaceMatches('/\s+/', ' ')->trim();

            if (isset($existentes[$key])) {
                $resultados[] = ['i' => $i, 'ok' => false, 'motivo' => 'duplicado'];
                $salteados++;
                continue;
            }

            // Per-row logo. Priority: uploaded file wins; otherwise use the preloaded
            // logo name that came from the scraper map (torneo_logo).
            $logoName = null;
            $fileKey = "torneos.{$i}.logo_file";
            if ($request->hasFile($fileKey)) {
                $image = $request->file($fileKey);
                $logoName = time() . '_' . $i . '.' . $image->getClientOriginalExtension();
                $image->move(public_path('/images'), $logoName);
            } elseif (!empty($t['torneo_logo'])) {
                // Preloaded logo (already a file in public/images) — no upload needed.
                $logoName = $t['torneo_logo'];
            }


            try {
                EquipoEstadisticaManual::create([
                    'equipo_id'       => $equipoId,
                    'torneo_nombre'   => $torneoNombre,
                    'torneo_logo'     => $logoName,
                    'tipo'            => $t['tipo'] ?? null,
                    'ambito'          => $t['ambito'] ?? null,
                    'posicion'        => $t['posicion'] ?? null,
                    'partidos'        => $t['partidos'] ?? 0,
                    'ganados'         => $t['ganados'] ?? 0,
                    'empatados'       => $t['empatados'] ?? 0,
                    'perdidos'        => $t['perdidos'] ?? 0,
                    'goles_favor'     => $t['goles_favor'] ?? 0,
                    'goles_en_contra' => $t['goles_en_contra'] ?? 0,
                ]);

                $existentes[$key] = true; // avoid in-batch duplicates
                if (isset($automaticos[$key])) $conAuto++;

                $resultados[] = ['i' => $i, 'ok' => true];
                $guardados++;

            } catch (\Illuminate\Database\QueryException $ex) {
                if (($ex->errorInfo[1] ?? null) == 1062) {
                    $resultados[] = ['i' => $i, 'ok' => false, 'motivo' => 'duplicado'];
                    $salteados++;
                } else {
                    $resultados[] = ['i' => $i, 'ok' => false, 'motivo' => 'error'];
                    $errores[] = "Fila {$i}: " . $ex->getMessage();
                }
            }
        }

        return response()->json([
            'guardados'  => $guardados,
            'salteados'  => $salteados,
            'con_auto'   => $conAuto,
            'errores'    => $errores,
            'resultados' => $resultados,
        ]);
    }
}
