<?php

use App\Http\Controllers\Api\V2\Admin\RelatorioController;
use Illuminate\Support\Facades\Route;

Route::get('/relatorios/planos-ciclos', [RelatorioController::class, 'planosCiclos']);
