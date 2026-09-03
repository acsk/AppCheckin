<?php

use App\Http\Controllers\Api\V2\SuperAdmin\AssinaturaController;
use Illuminate\Support\Facades\Route;

Route::get('/assinaturas', [AssinaturaController::class, 'index']);
