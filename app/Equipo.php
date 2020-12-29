<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Equipo extends Model
{
    protected $fillable = ['siglas','nombre', 'socios','fundacion','estadio','escudo','historia'];


}
