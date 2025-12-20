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
        Schema::table('empleados', function (Blueprint $table) {
            $table->enum('genero', ['Masculino', 'Femenino'])->nullable()->after('ci');
            $table->enum('tipo_contrato', ['completo', 'medio_tiempo'])->default('completo')->after('genero');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            $table->dropColumn(['genero', 'tipo_contrato']);
        });
    }
};
