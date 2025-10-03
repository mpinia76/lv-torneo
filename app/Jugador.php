<?php

namespace App;


use Illuminate\Database\Eloquent\Model;

class Jugador extends Model
{
    protected $fillable = ['tipoJugador','pie','persona_id','url_nombre'];

    public function persona() {
        return $this->belongsTo('App\Persona');
    }


    public function getFullNameAgeTipoAttribute()
    {
        return $this->persona->getFullNameAgeAttribute().' ('.$this->tipoJugador.')';
    }

    // NUEVAS RELACIONES

    public function alineacions()
    {
        return $this->hasMany('App\Alineacion', 'jugador_id');
    }

    public function gols()
    {
        return $this->hasMany('App\Gol', 'jugador_id');
    }

    public function tarjetas()
    {
        return $this->hasMany('App\Tarjeta', 'jugador_id');
    }

    public function penals()
    {
        return $this->hasMany('App\Penal', 'jugador_id');
    }

}
