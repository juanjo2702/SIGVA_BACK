<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class System extends Model
{
    protected $connection = 'core';
    protected $table = 'systems';

    protected $fillable = ['name', 'display_name', 'description', 'url', 'icon', 'active'];

    protected $casts = [
        'active' => 'boolean',
    ];
}
