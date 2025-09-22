<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Equipo extends Model
{
    protected $fillable = ['siglas','nombre', 'socios','fundacion','estadio','escudo','historia','pais', 'url_nombre','url_id'];

    public function getBanderaUrlAttribute()
    {
        // Reemplaza caracteres especiales en la nacionalidad
        $nacionalidadSinAcentos = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'Á', 'É', 'Í', 'Ó', 'Ú', 'Ñ'],
            ['a', 'e', 'i', 'o', 'u', 'n', 'A', 'E', 'I', 'O', 'U', 'N'],
            $this->pais
        );

        $path = 'images/' . $nacionalidadSinAcentos . '.gif';
        return file_exists(public_path($path)) ? url($path) : url('images/sinBandera.gif');
    }

    public function alineacions()
    {
        return $this->hasMany(Alineacion::class, 'equipo_id', 'id');
    }
}
