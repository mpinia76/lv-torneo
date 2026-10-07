<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTipoToTituloTorneosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('titulo_torneos', function (Blueprint $table) {
            $table->enum('tipo', ['Liga', 'Copa']);
            $table->enum('ambito', ['Nacional', 'Internacional'])->default('Nacional');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('titulo_torneos', function (Blueprint $table) {
            //
        });
    }
}
