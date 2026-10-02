<?php

namespace App;

use Illuminate\Database\Eloquent\Model;


class Incidencia extends Model
{
    protected $fillable = ['partido_id', 'puntos', 'equipo_id','torneo_id','observaciones','observaciones_en'];

    /**
     * La observación en el idioma del sitio: en /en usa `observaciones_en`
     * y, si está vacía, cae al castellano. Uso: $incidencia->observacion_idioma
     */
    public function getObservacionIdiomaAttribute()
    {
        if (app()->getLocale() === 'en' && trim((string) $this->observaciones_en) !== '') {
            return $this->observaciones_en;
        }
        return $this->observaciones;
    }

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
