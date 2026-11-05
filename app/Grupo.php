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

    /**
     * ¿La carga de fechas muestra las columnas de penales (PL/PV)?
     * En el grupo de llaves (`penales`) siempre. Y también en una zona con
     * tabla de una COPA: en la fase de grupos de la Leagues Cup el empate se
     * define por penales y la tabla da 2 y 1 puntos (GrupoController). No se
     * prende `penales` en esa zona porque ese tilde marca el grupo de llaves
     * en todo el importador.
     */
    public function getConPenalesAttribute()
    {
        if (!empty($this->penales)) return true;
        return !empty($this->posiciones) && $this->torneo && $this->torneo->tipo === 'Copa';
    }

}
