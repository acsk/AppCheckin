<?php

namespace App\Services\Mobile;

use App\Repositories\AdminPacoteRepository;
use App\Repositories\UsuarioRepository;
use App\Services\Admin\AdminPacoteService;
use App\Services\Admin\PacoteDescontoService;
use App\Services\Asaas\AsaasService;
use App\Services\CobrancaAnteriorService;
use App\Services\Pacote\PacoteConciliacaoService;
use App\Services\Payment\PaymentGatewayFactory;
use App\Support\AssinaturaGateway;
use App\Support\MetodoPagamentoResolver;
use App\Support\ReferenciaExterna;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Pacote pelo app: o aluno vê os pacotes à venda (para todos ou liberados para ele), contrata como
 * pagante, paga por PIX ou cartão no gateway da academia e renova perto do vencimento. Os demais
 * participantes são associados pelo admin depois do pagamento.
 *
 * A cobrança soma as parcelas em aberto do período do contrato e é feita na matrícula do pagante
 * (MAT-…): webhook e conferência agendada dão a baixa e a PacoteConciliacaoService a estende ao grupo.
 */
class MobilePacoteVendaService
{
    /** A renovação libera a partir de N dias antes do fim da vigência. */
    public const JANELA_RENOVACAO_DIAS = 7;

    public function __construct(
        private readonly AdminPacoteRepository $pacotes,
        private readonly AdminPacoteService $adminPacotes,
        private readonly PacoteDescontoService $descontos,
        private readonly PacoteConciliacaoService $conciliacao,
        private readonly PaymentGatewayFactory $gateways,
        private readonly UsuarioRepository $usuarios,
        private readonly CobrancaAnteriorService $cobrancasAnteriores,
    ) {}

    /** @return array{status: int, body: array<string, mixed>} */
    public function disponiveis(?int $tenantId, int $userId): array
    {
        if (! $tenantId) {
            return $this->erro(400, 'SEM_TENANT', 'Nenhuma academia selecionada');
        }
        $alunoId = $this->pacotes->findAlunoIdDoPaganteNoTenant($tenantId, $userId);
        if ($alunoId <= 0) {
            return $this->ok(['pacotes' => []]);
        }

        $pacotes = $this->queryDisponiveis($tenantId, $alunoId)->get()->map(function ($p) use ($tenantId, $userId) {
            $pendente = DB::table('pacote_contratos')->where('tenant_id', $tenantId)->where('pacote_id', $p->id)
                ->where('pagante_usuario_id', $userId)->where('status', 'pendente')->orderByDesc('id')->value('id');

            return [
                'id' => (int) $p->id,
                'nome' => (string) $p->nome,
                'descricao' => $p->descricao,
                'valor_total' => (float) $p->valor_total,
                'vagas' => (int) $p->qtd_beneficiarios + 1,
                'plano_nome' => $p->plano_nome,
                'modalidade_nome' => $p->modalidade_nome,
                'ciclo_meses' => $p->ciclo_meses !== null ? (int) $p->ciclo_meses : null,
                'liberado_para_voce' => $p->venda_app === 'liberados',
                'valida_ate' => $p->valida_ate,
                'contrato_pendente_id' => $pendente ? (int) $pendente : null,
            ];
        })->values()->all();

        return $this->ok(['pacotes' => $pacotes]);
    }

