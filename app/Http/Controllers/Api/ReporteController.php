<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Empleado;
use App\Models\SolicitudVacacion;
use App\Models\HistorialVacacion;
use App\Exports\EmpleadosExport;
use App\Exports\EmpleadosMultipleExport;
use App\Exports\SolicitudesExport;
use App\Exports\SolicitudesMultipleExport;
use App\Exports\ReporteGeneralExport;
use App\Exports\ReporteGeneralMultipleExport;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;

class ReporteController extends Controller
{
    /**
     * Reporte de saldos de vacaciones
     */
    public function saldos(Request $request): JsonResponse
    {
        $query = Empleado::activos();

        // Filtros
        if ($request->has('sede_id') && $request->sede_id !== 'todos' && $request->sede_id !== '') {
            $query->where('sede_id', $request->sede_id);
        }
        if ($request->has('saldo_min')) {
            $query->where('saldo_vacaciones', '>=', $request->saldo_min);
        }

        if ($request->has('saldo_max')) {
            $query->where('saldo_vacaciones', '<=', $request->saldo_max);
        }

        if ($request->has('solo_negativos') && $request->boolean('solo_negativos')) {
            $query->where('saldo_vacaciones', '<', 0);
        }

        $empleados = $query->orderBy('saldo_vacaciones', 'asc')->get([
            'id',
            'apellido_paterno',
            'apellido_materno',
            'nombres',
            'ci',
            'cargo',
            'fecha_ingreso',
            'saldo_vacaciones',
        ]);

        // Resumen
        $resumen = [
            'total_empleados' => $empleados->count(),
            'con_saldo_positivo' => $empleados->where('saldo_vacaciones', '>', 0)->count(),
            'con_saldo_cero' => $empleados->where('saldo_vacaciones', 0)->count(),
            'con_saldo_negativo' => $empleados->where('saldo_vacaciones', '<', 0)->count(),
            'total_dias_acumulados' => $empleados->sum('saldo_vacaciones'),
            'promedio_saldo' => round($empleados->avg('saldo_vacaciones'), 1),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'empleados' => $empleados,
                'resumen' => $resumen,
            ],
        ]);
    }

    /**
     * Reporte de solicitudes
     */
    public function solicitudes(Request $request): JsonResponse
    {
        $ano = $request->get('ano', date('Y'));
        $mes = $request->get('mes');

        $query = SolicitudVacacion::with('empleado')
            ->whereYear('fecha_solicitud', $ano);

        if ($request->has('sede_id') && $request->sede_id !== 'todos' && $request->sede_id !== '') {
            $query->whereHas('empleado', function($q) use ($request) {
                $q->where('sede_id', $request->sede_id);
            });
        }

        if ($mes) {
            $query->whereMonth('fecha_solicitud', $mes);
        }

        if ($request->has('estado') && $request->estado !== 'todos') {
            $query->where('estado', $request->estado);
        }

        $solicitudes = $query->orderBy('fecha_solicitud', 'desc')->get();

        // Resumen por estado
        $resumenEstados = [
            'pendientes' => $solicitudes->where('estado', 'pendiente')->count(),
            'aprobadas' => $solicitudes->where('estado', 'aprobada')->count(),
            'rechazadas' => $solicitudes->where('estado', 'rechazada')->count(),
        ];

        // Días totales
        $diasAprobados = $solicitudes->where('estado', 'aprobada')->sum('dias_solicitados');
        $diasPendientes = $solicitudes->where('estado', 'pendiente')->sum('dias_solicitados');

        return response()->json([
            'success' => true,
            'data' => [
                'solicitudes' => $solicitudes,
                'resumen' => [
                    'estados' => $resumenEstados,
                    'dias_aprobados' => $diasAprobados,
                    'dias_pendientes' => $diasPendientes,
                    'ano' => $ano,
                    'mes' => $mes,
                ],
            ],
        ]);
    }

    /**
     * Historial de un empleado específico
     */
    public function historialEmpleado(int $empleadoId): JsonResponse
    {
        $empleado = Empleado::with([
            'historial' => function ($q) {
                $q->orderBy('created_at', 'desc');
            },
            'solicitudes' => function ($q) {
                $q->orderBy('fecha_solicitud', 'desc');
            },
        ])->findOrFail($empleadoId);

        return response()->json([
            'success' => true,
            'data' => [
                'empleado' => $empleado,
                'historial' => $empleado->historial,
                'solicitudes' => $empleado->solicitudes,
            ],
        ]);
    }

    /**
     * Helper to get Sede Name safely for filenames
     */
    private function getSedeName($sedeId): string
    {
        $sede = \App\Models\Sede::find($sedeId);
        if ($sede) {
            return str_replace(' ', '_', mb_strtoupper($sede->nombre));
        }
        
        return 'TODAS_LAS_SEDES';
    }

    /**
     * Exportar empleados a Excel
     */
    public function exportarEmpleados(Request $request)
    {
        $filtros = $request->all();
        $sedeName = $this->getSedeName($filtros['sede_id'] ?? null);
        $filename = 'REPORTE_VACACIONES_SALDOS_' . $sedeName . '_' . date('d_m_Y') . '.xlsx';
        if (isset($filtros['sede_id']) && $filtros['sede_id'] !== 'todos' && $filtros['sede_id'] !== '') {
            return Excel::download(new EmpleadosExport($filtros), $filename);
        }

        return Excel::download(new EmpleadosMultipleExport($filtros), $filename);
    }

    /**
     * Exportar solicitudes a Excel
     */
    public function exportarSolicitudes(Request $request)
    {
        $filtros = $request->all();
        $sedeName = $this->getSedeName($filtros['sede_id'] ?? null);
        $filename = 'REPORTE_VACACIONES_SOLICITUDES_' . $sedeName . '_' . date('d_m_Y') . '.xlsx';
        if (isset($filtros['sede_id']) && $filtros['sede_id'] !== 'todos' && $filtros['sede_id'] !== '') {
            return Excel::download(new SolicitudesExport($filtros), $filename);
        }

        return Excel::download(new SolicitudesMultipleExport($filtros), $filename);
    }

    /**
     * Exportar reporte general (Plan de Vacaciones) a Excel
     */
    public function exportarReporteGeneral(Request $request)
    {
        $filtros = $request->all();
        $sedeName = $this->getSedeName($filtros['sede_id'] ?? null);
        $filename = 'PLAN_NACIONAL_VACACIONES_' . $sedeName . '_' . date('d_m_Y') . '.xlsx';
        if (isset($filtros['sede_id']) && $filtros['sede_id'] !== 'todos' && $filtros['sede_id'] !== '') {
            return Excel::download(new ReporteGeneralExport($filtros), $filename);
        }

        return Excel::download(new ReporteGeneralMultipleExport($filtros), $filename);
    }
}
