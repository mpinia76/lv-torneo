<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Torneo extends Model
{
     protected $fillable = ['nombre', 'year', 'equipos','grupos','tipo','ambito', 'url_nombre','escudo','neutral', 'descenso', 'descenso_promedio', 'region'];

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
