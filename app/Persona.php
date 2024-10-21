<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Persona extends Model
{
    protected $fillable = ['nombre', 'apellido','email','telefono','ciudad','observaciones','tipoDocumento','documento','nacimiento','peso','altura','foto','fallecimiento','nacionalidad','verificado'];

    public function jugador()
    {
        return $this->hasOne('App\Jugador');
    }

    public function tecnico()
    {
        return $this->hasOne('App\Tecnico');
    }

    public function arbitro()
    {
        return $this->hasOne('App\Arbitro');
    }

    public function getFullNameAttribute()
    {
        return $this->apellido . ', ' . $this->nombre;
    }

    public function getFullNameAgeAttribute()
    {
        return $this->apellido . ', ' . $this->nombre.' ('.$this->getAgeAttribute().')';
    }

    public function getBanderaUrlAttribute()
    {
        // Reemplaza caracteres especiales en la nacionalidad
        $nacionalidadSinAcentos = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'Á', 'É', 'Í', 'Ó', 'Ú', 'Ñ'],
            ['a', 'e', 'i', 'o', 'u', 'n', 'A', 'E', 'I', 'O', 'U', 'N'],
            $this->nacionalidad
        );

        $path = 'images/' . $nacionalidadSinAcentos . '.gif';
        return file_exists(public_path($path)) ? url($path) : url('images/sinBandera.gif');
    }


    public function getAgeAttribute()
    {
        if (!is_null($this->fallecimiento))
        {
            return ' ('.date('d/m/Y', strtotime($this->nacimiento)).'-'.date('d/m/Y', strtotime($this->fallecimiento)).')';
        }
        if (!is_null($this->nacimiento))
        {
            return Carbon::parse($this->nacimiento)->age.' años ('.date('d/m/Y', strtotime($this->nacimiento)).')';
        }

    }

    public function getAgeWithDateAttribute()
    {
        if (!is_null($this->fallecimiento))
        {
            return Carbon::parse($this->nacimiento)->diff(Carbon::parse($this->fallecimiento))->format('%y').' años ('.date('d/m/Y', strtotime($this->nacimiento)).'-'.date('d/m/Y', strtotime($this->fallecimiento)).')';
        }
        if (!is_null($this->nacimiento))
        {
            return Carbon::parse($this->nacimiento)->age.' años ('.date('d/m/Y', strtotime($this->nacimiento)).')';
        }

    }

    // Nuevo método para calcular la edad en una fecha específica
    public function getAgeAtDate($date)
    {
        if (!is_null($this->fallecimiento) && Carbon::parse($this->fallecimiento)->lte(Carbon::parse($date))) {
            return ' ('.date('d/m/Y', strtotime($this->nacimiento)).'-'.date('d/m/Y', strtotime($this->fallecimiento)).')';
        }
        if (!is_null($this->nacimiento)) {
            return Carbon::parse($this->nacimiento)->diffInYears(Carbon::parse($date));
        }
        return null;
    }
}
