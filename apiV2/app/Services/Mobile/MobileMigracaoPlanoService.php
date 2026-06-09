<?php

namespace App\Services\Mobile;

use Illuminate\Support\Facades\DB;

class MobileMigracaoPlanoService
{
    private \App\Services\MatriculaMigracaoService $core;

    public function __construct()
    {
        $slimServicePath = base_path('../api/app/Services/MatriculaMigracaoService.php');
        require_once $slimServicePath;
        $this->core = new \App\Services\MatriculaMigracaoService(DB::connection()->getPdo());
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function simular(int $userId, int $tenantId, int $planoId, ?int $planoCicloId): array
    {
        return $this->core->simular($userId, $tenantId, $planoId, $planoCicloId);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function migrar(int $userId, int $tenantId, array $data): array
    {
        return $this->core->migrar($userId, $tenantId, $data);
    }

    public function buscarMatriculaAtivaModalidade(int $alunoId, int $tenantId, int $modalidadeId): ?array
    {
        return $this->core->buscarMatriculaAtivaModalidade($alunoId, $tenantId, $modalidadeId);
    }
}
