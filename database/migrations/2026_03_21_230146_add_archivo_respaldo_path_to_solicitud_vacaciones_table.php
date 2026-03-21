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
            $table->string('archivo_respaldo_path')->nullable()->after('observacion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('solicitud_vacaciones', function (Blueprint $table) {
            $table->dropColumn('archivo_respaldo_path');
        });
    }
};
