<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Rol;
use Illuminate\Http\Request;

class RolController extends Controller
{
    /**
     * Listar roles de SIGVA (sistema_id = 3)
     */
    public function index(Request $request)
    {
        $query = Rol::where('sistema_id', 3)->withCount('usuarios');

        // Búsqueda
        if ($request->filled('buscar')) {
            $buscar = $request->buscar;
            $query->where('nombres', 'like', "%{$buscar}%");
        }

        // Sin paginación para select
        if ($request->get('sin_paginar') === 'true') {
            return response()->json(Rol::where('sistema_id', 3)->get());
        }

        $porPagina = $request->get('por_pagina', 15);
        $roles = $query->orderBy('nombres')->paginate($porPagina);

        return response()->json($roles);
    }

    /**
     * Crear nuevo rol
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:100',
        ], [
            'nombre.required' => 'El nombre del rol es obligatorio',
        ]);

        $rol = Rol::create([
            'nombres' => $validated['nombre'],
            'sistema_id' => 3,
        ]);

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
        $rol = Rol::withCount('usuarios')->where('sistema_id', 3)->findOrFail($id);
        return response()->json($rol);
    }

    /**
     * Actualizar rol
     */
    public function update(Request $request, $id)
    {
        $rol = Rol::where('sistema_id', 3)->findOrFail($id);

        $validated = $request->validate([
            'nombre' => 'required|string|max:100',
        ], [
            'nombre.required' => 'El nombre del rol es obligatorio',
        ]);

        $rol->update([
            'nombres' => $validated['nombre'],
        ]);

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
        $rol = Rol::withCount('usuarios')->where('sistema_id', 3)->findOrFail($id);

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
