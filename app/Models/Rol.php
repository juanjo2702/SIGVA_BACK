<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rol extends Model
{
    use HasFactory;

    protected $connection = 'core';
    protected $table = 'roles';

    protected $fillable = [
        'nombre',
        'descripcion',
        'guard_name',
        'system_id',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    // Para compatibilidad
    protected $appends = ['name'];

    public function getNameAttribute()
    {
        return $this->nombre;
    }

    /**
     * Usuarios con este rol
     */
    public function usuarios()
    {
        return $this->hasMany(User::class, 'rol_id');
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_has_permissions', 'role_id', 'permission_id');
    }
}
