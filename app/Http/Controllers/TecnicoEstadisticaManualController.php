<?php

namespace App\Http\Controllers;

use App\TecnicoEstadisticaManual;
use App\Tecnico;
use App\Equipo;
use App\PartidoTecnico;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use DB;

class TecnicoEstadisticaManualController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($tecnicoId)
    {

    }

    public function indexPorTecnico($tecnicoId)
    {
        $tecnico = Tecnico::findOrFail($tecnicoId);

        $stats = TecnicoEstadisticaManual::with('equipo')
            ->where('tecnico_id', $tecnicoId)
            ->paginate(20);

        return view('tecnico_estadisticas.index', compact('stats', 'tecnico'));
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function createPorTecnico($tecnicoId)
    {
        $tecnico = Tecnico::findOrFail($tecnicoId);
        $equipos = Equipo::orderBy('nombre', 'asc')->get();
        $equipos = $equipos->pluck('nombre', 'id')->prepend('','');

        return view('tecnico_estadisticas.create', compact('tecnico', 'equipos'));
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
            // Subida manual de archivo
            $image = $request->file('escudoTmp');
            $name = time() . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('/images'), $name);
            $request->merge(['torneo_logo' => $name]);

        } elseif ($request->filled('torneo_logo_guardado')) {
            $logoUrl = $request->torneo_logo_guardado;
            $proxyUrl = 'https://images.weserv.nl/?url=' . urlencode($logoUrl);

            try {
                $client = new \GuzzleHttp\Client();
                $response = $client->get($proxyUrl, [  // 👈 $proxyUrl, no $logoUrl
                    'headers' => [
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                        'Accept'     => 'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
                    ]
                ]);

                if ($response->getStatusCode() === 200) {
                    $contenido = $response->getBody()->getContents();
                    $finfo = new \finfo(FILEINFO_MIME_TYPE);
                    $mimeType = $finfo->buffer($contenido);
                    $mimeToExt = [
                        'image/png'     => 'png',
                        'image/jpeg'    => 'jpg',
                        'image/webp'    => 'webp',
                        'image/svg+xml' => 'svg',
                        'image/gif'     => 'gif',
                    ];
                    if (isset($mimeToExt[$mimeType])) {
                        $nombre = time() . '_' . uniqid() . '.' . $mimeToExt[$mimeType];
                        file_put_contents(public_path('/images/' . $nombre), $contenido);
                        $request->merge(['torneo_logo' => $nombre]);
                    }
                }
            } catch (\Exception $e) {
                \Log::error('Logo download en store: ' . $e->getMessage());
            }
        }
        DB::beginTransaction();
        $ok=1;
        try {
            $torneoNombre = $request->torneo_nombre;
            //dd($torneoNombre);
            $existeAuto = PartidoTecnico::where('tecnico_id', $request->tecnico_id)
                ->where('equipo_id', $request->equipo_id)
                ->whereHas('partido.fecha.grupo.torneo', function ($q) use ($torneoNombre) {
                    $q->whereRaw("CONCAT(nombre, ' ', year) = ?", [$torneoNombre]);
                })
                ->exists();

            TecnicoEstadisticaManual::create($request->all());
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
            $respuestaID = 'success';

            if ($existeAuto) {
                $respuestaMSJ = 'Registro creado, pero ⚠ ya existen estadísticas automáticas para ese técnico en un torneo con el mismo nombre.';

            } else {
                $respuestaMSJ = 'Registro creado satisfactoriamente';
            }
        }
        else{
            DB::rollback();
            $respuestaID='error';
            $respuestaMSJ=$error;
        }


        return redirect()->route('tecnico-estadisticas.indexPorTecnico', $request->tecnico_id)->with($respuestaID,$respuestaMSJ);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\TecnicoEstadisticaManual  $tecnicoEstadisticaManual
     * @return \Illuminate\Http\Response
     */
    public function show(TecnicoEstadisticaManual $tecnicoEstadisticaManual)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\TecnicoEstadisticaManual  $tecnicoEstadisticaManual
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $stat = TecnicoEstadisticaManual::findOrFail($id);
        $tecnico = $stat->tecnico;
        $equipos = Equipo::orderBy('nombre', 'asc')->get();
        $equipos = $equipos->pluck('nombre', 'id')->prepend('','');

        return view('tecnico_estadisticas.edit', compact('stat', 'tecnico', 'equipos'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\TecnicoEstadisticaManual  $tecnicoEstadisticaManual
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $stat = TecnicoEstadisticaManual::findOrFail($id);


        if ($files = $request->file('escudoTmp')) {
            // Subida manual de archivo
            $image = $request->file('escudoTmp');
            $name = time() . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('/images'), $name);
            $request->merge(['torneo_logo' => $name]);

        } elseif ($request->filled('torneo_logo_guardado')) {
            $logoUrl = $request->torneo_logo_guardado;

            try {
                $client = new \GuzzleHttp\Client();
                $response = $client->get($logoUrl, [
                    'headers' => [
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                        'Accept'     => 'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
                        'Referer'    => 'https://www.sofascore.com/',
                    ]
                ]);

                if ($response->getStatusCode() === 200) {
                    $contenido = $response->getBody()->getContents();
                    $finfo = new \finfo(FILEINFO_MIME_TYPE);
                    $mimeType = $finfo->buffer($contenido);
                    $mimeToExt = [
                        'image/png'     => 'png',
                        'image/jpeg'    => 'jpg',
                        'image/webp'    => 'webp',
                        'image/svg+xml' => 'svg',
                        'image/gif'     => 'gif',
                    ];
                    if (isset($mimeToExt[$mimeType])) {
                        $nombre = time() . '_' . uniqid() . '.' . $mimeToExt[$mimeType];
                        file_put_contents(public_path('/images/' . $nombre), $contenido);
                        $request->merge(['torneo_logo' => $nombre]);
                    }
                }
            } catch (\Exception $e) {
                \Log::error('Logo download en store: ' . $e->getMessage());
            }
        }
        DB::beginTransaction();
        $ok=1;
        try {
            $torneoNombre = $request->torneo_nombre;
            //dd($torneoNombre);
            $existeAuto = PartidoTecnico::where('tecnico_id', $request->tecnico_id)
                ->where('equipo_id', $request->equipo_id)
                ->whereHas('partido.fecha.grupo.torneo', function ($q) use ($torneoNombre) {
                    $q->whereRaw("CONCAT(nombre, ' ', year) = ?", [$torneoNombre]);
                })
                ->exists();
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
            $respuestaID = 'success';

            if ($existeAuto) {
                $respuestaMSJ = 'Registro modificado, pero ⚠ ya existen estadísticas automáticas para ese técnico en un torneo con el mismo nombre.';

            } else {
                $respuestaMSJ = 'Registro modificado satisfactoriamente';
            }
        }
        else{
            DB::rollback();
            $respuestaID='error';
            $respuestaMSJ=$error;
        }

        return redirect()->route('tecnico-estadisticas.indexPorTecnico', $request->tecnico_id)->with($respuestaID,$respuestaMSJ);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\TecnicoEstadisticaManual  $tecnicoEstadisticaManual
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        TecnicoEstadisticaManual::destroy($id);
        return back();
    }

    // ============================================================================
