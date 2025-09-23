<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TorneoClasificacion extends Model
{
    protected $fillable = ['torneo_id', 'nombre', 'cantidad'];
    // nombre: 'Libertadores', 'Sudamericana'

    public function torneo() {
        return $this->belongsTo('App\Torneo');
    }
}
