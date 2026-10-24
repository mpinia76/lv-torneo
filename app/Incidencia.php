<?php

namespace App;

use Illuminate\Database\Eloquent\Model;


class Incidencia extends Model
{
    protected $fillable = ['partido_id', 'puntos', 'equipo_id','torneo_id','tipo','observaciones','observaciones_en'];

    /**
     * Los tipos de incidencia, agrupados como se muestran en el formulario.
     *
     * Hasta el 24/10/2026 la incidencia era sólo texto, y cualquier incidencia
     * sacaba al partido de TODOS los controles de carga: marcar "terna
     * incompleta" escondía también los goles que faltaban. Ahora cada
     * incidencia dice qué explica, y sólo tapa los controles de eso (ver
     * `TAPA`).
     *
     * Las notas de carga usan como tipo la MISMA clave del control que las
     * genera (`Controles::definiciones()`), así el botón "no se puede arreglar"
     * de cada control escribe su propio tipo sin traducir nada.
     *
     * `tipo` en NULL es una incidencia vieja todavía sin clasificar: se la
     * trata como antes, tapando todos los controles.
     */
    public const TIPOS = [
        'Hechos del partido' => [
            'escritorio'     => 'Resultado de escritorio (se jugó, pero el resultado oficial es otro)',
            'no_jugado'      => 'No se jugó (con resultado dado o sin resultado)',
            'sancion_puntos' => 'Sanción de puntos',
            'otra'           => 'Otra (suspendido y continuado, error del árbitro, contexto)',
        ],
        'Datos que no están en la fuente' => [
            'sin_detalle'                => 'Sin detalle del partido',
            'alineaciones.sin_jugadores' => 'Sin alineación de algún equipo',
            'alineaciones.faltan'        => 'Alineación incompleta',
            'tecnicos.faltan'            => 'Sin técnico de algún equipo',
            'arbitros.terna'             => 'Terna incompleta',
            'goles.diferencia'           => 'Faltan goles (el detalle no da el resultado)',
            'goles.por_equipo'           => 'No se sabe qué equipo hizo cada gol',
            'goles.sin_jugar'            => 'El goleador no figura en la alineación',
            'tarjetas.sin_jugar'         => 'El amonestado no figura en la alineación',
            'cambios.sin_jugar'          => 'El jugador del cambio no figura en la alineación',
            'cambios.impares'            => 'Cambio sin contraparte (entra y no se sabe quién sale)',
            'cambios.sale_solo'          => 'Salida sin reemplazo (dato correcto)',
            'penales.faltantes'          => 'Sin registro del arquero que recibió el penal',
        ],
    ];

    /**
     * Qué controles de carga deja de mirar un partido con una incidencia de
     * cada tipo. '*' = todos. Un tipo que no está acá no tapa ninguno
     * (`sancion_puntos`, `otra`).
     *
     * Las de alineación tapan además lo que es consecuencia directa: si falta
     * la alineación de un equipo, que su goleador "no figure en la alineación"
     * no es un error nuevo, es el mismo.
     *
     * `escritorio` tapa sólo los de goles contra el resultado: el descuadre es
     * esperable (Celta-Estrella Roja se jugó 5-3 y quedó 3-0). Todo lo demás
     * del detalle se sigue controlando, porque el partido se jugó.
     */
    public const TAPA = [
        'no_jugado'   => ['*'],
        'sin_detalle' => ['*'],
        'escritorio'  => ['goles.diferencia', 'goles.por_equipo'],

        'alineaciones.sin_jugadores' => [
            'alineaciones.sin_jugadores', 'alineaciones.faltan', 'tecnicos.faltan',
            'goles.sin_jugar', 'goles.diferencia', 'goles.por_equipo',
            'tarjetas.sin_jugar',
            'cambios.sin_jugar', 'cambios.impares', 'cambios.sale_solo', 'cambios.titulares_entran',
            'penales.faltantes', 'penales.mal_cargados',
        ],
        'alineaciones.faltan' => [
            'alineaciones.faltan',
            'goles.sin_jugar', 'tarjetas.sin_jugar',
            'cambios.sin_jugar', 'cambios.titulares_entran',
            // `penales.faltantes` NO: ahí también caen los penales que recibió
            // un jugador de campo (arquero expulsado), que se cargan a mano.
            // Una alineación incompleta no tiene que esconderlos; el que de
            // verdad no tiene arquero se marca con su propio botón.
            'penales.mal_cargados',
        ],
        'goles.diferencia'   => ['goles.diferencia', 'goles.por_equipo'],
        'goles.por_equipo'   => ['goles.por_equipo'],
        'goles.sin_jugar'    => ['goles.sin_jugar'],
        'tarjetas.sin_jugar' => ['tarjetas.sin_jugar'],
        'cambios.sin_jugar'  => ['cambios.sin_jugar'],
        'cambios.impares'    => ['cambios.impares'],
        'cambios.sale_solo'  => ['cambios.sale_solo'],
        'arbitros.terna'     => ['arbitros.terna'],
        'tecnicos.faltan'    => ['tecnicos.faltan'],
        'penales.faltantes'  => ['penales.faltantes', 'penales.mal_cargados'],
    ];

    /**
     * Los tipos que anulan los goles del partido para las estadísticas
     * (goleadores): el resultado oficial no es el que se jugó.
     */
    public const ANULAN_GOLES = ['escritorio', 'no_jugado'];

    /** Los tipos que tapan alguno de estos controles. */
    public static function tiposQueTapan(array $claves): array
    {
        $tipos = [];
        foreach (self::TAPA as $tipo => $tapa) {
            if (in_array('*', $tapa, true) || array_intersect($claves, $tapa)) {
                $tipos[] = $tipo;
            }
        }
        return $tipos;
    }

    /** Lista plana tipo => etiqueta, para validar y para mostrar. */
    public static function etiquetas(): array
    {
        $plano = [];
        foreach (self::TIPOS as $grupo) {
            $plano += $grupo;
        }
        return $plano;
    }

    /** La etiqueta del tipo, o "Sin clasificar" si es una incidencia vieja. */
    public function getTipoEtiquetaAttribute()
    {
        if ($this->tipo === null || $this->tipo === '') return 'Sin clasificar';
        return self::etiquetas()[$this->tipo] ?? $this->tipo;
    }

    /**
     * La observación en el idioma del sitio: en /en usa `observaciones_en`
     * y, si está vacía, cae al castellano. Uso: $incidencia->observacion_idioma
     */
    public function getObservacionIdiomaAttribute()
    {
        if (app()->getLocale() === 'en' && trim((string) $this->observaciones_en) !== '') {
            return $this->observaciones_en;
        }
        return $this->observaciones;
    }

    public function equipo()
    {
        return $this->belongsTo(Equipo::class, 'equipo_id'); // Donde 'equipo_id' es la columna de clave foránea
    }

    public function torneo()
    {
        return $this->belongsTo(
            Torneo::class, 'torneo_id');
    }

    public function partido()
    {
        return $this->belongsTo(Partido::class, 'partido_id');
    }


}
