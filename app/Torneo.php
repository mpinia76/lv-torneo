<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Torneo extends Model
{
     protected $fillable = ['nombre', 'year', 'equipos','grupos','playoffs'];

    public function grupoDetalle() {
        return $this->hasMany('App\Grupo');
    }

}
