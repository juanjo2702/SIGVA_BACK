<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Feriado;
use App\Services\FeriadoService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class FeriadoController extends Controller
{
    protected FeriadoService $feriadoService;

    public function __construct(FeriadoService $feriadoService)
    {
        $this->feriadoService = $feriadoService;
    }

    /**
     * Listar feriados con filtros
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Feriado::with('sede');

            // Filtro por tipo
            if ($request->filled('tipo')) {
                $query->where('tipo', $request->tipo);
            }

            // Filtro por sede
            if ($request->filled('sede_id')) {
                $query->where('sede_id', $request->sede_id);
            }

            // Filtro por año (incluyendo recurrentes)
            if ($request->filled('ano')) {
                $ano = $request->ano;
                $query->where(function ($q) use ($ano) {
                    $q->whereYear('fecha', $ano)
                        ->orWhere('es_recurrente', true);
                });
            }

            // Búsqueda
            if ($request->filled('buscar')) {
                $query->where('nombre', 'like', "%{$request->buscar}%");
            }

            // Sin paginación para calendario
            if ($request->boolean('all')) {
                $datos = $query->orderBy('fecha')->get();
                // Ajustar fechas recurrentes si se filtró por año
                if ($request->filled('ano')) {
                    $ano = (int)$request->ano;
                    $datos->transform(function ($f) use ($ano) {
                        if ($f->es_recurrente && $f->fecha instanceof \Carbon\Carbon) {
                            $f->fecha = $f->fecha->copy()->setYear($ano);
                        }
                        return $f;
                    });
                    $datos = $datos->sortBy('fecha')->values();
                }
                return response()->json([
                    'success' => true,
                    'data' => $datos,
                ]);
            }

            $perPage = $request->get('per_page', 15);
            $feriados = $query->orderBy('fecha', 'desc')->paginate($perPage);

            // Ajustar fechas recurrentes en la paginación
            if ($request->filled('ano')) {
                $ano = (int)$request->ano;
                $feriados->getCollection()->transform(function ($f) use ($ano) {
                    if ($f->es_recurrente && $f->fecha instanceof \Carbon\Carbon) {
                        $f->fecha = $f->fecha->copy()->setYear($ano);
                    }
                    return $f;
                });
            }

            return response()->json([
                'success' => true,
                'data' => $feriados,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar feriados: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Obtener feriados para una sede (nacionales + departamentales de esa sede)
     * Ruta pública para el calendario
     */
    public function porSede(Request $request): JsonResponse
    {
        $sedeId = $request->get('sede_id');
        $ano = $request->get('ano', date('Y'));

        // Usar scopeDelAno que ya incluye recurrentes
        $query = Feriado::activos()->delAno($ano);

        if ($sedeId) {
            $query->where(function ($q) use ($sedeId) {
                $q->where('tipo', Feriado::TIPO_NACIONAL)
                    ->orWhere('sede_id', $sedeId);
            });
        } else {
            // Si no se especifica sede, solo nacionales
            $query->where('tipo', Feriado::TIPO_NACIONAL);
        }

        $feriados = $query->orderBy('fecha')->get(['id', 'nombre', 'fecha', 'tipo', 'es_recurrente']);

        // Ajustar fechas recurrentes
        $feriados->transform(function ($f) use ($ano) {
            $ano = (int)$ano;
            if ($f->es_recurrente && $f->fecha instanceof \Carbon\Carbon) {
                $f->fecha = $f->fecha->copy()->setYear($ano);
            }
            return $f;
        });

        // Reordenar cronológicamente
        $feriados = $feriados->sortBy('fecha')->values();

        return response()->json([
            'success' => true,
            'data' => $feriados,
        ]);
    }

    /**
     * Crear feriado
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:200',
            'fecha' => 'required|date',
            'tipo' => 'required|in:nacional,departamental',
            'sede_id' => 'nullable|required_if:tipo,departamental|exists:sedes,id',
            'activo' => 'boolean',
            'es_recurrente' => 'boolean',
            'procesar_devoluciones' => 'boolean', // Si debe procesar devoluciones automáticamente
        ], [
            'nombre.required' => 'El nombre es obligatorio.',
            'fecha.required' => 'La fecha es obligatoria.',
            'tipo.required' => 'El tipo es obligatorio.',
            'sede_id.required_if' => 'La sede es obligatoria para feriados departamentales.',
        ]);

        // Si es nacional, sede_id debe ser null
        if ($validated['tipo'] === Feriado::TIPO_NACIONAL) {
            $validated['sede_id'] = null;
        }

        // Verificar duplicado
        $existe = Feriado::where('fecha', $validated['fecha'])
            ->where('sede_id', $validated['sede_id'])
            ->exists();

        if ($existe) {
            return response()->json([
                'success' => false,
                'message' => 'Ya existe un feriado para esta fecha y sede.',
            ], 422);
        }

        // Crear el feriado
        $procesarDevoluciones = $validated['procesar_devoluciones'] ?? true;
        unset($validated['procesar_devoluciones']);

        $feriado = Feriado::create($validated);

        $devolucionesResult = null;

        // Procesar devoluciones automáticamente
        if ($procesarDevoluciones) {
            $devolucionesResult = $this->feriadoService->procesarDevolucionesPorFeriado($feriado);
        }

        return response()->json([
            'success' => true,
            'message' => $devolucionesResult && $devolucionesResult['empleados_afectados'] > 0
                ? "Feriado creado. Se devolvieron {$devolucionesResult['dias_devueltos']} días a {$devolucionesResult['empleados_afectados']} empleados."
                : 'Feriado creado correctamente.',
            'data' => $feriado->load('sede'),
            'devoluciones' => $devolucionesResult,
        ], 201);
    }

    /**
     * Mostrar feriado
     */
    public function show(int $id): JsonResponse
    {
        $feriado = Feriado::with('sede')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $feriado,
        ]);
    }

    /**
     * Actualizar feriado
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $feriado = Feriado::findOrFail($id);

        $validated = $request->validate([
            'nombre' => 'required|string|max:200',
            'fecha' => 'required|date',
            'tipo' => 'required|in:nacional,departamental',
            'sede_id' => 'nullable|required_if:tipo,departamental|exists:sedes,id',
            'activo' => 'boolean',
            'es_recurrente' => 'boolean',
        ]);

        // Si es nacional, sede_id debe ser null
        if ($validated['tipo'] === Feriado::TIPO_NACIONAL) {
            $validated['sede_id'] = null;
        }

        // Verificar duplicado (excluyendo el actual)
        $existe = Feriado::where('fecha', $validated['fecha'])
            ->where('sede_id', $validated['sede_id'])
            ->where('id', '!=', $id)
            ->exists();

        if ($existe) {
            return response()->json([
                'success' => false,
                'message' => 'Ya existe un feriado para esta fecha y sede.',
            ], 422);
        }

        $feriado->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Feriado actualizado correctamente.',
            'data' => $feriado->load('sede'),
        ]);
    }

    /**
     * Eliminar feriado
     */
    public function destroy(int $id): JsonResponse
    {
        $feriado = Feriado::findOrFail($id);
        $feriado->delete();

        return response()->json([
            'success' => true,
            'message' => 'Feriado eliminado correctamente.',
        ]);
    }

    /**
     * Preview de empleados afectados por un feriado
     */
    public function previewAfectados(int $id): JsonResponse
    {
        $feriado = Feriado::findOrFail($id);
        $preview = $this->feriadoService->previewAfectados($feriado);

        return response()->json([
            'success' => true,
            'data' => $preview,
        ]);
    }

    /**
     * Procesar devoluciones para un feriado existente
     */
    public function procesarDevoluciones(int $id): JsonResponse
    {
        $feriado = Feriado::findOrFail($id);

        try {
            $resultado = $this->feriadoService->procesarDevolucionesPorFeriado($feriado);

            return response()->json([
                'success' => true,
                'message' => $resultado['empleados_afectados'] > 0
                    ? "Se devolvieron {$resultado['dias_devueltos']} días a {$resultado['empleados_afectados']} empleados."
                    : 'No hay vacaciones para devolver en esta fecha.',
                'data' => $resultado,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar devoluciones: ' . $e->getMessage(),
            ], 500);
        }
    }
}
