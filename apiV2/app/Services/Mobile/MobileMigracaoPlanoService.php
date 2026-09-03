<?php

namespace App\Services\Mobile;

use Illuminate\Support\Facades\DB;

class MobileMigracaoPlanoService
{
    private ?\App\Services\MatriculaMigracaoService $core = null;

    private function core(): \App\Services\MatriculaMigracaoService
    {
        if ($this->core !== null) {
            return $this->core;
        }

        $slimServicePath = base_path('../api/app/Services/MatriculaMigracaoService.php');
        if (! is_file($slimServicePath)) {
            throw new \RuntimeException('MatriculaMigracaoService Slim não disponível em '.$slimServicePath);
        }

        require_once $slimServicePath;
        $this->core = new \App\Services\MatriculaMigracaoService(DB::connection()->getPdo());

        return $this->core;
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function simular(int $userId, int $tenantId, int $planoId, ?int $planoCicloId): array
    {
        return $this->core()->simular($userId, $tenantId, $planoId, $planoCicloId);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function migrar(int $userId, int $tenantId, array $data): array
    {
        return $this->core()->migrar($userId, $tenantId, $data);
    }

    public function buscarMatriculaAtivaModalidade(int $alunoId, int $tenantId, int $modalidadeId): ?array
    {
        return $this->core()->buscarMatriculaAtivaModalidade($alunoId, $tenantId, $modalidadeId);
    }

    public function temParcelaAtrasada(int $matriculaId, int $tenantId): bool
    {
        return $this->core()->temParcelaAtrasada($matriculaId, $tenantId);
    }

    /**
     * @param  array<string, mixed>  $matricula
     * @return array{apto: bool, gera_credito: bool, code: string, message: string, motivo: string|null}
     */
    public function avaliarAptidaoMigracao(array $matricula, int $tenantId): array
    {
        return $this->core()->avaliarAptidaoMigracao($matricula, $tenantId);
    }
}
