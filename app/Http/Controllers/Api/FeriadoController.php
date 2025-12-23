<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Feriado;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class FeriadoController extends Controller
{
    /**
     * Listar feriados con filtros
     */
    public function index(Request $request): JsonResponse
    {
        $query = Feriado::with('sede');

        // Filtro por tipo
        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }

        // Filtro por sede
        if ($request->filled('sede_id')) {
            $query->where('sede_id', $request->sede_id);
        }

        // Filtro por año
        if ($request->filled('ano')) {
            $query->whereYear('fecha', $request->ano);
        }

        // Búsqueda
        if ($request->filled('buscar')) {
            $query->where('nombre', 'like', "%{$request->buscar}%");
        }

        // Sin paginación para calendario
        if ($request->boolean('all')) {
            return response()->json([
                'success' => true,
                'data' => $query->orderBy('fecha')->get(),
            ]);
        }

        $perPage = $request->get('per_page', 15);
        $feriados = $query->orderBy('fecha', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $feriados,
        ]);
    }

    /**
     * Obtener feriados para una sede (nacionales + departamentales de esa sede)
     * Ruta pública para el calendario
     */
    public function porSede(Request $request): JsonResponse
    {
        $sedeId = $request->get('sede_id');
        $ano = $request->get('ano', date('Y'));

        $query = Feriado::activos()->whereYear('fecha', $ano);

        if ($sedeId) {
            $query->where(function ($q) use ($sedeId) {
                $q->where('tipo', Feriado::TIPO_NACIONAL)
                    ->orWhere('sede_id', $sedeId);
            });
        } else {
            // Si no se especifica sede, solo nacionales
            $query->where('tipo', Feriado::TIPO_NACIONAL);
        }

        $feriados = $query->orderBy('fecha')->get(['id', 'nombre', 'fecha', 'tipo']);

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

        $feriado = Feriado::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Feriado creado correctamente.',
            'data' => $feriado->load('sede'),
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
}
