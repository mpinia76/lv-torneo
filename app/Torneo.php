<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Torneo extends Model
{
     protected $fillable = ['nombre', 'year', 'equipos','grupos','tipo','ambito'];

    public function grupoDetalle() {
        return $this->hasMany('App\Grupo');
    }


    public function getFullNameAttribute()
    {
        return $this->nombre . ' ' . $this->year;
    }

}
