<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TorneoClasificacion extends Model
{
    protected $fillable = ['torneo_id', 'nombre', 'cantidad', 'campeon_torneo_id'];

    // campeon_torneo_id: el cupo es del campeón de ese torneo (ver App\Services\CuposCampeon)
    // nombre: 'Libertadores', 'Sudamericana'

    public function torneo() {
        return $this->belongsTo('App\Torneo');
    }
}
