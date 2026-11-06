<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Pasa a su equipo correcto los partidos que se cargaron con un HOMÓNIMO.
 *
 * Caso real (06/11/2026): el CA Independiente de Panamá (TM 28218) cayó por
 * nombre en Independiente #3 y la Concacaf se cargó con el de Avellaneda.
 * Mismo bug en Comunicaciones (Guatemala), CD Guadalajara (España), Zamora CF
 * (España) y Al-Arabi (Arabia Saudita).
 *
 * Antes de correrlo hay que crear el equipo correcto:
 *   /admin/import-partidos/crear-equipo?tm_id=<id TM>&reemplazar=1
 *
 * Qué partidos se mueven: los que tienen una fila de staging con ese club de
 * TM resuelto al equipo equivocado. Así no se arrastran los partidos buenos del
 * equipo (los del Independiente argentino tienen el club 1234, no el 28218).
 *
 * Plantel y posiciones van por torneo, no por partido: si el equipo bueno jugó
 * el MISMO torneo que el homónimo, se mudarían también los suyos. Por eso el
 * comando corta si encuentra ese caso, salvo con --mismo-torneo.
 *
 * Sin --aplicar sólo muestra lo que haría. Todo va en una transacción.
 *
 *   php artisan equipos:mover-homonimo 10907 279 NUEVO
 *   php artisan equipos:mover-homonimo 10907 279 NUEVO --aplicar
 */
class MoverHomonimo extends Command
{
    protected $signature = 'equipos:mover-homonimo
        {tm : id del club en Transfermarkt que quedó mal atado}
        {mal : id del equipo tuyo donde cayó por error}
        {nuevo : id del equipo correcto (ya creado)}
        {--aplicar : escribe; sin esto sólo muestra}
        {--mismo-torneo : seguir aunque el equipo bueno juegue alguno de esos torneos}';

    protected $description = 'Muda los partidos cargados con un homónimo al equipo correcto';

