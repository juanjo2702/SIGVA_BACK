<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EmpleadoPublicoController;
use App\Http\Controllers\Api\EmpleadoController;
use App\Http\Controllers\Api\SolicitudController;
use App\Http\Controllers\Api\ReporteController;

/*
|--------------------------------------------------------------------------
| API Routes - SIGVA
|--------------------------------------------------------------------------
*/

// =============================================
// RUTAS PÚBLICAS (sin autenticación)
// Rate limiting: 15 peticiones por minuto por IP
// =============================================

// Autenticación (rate limit más estricto para login)
Route::middleware('throttle:5,1')->post('/login', [AuthController::class, 'login']);

// Portal empleado - rutas públicas con rate limiting
Route::middleware('throttle:15,1')->group(function () {
    // Búsqueda por CI
    Route::get('/empleados/buscar/{ci}', [EmpleadoPublicoController::class, 'buscarPorCi']);

    // Crear solicitud de vacaciones
    Route::post('/solicitudes', [EmpleadoPublicoController::class, 'crearSolicitud']);

    // Crear solicitud con días individuales (nuevo formato calendario)
    Route::post('/solicitudes/con-dias', [EmpleadoPublicoController::class, 'crearSolicitudConDias']);

    // Obtener datos para formulario de impresión
    Route::get('/solicitudes/{id}/formulario', [EmpleadoPublicoController::class, 'datosFormulario']);

    // Calcular días hábiles (preview)
    Route::post('/calcular-dias', [EmpleadoPublicoController::class, 'calcularDias']);
});


// =============================================
// RUTAS PROTEGIDAS (requieren autenticación - RRHH)
// =============================================
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/cambiar-password', [AuthController::class, 'cambiarPassword']);

    // Empleados - CRUD completo
    Route::prefix('admin/empleados')->group(function () {
        Route::get('/', [EmpleadoController::class, 'index']);
        Route::get('/estadisticas', [EmpleadoController::class, 'estadisticas']);
        Route::get('/plantilla', [EmpleadoController::class, 'descargarPlantilla']);
        Route::post('/importar', [EmpleadoController::class, 'importar']);
        Route::get('/{id}', [EmpleadoController::class, 'show']);
        Route::post('/', [EmpleadoController::class, 'store']);
        Route::put('/{id}', [EmpleadoController::class, 'update']);
        Route::delete('/{id}', [EmpleadoController::class, 'destroy']);
        Route::post('/{id}/ajustar-saldo', [EmpleadoController::class, 'ajustarSaldo']);
    });

    // Solicitudes - Gestión RRHH
    Route::prefix('admin/solicitudes')->group(function () {
        Route::get('/', [SolicitudController::class, 'index']);
        Route::get('/estadisticas', [SolicitudController::class, 'estadisticas']);
        Route::post('/programar', [SolicitudController::class, 'programarVacaciones']);
        Route::get('/{id}', [SolicitudController::class, 'show']);
        Route::put('/{id}/aprobar', [SolicitudController::class, 'aprobar']);
        Route::put('/{id}/rechazar', [SolicitudController::class, 'rechazar']);
        Route::put('/{id}/confirmar-documento', [SolicitudController::class, 'confirmarDocumento']);
        Route::get('/{id}/formulario-pdf', [SolicitudController::class, 'generarFormulario']);
    });

    // Reportes
    Route::prefix('admin/reportes')->group(function () {
        Route::get('/saldos', [ReporteController::class, 'saldos']);
        Route::get('/solicitudes', [ReporteController::class, 'solicitudes']);
        Route::get('/historial/{empleadoId}', [ReporteController::class, 'historialEmpleado']);
        Route::get('/exportar/empleados', [ReporteController::class, 'exportarEmpleados']);
        Route::get('/exportar/solicitudes', [ReporteController::class, 'exportarSolicitudes']);
    });
});
