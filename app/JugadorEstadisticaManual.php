<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class JugadorEstadisticaManual extends Model
{


    protected $fillable = [
        'jugador_id',
        'equipo_id',
        'torneo_nombre',
        'torneo_logo',
        'tipo',
        'ambito',
        'partidos',
        'posicion',
        'goles_cabeza',
        'goles_penal',
        'goles_en_contra',
        'goles_tiro_libre',
        'goles_jugada',
        'amarillas',
        'rojas',
        'penales_errados',
        'penales_atajados',
        'goles_recibidos',
        'vallas_invictas',
        'penales_atajo'
    ];


    public function equipo()
    {
        return $this->belongsTo(Equipo::class);
    }

    public function jugador()
    {
        return $this->belongsTo(Jugador::class);
    }
}
