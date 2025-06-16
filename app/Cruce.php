<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Cruce extends Model
{
    protected $fillable = [
        'torneo_id', 'fase', 'orden',
        'clasificado_1', 'clasificado_2',
        'ganador_id', 'partido_id','dia','neutral'
    ];

    public function torneo()
    {
        return $this->belongsTo(Torneo::class);
    }

    public function partido()
    {
        return $this->belongsTo(Partido::class);
    }

    public function ganador()
    {
        return $this->belongsTo(Equipo::class, 'ganador_id');
    }
}
