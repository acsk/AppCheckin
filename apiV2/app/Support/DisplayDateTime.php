<?php

namespace App\Support;

use Carbon\Carbon;

final class DisplayDateTime
{
    public static function format(?string $datetime, string $pattern = 'd/m/Y H:i:s'): string
    {
        if ($datetime === null || $datetime === '') {
            return '-';
        }

        try {
            $sourceTz = (string) config('app.timezone', 'UTC');

            return Carbon::parse($datetime, $sourceTz)
                ->timezone(AcademyDateTime::TZ)
                ->format($pattern);
        } catch (\Throwable) {
            return (string) $datetime;
        }
    }

    public static function label(?string $datetime, string $pattern = 'd/m/Y H:i:s'): string
    {
        if ($datetime === null || $datetime === '') {
            return '-';
        }

        return self::format($datetime, $pattern).' (BRT)';
    }
}
