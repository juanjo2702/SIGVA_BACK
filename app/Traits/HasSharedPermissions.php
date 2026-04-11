<?php

namespace App\Traits;

use App\Models\Permission;
use Illuminate\Support\Collection;

trait HasSharedPermissions
{
    /**
     * Get all permissions (from roles only)
     */
    public function getAllPermissions(): Collection
    {
        try {
            return $this->roles->flatMap(function ($role) {
                return $role->permissions;
            })->unique('id_permision');
        } catch (\Throwable) {
            return collect();
        }
    }

    /**
     * Get permissions for a specific system
     */
    public function getPermissionsBySystem(string $systemName): Collection
    {
        return $this->getAllPermissions()->filter(function ($permission) use ($systemName) {
            return $permission->system && strtoupper($permission->system) === strtoupper($systemName);
        });
    }

    /**
     * Check if user has a specific permission
     */
    public function hasPermissionTo(string $permissionName): bool
    {
        return $this->getAllPermissions()->contains('nombres', $permissionName);
    }

    /**
     * Get all systems where the user has at least one permission
     */
    public function getAccessibleSystems(): Collection
    {
        $sysIds = $this->getAllPermissions()->pluck('sistema_id')->unique()->filter();
        return \App\Models\System::whereIn('id_sistema', $sysIds)->get();
    }
}
