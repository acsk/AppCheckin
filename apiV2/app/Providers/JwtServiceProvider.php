<?php

namespace App\Providers;

use App\Services\JwtService;
use Illuminate\Support\ServiceProvider;

class JwtServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(JwtService::class, function () {
            return new JwtService(
                secret: (string) config('appcheckin.jwt_secret'),
                expiration: (int) config('appcheckin.jwt_expiration', 86400),
            );
        });
    }
}
