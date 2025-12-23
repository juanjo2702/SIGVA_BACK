<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Añadir columna sede_id
        Schema::table('empleados', function (Blueprint $table) {
            $table->foreignId('sede_id')->nullable()->after('sede')->constrained();
        });

        // Migrar datos existentes de sede string a sede_id
        $sedes = DB::table('sedes')->get();
        foreach ($sedes as $sede) {
            DB::table('empleados')
                ->where('sede', $sede->nombre)
                ->orWhere('sede', $sede->abreviacion)
                ->update(['sede_id' => $sede->id]);
        }

        // Eliminar la columna sede string
        Schema::table('empleados', function (Blueprint $table) {
            $table->dropColumn('sede');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Añadir columna sede string
        Schema::table('empleados', function (Blueprint $table) {
            $table->string('sede')->nullable()->after('tipo_contrato');
        });

        // Migrar datos de vuelta
        $empleados = DB::table('empleados')->whereNotNull('sede_id')->get();
        foreach ($empleados as $empleado) {
            $sede = DB::table('sedes')->find($empleado->sede_id);
            if ($sede) {
                DB::table('empleados')
                    ->where('id', $empleado->id)
                    ->update(['sede' => $sede->nombre]);
            }
        }

        // Eliminar sede_id
        Schema::table('empleados', function (Blueprint $table) {
            $table->dropForeign(['sede_id']);
            $table->dropColumn('sede_id');
        });
    }
};
