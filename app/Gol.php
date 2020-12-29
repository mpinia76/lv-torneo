<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Gol extends Model
{
    protected $fillable = ['partido_id', 'jugador_id', 'minuto','tipo'];

    public function partido() {
        return $this->belongsTo('App\Partido');
    }

    public function jugador() {
        return $this->belongsTo('App\Jugador');
    }
}
