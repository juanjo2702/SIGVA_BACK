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
        Schema::table('solicitud_vacaciones', function (Blueprint $table) {
            $table->boolean('tiene_reemplazo')->default(false)->after('lugar_solicitud');
            $table->string('nombre_reemplazo')->nullable()->after('tiene_reemplazo');
            $table->boolean('documento_entregado')->default(false)->after('nombre_reemplazo');
        });

        // Modificar el enum de estado para incluir 'pendiente_documento'
        // Primero cambiamos a string temporalmente
        DB::statement("ALTER TABLE solicitud_vacaciones MODIFY COLUMN estado ENUM('pendiente', 'pendiente_documento', 'aprobada', 'rechazada') DEFAULT 'pendiente'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir el enum primero
        DB::statement("ALTER TABLE solicitud_vacaciones MODIFY COLUMN estado ENUM('pendiente', 'aprobada', 'rechazada') DEFAULT 'pendiente'");

        Schema::table('solicitud_vacaciones', function (Blueprint $table) {
            $table->dropColumn(['tiene_reemplazo', 'nombre_reemplazo', 'documento_entregado']);
        });
    }
};
