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

        $nombre = (string) Str::of($nombreCompetencia)
            ->lower()
            ->ascii()
            ->replaceMatches('/\s+/', ' ')
            ->trim();

        $reglas = self::activas();

        foreach ($reglas as $regla) {
            $patron = mb_strtolower(trim($regla['patron']));
            $tipo   = $regla['tipo_match'];
            $match  = false;

            if ($tipo === 'exacto') {
                $match = ($nombre === $patron);
            } elseif ($tipo === 'regex') {
                $match = (@preg_match('/' . $regla['patron'] . '/i', $nombreCompetencia) === 1);
            } else {
                // 'contiene' por default
                $match = (strpos($nombre, $patron) !== false);
            }

            if ($match) {
                return true;
            }
        }

        return false;
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