    public function handle()
    {
        $tm    = (string) $this->argument('tm');
        $mal   = (int) $this->argument('mal');
        $nuevo = (int) $this->argument('nuevo');

        if ($mal === $nuevo) {
            $this->error('El equipo equivocado y el nuevo son el mismo.');
            return 1;
        }
        foreach ([$mal, $nuevo] as $id) {
            if (!DB::table('equipos')->where('id', $id)->exists()) {
                $this->error("No existe el equipo #$id.");
                return 1;
            }
        }
        $this->line('Mal:   #' . $mal . ' ' . DB::table('equipos')->where('id', $mal)->value('nombre'));
        $this->line('Nuevo: #' . $nuevo . ' ' . DB::table('equipos')->where('id', $nuevo)->value('nombre'));

        $partidos = DB::table('import_partidos as i')
            ->join('partidos as p', 'p.id', '=', 'i.partido_id')
            ->join('fechas as f', 'f.id', '=', 'p.fecha_id')
            ->join('grupos as g', 'g.id', '=', 'f.grupo_id')
            ->join('torneos as t', 't.id', '=', 'g.torneo_id')
            ->where(function ($q) use ($tm, $mal) {
                $q->where(function ($w) use ($tm, $mal) {
                    $w->where('i.club_external_id', $tm)->where('i.equipo_id', $mal);
                })->orWhere(function ($w) use ($tm, $mal) {
                    $w->where('i.rival_external_id', $tm)->where('i.rival_id', $mal);
                });
            })
            ->where(function ($q) use ($mal) {
                $q->where('p.equipol_id', $mal)->orWhere('p.equipov_id', $mal);
            })
            ->select('p.id', 'p.dia', 'p.equipol_id', 'p.equipov_id', 'f.grupo_id', 'g.torneo_id',
                't.nombre as torneo', 't.year')
            ->distinct()->orderBy('p.dia')->get();

        if ($partidos->isEmpty()) {
            $this->warn('No hay partidos del equipo #' . $mal . ' cargados con el club de TM ' . $tm . '.');
        } else {
            $this->table(['partido', 'día', 'local', 'visitante', 'torneo'], $partidos->map(function ($p) {
                return [$p->id, $p->dia, $p->equipol_id, $p->equipov_id, $p->torneo . ' ' . $p->year];
            })->all());
        }

        $ids     = $partidos->pluck('id')->all();
        $grupos  = $partidos->pluck('grupo_id')->unique()->values()->all();
        $torneos = $partidos->pluck('torneo_id')->unique()->values()->all();

        // ¿El equipo bueno jugó alguno de esos torneos con partidos que NO se mueven?
        $propios = $torneos ? DB::table('partidos as p')
            ->join('fechas as f', 'f.id', '=', 'p.fecha_id')
            ->join('grupos as g', 'g.id', '=', 'f.grupo_id')
            ->whereIn('g.torneo_id', $torneos)
            ->whereNotIn('p.id', $ids)
            ->where(function ($q) use ($mal) {
                $q->where('p.equipol_id', $mal)->orWhere('p.equipov_id', $mal);
            })->count() : 0;

        if ($propios && !$this->option('mismo-torneo')) {
            $this->error("El equipo #$mal tiene $propios partido(s) propios en esos torneos: moverle el plantel "
                . 'y la posición por torneo se llevaría también los suyos. Revisalo a mano, o seguí con '
                . '--mismo-torneo sabiendo eso.');
            return 1;
        }

        $pasos = [
            ['partidos (local)', 'partidos', 'equipol_id', 'id', $ids],
            ['partidos (visitante)', 'partidos', 'equipov_id', 'id', $ids],
            ['alineaciones', 'alineacions', 'equipo_id', 'partido_id', $ids],
            ['técnicos', 'partido_tecnicos', 'equipo_id', 'partido_id', $ids],
            ['incidencias', 'incidencias', 'equipo_id', 'partido_id', $ids],
            ['plantel', 'plantillas', 'equipo_id', 'grupo_id', $grupos],
            ['posiciones', 'posicion_torneos', 'equipo_id', 'torneo_id', $torneos],
        ];

        $aplicar = (bool) $this->option('aplicar');
        $filas = [];

        DB::beginTransaction();
        try {
            foreach ($pasos as [$nombre, $tabla, $col, $por, $valores]) {
                $q = DB::table($tabla)->where($col, $mal)->whereIn($por, $valores ?: [0]);
                $filas[] = [$nombre, $aplicar ? $q->update([$col => $nuevo]) : $q->count()];
            }

            $q = DB::table('import_partidos')->where('club_external_id', $tm)->where('equipo_id', $mal);
            $filas[] = ['staging (club)', $aplicar ? $q->update(['equipo_id' => $nuevo]) : $q->count()];
            $q = DB::table('import_partidos')->where('rival_external_id', $tm)->where('rival_id', $mal);
            $filas[] = ['staging (rival)', $aplicar ? $q->update(['rival_id' => $nuevo]) : $q->count()];

            // Por si «Crear desde TM» no reapuntó el mapeo.
            $q = DB::table('equipo_tm')->where('tm_club_id', $tm)->where('equipo_id', '!=', $nuevo);
            $filas[] = ['equipo_tm', $aplicar ? $q->update(['equipo_id' => $nuevo, 'origen' => 'manual',
                'updated_at' => now()]) : $q->count()];

            $aplicar ? DB::commit() : DB::rollBack();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Nada se escribió: ' . $e->getMessage());
            return 1;
        }

        $this->table(['qué', $aplicar ? 'filas movidas' : 'filas a mover'], $filas);

        if ($aplicar) {
            $this->info('Listo. Vaciá la caché de páginas: rm -rf storage/framework/cache/paginas/*');
        } else {
            $this->comment('No se escribió nada. Si la lista está bien, repetí con --aplicar.');
        }
        return 0;
    }
}
