<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PartidoTecnico extends Model
{
    protected $fillable = ['partido_id', 'tecnico_id','equipo_id'];


    public function partido() {
        return $this->belongsTo('App\Partido');
    }

    public function tecnico() {
        return $this->belongsTo('App\Tecnico');
    }

    public function equipo() {
        return $this->belongsTo('App\Equipo');
    }
}
