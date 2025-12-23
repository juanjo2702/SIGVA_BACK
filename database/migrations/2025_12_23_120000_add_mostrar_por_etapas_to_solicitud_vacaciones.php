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
        Schema::table('solicitud_vacaciones', function (Blueprint $table) {
            $table->boolean('mostrar_por_etapas')->default(false)->after('documento_entregado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('solicitud_vacaciones', function (Blueprint $table) {
            $table->dropColumn('mostrar_por_etapas');
        });
    }
};
