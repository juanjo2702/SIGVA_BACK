<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sede extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'abreviacion',
        'departamento',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    /**
     * Relación con empleados
     */
    public function empleados()
    {
        return $this->hasMany(Empleado::class);
    }

    /**
     * Relación con feriados departamentales
     */
    public function feriados()
    {
        return $this->hasMany(Feriado::class);
    }

    /**
     * Scope para sedes activas
     */
    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }
}
