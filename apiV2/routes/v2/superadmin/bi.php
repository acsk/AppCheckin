<?php

use App\Http\Controllers\Api\V2\SuperAdmin\BiGlobalController;
use Illuminate\Support\Facades\Route;

// BI Global: prefix('v2/superadmin') + superadmin.auth (routes/api.php)
Route::get('/bi/global', [BiGlobalController::class, 'index']);
Route::get('/bi/global/exportar', [BiGlobalController::class, 'exportar']);
