<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SolicitudVacacion extends Model
{
    use HasFactory;

    protected $table = 'solicitud_vacaciones';

    protected $fillable = [
        'empleado_id',
        'fecha_solicitud',
        'fecha_inicio',
        'fecha_fin',
        'tipo',
        'dias_solicitados',
        'estado',
        'motivo_rechazo',
        'lugar_solicitud',
        'tiene_reemplazo',
        'nombre_reemplazo',
        'documento_entregado',
    ];

    protected $casts = [
        'fecha_solicitud' => 'date',
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'dias_solicitados' => 'decimal:1',
        'tiene_reemplazo' => 'boolean',
        'documento_entregado' => 'boolean',
    ];

    // Constantes de estado
    const ESTADO_PENDIENTE = 'pendiente';
    const ESTADO_PENDIENTE_DOCUMENTO = 'pendiente_documento';
    const ESTADO_APROBADA = 'aprobada';
    const ESTADO_RECHAZADA = 'rechazada';

    // Constantes de tipo
    const TIPO_COMPLETO = 'completo';
    const TIPO_PARCIAL_MANANA = 'parcial_manana';
    const TIPO_PARCIAL_TARDE = 'parcial_tarde';

    // Relaciones
    public function empleado()
    {
        return $this->belongsTo(Empleado::class);
    }

    /**
     * Relación con los detalles de días individuales
     */
    public function detalles()
    {
        return $this->hasMany(DetalleSolicitudVacacion::class, 'solicitud_vacacion_id')->orderBy('fecha');
    }

    // Scopes
    public function scopePendientes($query)
    {
        return $query->where('estado', self::ESTADO_PENDIENTE);
    }

    public function scopePendientesDocumento($query)
    {
        return $query->where('estado', self::ESTADO_PENDIENTE_DOCUMENTO);
    }

    public function scopeAprobadas($query)
    {
        return $query->where('estado', self::ESTADO_APROBADA);
    }

    public function scopeRechazadas($query)
    {
        return $query->where('estado', self::ESTADO_RECHAZADA);
    }

    public function scopeDelAno($query, $ano = null)
    {
        $ano = $ano ?? date('Y');
        return $query->whereYear('fecha_solicitud', $ano);
    }

    // Métodos de estado
    public function esPendiente(): bool
    {
        return $this->estado === self::ESTADO_PENDIENTE;
    }

    public function esPendienteDocumento(): bool
    {
        return $this->estado === self::ESTADO_PENDIENTE_DOCUMENTO;
    }

    public function esAprobada(): bool
    {
        return $this->estado === self::ESTADO_APROBADA;
    }

    public function esRechazada(): bool
    {
        return $this->estado === self::ESTADO_RECHAZADA;
    }

    public function esParcial(): bool
    {
        return in_array($this->tipo, [self::TIPO_PARCIAL_MANANA, self::TIPO_PARCIAL_TARDE]);
    }

    // Métodos de reemplazo
    public function tieneReemplazo(): bool
    {
        return $this->tiene_reemplazo && !empty($this->nombre_reemplazo);
    }

    public function documentoRecibido(): bool
    {
        return $this->documento_entregado;
    }

    /**
     * Obtiene el texto de reemplazo para mostrar en formulario
     */
    public function getTextoReemplazoAttribute(): string
    {
        if ($this->tiene_reemplazo && !empty($this->nombre_reemplazo)) {
            return $this->nombre_reemplazo;
        }
        return 'Sin Reemplazo';
    }
}
