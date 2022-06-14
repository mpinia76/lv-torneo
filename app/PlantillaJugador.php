<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PlantillaJugador extends Model
{
    protected $fillable = ['plantilla_id', 'jugador_id', 'dorsal','foto'];


    public function plantilla() {
        return $this->belongsTo('App\Plantilla');
    }

    public function jugador() {
        return $this->belongsTo('App\Jugador');
    }


}
