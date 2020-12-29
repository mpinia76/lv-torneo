<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Grupo extends Model
{
    protected $fillable = ['nombre', 'torneo_id', 'equipos'];


	public function torneo() {
        return $this->belongsTo('App\Torneo');
    }
}
