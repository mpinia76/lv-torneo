<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PosicionTorneo extends Model
{
    protected $fillable = ['torneo_id', 'equipo_id', 'posicion'];


    public function torneo() {
        return $this->belongsTo('App\Torneo');
    }

    public function equipo() {
        return $this->belongsTo('App\equipo');
    }
}
