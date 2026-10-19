<?php

namespace App\Services\SuperAdmin;

use App\Repositories\DashboardRepository;
use App\Support\TipoCobrancaPlanoSistema;

/**
 * Mensalidade do tenant conforme o plano do SaaS. Aluno ativo = aluno com matrícula ativa no tenant.
 */
class CobrancaTenantService
{
    public function __construct(
        private readonly DashboardRepository $dashboard,
    ) {}

    public function alunosAtivos(int $tenantId): int
    {
        return $this->dashboard->matriculasCards($tenantId)['alunos_com_matricula_ativa'];
    }

    /**
     * @param  array<string, mixed>  $plano  Linha de planos_sistema
     */
    public function valorMensal(array $plano, int $tenantId): float
    {
        $tipo = TipoCobrancaPlanoSistema::doPlano($plano);
        $alunos = $tipo === TipoCobrancaPlanoSistema::POR_ALUNO ? $this->alunosAtivos($tenantId) : 0;

        return TipoCobrancaPlanoSistema::calcularValor($plano, $alunos);
    }

    /**
     * @param  array<string, mixed>  $plano
     * @return array<string, mixed>
     */
    public function estimativa(array $plano, int $tenantId): array
    {
        $tipo = TipoCobrancaPlanoSistema::doPlano($plano);
        $alunos = $this->alunosAtivos($tenantId);
        $maxAlunos = isset($plano['max_alunos']) ? (int) $plano['max_alunos'] : null;

        return [
            'tipo_cobranca' => $tipo,
            'tipo_cobranca_rotulo' => TipoCobrancaPlanoSistema::ROTULOS[$tipo],
            'alunos_ativos' => $alunos,
            'max_alunos' => $maxAlunos,
            'acima_do_limite' => $maxAlunos !== null && $alunos > $maxAlunos,
            'valor_fixo' => $tipo === TipoCobrancaPlanoSistema::FIXO ? (float) $plano['valor'] : null,
            'valor_por_aluno' => $tipo === TipoCobrancaPlanoSistema::POR_ALUNO ? (float) $plano['valor_por_aluno'] : null,
            'total_estimado' => TipoCobrancaPlanoSistema::calcularValor($plano, $alunos),
        ];
    }
}
