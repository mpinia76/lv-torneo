<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PromedioTorneo extends Model
{
    protected $fillable = ['torneo_id', 'torneoAnterior_id'];


    public function torneo() {
        return $this->belongsTo('App\Torneo');
    }

    public function torneoAnterior() {
        return $this->belongsTo('App\Torneo');
    }
}
