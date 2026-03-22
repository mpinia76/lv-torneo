<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TecnicoEstadisticaManual extends Model
{
    protected $fillable = [
        'tecnico_id',
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

    public function tecnico()
    {
        return $this->belongsTo(tecnico::class);
    }
}
