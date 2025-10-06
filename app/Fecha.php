<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Fecha extends Model
{
    protected $fillable = ['numero', 'grupo_id', 'url_nombre', 'orden','penales_importados'];

    public function grupo() {
        return $this->belongsTo('App\Grupo');
    }

    public function partidos() {
        return $this->hasMany('App\Partido')->orderBy('dia');
    }
}
