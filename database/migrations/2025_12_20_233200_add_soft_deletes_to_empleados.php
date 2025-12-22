<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agregar soft deletes a la tabla empleados
     */
    public function up(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Migrar empleados inactivos a soft deleted
        // Los que tienen activo = false serán marcados como eliminados
        DB::table('empleados')
            ->where('activo', false)
            ->update(['deleted_at' => now()]);
    }

    /**
     * Revertir soft deletes
     */
    public function down(): void
    {
        // Restaurar el flag activo para los eliminados
        DB::table('empleados')
            ->whereNotNull('deleted_at')
            ->update(['activo' => false, 'deleted_at' => null]);

        Schema::table('empleados', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
