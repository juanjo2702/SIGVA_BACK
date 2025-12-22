<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // CI como identificador único para login
            $table->string('ci')->unique()->after('id');

            // Apellidos separados
            $table->string('apellido_paterno')->after('name');
            $table->string('apellido_materno')->nullable()->after('apellido_paterno');

            // Rol del usuario
            $table->enum('rol', ['admin', 'rrhh'])->default('rrhh')->after('password');

            // Estado del usuario
            $table->boolean('activo')->default(true)->after('rol');

            // Email ahora es opcional
            $table->string('email')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['ci', 'apellido_paterno', 'apellido_materno', 'rol', 'activo']);
        });
    }
};
