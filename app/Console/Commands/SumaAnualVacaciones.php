<?php

namespace App\Console\Commands;

use App\Models\Empleado;
use App\Services\VacacionesService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

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

            $esAniversario = $empleado->esAniversarioHoy();
            $necesitaCaptura = false;
            $hoy = Carbon::now();

            // Lógica de "Catch-up": Solo si no es aniversario hoy y ya pasó su fecha de aniversario este año
            if (!$esAniversario && !$this->option('force')) {
                $yaPasoAniversarioEsteAno = $hoy->month > $empleado->fecha_ingreso->month
                    || ($hoy->month == $empleado->fecha_ingreso->month && $hoy->day > $empleado->fecha_ingreso->day);

                if ($yaPasoAniversarioEsteAno) {
                    $anos = $empleado->anos_servicio;
                    if ($anos >= 1) {
                        $yaSumado = $empleado->historial()
                            ->where('tipo_cambio', 'suma_anual')
                            ->where('descripcion', 'like', "%Años de servicio: $anos%")
                            ->exists();
                        if (!$yaSumado) {
                            $necesitaCaptura = true;
                            $this->line("! Detectado aniversario pendiente para {$empleado->nombre_completo} (Año: $anos)");
                        }
                    }
                }
            }

            // Ejecutar si es aniversario, si se forzó, o si detectamos que falta captura
            if ($this->option('force') || $esAniversario || $necesitaCaptura) {
                $historial = $this->vacacionesService->procesarSumaAnual($empleado);

                if ($historial) {
                    $sumados++;
                    $this->line("✓ {$empleado->nombre_completo} (CI: {$empleado->ci}) - Sumados {$historial->dias_cambio} días (Total: {$historial->dias_nuevos})");

                    Log::info('Suma anual de vacaciones', [
                        'empleado_id' => $empleado->id,
                        'ci' => $empleado->ci,
                        'anos_servicio' => $empleado->anos_servicio,
                        'dias_sumados' => $historial->dias_cambio,
                        'nuevo_saldo' => $historial->dias_nuevos,
                        'motivo' => $esAniversario ? 'aniversario_hoy' : ($necesitaCaptura ? 'catch_up' : 'forced')
                    ]);
                }
            }
        }

        $this->info("Proceso completado. Empleados procesados: {$procesados}, Días sumados a: {$sumados} empleados.");

        return Command::SUCCESS;
    }
}
