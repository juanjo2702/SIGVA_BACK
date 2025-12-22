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
        // Crear tabla de roles
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->string('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // Modificar tabla users para usar FK a roles
        Schema::table('users', function (Blueprint $table) {
            // Quitar columna enum rol
            $table->dropColumn('rol');
        });

        Schema::table('users', function (Blueprint $table) {
            // Agregar FK a roles
            $table->foreignId('rol_id')->nullable()->after('password')->constrained('roles')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['rol_id']);
            $table->dropColumn('rol_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->enum('rol', ['admin', 'rrhh'])->default('rrhh')->after('password');
        });

        Schema::dropIfExists('roles');
    }
};
