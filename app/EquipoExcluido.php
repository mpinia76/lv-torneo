<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class EquipoExcluido extends Model
{
    protected $table = 'equipos_excluidos';
    protected $fillable = ['nombre'];

    public static function debeExcluir($nombre)
    {
        $nombreNorm = self::normalizar($nombre);

        return self::all()->contains(function ($e) use ($nombreNorm) {
            $excluidoNorm = self::normalizar($e->nombre);
            return str_contains($nombreNorm, $excluidoNorm)
                || str_contains($excluidoNorm, $nombreNorm);
        });
    }

    private static function normalizar($txt)
    {
        $txt = mb_strtolower($txt);
        $txt = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $txt);
        $txt = preg_replace('/\b(fc|cf|club|de|la|el|ca|aa)\b/', ' ', $txt);
        $txt = preg_replace('/[^a-z0-9 ]/', ' ', $txt);
        $txt = preg_replace('/\s+/', ' ', $txt);
        return trim($txt);
    }
}
