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
        Schema::create('historial_vacaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empleado_id')->constrained('empleados')->onDelete('cascade');
            $table->decimal('dias_anteriores', 6, 1);
            $table->decimal('dias_cambio', 6, 1);
            $table->decimal('dias_nuevos', 6, 1);
            $table->enum('tipo_cambio', ['suma_anual', 'solicitud_aprobada', 'ajuste_manual', 'importacion']);
            $table->text('descripcion')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('historial_vacaciones');
    }
};
