<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Rol;
use Illuminate\Http\Request;

class RolController extends Controller
{
    /**
     * Listar roles con paginación
     */
    public function index(Request $request)
    {
        $query = Rol::withCount('usuarios');

        // Búsqueda
        if ($request->filled('buscar')) {
            $buscar = $request->buscar;
            $query->where(function ($q) use ($buscar) {
                $q->where('nombre', 'like', "%{$buscar}%")
                    ->orWhere('descripcion', 'like', "%{$buscar}%");
            });
        }

        // Filtro por estado
        if ($request->filled('activo')) {
            $query->where('activo', $request->activo === 'true' || $request->activo === '1');
        }

        // Sin paginación para select
        if ($request->get('sin_paginar') === 'true') {
            return response()->json(Rol::where('activo', true)->get());
        }

        $porPagina = $request->get('por_pagina', 15);
        $roles = $query->orderBy('nombre')->paginate($porPagina);

        return response()->json($roles);
    }

    /**
     * Crear nuevo rol
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:100|unique:roles,nombre',
            'descripcion' => 'nullable|string|max:255',
        ], [
            'nombre.required' => 'El nombre del rol es obligatorio',
            'nombre.unique' => 'Este nombre de rol ya existe',
        ]);

        $validated['activo'] = true;
        $rol = Rol::create($validated);

        return response()->json([
            'message' => 'Rol creado exitosamente',
            'data' => $rol
        ], 201);
    }

    /**
     * Mostrar detalle de rol
     */
    public function show($id)
    {
        $rol = Rol::withCount('usuarios')->findOrFail($id);
        return response()->json($rol);
    }

    /**
     * Actualizar rol
     */
    public function update(Request $request, $id)
    {
        $rol = Rol::findOrFail($id);

        $validated = $request->validate([
            'nombre' => 'required|string|max:100|unique:roles,nombre,' . $id,
            'descripcion' => 'nullable|string|max:255',
            'activo' => 'boolean',
        ], [
            'nombre.required' => 'El nombre del rol es obligatorio',
            'nombre.unique' => 'Este nombre de rol ya existe',
        ]);

        $rol->update($validated);

        return response()->json([
            'message' => 'Rol actualizado exitosamente',
            'data' => $rol
        ]);
    }

    /**
     * Eliminar rol (solo si no tiene usuarios)
     */
    public function destroy($id)
    {
        $rol = Rol::withCount('usuarios')->findOrFail($id);

        if ($rol->usuarios_count > 0) {
            return response()->json([
                'message' => 'No se puede eliminar un rol que tiene usuarios asignados'
            ], 422);
        }

        $rol->delete();

        return response()->json([
            'message' => 'Rol eliminado exitosamente'
        ]);
    }
}
