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
        Schema::create('detalle_solicitud_vacacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_vacacion_id')
                ->constrained('solicitud_vacaciones')
                ->onDelete('cascade');
            $table->date('fecha');
            $table->enum('tipo', ['completo', 'parcial_manana', 'parcial_tarde'])->default('completo');
            $table->decimal('dias_descontados', 3, 1)->default(1.0);
            $table->timestamps();

            // Cada fecha es única por solicitud
            $table->unique(['solicitud_vacacion_id', 'fecha']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detalle_solicitud_vacacion');
    }
};
