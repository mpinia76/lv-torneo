<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Penal extends Model
{
    protected $fillable = ['partido_id', 'jugador_id', 'minuto', 'adicionado', 'tipo'];

    /** El minuto como se escribe: «90», «90+6». Ver App\Services\MinutoHelper. */
    public function getMinutoTextoAttribute()
    {
        return \App\Services\MinutoHelper::texto($this->minuto, $this->adicionado);
    }

    public function partido() {
        return $this->belongsTo('App\Partido');
    }

    public function jugador() {
        return $this->belongsTo('App\Jugador');
    }
}
