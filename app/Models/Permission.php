<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    protected $connection = 'core';
    protected $table = 'permissions';

    protected $fillable = ['name', 'guard_name', 'system_id', 'description'];

    public function roles()
    {
        return $this->belongsToMany(Rol::class, 'role_has_permissions', 'permission_id', 'role_id');
    }

    public function systems()
    {
        return $this->belongsTo(System::class, 'system_id');
    }
}
