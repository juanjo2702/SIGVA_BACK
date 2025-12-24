<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EmpleadoPublicoController;
use App\Http\Controllers\Api\EmpleadoController;
use App\Http\Controllers\Api\SolicitudController;
use App\Http\Controllers\Api\ReporteController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\RolController;
use App\Http\Controllers\Api\SedeController;
use App\Http\Controllers\Api\FeriadoController;

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
    // Búsqueda por CI y Fecha de Ingreso
    Route::post('/empleados/buscar', [EmpleadoPublicoController::class, 'buscarEmpleado']);

    // Crear solicitud de vacaciones
    Route::post('/solicitudes', [EmpleadoPublicoController::class, 'crearSolicitud']);

    // Crear solicitud con días individuales (nuevo formato calendario)
    Route::post('/solicitudes/con-dias', [EmpleadoPublicoController::class, 'crearSolicitudConDias']);

    // Obtener datos para formulario de impresión
    Route::get('/solicitudes/{id}/formulario', [EmpleadoPublicoController::class, 'datosFormulario']);

    // Calcular días hábiles (preview)
    Route::post('/calcular-dias', [EmpleadoPublicoController::class, 'calcularDias']);

    // Feriados para el calendario (público)
    Route::get('/feriados', [FeriadoController::class, 'porSede']);

    // Sedes para select (público)
    Route::get('/sedes', [SedeController::class, 'index']);
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
        Route::put('/{id}', [SolicitudController::class, 'update']);
        Route::put('/{id}/aprobar', [SolicitudController::class, 'aprobar']);
        Route::put('/{id}/rechazar', [SolicitudController::class, 'rechazar']);
        Route::put('/{id}/confirmar-documento', [SolicitudController::class, 'confirmarDocumento']);
        Route::get('/{id}/formulario-pdf', [SolicitudController::class, 'generarFormulario']);
    });

    // Calendario Compartido - Vista de vacaciones del equipo
    Route::get('/admin/vacaciones-calendario', [SolicitudController::class, 'vacacionesCalendario']);

    // Sedes - Gestión
    Route::prefix('admin/sedes')->group(function () {
        Route::get('/', [SedeController::class, 'index']);
        Route::post('/', [SedeController::class, 'store']);
        Route::get('/{id}', [SedeController::class, 'show']);
        Route::put('/{id}', [SedeController::class, 'update']);
        Route::delete('/{id}', [SedeController::class, 'destroy']);
    });

    // Feriados - Gestión
    Route::prefix('admin/feriados')->group(function () {
        Route::get('/', [FeriadoController::class, 'index']);
        Route::post('/', [FeriadoController::class, 'store']);
        Route::get('/{id}', [FeriadoController::class, 'show']);
        Route::put('/{id}', [FeriadoController::class, 'update']);
        Route::delete('/{id}', [FeriadoController::class, 'destroy']);
        Route::get('/{id}/preview-afectados', [FeriadoController::class, 'previewAfectados']);
        Route::post('/{id}/procesar-devoluciones', [FeriadoController::class, 'procesarDevoluciones']);
    });

    // Reportes
    Route::prefix('admin/reportes')->group(function () {
        Route::get('/saldos', [ReporteController::class, 'saldos']);
        Route::get('/solicitudes', [ReporteController::class, 'solicitudes']);
        Route::get('/historial/{empleadoId}', [ReporteController::class, 'historialEmpleado']);
        Route::get('/exportar/empleados', [ReporteController::class, 'exportarEmpleados']);
        Route::get('/exportar/solicitudes', [ReporteController::class, 'exportarSolicitudes']);
    });

    // Usuarios - Gestión de usuarios del sistema
    Route::prefix('admin/usuarios')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::post('/', [UserController::class, 'store']);
        Route::get('/{id}', [UserController::class, 'show']);
        Route::put('/{id}', [UserController::class, 'update']);
        Route::delete('/{id}', [UserController::class, 'destroy']);
        Route::post('/{id}/reset-password', [UserController::class, 'resetPassword']);
    });

    // Roles - Gestión de roles del sistema
    Route::prefix('admin/roles')->group(function () {
        Route::get('/', [RolController::class, 'index']);
        Route::post('/', [RolController::class, 'store']);
        Route::get('/{id}', [RolController::class, 'show']);
        Route::put('/{id}', [RolController::class, 'update']);
        Route::delete('/{id}', [RolController::class, 'destroy']);
    });
});
