<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::morphMap([
            'user' => User::class,
            User::class => User::class,
            'Src\\Auth\\Infrastructure\\Persistence\\Models\\UserModel' => User::class,
        ]);

        Sanctum::usePersonalAccessTokenModel(
            \App\Models\Sanctum\PersonalAccessToken::class
        );
    }
}
