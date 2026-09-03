<?php

use App\Http\Controllers\Api\V2\SuperAdmin\MiscController;
use Illuminate\Support\Facades\Route;

Route::get('/env', [MiscController::class, 'env']);
