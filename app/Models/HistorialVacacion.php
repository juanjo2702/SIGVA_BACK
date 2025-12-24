<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HistorialVacacion extends Model
{
    use HasFactory;

    protected $table = 'historial_vacaciones';

    protected $fillable = [
        'empleado_id',
        'dias_anteriores',
        'dias_cambio',
        'dias_nuevos',
        'tipo_cambio',
        'descripcion',
        'user_id',
    ];

    protected $casts = [
        'dias_anteriores' => 'decimal:1',
        'dias_cambio' => 'decimal:1',
        'dias_nuevos' => 'decimal:1',
    ];

    // Constantes de tipo de cambio
    const TIPO_SUMA_ANUAL = 'suma_anual';
    const TIPO_SOLICITUD_APROBADA = 'solicitud_aprobada';
    const TIPO_AJUSTE_MANUAL = 'ajuste_manual';
    const TIPO_IMPORTACION = 'importacion';
    const TIPO_DEVOLUCION_FERIADO = 'devolucion_feriado';

    // Relaciones
    public function empleado()
    {
        return $this->belongsTo(Empleado::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Método estático para registrar cambio
    public static function registrar(
        Empleado $empleado,
        float $diasAnteriores,
        float $diasCambio,
        string $tipoCambio,
        ?string $descripcion = null,
        ?int $userId = null
    ): self {
        return self::create([
            'empleado_id' => $empleado->id,
            'dias_anteriores' => $diasAnteriores,
            'dias_cambio' => $diasCambio,
            'dias_nuevos' => $diasAnteriores + $diasCambio,
            'tipo_cambio' => $tipoCambio,
            'descripcion' => $descripcion,
            'user_id' => $userId,
        ]);
    }
}