    /**
     * Contrata o pacote com o aluno como pagante (sem os demais: o admin associa depois).
     * Um contrato pendente do mesmo pacote é reaproveitado.
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function contratar(?int $tenantId, int $userId, int $pacoteId): array
    {
        if (! $tenantId) {
            return $this->erro(400, 'SEM_TENANT', 'Nenhuma academia selecionada');
        }
        $alunoId = $this->pacotes->findAlunoIdDoPaganteNoTenant($tenantId, $userId);
        if ($alunoId <= 0 || ! $this->queryDisponiveis($tenantId, $alunoId)->where('p.id', $pacoteId)->exists()) {
            return $this->erro(404, 'PACOTE_INDISPONIVEL', 'Este pacote não está disponível para você.');
        }

        $existente = DB::table('pacote_contratos')->where('tenant_id', $tenantId)->where('pacote_id', $pacoteId)
            ->where('pagante_usuario_id', $userId)->where('status', 'pendente')->orderByDesc('id')->value('id');
        if ($existente) {
            return $this->ok(['contrato_id' => (int) $existente, 'reaproveitado' => true]);
        }

        $r = $this->adminPacotes->contratar($tenantId, $pacoteId, ['pagante_usuario_id' => $userId, 'beneficiarios' => []]);
        if (($r['status'] ?? 500) >= 300) {
            return $this->erro((int) $r['status'], 'ERRO_CONTRATAR', (string) ($r['body']['message'] ?? $r['body']['error'] ?? 'Não foi possível contratar o pacote.'));
        }
        $contratoId = (int) $r['body']['pacote_contrato_id'];
        DB::table('pacote_contratos')->where('id', $contratoId)->update(['origem' => 'app', 'updated_at' => now()]);

        return $this->ok(['contrato_id' => $contratoId, 'reaproveitado' => false], 201);
    }

    /**
     * Gera a cobrança do período em aberto do contrato (PIX ou cartão/checkout), na matrícula do pagante.
     *
     * @param  array<string, mixed>  $body  metodo_pagamento (pix|cartao|checkout), gateway, installment_count
     * @return array{status: int, body: array<string, mixed>}
     */
    public function cobrar(?int $tenantId, int $userId, int $contratoId, array $body, bool $podeSandbox = false): array
    {
        if (! $tenantId) {
            return $this->erro(400, 'SEM_TENANT', 'Nenhuma academia selecionada');
        }
        $contrato = $this->contratoDoPagante($tenantId, $userId, $contratoId);
        if (! $contrato) {
            return $this->erro(404, 'CONTRATO_NAO_ENCONTRADO', 'Contrato não encontrado');
        }
        $this->conciliacao->conciliar($tenantId, $contratoId);

        $paganteMatriculaId = $this->conciliacao->matriculaDoPagante($tenantId, $contratoId);
        $periodo = $this->periodoEmAberto($tenantId, $contratoId, $paganteMatriculaId);
        if (! $periodo) {
            return $this->erro(422, 'NADA_A_PAGAR', 'Não há pagamento em aberto neste pacote.');
        }

        $usuario = $this->usuarios->findById($userId, $tenantId);
        if (! $usuario) {
            return $this->erro(404, 'USUARIO_NAO_ENCONTRADO', 'Usuário não encontrado');
        }
        $cpf = preg_replace('/\D/', '', (string) ($usuario['cpf'] ?? ''));
        if (strlen($cpf) !== 11) {
            return $this->erro(400, 'CPF_OBRIGATORIO', 'Cadastre um CPF válido para pagar pelo app.');
        }

        $metodo = strtolower((string) ($body['metodo_pagamento'] ?? $body['metodo'] ?? 'pix'));
        $metodo = in_array($metodo, ['cartao', 'credit_card', 'card', 'checkout', 'boleto'], true) ? 'checkout' : 'pix';
        $pedido = isset($body['gateway']) ? (string) $body['gateway'] : (isset($body['provider']) ? (string) $body['provider'] : null);
        $escolha = $metodo === 'pix'
            ? $this->gateways->resolverPix($tenantId, $podeSandbox, $pedido)
            : $this->gateways->resolverMetodo($tenantId, $podeSandbox, 'checkout', $pedido);
        if (! ($escolha['ok'] ?? false)) {
            return [
                'status' => (int) ($escolha['status'] ?? 400),
                'body' => ['success' => false, 'code' => $escolha['code'] ?? 'SEM_GATEWAY', 'message' => $escolha['error'] ?? 'Nenhum meio de pagamento disponível.', 'gateways' => $escolha['gateways'] ?? []],
            ];
        }
        $provider = (string) $escolha['provider'];
        $gateway = $escolha['instance'];

        $nomePacote = (string) $contrato->pacote_nome;
        $dados = [
            'tenant_id' => $tenantId,
            'matricula_id' => $paganteMatriculaId,
            'aluno_id' => (int) $periodo['aluno_id'],
            'usuario_id' => $userId,
            'aluno_nome' => $usuario['nome'] ?? '',
            'aluno_email' => $usuario['email'] ?? '',
            'aluno_telefone' => $usuario['telefone'] ?? '',
            'aluno_cpf' => $cpf,
            'plano_nome' => $nomePacote,
            'descricao' => 'Pacote: '.$nomePacote.($periodo['renovacao'] ? ' (renovação)' : ''),
            'valor' => $periodo['valor'],
            'max_parcelas' => 12,
            'installment_count' => (int) ($body['installment_count'] ?? $body['parcelas'] ?? 1),
            'academia_nome' => DB::table('tenants')->where('id', $tenantId)->value('nome') ?? 'Academia',
            'metodo_pagamento' => $metodo === 'pix' ? 'pix' : 'cartao',
            'billing_type' => $metodo === 'pix' ? '' : 'CREDIT_CARD',
        ];

        $anteriores = $this->cobrancasAnteriores->idsEmAberto($tenantId, $paganteMatriculaId);
        try {
            if ($metodo === 'pix') {
                $cobranca = $gateway->criarPagamentoPix($dados);
                $paymentUrl = $cobranca['ticket_url'] ?? null;
            } elseif ($gateway instanceof AsaasService) {
                $cobranca = $gateway->criarCobrancaCheckout($dados);
                $paymentUrl = $cobranca['ticket_url'] ?? null;
            } else {
                $cobranca = $gateway->criarPreferenciaPagamento($dados);
                $paymentUrl = $cobranca['init_point'] ?? null;
            }
        } catch (\Throwable $e) {
            Log::warning('Pacote app: falha ao gerar cobrança', ['tenant_id' => $tenantId, 'contrato_id' => $contratoId, 'error' => $e->getMessage()]);

            return $this->erro(400, 'ERRO_COBRANCA', 'Não foi possível gerar o pagamento: '.$e->getMessage());
        }
        $paymentId = (string) ($cobranca['id'] ?? '');
        if ($paymentId === '') {
            return $this->erro(502, 'ERRO_COBRANCA', 'O meio de pagamento não confirmou a cobrança. Tente de novo.');
        }

        DB::transaction(function () use ($tenantId, $contratoId, $paganteMatriculaId, $periodo, $cobranca, $paymentId, $paymentUrl, $provider, $metodo, $contrato) {
            DB::table('pacote_cobrancas')->updateOrInsert(
                ['pagamento_plano_id' => $periodo['parcela_pagante_id']],
                ['tenant_id' => $tenantId, 'pacote_contrato_id' => $contratoId, 'valor' => $periodo['valor'], 'payment_id' => $paymentId, 'updated_at' => now(), 'created_at' => now()],
            );
            if ($metodo === 'pix' && ! empty($cobranca['ticket_url'])) {
                DB::table('pagamentos_pix')->insert([
                    'tenant_id' => $tenantId,
                    'matricula_id' => $paganteMatriculaId,
                    'payment_id' => $paymentId,
                    'ticket_url' => $cobranca['ticket_url'] ?? null,
                    'qr_code' => $cobranca['qr_code'] ?? null,
                    'qr_code_base64' => $cobranca['qr_code_base64'] ?? null,
                    'expires_at' => isset($cobranca['date_of_expiration']) ? date('Y-m-d H:i:s', strtotime((string) $cobranca['date_of_expiration'])) : null,
                    'status' => $cobranca['status'] ?? 'pending',
                ]);
            }
            $this->salvarAssinatura($tenantId, $contratoId, $paganteMatriculaId, (int) $periodo['aluno_id'], (int) $contrato->plano_id, $periodo['valor'], $paymentId, $paymentUrl, (string) ($cobranca['external_reference'] ?? ReferenciaExterna::matricula($paganteMatriculaId)), $provider, $metodo);
        });

        // A cobrança anterior do pagante (outro PIX/checkout) não pode continuar aberta no provedor
        $canceladas = [];
        if ($anteriores !== []) {
            $canceladas = $this->cobrancasAnteriores->cancelar($tenantId, $anteriores, [$paymentId])['canceladas'] ?? [];
        }

        return $this->ok([
            'contrato_id' => $contratoId,
            'matricula_id' => $paganteMatriculaId,
            'valor' => $periodo['valor'],
            'renovacao' => $periodo['renovacao'],
            'gateway' => $provider,
            'metodo_pagamento' => $metodo === 'pix' ? 'pix' : 'cartao',
            'payment_url' => $paymentUrl,
            'preference_id' => $paymentId,
            'aviso' => CobrancaAnteriorService::aviso($canceladas),
            'pix' => $metodo === 'pix' ? [
                'payment_id' => $paymentId,
                'status' => $cobranca['status'] ?? null,
                'qr_code' => $cobranca['qr_code'] ?? null,
                'qr_code_base64' => $cobranca['qr_code_base64'] ?? null,
                'ticket_url' => $cobranca['ticket_url'] ?? null,
                'expires_at' => $cobranca['date_of_expiration'] ?? null,
            ] : null,
        ]);
    }

