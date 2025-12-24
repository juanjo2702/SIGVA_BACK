<?php

namespace App\Services;

use App\Models\Empleado;
use App\Models\SolicitudVacacion;
use App\Models\DetalleSolicitudVacacion;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SolicitudService
{
    protected VacacionesService $vacacionesService;

    public function __construct(VacacionesService $vacacionesService)
    {
        $this->vacacionesService = $vacacionesService;
    }

    /**
     * Obtener estadísticas de solicitudes
     */
    public function getEstadisticas(int $ano): array
    {
        $pendientes = SolicitudVacacion::pendientes()->delAno($ano)->count();
        $pendientesDocumento = SolicitudVacacion::pendientesDocumento()->delAno($ano)->count();
        $aprobadas = SolicitudVacacion::aprobadas()->delAno($ano)->count();
        $rechazadas = SolicitudVacacion::rechazadas()->delAno($ano)->count();
        $diasAprobados = SolicitudVacacion::aprobadas()->delAno($ano)->sum('dias_solicitados');

        return [
            'pendientes' => $pendientes,
            'pendientes_documento' => $pendientesDocumento,
            'aprobadas' => $aprobadas,
            'rechazadas' => $rechazadas,
            'total' => $pendientes + $pendientesDocumento + $aprobadas + $rechazadas,
            'dias_aprobados' => $diasAprobados,
            'ano' => $ano,
        ];
    }

    /**
     * Aprobar una solicitud
     *
     * @throws \InvalidArgumentException
     */
    public function aprobar(SolicitudVacacion $solicitud, ?int $userId = null): SolicitudVacacion
    {
        if (!$solicitud->esPendiente()) {
            throw new \InvalidArgumentException('Solo se pueden aprobar solicitudes pendientes.');
        }

        // Aprobar
        $solicitud->estado = SolicitudVacacion::ESTADO_APROBADA;
        $solicitud->save();

        // Descontar del saldo del empleado
        $this->vacacionesService->descontarVacaciones(
            $solicitud->empleado,
            $solicitud->dias_solicitados,
            $solicitud->id,
            $userId
        );

        return $solicitud->fresh(['empleado']);
    }

    /**
     * Rechazar una solicitud
     *
     * @throws \InvalidArgumentException
     */
    public function rechazar(SolicitudVacacion $solicitud, string $motivo): SolicitudVacacion
    {
        if (!$solicitud->esPendiente()) {
            throw new \InvalidArgumentException('Solo se pueden rechazar solicitudes pendientes.');
        }

        $solicitud->estado = SolicitudVacacion::ESTADO_RECHAZADA;
        $solicitud->motivo_rechazo = $motivo;
        $solicitud->save();

        return $solicitud->fresh(['empleado']);
    }

    /**
     * Confirmar recepción de documento y aprobar
     *
     * @throws \InvalidArgumentException
     */
    public function confirmarDocumento(SolicitudVacacion $solicitud, ?int $userId = null): SolicitudVacacion
    {
        if (!$solicitud->esPendienteDocumento()) {
            throw new \InvalidArgumentException('Solo se puede confirmar documento para solicitudes en estado "pendiente_documento".');
        }

        // Marcar documento como entregado y aprobar
        $solicitud->documento_entregado = true;
        $solicitud->estado = SolicitudVacacion::ESTADO_APROBADA;
        $solicitud->save();

        // Descontar días del saldo
        $this->vacacionesService->descontarVacaciones(
            $solicitud->empleado,
            $solicitud->dias_solicitados,
            $solicitud->id,
            $userId
        );

        return $solicitud->fresh(['empleado']);
    }

    /**
     * Cancelar una solicitud
     * Si estaba aprobada, devuelve los días al saldo del empleado
     *
     * @throws \InvalidArgumentException
     */
    public function cancelar(SolicitudVacacion $solicitud, string $motivo, ?int $userId = null): SolicitudVacacion
    {
        if (!$solicitud->puedeCancelarse()) {
            throw new \InvalidArgumentException('No se puede cancelar esta solicitud. Solo pueden cancelarse solicitudes pendientes, pendientes de documento o aprobadas.');
        }

        $estabaAprobada = $solicitud->esAprobada();
        $diasDevolver = $solicitud->dias_solicitados;

        // Cambiar estado a cancelada
        $solicitud->estado = SolicitudVacacion::ESTADO_CANCELADA;
        $solicitud->motivo_cancelacion = $motivo;
        $solicitud->cancelada_por = $userId;
        $solicitud->fecha_cancelacion = Carbon::now();
        $solicitud->save();

        // Si estaba aprobada, devolver los días al saldo del empleado
        if ($estabaAprobada) {
            $this->vacacionesService->devolverVacaciones(
                $solicitud->empleado,
                $diasDevolver,
                $solicitud->id,
                $userId,
                'Cancelación de solicitud: ' . $motivo
            );
        }

        return $solicitud->fresh(['empleado']);
    }

    /**
     * Programar vacaciones para un empleado (Talento Humano)
     */
    public function programarVacaciones(
        Empleado $empleado,
        array $dias,
        bool $tieneReemplazo = false,
        ?string $nombreReemplazo = null,
        bool $mostrarPorEtapas = false
    ): array {
        // Validar días
        $validacion = $this->vacacionesService->validarDiasArray($dias, $empleado);

        if (!$validacion['valid']) {
            return [
                'success' => false,
                'errors' => $validacion['errors'],
            ];
        }

        // Ordenar días por fecha
        $diasOrdenados = collect($validacion['detalles'])->sortBy('fecha')->values();
        $primeraFecha = $diasOrdenados->first()['fecha'];
        $ultimaFecha = $diasOrdenados->last()['fecha'];

        // Detectar tipo automáticamente
        $tipoDetectado = $this->detectarTipoVacacion($diasOrdenados);

        // Crear solicitud
        $solicitud = SolicitudVacacion::create([
            'empleado_id' => $empleado->id,
            'fecha_solicitud' => Carbon::now(),
            'fecha_inicio' => $primeraFecha,
            'fecha_fin' => $ultimaFecha,
            'tipo' => $tipoDetectado,
            'dias_solicitados' => $validacion['dias'],
            'estado' => SolicitudVacacion::ESTADO_PENDIENTE_DOCUMENTO,
            'lugar_solicitud' => 'Programada por Talento Humano',
            'tiene_reemplazo' => $tieneReemplazo,
            'nombre_reemplazo' => $tieneReemplazo ? $nombreReemplazo : null,
            'documento_entregado' => false,
            'mostrar_por_etapas' => $mostrarPorEtapas,
        ]);

        // Crear detalles de cada día
        foreach ($validacion['detalles'] as $detalle) {
            $solicitud->detalles()->create([
                'fecha' => $detalle['fecha'],
                'tipo' => $detalle['tipo'],
                'dias_descontados' => $detalle['dias_descontados'],
            ]);
        }

        return [
            'success' => true,
            'solicitud' => $solicitud->load(['empleado', 'detalles']),
            'tipo_detectado' => $tipoDetectado,
            'dias_programados' => $validacion['dias'],
            'detalles' => $validacion['detalles'],
            'saldo_actual' => $empleado->saldo_vacaciones,
            'saldo_despues_aprobar' => $validacion['saldo_resultante'],
        ];
    }

    /**
     * Generar datos para el formulario PDF
     */
    public function generarDatosFormulario(SolicitudVacacion $solicitud): array
    {
        $empleado = $solicitud->empleado;

        return [
            'empleado' => [
                'nombre_completo' => $empleado->nombre_completo,
                'ci' => $empleado->ci,
                'cargo' => $empleado->cargo,
                'sede' => $empleado->sede?->nombre ?? 'Sin asignar',
                'fecha_ingreso' => $empleado->fecha_ingreso->format('d/m/Y'),
                'anos_servicio' => $empleado->anos_servicio,
                'dias_correspondientes' => $empleado->dias_correspondientes,
                'genero' => $empleado->genero ?? 'No especificado',
                'tipo_contrato' => $empleado->tipo_contrato === 'medio_tiempo' ? 'Medio Tiempo' : 'Tiempo Completo',
            ],
            'solicitud' => [
                'id' => $solicitud->id,
                'fecha_solicitud' => $solicitud->fecha_solicitud->format('d/m/Y'),
                'fecha_inicio' => $solicitud->fecha_inicio->format('d/m/Y'),
                'fecha_fin' => $solicitud->fecha_fin->format('d/m/Y'),
                'dias_solicitados' => $solicitud->dias_solicitados,
                'tipo' => $this->traducirTipo($solicitud->tipo),
                'reemplazo' => $solicitud->texto_reemplazo,
                'estado' => $this->traducirEstado($solicitud->estado),
            ],
            'saldo' => [
                // Si está aprobada, el saldo YA fue descontado, entonces:
                // - 'actual' = saldo ANTES de aprobar (saldo_vacaciones + dias_solicitados)
                // - 'despues' = saldo actual (ya descontado)
                'actual' => $solicitud->esAprobada()
                    ? $empleado->saldo_vacaciones + $solicitud->dias_solicitados
                    : $empleado->saldo_vacaciones,
                'despues' => $solicitud->esAprobada()
                    ? $empleado->saldo_vacaciones
                    : $empleado->saldo_vacaciones - $solicitud->dias_solicitados,
            ],
            'etapas' => $solicitud->mostrar_por_etapas ? $this->agruparDiasEnEtapas($solicitud) : [],
            'mostrar_por_etapas' => $solicitud->mostrar_por_etapas ?? false,
        ];
    }

    /**
     * Agrupa los días de una solicitud en etapas consecutivas
     */
    public function agruparDiasEnEtapas(SolicitudVacacion $solicitud): array
    {
        $detalles = $solicitud->detalles()->orderBy('fecha')->get();

        if ($detalles->isEmpty()) {
            // Si no hay detalles, retornar una sola etapa con las fechas de la solicitud
            return [[
                'numero' => 1,
                'fecha_inicio' => $solicitud->fecha_inicio->format('d/m/Y'),
                'fecha_fin' => $solicitud->fecha_fin->format('d/m/Y'),
                'dias' => $solicitud->dias_solicitados,
            ]];
        }

        $etapas = [];
        $etapaActual = null;
        $diasEtapa = 0;
        $fechaAnterior = null;

        foreach ($detalles as $detalle) {
            $fechaActual = Carbon::parse($detalle->fecha);

            // Determinar si hay un gap (más de un día laboral entre fechas)
            $hayGap = false;
            if ($fechaAnterior) {
                $diasEntre = $fechaAnterior->copy()->addDay();
                while ($diasEntre->lt($fechaActual)) {
                    if ($diasEntre->dayOfWeek !== Carbon::SUNDAY) {
                        $hayGap = true;
                        break;
                    }
                    $diasEntre->addDay();
                }
            }

            // Si hay gap o es el primer día, iniciar nueva etapa
            if (!$etapaActual || $hayGap) {
                // Guardar etapa anterior si existe
                if ($etapaActual) {
                    $etapaActual['dias'] = $diasEtapa;
                    $etapas[] = $etapaActual;
                }

                // Iniciar nueva etapa
                $etapaActual = [
                    'numero' => count($etapas) + 1,
                    'fecha_inicio' => $fechaActual->format('d/m/Y'),
                    'fecha_fin' => $fechaActual->format('d/m/Y'),
                ];
                $diasEtapa = 0;
            }

            // Actualizar fecha fin y acumular días
            $etapaActual['fecha_fin'] = $fechaActual->format('d/m/Y');
            $diasEtapa += $detalle->dias_descontados;
            $fechaAnterior = $fechaActual;
        }

        // Guardar última etapa
        if ($etapaActual) {
            $etapaActual['dias'] = $diasEtapa;
            $etapas[] = $etapaActual;
        }

        return $etapas;
    }

    /**
     * Detecta automáticamente el tipo de vacación basado en los días seleccionados
     */
    public function detectarTipoVacacion(Collection $diasOrdenados): string
    {
        // Verificar si hay días parciales
        $tieneParciales = $diasOrdenados->contains(function ($dia) {
            return $dia['tipo'] !== 'completo';
        });

        // Verificar si son continuos
        $esContinuo = $this->sonDiasContinuos($diasOrdenados);

        if ($tieneParciales) {
            return $esContinuo ? 'parcial_continua' : 'parcial_discontinua';
        } else {
            return $esContinuo ? 'completa_continua' : 'completa_discontinua';
        }
    }

    /**
     * Verifica si los días seleccionados son continuos (sin gaps de días laborales)
     */
    public function sonDiasContinuos(Collection $diasOrdenados): bool
    {
        if ($diasOrdenados->count() <= 1) {
            return true;
        }

        $fechas = $diasOrdenados->pluck('fecha')->map(fn($f) => Carbon::parse($f))->values();

        for ($i = 1; $i < $fechas->count(); $i++) {
            $fechaAnterior = $fechas[$i - 1];
            $fechaActual = $fechas[$i];

            // Calcular los días laborales entre las dos fechas
            $diasEntre = $fechaAnterior->copy()->addDay();

            while ($diasEntre->lt($fechaActual)) {
                // Si hay un día laboral (L-S) no seleccionado, no es continuo
                if ($diasEntre->dayOfWeek !== Carbon::SUNDAY) {
                    return false;
                }
                $diasEntre->addDay();
            }
        }

        return true;
    }

    /**
     * Traduce el tipo de vacación a español
     */
    public function traducirTipo(?string $tipo): string
    {
        if (!$tipo) {
            return 'No especificado';
        }

        return match ($tipo) {
            'completo' => 'Día Completo',
            'parcial_manana' => 'Medio Día (Mañana)',
            'parcial_tarde' => 'Medio Día (Tarde)',
            'completa_continua' => 'Completa Continua',
            'completa_discontinua' => 'Completa Discontinua',
            'parcial_continua' => 'Parcial Continua',
            'parcial_discontinua' => 'Parcial Discontinua',
            default => $tipo,
        };
    }

    /**
     * Traduce el estado a español
     */
    public function traducirEstado(string $estado): string
    {
        return match ($estado) {
            'pendiente' => 'Pendiente',
            'pendiente_documento' => 'Pendiente Documento',
            'aprobada' => 'Aprobada',
            'rechazada' => 'Rechazada',
            default => $estado,
        };
    }

    /**
     * Actualizar una solicitud existente
     * Si está aprobada, recalcula el saldo del empleado
     */
    public function actualizarSolicitud(
        SolicitudVacacion $solicitud,
        array $dias,
        bool $tieneReemplazo = false,
        ?string $nombreReemplazo = null
    ): array {
        $empleado = $solicitud->empleado;
        $diasOriginales = $solicitud->dias_solicitados;
        $estaAprobada = $solicitud->esAprobada();

        // Validar días (para aprobadas, necesitamos considerar los días que se van a devolver)
        $saldoParaValidar = $estaAprobada
            ? $empleado->saldo_vacaciones + $diasOriginales
            : $empleado->saldo_vacaciones;

        $validacion = $this->vacacionesService->validarDiasArrayConSaldo($dias, $empleado, $saldoParaValidar);

        if (!$validacion['valid']) {
            return [
                'success' => false,
                'errors' => $validacion['errors'],
            ];
        }

        // Ordenar días por fecha
        $diasOrdenados = collect($validacion['detalles'])->sortBy('fecha')->values();
        $primeraFecha = $diasOrdenados->first()['fecha'];
        $ultimaFecha = $diasOrdenados->last()['fecha'];

        // Detectar tipo automáticamente
        $tipoDetectado = $this->detectarTipoVacacion($diasOrdenados);

        $diasNuevos = $validacion['dias'];

        // Si está aprobada, ajustar el saldo del empleado
        if ($estaAprobada && $diasOriginales != $diasNuevos) {
            $diferencia = $diasOriginales - $diasNuevos;

            // Ajustar saldo: devolver días originales y descontar nuevos
            // Esto es equivalente a sumar la diferencia
            $empleado->saldo_vacaciones = $empleado->saldo_vacaciones + $diferencia;
            $empleado->save();

            // Registrar en historial
            $this->vacacionesService->registrarAjustePorEdicion(
                $empleado,
                $solicitud->id,
                $diasOriginales,
                $diasNuevos,
                $diferencia
            );
        }

        // Actualizar solicitud
        $solicitud->update([
            'fecha_inicio' => $primeraFecha,
            'fecha_fin' => $ultimaFecha,
            'tipo' => $tipoDetectado,
            'dias_solicitados' => $diasNuevos,
            'tiene_reemplazo' => $tieneReemplazo,
            'nombre_reemplazo' => $tieneReemplazo ? $nombreReemplazo : null,
        ]);

        // Eliminar detalles anteriores
        $solicitud->detalles()->delete();

        // Crear nuevos detalles
        foreach ($validacion['detalles'] as $detalle) {
            $solicitud->detalles()->create([
                'fecha' => $detalle['fecha'],
                'tipo' => $detalle['tipo'],
                'dias_descontados' => $detalle['dias_descontados'],
            ]);
        }

        return [
            'success' => true,
            'solicitud' => $solicitud->fresh(['empleado', 'detalles']),
            'tipo_detectado' => $tipoDetectado,
            'dias_actualizados' => $diasNuevos,
            'dias_originales' => $diasOriginales,
            'ajuste_realizado' => $estaAprobada && $diasOriginales != $diasNuevos,
            'detalles' => $validacion['detalles'],
            'saldo_actual' => $empleado->fresh()->saldo_vacaciones,
        ];
    }
}
