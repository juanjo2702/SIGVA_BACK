<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class DetalleSolicitudVacacion extends Model
{
    use HasFactory;

    protected $table = 'detalle_solicitud_vacacion';

    protected $fillable = [
        'solicitud_vacacion_id',
        'fecha',
        'tipo',
        'dias_descontados',
    ];

    protected $casts = [
        'fecha' => 'date',
        'dias_descontados' => 'decimal:1',
    ];

    // Constantes de tipo
    const TIPO_COMPLETO = 'completo';
    const TIPO_MANANA = 'parcial_manana';
    const TIPO_TARDE = 'parcial_tarde';

    /**
     * Relación con solicitud
     */
    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudVacacion::class, 'solicitud_vacacion_id');
    }

    /**
     * Nombre del día de la semana
     */
    public function getDiaSemanaAttribute(): string
    {
        $dias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        return $dias[$this->fecha->dayOfWeek];
    }

    /**
     * Tipo formateado para mostrar
     */
    public function getTipoFormateadoAttribute(): string
    {
        return match ($this->tipo) {
            self::TIPO_COMPLETO => 'Día Completo',
            self::TIPO_MANANA => 'Mañana',
            self::TIPO_TARDE => 'Tarde',
            default => $this->tipo,
        };
    }

    /**
     * Es sábado
     */
    public function esSabado(): bool
    {
        return $this->fecha->isSaturday();
    }

    /**
     * Es día de semana (L-V)
     */
    public function esDiaSemana(): bool
    {
        return $this->fecha->isWeekday();
    }
}