    /**
     * Renovação pelo pagante: parcelas do próximo período para todo o grupo (o valor atual do pacote
     * rateado), vencendo no fim da vigência atual. Depois o app chama cobrar(). Idempotente.
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function renovar(?int $tenantId, int $userId, int $contratoId): array
    {
        if (! $tenantId) {
            return $this->erro(400, 'SEM_TENANT', 'Nenhuma academia selecionada');
        }
        $contrato = $this->contratoDoPagante($tenantId, $userId, $contratoId);
        if (! $contrato) {
            return $this->erro(404, 'CONTRATO_NAO_ENCONTRADO', 'Contrato não encontrado');
        }
        $this->conciliacao->conciliar($tenantId, $contratoId);
        $contrato = $this->contratoDoPagante($tenantId, $userId, $contratoId);
        if ($contrato->status !== 'ativo') {
            return $this->erro(422, 'RENOVACAO_INDISPONIVEL', 'Só é possível renovar um pacote já pago.');
        }

        $paganteMatriculaId = $this->conciliacao->matriculaDoPagante($tenantId, $contratoId);
        $aberto = $this->periodoEmAberto($tenantId, $contratoId, $paganteMatriculaId);
        if ($aberto) {
            return $this->ok(['contrato_id' => $contratoId, 'valor' => $aberto['valor'], 'vencimento' => $aberto['vencimento'], 'ja_gerada' => true]);
        }

        $janela = self::renovacaoLiberadaEm($contrato->data_fim);
        if ($janela !== null && $janela > now()->toDateString()) {
            return $this->erro(422, 'RENOVACAO_AINDA_NAO_LIBERADA', 'A renovação fica disponível a partir de '.date('d/m/Y', strtotime($janela)).'.');
        }

        $pacote = $this->pacotes->findPacoteComPlano((int) $contrato->pacote_id, $tenantId, false);
        if (! $pacote || ! (int) $pacote['ativo']) {
            return $this->erro(422, 'PACOTE_INATIVO', 'Este pacote não está mais disponível para renovação. Fale com a academia.');
        }

        // Grupo atual (pagante primeiro): quem ainda tem matrícula no contrato
        $membros = DB::table('matriculas as m')
            ->join('alunos as a', 'a.id', '=', 'm.aluno_id')
            ->join('status_matricula as sm', 'sm.id', '=', 'm.status_id')
            ->where('m.tenant_id', $tenantId)
            ->where('m.pacote_contrato_id', $contratoId)
            ->whereNotIn('sm.codigo', ['cancelada', 'finalizada', 'concluida'])
            ->orderByRaw('CASE WHEN a.usuario_id = ? THEN 0 ELSE 1 END', [$userId])
            ->orderBy('m.id')
            ->get(['m.id as matricula_id', 'm.aluno_id']);
        if ($membros->isEmpty()) {
            return $this->erro(422, 'SEM_PARTICIPANTES', 'Não há participantes ativos neste pacote.');
        }

        $vencimento = max((string) ($contrato->data_fim ?: now()->toDateString()), now()->toDateString());
        $valorTotal = (float) $pacote['valor_total'];
        $base = round($valorTotal / $membros->count(), 2);
        $valorCheio = $this->descontos->resolverValorCheio($tenantId, (int) $pacote['plano_id'], ! empty($pacote['plano_ciclo_id']) ? (int) $pacote['plano_ciclo_id'] : null);

        DB::transaction(function () use ($membros, $tenantId, $contratoId, $pacote, $valorTotal, $base, $valorCheio, $vencimento) {
            $acumulado = 0.0;
            foreach ($membros->values() as $i => $m) {
                $valor = $i === $membros->count() - 1 ? round($valorTotal - $acumulado, 2) : $base;
                $acumulado += $valor;
                $this->descontos->criarPagamentoPacote(
                    $tenantId,
                    (int) $m->aluno_id,
                    (int) $m->matricula_id,
                    (int) $pacote['plano_id'],
                    $contratoId,
                    (string) $pacote['nome'],
                    $valorCheio >= 0.01 ? $valorCheio : $valor,
                    $valor,
                    $vencimento,
                    $vencimento,
                    null,
                    'Renovação do pacote',
                );
            }
            DB::table('pacote_contratos')->where('id', $contratoId)->update(['valor_total' => $valorTotal, 'updated_at' => now()]);
        });

        return $this->ok(['contrato_id' => $contratoId, 'valor' => $valorTotal, 'vencimento' => $vencimento, 'ja_gerada' => false], 201);
    }

    /** Data a partir da qual o pagante pode renovar (null = já pode). */
    public static function renovacaoLiberadaEm(?string $dataFim): ?string
    {
        return $dataFim ? date('Y-m-d', strtotime($dataFim.' -'.self::JANELA_RENOVACAO_DIAS.' days')) : null;
    }

