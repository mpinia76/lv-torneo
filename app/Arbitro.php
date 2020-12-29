<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Arbitro extends Model
{
    protected $fillable = ['nombre', 'apellido','email','telefono','ciudad','observaciones','tipoDocumento','documento','nacimiento','foto'];

    public function getFullNameAttribute()
    {
        return $this->apellido . ', ' . $this->nombre;
    }
}
