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
            $table->text('motivo_cancelacion')->nullable()->after('motivo_rechazo');
            $table->foreignId('cancelada_por')->nullable()->after('motivo_cancelacion')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('fecha_cancelacion')->nullable()->after('cancelada_por');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('solicitud_vacaciones', function (Blueprint $table) {
            $table->dropForeign(['cancelada_por']);
            $table->dropColumn(['motivo_cancelacion', 'cancelada_por', 'fecha_cancelacion']);
        });
    }
};
