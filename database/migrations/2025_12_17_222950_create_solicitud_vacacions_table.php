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
        Schema::create('solicitud_vacaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empleado_id')->constrained('empleados')->onDelete('cascade');
            $table->date('fecha_solicitud');
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->enum('tipo', ['completo', 'parcial_manana', 'parcial_tarde'])->default('completo');
            $table->decimal('dias_solicitados', 5, 1); // Hasta 999.9 días
            $table->enum('estado', ['pendiente', 'aprobada', 'rechazada'])->default('pendiente');
            $table->text('motivo_rechazo')->nullable();
            $table->string('lugar_solicitud')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('solicitud_vacaciones');
    }
};
