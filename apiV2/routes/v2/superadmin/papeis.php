<?php

use App\Http\Controllers\Api\V2\SuperAdmin\PapelController;
use Illuminate\Support\Facades\Route;

Route::get('/papeis', [PapelController::class, 'index']);
