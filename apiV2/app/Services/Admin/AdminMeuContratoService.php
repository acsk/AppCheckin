<?php

namespace App\Services\Admin;

use App\Repositories\PagamentoContratoRepository;
use App\Repositories\PlanoSistemaRepository;
use App\Repositories\TenantPlanoRepository;
use App\Services\SuperAdmin\CobrancaContratoService;
use App\Services\SuperAdmin\CobrancaTenantService;
use App\Services\SuperAdmin\SuperAdminContratoService;
use App\Support\Documento;
use App\Support\TipoCobrancaPlanoSistema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Contrato da própria academia, visto pelo admin dela: ver o plano e as cobranças, pagar pelo
 * Asaas da conta do sistema e trocar de plano entre os planos à venda.
 */
class AdminMeuContratoService
{
    private const ABERTOS = [1, 3]; // Aguardando, Atrasado

    private const ATRASADO = 3;

    public function __construct(
        private readonly TenantPlanoRepository $contratos,
        private readonly PlanoSistemaRepository $planos,
        private readonly PagamentoContratoRepository $pagamentos,
        private readonly CobrancaContratoService $cobrancas,
        private readonly CobrancaTenantService $cobranca,
        private readonly SuperAdminContratoService $contratoService,
        ?\Closure $buscarAcademia = null,
    ) {
        $this->buscarAcademia = $buscarAcademia ?? static fn (int $id) => (array) DB::table('tenants')->where('id', $id)->first();
    }

    /** @var \Closure(int): array<string, mixed> linha de tenants */
    private \Closure $buscarAcademia;

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function mostrar(int $tenantId): array
    {
        $contrato = $this->contratos->buscarContratoVigente($tenantId);
        $plano = $contrato ? $this->planos->buscarPorId((int) $contrato['plano_sistema_id']) : null;

        $academia = DB::table('tenants')->where('id', $tenantId)->first(['id', 'nome']);

        return [
            'status' => 200,
            'body' => [
                'academia' => $academia ? ['id' => (int) $academia->id, 'nome' => $academia->nome] : null,
                'contrato' => $contrato ? $this->contratoPublico($contrato, $plano) : null,
                'pagamentos' => $this->pagamentosDaAcademia($tenantId, $contrato ? (int) $contrato['id'] : null),
                'planos' => array_map(fn (array $p) => $this->planoPublico($p), $this->planos->listarPublicos()),
                'formas' => $this->cobrancas->formasHabilitadas(),
                'alunos_ativos' => $this->cobranca->alunosAtivos($tenantId),
                'documento_valido' => $this->documentoValido($tenantId),
            ],
        ];
    }

