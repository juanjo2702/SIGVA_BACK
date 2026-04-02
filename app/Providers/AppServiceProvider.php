<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

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
        \Illuminate\Database\Eloquent\Relations\Relation::morphMap([
            'user' => \App\Models\User::class,
        ]);

        \Laravel\Sanctum\Sanctum::usePersonalAccessTokenModel(
            \App\Models\Sanctum\PersonalAccessToken::class
        );
    }
}
