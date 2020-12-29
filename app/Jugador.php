<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Jugador extends Model
{
    protected $fillable = ['tipoJugador','nombre', 'apellido','email','telefono','ciudad','observaciones','tipoDocumento','documento','nacimiento','pie','peso','altura','foto'];

    public function getFullNameAttribute()
    {
        return $this->apellido . ', ' . $this->nombre;
    }

}
