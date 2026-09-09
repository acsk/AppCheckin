<?php

use App\Http\Controllers\Ops\ErrorLogViewController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'name' => 'AppCheckin API',
        'version' => config('appcheckin.api_version'),
        'docs' => url('/v2/ping'),
    ]);
});

Route::middleware('ops.token')->prefix('ops')->group(function () {
    Route::get('/errors', [ErrorLogViewController::class, 'index']);
    Route::get('/errors/{fingerprint}', [ErrorLogViewController::class, 'show'])
        ->where('fingerprint', '[a-f0-9]{64}');
});
