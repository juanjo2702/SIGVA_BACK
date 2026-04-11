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
            ['id' => 1, 'nombre' => 'LA PAZ', 'abreviacion' => 'LPZ', 'departamento' => 'LA PAZ'],
            ['id' => 2, 'nombre' => 'EL ALTO', 'abreviacion' => 'EAL', 'departamento' => 'LA PAZ'],
            ['id' => 3, 'nombre' => 'COCHABAMBA', 'abreviacion' => 'COC', 'departamento' => 'COCHABAMBA'],
            ['id' => 4, 'nombre' => 'IVIRGARZAMA', 'abreviacion' => 'IVI', 'departamento' => 'COCHABAMBA'],
            ['id' => 5, 'nombre' => 'GUAYARAMERIN', 'abreviacion' => 'GYA', 'departamento' => 'BENI'],
            ['id' => 6, 'nombre' => 'SANTA CRUZ', 'abreviacion' => 'SCZ', 'departamento' => 'SANTA CRUZ'],
            ['id' => 7, 'nombre' => 'PUERTO QUIJARRO', 'abreviacion' => 'PQJ', 'departamento' => 'SANTA CRUZ'],
            ['id' => 8, 'nombre' => 'COBIJA', 'abreviacion' => 'CBJ', 'departamento' => 'PANDO'],
            ['id' => 9, 'nombre' => 'NACIONAL', 'abreviacion' => 'NAC', 'departamento' => 'NACIONAL'],
        ];

        foreach ($sedes as $sede) {
            Sede::updateOrCreate(['id' => $sede['id']], $sede);
        }
    }
}
