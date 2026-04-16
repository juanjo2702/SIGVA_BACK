<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('solicitud_vacaciones')
            ->where('estado', 'aprobada')
            ->orderBy('id')
            ->chunkById(200, function ($solicitudes) {
                foreach ($solicitudes as $solicitud) {
                    $historialAprobacion = DB::table('historial_vacaciones')
                        ->where('empleado_id', $solicitud->empleado_id)
                        ->where('tipo_cambio', 'solicitud_aprobada')
                        ->where('descripcion', "Solicitud de vacaciones #{$solicitud->id} aprobada")
                        ->orderByDesc('id')
                        ->first();

                    if (!$historialAprobacion) {
                        continue;
                    }

                    DB::table('solicitud_vacaciones')
                        ->where('id', $solicitud->id)
                        ->update([
                            'saldo_actual' => (float) $historialAprobacion->dias_anteriores,
                            'saldo_despues' => (float) $historialAprobacion->dias_nuevos,
                        ]);
                }
            });
    }

    public function down(): void
    {
        // No-op: esta migración solo corrige snapshots históricos.
    }
};
