<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Equipo extends Model
{
    protected $fillable = ['siglas','nombre', 'socios','fundacion','estadio','escudo','historia','pais'];

    public function getBanderaUrlAttribute()
    {
        $path = 'images/' . $this->pais . '.gif';
        return file_exists(public_path($path)) ? url($path) : url('images/Argentina.gif');
    }

}
