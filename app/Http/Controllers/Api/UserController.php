<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Listar usuarios con paginación y filtros
     */
    public function index(Request $request)
    {
        $query = User::query();

        // Búsqueda por CI o nombre
        if ($request->filled('buscar')) {
            $buscar = $request->buscar;
            $query->where(function ($q) use ($buscar) {
                $q->where('ci', 'like', "%{$buscar}%")
                    ->orWhere('name', 'like', "%{$buscar}%")
                    ->orWhere('apellido_paterno', 'like', "%{$buscar}%")
                    ->orWhere('apellido_materno', 'like', "%{$buscar}%");
            });
        }

        // Filtro por rol
        if ($request->filled('rol_id')) {
            $query->where('rol_id', $request->rol_id);
        }

        // Filtro por estado
        if ($request->filled('activo')) {
            $query->where('activo', $request->activo === 'true' || $request->activo === '1');
        }

        // Ordenamiento
        $ordenarPor = $request->get('ordenar_por', 'created_at');
        $orden = $request->get('orden', 'desc');
        $query->orderBy($ordenarPor, $orden);

        // Paginación
        $porPagina = $request->get('por_pagina', 15);
        $usuarios = $query->paginate($porPagina);

        return response()->json($usuarios);
    }

    /**
     * Crear nuevo usuario
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'ci' => ['required', 'string', 'max:20', 'unique:users,ci', 'regex:/^[0-9]+(-[0-9A-Za-z]+)?$/'],
            'name' => 'required|string|max:255',
            'apellido_paterno' => 'required|string|max:255',
            'apellido_materno' => 'nullable|string|max:255',
            'rol_id' => 'required|exists:roles,id',
        ], [
            'ci.required' => 'El CI es obligatorio',
            'ci.unique' => 'Este CI ya está registrado',
            'ci.regex' => 'El formato del CI no es válido',
            'name.required' => 'El nombre es obligatorio',
            'apellido_paterno.required' => 'El apellido paterno es obligatorio',
            'rol_id.required' => 'El rol es obligatorio',
            'rol_id.exists' => 'El rol seleccionado no existe',
        ]);

        // Password inicial es el CI
        $validated['password'] = Hash::make($validated['ci']);
        $validated['must_change_password'] = true;
        $validated['activo'] = true;

        $user = User::create($validated);

        return response()->json([
            'message' => 'Usuario creado exitosamente',
            'data' => $user
        ], 201);
    }

    /**
     * Mostrar detalle de usuario
     */
    public function show($id)
    {
        $user = User::findOrFail($id);
        return response()->json($user);
    }

    /**
     * Actualizar usuario
     */
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'ci' => ['required', 'string', 'max:20', Rule::unique('users')->ignore($user->id), 'regex:/^[0-9]+(-[0-9A-Za-z]+)?$/'],
            'name' => 'required|string|max:255',
            'apellido_paterno' => 'required|string|max:255',
            'apellido_materno' => 'nullable|string|max:255',
            'rol_id' => 'required|exists:roles,id',
            'activo' => 'boolean',
        ], [
            'ci.required' => 'El CI es obligatorio',
            'ci.unique' => 'Este CI ya está registrado',
            'ci.regex' => 'El formato del CI no es válido',
            'name.required' => 'El nombre es obligatorio',
            'apellido_paterno.required' => 'El apellido paterno es obligatorio',
            'rol_id.required' => 'El rol es obligatorio',
            'rol_id.exists' => 'El rol seleccionado no existe',
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'Usuario actualizado exitosamente',
            'data' => $user
        ]);
    }

    /**
     * Desactivar usuario (soft delete lógico)
     */
    public function destroy($id)
    {
        $user = User::findOrFail($id);

        // No permitir desactivarse a sí mismo
        if ($user->id === auth()->id()) {
            return response()->json([
                'message' => 'No puedes desactivar tu propia cuenta'
            ], 422);
        }

        $user->update(['activo' => false]);

        return response()->json([
            'message' => 'Usuario desactivado exitosamente'
        ]);
    }

    /**
     * Restablecer contraseña a CI
     */
    public function resetPassword($id)
    {
        $user = User::findOrFail($id);

        $user->update([
            'password' => Hash::make($user->ci),
            'must_change_password' => true,
        ]);

        return response()->json([
            'message' => 'Contraseña restablecida exitosamente. La nueva contraseña es el CI del usuario.'
        ]);
    }
}
