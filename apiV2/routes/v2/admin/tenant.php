<?php

use App\Http\Controllers\Api\V2\Admin\TenantController;
use Illuminate\Support\Facades\Route;

Route::get('/tenant/whatsapp-links', [TenantController::class, 'obterWhatsappLinks']);
Route::put('/tenant/whatsapp-links', [TenantController::class, 'salvarWhatsappLinks']);
