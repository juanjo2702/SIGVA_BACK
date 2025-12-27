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
        Schema::table('detalle_solicitud_vacacion', function (Blueprint $table) {
            $table->unsignedTinyInteger('etapa')->default(1)->after('dias_descontados');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('detalle_solicitud_vacacion', function (Blueprint $table) {
            $table->dropColumn('etapa');
        });
    }
};
