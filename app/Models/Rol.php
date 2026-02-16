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
        'name',
        'description',
        'guard_name',
        'system_id',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    protected $appends = ['nombre', 'descripcion'];

    public function getNombreAttribute()
    {
        return $this->name;
    }

    public function getDescripcionAttribute()
    {
        return $this->description;
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
