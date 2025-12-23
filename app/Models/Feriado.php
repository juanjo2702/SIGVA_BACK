<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Feriado extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'fecha',
        'tipo',
        'sede_id',
        'activo',
    ];

    protected $casts = [
        'fecha' => 'date',
        'activo' => 'boolean',
    ];

    // Constantes de tipo
    const TIPO_NACIONAL = 'nacional';
    const TIPO_DEPARTAMENTAL = 'departamental';

    /**
     * Relación con sede (solo para departamentales)
     */
    public function sede()
    {
        return $this->belongsTo(Sede::class);
    }

    /**
     * Scope para feriados nacionales
     */
    public function scopeNacionales($query)
    {
        return $query->where('tipo', self::TIPO_NACIONAL);
    }

    /**
     * Scope para feriados departamentales
     */
    public function scopeDepartamentales($query)
    {
        return $query->where('tipo', self::TIPO_DEPARTAMENTAL);
    }

    /**
     * Scope para feriados activos
     */
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Scope para feriados de un año específico
     */
    public function scopeDelAno($query, $ano = null)
    {
        $ano = $ano ?? date('Y');
        return $query->whereYear('fecha', $ano);
    }

    /**
     * Obtener feriados aplicables a una sede (nacionales + departamentales de esa sede)
     */
    public function scopeParaSede($query, $sedeId)
    {
        return $query->where(function ($q) use ($sedeId) {
            $q->where('tipo', self::TIPO_NACIONAL)
                ->orWhere('sede_id', $sedeId);
        });
    }

    /**
     * Verificar si una fecha es feriado para una sede
     */
    public static function esFeriado($fecha, $sedeId = null): bool
    {
        $query = self::activos()->where('fecha', $fecha);

        if ($sedeId) {
            $query->where(function ($q) use ($sedeId) {
                $q->where('tipo', self::TIPO_NACIONAL)
                    ->orWhere('sede_id', $sedeId);
            });
        } else {
            $query->where('tipo', self::TIPO_NACIONAL);
        }

        return $query->exists();
    }
}
