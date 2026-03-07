<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class System extends Model
{
    protected $connection = 'core';
    protected $table = 'applications';

    protected $fillable = ['key', 'nombre', 'descripcion', 'url', 'icono', 'color', 'activo'];

    protected $appends = ['name'];

    public function getNameAttribute()
    {
        return $this->attributes['key'] ?? $this->nombre;
    }

    public function getDisplayNameAttribute()
    {
        return $this->nombre;
    }

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'application_user', 'application_id', 'user_id')
                    ->withPivot('role', 'permissions')
                    ->withTimestamps();
    }
}
