<?php

use App\Http\Controllers\Api\V2\SuperAdmin\MonetizacaoController;
use Illuminate\Support\Facades\Route;

Route::get('/monetizacao/{provedor}', [MonetizacaoController::class, 'index'])->whereIn('provedor', ['adsense', 'admob']);
Route::put('/monetizacao/{provedor}/{id}', [MonetizacaoController::class, 'update'])->whereIn('provedor', ['adsense', 'admob'])->whereNumber('id');
