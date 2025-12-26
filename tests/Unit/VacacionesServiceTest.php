<?php

namespace Tests\Unit;

use App\Models\Empleado;
use App\Services\VacacionesService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use Mockery;

class VacacionesServiceTest extends TestCase
{
    protected VacacionesService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new VacacionesService();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ==========================================
    // Tests para calcularAnosServicio
    // ==========================================

    public function test_calcular_anos_servicio_5_anos(): void
    {
        $fechaIngreso = Carbon::now()->subYears(5);
        $anos = $this->service->calcularAnosServicio($fechaIngreso);
        $this->assertEquals(5, $anos);
    }

    public function test_calcular_anos_servicio_nuevo_empleado(): void
    {
        $fechaIngreso = Carbon::now()->subMonths(6);
        $anos = $this->service->calcularAnosServicio($fechaIngreso);
        $this->assertEquals(0, $anos);
    }

    public function test_calcular_anos_servicio_10_anos(): void
    {
        $fechaIngreso = Carbon::now()->subYears(10)->subMonths(2);
        $anos = $this->service->calcularAnosServicio($fechaIngreso);
        $this->assertEquals(10, $anos);
    }

    // ==========================================
    // Tests para diasCorrespondientes
    // ==========================================

    public function test_dias_correspondientes_menos_de_1_ano(): void
    {
        $this->assertEquals(0, $this->service->diasCorrespondientes(0));
    }

    public function test_dias_correspondientes_1_a_4_anos(): void
    {
        $this->assertEquals(15, $this->service->diasCorrespondientes(1));
        $this->assertEquals(15, $this->service->diasCorrespondientes(2));
        $this->assertEquals(15, $this->service->diasCorrespondientes(4));
    }

    public function test_dias_correspondientes_5_a_9_anos(): void
    {
        $this->assertEquals(20, $this->service->diasCorrespondientes(5));
        $this->assertEquals(20, $this->service->diasCorrespondientes(7));
        $this->assertEquals(20, $this->service->diasCorrespondientes(9));
    }

    public function test_dias_correspondientes_10_o_mas_anos(): void
    {
        $this->assertEquals(30, $this->service->diasCorrespondientes(10));
        $this->assertEquals(30, $this->service->diasCorrespondientes(15));
        $this->assertEquals(30, $this->service->diasCorrespondientes(20));
    }

    // ==========================================
    // Tests para calcularDiaIndividual
    // ==========================================

    public function test_domingo_nunca_cuenta(): void
    {
        $empleado = $this->crearEmpleadoMock(false, false); // tiempo completo
        $domingo = Carbon::parse('2024-12-22'); // Un domingo

        $dias = $this->service->calcularDiaIndividual($domingo, 'completo', $empleado);
        $this->assertEquals(0, $dias);
    }

    public function test_sabado_cuenta_dia_completo(): void
    {
        $empleado = $this->crearEmpleadoMock(false, false); // tiempo completo
        $sabado = Carbon::parse('2024-12-21'); // Un sábado

        $dias = $this->service->calcularDiaIndividual($sabado, 'completo', $empleado);
        $this->assertEquals(1.0, $dias);
    }

    public function test_dia_completo_tiempo_completo(): void
    {
        $empleado = $this->crearEmpleadoMock(false, false); // tiempo completo
        $lunes = Carbon::parse('2024-12-23'); // Un lunes

        $dias = $this->service->calcularDiaIndividual($lunes, 'completo', $empleado);
        $this->assertEquals(1.0, $dias);
    }

    public function test_dia_parcial_tiempo_completo(): void
    {
        $empleado = $this->crearEmpleadoMock(false, false); // tiempo completo
        $lunes = Carbon::parse('2024-12-23');

        $dias = $this->service->calcularDiaIndividual($lunes, 'parcial_manana', $empleado);
        $this->assertEquals(0.5, $dias);
    }

    public function test_dia_parcial_medio_tiempo(): void
    {
        $empleado = $this->crearEmpleadoMock(true, false); // medio tiempo
        $lunes = Carbon::parse('2024-12-23');

        // Parcial para medio tiempo = día completo (no viene en su periodo)
        $dias = $this->service->calcularDiaIndividual($lunes, 'parcial_manana', $empleado);
        $this->assertEquals(1.0, $dias);
    }

    public function test_sabado_mujer_medio_tiempo_no_cuenta(): void
    {
        $empleado = $this->crearEmpleadoMock(true, true); // mujer medio tiempo
        $sabado = Carbon::parse('2024-12-21'); // Un sábado

        $dias = $this->service->calcularDiaIndividual($sabado, 'completo', $empleado);
        $this->assertEquals(0, $dias);
    }

    // ==========================================
    // Helper para crear mocks de Empleado
    // ==========================================

    private function crearEmpleadoMock(bool $esMedioTiempo, bool $esMujerMedioTiempo): Empleado
    {
        $empleado = Mockery::mock(Empleado::class);
        $empleado->shouldReceive('esMedioTiempo')->andReturn($esMedioTiempo);
        $empleado->shouldReceive('esMujerMedioTiempo')->andReturn($esMujerMedioTiempo);
        return $empleado;
    }
}
