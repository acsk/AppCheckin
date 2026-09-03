<?php

use App\Http\Controllers\Api\V2\Admin\MatriculaController;
use Illuminate\Support\Facades\Route;

// Rotas estáticas / específicas de matrículas (antes de {id} em routes/api.php)
Route::post('/matriculas/pacote-contrato/{contratoId}/baixa', [MatriculaController::class, 'darBaixaPacote']);
