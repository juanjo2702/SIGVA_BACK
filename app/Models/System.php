<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class System extends Model
{
    protected $connection = 'core';
    protected $table = 'applications';

    protected $fillable = ['key', 'nombre', 'descripcion', 'url', 'icono', 'color', 'activo'];

    public function getNameAttribute()
    {
        return $this->key;
    }

    public function getDisplayNameAttribute()
    {
        return $this->nombre;
    }

    protected $casts = [
        'active' => 'boolean',
    ];
}
