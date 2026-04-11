<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Persona extends Model
{
    protected $connection = 'core';
    protected $table = 'personas';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'nombres',
        'primer_apellido',
        'segundo_apellido',
        'ci',
        'complemento',
        'tratamiento',
        'id_ci_expedido',
        'id_sexo',
        'fecha_nacimiento',
        'correo_personal',
        'celular_personal',
        'estado_civil',
        'direccion_domicilio',
        'foto',
    ];

    protected $casts = [
        'fecha_nacimiento' => 'date',
    ];

    protected $appends = [
        'apellido_paterno',
        'apellido_materno',
        'foto_url',
    ];

    public function getApellidoPaternoAttribute(): ?string
    {
        return $this->primer_apellido;
    }

    public function getApellidoMaternoAttribute(): ?string
    {
        return $this->segundo_apellido;
    }

    public function getFotoUrlAttribute(): ?string
    {
        if (!$this->foto) {
            return null;
        }

        if (str_starts_with($this->foto, 'http://') || str_starts_with($this->foto, 'https://')) {
            return $this->foto;
        }

        if (str_starts_with($this->foto, '/storage/')) {
            return url($this->foto);
        }

        return url('/storage/' . ltrim($this->foto, '/'));
    }
}
