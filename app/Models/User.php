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
        return $this->getAllPermissions()->pluck('name')->toArray();
    }

    /**
     * Eager load relations by default
     */
    protected $with = ['rol'];

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
     * Relación con Rol
     */
    public function rol()
    {
        return $this->belongsTo(Rol::class, 'rol_id');
    }

    /**
     * Relación con Sede
     */
    public function sede()
    {
        return $this->belongsTo(Sede::class, 'sede_id');
    }

    /**
     * Systems link (pivot)
     */
    public function userSystems()
    {
        return $this->belongsToMany(System::class, 'user_systems', 'user_id', 'system_id')
                    ->withPivot('role_id', 'activo')
                    ->withTimestamps();
    }

    /**
     * Dynamic systems attribute (filtered by permissions)
     */
    public function getSystemsAttribute()
    {
        // En producción puede que getAllPermissions falle si no hay pivot,
        // pero asumimos que el trait funciona.
        try {
            $systemIds = $this->getAllPermissions()->pluck('system_id')->unique()->filter();
            return System::whereIn('id', $systemIds)->get();
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
            'rol_id' => $this->rol_id,
            'ci' => $this->ci,
            // Add other helpful claims here to avoid DB lookups?
        ];
    }
}
