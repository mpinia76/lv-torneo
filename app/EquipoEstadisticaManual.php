<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class EquipoEstadisticaManual extends Model
{
    protected $fillable = [
        'equipo_id',
        'torneo_nombre',
        'torneo_logo',
        'tipo',
        'ambito',
        'partidos',
        'posicion',
        'ganados',
        'empatados',
        'perdidos',
        'goles_favor',
        'goles_en_contra'
    ];


    public function equipo()
    {
        return $this->belongsTo(Equipo::class);
    }


}