    /* ---------- Internos ---------- */

    private function queryDisponiveis(int $tenantId, int $alunoId)
    {
        $hoje = now()->toDateString();

        return DB::table('pacotes as p')
            ->join('planos as pl', 'pl.id', '=', 'p.plano_id')
            ->leftJoin('modalidades as mo', 'mo.id', '=', 'pl.modalidade_id')
            ->leftJoin('plano_ciclos as ci', 'ci.id', '=', 'p.plano_ciclo_id')
            ->leftJoin('pacote_liberacoes as lib', fn ($j) => $j->on('lib.pacote_id', '=', 'p.id')->where('lib.aluno_id', '=', $alunoId))
            ->where('p.tenant_id', $tenantId)
            ->where('p.ativo', 1)
            ->where(fn ($w) => $w->where('p.venda_app', 'todos')
                ->orWhere(fn ($l) => $l->where('p.venda_app', 'liberados')->whereNotNull('lib.id')
                    ->where(fn ($v) => $v->whereNull('lib.valida_ate')->orWhere('lib.valida_ate', '>=', $hoje))))
            ->orderBy('p.valor_total')
            ->select(['p.id', 'p.nome', 'p.descricao', 'p.valor_total', 'p.qtd_beneficiarios', 'p.venda_app', 'pl.nome as plano_nome', 'mo.nome as modalidade_nome', 'ci.meses as ciclo_meses', 'lib.valida_ate']);
    }

