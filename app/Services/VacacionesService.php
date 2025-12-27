<?php

namespace App\Services;

use App\Models\Empleado;
use App\Models\HistorialVacacion;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class VacacionesService
{
    /**
     * Calcula los años de servicio de un empleado
     */
    public function calcularAnosServicio(Carbon $fechaIngreso): int
    {
        return $fechaIngreso->diffInYears(Carbon::now());
    }

    /**
     * Retorna los días de vacaciones correspondientes según antigüedad
     * 1-5 años: 15 días
     * 5-10 años: 20 días
     * Más de 10 años: 30 días
     */
    public function diasCorrespondientes(int $anosServicio): int
    {
        if ($anosServicio >= 10) {
            return 30;
        } elseif ($anosServicio >= 5) {
            return 20;
        } elseif ($anosServicio >= 1) {
            return 15;
        }

        return 0;
    }

    /**
     * Calcula los días hábiles entre dos fechas considerando el tipo de empleado
     *
     * Reglas:
     * - Domingo nunca cuenta para nadie
     * - Sábado SIEMPRE cuenta como día COMPLETO para todos (aunque sea medio tiempo)
     * - Mujeres medio tiempo: solo lunes-viernes (+sábados) son laborales
     * - Jornadas parciales (mañana/tarde):
     *   - Tiempo completo: cada día cuenta como 0.5
     *   - Medio tiempo: cada día cuenta como 1 (día completo porque no viene en su periodo)
     *
     * @param Carbon $inicio Fecha de inicio
     * @param Carbon $fin Fecha de fin
     * @param string $tipo 'completo', 'parcial_manana', 'parcial_tarde'
     * @param Empleado|null $empleado El empleado (para considerar género y tipo contrato)
     * @return float Días a descontar
     */
    public function calcularDiasHabiles(Carbon $inicio, Carbon $fin, string $tipo = 'completo', ?Empleado $empleado = null): float
    {
        $diasHabiles = 0;
        $period = CarbonPeriod::create($inicio, $fin);

        // ¿Es empleado de medio tiempo?
        $esMedioTiempo = $empleado && $empleado->esMedioTiempo();
        // ¿Es mujer de medio tiempo? (solo trabaja L-V)
        $esMujerMedioTiempo = $empleado && $empleado->esMujerMedioTiempo();

        foreach ($period as $fecha) {
            // Domingo NUNCA cuenta
            if ($fecha->dayOfWeek === Carbon::SUNDAY) {
                continue;
            }

            // Sábado SIEMPRE cuenta como día COMPLETO
            if ($fecha->dayOfWeek === Carbon::SATURDAY) {
                $diasHabiles += 1;
                continue;
            }

            // Para mujeres medio tiempo: solo lunes-viernes son laborales
            if ($esMujerMedioTiempo) {
                // Lunes a Viernes
                if ($fecha->dayOfWeek >= Carbon::MONDAY && $fecha->dayOfWeek <= Carbon::FRIDAY) {
                    $diasHabiles += 1;
                }
                // Si no es L-V (y ya descartamos domingo y sábado arriba), no cuenta
                continue;
            }

            // Para todos los demás empleados: L-V cuentan
            $diasHabiles += 1;
        }

        // Aplicar factor parcial según tipo de empleado y tipo de vacación
        if ($tipo !== 'completo') {
            if ($esMedioTiempo) {
                // Medio tiempo + parcial = DÍA COMPLETO (no viene en su periodo de trabajo)
                // Los días ya están contados como 1, no se modifica
            } else {
                // Tiempo completo + parcial = cada día cuenta como 0.5
                $diasHabiles = $diasHabiles * 0.5;
            }
        }

        return $diasHabiles;
    }

    /**
     * Calcula los días a descontar desde un array de días individuales con sus tipos
     *
     * @param array $dias Array con ['fecha' => 'Y-m-d', 'tipo' => 'completo|parcial_manana|parcial_tarde', 'etapa' => int (opcional)]
     * @param Empleado $empleado El empleado
     * @return array ['total' => float, 'detalles' => array]
     */
    public function calcularDiasDesdeArray(array $dias, Empleado $empleado): array
    {
        $detalles = [];
        $total = 0;

        foreach ($dias as $dia) {
            $fecha = Carbon::parse($dia['fecha']);
            $tipo = $dia['tipo'] ?? 'completo';
            $etapa = $dia['etapa'] ?? 1;

            $diasDescontados = $this->calcularDiaIndividual($fecha, $tipo, $empleado);

            if ($diasDescontados > 0) {
                $detalles[] = [
                    'fecha' => $fecha->format('Y-m-d'),
                    'tipo' => $tipo,
                    'dias_descontados' => $diasDescontados,
                    'es_sabado' => $fecha->isSaturday(),
                    'dia_semana' => $fecha->locale('es')->dayName,
                    'etapa' => $etapa,
                ];
                $total += $diasDescontados;
            }
        }

        return [
            'total' => $total,
            'detalles' => $detalles,
            'saldo_actual' => $empleado->saldo_vacaciones,
            'saldo_resultante' => $empleado->saldo_vacaciones - $total,
        ];
    }

    /**
     * Calcula los días a descontar para un día individual
     *
     * @param Carbon $fecha La fecha
     * @param string $tipo 'completo', 'parcial_manana', 'parcial_tarde'
     * @param Empleado $empleado El empleado
     * @return float Días a descontar (0, 0.5, o 1)
     */
    public function calcularDiaIndividual(Carbon $fecha, string $tipo, Empleado $empleado): float
    {
        $esMedioTiempo = $empleado->esMedioTiempo();
        $esMujerMedioTiempo = $empleado->esMujerMedioTiempo();

        // Domingo NUNCA cuenta
        if ($fecha->isSunday()) {
            return 0;
        }

        // Para mujeres medio tiempo: sábado NO cuenta (no trabajan sábados)
        if ($esMujerMedioTiempo && $fecha->isSaturday()) {
            return 0;
        }

        // Sábado SIEMPRE es día completo (no se permite parcial)
        if ($fecha->isSaturday()) {
            return 1.0;
        }

        // Día de semana (L-V)
        if ($tipo === 'completo') {
            return 1.0;
        }

        // Tipo parcial (mañana o tarde)
        if ($esMedioTiempo) {
            // Medio tiempo + parcial = día completo (no viene en su periodo)
            return 1.0;
        } else {
            // Tiempo completo + parcial = 0.5 días
            return 0.5;
        }
    }

    /**
     * Valida un array de días para solicitud de vacaciones
     */
    public function validarDiasArray(array $dias, Empleado $empleado): array
    {
        $errors = [];
        $esMujerMedioTiempo = $empleado->esMujerMedioTiempo();

        foreach ($dias as $dia) {
            $fecha = Carbon::parse($dia['fecha']);
            $tipo = $dia['tipo'] ?? 'completo';

            // Validar domingo
            if ($fecha->isSunday()) {
                $errors[] = "No se puede seleccionar domingo ({$fecha->format('d/m/Y')}).";
            }

            // Validar sábado para mujer medio tiempo
            if ($esMujerMedioTiempo && $fecha->isSaturday()) {
                $errors[] = "Las empleadas a medio tiempo no pueden seleccionar sábados ({$fecha->format('d/m/Y')}).";
            }

            // Validar sábado parcial para tiempo completo
            if ($fecha->isSaturday() && $tipo !== 'completo' && !$empleado->esMedioTiempo()) {
                $errors[] = "El sábado ({$fecha->format('d/m/Y')}) debe ser día completo, no se permite parcial.";
            }
        }

        // Calcular días
        $calculo = $this->calcularDiasDesdeArray($dias, $empleado);

        return [
            'valid' => count($errors) === 0,
            'errors' => $errors,
            'dias' => $calculo['total'],
            'detalles' => $calculo['detalles'],
            'saldo_resultante' => $calculo['saldo_resultante'],
            'advertencia_negativo' => $calculo['saldo_resultante'] < 0,
        ];
    }

    /**
     * Valida una solicitud de vacaciones
     * @return array ['valid' => bool, 'errors' => array, 'dias' => float]
     */
    public function validarSolicitud(array $data, Empleado $empleado): array
    {
        $errors = [];
        $fechaInicio = Carbon::parse($data['fecha_inicio']);
        $fechaFin = Carbon::parse($data['fecha_fin']);
        $tipo = $data['tipo'] ?? 'completo';

        // Validar que fecha fin sea mayor o igual a fecha inicio
        if ($fechaFin->lt($fechaInicio)) {
            $errors[] = 'La fecha de fin debe ser mayor o igual a la fecha de inicio.';
        }

        // Validar que sábado no sea parcial para tiempo completo
        if ($tipo !== 'completo' && !$empleado->esMedioTiempo()) {
            // Si es un solo día y es sábado, no se permite parcial
            if ($fechaInicio->isSaturday() && $fechaInicio->eq($fechaFin)) {
                $errors[] = 'No se permite día parcial en sábado. El sábado cuenta como día completo.';
            }
        }

        // Calcular días con la nueva lógica
        $diasSolicitados = $this->calcularDiasHabiles($fechaInicio, $fechaFin, $tipo, $empleado);

        // Validar que no solicite más días de los que tiene disponibles
        // (Se permite saldo negativo, pero mostramos advertencia)
        $saldoResultante = $empleado->saldo_vacaciones - $diasSolicitados;

        return [
            'valid' => count($errors) === 0,
            'errors' => $errors,
            'dias' => $diasSolicitados,
            'saldo_resultante' => $saldoResultante,
            'advertencia_negativo' => $saldoResultante < 0,
        ];
    }

    /**
     * Procesa la suma anual de vacaciones para un empleado
     */
    public function procesarSumaAnual(Empleado $empleado): ?HistorialVacacion
    {
        if (!$empleado->esAniversarioHoy()) {
            return null;
        }

        $diasCorrespondientes = $empleado->dias_correspondientes;

        if ($diasCorrespondientes <= 0) {
            return null;
        }

        $saldoAnterior = $empleado->saldo_vacaciones;
        $empleado->saldo_vacaciones += $diasCorrespondientes;
        $empleado->save();

        return HistorialVacacion::registrar(
            $empleado,
            $saldoAnterior,
            $diasCorrespondientes,
            HistorialVacacion::TIPO_SUMA_ANUAL,
            "Suma anual automática por aniversario de contrato. Años de servicio: {$empleado->anos_servicio}"
        );
    }

    /**
     * Descuenta días de vacaciones por solicitud aprobada
     */
    public function descontarVacaciones(Empleado $empleado, float $dias, int $solicitudId, ?int $userId = null): HistorialVacacion
    {
        $saldoAnterior = $empleado->saldo_vacaciones;
        $empleado->saldo_vacaciones -= $dias;
        $empleado->save();

        return HistorialVacacion::registrar(
            $empleado,
            $saldoAnterior,
            -$dias,
            HistorialVacacion::TIPO_SOLICITUD_APROBADA,
            "Solicitud de vacaciones #{$solicitudId} aprobada",
            $userId
        );
    }

    /**
     * Devuelve días de vacaciones por cancelación de solicitud aprobada
     */
    public function devolverVacaciones(Empleado $empleado, float $dias, int $solicitudId, ?int $userId = null, ?string $motivo = null): HistorialVacacion
    {
        $saldoAnterior = $empleado->saldo_vacaciones;
        $empleado->saldo_vacaciones += $dias;
        $empleado->save();

        $descripcion = $motivo ?? "Cancelación de solicitud #{$solicitudId}";

        return HistorialVacacion::registrar(
            $empleado,
            $saldoAnterior,
            $dias,
            'cancelacion',
            $descripcion,
            $userId
        );
    }

    /**
     * Ajuste manual de saldo de vacaciones
     */
    public function ajustarSaldo(Empleado $empleado, float $nuevoDias, string $descripcion, int $userId): HistorialVacacion
    {
        $saldoAnterior = $empleado->saldo_vacaciones;
        $cambio = $nuevoDias - $saldoAnterior;
        $empleado->saldo_vacaciones = $nuevoDias;
        $empleado->save();

        return HistorialVacacion::registrar(
            $empleado,
            $saldoAnterior,
            $cambio,
            HistorialVacacion::TIPO_AJUSTE_MANUAL,
            $descripcion,
            $userId
        );
    }

    /**
     * Valida un array de días usando un saldo específico (para edición de aprobadas)
     */
    public function validarDiasArrayConSaldo(array $dias, Empleado $empleado, float $saldoParaValidar): array
    {
        $errors = [];
        $esMujerMedioTiempo = $empleado->esMujerMedioTiempo();

        foreach ($dias as $dia) {
            $fecha = Carbon::parse($dia['fecha']);
            $tipo = $dia['tipo'] ?? 'completo';

            // Validar domingo
            if ($fecha->isSunday()) {
                $errors[] = "No se puede seleccionar domingo ({$fecha->format('d/m/Y')}).";
            }

            // Validar sábado para mujer medio tiempo
            if ($esMujerMedioTiempo && $fecha->isSaturday()) {
                $errors[] = "Las empleadas a medio tiempo no pueden seleccionar sábados ({$fecha->format('d/m/Y')}).";
            }

            // Validar sábado parcial para tiempo completo
            if ($fecha->isSaturday() && $tipo !== 'completo' && !$empleado->esMedioTiempo()) {
                $errors[] = "El sábado ({$fecha->format('d/m/Y')}) debe ser día completo, no se permite parcial.";
            }
        }

        // Calcular días
        $calculo = $this->calcularDiasDesdeArray($dias, $empleado);

        // Usar el saldo pasado en lugar del saldo actual del empleado
        $saldoResultante = $saldoParaValidar - $calculo['total'];

        return [
            'valid' => count($errors) === 0,
            'errors' => $errors,
            'dias' => $calculo['total'],
            'detalles' => $calculo['detalles'],
            'saldo_resultante' => $saldoResultante,
            'advertencia_negativo' => $saldoResultante < 0,
        ];
    }

    /**
     * Registra en historial el ajuste por edición de solicitud aprobada
     */
    public function registrarAjustePorEdicion(
        Empleado $empleado,
        int $solicitudId,
        float $diasOriginales,
        float $diasNuevos,
        float $diferencia
    ): HistorialVacacion {
        $saldoAnterior = $empleado->saldo_vacaciones - $diferencia;

        $descripcion = "Edición de solicitud #{$solicitudId}: días cambiados de {$diasOriginales} a {$diasNuevos}";

        return HistorialVacacion::registrar(
            $empleado,
            $saldoAnterior,
            $diferencia,
            HistorialVacacion::TIPO_AJUSTE_MANUAL,
            $descripcion,
            auth()->id()
        );
    }
}
