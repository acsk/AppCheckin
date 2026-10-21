<?php

namespace App\Services\Admin\Prospeccao;

use App\Repositories\ProspeccaoRepository;
use App\Support\AcademyDateTime;
use App\Support\ProspeccaoStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Agendamentos de retorno e a agenda de Contatos Pendentes (atrasados, hoje, próximos).
 */
class ProspeccaoAgendamentoService
{
    public const DIAS_PROXIMOS = 30;

    private const LIMITE_POR_GRUPO = 100;

    public function __construct(
        private readonly ProspeccaoRepository $repo,
    ) {}

    /**
     * @param  array<string, mixed>  $q
     * @return array{status: int, body: array<string, mixed>}
     */
    public function agenda(int $tenantId, array $q, array $ctx): array
    {
        $hoje = AcademyDateTime::todayStart();
        $inicioHoje = $hoje->format('Y-m-d H:i:s');
        $inicioAmanha = (clone $hoje)->modify('+1 day')->format('Y-m-d H:i:s');
        $fimProximos = (clone $hoje)->modify('+'.(self::DIAS_PROXIMOS + 1).' days')->format('Y-m-d H:i:s');
        $resp = $q['responsavel'] ?? null;
        $responsavelId = $resp === 'meus' ? $ctx['usuario_id'] : (ctype_digit((string) $resp) ? (int) $resp : null);

        return ['status' => 200, 'body' => [
            'atrasados' => $this->repo->agenda($tenantId, null, $inicioHoje, $responsavelId, null, self::LIMITE_POR_GRUPO),
            'hoje' => $this->repo->agenda($tenantId, $inicioHoje, $inicioAmanha, $responsavelId, null, self::LIMITE_POR_GRUPO),
            'proximos' => $this->repo->agenda($tenantId, $inicioAmanha, $fimProximos, $responsavelId, null, self::LIMITE_POR_GRUPO),
            'dias_proximos' => self::DIAS_PROXIMOS,
        ]];
    }

    /**
     * @param  array<string, mixed>  $data  {oportunidade_id, agendado_para, tipo_contato, observacao?, responsavel_id?, marcar_retorno_agendado?}
     * @return array{status: int, body: array<string, mixed>}
     */
    public function criar(int $tenantId, array $data, array $ctx): array
    {
        $o = $this->repo->buscarSimples($tenantId, (int) ($data['oportunidade_id'] ?? 0));
        if (! $o) {
            return $this->erro('Oportunidade não encontrada', 404);
        }
        if (! ProspeccaoStatus::aberto((string) $o['status'])) {
            return $this->erro('Só é possível agendar retorno em oportunidade aberta', 409);
        }
        if ($this->repo->restricaoAtiva($tenantId, (int) $o['aluno_id'])) {
            return $this->erro('Aluno pediu para não ser contatado', 409);
        }
        $campos = $this->validar($tenantId, $data, $ctx);
        if (isset($campos['error'])) {
            return $this->erro($campos['error'], 422);
        }

        $id = DB::transaction(function () use ($tenantId, $o, $campos, $data, $ctx) {
            $id = $this->repo->inserirAgendamento($campos + [
                'tenant_id' => $tenantId,
                'oportunidade_id' => (int) $o['id'],
                'situacao' => 'pendente',
                'criado_por' => $ctx['usuario_id'],
            ]);
            $this->repo->sincronizarProximoContato($tenantId, (int) $o['id']);
            $marcar = filter_var($data['marcar_retorno_agendado'] ?? true, FILTER_VALIDATE_BOOLEAN);
            if ($marcar && ProspeccaoStatus::podeTransicionar((string) $o['status'], ProspeccaoStatus::RETORNO_AGENDADO)) {
                $this->repo->atualizar($tenantId, (int) $o['id'], ['status' => ProspeccaoStatus::RETORNO_AGENDADO]);
                $this->repo->registrarHistorico($tenantId, (int) $o['id'], (string) $o['status'], ProspeccaoStatus::RETORNO_AGENDADO, $ctx['usuario_id'], 'Retorno agendado para '.$campos['agendado_para']);
            }

            return $id;
        });
        Log::info('prospeccao.agendamento', ['tenant_id' => $tenantId, 'agendamento_id' => $id, 'usuario_id' => $ctx['usuario_id']]);

        return ['status' => 201, 'body' => ['agendamento' => $this->repo->buscarAgendamento($tenantId, $id)]];
    }