//  Add this method to TecnicoEstadisticaManualController.
//  Reads multipart FormData (torneos[i][campo] + torneos[i][logo_file]),
//  scopes everything to tecnico_id, skips duplicates (1062) without aborting
//  the batch, flags rows that already have automatic stats (PartidoTecnico),
//  and reports the per-row outcome so the UI removes only the saved rows.
// ============================================================================

    /**
     * Store several scraped tournaments at once (from a single scrape).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeMasivo(Request $request)
    {
        $tecnicoId = $request->input('tecnico_id');
        $torneos   = $request->input('torneos', []);

        // Whitelist of stat fields we accept from the client payload.
        // Aligned with the model $fillable. torneo_logo is handled separately (file upload).
        $campos = [
            'equipo_id', 'torneo_nombre', 'tipo', 'ambito',
            'posicion', 'partidos',
            'ganados', 'empatados', 'perdidos',
            'goles_favor', 'goles_en_contra',
        ];

        $guardados = 0;
        $salteados = 0;
        $conAuto   = 0;
        $errores   = [];
        // Per-row outcome keyed by the client index, so the UI removes only saved cards.
        $resultados = [];

        foreach ($torneos as $index => $torneo) {
            // Always scope to the current coach. Never trust a tecnico_id coming inside the row.
            $data = ['tecnico_id' => $tecnicoId];

            foreach ($campos as $campo) {
                $data[$campo] = $torneo[$campo] ?? null;
            }

            // Skip empty/garbage rows (no team and no tournament name).
            if (empty($data['equipo_id']) && empty($data['torneo_nombre'])) {
                $salteados++;
                $resultados[] = ['i' => $index, 'ok' => false, 'motivo' => 'vacio'];
                continue;
            }

            // Per-row logo. Priority: an uploaded file wins; otherwise fall back to
            // the preloaded logo name that came from the scraper map (torneo_logo).
            $data['torneo_logo'] = null;
            $logoFile = $request->file("torneos.$index.logo_file");

            if ($logoFile) {
                // time()_uniqid() avoids collisions when several logos are saved in the same second.
                $name = time() . '_' . uniqid() . '.' . $logoFile->getClientOriginalExtension();
                $logoFile->move(public_path('/images'), $name);
                $data['torneo_logo'] = $name;
            } elseif (!empty($torneo['torneo_logo'])) {
                // Preloaded logo (already a file living in public/images) — no upload needed.
                $data['torneo_logo'] = $torneo['torneo_logo'];
            }

            try {
                TecnicoEstadisticaManual::create($data);
                $guardados++;

                // Warn (do not block) if automatic stats already exist for this coach/team/tournament.
                $torneoNombre = $data['torneo_nombre'];
                $existeAuto = PartidoTecnico::where('tecnico_id', $tecnicoId)
                    ->where('equipo_id', $data['equipo_id'])
                    ->whereHas('partido.fecha.grupo.torneo', function ($q) use ($torneoNombre) {
                        $q->whereRaw("CONCAT(nombre, ' ', year) = ?", [$torneoNombre]);
                    })
                    ->exists();

                if ($existeAuto) {
                    $conAuto++;
                }

                $resultados[] = ['i' => $index, 'ok' => true, 'auto' => $existeAuto];
            } catch (QueryException $ex) {
                $errorCode = $ex->errorInfo[1] ?? null;

                if ($errorCode == 1062) {
                    // Duplicate for this coach/team/tournament: skip, keep going.
                    $salteados++;
                    $resultados[] = ['i' => $index, 'ok' => false, 'motivo' => 'duplicado'];
                } else {
                    $salteados++;
                    $errores[] = ($data['torneo_nombre'] ?? '?') . ': ' . $ex->getMessage();
                    $resultados[] = ['i' => $index, 'ok' => false, 'motivo' => 'error'];
                }
            }
        }

        return response()->json([
            'ok'         => true,
            'guardados'  => $guardados,
            'salteados'  => $salteados,
            'con_auto'   => $conAuto,
            'errores'    => $errores,
            'resultados' => $resultados,
        ]);
    }
}
