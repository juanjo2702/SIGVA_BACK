<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitud_vacaciones', function (Blueprint $table) {
            $table->decimal('saldo_actual', 5, 1)->nullable()->after('dias_solicitados');
            $table->decimal('saldo_despues', 5, 1)->nullable()->after('saldo_actual');
        });

        DB::table('solicitud_vacaciones')
            ->orderBy('id')
            ->chunkById(200, function ($solicitudes) {
                foreach ($solicitudes as $solicitud) {
                    $saldoActual = null;
                    $saldoDespues = null;

                    $historialAprobacion = DB::table('historial_vacaciones')
                        ->where('empleado_id', $solicitud->empleado_id)
                        ->where('tipo_cambio', 'solicitud_aprobada')
                        ->where('descripcion', "Solicitud de vacaciones #{$solicitud->id} aprobada")
                        ->orderByDesc('id')
                        ->first();

                    if ($historialAprobacion) {
                        $saldoActual = (float) $historialAprobacion->dias_anteriores;
                        $saldoDespues = (float) $historialAprobacion->dias_nuevos;
                    } else {
                        $marcaTiempo = $solicitud->created_at ?? $solicitud->fecha_solicitud;

                        $historialPrevio = DB::table('historial_vacaciones')
                            ->where('empleado_id', $solicitud->empleado_id)
                            ->where('created_at', '<=', $marcaTiempo)
                            ->orderByDesc('created_at')
                            ->orderByDesc('id')
                            ->first();

                        if ($historialPrevio) {
                            $saldoActual = (float) $historialPrevio->dias_nuevos;
                        } else {
                            $historialPosterior = DB::table('historial_vacaciones')
                                ->where('empleado_id', $solicitud->empleado_id)
                                ->where('created_at', '>', $marcaTiempo)
                                ->orderBy('created_at')
                                ->orderBy('id')
                                ->first();

                            if ($historialPosterior) {
                                $saldoActual = (float) $historialPosterior->dias_anteriores;
                            } else {
                                $empleado = DB::table('empleados')
                                    ->where('id', $solicitud->empleado_id)
                                    ->first(['saldo_vacaciones']);

                                if ($empleado) {
                                    $saldoActual = (float) $empleado->saldo_vacaciones;
                                }
                            }
                        }

                        if ($saldoActual !== null) {
                            $saldoDespues = $saldoActual - (float) $solicitud->dias_solicitados;
                        }
                    }

                    DB::table('solicitud_vacaciones')
                        ->where('id', $solicitud->id)
                        ->update([
                            'saldo_actual' => $saldoActual,
                            'saldo_despues' => $saldoDespues,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('solicitud_vacaciones', function (Blueprint $table) {
            $table->dropColumn(['saldo_actual', 'saldo_despues']);
        });
    }
};