    /**
     * @param  array<string, mixed>  $data  {acao: concluir|cancelar|reagendar, agendado_para?, observacao?}
     * @return array{status: int, body: array<string, mixed>}
     */
    public function atualizar(int $tenantId, int $id, array $data, array $ctx): array
    {
        $ag = $this->repo->buscarAgendamento($tenantId, $id);
        if (! $ag) {
            return $this->erro('Agendamento não encontrado', 404);
        }
        if ($ag['situacao'] !== 'pendente') {
            return $this->erro('Este agendamento já foi '.mb_strtolower(ProspeccaoStatus::SITUACOES_AGENDAMENTO[$ag['situacao']] ?? $ag['situacao']), 409);
        }
        $acao = (string) ($data['acao'] ?? '');
        $agora = AcademyDateTime::nowFormatted();
        $oportunidadeId = (int) $ag['oportunidade_id'];

        if ($acao === 'concluir' || $acao === 'cancelar') {
            DB::transaction(function () use ($tenantId, $id, $acao, $agora, $ctx, $oportunidadeId) {
                $this->repo->atualizarAgendamento($tenantId, $id, ['situacao' => $acao === 'concluir' ? 'concluido' : 'cancelado', 'finalizado_por' => $ctx['usuario_id'], 'finalizado_em' => $agora]);
                $this->repo->sincronizarProximoContato($tenantId, $oportunidadeId);
            });

            return ['status' => 200, 'body' => ['agendamento' => $this->repo->buscarAgendamento($tenantId, $id)]];
        }

        if ($acao !== 'reagendar') {
            return $this->erro('Ação inválida (use concluir, cancelar ou reagendar)', 422);
        }
        $campos = $this->validar($tenantId, $data + ['tipo_contato' => $ag['tipo_contato'], 'responsavel_id' => $ag['responsavel_id']], $ctx);
        if (isset($campos['error'])) {
            return $this->erro($campos['error'], 422);
        }
        $novoId = DB::transaction(function () use ($tenantId, $id, $ag, $campos, $agora, $ctx, $oportunidadeId) {
            $this->repo->atualizarAgendamento($tenantId, $id, ['situacao' => 'reagendado', 'finalizado_por' => $ctx['usuario_id'], 'finalizado_em' => $agora]);
            $novoId = $this->repo->inserirAgendamento($campos + [
                'tenant_id' => $tenantId,
                'oportunidade_id' => $oportunidadeId,
                'situacao' => 'pendente',
                'reagendado_de_id' => $id,
                'criado_por' => $ctx['usuario_id'],
                'observacao' => $campos['observacao'] ?? $ag['observacao'],
            ]);
            $this->repo->sincronizarProximoContato($tenantId, $oportunidadeId);

            return $novoId;
        });

        return ['status' => 200, 'body' => ['agendamento' => $this->repo->buscarAgendamento($tenantId, $novoId)]];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed> campos validados, ou ['error' => string]
     */
    private function validar(int $tenantId, array $data, array $ctx): array
    {
        try {
            $quando = AcademyDateTime::parse((string) ($data['agendado_para'] ?? ''));
        } catch (\Exception) {
            return ['error' => 'Informe data e horário do retorno'];
        }
        if (empty($data['agendado_para']) || $quando < (AcademyDateTime::now())->modify('-5 minutes')) {
            return ['error' => 'O retorno precisa ser agendado para agora ou para o futuro'];
        }
        $tipo = (string) ($data['tipo_contato'] ?? '');
        if (! isset(ProspeccaoStatus::TIPOS_CONTATO[$tipo])) {
            return ['error' => 'Tipo de contato inválido'];
        }
        $responsavel = isset($data['responsavel_id']) && $data['responsavel_id'] !== '' ? (int) $data['responsavel_id'] : $ctx['usuario_id'];
        if (! $this->repo->usuarioDaEquipe($tenantId, $responsavel) && $responsavel !== $ctx['usuario_id']) {
            return ['error' => 'Responsável precisa ser administrador ou comercial desta academia'];
        }
        $obs = isset($data['observacao']) ? trim((string) $data['observacao']) : '';

        return array_filter([
            'agendado_para' => $quando->format('Y-m-d H:i:s'),
            'tipo_contato' => $tipo,
            'responsavel_id' => $responsavel,
            'observacao' => $obs !== '' ? mb_substr($obs, 0, 500) : null,
        ], fn ($v) => $v !== null);
    }

    /** @return array{status: int, body: array{error: string}} */
    private function erro(string $mensagem, int $status): array
    {
        return ['status' => $status, 'body' => ['error' => $mensagem]];
    }
}
