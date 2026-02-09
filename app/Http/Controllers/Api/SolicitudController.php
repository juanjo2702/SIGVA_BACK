<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SolicitudVacacion;
use App\Models\Empleado;
use App\Services\SolicitudService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SolicitudController extends Controller
{
    protected SolicitudService $solicitudService;

    public function __construct(SolicitudService $solicitudService)
    {
        $this->solicitudService = $solicitudService;
    }

    /**
     * Listar solicitudes con filtros
     */
    public function index(Request $request): JsonResponse
    {
        $query = SolicitudVacacion::with(['empleado', 'empleado.sede', 'detalles']);

        // Filtros
        if ($request->has('estado') && $request->estado !== 'todos') {
            $query->where('estado', $request->estado);
        }

        if ($request->has('empleado_id')) {
            $query->where('empleado_id', $request->empleado_id);
        }

        // Filtro de búsqueda por nombre o CI del empleado
        if ($request->filled('buscar')) {
            $buscar = $request->buscar;
            $query->whereHas('empleado', function ($q) use ($buscar) {
                $q->where('apellido_paterno', 'like', "%{$buscar}%")
                    ->orWhere('apellido_materno', 'like', "%{$buscar}%")
                    ->orWhere('nombres', 'like', "%{$buscar}%")
                    ->orWhere('ci', 'like', "%{$buscar}%");
            });
        }

        // Filtro por sede
        if ($request->filled('sede_id')) {
            $query->whereHas('empleado', function ($q) use ($request) {
                $q->where('sede_id', $request->sede_id);
            });
        }

        if ($request->has('fecha_desde')) {
            $query->whereDate('fecha_solicitud', '>=', $request->fecha_desde);
        }

        if ($request->has('fecha_hasta')) {
            $query->whereDate('fecha_solicitud', '<=', $request->fecha_hasta);
        }

        if ($request->has('ano')) {
            $query->delAno($request->ano);
        }

        // Ordenar por más reciente
        $query->orderBy('created_at', 'desc');

        // Paginación
        $perPage = $request->get('per_page', 15);
        $solicitudes = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $solicitudes,
        ]);
    }

    /**
     * Obtener detalle de una solicitud
     */
    public function show(int $id): JsonResponse
    {
        $solicitud = SolicitudVacacion::with(['empleado', 'empleado.sede', 'empleado.historial'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $solicitud,
        ]);
    }

    /**
     * Aprobar solicitud
     */
    public function aprobar(int $id): JsonResponse
    {
        $solicitud = SolicitudVacacion::with(['empleado', 'empleado.sede'])->findOrFail($id);

        try {
            $solicitud = $this->solicitudService->aprobar($solicitud, auth()->id());

            return response()->json([
                'success' => true,
                'message' => 'Solicitud aprobada correctamente. Se descontaron ' . $solicitud->dias_solicitados . ' días.',
                'data' => $solicitud,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Rechazar solicitud
     */
    public function rechazar(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'motivo' => 'required|string|max:500',
        ]);

        $solicitud = SolicitudVacacion::findOrFail($id);

        try {
            $solicitud = $this->solicitudService->rechazar($solicitud, $request->motivo);

            return response()->json([
                'success' => true,
                'message' => 'Solicitud rechazada correctamente.',
                'data' => $solicitud,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Estadísticas de solicitudes
     */
    public function estadisticas(Request $request): JsonResponse
    {
        $ano = $request->get('ano', date('Y'));
        $estadisticas = $this->solicitudService->getEstadisticas($ano);

        return response()->json([
            'success' => true,
            'data' => $estadisticas,
        ]);
    }

    /**
     * Talento Humano programa vacaciones para un empleado
     */
    public function programarVacaciones(Request $request): JsonResponse
    {
        $request->validate([
            'empleado_id' => 'required|exists:empleados,id',
            'dias' => 'required|array|min:1',
            'dias.*.fecha' => 'required|date',
            'dias.*.tipo' => 'required|in:completo,parcial_manana,parcial_tarde',
            'tiene_reemplazo' => 'nullable|boolean',
            'nombre_reemplazo' => 'nullable|string|max:200',
            'mostrar_por_etapas' => 'nullable|boolean',
        ]);

        $empleado = Empleado::findOrFail($request->empleado_id);

        $resultado = $this->solicitudService->programarVacaciones(
            $empleado,
            $request->dias,
            $request->boolean('tiene_reemplazo', false),
            $request->nombre_reemplazo,
            $request->boolean('mostrar_por_etapas', false)
        );

        if (!$resultado['success']) {
            return response()->json([
                'success' => false,
                'message' => 'La solicitud no es válida.',
                'errors' => $resultado['errors'],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Vacaciones programadas. Pendiente recepción de documento firmado para aprobar.',
            'data' => $resultado,
        ], 201);
    }

    /**
     * Confirmar recepción de documento y aprobar vacaciones
     */
    public function confirmarDocumento(int $id): JsonResponse
    {
        $solicitud = SolicitudVacacion::with('empleado')->findOrFail($id);

        try {
            $solicitud = $this->solicitudService->confirmarDocumento($solicitud, auth()->id());

            return response()->json([
                'success' => true,
                'message' => 'Documento confirmado. Vacaciones aprobadas. Se descontaron ' . $solicitud->dias_solicitados . ' días.',
                'data' => $solicitud,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Cancelar una solicitud
     */
    public function cancelar(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'motivo' => 'required|string|max:500',
        ]);

        $solicitud = SolicitudVacacion::with('empleado')->findOrFail($id);

        try {
            $estabaAprobada = $solicitud->esAprobada();
            $diasDevueltos = $estabaAprobada ? $solicitud->dias_solicitados : 0;

            $solicitud = $this->solicitudService->cancelar($solicitud, $request->motivo, auth()->id());

            $mensaje = 'Solicitud cancelada correctamente.';
            if ($estabaAprobada) {
                $mensaje .= " Se devolvieron {$diasDevueltos} días al saldo del empleado.";
            }

            return response()->json([
                'success' => true,
                'message' => $mensaje,
                'data' => $solicitud,
                'dias_devueltos' => $diasDevueltos,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Generar formulario PDF para impresión
     */
    public function generarFormulario(int $id): JsonResponse
    {
        $solicitud = SolicitudVacacion::with(['empleado', 'empleado.sede', 'detalles'])->findOrFail($id);
        $datos = $this->solicitudService->generarDatosFormulario($solicitud);

        return response()->json([
            'success' => true,
            'data' => $datos,
        ]);
    }

    /**
     * Actualizar una solicitud existente
     * Permite editar pendientes, pendiente_documento Y aprobadas
     * Para aprobadas: recalcula automáticamente el saldo del empleado
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $solicitud = SolicitudVacacion::with(['empleado', 'detalles'])->findOrFail($id);

        // Solo rechazadas no se pueden editar
        if ($solicitud->estado === SolicitudVacacion::ESTADO_RECHAZADA) {
            return response()->json([
                'success' => false,
                'message' => 'No se pueden editar solicitudes rechazadas.',
            ], 422);
        }

        $request->validate([
            'dias' => 'required|array|min:1',
            'dias.*.fecha' => 'required|date',
            'dias.*.tipo' => 'required|in:completo,parcial_manana,parcial_tarde',
            'tiene_reemplazo' => 'nullable|boolean',
            'nombre_reemplazo' => 'nullable|string|max:200',
        ]);

        try {
            $resultado = $this->solicitudService->actualizarSolicitud(
                $solicitud,
                $request->dias,
                $request->boolean('tiene_reemplazo', false),
                $request->nombre_reemplazo
            );

            if (!$resultado['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al actualizar la solicitud.',
                    'errors' => $resultado['errors'] ?? [],
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => 'Solicitud actualizada correctamente.',
                'data' => $resultado,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Obtener vacaciones para el calendario compartido
     */
    public function vacacionesCalendario(Request $request): JsonResponse
    {
        $mes = $request->get('mes', date('m'));
        $ano = $request->get('ano', date('Y'));
        $sedeId = $request->get('sede_id');

        // Calcular primer y último día del mes
        $primerDia = "{$ano}-" . str_pad($mes, 2, '0', STR_PAD_LEFT) . "-01";
        $ultimoDia = date('Y-m-t', strtotime($primerDia));

        // Determinar estados a incluir según filtro
        $estadoFiltro = $request->get('estado', 'aprobada');
        $estados = $estadoFiltro === 'aprobada'
            ? [SolicitudVacacion::ESTADO_APROBADA]
            : [SolicitudVacacion::ESTADO_APROBADA, SolicitudVacacion::ESTADO_PENDIENTE_DOCUMENTO];

        // Obtener solicitudes según estado filtrado
        $query = SolicitudVacacion::with(['empleado', 'empleado.sede', 'detalles'])
            ->whereIn('estado', $estados)
            ->whereHas('detalles', function ($q) use ($primerDia, $ultimoDia) {
                $q->whereBetween('fecha', [$primerDia, $ultimoDia]);
            });

        // Filtrar por sede si se especifica
        if ($sedeId) {
            $query->whereHas('empleado', function ($q) use ($sedeId) {
                $q->where('sede_id', $sedeId);
            });
        }

        $solicitudes = $query->get();

        // Transformar a formato para el calendario
        $vacaciones = [];

        foreach ($solicitudes as $solicitud) {
            foreach ($solicitud->detalles as $detalle) {
                // Solo incluir días de este mes
                if ($detalle->fecha >= $primerDia && $detalle->fecha <= $ultimoDia) {
                    $empleado = $solicitud->empleado;
                    $nombres = explode(' ', $empleado->nombres);
                    $nombreCorto = $nombres[0] . ' ' . substr($empleado->apellido_paterno, 0, 1) . '.';
                    $iniciales = substr($nombres[0], 0, 1) . substr($empleado->apellido_paterno, 0, 1);

                    $vacaciones[] = [
                        'fecha' => $detalle->fecha,
                        'empleado_id' => $empleado->id,
                        'nombre_completo' => $empleado->nombre_completo,
                        'nombre_corto' => $nombreCorto,
                        'iniciales' => strtoupper($iniciales),
                        'tipo' => $detalle->tipo,
                        'sede_id' => $empleado->sede_id,
                        'sede_nombre' => $empleado->sede?->nombre ?? 'Sin sede',
                        'solicitud_id' => $solicitud->id,
                    ];
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => $vacaciones,
        ]);
    }
}
