<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SolicitudVacacion;
use App\Models\Empleado;
use App\Services\VacacionesService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class SolicitudController extends Controller
{
    protected VacacionesService $vacacionesService;

    public function __construct(VacacionesService $vacacionesService)
    {
        $this->vacacionesService = $vacacionesService;
    }

    /**
     * Listar solicitudes con filtros
     */
    public function index(Request $request): JsonResponse
    {
        $query = SolicitudVacacion::with('empleado');

        // Filtros
        if ($request->has('estado') && $request->estado !== 'todos') {
            $query->where('estado', $request->estado);
        }

        if ($request->has('empleado_id')) {
            $query->where('empleado_id', $request->empleado_id);
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
        $solicitud = SolicitudVacacion::with(['empleado', 'empleado.historial'])
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
        $solicitud = SolicitudVacacion::with('empleado')->findOrFail($id);

        if (!$solicitud->esPendiente()) {
            return response()->json([
                'success' => false,
                'message' => 'Solo se pueden aprobar solicitudes pendientes.',
            ], 422);
        }

        // Aprobar y descontar días
        $solicitud->estado = SolicitudVacacion::ESTADO_APROBADA;
        $solicitud->save();

        // Descontar del saldo del empleado
        $this->vacacionesService->descontarVacaciones(
            $solicitud->empleado,
            $solicitud->dias_solicitados,
            $solicitud->id,
            auth()->id()
        );

        return response()->json([
            'success' => true,
            'message' => 'Solicitud aprobada correctamente. Se descontaron ' . $solicitud->dias_solicitados . ' días.',
            'data' => $solicitud->fresh(['empleado']),
        ]);
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

        if (!$solicitud->esPendiente()) {
            return response()->json([
                'success' => false,
                'message' => 'Solo se pueden rechazar solicitudes pendientes.',
            ], 422);
        }

        $solicitud->estado = SolicitudVacacion::ESTADO_RECHAZADA;
        $solicitud->motivo_rechazo = $request->motivo;
        $solicitud->save();

        return response()->json([
            'success' => true,
            'message' => 'Solicitud rechazada correctamente.',
            'data' => $solicitud->fresh(['empleado']),
        ]);
    }

    /**
     * Estadísticas de solicitudes
     */
    public function estadisticas(Request $request): JsonResponse
    {
        $ano = $request->get('ano', date('Y'));

        $pendientes = SolicitudVacacion::pendientes()->delAno($ano)->count();
        $aprobadas = SolicitudVacacion::aprobadas()->delAno($ano)->count();
        $rechazadas = SolicitudVacacion::rechazadas()->delAno($ano)->count();
        $diasAprobados = SolicitudVacacion::aprobadas()->delAno($ano)->sum('dias_solicitados');

        return response()->json([
            'success' => true,
            'data' => [
                'pendientes' => $pendientes,
                'aprobadas' => $aprobadas,
                'rechazadas' => $rechazadas,
                'total' => $pendientes + $aprobadas + $rechazadas,
                'dias_aprobados' => $diasAprobados,
                'ano' => $ano,
            ],
        ]);
    }

    /**
     * RRHH programa vacaciones para un empleado
     * No requiere 5 días de anticipación
     * Las vacaciones quedan en estado 'pendiente_documento' hasta que se confirme recepción
     * Ahora acepta días individuales con tipos diferentes
     */
    public function programarVacaciones(Request $request): JsonResponse
    {
        $request->validate([
            'empleado_id' => 'required|exists:empleados,id',
            'dias' => 'required|array|min:1',
            'dias.*.fecha' => 'required|date|after_or_equal:today',
            'dias.*.tipo' => 'required|in:completo,parcial_manana,parcial_tarde',
            'tiene_reemplazo' => 'nullable|boolean',
            'nombre_reemplazo' => 'nullable|string|max:200',
        ]);

        $empleado = Empleado::findOrFail($request->empleado_id);

        // Validar días
        $validacion = $this->vacacionesService->validarDiasArray($request->dias, $empleado);

        if (!$validacion['valid']) {
            return response()->json([
                'success' => false,
                'message' => 'La solicitud no es válida.',
                'errors' => $validacion['errors'],
            ], 422);
        }

        // Ordenar días por fecha
        $diasOrdenados = collect($validacion['detalles'])->sortBy('fecha')->values();
        $primeraFecha = $diasOrdenados->first()['fecha'];
        $ultimaFecha = $diasOrdenados->last()['fecha'];

        // Detectar tipo automáticamente
        $tipoDetectado = $this->detectarTipoVacacion($diasOrdenados);

        // Crear solicitud
        $solicitud = SolicitudVacacion::create([
            'empleado_id' => $empleado->id,
            'fecha_solicitud' => Carbon::now(),
            'fecha_inicio' => $primeraFecha,
            'fecha_fin' => $ultimaFecha,
            'tipo' => $tipoDetectado,
            'dias_solicitados' => $validacion['dias'],
            'estado' => SolicitudVacacion::ESTADO_PENDIENTE_DOCUMENTO,
            'lugar_solicitud' => 'Programada por RRHH',
            'tiene_reemplazo' => $request->boolean('tiene_reemplazo', false),
            'nombre_reemplazo' => $request->tiene_reemplazo ? $request->nombre_reemplazo : null,
            'documento_entregado' => false,
        ]);

        // Crear detalles de cada día
        foreach ($validacion['detalles'] as $detalle) {
            $solicitud->detalles()->create([
                'fecha' => $detalle['fecha'],
                'tipo' => $detalle['tipo'],
                'dias_descontados' => $detalle['dias_descontados'],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Vacaciones programadas. Pendiente recepción de documento firmado para aprobar.',
            'data' => [
                'solicitud' => $solicitud->load(['empleado', 'detalles']),
                'tipo_detectado' => $tipoDetectado,
                'dias_programados' => $validacion['dias'],
                'detalles' => $validacion['detalles'],
                'saldo_actual' => $empleado->saldo_vacaciones,
                'saldo_despues_aprobar' => $validacion['saldo_resultante'],
            ],
        ], 201);
    }

    /**
     * Detecta automáticamente el tipo de vacación basado en los días seleccionados
     *
     * Tipos posibles:
     * - completa_continua: Todos los días son completos y consecutivos
     * - completa_discontinua: Todos los días son completos pero hay gaps
     * - parcial_continua: Hay días parciales y son consecutivos
     * - parcial_discontinua: Hay días parciales y hay gaps
     */
    private function detectarTipoVacacion($diasOrdenados): string
    {
        // Verificar si hay días parciales
        $tieneParciales = $diasOrdenados->contains(function ($dia) {
            return $dia['tipo'] !== 'completo';
        });

        // Verificar si son continuos (considerando solo días laborales)
        $esContinuo = $this->sonDiasContinuos($diasOrdenados);

        if ($tieneParciales) {
            return $esContinuo ? 'parcial_continua' : 'parcial_discontinua';
        } else {
            return $esContinuo ? 'completa_continua' : 'completa_discontinua';
        }
    }

    /**
     * Verifica si los días seleccionados son continuos (sin gaps de días laborales)
     */
    private function sonDiasContinuos($diasOrdenados): bool
    {
        if ($diasOrdenados->count() <= 1) {
            return true;
        }

        $fechas = $diasOrdenados->pluck('fecha')->map(fn($f) => Carbon::parse($f))->values();

        for ($i = 1; $i < $fechas->count(); $i++) {
            $fechaAnterior = $fechas[$i - 1];
            $fechaActual = $fechas[$i];

            // Calcular los días laborales entre las dos fechas
            $diasEntre = $fechaAnterior->copy()->addDay();

            while ($diasEntre->lt($fechaActual)) {
                // Si hay un día laboral (L-S) no seleccionado, no es continuo
                if ($diasEntre->dayOfWeek !== Carbon::SUNDAY) {
                    return false;
                }
                $diasEntre->addDay();
            }
        }

        return true;
    }

    /**
     * Confirmar recepción de documento y aprobar vacaciones
     */
    public function confirmarDocumento(int $id): JsonResponse
    {
        $solicitud = SolicitudVacacion::with('empleado')->findOrFail($id);

        if (!$solicitud->esPendienteDocumento()) {
            return response()->json([
                'success' => false,
                'message' => 'Solo se puede confirmar documento para solicitudes en estado "pendiente_documento".',
            ], 422);
        }

        // Marcar documento como entregado y aprobar
        $solicitud->documento_entregado = true;
        $solicitud->estado = SolicitudVacacion::ESTADO_APROBADA;
        $solicitud->save();

        // Descontar días del saldo
        $this->vacacionesService->descontarVacaciones(
            $solicitud->empleado,
            $solicitud->dias_solicitados,
            $solicitud->id,
            auth()->id()
        );

        return response()->json([
            'success' => true,
            'message' => 'Documento confirmado. Vacaciones aprobadas. Se descontaron ' . $solicitud->dias_solicitados . ' días.',
            'data' => $solicitud->fresh(['empleado']),
        ]);
    }

    /**
     * Generar formulario PDF para impresión
     */
    public function generarFormulario(int $id): JsonResponse
    {
        $solicitud = SolicitudVacacion::with('empleado')->findOrFail($id);
        $empleado = $solicitud->empleado;

        // Datos para el formulario
        $datosFormulario = [
            'empleado' => [
                'nombre_completo' => $empleado->nombre_completo,
                'ci' => $empleado->ci,
                'cargo' => $empleado->cargo,
                'sede' => $empleado->sede,
                'fecha_ingreso' => $empleado->fecha_ingreso->format('d/m/Y'),
                'anos_servicio' => $empleado->anos_servicio,
                'dias_correspondientes' => $empleado->dias_correspondientes,
                'genero' => $empleado->genero ?? 'No especificado',
                'tipo_contrato' => $empleado->tipo_contrato === 'medio_tiempo' ? 'Medio Tiempo' : 'Tiempo Completo',
            ],
            'solicitud' => [
                'id' => $solicitud->id,
                'fecha_solicitud' => $solicitud->fecha_solicitud->format('d/m/Y'),
                'fecha_inicio' => $solicitud->fecha_inicio->format('d/m/Y'),
                'fecha_fin' => $solicitud->fecha_fin->format('d/m/Y'),
                'dias_solicitados' => $solicitud->dias_solicitados,
                'tipo' => $this->traducirTipo($solicitud->tipo),
                'reemplazo' => $solicitud->texto_reemplazo,
                'estado' => $this->traducirEstado($solicitud->estado),
            ],
            'saldo' => [
                'actual' => $empleado->saldo_vacaciones,
                'despues' => $empleado->saldo_vacaciones - $solicitud->dias_solicitados,
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $datosFormulario,
        ]);
    }

    /**
     * Traduce el tipo de vacación a español
     */
    private function traducirTipo(?string $tipo): string
    {
        if (!$tipo) {
            return 'No especificado';
        }

        return match ($tipo) {
            'completo' => 'Día Completo',
            'parcial_manana' => 'Medio Día (Mañana)',
            'parcial_tarde' => 'Medio Día (Tarde)',
            'completa_continua' => 'Completa Continua',
            'completa_discontinua' => 'Completa Discontinua',
            'parcial_continua' => 'Parcial Continua',
            'parcial_discontinua' => 'Parcial Discontinua',
            default => $tipo,
        };
    }

    /**
     * Traduce el estado a español
     */
    private function traducirEstado(string $estado): string
    {
        return match ($estado) {
            'pendiente' => 'Pendiente',
            'pendiente_documento' => 'Pendiente Documento',
            'aprobada' => 'Aprobada',
            'rechazada' => 'Rechazada',
            default => $estado,
        };
    }
}
