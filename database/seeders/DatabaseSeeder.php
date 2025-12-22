<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Rol;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Crear roles base
        $rolAdmin = Rol::create([
            'nombre' => 'Administrador',
            'descripcion' => 'Acceso total al sistema',
            'activo' => true,
        ]);

        Rol::create([
            'nombre' => 'RRHH',
            'descripcion' => 'Gestión de recursos humanos y vacaciones',
            'activo' => true,
        ]);

        // Crear usuario administrador principal - Juan José Mamani Via
        User::create([
            'ci' => '5927724',
            'name' => 'Juan José',
            'apellido_paterno' => 'Mamani',
            'apellido_materno' => 'Via',
            'email' => null,
            'password' => Hash::make('5927724'),
            'rol_id' => $rolAdmin->id,
            'activo' => true,
            'must_change_password' => true,
        ]);
    }
}
