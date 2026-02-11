<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Empleado;
use App\Models\SolicitudVacacion;
use App\Services\VacacionesService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class EmpleadoPublicoController extends Controller
{
    protected VacacionesService $vacacionesService;

    public function __construct(VacacionesService $vacacionesService)
    {
        $this->vacacionesService = $vacacionesService;
    }

    /**
     * Buscar empleado por CI y Fecha de Ingreso (público, sin auth)
     */
    public function buscarEmpleado(Request $request): JsonResponse
    {
        $request->validate([
            'ci' => 'required|string',
            'fecha_ingreso' => 'required|date',
        ], [
            'ci.required' => 'El CI es obligatorio.',
            'fecha_ingreso.required' => 'La fecha de ingreso es obligatoria.',
            'fecha_ingreso.date' => 'La fecha de ingreso no es válida.',
        ]);

        // Normalizar CI: eliminar ceros a la izquierda y espacios
        $ciNormalizado = ltrim(trim($request->ci), '0');

        // Parsear la fecha con Carbon para asegurar formato correcto
        $fechaIngreso = Carbon::parse($request->fecha_ingreso)->format('Y-m-d');

        // Buscar empleado - intentar tanto con CI original como normalizado
        $empleado = Empleado::activos()
            ->where(function ($q) use ($ciNormalizado, $request) {
                $q->where('ci', $request->ci)
                    ->orWhere('ci', $ciNormalizado)
                    ->orWhere('ci', ltrim($request->ci, '0'));
            })
            ->whereDate('fecha_ingreso', $fechaIngreso)
            ->with(['sede', 'solicitudes' => function ($query) {
                $query->orderBy('created_at', 'desc')->limit(10);
            }])
            ->first();

        if (!$empleado) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró un empleado con el CI y fecha de ingreso proporcionados. Verifique sus datos.',
            ], 404);
        }

        // Fecha mínima para solicitar (1 día desde hoy - desde mañana)
        $fechaMinima = Carbon::now()->addDays(1)->format('Y-m-d');

        return response()->json([
            'success' => true,
            'data' => [
                'empleado' => $empleado,
                'puede_solicitar' => true, // Permite aunque saldo sea 0 o negativo
                'fecha_minima_solicitud' => $fechaMinima,
            ],
        ]);
    }

    /**
     * Crear solicitud de vacaciones (público, sin auth)
     */
    public function crearSolicitud(Request $request): JsonResponse
    {
        // Fecha mínima: 1 día desde hoy
        $fechaMinima = Carbon::now()->addDays(1)->format('Y-m-d');

        $request->validate([
            'empleado_id' => 'required|exists:empleados,id',
            'fecha_inicio' => 'required|date|after_or_equal:' . $fechaMinima,
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
            'tipo' => 'required|in:completo,parcial_manana,parcial_tarde',
            'lugar_solicitud' => 'nullable|string|max:255',
        ], [
            'fecha_inicio.after_or_equal' => 'La fecha de inicio debe ser al menos 1 día después de hoy (desde mañana).',
        ]);

        $empleado = Empleado::findOrFail($request->empleado_id);

        // Validar solicitud
        $validacion = $this->vacacionesService->validarSolicitud(
            $request->only(['fecha_inicio', 'fecha_fin', 'tipo']),
            $empleado
        );

        if (!$validacion['valid']) {
            return response()->json([
                'success' => false,
                'message' => 'La solicitud no es válida.',
                'errors' => $validacion['errors'],
            ], 422);
        }

        // Crear solicitud (sin descontar aún, estado pendiente)
        $solicitud = SolicitudVacacion::create([
            'empleado_id' => $empleado->id,
            'fecha_solicitud' => Carbon::now(),
            'fecha_inicio' => $request->fecha_inicio,
            'fecha_fin' => $request->fecha_fin,
            'tipo' => $request->tipo,
            'dias_solicitados' => $validacion['dias'],
            'estado' => SolicitudVacacion::ESTADO_PENDIENTE,
            'lugar_solicitud' => $request->lugar_solicitud,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Solicitud creada correctamente. Pendiente de aprobación por Talento Humano.',
            'data' => [
                'solicitud' => $solicitud->load('empleado'),
                'dias_solicitados' => $validacion['dias'],
                'saldo_actual' => $empleado->saldo_vacaciones,
                'saldo_resultante' => $validacion['saldo_resultante'],
                'advertencia_negativo' => $validacion['advertencia_negativo'],
            ],
        ], 201);
    }

    /**
     * Crear solicitud con días individuales (nuevo formato calendario)
     */
    public function crearSolicitudConDias(Request $request): JsonResponse
    {
        // Fecha mínima: 1 día desde hoy
        $fechaMinima = Carbon::now()->addDays(1)->format('Y-m-d');

        $request->validate([
            'empleado_id' => 'required|exists:empleados,id',
            'dias' => 'required|array|min:1',
            'dias.*.fecha' => 'required|date|after_or_equal:' . $fechaMinima,
            'dias.*.tipo' => 'required|in:completo,parcial_manana,parcial_tarde',
            'lugar_solicitud' => 'nullable|string|max:255',
            'reemplazo' => 'nullable|string|max:255',
        ], [
            'dias.*.fecha.after_or_equal' => 'Las vacaciones deben solicitarse con al menos 1 día de anticipación (desde mañana).',
        ]);

        $empleado = \App\Models\Empleado::findOrFail($request->empleado_id);
        $diasData = collect($request->dias)->sortBy('fecha');

        // Validar y calcular usando el servicio
        $validacion = $this->vacacionesService->calcularDiasDesdeArray($diasData->toArray(), $empleado);

        $primeraFecha = $diasData->first()['fecha'];
        $ultimaFecha = $diasData->last()['fecha'];

        // Detectar tipo de vacación
        $tieneParciales = $diasData->contains(fn($d) => $d['tipo'] !== 'completo');
        $esContinuo = $this->sonDiasContinuos($diasData);

        if ($tieneParciales) {
            $tipoDetectado = $esContinuo ? 'parcial_continua' : 'parcial_discontinua';
        } else {
            $tipoDetectado = $esContinuo ? 'completa_continua' : 'completa_discontinua';
        }

        // Crear solicitud
        $solicitud = SolicitudVacacion::create([
            'empleado_id' => $empleado->id,
            'fecha_solicitud' => Carbon::now(),
            'fecha_inicio' => $primeraFecha,
            'fecha_fin' => $ultimaFecha,
            'tipo' => $tipoDetectado,
            'dias_solicitados' => $validacion['total'],
            'estado' => SolicitudVacacion::ESTADO_PENDIENTE,
            'lugar_solicitud' => $request->lugar_solicitud,
            'texto_reemplazo' => $request->reemplazo,
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
            'message' => 'Solicitud creada correctamente. Pendiente de aprobación por Talento Humano.',
            'data' => [
                'solicitud' => $solicitud->load('empleado', 'detalles'),
                'dias_solicitados' => $validacion['total'],
                'saldo_actual' => $empleado->saldo_vacaciones,
                'saldo_resultante' => $validacion['saldo_resultante'],
            ],
        ], 201);
    }

    /**
     * Verificar si los días son continuos
     */
    private function sonDiasContinuos($dias): bool
    {
        $fechas = $dias->pluck('fecha')->map(fn($f) => Carbon::parse($f))->sortBy(fn($f) => $f);

        $anterior = null;
        foreach ($fechas as $fecha) {
            if ($anterior) {
                $diferencia = $fecha->diffInDays($anterior);
                // Permitir saltos de domingo (diferencia de 2 si es L-S)
                if ($diferencia > 2 || ($diferencia == 2 && !$anterior->isSaturday())) {
                    return false;
                }
            }
            $anterior = $fecha;
        }
        return true;
    }

    /**
     * Obtener datos para el formulario de vacaciones
     */
    public function datosFormulario(int $solicitudId): JsonResponse
    {
        $solicitud = SolicitudVacacion::with(['empleado', 'empleado.sede', 'detalles'])->findOrFail($solicitudId);

        // Usar el servicio para generar datos del formulario (incluye etapas)
        $solicitudService = app(\App\Services\SolicitudService::class);
        $datos = $solicitudService->generarDatosFormulario($solicitud);

        return response()->json([
            'success' => true,
            'data' => $datos,
        ]);
    }

    /**
     * Calcular días entre fechas (para preview en frontend)
     */
    public function calcularDias(Request $request): JsonResponse
    {
        $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
            'tipo' => 'required|in:completo,parcial_manana,parcial_tarde',
        ]);

        $fechaInicio = Carbon::parse($request->fecha_inicio);
        $fechaFin = Carbon::parse($request->fecha_fin);

        $dias = $this->vacacionesService->calcularDiasHabiles(
            $fechaInicio,
            $fechaFin,
            $request->tipo
        );

        // Validar sábado parcial
        $esSabadoParcial = false;
        if ($request->tipo !== 'completo' && $fechaInicio->isSaturday() && $fechaInicio->eq($fechaFin)) {
            $esSabadoParcial = true;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'dias_habiles' => $dias,
                'error_sabado_parcial' => $esSabadoParcial,
            ],
        ]);
    }
}
