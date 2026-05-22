<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'name' => 'AppCheckin API',
        'version' => config('appcheckin.api_version'),
        'docs' => url('/v2/ping'),
    ]);
});
