<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePersonasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('personas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->nullable();
            $table->string('apellido')->nullable();
            $table->string('email')->nullable();
            $table->string('telefono')->nullable();
            $table->string('ciudad')->nullable();
            $table->float('altura',8,2)->nullable();
            $table->float('peso',8,2)->nullable();
            $table->text('foto')->nullable();
            $table->text('observaciones')->nullable();
            $table->enum('tipoDocumento', ['DNI', 'PAS', 'CI']);
            $table->string('documento')->nullable();
            $table->datetime('nacimiento')->nullable();
            $table->datetime('fallecimiento')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('personas');
    }
}
