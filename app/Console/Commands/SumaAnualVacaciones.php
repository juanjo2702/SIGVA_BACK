<?php

namespace App\Console\Commands;

use App\Models\Empleado;
use App\Services\VacacionesService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SumaAnualVacaciones extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'vacaciones:suma-anual {--force : Forzar ejecución para todos los empleados}';

    /**
     * The console command description.
     */
    protected $description = 'Suma automática de días de vacaciones en el aniversario de contrato de cada empleado';

    protected VacacionesService $vacacionesService;

    public function __construct(VacacionesService $vacacionesService)
    {
        parent::__construct();
        $this->vacacionesService = $vacacionesService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Iniciando proceso de suma anual de vacaciones...');

        $empleados = Empleado::activos()->get();
        $procesados = 0;
        $sumados = 0;

        foreach ($empleados as $empleado) {
            $procesados++;

            // Verificar si es aniversario o si se forzó
            if ($this->option('force') || $empleado->esAniversarioHoy()) {
                $historial = $this->vacacionesService->procesarSumaAnual($empleado);

                if ($historial) {
                    $sumados++;
                    $this->line("✓ {$empleado->nombre_completo} (CI: {$empleado->ci}) - Sumados {$historial->dias_cambio} días");

                    Log::info('Suma anual de vacaciones', [
                        'empleado_id' => $empleado->id,
                        'ci' => $empleado->ci,
                        'dias_sumados' => $historial->dias_cambio,
                        'nuevo_saldo' => $historial->dias_nuevos,
                    ]);
                }
            }
        }

        $this->info("Proceso completado. Empleados procesados: {$procesados}, Días sumados a: {$sumados} empleados.");

        return Command::SUCCESS;
    }
}