    private function contratoDoPagante(int $tenantId, int $userId, int $contratoId): ?object
    {
        return DB::table('pacote_contratos as pc')
            ->join('pacotes as p', 'p.id', '=', 'pc.pacote_id')
            ->where('pc.tenant_id', $tenantId)
            ->where('pc.id', $contratoId)
            ->where('pc.pagante_usuario_id', $userId)
            ->whereIn('pc.status', ['pendente', 'ativo'])
            ->first(['pc.id', 'pc.status', 'pc.pacote_id', 'pc.data_fim', 'pc.valor_total', 'p.nome as pacote_nome', 'p.plano_id']);
    }

    /**
     * Período mais antigo com parcela em aberto no contrato: total do grupo nesse vencimento e a parcela
     * do pagante (a que recebe a baixa do gateway).
     *
     * @return array{vencimento: string, valor: float, parcela_pagante_id: int, aluno_id: int, renovacao: bool}|null
     */
    private function periodoEmAberto(int $tenantId, int $contratoId, int $paganteMatriculaId): ?array
    {
        if ($paganteMatriculaId <= 0) {
            return null;
        }
        $abertas = fn () => DB::table('pagamentos_plano as pp')
            ->join('matriculas as m', fn ($j) => $j->on('m.id', '=', 'pp.matricula_id')->on('m.tenant_id', '=', 'pp.tenant_id'))
            ->where('pp.tenant_id', $tenantId)
            ->where('m.pacote_contrato_id', $contratoId)
            ->whereIn('pp.status_pagamento_id', [1, 3])
            ->whereNull('pp.data_pagamento');

        $parcelaPagante = $abertas()->where('pp.matricula_id', $paganteMatriculaId)
            ->orderBy('pp.data_vencimento')->orderBy('pp.id')
            ->first(['pp.id', 'pp.data_vencimento', 'pp.aluno_id', 'pp.observacoes']);
        if (! $parcelaPagante) {
            return null;
        }
        $vencimento = substr((string) $parcelaPagante->data_vencimento, 0, 10);
        $valor = round((float) $abertas()->where('pp.data_vencimento', $parcelaPagante->data_vencimento)->sum('pp.valor'), 2);

        return $valor < 0.01 ? null : [
            'vencimento' => $vencimento,
            'valor' => $valor,
            'parcela_pagante_id' => (int) $parcelaPagante->id,
            'aluno_id' => (int) $parcelaPagante->aluno_id,
            'renovacao' => str_contains((string) $parcelaPagante->observacoes, 'Renovação'),
        ];
    }

