<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CompetenciaExcluida extends Model
{
    protected $table = 'competencias_excluidas';

    protected $fillable = ['patron', 'tipo_match', 'motivo', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
    ];

    const CACHE_KEY = 'competencias_excluidas_activas';

    /**
     * Returns all active exclusion patterns, cached for 10 minutes.
     */
    public static function activas()
    {
        return Cache::remember(self::CACHE_KEY, 600, function () {
            return static::where('activo', true)
                ->get(['patron', 'tipo_match'])
                ->toArray();
        });
    }

    /**
     * Checks whether a competition name matches any active exclusion pattern.
     */
    public static function debeExcluir($nombreCompetencia)
    {
        if (empty($nombreCompetencia)) {
            return false;
        }

        foreach (self::activas() as $regla) {
            if (self::matcheaPatron($nombreCompetencia, $regla['patron'], $regla['tipo_match'])) {
                return true;
            }
        }

        return false;
    }

    /** Minusculas, sin acentos y con los espacios colapsados. */
    public static function normalizarNombre($s)
    {
        return (string) Str::of((string) $s)->lower()->ascii()->replaceMatches('/\s+/', ' ')->trim();
    }

    /**
     * Unico lugar donde se decide si un nombre matchea un patron, para que el
     * scraper viejo, el ABM y el sondeo de partidos usen el mismo criterio.
     *
     * OJO con 'contiene': es por PALABRA COMPLETA, no por substring. El patron
     * «uefa euro» (guardado al excluir la Eurocopa desde el sondeo) estaba
     * adentro de «uefa europa league» con strpos(), asi que la regla del Euro
     * se comia tambien a la Europa League y a su fase previa. Mismo criterio
     * que NivelCompetencia::contiene() para las listas automaticas.
     *
     * El ano sigue entrando: «uefa euro» matchea «uefa euro 2024», porque
     * despues de "euro" hay un espacio.
     */
    public static function matcheaPatron($nombre, $patron, $tipo = 'contiene')
    {
        $n = self::normalizarNombre($nombre);
        $p = self::normalizarNombre($patron);

        if ($n === '' || $p === '') {
            return false;
        }

        if ($tipo === 'exacto') {
            return $n === $p;
        }

        if ($tipo === 'regex') {
            return @preg_match('/' . $patron . '/i', $nombre) === 1;
        }

        // 'contiene' por default, por palabra completa.
        return preg_match('/(?<![a-z0-9])' . preg_quote($p, '/') . '(?![a-z0-9])/', $n) === 1;
    }

    /**
     * Bust the cache when records change.
     */
    protected static function booted()
    {
        static::saved(function ($model) {
            Cache::forget(self::CACHE_KEY);
        });

        static::deleted(function ($model) {
            Cache::forget(self::CACHE_KEY);
        });
    }
}
