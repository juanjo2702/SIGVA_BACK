<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
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
    protected $primaryKey = 'id_user';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'ci',
        'nombres',
        'apellido_paterno',
        'apellido_materno',
        'email',
        'password',
        'rol_id',
        'activo',
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
    protected $appends = ['nombre_completo', 'permisos', 'systems'];

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
    protected $with = ['roles'];

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
     * Get single role for backward compatibility (returns first role)
     */
    public function getRolAttribute()
    {
        return $this->roles->first();
    }

    /**
     * Relación con Sede
     */
    public function sede()
    {
        return $this->belongsTo(Sede::class, 'sede_id');
    }

    /**
     * Relación con Persona (Shared in core)
     */
    public function persona()
    {
        return $this->belongsTo(Persona::class, 'id_persona', 'id');
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
        return $this->rol && strtolower($this->rol->nombre) === strtolower($nombreRol);
    }

    /**
     * Verificar si es admin
     */

    public function esAdmin(): bool
    {
        return $this->tieneRol('admin') || $this->tieneRol('administrador');
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


