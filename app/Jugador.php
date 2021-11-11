<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Jugador extends Model
{
    protected $fillable = ['tipoJugador','nombre', 'apellido','email','telefono','ciudad','observaciones','tipoDocumento','documento','nacimiento','pie','peso','altura','foto','fallecimiento'];

    public function getFullNameAttribute()
    {
        return $this->apellido . ', ' . $this->nombre;
    }

    public function getAgeAttribute()
    {
        if (!is_null($this->fallecimiento))
        {
            return Carbon::parse($this->nacimiento)->diff(Carbon::parse($this->fallecimiento))->format('%y').' años ('.date('d/m/Y', strtotime($this->nacimiento)).'-'.date('d/m/Y', strtotime($this->fallecimiento)).')';
        }
        if (!is_null($this->nacimiento))
        {
            return Carbon::parse($this->nacimiento)->age.' años';
        }

    }

}
