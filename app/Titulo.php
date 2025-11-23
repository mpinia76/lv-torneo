<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Titulo extends Model
{
    protected $fillable = [
        'nombre', 'equipo_id', 'year','tipo','ambito'
    ];

    public function equipo()
    {
        return $this->belongsTo(Equipo::class);
    }

    public function torneos()
    {
        return $this->belongsToMany(Torneo::class, 'titulo_torneos');
    }
}

