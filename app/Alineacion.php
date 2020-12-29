<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Alineacion extends Model
{
    protected $fillable = ['partido_id', 'jugador_id', 'equipo_id', 'dorsal','tipo','orden'];

    public function partido() {
        return $this->belongsTo('App\Partido');
    }

    public function jugador() {
        return $this->belongsTo('App\Jugador');
    }

    public function equipo() {
        return $this->belongsTo('App\Equipo');
    }
}
