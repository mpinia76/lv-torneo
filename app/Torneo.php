<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Torneo extends Model
{
     protected $fillable = ['nombre', 'year', 'equipos','grupos','tipo','ambito', 'url_nombre','escudo'];

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

}
