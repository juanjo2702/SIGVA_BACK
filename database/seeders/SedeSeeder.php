<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Sede;

class SedeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sedes = [
            ['nombre' => 'COCHABAMBA', 'abreviacion' => 'CBBA', 'departamento' => 'Cochabamba'],
            ['nombre' => 'LA PAZ', 'abreviacion' => 'LP', 'departamento' => 'La Paz'],
            ['nombre' => 'SANTA CRUZ', 'abreviacion' => 'SCZ', 'departamento' => 'Santa Cruz'],
            ['nombre' => 'EL ALTO', 'abreviacion' => 'ALTO', 'departamento' => 'La Paz'],
            ['nombre' => 'COBIJA', 'abreviacion' => 'CB', 'departamento' => 'Pando'],
            ['nombre' => 'IVIRGARZAMA', 'abreviacion' => 'IVI', 'departamento' => 'Cochabamba'],
            ['nombre' => 'PUERTO QUIJARRO', 'abreviacion' => 'PQ', 'departamento' => 'Santa Cruz'],
            ['nombre' => 'GUAYARAMERIN', 'abreviacion' => 'GYA', 'departamento' => 'Beni'],
        ];

        foreach ($sedes as $sede) {
            Sede::firstOrCreate(
                ['abreviacion' => $sede['abreviacion']],
                $sede
            );
        }
    }
}
