<?php

use App\Http\Controllers\Api\V2\Admin\PacoteController;
use Illuminate\Support\Facades\Route;

// Contexto: prefix('v2/admin') + jwt.auth + admin.auth (routes/api.php)
// Rotas estáticas de contratos antes de /pacotes/{id}.

Route::get('/pacotes', [PacoteController::class, 'index']);
Route::post('/pacotes', [PacoteController::class, 'store']);
Route::post('/pacotes/contratos/{contratoId}/beneficiarios', [PacoteController::class, 'definirBeneficiarios']);
Route::post('/pacotes/contratos/{contratoId}/confirmar-pagamento', [PacoteController::class, 'confirmarPagamento']);
Route::delete('/pacotes/contratos/{contratoId}', [PacoteController::class, 'excluirContrato']);
Route::put('/pacotes/{id}', [PacoteController::class, 'update']);
Route::post('/pacotes/{pacoteId}/contratar', [PacoteController::class, 'contratar']);
