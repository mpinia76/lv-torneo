<?php

namespace App;

use Illuminate\Database\Eloquent\Model;


class Partido extends Model
{
    protected $fillable = ['fecha_id', 'dia', 'equipol_id','equipov_id','golesl','golesv','penalesl','penalesv', 'orden','neutral','bloquear'];

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

    public function fue_invicto($equipo_id)
    {
        // Retorna true si el equipo no recibió goles en este partido
        if ($this->equipol_id == $equipo_id && $this->golesv == 0) {
            return true;
        } elseif ($this->equipov_id == $equipo_id && $this->golesl == 0) {
            return true;
        }
        return false;
    }

    public function goles_recibidos_por_equipo($equipo_id)
    {
        // Retorna los goles recibidos por el equipo en este partido
        if ($this->equipol_id == $equipo_id) {
            return $this->golesv; // goles del visitante
        } elseif ($this->equipov_id == $equipo_id) {
            return $this->golesl; // goles del local
        }
        return 0;
    }

    public function invicta_por_equipo($equipo_id)
    {
        // Retorna 1 si el equipo no recibió goles, 0 si sí
        return $this->goles_recibidos_por_equipo($equipo_id) == 0 ? 1 : 0;
    }

    // App/Partido.php

// Relación con equipo local
    public function local()
    {
        return $this->belongsTo(Equipo::class, 'equipol_id');
    }

// Relación con equipo visitante
    public function visitante()
    {
        return $this->belongsTo(Equipo::class, 'equipov_id');
    }

// Relación con alineaciones
    public function alineacions()
    {
        return $this->hasMany(Alineacion::class, 'partido_id');
    }

// Opcional: relación con cambios
    public function cambios()
    {
        return $this->hasMany(Cambio::class, 'partido_id');
    }


}
