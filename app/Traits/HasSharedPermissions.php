<?php

namespace App\Traits;

use App\Models\Permission;
use Illuminate\Support\Collection;

trait HasSharedPermissions
{
    /**
     * Individual permissions assigned directly to the user
     */
    public function individualPermissions()
    {
        return $this->belongsToMany(Permission::class, 'model_has_permissions', 'model_id', 'permission_id')
                    ->where('model_type', 'App\Models\User'); // Consistency across systems
    }

    /**
     * Get all permissions (from role + individual)
     */
    public function getAllPermissions(): Collection
    {
        $rolePermissions = $this->rol ? $this->rol->permissions : collect();
        $individualPermissions = $this->individualPermissions;

        return $rolePermissions->merge($individualPermissions)->unique('id');
    }

    /**
     * Get permissions for a specific system
     */
    public function getPermissionsBySystem(string $systemName): Collection
    {
        return $this->getAllPermissions()->filter(function ($permission) use ($systemName) {
            return $permission->systems && $permission->systems->name === $systemName;
        });
    }

    /**
     * Check if user has a specific permission
     */
    public function hasPermissionTo(string $permissionName): bool
    {
        return $this->getAllPermissions()->contains('name', $permissionName);
    }

    /**
     * Get all systems where the user has at least one permission
     */
    public function getAccessibleSystems(): Collection
    {
        $systemIds = $this->getAllPermissions()->pluck('system_id')->unique()->filter();
        return \App\Models\System::whereIn('id', $systemIds)->get();
    }
}
