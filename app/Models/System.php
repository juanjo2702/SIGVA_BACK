<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class System extends Model
{
    protected $connection = 'core';
    protected $table = 'sistemas';
    protected $primaryKey = 'id_sistema';

    protected $fillable = [
        'sistema',
        'url_sistema',
    ];

    protected $appends = ['name'];

    public function getNameAttribute()
    {
        return $this->sistema;
    }

    public function getDisplayNameAttribute()
    {
        return $this->sistema;
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
