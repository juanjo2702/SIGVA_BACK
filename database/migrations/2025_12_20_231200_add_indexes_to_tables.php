<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agregar índices para mejorar rendimiento de consultas frecuentes
     */
    public function up(): void
    {
        // Índices para tabla empleados
        Schema::table('empleados', function (Blueprint $table) {
            // CI - búsqueda pública muy frecuente
            $table->index('ci', 'idx_empleados_ci');

            // Activo - filtro en casi todas las queries
            $table->index('activo', 'idx_empleados_activo');

            // Índice compuesto para ordenamiento por nombre
            $table->index(['apellido_paterno', 'apellido_materno', 'nombres'], 'idx_empleados_nombre');
        });

        // Índices para tabla solicitud_vacaciones
        Schema::table('solicitud_vacaciones', function (Blueprint $table) {
            // Estado - filtro muy frecuente en dashboard y listados
            $table->index('estado', 'idx_solicitudes_estado');

            // Fecha de solicitud - filtros por rango de fechas
            $table->index('fecha_solicitud', 'idx_solicitudes_fecha');

            // Índice compuesto para consultas de dashboard
            $table->index(['estado', 'fecha_solicitud'], 'idx_solicitudes_estado_fecha');
        });

        // Índices para tabla historial_vacaciones
        Schema::table('historial_vacaciones', function (Blueprint $table) {
            // Empleado ID ya tiene índice por foreign key, pero agregamos fecha
            $table->index('created_at', 'idx_historial_fecha');
        });
    }

    /**
     * Revertir los índices
     */
    public function down(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            $table->dropIndex('idx_empleados_ci');
            $table->dropIndex('idx_empleados_activo');
            $table->dropIndex('idx_empleados_nombre');
        });

        Schema::table('solicitud_vacaciones', function (Blueprint $table) {
            $table->dropIndex('idx_solicitudes_estado');
            $table->dropIndex('idx_solicitudes_fecha');
            $table->dropIndex('idx_solicitudes_estado_fecha');
        });

        Schema::table('historial_vacaciones', function (Blueprint $table) {
            $table->dropIndex('idx_historial_fecha');
        });
    }
};
