<?php

use App\Http\Controllers\Api\V2\SuperAdmin\AppVersaoController;
use Illuminate\Support\Facades\Route;

// App mobile: versão publicada e se a atualização bloqueia o app ou só avisa
Route::get('/app-versao', [AppVersaoController::class, 'show']);
Route::put('/app-versao', [AppVersaoController::class, 'update']);
