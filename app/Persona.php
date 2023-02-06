<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Persona extends Model
{
    protected $fillable = ['nombre', 'apellido','email','telefono','ciudad','observaciones','tipoDocumento','documento','nacimiento','peso','altura','foto','fallecimiento','nacionalidad'];

    public function jugador()
    {
        return $this->hasOne('App\Jugador');
    }

    public function tecnico()
    {
        return $this->hasOne('App\Tecnico');
    }

    public function arbitro()
    {
        return $this->hasOne('App\Arbitro');
    }

    public function getFullNameAttribute()
    {
        return $this->apellido . ', ' . $this->nombre;
    }

    public function getFullNameAgeAttribute()
    {
        return $this->apellido . ', ' . $this->nombre.' ('.$this->getAgeAttribute().')';
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
