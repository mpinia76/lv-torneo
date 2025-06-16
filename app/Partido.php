<?php

namespace App;

use Illuminate\Database\Eloquent\Model;


class Partido extends Model
{
    protected $fillable = ['fecha_id', 'dia', 'equipol_id','equipov_id','golesl','golesv','penalesl','penalesv', 'orden'];

    public function equipol()
    {
        return $this->belongsTo(Equipo::class, 'equipol_id'); // Donde 'equipol_id' es la columna de clave foránea
    }

    public function equipov()
    {
        return $this->belongsTo(Equipo::class, 'equipov_id'); // Donde 'equipov_id' es la columna de clave foránea
    }

    public function fecha() {
        return $this->belongsTo('App\Fecha');
    }

}
