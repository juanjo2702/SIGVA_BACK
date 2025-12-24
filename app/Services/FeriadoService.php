<?php

namespace App\Services;

use App\Models\Feriado;
use App\Models\DetalleSolicitudVacacion;
use App\Models\HistorialVacacion;
use App\Models\Empleado;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FeriadoService
{
    /**
     * Procesar devoluciones de vacaciones para un feriado
     * Elimina los detalles de vacaciones en esa fecha y devuelve días a los empleados
     */
    public function procesarDevolucionesPorFeriado(Feriado $feriado): array
    {
        $resultado = [
            'empleados_afectados' => 0,
            'dias_devueltos' => 0,
            'detalles' => [],
        ];

        DB::beginTransaction();

        try {
            // Obtener fecha del feriado
            $fecha = $feriado->fecha->format('Y-m-d');

            // Query para obtener detalles de vacaciones en esa fecha
            $query = DetalleSolicitudVacacion::where('fecha', $fecha)
                ->whereHas('solicitud', function ($q) {
                    // Solo solicitudes aprobadas o pendiente_documento
                    $q->whereIn('estado', ['aprobada', 'pendiente_documento']);
                })
                ->with(['solicitud.empleado']);

            // Si es feriado departamental, solo afecta empleados de esa sede
            if ($feriado->tipo === Feriado::TIPO_DEPARTAMENTAL && $feriado->sede_id) {
                $query->whereHas('solicitud.empleado', function ($q) use ($feriado) {
                    $q->where('sede_id', $feriado->sede_id);
                });
            }

            $detallesAfectados = $query->get();

            foreach ($detallesAfectados as $detalle) {
                $empleado = $detalle->solicitud->empleado;

                if (!$empleado) continue;

                // Calcular días a devolver
                $diasDevolver = $detalle->tipo === DetalleSolicitudVacacion::TIPO_COMPLETO ? 1 : 0.5;

                // Devolver días al saldo del empleado
                $saldoAnterior = $empleado->saldo_vacaciones;
                $empleado->saldo_vacaciones += $diasDevolver;
                $empleado->save();

                // Registrar en historial usando el método estático que tiene los campos correctos
                HistorialVacacion::registrar(
                    $empleado,
                    $saldoAnterior,
                    $diasDevolver,
                    'devolucion_feriado',
                    "Devolución automática por feriado: {$feriado->nombre} ({$feriado->fecha->format('d/m/Y')})",
                    auth()->id()
                );

                // Agregar al resultado
                $resultado['detalles'][] = [
                    'empleado_id' => $empleado->id,
                    'empleado_nombre' => $empleado->nombre_completo,
                    'dias_devueltos' => $diasDevolver,
                    'solicitud_id' => $detalle->solicitud_vacacion_id,
                ];

                $resultado['dias_devueltos'] += $diasDevolver;

                // Eliminar el detalle de vacación
                $detalle->delete();
            }

            $resultado['empleados_afectados'] = count($resultado['detalles']);

            DB::commit();

            Log::info("Feriado procesado: {$feriado->nombre}", $resultado);

            return $resultado;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error procesando feriado: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtener preview de empleados afectados sin procesar
     */
    public function previewAfectados(Feriado $feriado): array
    {
        $fecha = $feriado->fecha->format('Y-m-d');

        $query = DetalleSolicitudVacacion::where('fecha', $fecha)
            ->whereHas('solicitud', function ($q) {
                $q->whereIn('estado', ['aprobada', 'pendiente_documento']);
            })
            ->with(['solicitud.empleado']);

        if ($feriado->tipo === Feriado::TIPO_DEPARTAMENTAL && $feriado->sede_id) {
            $query->whereHas('solicitud.empleado', function ($q) use ($feriado) {
                $q->where('sede_id', $feriado->sede_id);
            });
        }

        $detalles = $query->get();

        $empleados = [];
        $totalDias = 0;

        foreach ($detalles as $detalle) {
            $empleado = $detalle->solicitud->empleado;
            if (!$empleado) continue;

            $dias = $detalle->tipo === DetalleSolicitudVacacion::TIPO_COMPLETO ? 1 : 0.5;
            $totalDias += $dias;

            $empleados[] = [
                'id' => $empleado->id,
                'nombre' => $empleado->nombre_completo,
                'dias' => $dias,
                'tipo' => $detalle->tipo_formateado,
            ];
        }

        return [
            'empleados' => $empleados,
            'total_empleados' => count($empleados),
            'total_dias' => $totalDias,
        ];
    }
}
