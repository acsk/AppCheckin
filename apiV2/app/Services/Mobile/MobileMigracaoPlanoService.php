<?php

namespace App\Services\Mobile;

use App\Services\MatriculaMigracaoAptidaoService;
use App\Services\MatriculaMigracaoService;

class MobileMigracaoPlanoService
{
    private ?MatriculaMigracaoService $core = null;

    private readonly MatriculaMigracaoAptidaoService $aptidao;

    public function __construct(?MatriculaMigracaoAptidaoService $aptidao = null)
    {
        $this->aptidao = $aptidao ?? app(MatriculaMigracaoAptidaoService::class);
    }

    private function core(): MatriculaMigracaoService
    {
        return $this->core ??= app(MatriculaMigracaoService::class);
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
        return $this->aptidao->temParcelaAtrasada($matriculaId, $tenantId);
    }

    /**
     * @param  array<string, mixed>  $matricula
     * @return array{apto: bool, gera_credito: bool, code: string, message: string, motivo: string|null}
     */
    public function avaliarAptidaoMigracao(array $matricula, int $tenantId): array
    {
        return $this->aptidao->avaliarAptidaoMigracao($matricula, $tenantId);
    }
}
