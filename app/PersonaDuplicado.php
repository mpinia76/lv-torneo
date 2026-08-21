<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * Un par candidato a ser la misma persona.
 * Invariante: persona_id es SIEMPRE el id menor y simil_id el mayor, así el par
 * (A,B) y el (B,A) son la misma fila y la lista nunca muestra información repetida.
 */
class PersonaDuplicado extends Model
{
    protected $table = 'persona_duplicados';

    protected $fillable = ['persona_id', 'simil_id', 'puntaje', 'motivo', 'estado'];

    const PENDIENTE  = 'pendiente';
    const DESCARTADO = 'descartado';
    const FUSIONADO  = 'fusionado';

    public function persona()
    {
        return $this->belongsTo(Persona::class, 'persona_id');
    }

    public function simil()
    {
        return $this->belongsTo(Persona::class, 'simil_id');
    }

    public function scopePendientes($query)
    {
        return $query->where('estado', self::PENDIENTE);
    }

    /** Guarda el par siempre con el id menor primero. */
    public static function ordenado($a, $b): array
    {
        $a = (int) $a;
        $b = (int) $b;

        return $a < $b ? [$a, $b] : [$b, $a];
    }
}
