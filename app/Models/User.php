<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\HasApiTokens;
use Tymon\JWTAuth\Contracts\JWTSubject;

use App\Traits\HasSharedPermissions;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasSharedPermissions;
    
    public function getMorphClass()
    {
        return 'user';
    }

    public function getKeyName()
    {
        static $primaryKey;

        if ($primaryKey) {
            return $primaryKey;
        }

        $primaryKey = Schema::connection($this->getConnectionName())->hasColumn($this->getTable(), 'id_user')
            ? 'id_user'
            : 'id';

        return $primaryKey;
    }

    public function getIdUserAttribute()
    {
        return $this->attributes['id_user'] ?? $this->getAttributeFromArray($this->getKeyName());
    }

    /**
     * Use the 'core' connection for shared users table.
     */
    protected $connection = 'core';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'users';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id_persona',
        'id_sede_scope',
        'username',
        'ci',
        'nombres',
        'apellido_paterno',
        'apellido_materno',
        'email',
        'password',
        'rol_id',
        'activo',
        'debe_cambiar_password',
        'must_change_password',
        'sede_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be appended to arrays.
     *
     * @var array
     */
    protected $appends = ['nombre_completo', 'permisos', 'systems', 'ci', 'name', 'nombres', 'apellido_paterno', 'apellido_materno', 'email', 'rol', 'sede_id', 'password_segura', 'password_actual'];

    public function getCiAttribute()
    {
        return $this->attributes['ci'] ?? $this->persona?->ci ?? $this->username;
    }

    public function getNombresAttribute()
    {
        return $this->attributes['nombres'] ?? $this->persona?->nombres ?? $this->username;
    }

    public function getNameAttribute()
    {
        return $this->attributes['name'] ?? $this->nombres;
    }

    public function getApellidoPaternoAttribute()
    {
        return $this->attributes['apellido_paterno'] ?? $this->persona?->apellido_paterno ?? $this->persona?->primer_apellido ?? '';
    }

    public function getApellidoMaternoAttribute()
    {
        return $this->attributes['apellido_materno'] ?? $this->persona?->segundo_apellido ?? $this->persona?->apellido_materno ?? '';
    }

    public function getEmailAttribute()
    {
        return $this->attributes['email'] ?? $this->persona?->correo_personal ?? null;
    }

    public function getSedeIdAttribute()
    {
        return $this->attributes['id_sede_scope'] ?? null;
    }

    public function setSedeIdAttribute($value)
    {
        $this->attributes['id_sede_scope'] = $value;
    }

    public function getMustChangePasswordAttribute()
    {
        return (bool) ($this->attributes['debe_cambiar_password'] ?? false);
    }

    public function setMustChangePasswordAttribute($value)
    {
        $this->attributes['debe_cambiar_password'] = (bool) $value;
    }

    public function getPasswordSeguraAttribute(): bool
    {
        return !($this->attributes['debe_cambiar_password'] ?? false);
    }

    public function getPasswordActualAttribute(): string
    {
        return ($this->attributes['debe_cambiar_password'] ?? false)
            ? ($this->persona?->ci ?: $this->username)
            : '🔒 Personalizada';
    }

    /**
     * Get merged permissions (from role + individual)
     */
    public function getPermisosAttribute(): array
    {
        return $this->getAllPermissions()->pluck('nombres')->values()->toArray();
    }

    /**
     * Eager load relations by default
     */
    protected $with = [];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
            'debe_cambiar_password' => 'boolean',
            'must_change_password' => 'boolean',
        ];
    }

    /**
     * Relación con Roles (Many-to-Many como en SIGETH)
     */
    public function roles()
    {
        return $this->belongsToMany(Rol::class, 'user_has_roles', 'user_id', 'role_id');
    }

    /**
     * Eager loadable rol relation alias for backward compatibility
     */
    public function rol()
    {
        return $this->belongsToMany(Rol::class, 'user_has_roles', 'user_id', 'role_id');
    }

    /**
     * Get single role for backward compatibility (filtered by system 3)
     */
    public function getRolAttribute()
    {
        try {
            $sigvaRole = $this->roles->firstWhere('sistema_id', 3);
            if ($sigvaRole) {
                return [
                    'id' => $sigvaRole->id_rol ?? $sigvaRole->id,
                    'id_rol' => $sigvaRole->id_rol ?? $sigvaRole->id,
                    'nombre' => $sigvaRole->nombre ?? $sigvaRole->name ?? $sigvaRole->nombres,
                    'name' => $sigvaRole->nombre ?? $sigvaRole->name ?? $sigvaRole->nombres,
                ];
            }

            // Fallback ONLY if user is a Global Super Admin from SIGETH (sistema_id: 1)
            $globalAdmin = $this->roles->first(function ($r) {
                return (int)($r->sistema_id ?? 0) === 1 && in_array(strtoupper(trim($r->nombres ?? '')), ['ADMINISTRADOR', 'ADMIN', 'SUPER ADMIN', 'SUPERADMIN', 'DIRECTOR (ENCARGADO)']);
            });

            if ($globalAdmin) {
                return [
                    'id' => $globalAdmin->id_rol ?? $globalAdmin->id,
                    'id_rol' => $globalAdmin->id_rol ?? $globalAdmin->id,
                    'nombre' => 'Administrador',
                    'name' => 'Administrador',
                ];
            }

            return null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Relación con Sede
     */
    public function sede()
    {
        return $this->belongsTo(Sede::class, 'id_sede_scope', 'id_sede');
    }

    /**
     * Relación con Persona (Shared in core)
     */
    public function persona()
    {
        return $this->belongsTo(Persona::class, 'id_persona', 'id');
    }

    /**
     * Permisos individuales asignados directamente al usuario
     */
    public function individualPermissions()
    {
        return $this->belongsToMany(Permission::class, 'user_has_permissions', 'user_id', 'permission_id');
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'user_has_permissions', 'user_id', 'permission_id');
    }

    /**
     * Systems link (pivot)
     */
    public function userSystems()
    {
        return $this->belongsToMany(System::class, 'application_user', 'user_id', 'application_id')
                    ->withPivot('role', 'permissions')
                    ->withTimestamps();
    }

    /**
     * Dynamic systems attribute (filtered by permissions)
     */
    public function getSystemsAttribute()
    {
        try {
            return $this->getAllPermissions()
                ->pluck('system')  // Uses the accessor that returns app name
                ->unique()
                ->filter()
                ->values()
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Accessor para nombre completo
     */
    public function getNombreCompletoAttribute(): string
    {
        return trim("{$this->nombres} {$this->apellido_paterno} {$this->apellido_materno}");
    }

    /**
     * Verificar si tiene un rol específico
     */
    public function tieneRol(string $nombreRol): bool
    {
        $rolName = is_array($this->rol) ? ($this->rol['nombre'] ?? '') : ($this->rol?->nombre ?? '');
        return strtolower($rolName) === strtolower($nombreRol);
    }

    /**
     * Verificar si es admin
     */
    public function esAdmin(): bool
    {
        foreach ($this->roles as $role) {
            $rName = strtoupper(trim($role->nombres ?? $role->nombre ?? ''));
            $sysId = (int)($role->sistema_id ?? 0);
            if ($sysId === 3 && in_array($rName, ['ADMINISTRADOR', 'ADMIN'])) {
                return true;
            }
            if ($sysId === 1 && in_array($rName, ['ADMINISTRADOR', 'ADMIN', 'SUPER ADMIN', 'SUPERADMIN', 'DIRECTOR (ENCARGADO)'])) {
                return true;
            }
        }
        return false;
    }

    public function hasSystemAccess(int $systemId = 3): bool
    {
        if ($this->esAdmin()) {
            return true;
        }
        return $this->roles->contains(fn($r) => (int)($r->sistema_id ?? 0) === $systemId)
            || $this->permissions->contains(fn($p) => (int)($p->sistema_id ?? 0) === $systemId);
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [
            'id_rol' => $this->roles->first()?->id_rol,
            'ci' => $this->ci,
            // Add other helpful claims here to avoid DB lookups?
        ];
    }
}


