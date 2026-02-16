<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sede;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SedeController extends Controller
{
    /**
     * Listar sedes
     */
    public function index(Request $request): JsonResponse
    {
        $query = Sede::query();

        if ($request->filled('buscar')) {
            $buscar = $request->buscar;
            $query->where(function ($q) use ($buscar) {
                $q->where('nombre', 'like', "%{$buscar}%")
                    ->orWhere('abreviacion', 'like', "%{$buscar}%")
                    ->orWhere('departamento', 'like', "%{$buscar}%");
            });
        }

        if ($request->has('activo')) {
            $query->where('activo', $request->boolean('activo'));
        }

        // Sin paginación para select
        if ($request->boolean('all')) {
            return response()->json([
                'success' => true,
                'data' => $query->orderBy('nombre')->get(),
            ]);
        }

        $perPage = $request->get('per_page', 15);
        $sedes = $query->orderBy('nombre')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $sedes,
        ]);
    }

    /**
     * Crear sede
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:100|unique:sedes,nombre',
            'abreviacion' => 'required|string|max:10|unique:sedes,abreviacion',
            'departamento' => 'required|string|max:100',
            'activo' => 'boolean',
        ], [
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.unique' => 'Ya existe una sede con este nombre.',
            'abreviacion.required' => 'La abreviación es obligatoria.',
            'abreviacion.unique' => 'Ya existe una sede con esta abreviación.',
            'departamento.required' => 'El departamento es obligatorio.',
        ]);

        if (isset($validated['abreviacion'])) {
            $validated['sigla'] = $validated['abreviacion'];
        }

        $sede = Sede::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Sede creada correctamente.',
            'data' => $sede,
        ], 201);
    }

    /**
     * Mostrar sede
     */
    public function show(int $id): JsonResponse
    {
        $sede = Sede::withCount('empleados')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $sede,
        ]);
    }

    /**
     * Actualizar sede
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $sede = Sede::findOrFail($id);

        $validated = $request->validate([
            'nombre' => 'required|string|max:100|unique:sedes,nombre,' . $id,
            'abreviacion' => 'required|string|max:10|unique:sedes,abreviacion,' . $id,
            'departamento' => 'required|string|max:100',
            'activo' => 'boolean',
        ]);

        if (isset($validated['abreviacion'])) {
            $validated['sigla'] = $validated['abreviacion'];
        }

        $sede->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Sede actualizada correctamente.',
            'data' => $sede,
        ]);
    }

    /**
     * Eliminar sede
     */
    public function destroy(int $id): JsonResponse
    {
        $sede = Sede::withCount('empleados')->findOrFail($id);

        if ($sede->empleados_count > 0) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar la sede porque tiene empleados asignados.',
            ], 422);
        }

        $sede->delete();

        return response()->json([
            'success' => true,
            'message' => 'Sede eliminada correctamente.',
        ]);
    }
}
