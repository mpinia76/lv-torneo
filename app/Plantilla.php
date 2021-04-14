<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Plantilla extends Model
{
    protected $fillable = ['equipo_id', 'grupo_id'];



    public function equipo() {
        return $this->belongsTo('App\Equipo');
    }

    public function jugadores() {
        return $this->hasMany('App\PlantillaJugador');
    }

    public function grupo() {
        return $this->belongsTo('App\Grupo');
    }
}
