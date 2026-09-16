<?php

namespace App\Services;

use App\Persona;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Árbitros que entraron con el apellido doble partido al medio.
 *
 * El JSON de `/referees` no trae shortName, así que `NombreHelper::separarTM`
 * se quedaba con "la última palabra es el apellido":
 *
 *   Leandro Carbajales Gómez  ->  nombre "Leandro Carbajales" / apellido "Gómez"
 *
 * Desde 2026-09-16 el importador lo decide mejor (ver
 * `TmDetallePartido::tokenEsApellido`), pero las fichas que ya estaban cargadas
 * quedaron así. Este servicio las encuentra con la MISMA regla y propone el
 * arreglo; no guarda nada que no se haya tildado en pantalla.
 *
 * Una diferencia con el importador: al contar en `persona_tokens` cuántas
 * personas tienen la palabra como nombre de pila, se EXCLUYE a los propios
 * candidatos. Si no, "Carbajales" cuenta como nombre justamente por la ficha
 * que queremos arreglar, y nunca se arreglaría.
 */
class ApellidosPartidos
{
    /** Misma lista que `TmDetallePartido::esHispana()`. */
    const HISPANAS = ['Argentina', 'Bolivia', 'Chile', 'Colombia', 'Costa Rica', 'Cuba',
        'Ecuador', 'El Salvador', 'España', 'Guatemala', 'Guinea Ecuatorial', 'Honduras',
        'México', 'Nicaragua', 'Panamá', 'Paraguay', 'Perú', 'Puerto Rico',
        'República Dominicana', 'Uruguay', 'Venezuela'];

    const PARTICULAS = ['de', 'da', 'do', 'dos', 'das', 'del', 'della', 'di', 'la', 'las', 'los',
        'van', 'von', 'der', 'den', 'du', 'le'];

    /**
     * Veredictos:
     *   apellido = la base lo tiene claramente como apellido       (tildado)
     *   probable = nadie en la base lo tiene como nombre de pila    (tildado)
     *   dudoso   = aparece como las dos cosas, sin mayoría clara    (sin tildar)
     *   forma    = 3+ nombres y 1 apellido: la regla del importador (sin tildar)
     *   nombre   = la base lo tiene claramente como nombre: está bien, no se lista
     *
     * @param bool $soloTm  true = sólo los árbitros atados en `arbitro_tm`
     *                      (los que cargó el importador). false = todos.
     * @return array lista de filas con la propuesta
     */
    public static function candidatos($soloTm = true, $conNombres = false)
    {
        $q = DB::table('personas')
            ->join('arbitros', 'arbitros.persona_id', '=', 'personas.id')
            ->whereIn('personas.nacionalidad', self::HISPANAS)
            ->where('personas.nombre', 'like', '% %')
            ->where('personas.apellido', '<>', '')
            ->whereNotNull('personas.apellido')
            ->select('personas.id', 'personas.name', 'personas.nombre', 'personas.apellido',
                'personas.nacionalidad', 'personas.foto', 'arbitros.id as arbitro_id');

        if ($soloTm && Schema::hasTable('arbitro_tm')) {
            $q->whereExists(function ($s) {
                $s->select(DB::raw(1))->from('arbitro_tm')
                    ->whereColumn('arbitro_tm.arbitro_id', 'arbitros.id');
            });
        }

        $filas = [];
        $tokens = [];
        foreach ($q->orderBy('personas.apellido')->orderBy('personas.nombre')->get() as $p) {
            $nombres   = self::palabras($p->nombre);
            $apellidos = self::palabras($p->apellido);
            if (count($apellidos) !== 1 || count($nombres) < 2) continue;

            // Palabra que pasaría al apellido, arrastrando partículas ("… de la Cruz").
            $resto = $nombres;
            $mover = [array_pop($resto)];
            if (in_array(mb_strtolower($mover[0]), self::PARTICULAS, true)) continue;
            while (count($resto) >= 2 && in_array(mb_strtolower(end($resto)), self::PARTICULAS, true)) {
                array_unshift($mover, array_pop($resto));
            }

            $nuevoApellido = implode(' ', $mover) . ' ' . $apellidos[0];
            $tok = DuplicadosPersonas::tokenizar($mover[count($mover) - 1]);

            $fila = [
                'id'             => (int) $p->id,
                'arbitro_id'     => (int) $p->arbitro_id,
                'name'           => $p->name,
                'nombre'         => $p->nombre,
                'apellido'       => $p->apellido,
                'nacionalidad'   => $p->nacionalidad,
                'foto'           => $p->foto,
                'nuevo_nombre'   => implode(' ', $resto),
                'nuevo_apellido' => $nuevoApellido,
                'nuevo_name'     => NombreHelper::completarApellido($p->name, $nuevoApellido),
                'palabra'        => $mover[count($mover) - 1],
                'token'          => $tok ? $tok[0] : '',
                'como_apellido'  => 0,
                'como_nombre'    => 0,
                'veredicto'      => count($nombres) >= 3 ? 'forma' : null,
            ];
            if ($fila['veredicto'] === null && $fila['token'] !== '') {
                $tokens[$fila['token']] = true;
            }
            $filas[] = $fila;
        }

        // Conteos en el índice, sin contar a los propios candidatos.
        $ids = array_column($filas, 'id');
        $conteo = [];
        foreach (array_chunk(array_keys($tokens), 300) as $trozo) {
            $r = DB::table('persona_tokens')
                ->select('token', 'campo', DB::raw('COUNT(DISTINCT persona_id) as n'))
                ->whereIn('token', $trozo)
                ->whereIn('campo', ['a', 'n'])
                ->whereNotIn('persona_id', $ids)
                ->groupBy('token', 'campo')
                ->get();
            foreach ($r as $c) {
                $conteo[$c->token][$c->campo] = (int) $c->n;
            }
        }

        $salida = [];
        foreach ($filas as $f) {
            if ($f['veredicto'] === null) {
                $a = isset($conteo[$f['token']]['a']) ? $conteo[$f['token']]['a'] : 0;
                $n = isset($conteo[$f['token']]['n']) ? $conteo[$f['token']]['n'] : 0;
                $f['como_apellido'] = $a;
                $f['como_nombre']   = $n;

                if ($f['token'] === '')                 $f['veredicto'] = 'dudoso';
                elseif ($a >= 3 && $a >= 2 * $n)        $f['veredicto'] = 'apellido';
                elseif ($n >= 3 && $n >= 2 * $a)        $f['veredicto'] = 'nombre';
                elseif ($n === 0)                       $f['veredicto'] = 'probable';
                else                                    $f['veredicto'] = 'dudoso';
            }

            if ($f['veredicto'] === 'nombre' && !$conNombres) continue;
            $f['tildado'] = in_array($f['veredicto'], ['apellido', 'probable'], true);
            $salida[] = $f;
        }

        return $salida;
    }

