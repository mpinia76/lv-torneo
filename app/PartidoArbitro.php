<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PartidoArbitro extends Model
{
    protected $fillable = ['partido_id', 'arbitro_id','tipo'];

    public function partido() {
        return $this->belongsTo('App\Partido');
    }

    public function arbitro() {
        return $this->belongsTo('App\Arbitro');
    }
}
