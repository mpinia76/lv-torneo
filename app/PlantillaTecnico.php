<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PlantillaTecnico extends Model
{
    protected $fillable = ['plantilla_id', 'tecnico_id','foto'];


    public function plantilla() {
        return $this->belongsTo('App\Plantilla');
    }

    public function tecnico() {
        return $this->belongsTo('App\Tecnico');
    }
}