    /**
     * Aplica la propuesta a los ids tildados. Se vuelve a calcular todo acá:
     * lo que llega del formulario sólo elige, no dicta el nombre nuevo.
     * `$antes` = [id => "apellido|nombre"] tal como se vio en pantalla; si la
     * ficha cambió en el medio, se saltea.
     *
     * @return array ['ok' => n, 'salteados' => [textos]]
     */
    public static function aplicar(array $ids, array $antes)
    {
        $ids = array_flip(array_map('intval', $ids));
        $ok = 0;
        $salteados = [];

        foreach (self::candidatos(false, true) as $f) {
            if (!isset($ids[$f['id']])) continue;

            $visto = isset($antes[$f['id']]) ? $antes[$f['id']] : null;
            if ($visto !== $f['apellido'] . '|' . $f['nombre']) {
                $salteados[] = $f['nombre'] . ' ' . $f['apellido'] . ' (la ficha cambió desde que cargaste la página)';
                continue;
            }

            try {
                $persona = Persona::find($f['id']);
                if (!$persona) continue;
                $persona->nombre   = $f['nuevo_nombre'];
                $persona->apellido = $f['nuevo_apellido'];
                if ($f['nuevo_name'] !== '') $persona->name = $f['nuevo_name'];
                $persona->save(); // el evento saved reindexa persona_tokens
                $ok++;
            } catch (\Exception $e) {
                $salteados[] = $f['nombre'] . ' ' . $f['apellido'] . ': ' . $e->getMessage();
            }
            unset($ids[$f['id']]);
        }

        // Los que quedaron ya no son candidatos (se arreglaron a mano, o cambió la forma).
        foreach (array_keys($ids) as $id) {
            $salteados[] = 'Persona ' . $id . ' (ya no tiene la forma a corregir)';
        }

        return ['ok' => $ok, 'salteados' => $salteados];
    }

    private static function palabras($texto)
    {
        return array_values(array_filter(preg_split('/\s+/u', trim((string) $texto)), 'strlen'));
    }
}
