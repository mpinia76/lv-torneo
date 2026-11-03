<?php

namespace App;

use App\Services\MenuTorneos;
use Illuminate\Database\Eloquent\Model;

class Torneo extends Model
{
    /**
     * Los menús públicos se arman con una lista de torneos cacheada
     * (ver App\Services\MenuTorneos). Al tocar un torneo hay que tirarla,
     * junto con el armado por país y competencia.
     */
    protected static function booted()
    {
        static::saved(function () { MenuTorneos::olvidar(); });
        static::deleted(function () { MenuTorneos::olvidar(); });
    }

     protected $fillable = ['nombre', 'year', 'equipos','grupos','tipo','ambito','selecciones', 'url_nombre','escudo','neutral', 'descenso', 'descenso_promedio', 'region','sofa_tournament_id',
         'sofa_season_id',
         'sofa_slug',
         'sofa_category_id',
         'sofa_category_slug','goles_importados','pais','parcial','inconcluso','sin_titulo',
        'tm_competition_id','tm_season_id'];

    /**
     * Ids de los torneos marcados «No da título» (`sin_titulo`): el primero
     * de sus posiciones no suma título en ninguna parte. Una consulta por
     * request; sin la columna (antes de la migración), ninguno.
     */
    public static function idsSinTitulo()
    {
        static $ids = null;
        if ($ids === null) {
            $ids = \Illuminate\Support\Facades\Schema::hasColumn('torneos', 'sin_titulo')
                ? array_map('intval', \Illuminate\Support\Facades\DB::table('torneos')
                    ->where('sin_titulo', 1)->pluck('id')->all())
                : [];
        }
        return $ids;
    }

    /** ¿El campeón de este torneo suma título? */
    public static function daTitulo($torneoId)
    {
        return !in_array((int) $torneoId, self::idsSinTitulo(), true);
    }

    /**
     * Para SQL crudo: « AND <col> NOT IN (…)» con los torneos sin título, o ''
     * si no hay ninguno. `$col` es la columna del id de torneo en la consulta.
     */
    public static function sqlConTitulo($col)
    {
        $ids = self::idsSinTitulo();
        return $ids ? ' AND ' . $col . ' NOT IN (' . implode(',', $ids) . ')' : '';
    }

    public function grupoDetalle() {
        return $this->hasMany('App\Grupo');
    }


    public function getFullNameAttribute()
    {
        return $this->nombre . ' ' . $this->year;
    }

    public function cruces()
    {
        return $this->hasMany('App\Cruce');
    }

    public function clasificaciones()
    {
        return $this->hasMany('App\TorneoClasificacion');
    }

    public function grupos()
    {
        return $this->hasMany(Grupo::class);
    }


}
