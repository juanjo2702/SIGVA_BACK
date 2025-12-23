<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('feriados', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->date('fecha');
            $table->enum('tipo', ['nacional', 'departamental'])->default('nacional');
            $table->foreignId('sede_id')->nullable()->constrained()->onDelete('cascade');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            // Índice único: no duplicar feriados en la misma fecha y sede
            $table->unique(['fecha', 'sede_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feriados');
    }
};