    /**
     * Gera (ou reaproveita) no Asaas a cobrança de um pagamento em aberto da academia.
     *
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function pagar(int $tenantId, int $pagamentoId, array $data): array
    {
        $pagamento = $this->pagamentoDaAcademia($tenantId, $pagamentoId);
        if (! $pagamento) {
            return ['status' => 404, 'body' => ['type' => 'error', 'message' => 'Cobrança não encontrada']];
        }
        if (! in_array((int) $pagamento['status_pagamento_id'], self::ABERTOS, true) || (float) $pagamento['valor'] <= 0) {
            return ['status' => 422, 'body' => ['type' => 'error', 'message' => 'Esta cobrança não está em aberto']];
        }
        // De contrato que não é o vigente, só a dívida atrasada pode ser paga (período já usado);
        // "Aguardando" de contrato substituído era do plano antigo e não vale mais.
        $vigente = $this->contratos->buscarContratoVigente($tenantId);
        if ((! $vigente || (int) $vigente['id'] !== (int) $pagamento['tenant_plano_id']) && (int) $pagamento['status_pagamento_id'] !== self::ATRASADO) {
            return ['status' => 422, 'body' => ['type' => 'error', 'message' => 'Esta cobrança é de um plano que a academia não usa mais.']];
        }

        // Já tem cobrança no Asaas: devolve o mesmo link para não cobrar duas vezes
        if (! empty($pagamento['gateway_payment_id'])) {
            return ['status' => 200, 'body' => [
                'type' => 'success',
                'reaproveitada' => true,
                'cobranca' => [
                    'billing_type' => $pagamento['gateway_billing_type'] ?? null,
                    'url' => $pagamento['gateway_url'] ?? null,
                ],
            ]];
        }

        $forma = strtoupper((string) ($data['forma_pagamento'] ?? ''));
        $formas = $this->cobrancas->formasHabilitadas();
        if ($formas === []) {
            return ['status' => 503, 'body' => ['type' => 'error', 'message' => 'O pagamento online está indisponível no momento. Fale com o suporte do Check-in.']];
        }
        if (! in_array($forma, $formas, true)) {
            return ['status' => 422, 'body' => ['type' => 'error', 'message' => 'Escolha como pagar.']];
        }

        $doc = $this->exigirDocumento($tenantId, $data);
        if ($doc) {
            return $doc;
        }

        try {
            $cobranca = $this->cobrancas->gerar($pagamentoId, $forma);
        } catch (\Throwable $e) {
            Log::error('Meu contrato: cobrança não gerada', ['tenant_id' => $tenantId, 'pagamento_id' => $pagamentoId, 'error' => $e->getMessage()]);

            return ['status' => 502, 'body' => ['type' => 'error', 'message' => $this->mensagemGateway($e)]];
        }

        return ['status' => 200, 'body' => ['type' => 'success', 'cobranca' => $this->cobrancaPublica($cobranca)]];
    }

    /**
     * Troca o plano da própria academia. Nos planos pagos o novo contrato fica Pendente e a primeira
     * cobrança já sai no Asaas; o contrato é ativado quando ela for paga (webhook).
     *
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function trocarPlano(int $tenantId, array $data, ?string $usuarioNome = null): array
    {
        $planoId = (int) ($data['plano_sistema_id'] ?? 0);
        $plano = null;
        foreach ($this->planos->listarPublicos() as $p) {
            if ((int) $p['id'] === $planoId) {
                $plano = $p;
            }
        }
        if (! $plano) {
            return ['status' => 422, 'body' => ['type' => 'error', 'message' => 'Escolha um dos planos disponíveis.']];
        }

        $atual = $this->contratos->buscarContratoVigente($tenantId);
        if ($atual && (int) $atual['plano_sistema_id'] === $planoId) {
            return ['status' => 422, 'body' => ['type' => 'error', 'message' => 'A academia já está neste plano.']];
        }

        $alunos = $this->cobranca->alunosAtivos($tenantId);
        if (! empty($plano['max_alunos']) && $alunos > (int) $plano['max_alunos']) {
            return ['status' => 422, 'body' => ['type' => 'error', 'message' => sprintf(
                'O %s aceita até %d alunos e a academia tem %d ativos. Escolha um plano maior.',
                $plano['nome'], (int) $plano['max_alunos'], $alunos,
            )]];
        }

        $pago = ! TipoCobrancaPlanoSistema::isGratuito($plano);
        $forma = strtoupper((string) ($data['forma_pagamento'] ?? ''));
        if ($pago) {
            $formas = $this->cobrancas->formasHabilitadas();
            if ($formas === []) {
                return ['status' => 503, 'body' => ['type' => 'error', 'message' => 'O pagamento online está indisponível no momento. Fale com o suporte do Check-in.']];
            }
            if (! in_array($forma, $formas, true)) {
                return ['status' => 422, 'body' => ['type' => 'error', 'message' => 'Escolha como pagar.']];
            }
            $doc = $this->exigirDocumento($tenantId, $data);
            if ($doc) {
                return $doc;
            }
        }

        $motivo = trim((string) ($data['motivo'] ?? ''));
        $res = $this->contratoService->trocarPlano($tenantId, [
            'plano_sistema_id' => $planoId,
            'forma_pagamento_id' => $pago ? CobrancaContratoService::formaPagamentoId($forma) : null,
            'data_fim' => ! empty($plano['prazo_dias']) ? date('Y-m-d', strtotime('+'.(int) $plano['prazo_dias'].' days')) : null,
            'observacoes' => 'Troca de plano pelo painel da academia'.($usuarioNome ? ' ('.$usuarioNome.')' : '').($motivo !== '' ? ': '.$motivo : ''),
        ]);
        if ($res['status'] !== 200) {
            return $res;
        }

        $novo = $res['body']['contrato'] ?? [];
        $cobranca = null;
        $aviso = null;
        if ($pago && ! empty($novo['id'])) {
            try {
                $cobranca = $this->cobrancas->gerarProxima((int) $novo['id'], $forma);
            } catch (\Throwable $e) {
                Log::error('Meu contrato: cobrança da troca não gerada', ['tenant_id' => $tenantId, 'contrato_id' => $novo['id'], 'error' => $e->getMessage()]);
                $aviso = 'O plano foi trocado, mas não conseguimos gerar a cobrança agora. Tente pagar de novo em instantes.';
            }
        }

        return ['status' => 200, 'body' => [
            'type' => 'success',
            'message' => $pago ? 'Plano trocado. Pague a primeira cobrança para ativar.' : 'Plano trocado',
            'contrato' => ['id' => (int) ($novo['id'] ?? 0), 'status' => $pago ? 'pendente' : 'ativo'],
            'valor' => $pago ? $this->cobranca->valorMensal($plano, $tenantId) : 0,
            'cobranca' => $cobranca ? $this->cobrancaPublica($cobranca) : null,
            'aviso' => $aviso,
        ]];
    }

    /**
     * @param  array<string, mixed>  $c
     * @param  array<string, mixed>|null  $plano
     * @return array<string, mixed>
     */
    private function contratoPublico(array $c, ?array $plano): array
    {
        return [
            'id' => (int) $c['id'],
            'status_id' => (int) $c['status_id'],
            'status_nome' => $c['status_nome'] ?? null,
            'data_inicio' => $c['data_inicio'] ?? null,
            'data_fim' => $c['data_fim'] ?? null,
            'plano' => $plano ? $this->planoPublico($plano) : [
                'id' => (int) $c['plano_sistema_id'], 'nome' => $c['plano_nome'] ?? null, 'tipo_cobranca' => $c['tipo_cobranca'] ?? null,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $p
     * @return array<string, mixed>
     */
    private function planoPublico(array $p): array
    {
        return [
            'id' => (int) $p['id'],
            'codigo' => $p['codigo'] ?? null,
            'nome' => $p['nome'],
            'descricao' => $p['descricao'] ?? null,
            'tipo_cobranca' => TipoCobrancaPlanoSistema::doPlano($p),
            'valor' => (float) ($p['valor'] ?? 0),
            'valor_por_aluno' => $p['valor_por_aluno'] !== null ? (float) $p['valor_por_aluno'] : null,
            'duracao_dias' => (int) ($p['duracao_dias'] ?? 30),
            'prazo_dias' => ! empty($p['prazo_dias']) ? (int) $p['prazo_dias'] : null,
            'max_alunos' => ! empty($p['max_alunos']) ? (int) $p['max_alunos'] : null,
            'max_admins' => ! empty($p['max_admins']) ? (int) $p['max_admins'] : null,
            'features' => $p['features'] ?? null,
        ];
    }

    /**
     * Cobranças do contrato vigente e as que ficaram em aberto de contratos anteriores.
     * Sem observações internas do super admin.
     *
     * @return list<array<string, mixed>>
     */
    private function pagamentosDaAcademia(int $tenantId, ?int $contratoId): array
    {
        return DB::table('pagamentos_contrato as p')
            ->join('tenant_planos_sistema as c', 'c.id', '=', 'p.tenant_plano_id')
            ->join('planos_sistema as ps', 'ps.id', '=', 'c.plano_sistema_id')
            ->join('status_pagamento as sp', 'sp.id', '=', 'p.status_pagamento_id')
            ->leftJoin('formas_pagamento as fp', 'fp.id', '=', 'p.forma_pagamento_id')
            ->where('c.tenant_id', $tenantId)
            ->where(function ($q) use ($contratoId) {
                // Todas as do contrato vigente; de contratos anteriores, só a dívida atrasada
                $q->where(function ($d) {
                    $d->where('p.status_pagamento_id', self::ATRASADO)->where('p.valor', '>', 0);
                });
                if ($contratoId) {
                    $q->orWhere('p.tenant_plano_id', $contratoId);
                }
            })
            ->orderByDesc('p.data_vencimento')
            ->orderByDesc('p.id')
            ->get([
                'p.id', 'p.tenant_plano_id as contrato_id', 'ps.nome as plano_nome', 'p.valor', 'p.data_vencimento', 'p.data_pagamento',
                'p.status_pagamento_id', 'sp.nome as status_nome', 'fp.nome as forma_pagamento_nome',
                'p.gateway_billing_type', 'p.gateway_url',
            ])
            ->map(fn ($r) => (array) $r + ['tem_cobranca' => ! empty($r->gateway_url)])
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function pagamentoDaAcademia(int $tenantId, int $pagamentoId): ?array
    {
        $row = DB::table('pagamentos_contrato as p')
            ->join('tenant_planos_sistema as c', 'c.id', '=', 'p.tenant_plano_id')
            ->where('p.id', $pagamentoId)
            ->where('c.tenant_id', $tenantId)
            ->first(['p.*']);

        return $row ? (array) $row : null;
    }

    /**
     * @param  array<string, mixed>  $c
     * @return array<string, mixed>
     */
    private function cobrancaPublica(array $c): array
    {
        return array_intersect_key($c, array_flip(['billing_type', 'vencimento', 'url', 'boleto_url', 'pix_payload', 'pix_qr_code']));
    }

    private function documentoValido(int $tenantId): bool
    {
        return CobrancaContratoService::documentoCobranca(($this->buscarAcademia)($tenantId)) !== null;
    }

    /**
     * O Asaas só cria a cobrança com CNPJ ou CPF válido. Se veio `documento`, grava no cadastro da academia
     * (CNPJ com 14 dígitos, CPF do responsável com 11); se ainda faltar, devolve o erro que a tela usa para pedir.
     *
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}|null
     */
    private function exigirDocumento(int $tenantId, array $data): ?array
    {
        $informado = Documento::digitos((string) ($data['documento'] ?? ''));
        if ($informado !== '') {
            $erro = $this->salvarDocumento($tenantId, $informado);
            if ($erro) {
                return ['status' => 422, 'body' => ['type' => 'error', 'code' => 'DOCUMENTO_INVALIDO', 'message' => $erro]];
            }
        }

        if ($this->documentoValido($tenantId)) {
            return null;
        }

        return ['status' => 422, 'body' => [
            'type' => 'error',
            'code' => 'DOCUMENTO_INVALIDO',
            'message' => 'Para pagar online, informe o CNPJ da academia ou o CPF do responsável. O do cadastro não é válido.',
        ]];
    }

    private function salvarDocumento(int $tenantId, string $doc): ?string
    {
        if (strlen($doc) === 14) {
            if (! Documento::cnpjValido($doc)) {
                return 'Confira o CNPJ: os dígitos não conferem.';
            }
            if (DB::table('tenants')->where('cnpj', $doc)->where('id', '!=', $tenantId)->exists()) {
                return 'Este CNPJ já está em outra academia do Check-in. Fale com o suporte.';
            }
            DB::table('tenants')->where('id', $tenantId)->update(['cnpj' => $doc, 'updated_at' => DB::raw('NOW()')]);

            return null;
        }
        if (strlen($doc) === 11) {
            if (! Documento::cpfValido($doc)) {
                return 'Confira o CPF: os dígitos não conferem.';
            }
            DB::table('tenants')->where('id', $tenantId)->update(['responsavel_cpf' => $doc, 'updated_at' => DB::raw('NOW()')]);

            return null;
        }

        return 'Informe um CNPJ (14 números) ou um CPF (11 números).';
    }

    private function mensagemGateway(\Throwable $e): string
    {
        $msg = $e->getMessage();
        if (stripos($msg, 'CPF ou CNPJ') !== false || stripos($msg, 'CPF/CNPJ') !== false || stripos($msg, 'CNPJ nem CPF') !== false) {
            return 'Para pagar online, a academia precisa de CNPJ (ou o CPF do responsável) no cadastro. Fale com o suporte do Check-in.';
        }

        return 'Não foi possível gerar a cobrança agora. Tente de novo em instantes.';
    }
}
