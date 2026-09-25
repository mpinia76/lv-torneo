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

     protected $fillable = ['nombre', 'year', 'equipos','grupos','tipo','ambito', 'url_nombre','escudo','neutral', 'descenso', 'descenso_promedio', 'region','sofa_tournament_id',
         'sofa_season_id',
         'sofa_slug',
         'sofa_category_id',
         'sofa_category_slug','goles_importados','pais','parcial','inconcluso',
        'tm_competition_id','tm_season_id'];

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
