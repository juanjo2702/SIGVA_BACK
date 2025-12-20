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
        Schema::table('solicitud_vacaciones', function (Blueprint $table) {
            // Hacer tipo nullable ya que ahora los tipos están en la tabla de detalles
            $table->string('tipo')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('solicitud_vacaciones', function (Blueprint $table) {
            $table->string('tipo')->nullable(false)->change();
        });
    }
};
