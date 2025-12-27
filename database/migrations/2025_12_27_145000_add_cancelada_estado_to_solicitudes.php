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
        // Modificar el enum de estado para incluir 'cancelada'
        DB::statement("ALTER TABLE solicitud_vacaciones MODIFY COLUMN estado ENUM('pendiente', 'pendiente_documento', 'aprobada', 'rechazada', 'cancelada') DEFAULT 'pendiente'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir al enum anterior (sin cancelada)
        DB::statement("ALTER TABLE solicitud_vacaciones MODIFY COLUMN estado ENUM('pendiente', 'pendiente_documento', 'aprobada', 'rechazada') DEFAULT 'pendiente'");
    }
};
