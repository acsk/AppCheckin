<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Mail\Events\MessageSending;
use App\Listeners\EnforceAllowedOutboundMail;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Event::listen(MessageSending::class, EnforceAllowedOutboundMail::class);

        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        if (config('database.default') === 'mysql') {
            try {
                DB::statement("SET time_zone = '-03:00'");
            } catch (\Throwable) {
                // Ignorar se o driver ainda não estiver disponível (ex.: artisan package:discover)
            }
        }
    }
}
