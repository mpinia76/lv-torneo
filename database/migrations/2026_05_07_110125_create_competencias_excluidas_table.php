<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competencias_excluidas', function (Blueprint $table) {
            $table->id();
            $table->string('patron')->index();
            $table->enum('tipo_match', ['exacto', 'contiene', 'regex'])->default('contiene');
            $table->string('motivo')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competencias_excluidas');
    }
};
