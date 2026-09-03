<?php

use App\Http\Controllers\Api\V2\Admin\UsuarioController;
use Illuminate\Support\Facades\Route;

/*
| Slim: grupo /admin com AdminMiddleware + AuthMiddleware.
| Contexto herdado: prefix('v2/admin') + jwt.auth + admin.auth.
*/
Route::get('/admins', [UsuarioController::class, 'admins']);
