<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Empleado;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Crear usuario RRHH de prueba
        User::create([
            'name' => 'Administrador RRHH',
            'email' => 'rrhh@sigva.com',
            'password' => Hash::make('password123'),
        ]);

        // Crear usuario administrador principal - Juan José Mamani Via
        User::create([
            'name' => 'Juan José Mamani Via',
            'email' => '5927724',
            'password' => Hash::make('5927724'),
            'must_change_password' => true,
        ]);

        // Lista de sedes disponibles
        $sedes = [
            'Oficina Central - La Paz',
            'Sucursal Cochabamba',
            'Sucursal Santa Cruz',
            'Oficina El Alto',
            'Sucursal Oruro',
        ];

        // Crear empleados de prueba con todos los nuevos campos
        $empleados = [
            // Sede La Paz
            [
                'apellido_paterno' => 'García',
                'apellido_materno' => 'López',
                'nombres' => 'Juan Carlos',
                'ci' => '12345678',
                'genero' => 'Masculino',
                'tipo_contrato' => 'completo',
                'sede' => 'Oficina Central - La Paz',
                'cargo' => 'Analista de Sistemas',
                'fecha_ingreso' => Carbon::now()->subYears(3)->subMonths(2),
                'saldo_vacaciones' => 15,
            ],
            [
                'apellido_paterno' => 'Mamani',
                'apellido_materno' => 'Quispe',
                'nombres' => 'María Elena',
                'ci' => '23456789',
                'genero' => 'Femenino',
                'tipo_contrato' => 'completo',
                'sede' => 'Oficina Central - La Paz',
                'cargo' => 'Contadora General',
                'fecha_ingreso' => Carbon::now()->subYears(7)->subMonths(5),
                'saldo_vacaciones' => 25,
            ],
            [
                'apellido_paterno' => 'Rojas',
                'apellido_materno' => 'Fernández',
                'nombres' => 'Pedro Antonio',
                'ci' => '34567890',
                'genero' => 'Masculino',
                'tipo_contrato' => 'completo',
                'sede' => 'Oficina Central - La Paz',
                'cargo' => 'Gerente de Operaciones',
                'fecha_ingreso' => Carbon::now()->subYears(12)->subMonths(1),
                'saldo_vacaciones' => 35,
            ],
            // Sede El Alto - Mujeres medio tiempo
            [
                'apellido_paterno' => 'Condori',
                'apellido_materno' => 'Vargas',
                'nombres' => 'Ana Lucía',
                'ci' => '45678901',
                'genero' => 'Femenino',
                'tipo_contrato' => 'medio_tiempo',
                'sede' => 'Oficina El Alto',
                'cargo' => 'Asistente Administrativo',
                'fecha_ingreso' => Carbon::now()->subYears(1)->subMonths(3),
                'saldo_vacaciones' => 10,
            ],
            [
                'apellido_paterno' => 'Choque',
                'apellido_materno' => 'Huanca',
                'nombres' => 'Rosa María',
                'ci' => '56789013',
                'genero' => 'Femenino',
                'tipo_contrato' => 'medio_tiempo',
                'sede' => 'Oficina El Alto',
                'cargo' => 'Recepcionista',
                'fecha_ingreso' => Carbon::now()->subYears(2)->subMonths(6),
                'saldo_vacaciones' => 12,
            ],
            // Sede Cochabamba
            [
                'apellido_paterno' => 'Flores',
                'apellido_materno' => null,
                'nombres' => 'Roberto',
                'ci' => '56789012',
                'genero' => 'Masculino',
                'tipo_contrato' => 'completo',
                'sede' => 'Sucursal Cochabamba',
                'cargo' => 'Técnico de Mantenimiento',
                'fecha_ingreso' => Carbon::now()->subYears(5)->subMonths(8),
                'saldo_vacaciones' => -2, // Saldo negativo de prueba
            ],
            [
                'apellido_paterno' => 'Gutiérrez',
                'apellido_materno' => 'Mendoza',
                'nombres' => 'Carmen Julia',
                'ci' => '67890123',
                'genero' => 'Femenino',
                'tipo_contrato' => 'completo',
                'sede' => 'Sucursal Cochabamba',
                'cargo' => 'Jefa de Ventas',
                'fecha_ingreso' => Carbon::now()->subYears(4)->subMonths(2),
                'saldo_vacaciones' => 18,
            ],
            // Sede Santa Cruz
            [
                'apellido_paterno' => 'Montaño',
                'apellido_materno' => 'Suárez',
                'nombres' => 'Carlos Eduardo',
                'ci' => '78901234',
                'genero' => 'Masculino',
                'tipo_contrato' => 'completo',
                'sede' => 'Sucursal Santa Cruz',
                'cargo' => 'Gerente Regional',
                'fecha_ingreso' => Carbon::now()->subYears(8)->subMonths(4),
                'saldo_vacaciones' => 28,
            ],
            [
                'apellido_paterno' => 'Pedraza',
                'apellido_materno' => 'Vaca',
                'nombres' => 'Sofía Alejandra',
                'ci' => '89012345',
                'genero' => 'Femenino',
                'tipo_contrato' => 'medio_tiempo',
                'sede' => 'Sucursal Santa Cruz',
                'cargo' => 'Diseñadora Gráfica',
                'fecha_ingreso' => Carbon::now()->subYears(1)->subMonths(1),
                'saldo_vacaciones' => 8,
            ],
            // Sede Oruro
            [
                'apellido_paterno' => 'Patiño',
                'apellido_materno' => 'Llanos',
                'nombres' => 'Miguel Ángel',
                'ci' => '90123456',
                'genero' => 'Masculino',
                'tipo_contrato' => 'medio_tiempo',
                'sede' => 'Sucursal Oruro',
                'cargo' => 'Auxiliar Contable',
                'fecha_ingreso' => Carbon::now()->subYears(2)->subMonths(9),
                'saldo_vacaciones' => 14,
            ],
            [
                'apellido_paterno' => 'Huacani',
                'apellido_materno' => 'Yanapa',
                'nombres' => 'Teófilo',
                'ci' => '01234567',
                'genero' => 'Masculino',
                'tipo_contrato' => 'completo',
                'sede' => 'Sucursal Oruro',
                'cargo' => 'Auxiliar Administrativo',
                'fecha_ingreso' => Carbon::now()->subYears(6)->subMonths(3),
                'saldo_vacaciones' => 22,
            ],
        ];

        foreach ($empleados as $empleado) {
            Empleado::create($empleado);
        }
    }
}
