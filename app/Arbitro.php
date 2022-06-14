<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Arbitro extends Model
{
    protected $fillable = ['persona_id'];

    public function persona() {
        return $this->belongsTo('App\Persona');
    }
}
