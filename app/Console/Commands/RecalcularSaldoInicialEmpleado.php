<?php

namespace App\Console\Commands;

use App\Models\Empleado;
use App\Models\DetalleSolicitudVacacion;
use App\Models\HistorialVacacion;
use App\Models\SolicitudVacacion;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class RecalcularSaldoInicialEmpleado extends Command
{
    protected $signature = 'vacaciones:recalcular-inicial
        {ci : CI del empleado}
        {saldo=9 : Saldo inicial historico desde el primer movimiento}
        {--fecha-ingreso= : Fecha de ingreso esperada, en Y-m-d o d/m/Y}
        {--detalle-parcial=* : Corrige un detalle a medio dia: SOLICITUD_ID:FECHA:parcial_manana|parcial_tarde}
        {--apply : Aplica los cambios. Sin esta opcion solo simula}
        {--no-backup : No generar respaldo JSON antes de aplicar}';

    protected $description = 'Recalcula el historial de vacaciones de un empleado desde un saldo inicial especifico';

    public function handle(): int
    {
        $ci = trim((string) $this->argument('ci'));
        $saldoInicial = round((float) $this->argument('saldo'), 1);
        $apply = (bool) $this->option('apply');

        $empleado = Empleado::where('ci', $ci)->first();

        if (!$empleado) {
            $this->error("No se encontro empleado con CI {$ci}.");
            return Command::FAILURE;
        }

        if (!$this->validarFechaIngreso($empleado)) {
            return Command::FAILURE;
        }

        $correccionesDetalle = $this->resolverCorreccionesDetalle($empleado);

        if ($correccionesDetalle === null) {
            return Command::FAILURE;
        }

        $historial = HistorialVacacion::query()
            ->where('empleado_id', $empleado->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        if ($historial->isEmpty()) {
            $this->warn('El empleado no tiene historial. Solo se ajustaria el saldo actual.');
            $this->mostrarEmpleado($empleado, $saldoInicial);

            if ($apply) {
                if (!$this->option('no-backup')) {
                    $this->respaldar($empleado, $historial, collect());
                }

                $empleado->update(['saldo_vacaciones' => $saldoInicial]);
                $this->info("Saldo actualizado a {$saldoInicial}.");
            }

            return Command::SUCCESS;
        }

        $primerSaldoAnterior = round((float) $historial->first()->dias_anteriores, 1);
        $delta = round($saldoInicial - $primerSaldoAnterior, 1);
        $solicitudesConDiasCorregidos = $correccionesDetalle
            ->pluck('solicitud_dias_nuevo', 'solicitud_id')
            ->all();
        $recalculo = $this->recalcularHistorial($historial, $saldoInicial, $solicitudesConDiasCorregidos);
        $saldoFinal = $recalculo['saldo_final'];
        $actualizacionesSolicitudes = $this->resolverActualizacionesSolicitudes($empleado, $recalculo['filas']);

        $this->mostrarResumen($empleado, $primerSaldoAnterior, $saldoInicial, $delta, $saldoFinal, $apply);
        $this->mostrarCorreccionesDetalle($correccionesDetalle);
        $this->mostrarHistorial($recalculo['filas']);
        $this->mostrarSolicitudes($actualizacionesSolicitudes);

        if (!$apply) {
            $this->newLine();
            $this->warn('SIMULACION: no se guardo ningun cambio.');
            $this->line('Para aplicar: ' . $this->comandoAplicar($ci, $saldoInicial, $empleado, $correccionesDetalle));
            return Command::SUCCESS;
        }

        DB::transaction(function () use ($empleado, $historial, $recalculo, $actualizacionesSolicitudes, $saldoFinal, $correccionesDetalle) {
            if (!$this->option('no-backup')) {
                $this->respaldar($empleado, $historial, $actualizacionesSolicitudes);
            }

            foreach ($correccionesDetalle as $correccion) {
                DetalleSolicitudVacacion::whereKey($correccion['detalle_id'])->update([
                    'tipo' => $correccion['tipo_nuevo'],
                    'dias_descontados' => $correccion['dias_nuevo'],
                ]);

                SolicitudVacacion::whereKey($correccion['solicitud_id'])->update([
                    'dias_solicitados' => $correccion['solicitud_dias_nuevo'],
                ]);
            }

            foreach ($recalculo['filas'] as $fila) {
                HistorialVacacion::whereKey($fila['id'])->update([
                    'dias_anteriores' => $fila['nuevo_dias_anteriores'],
                    'dias_cambio' => $fila['nuevo_dias_cambio'],
                    'dias_nuevos' => $fila['nuevo_dias_nuevos'],
                ]);
            }

            foreach ($actualizacionesSolicitudes as $actualizacion) {
                SolicitudVacacion::whereKey($actualizacion['solicitud_id'])->update([
                    'saldo_actual' => $actualizacion['nuevo_saldo_actual'],
                    'saldo_despues' => $actualizacion['nuevo_saldo_despues'],
                ]);
            }

            $empleado->update(['saldo_vacaciones' => $saldoFinal]);
        });

        $this->newLine();
        $this->info("Cambios aplicados. Saldo final del empleado: {$saldoFinal}.");

        return Command::SUCCESS;
    }

    private function resolverCorreccionesDetalle(Empleado $empleado): ?\Illuminate\Support\Collection
    {
        $opciones = collect($this->option('detalle-parcial') ?? [])->filter();

        if ($opciones->isEmpty()) {
            return collect();
        }

        $correcciones = collect();

        foreach ($opciones as $opcion) {
            $partes = explode(':', (string) $opcion);

            if (count($partes) !== 3) {
                $this->error("Formato invalido en --detalle-parcial={$opcion}. Usa SOLICITUD_ID:FECHA:parcial_manana|parcial_tarde.");
                return null;
            }

            [$solicitudId, $fechaValor, $tipoNuevo] = $partes;
            $tipoNuevo = trim($tipoNuevo);

            if (!in_array($tipoNuevo, [DetalleSolicitudVacacion::TIPO_MANANA, DetalleSolicitudVacacion::TIPO_TARDE], true)) {
                $this->error("Tipo invalido en --detalle-parcial={$opcion}. Usa parcial_manana o parcial_tarde.");
                return null;
            }

            $fecha = $this->parseFecha(trim($fechaValor));

            if (!$fecha) {
                $this->error("Fecha invalida en --detalle-parcial={$opcion}.");
                return null;
            }

            $solicitud = SolicitudVacacion::query()
                ->where('id', (int) $solicitudId)
                ->where('empleado_id', $empleado->id)
                ->first();

            if (!$solicitud) {
                $this->error("La solicitud #{$solicitudId} no pertenece al CI {$empleado->ci} o no existe.");
                return null;
            }

            $detalle = DetalleSolicitudVacacion::query()
                ->where('solicitud_vacacion_id', $solicitud->id)
                ->whereDate('fecha', $fecha->format('Y-m-d'))
                ->first();

            if (!$detalle) {
                $this->error("No existe detalle para solicitud #{$solicitud->id} en fecha {$fecha->format('Y-m-d')}.");
                return null;
            }

            $diasNuevo = 0.5;
            $diasAnterior = round((float) $detalle->dias_descontados, 1);
            $solicitudDiasActual = round((float) $solicitud->dias_solicitados, 1);
            $solicitudDiasNuevo = round($solicitudDiasActual - $diasAnterior + $diasNuevo, 1);

            $correcciones->push([
                'solicitud_id' => $solicitud->id,
                'detalle_id' => $detalle->id,
                'fecha' => $fecha->format('Y-m-d'),
                'tipo_anterior' => $detalle->tipo,
                'tipo_nuevo' => $tipoNuevo,
                'dias_anterior' => $diasAnterior,
                'dias_nuevo' => $diasNuevo,
                'solicitud_dias_actual' => $solicitudDiasActual,
                'solicitud_dias_nuevo' => $solicitudDiasNuevo,
            ]);
        }

        return $correcciones;
    }

    private function validarFechaIngreso(Empleado $empleado): bool
    {
        $fechaEsperada = $this->option('fecha-ingreso');

        if (!$fechaEsperada) {
            return true;
        }

        $fecha = $this->parseFecha((string) $fechaEsperada);

        if (!$fecha) {
            $this->error('No pude interpretar --fecha-ingreso. Usa Y-m-d o d/m/Y.');
            return false;
        }

        $fechaReal = $empleado->fecha_ingreso->format('Y-m-d');

        if ($fechaReal !== $fecha->format('Y-m-d')) {
            $this->error("La fecha de ingreso no coincide. Esperada: {$fecha->format('Y-m-d')}; en sistema: {$fechaReal}.");
            return false;
        }

        return true;
    }

    private function parseFecha(string $valor): ?Carbon
    {
        foreach (['Y-m-d', 'd/m/Y', 'j/n/Y', 'd-m-Y', 'j-n-Y'] as $formato) {
            try {
                $fecha = Carbon::createFromFormat($formato, $valor);

                if ($fecha && $fecha->format($formato) === $valor) {
                    return $fecha->startOfDay();
                }
            } catch (\Throwable) {
                continue;
            }
        }

        try {
            return Carbon::parse($valor)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function recalcularHistorial($historial, float $saldoInicial, array $solicitudesConDiasCorregidos = []): array
    {
        $saldo = $saldoInicial;
        $filas = [];

        foreach ($historial as $movimiento) {
            $cambioActual = round((float) $movimiento->dias_cambio, 1);
            $cambio = $cambioActual;

            if (
                $movimiento->tipo_cambio === HistorialVacacion::TIPO_SOLICITUD_APROBADA
                && preg_match('/Solicitud de vacaciones #(\d+) aprobada/', (string) $movimiento->descripcion, $matches)
                && array_key_exists((int) $matches[1], $solicitudesConDiasCorregidos)
            ) {
                $cambio = round(-1 * (float) $solicitudesConDiasCorregidos[(int) $matches[1]], 1);
            }

            $nuevoAnterior = round($saldo, 1);
            $nuevoNuevo = round($nuevoAnterior + $cambio, 1);

            $filas[] = [
                'id' => $movimiento->id,
                'tipo_cambio' => $movimiento->tipo_cambio,
                'descripcion' => $movimiento->descripcion,
                'actual_dias_cambio' => $cambioActual,
                'nuevo_dias_cambio' => $cambio,
                'actual_dias_anteriores' => round((float) $movimiento->dias_anteriores, 1),
                'actual_dias_nuevos' => round((float) $movimiento->dias_nuevos, 1),
                'nuevo_dias_anteriores' => $nuevoAnterior,
                'nuevo_dias_nuevos' => $nuevoNuevo,
                'created_at' => $movimiento->created_at?->format('Y-m-d H:i:s'),
            ];

            $saldo = $nuevoNuevo;
        }

        return [
            'filas' => $filas,
            'saldo_final' => round($saldo, 1),
        ];
    }

    private function resolverActualizacionesSolicitudes(Empleado $empleado, array $filas)
    {
        return collect($filas)
            ->filter(fn (array $fila) => $fila['tipo_cambio'] === HistorialVacacion::TIPO_SOLICITUD_APROBADA)
            ->map(function (array $fila) use ($empleado) {
                if (!preg_match('/Solicitud de vacaciones #(\d+) aprobada/', (string) $fila['descripcion'], $matches)) {
                    return null;
                }

                $solicitud = SolicitudVacacion::query()
                    ->where('id', (int) $matches[1])
                    ->where('empleado_id', $empleado->id)
                    ->first();

                if (!$solicitud) {
                    return null;
                }

                return [
                    'solicitud_id' => $solicitud->id,
                    'estado' => $solicitud->estado,
                    'dias_solicitados' => round((float) $solicitud->dias_solicitados, 1),
                    'dias_recalculados' => abs($fila['nuevo_dias_cambio']),
                    'actual_saldo_actual' => $solicitud->saldo_actual === null ? null : round((float) $solicitud->saldo_actual, 1),
                    'actual_saldo_despues' => $solicitud->saldo_despues === null ? null : round((float) $solicitud->saldo_despues, 1),
                    'nuevo_saldo_actual' => $fila['nuevo_dias_anteriores'],
                    'nuevo_saldo_despues' => $fila['nuevo_dias_nuevos'],
                ];
            })
            ->filter()
            ->values();
    }

    private function mostrarResumen(Empleado $empleado, float $saldoAnterior, float $saldoInicial, float $delta, float $saldoFinal, bool $apply): void
    {
        $this->info($apply ? 'MODO APLICAR' : 'MODO SIMULACION');
        $this->line("Empleado: {$empleado->nombre_completo} | CI: {$empleado->ci} | ID: {$empleado->id}");
        $this->line("Fecha ingreso: {$empleado->fecha_ingreso->format('Y-m-d')}");
        $this->line("Saldo actual en empleado: {$empleado->saldo_vacaciones}");
        $this->line("Saldo inicial historico: {$saldoAnterior} -> {$saldoInicial} (delta {$delta})");
        $this->line("Saldo final recalculado: {$saldoFinal}");
        $this->newLine();
    }

    private function mostrarEmpleado(Empleado $empleado, float $saldoInicial): void
    {
        $this->line("Empleado: {$empleado->nombre_completo} | CI: {$empleado->ci} | ID: {$empleado->id}");
        $this->line("Saldo actual: {$empleado->saldo_vacaciones}");
        $this->line("Nuevo saldo: {$saldoInicial}");
    }

    private function mostrarHistorial(array $filas): void
    {
        $this->table(
            ['ID', 'Tipo', 'Cambio', 'Cambio recalc.', 'Antes', 'Despues', 'Antes recalc.', 'Despues recalc.', 'Descripcion'],
            collect($filas)->map(fn (array $fila) => [
                $fila['id'],
                $fila['tipo_cambio'],
                $fila['actual_dias_cambio'],
                $fila['nuevo_dias_cambio'],
                $fila['actual_dias_anteriores'],
                $fila['actual_dias_nuevos'],
                $fila['nuevo_dias_anteriores'],
                $fila['nuevo_dias_nuevos'],
                Str::limit((string) $fila['descripcion'], 42),
            ])->all()
        );
    }

    private function mostrarCorreccionesDetalle($correcciones): void
    {
        if ($correcciones->isEmpty()) {
            return;
        }

        $this->line('Correcciones de detalles:');
        $this->table(
            ['Solicitud', 'Detalle', 'Fecha', 'Tipo', 'Tipo recalc.', 'Dias', 'Dias recalc.', 'Total solicitud', 'Total recalc.'],
            $correcciones->map(fn (array $fila) => [
                $fila['solicitud_id'],
                $fila['detalle_id'],
                $fila['fecha'],
                $fila['tipo_anterior'],
                $fila['tipo_nuevo'],
                $fila['dias_anterior'],
                $fila['dias_nuevo'],
                $fila['solicitud_dias_actual'],
                $fila['solicitud_dias_nuevo'],
            ])->all()
        );
    }

    private function mostrarSolicitudes($actualizaciones): void
    {
        if ($actualizaciones->isEmpty()) {
            return;
        }

        $this->newLine();
        $this->line('Snapshots de solicitudes aprobadas que se actualizarian:');
        $this->table(
            ['Solicitud', 'Estado', 'Dias', 'Dias recalc.', 'Saldo actual', 'Saldo despues', 'Actual recalc.', 'Despues recalc.'],
            $actualizaciones->map(fn (array $fila) => [
                $fila['solicitud_id'],
                $fila['estado'],
                $fila['dias_solicitados'],
                $fila['dias_recalculados'],
                $fila['actual_saldo_actual'],
                $fila['actual_saldo_despues'],
                $fila['nuevo_saldo_actual'],
                $fila['nuevo_saldo_despues'],
            ])->all()
        );
    }

    private function comandoAplicar(string $ci, float $saldoInicial, Empleado $empleado, $correccionesDetalle): string
    {
        $opcionesDetalle = $correccionesDetalle
            ->map(fn (array $fila) => "--detalle-parcial={$fila['solicitud_id']}:{$fila['fecha']}:{$fila['tipo_nuevo']}")
            ->implode(' ');

        $partes = [
            'php artisan vacaciones:recalcular-inicial',
            $ci,
            $saldoInicial,
            '--fecha-ingreso=' . $empleado->fecha_ingreso->format('Y-m-d'),
        ];

        if ($opcionesDetalle !== '') {
            $partes[] = $opcionesDetalle;
        }

        $partes[] = '--apply';

        return implode(' ', $partes);
    }

    private function respaldar(Empleado $empleado, $historial, $actualizacionesSolicitudes): void
    {
        $directorio = storage_path('app/backups');
        File::ensureDirectoryExists($directorio);

        $solicitudesIds = $actualizacionesSolicitudes
            ->pluck('solicitud_id')
            ->filter()
            ->values();

        $respaldo = [
            'generado_en' => now()->toDateTimeString(),
            'empleado' => $empleado->fresh()->toArray(),
            'historial' => $historial->map->toArray()->all(),
            'solicitudes' => SolicitudVacacion::whereIn('id', $solicitudesIds)->get()->map->toArray()->all(),
            'detalles_solicitud' => DB::table('detalle_solicitud_vacacion')
                ->whereIn('solicitud_vacacion_id', $solicitudesIds)
                ->get()
                ->map(fn ($detalle) => (array) $detalle)
                ->all(),
        ];

        $ruta = "{$directorio}/saldo_inicial_{$empleado->ci}_" . now()->format('Ymd_His') . '.json';
        File::put($ruta, json_encode($respaldo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->line("Respaldo creado: {$ruta}");
    }
}
