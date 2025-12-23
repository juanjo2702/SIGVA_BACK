<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Feriado;

class FeriadoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Feriados nacionales de Bolivia 2024
        $feriadosNacionales2024 = [
            ['nombre' => 'Año Nuevo', 'fecha' => '2024-01-01'],
            ['nombre' => 'Día del Estado Plurinacional', 'fecha' => '2024-01-22'],
            ['nombre' => 'Carnaval', 'fecha' => '2024-02-12'],
            ['nombre' => 'Carnaval', 'fecha' => '2024-02-13'],
            ['nombre' => 'Viernes Santo', 'fecha' => '2024-03-29'],
            ['nombre' => 'Día del Trabajo', 'fecha' => '2024-05-01'],
            ['nombre' => 'Corpus Christi', 'fecha' => '2024-05-30'],
            ['nombre' => 'Año Nuevo Aymara', 'fecha' => '2024-06-21'],
            ['nombre' => 'Día de la Independencia', 'fecha' => '2024-08-06'],
            ['nombre' => 'Día de los Difuntos', 'fecha' => '2024-11-02'],
            ['nombre' => 'Navidad', 'fecha' => '2024-12-25'],
        ];

        // Feriados nacionales de Bolivia 2025
        $feriadosNacionales2025 = [
            ['nombre' => 'Año Nuevo', 'fecha' => '2025-01-01'],
            ['nombre' => 'Día del Estado Plurinacional', 'fecha' => '2025-01-22'],
            ['nombre' => 'Carnaval', 'fecha' => '2025-03-03'],
            ['nombre' => 'Carnaval', 'fecha' => '2025-03-04'],
            ['nombre' => 'Viernes Santo', 'fecha' => '2025-04-18'],
            ['nombre' => 'Día del Trabajo', 'fecha' => '2025-05-01'],
            ['nombre' => 'Corpus Christi', 'fecha' => '2025-06-19'],
            ['nombre' => 'Año Nuevo Aymara', 'fecha' => '2025-06-21'],
            ['nombre' => 'Día de la Independencia', 'fecha' => '2025-08-06'],
            ['nombre' => 'Día de los Difuntos', 'fecha' => '2025-11-02'],
            ['nombre' => 'Navidad', 'fecha' => '2025-12-25'],
        ];

        // Insertar feriados nacionales
        foreach (array_merge($feriadosNacionales2024, $feriadosNacionales2025) as $feriado) {
            Feriado::firstOrCreate(
                ['fecha' => $feriado['fecha'], 'sede_id' => null],
                [
                    'nombre' => $feriado['nombre'],
                    'tipo' => 'nacional',
                    'activo' => true,
                ]
            );
        }

        // Feriados departamentales (ejemplos)
        $this->crearFeriadosDepartamentales();
    }

    private function crearFeriadosDepartamentales(): void
    {
        // Obtener sedes por departamento
        $sedesPorDepartamento = \App\Models\Sede::all()->groupBy('departamento');

        // Feriados departamentales 2024-2025
        $feriadosDepartamentales = [
            'Cochabamba' => [
                ['nombre' => 'Aniversario de Cochabamba', 'fecha' => '2024-09-14'],
                ['nombre' => 'Aniversario de Cochabamba', 'fecha' => '2025-09-14'],
            ],
            'La Paz' => [
                ['nombre' => 'Aniversario de La Paz', 'fecha' => '2024-07-16'],
                ['nombre' => 'Aniversario de La Paz', 'fecha' => '2025-07-16'],
            ],
            'Santa Cruz' => [
                ['nombre' => 'Aniversario de Santa Cruz', 'fecha' => '2024-09-24'],
                ['nombre' => 'Aniversario de Santa Cruz', 'fecha' => '2025-09-24'],
            ],
            'Beni' => [
                ['nombre' => 'Aniversario de Beni', 'fecha' => '2024-11-18'],
                ['nombre' => 'Aniversario de Beni', 'fecha' => '2025-11-18'],
            ],
            'Pando' => [
                ['nombre' => 'Aniversario de Pando', 'fecha' => '2024-09-24'],
                ['nombre' => 'Aniversario de Pando', 'fecha' => '2025-09-24'],
            ],
        ];

        foreach ($feriadosDepartamentales as $departamento => $feriados) {
            if (!isset($sedesPorDepartamento[$departamento])) {
                continue;
            }

            foreach ($sedesPorDepartamento[$departamento] as $sede) {
                foreach ($feriados as $feriado) {
                    Feriado::firstOrCreate(
                        ['fecha' => $feriado['fecha'], 'sede_id' => $sede->id],
                        [
                            'nombre' => $feriado['nombre'],
                            'tipo' => 'departamental',
                            'activo' => true,
                        ]
                    );
                }
            }
        }
    }
}
