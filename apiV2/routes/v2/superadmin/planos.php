<?php

use App\Http\Controllers\Api\V2\SuperAdmin\PlanoAlunoController;
use Illuminate\Support\Facades\Route;

Route::get('/planos', [PlanoAlunoController::class, 'index']);
