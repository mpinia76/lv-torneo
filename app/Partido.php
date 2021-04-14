<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Partido extends Model
{
    protected $fillable = ['fecha_id', 'dia', 'equipol_id','equipov_id','golesl','golesv','penalesl','penalesv'];

    public function equipol() {
        return $this->belongsTo('App\Equipo');
    }

    public function equipov() {
        return $this->belongsTo('App\Equipo');
    }

    public function fecha() {
        return $this->belongsTo('App\Fecha');
    }

}
