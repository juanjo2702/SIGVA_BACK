<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    protected $connection = 'core';
    protected $table = 'permissions';

    protected $fillable = ['name', 'guard_name', 'application_id', 'description'];

    /**
     * Accessor: 'system' devuelve el nombre de la aplicación asociada.
     * Permite que User->getSystemsAttribute() funcione correctamente.
     */
    public function getSystemAttribute()
    {
        if ($this->application_id) {
            /** @var object|null $app */
            $app = \Illuminate\Support\Facades\DB::connection('core')
                ->table('applications')
                ->where('id', $this->application_id)
                ->first();
            return ($app && isset($app->nombre)) ? $app->nombre : null;
        }
        return null;
    }

    /**
     * Accessor: 'system_id' mapeado a application_id para compatibilidad
     */
    public function getSystemIdAttribute()
    {
        return $this->application_id;
    }

    public function roles()
    {
        return $this->belongsToMany(Rol::class, 'role_has_permissions', 'permission_id', 'role_id');
    }

    public function application()
    {
        return $this->belongsTo(System::class, 'application_id');
    }
}
