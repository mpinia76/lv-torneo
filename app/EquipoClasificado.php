<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class EquipoClasificado extends Model
{
    protected $fillable = ['torneo_id', 'equipo_id', 'torneo_clasificacion_id'];

    public function torneo() {
        return $this->belongsTo(Torneo::class);
    }

    public function equipo() {
        return $this->belongsTo(Equipo::class);
    }

    public function clasificacion() {
        return $this->belongsTo(TorneoClasificacion::class, 'torneo_clasificacion_id');
    }
}
