<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (config('database.default') === 'mysql') {
            try {
                DB::statement("SET time_zone = '-03:00'");
            } catch (\Throwable) {
                // Ignorar se o driver ainda não estiver disponível (ex.: artisan package:discover)
            }
        }
    }
}
