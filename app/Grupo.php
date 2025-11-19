<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Grupo extends Model
{
    protected $fillable = ['nombre', 'torneo_id', 'equipos','posiciones','promedios','agrupacion','penales','acumulado','clasificados','goles_importados'];


    // Relación con las fechas
    public function fechas()
    {
        return $this->hasMany(Fecha::class);
    }

	public function torneo() {
        return $this->belongsTo('App\Torneo');
    }

    public function plantillas()
    {
        return $this->hasMany(Plantilla::class);
    }

}
