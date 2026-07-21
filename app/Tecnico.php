<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Tecnico extends Model
{
    protected $fillable = ['persona_id','transfermarkt_url'];

    public function persona() {
        return $this->belongsTo('App\Persona');
    }
}
