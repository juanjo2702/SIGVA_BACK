<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Login para RRHH
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'ci' => 'required|string',
            'password' => 'required|string',
        ]);

        // Buscar usuario por CI
        $user = \App\Models\User::where('ci', $request->ci)->first();

        // Verificar credenciales con attempt (JWT lo hace automáticamente, pero podemos pre-verificar si queremos)
        // Sin embargo, para JWT standard, usamos auth()->attempt.
        // Como estamos modificando el flow, eliminamos la verificacion manual si usamos attempt abajo.
        // Pero el codigo original hacia verificaciones manuales.
        // Vamos a simplificar usando attempt.


        // Verificar que el usuario esté activo
        if (!$user->activo) {
            throw ValidationException::withMessages([
                'ci' => ['Esta cuenta ha sido desactivada.'],
            ]);
        }

        // JWT Auth
        $credentials = $request->only('ci', 'password');

        if (! $token = auth('api')->attempt($credentials)) {
            throw ValidationException::withMessages([
                'ci' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        // Get the authenticated user
        $user = auth('api')->user();
        $user->load(['roles.permissions', 'sede', 'persona']);

        // Verificar que el usuario esté activo
        if (!$user->activo) {
             auth('api')->logout();
            throw ValidationException::withMessages([
                'ci' => ['Esta cuenta ha sido desactivada.'],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Inicio de sesión exitoso.',
            'data' => [
                'user' => $user,
                'token' => $token,
                'token_type' => 'bearer',
                'expires_in' => auth('api')->factory()->getTTL() * 60,
                'must_change_password' => $user->must_change_password,
            ],
        ]);
    }

    /**
     * Cambiar contraseña
     */
    public function cambiarPassword(Request $request): JsonResponse
    {
        $request->validate([
            'password_actual' => 'required|string',
            'password_nuevo' => 'required|string|min:6|confirmed',
        ]);

        $user = $request->user();

        // Verificar contraseña actual
        if (!Hash::check($request->password_actual, $user->password)) {
            throw ValidationException::withMessages([
                'password_actual' => ['La contraseña actual es incorrecta.'],
            ]);
        }

        // Actualizar contraseña
        $user->password = Hash::make($request->password_nuevo);
        $user->must_change_password = false;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Contraseña actualizada correctamente.',
        ]);
    }

    /**
     * Logout
     */
    public function logout(Request $request): JsonResponse
    {
        auth('api')->logout();

        return response()->json([
            'success' => true,
            'message' => 'Sesión cerrada correctamente.',
        ]);
    }

    /**
     * Obtener usuario actual
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load(['roles.permissions', 'sede', 'persona']);
        return response()->json([
            'success' => true,
            'data' => $user,
        ]);
    }
}
