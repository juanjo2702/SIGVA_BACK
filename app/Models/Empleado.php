<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Empleado extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'apellido_paterno',
        'apellido_materno',
        'nombres',
        'ci',
        'genero',
        'tipo_contrato',
        'sede_id',
        'cargo',
        'fecha_ingreso',
        'saldo_vacaciones',
        'activo',
    ];

    protected $casts = [
        'fecha_ingreso' => 'date',
        'saldo_vacaciones' => 'decimal:1',
        'activo' => 'boolean',
    ];

    protected $appends = [
        'nombre_completo',
        'anos_servicio',
        'dias_correspondientes',
    ];

    // Constantes de género
    const GENERO_MASCULINO = 'Masculino';
    const GENERO_FEMENINO = 'Femenino';

    // Constantes de tipo de contrato
    const CONTRATO_COMPLETO = 'completo';
    const CONTRATO_MEDIO_TIEMPO = 'medio_tiempo';

    // Relaciones
    public function solicitudes()
    {
        return $this->hasMany(SolicitudVacacion::class);
    }

    public function historial()
    {
        return $this->hasMany(HistorialVacacion::class);
    }

    public function sede()
    {
        return $this->belongsTo(Sede::class, 'sede_id', 'id_sede');
    }

    // Scopes
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopeBuscarPorCi($query, $ci)
    {
        return $query->where('ci', $ci);
    }

    // Atributos calculados
    public function getNombreCompletoAttribute(): string
    {
        $nombre = $this->apellido_paterno;
        if ($this->apellido_materno) {
            $nombre .= ' ' . $this->apellido_materno;
        }
        $nombre .= ' ' . $this->nombres;
        return $nombre;
    }

    public function getAnosServicioAttribute(): int
    {
        return $this->fecha_ingreso->diffInYears(Carbon::now());
    }

    public function getDiasCorrespondientesAttribute(): int
    {
        $anos = $this->anos_servicio;

        if ($anos >= 10) {
            return 30;
        } elseif ($anos >= 5) {
            return 20;
        } elseif ($anos >= 1) {
            return 15;
        }

        return 0; // Menos de 1 año no tiene vacaciones
    }

    // Método para verificar si es aniversario de contrato
    public function esAniversarioHoy(): bool
    {
        $hoy = Carbon::now();
        return $this->fecha_ingreso->month === $hoy->month
            && $this->fecha_ingreso->day === $hoy->day
            && $this->anos_servicio >= 1;
    }

    /**
     * Verifica si es mujer de medio tiempo
     */
    public function esMujerMedioTiempo(): bool
    {
        return $this->genero === self::GENERO_FEMENINO
            && $this->tipo_contrato === self::CONTRATO_MEDIO_TIEMPO;
    }

    /**
     * Verifica si es de medio tiempo
     */
    public function esMedioTiempo(): bool
    {
        return $this->tipo_contrato === self::CONTRATO_MEDIO_TIEMPO;
    }

    /**
     * Retorna los días de la semana que cuenta como laborales
     * 0 = Domingo, 1 = Lunes, ..., 6 = Sábado
     * Mujeres medio tiempo: solo Lunes-Viernes (1-5)
     * Todos los demás: Lunes-Sábado (1-6)
     * Domingo nunca cuenta para nadie
     */
    public function getDiasLaborales(): array
    {
        if ($this->esMujerMedioTiempo()) {
            // Mujeres medio tiempo: solo lunes a viernes
            return [1, 2, 3, 4, 5]; // Carbon::MONDAY to Carbon::FRIDAY
        }
        // Todos los demás: lunes a sábado
        return [1, 2, 3, 4, 5, 6]; // Carbon::MONDAY to Carbon::SATURDAY
    }

    /**
     * Verifica si un día específico es laboral para este empleado
     */
    public function esDiaLaboral(Carbon $fecha): bool
    {
        $diaSemana = $fecha->dayOfWeek;

        // Domingo nunca es laboral
        if ($diaSemana === Carbon::SUNDAY) {
            return false;
        }

        // Sábado siempre es laboral Y cuenta como día completo para todos
        if ($diaSemana === Carbon::SATURDAY) {
            return true;
        }

        // Para mujeres medio tiempo, solo L-V son laborales
        if ($this->esMujerMedioTiempo()) {
            return in_array($diaSemana, [1, 2, 3, 4, 5]);
        }

        return true;
    }
}
