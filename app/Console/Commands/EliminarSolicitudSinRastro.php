<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Empleado;
use App\Models\SolicitudVacacion;
use App\Models\HistorialVacacion;
use Illuminate\Support\Facades\DB;

class EliminarSolicitudSinRastro extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vacacion:eliminar-sin-rastro {id : El ID de la solicitud a eliminar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Elimina una solicitud de vacaciones de forma fisica y logica de la base de datos sin dejar rastro';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $solicitudId = $this->argument('id');

        $this->info("Buscando solicitud #{$solicitudId}...");

        $solicitud = SolicitudVacacion::with('empleado')->find($solicitudId);

        if (!$solicitud) {
            $this->error("No se encontró la solicitud con ID #{$solicitudId} en la base de datos.");
            return 1;
        }

        $empleado = $solicitud->empleado;
        if (!$empleado) {
            $this->error("La solicitud no tiene un empleado asociado.");
            return 1;
        }

        $this->line("---------------------------------------------------------");
        $this->info("DETALLES DE LA SOLICITUD A ELIMINAR:");
        $this->line("Empleado:      {$empleado->nombres} {$empleado->apellido_paterno} {$empleado->apellido_materno}");
        $this->line("CI Empleado:   {$empleado->ci}");
        $this->line("Fecha Solic.:  " . ($solicitud->fecha_solicitud ? $solicitud->fecha_solicitud->format('d/m/Y') : 'N/A'));
        $this->line("Periodo Vac.:  " . ($solicitud->fecha_inicio ? $solicitud->fecha_inicio->format('d/m/Y') : 'N/A') . " al " . ($solicitud->fecha_fin ? $solicitud->fecha_fin->format('d/m/Y') : 'N/A'));
        $this->line("Días Solicit.: {$solicitud->dias_solicitados} días");
        $this->line("Estado Actual: " . strtoupper($solicitud->estado));
        $this->line("Tipo Solicit.: {$solicitud->tipo}");
        $this->line("---------------------------------------------------------");

        if (!$this->confirm('¿Está seguro de que desea eliminar esta solicitud permanentemente y sin dejar rastros?')) {
            $this->error("Operación cancelada.");
            return 0;
        }

        $diasRestaurar = (float)$solicitud->dias_solicitados;
        $estadoOriginal = $solicitud->estado;
        $saldoAnterior = (float)$empleado->saldo_vacaciones;

        try {
            DB::beginTransaction();

            // 1. Restaurar saldo si estaba aprobada
            if ($estadoOriginal === SolicitudVacacion::ESTADO_APROBADA) {
                $empleado->saldo_vacaciones += $diasRestaurar;
                $empleado->save();
                $this->comment("Saldo del empleado restaurado: de {$saldoAnterior} a {$empleado->saldo_vacaciones} (+{$diasRestaurar} días).");
            } else {
                $this->comment("No se requirió restaurar saldo porque la solicitud estaba en estado '{$estadoOriginal}'.");
            }

            // 2. Eliminar detalles individuales
            $detallesCount = $solicitud->detalles()->count();
            $solicitud->detalles()->delete();
            $this->comment("Eliminados {$detallesCount} días detallados en detalle_solicitud_vacacion.");

            // 3. Eliminar registros del historial de vacaciones
            $historialesABorrar = HistorialVacacion::where('empleado_id', $empleado->id)
                ->where(function($query) use ($solicitudId) {
                    $query->where('descripcion', 'like', "%#{$solicitudId}%")
                          ->orWhere('descripcion', 'like', "%Solicitud de vacaciones #{$solicitudId}%");
                })
                ->get();

            $historialCount = $historialesABorrar->count();
            foreach ($historialesABorrar as $hist) {
                $hist->delete();
            }
            $this->comment("Eliminados {$historialCount} registros del historial de vacaciones.");

            // 4. Eliminar la solicitud
            $solicitud->delete();
            $this->comment("Solicitud ID #{$solicitudId} eliminada físicamente.");

            DB::commit();
            $this->info("¡La solicitud #{$solicitudId} ha sido eliminada con éxito y sin dejar rastro!");
            return 0;

        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error("Ocurrió un error al eliminar: " . $e->getMessage());
            return 1;
        }
    }
}
