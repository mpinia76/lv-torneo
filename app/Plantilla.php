<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Plantilla extends Model
{
    protected $fillable = ['equipo_id', 'torneo_id'];

    public function torneo() {
        return $this->belongsTo('App\Torneo');
    }

    public function equipo() {
        return $this->belongsTo('App\Equipo');
    }

    public function jugadores() {
        return $this->hasMany('App\PlantillaJugador');
    }
}
