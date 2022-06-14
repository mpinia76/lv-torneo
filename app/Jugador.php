<?php

namespace App;


use Illuminate\Database\Eloquent\Model;

class Jugador extends Model
{
    protected $fillable = ['tipoJugador','pie','persona_id'];

    public function persona() {
        return $this->belongsTo('App\Persona');
    }



}