    private function salvarAssinatura(int $tenantId, int $contratoId, int $matriculaId, int $alunoId, int $planoId, float $valor, string $paymentId, ?string $paymentUrl, string $externalReference, string $provider, string $metodo): void
    {
        $dados = [
            'gateway_id' => AssinaturaGateway::id($provider === 'asaas' ? 'asaas' : 'mercadopago'),
            'gateway_preference_id' => $paymentId,
            'external_reference' => $externalReference,
            'payment_url' => $paymentUrl,
            'status_gateway' => 'pending',
            'valor' => $valor,
            'pacote_contrato_id' => $contratoId,
            'atualizado_em' => now(),
        ];
        $metodoId = MetodoPagamentoResolver::resolve($metodo === 'pix' ? 'pix' : 'cartao');
        if ($metodoId !== null) {
            $dados['metodo_pagamento_id'] = $metodoId;
        }

        $assinaturaId = DB::table('assinaturas')->where('tenant_id', $tenantId)->where('matricula_id', $matriculaId)->orderByDesc('id')->value('id');
        if ($assinaturaId) {
            DB::table('assinaturas')->where('id', $assinaturaId)->update($dados);
        } else {
            $assinaturaId = DB::table('assinaturas')->insertGetId($dados + [
                'tenant_id' => $tenantId,
                'matricula_id' => $matriculaId,
                'aluno_id' => $alunoId,
                'plano_id' => $planoId,
                'status_id' => DB::table('assinatura_status')->where('codigo', 'pendente')->value('id') ?: 1,
                'frequencia_id' => 4,
                'dia_cobranca' => (int) date('d'),
                'data_inicio' => date('Y-m-d'),
                'tipo_cobranca' => 'avulso',
                'criado_em' => now(),
            ]);
        }
        DB::table('pacote_contratos')->where('id', $contratoId)->update([
            'assinatura_id' => $assinaturaId,
            'payment_url' => $paymentUrl,
            'payment_preference_id' => $paymentId,
            'updated_at' => now(),
        ]);
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function ok(array $data, int $status = 200): array
    {
        return ['status' => $status, 'body' => ['success' => true, 'data' => $data]];
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function erro(int $status, string $code, string $message): array
    {
        return ['status' => $status, 'body' => ['success' => false, 'code' => $code, 'message' => $message]];
    }
}
