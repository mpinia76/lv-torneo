<?php

namespace App;

use Illuminate\Database\Eloquent\Model;


class Incidencia extends Model
{
    protected $fillable = ['partido_id', 'puntos', 'equipo_id','torneo_id','observaciones'];

    public function equipo()
    {
        return $this->belongsTo(Equipo::class, 'equipo_id'); // Donde 'equipo_id' es la columna de clave foránea
    }

    public function torneo()
    {
        return $this->belongsTo(
            Torneo::class, 'torneo_id');
    }

    public function partido()
    {
        return $this->belongsTo(Partido::class, 'partido_id');
    }


}
