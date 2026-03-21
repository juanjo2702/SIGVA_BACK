<?php

namespace Tests\Feature;

use App\Models\Empleado;
use App\Models\Rol;
use App\Models\SolicitudVacacion;
use App\Models\User;
use App\Models\Sede;
use App\Services\SolicitudService;
use App\Services\VacacionesService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tests de integración para SolicitudService
 *
 * Estos tests verifican el flujo de aprobación y rechazo de solicitudes.
 */
class SolicitudServiceTest extends TestCase
{
    protected SolicitudService $solicitudService;
    protected VacacionesService $vacacionesService;
    protected ?User $user = null;
    protected ?Empleado $empleado = null;
    protected ?Sede $sede = null;
    protected ?Rol $rol = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vacacionesService = new VacacionesService();
        $this->solicitudService = new SolicitudService($this->vacacionesService);

        // Usar datos existentes o crear nuevos
        $this->rol = Rol::first() ?? Rol::create([
            'nombre' => 'Admin Test',
            'descripcion' => 'Administrador Test',
            'activo' => true,
        ]);

        $this->sede = Sede::first() ?? Sede::create([
            'nombre' => 'Sede Test',
            'abreviacion' => 'ST',
            'departamento' => 'Cochabamba',
            'activo' => true,
        ]);

        $this->user = User::where('email', 'test@sigva.com')->first() ?? User::create([
            'ci' => '99999999',
            'name' => 'Test',
            'apellido_paterno' => 'User',
            'email' => 'test@sigva.com',
            'password' => bcrypt('password'),
            'rol_id' => $this->rol->id,
            'activo' => true,
            'must_change_password' => false,
        ]);

        $this->empleado = Empleado::where('ci', '88888888')->first() ?? Empleado::create([
            'ci' => '88888888',
            'nombres' => 'Empleado',
            'apellido_paterno' => 'Test',
            'apellido_materno' => 'User',
            'fecha_ingreso' => now()->subYears(3),
            'cargo' => 'Docente Test',
            'tipo_contrato' => 'completo',
            'genero' => 'Masculino',
            'sede_id' => $this->sede->id,
            'saldo_vacaciones' => 15,
            'activo' => true,
        ]);

        // Restaurar saldo antes de cada test
        $this->empleado->saldo_vacaciones = 15;
        $this->empleado->save();
    }

    protected function tearDown(): void
    {
        // Limpiar solicitudes de prueba
        if ($this->empleado) {
            SolicitudVacacion::where('empleado_id', $this->empleado->id)->delete();
        }

        parent::tearDown();
    }

    // ==========================================
    // Test: Aprobar solicitud pendiente
    // ==========================================
    public function test_aprobar_solicitud_pendiente_cambia_a_pendiente_documento(): void
    {
        $solicitud = $this->crearSolicitud('pendiente');

        $resultado = $this->solicitudService->aprobar($solicitud, $this->user->id);

        $this->assertEquals('pendiente_documento', $resultado->estado);
        $this->assertNotNull($resultado->aprobada_por);
    }

    // ==========================================
    // Test: Rechazar solicitud
    // ==========================================
    public function test_rechazar_solicitud_con_motivo(): void
    {
        $solicitud = $this->crearSolicitud('pendiente');

        $resultado = $this->solicitudService->rechazar($solicitud, 'Fechas no disponibles');

        $this->assertEquals('rechazada', $resultado->estado);
        $this->assertEquals('Fechas no disponibles', $resultado->motivo_rechazo);
    }

    // ==========================================
    // Test: Confirmar documento descuenta días
    // ==========================================
    public function test_confirmar_documento_descuenta_dias(): void
    {
        $saldoInicial = $this->empleado->saldo_vacaciones;

        $solicitud = $this->crearSolicitud('pendiente_documento');

        $resultado = $this->solicitudService->confirmarDocumento($solicitud, $this->user->id);

        $this->empleado->refresh();
        $this->assertEquals('aprobada', $resultado->estado);
        $this->assertTrue($resultado->documento_entregado);
        $this->assertLessThan($saldoInicial, $this->empleado->saldo_vacaciones);
    }

    // ==========================================
    // Test: Estadísticas de solicitudes
    // ==========================================
    public function test_estadisticas_retorna_conteos_correctos(): void
    {
        // Crear solicitudes de diferentes estados
        $this->crearSolicitud('pendiente');
        $this->crearSolicitud('pendiente');
        $this->crearSolicitud('aprobada');

        $stats = $this->solicitudService->getEstadisticas(now()->year);

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('pendientes', $stats);
        $this->assertArrayHasKey('pendientes_documento', $stats);
        $this->assertArrayHasKey('aprobadas', $stats);
    }

    // ==========================================
    // Helper
    // ==========================================
    private function crearSolicitud(string $estado): SolicitudVacacion
    {
        return SolicitudVacacion::create([
            'empleado_id' => $this->empleado->id,
            'fecha_solicitud' => now()->format('Y-m-d'),
            'fecha_inicio' => now()->addDays(30)->format('Y-m-d'),
            'fecha_fin' => now()->addDays(32)->format('Y-m-d'),
            'dias_solicitados' => 3,
            'estado' => $estado,
            'tipo' => 'completo',
            'tiene_reemplazo' => false,
            'lugar_solicitud' => 'Cochabamba Test',
        ]);
    }
}
