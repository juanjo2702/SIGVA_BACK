<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Modificar el enum para agregar devolucion_feriado
        DB::statement("ALTER TABLE historial_vacaciones MODIFY COLUMN tipo_cambio ENUM('suma_anual', 'solicitud_aprobada', 'ajuste_manual', 'importacion', 'devolucion_feriado')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE historial_vacaciones MODIFY COLUMN tipo_cambio ENUM('suma_anual', 'solicitud_aprobada', 'ajuste_manual', 'importacion')");
    }
};
