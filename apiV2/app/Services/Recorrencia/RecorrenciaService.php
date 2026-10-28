<?php

namespace App\Services\Recorrencia;

use App\Services\Asaas\AsaasService;
use App\Services\MatriculaEncadeamentoService;
use App\Services\PagamentoPlanoService;
use App\Support\AcademyDateTime;
use App\Support\AsaasPagamentoStatus;
use App\Support\Pessoa;
use App\Support\RecorrenciaCalculo as Calc;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Planos com fidelização: assinatura mensal com preço promocional condicionado à permanência mínima.
 *
 * Contratar cria matrícula + contrato (valores e termos congelados) + assinatura mensal no Asaas. Cada
 * cobrança gerada vira uma mensalidade (recorrencia_cobrancas) ligada a uma parcela de pagamentos_plano;
 * a baixa vem do webhook (confirmada na API do Asaas) e é idempotente pelo id da cobrança.
 *
 * Máquina de estados do contrato (toda transição vai para recorrencia_eventos):
 *   pendente_ativacao → ativo ⇄ pagamento_pendente → inadimplente
 *   ativo|pagamento_pendente|inadimplente → cancelamento_solicitado → cancelamento_agendado → cancelado
 *   ativo → (fim da fidelização, política "encerrar") → encerrado
 */
class RecorrenciaService
{
    public const ATIVOS = ['pendente_ativacao', 'ativo', 'pagamento_pendente', 'inadimplente'];

    private const PAGOS = ['RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH'];

    private const ESTORNOS = ['REFUNDED', 'REFUND_REQUESTED', 'REFUND_IN_PROGRESS', 'CHARGEBACK_REQUESTED', 'CHARGEBACK_DISPUTE', 'AWAITING_CHARGEBACK_REVERSAL'];

    /** Dias de atraso de uma mensalidade para o contrato virar inadimplente (mesma régua da matrícula). */
    public const DIAS_INADIMPLENCIA = 5;

    public function __construct(
        private readonly RecorrenciaAcesso $acesso,
        private readonly PagamentoPlanoService $pagamentosPlano,
        private readonly MatriculaEncadeamentoService $encadeamento,
    ) {}

    protected function asaas(int $tenantId): AsaasService
    {
        return app(AsaasService::class, ['tenantId' => $tenantId]);
    }

    /* ================================================================ ofertas e termos */

    /**
     * Ciclos com fidelização de um plano, com os termos que o aluno aceita.
     *
     * @return list<array<string, mixed>>
     */
    public function ofertas(int $tenantId, int $planoId): array
    {
        $plano = DB::table('planos as p')->leftJoin('modalidades as m', 'm.id', '=', 'p.modalidade_id')
            ->where('p.id', $planoId)->where('p.tenant_id', $tenantId)->where('p.ativo', 1)
            ->first(['p.id', 'p.nome', 'p.valor', 'p.modalidade_id', 'm.nome as modalidade_nome']);
        if (! $plano) {
            return [];
        }
        $ciclos = DB::table('plano_ciclos as pc')->join('assinatura_frequencias as af', 'af.id', '=', 'pc.assinatura_frequencia_id')
            ->where('pc.plano_id', $planoId)->where('pc.tenant_id', $tenantId)->where('pc.ativo', 1)->where('pc.cobranca_mensal', 1)
            ->orderBy('pc.meses')->get(['pc.*', 'af.nome as ciclo_nome']);

        return $ciclos->map(fn ($c) => $this->oferta($tenantId, (array) $plano, (array) $c))->all();
    }

    /**
     * @param  array<string, mixed>  $plano
     * @param  array<string, mixed>  $ciclo
     * @return array<string, mixed>
     */
    public function oferta(int $tenantId, array $plano, array $ciclo): array
    {
        $meses = (int) $ciclo['meses'];
        $mensal = Calc::reais(intdiv(Calc::centavos((string) $ciclo['valor']), max(1, $meses)));
        $referencia = Calc::reais(Calc::centavos((string) $plano['valor']));
        $fidelizacao = (int) ($ciclo['fidelizacao_meses'] ?: $meses);
        $termos = $this->termos($tenantId, $plano, $ciclo, $mensal, $referencia, $fidelizacao);

        return [
            'plano_id' => (int) $plano['id'], 'plano_ciclo_id' => (int) $ciclo['id'], 'plano_nome' => $plano['nome'],
            'ciclo_nome' => $ciclo['ciclo_nome'] ?? "{$meses} meses", 'modalidade_nome' => $plano['modalidade_nome'] ?? null,
            'valor_mensal' => $mensal, 'valor_mensal_referencia' => $referencia,
            'desconto_mensal' => Calc::reais(max(0, Calc::centavos($referencia) - Calc::centavos($mensal))),
            'fidelizacao_meses' => $fidelizacao, 'politica_renovacao' => $ciclo['politica_renovacao'],
            'regularizacao_ativa' => (bool) $ciclo['regularizacao_ativa'], 'arrependimento_dias' => (int) $ciclo['arrependimento_dias'],
            'termos' => $termos, 'termos_versao' => (int) $ciclo['termos_versao'], 'termos_hash' => hash('sha256', $termos),
            'metodos' => $this->metodosAssinatura($tenantId),
        ];
    }

    /** Texto exato que o aluno aceita (gerado dos dados do ciclo + texto livre da academia). */
    private function termos(int $tenantId, array $plano, array $ciclo, string $mensal, string $referencia, int $fidelizacao): string
    {
        $academia = DB::table('tenants')->where('id', $tenantId)->value('nome') ?? 'a academia';
        $brl = fn ($v) => Calc::brl(Calc::centavos($v));
        $renovacao = [
            'encerrar' => 'Ao fim da fidelização o contrato se encerra e não há novas cobranças.',
            'mensal' => "Ao fim da fidelização a assinatura continua mensal pelo valor de referência ({$brl($referencia)}/mês), sem novo prazo mínimo. Você pode cancelar a qualquer momento.",
            'promocional' => "Ao fim da fidelização a assinatura continua pelo mesmo valor ({$brl($mensal)}/mês), sem novo prazo mínimo. Você pode cancelar a qualquer momento.",
            'nova_fidelizacao' => "Ao fim da fidelização você receberá a oferta de um novo período promocional, que só começa com o seu aceite. Até lá a assinatura continua pelo mesmo valor ({$brl($mensal)}/mês), sem prazo mínimo.",
        ][$ciclo['politica_renovacao']] ?? '';
        $dif = Calc::centavos($referencia) - Calc::centavos($mensal);
        $cancel = $ciclo['regularizacao_ativa'] && $dif > 0
            ? "Se você cancelar antes de completar {$fidelizacao} mensalidades, será cobrada a diferença do desconto que já recebeu ({$brl(Calc::reais($dif))} por mensalidade paga), nunca de meses futuros."
            : 'Você pode cancelar a qualquer momento, sem cobrança de diferença de desconto.';
        $linhas = [
            "Plano {$plano['nome']} com fidelização de {$fidelizacao} meses em {$academia}.",
            "Você paga {$brl($mensal)} por mês (valor de referência sem fidelização: {$brl($referencia)}). Cada mês é uma cobrança separada; não é uma compra parcelada.",
            $cancel,
            "Você pode desistir em até {$ciclo['arrependimento_dias']} dias após o aceite, sem regularização.",
            $renovacao,
            'O cancelamento pode ser pedido pelo app; o acesso vai até o fim do período já pago.',
        ];
        // Termos adicionais: os próprios do ciclo, ou os da academia (herdados)
        $adicionais = trim((string) ($ciclo['termos'] ?? '')) ?: app(TermosFidelizacaoService::class)->textoDaAcademia($tenantId);
        if ($adicionais !== '') {
            $linhas[] = $adicionais;
        }

        return implode("\n\n", array_filter($linhas));
    }

    /**
     * Formas aceitas na assinatura mensal: cartão sempre (débito automático); PIX só com
     * "PIX na assinatura recorrente" ligado nas Configurações de pagamento (o aluno paga cada mês).
     *
     * @return list<string>
     */
    public function metodosAssinatura(int $tenantId): array
    {
        return app(\App\Repositories\TenantPaymentCredentialsRepository::class)->pixRecorrenciaHabilitado($tenantId) ? ['cartao', 'pix'] : ['cartao'];
    }

    /* ================================================================ contratar */

    /**
     * @param  array{plano_ciclo_id: mixed, metodo: mixed, termos_hash: mixed}  $data
     * @param  array{ip?: ?string, user_agent?: ?string}  $origem
     * @return array{status: int, body: array<string, mixed>}
     */
    public function contratar(int $tenantId, int $usuarioId, array $data, array $origem = []): array
    {
        $cicloId = (int) ($data['plano_ciclo_id'] ?? 0);
        $metodo = (string) ($data['metodo'] ?? 'cartao');
        if (! in_array($metodo, ['cartao', 'pix'], true)) {
            return $this->erro('Forma de pagamento inválida para a assinatura.', 422);
        }
        $aluno = DB::table('alunos as a')->join('usuarios as u', 'u.id', '=', 'a.usuario_id')
            ->join('tenant_usuario_papel as tup', fn ($j) => $j->on('tup.usuario_id', '=', 'a.usuario_id')->where('tup.tenant_id', $tenantId)->where('tup.papel_id', 1)->where('tup.ativo', 1))
            ->where('a.usuario_id', $usuarioId)
            ->first(['a.id', 'u.nome', 'u.email', Pessoa::select('cpf'), Pessoa::select('telefone')]);
        if (! $aluno) {
            return $this->erro('Aluno não encontrado nesta academia.', 404);
        }
        $ciclo = DB::table('plano_ciclos')->where('id', $cicloId)->where('tenant_id', $tenantId)->where('ativo', 1)->where('cobranca_mensal', 1)->first();
        $plano = $ciclo ? DB::table('planos as p')->leftJoin('modalidades as m', 'm.id', '=', 'p.modalidade_id')
            ->where('p.id', $ciclo->plano_id)->where('p.tenant_id', $tenantId)->where('p.ativo', 1)
            ->first(['p.id', 'p.nome', 'p.valor', 'p.modalidade_id', 'm.nome as modalidade_nome']) : null;
        if (! $ciclo || ! $plano) {
            return $this->erro('Plano com fidelização não encontrado.', 404);
        }
        $ciclo = (array) $ciclo + ['ciclo_nome' => DB::table('assinatura_frequencias')->where('id', $ciclo->assinatura_frequencia_id)->value('nome')];
        $oferta = $this->oferta($tenantId, (array) $plano, $ciclo);
        // O aluno aceita o texto exato que viu: termos mudaram no meio do caminho = recarregar
        if (! hash_equals($oferta['termos_hash'], (string) ($data['termos_hash'] ?? ''))) {
            return $this->erro('Os termos deste plano foram atualizados. Leia e aceite de novo.', 409, 'TERMOS_DESATUALIZADOS', ['oferta' => $oferta]);
        }
        if (! in_array($metodo, $oferta['metodos'], true)) {
            return $this->erro('A assinatura mensal desta academia é só no cartão. Para pagar por PIX, escolha o pagamento à vista.', 422, 'PIX_RECORRENCIA_DESLIGADO');
        }
        if (strlen(preg_replace('/\D/', '', (string) $aluno->cpf)) !== 11) {
            return $this->erro('Cadastre seu CPF no perfil para assinar.', 422, 'CPF_OBRIGATORIO');
        }

        // Um contrato vivo por modalidade; pendente de ativação é retomado (gera de novo o link)
        $existente = DB::table('contratos_recorrencia as c')->join('planos as p', 'p.id', '=', 'c.plano_id')
            ->where('c.tenant_id', $tenantId)->where('c.aluno_id', $aluno->id)->where('p.modalidade_id', $plano->modalidade_id)
            ->whereIn('c.status', self::ATIVOS)->orderByDesc('c.id')->first(['c.*']);
        if ($existente && $existente->status !== 'pendente_ativacao') {
            return $this->erro('Você já tem uma assinatura ativa nesta modalidade.', 409, 'ASSINATURA_EXISTENTE', ['contrato_id' => (int) $existente->id]);
        }
        if ($existente && (int) $existente->plano_ciclo_id === $cicloId && $existente->assinatura_gateway_id) {
            return $this->ok(['contrato_id' => (int) $existente->id, 'retomado' => true] + $this->pagamentoAberto($tenantId, (int) $existente->id));
        }
        $hoje = AcademyDateTime::today();
        $ativa = DB::table('matriculas as m')->join('planos as p', 'p.id', '=', 'm.plano_id')->join('status_matricula as s', 's.id', '=', 'm.status_id')
            ->where('m.tenant_id', $tenantId)->where('m.aluno_id', $aluno->id)->where('p.modalidade_id', $plano->modalidade_id)
            ->where('s.codigo', 'ativa')->whereRaw('COALESCE(m.proxima_data_vencimento, m.data_vencimento) >= ?', [$hoje])
            ->when($existente, fn ($q) => $q->where('m.id', '<>', $existente->matricula_id))
            ->value('m.id');
        if ($ativa) {
            return $this->erro('Você já tem um plano ativo nesta modalidade. A assinatura com fidelidade pode ser feita quando ele vencer.', 409, 'MATRICULA_ATIVA_EXISTENTE');
        }

        try {
            [$contratoId, $matriculaId] = DB::transaction(function () use ($tenantId, $usuarioId, $aluno, $plano, $ciclo, $oferta, $metodo, $hoje, $existente, $origem) {
                if ($existente) {
                    $this->transicionar($tenantId, (int) $existente->id, 'cancelado', 'substituido', null, ['por_ciclo' => $ciclo['id']]);
                }
                $matriculaId = $this->prepararMatricula($tenantId, (int) $aluno->id, (array) $plano, $ciclo, $oferta['valor_mensal'], $usuarioId, $hoje, $existente ? (int) $existente->matricula_id : null);
                $contratoId = (int) DB::table('contratos_recorrencia')->insertGetId([
                    'tenant_id' => $tenantId, 'aluno_id' => (int) $aluno->id, 'matricula_id' => $matriculaId,
                    'plano_id' => (int) $plano->id, 'plano_ciclo_id' => (int) $ciclo['id'], 'gateway' => 'asaas', 'metodo' => $metodo,
                    'valor_mensal_referencia' => $oferta['valor_mensal_referencia'], 'valor_mensal_contratado' => $oferta['valor_mensal'],
                    'valor_mensal_vigente' => $oferta['valor_mensal'], 'fidelizacao_meses' => $oferta['fidelizacao_meses'],
                    'inicio_em' => $hoje, 'fim_fidelizacao_em' => Calc::maisMeses($hoje, $oferta['fidelizacao_meses']), 'proxima_cobranca_em' => $hoje,
                    'status' => 'pendente_ativacao', 'em_fidelizacao' => 1, 'politica_renovacao' => $oferta['politica_renovacao'],
                    'politica_cancelamento' => json_encode(['regularizacao_ativa' => (bool) $ciclo['regularizacao_ativa'], 'proporcional' => (bool) $ciclo['regularizacao_proporcional'], 'arrependimento_dias' => (int) $ciclo['arrependimento_dias']]),
                    'termos_versao' => $oferta['termos_versao'], 'termos_aceitos' => $oferta['termos'], 'termos_hash' => $oferta['termos_hash'],
                    'aceite_em' => AcademyDateTime::nowFormatted(), 'aceite_usuario_id' => $usuarioId,
                    'aceite_ip' => isset($origem['ip']) ? mb_substr((string) $origem['ip'], 0, 45) : null,
                    'aceite_user_agent' => isset($origem['user_agent']) ? mb_substr((string) $origem['user_agent'], 0, 255) : null,
                    'contrato_anterior_id' => $existente->id ?? null, 'created_at' => now(), 'updated_at' => now(),
                ]);
                $this->evento($tenantId, $contratoId, 'contratado', null, 'pendente_ativacao', $usuarioId, ['termos_versao' => $oferta['termos_versao'], 'metodo' => $metodo]);

                return [$contratoId, $matriculaId];
            });
        } catch (\Throwable $e) {
            Log::error('Recorrência: falha ao contratar', ['tenant_id' => $tenantId, 'usuario_id' => $usuarioId, 'error' => $e->getMessage()]);

            return $this->erro('Não foi possível criar a assinatura. Tente de novo.', 500);
        }

        try {
            $sub = $this->asaas($tenantId)->criarAssinatura([
                'contrato_id' => $contratoId, 'valor' => $oferta['valor_mensal'],
                'billing_type' => ['cartao' => 'CREDIT_CARD', 'pix' => 'PIX'][$metodo],
                'proximo_vencimento' => $hoje,
                'descricao' => "{$plano->nome} · fidelização {$oferta['fidelizacao_meses']} meses · ".Calc::brl(Calc::centavos($oferta['valor_mensal'])).'/mês',
                // Política "encerrar": o próprio Asaas para depois da última mensalidade da fidelização
                'max_pagamentos' => $oferta['politica_renovacao'] === 'encerrar' ? $oferta['fidelizacao_meses'] : null,
                'cliente' => ['name' => (string) $aluno->nome, 'cpfCnpj' => (string) $aluno->cpf, 'email' => $aluno->email, 'mobilePhone' => $aluno->telefone],
            ]);
        } catch (\Throwable $e) {
            Log::error('Recorrência: Asaas recusou a assinatura', ['contrato_id' => $contratoId, 'error' => $e->getMessage()]);
            $this->transicionar($tenantId, $contratoId, 'cancelado', 'erro_gateway', null, ['erro' => mb_substr($e->getMessage(), 0, 300)]);

            return $this->erro('O gateway de pagamento recusou a assinatura: '.$e->getMessage(), 502, 'GATEWAY_ERRO');
        }
        DB::table('contratos_recorrencia')->where('id', $contratoId)->update(['assinatura_gateway_id' => $sub['id'], 'gateway_cliente_id' => $sub['customer'], 'updated_at' => now()]);
        $this->evento($tenantId, $contratoId, 'assinatura_criada', null, null, null, ['assinatura' => $sub['id']]);
        $this->sincronizar($tenantId, $contratoId);

        return ['status' => 201, 'body' => ['success' => true, 'data' => ['contrato_id' => $contratoId, 'matricula_id' => $matriculaId] + $this->pagamentoAberto($tenantId, $contratoId)]];
    }

    /**
     * Mesmas regras da compra pelo app: reaproveita matrícula pendente sem pagamento da modalidade;
     * senão cria e liga ao último ciclo pago (encadeamento).
     */
    private function prepararMatricula(int $tenantId, int $alunoId, array $plano, array $ciclo, string $mensal, int $usuarioId, string $hoje, ?int $matriculaAnterior): int
    {
        $statusPendente = (int) (DB::table('status_matricula')->where('codigo', 'pendente')->value('id') ?? 5);
        $campos = [
            'plano_id' => (int) $plano['id'], 'plano_ciclo_id' => (int) $ciclo['id'], 'tipo_cobranca' => 'recorrente', 'valor' => $mensal,
            'status_id' => $statusPendente, 'data_inicio' => $hoje, 'data_vencimento' => $hoje, 'proxima_data_vencimento' => $hoje,
            'dia_vencimento' => (int) substr($hoje, 8, 2), 'criado_por' => $usuarioId, 'updated_at' => now(),
        ];
        $reaproveitar = $matriculaAnterior ?: DB::table('matriculas as m')->join('planos as p', 'p.id', '=', 'm.plano_id')->join('status_matricula as s', 's.id', '=', 'm.status_id')
            ->where('m.tenant_id', $tenantId)->where('m.aluno_id', $alunoId)->where('p.modalidade_id', $plano['modalidade_id'])->where('s.codigo', 'pendente')
            ->orderByDesc('m.id')->value('m.id');
        if ($reaproveitar && $this->encadeamento->podeReaproveitar((int) $reaproveitar)) {
            DB::table('matriculas')->where('id', $reaproveitar)->where('tenant_id', $tenantId)->update($campos);
            $this->pagamentosPlano->cancelarParcelasAbertas($tenantId, (int) $reaproveitar, 'Cancelado: assinatura com fidelização');
            $matriculaId = (int) $reaproveitar;
        } else {
            $matriculaId = (int) DB::table('matriculas')->insertGetId($campos + [
                'tenant_id' => $tenantId, 'aluno_id' => $alunoId, 'data_matricula' => $hoje,
                'motivo_id' => (int) (DB::table('motivo_matricula')->where('codigo', 'nova')->value('id') ?? 1),
                'periodo_teste' => 0, 'created_at' => now(),
            ]);
        }
        if ($anterior = $this->encadeamento->encadearAoUltimoCicloPago($matriculaId, $tenantId, $alunoId, (int) $plano['modalidade_id'], (int) $plano['id'], (float) $mensal)) {
            $this->pagamentosPlano->cancelarParcelasAbertas($tenantId, $anterior, "Cancelado: assinatura com fidelização (matrícula #{$matriculaId})");
        }

        return $matriculaId;
    }

    /* ================================================================ gateway */

    /** Busca as cobranças da assinatura no Asaas e processa cada uma (cobre webhook perdido). */
    public function sincronizar(int $tenantId, int $contratoId): int
    {
        $c = DB::table('contratos_recorrencia')->where('id', $contratoId)->where('tenant_id', $tenantId)->first();
        if (! $c || ! $c->assinatura_gateway_id) {
            return 0;
        }
        try {
            $pagamentos = $this->asaas($tenantId)->pagamentosDaAssinatura((string) $c->assinatura_gateway_id);
        } catch (\Throwable $e) {
            Log::warning('Recorrência: falha ao consultar cobranças da assinatura', ['contrato_id' => $contratoId, 'error' => $e->getMessage()]);

            return 0;
        }
        foreach ($pagamentos as $p) {
            $this->processarPagamento($tenantId, $contratoId, $p, 'SYNC', true);
        }

        return count($pagamentos);
    }

    /**
     * Uma cobrança do Asaas (webhook ou sincronização). $confirmado = payload já veio da API do Asaas;
     * senão consulta antes (nunca confia só no payload). Idempotente: repetir não baixa duas vezes.
     *
     * @param  array<string, mixed>  $payment
     */
    public function processarPagamento(int $tenantId, int $contratoId, array $payment, string $evento, bool $confirmado = false): ?string
    {
        $paymentId = (string) ($payment['id'] ?? '');
        if ($paymentId === '') {
            return null;
        }
        if (! $confirmado) {
            try {
                $remoto = $this->asaas($tenantId)->obterPagamento($paymentId);
                if ($remoto !== []) {
                    $payment = array_merge($payment, $remoto);
                }
            } catch (\Throwable $e) {
                Log::warning('Recorrência: não deu para confirmar a cobrança no Asaas', ['payment_id' => $paymentId, 'error' => $e->getMessage()]);

                return null;
            }
        }

        $resultado = DB::transaction(function () use ($tenantId, $contratoId, $payment, $paymentId, $evento) {
            // Trava o contrato: webhooks simultâneos da mesma assinatura são processados um de cada vez
            $c = DB::table('contratos_recorrencia')->where('id', $contratoId)->where('tenant_id', $tenantId)->lockForUpdate()->first();
            if (! $c) {
                return null;
            }
            $cob = $this->registrarCobranca($c, $payment);
            if (! $cob) {
                return null;
            }
            $status = strtoupper((string) ($payment['status'] ?? ''));

            if ($evento === 'PAYMENT_CREDIT_CARD_CAPTURE_REFUSED') {
                DB::table('recorrencia_cobrancas')->where('id', $cob->id)->increment('tentativas');
                $this->evento($tenantId, $contratoId, 'cartao_recusado', null, null, null, ['cobranca' => $cob->numero, 'payment_id' => $paymentId]);
                if ($c->status === 'ativo') {
                    $this->transicionar($tenantId, $contratoId, 'pagamento_pendente', 'cartao_recusado');
                }

                return 'recusado';
            }
            if (in_array($status, self::PAGOS, true)) {
                if ($cob->status === 'paga') {
                    return 'ja_paga';
                }
                $pagoEm = AsaasPagamentoStatus::dataPagamento($payment) ?: AcademyDateTime::today();
                DB::table('recorrencia_cobrancas')->where('id', $cob->id)->update(['status' => 'paga', 'pago_em' => $pagoEm, 'updated_at' => now()]);
                DB::table('pagamentos_plano')->where('id', $cob->pagamento_plano_id)->where('status_pagamento_id', '<>', 2)->update([
                    'status_pagamento_id' => 2, 'data_pagamento' => $pagoEm,
                    'forma_pagamento_id' => $this->formaPagamento((string) ($payment['billingType'] ?? '')),
                    'tipo_baixa_id' => DB::table('tipos_baixa')->where('nome', 'like', 'Integra%')->value('id') ?: 4,
                    'observacoes' => "Mensalidade {$cob->numero} da assinatura · Asaas {$paymentId}", 'updated_at' => now(),
                ]);
                DB::table('contratos_recorrencia')->where('id', $contratoId)->update(['proxima_cobranca_em' => Calc::maisUmMes((string) $cob->vencimento), 'updated_at' => now()]);
                $this->evento($tenantId, $contratoId, 'mensalidade_paga', null, null, null, ['cobranca' => $cob->numero, 'valor' => $cob->valor, 'payment_id' => $paymentId]);
                if (in_array($c->status, ['pendente_ativacao', 'pagamento_pendente', 'inadimplente'], true)
                    && ! DB::table('recorrencia_cobrancas')->where('contrato_id', $contratoId)->where('status', 'vencida')->exists()) {
                    $this->transicionar($tenantId, $contratoId, 'ativo', 'pagamento');
                }

                return 'paga';
            }
            if (in_array($status, self::ESTORNOS, true)) {
                if ($cob->status !== 'estornada') {
                    DB::table('recorrencia_cobrancas')->where('id', $cob->id)->update(['status' => 'estornada', 'updated_at' => now()]);
                    DB::table('pagamentos_plano')->where('id', $cob->pagamento_plano_id)->update([
                        'status_pagamento_id' => 4, 'observacoes' => "Mensalidade {$cob->numero} estornada/contestada no Asaas ({$status}) · {$paymentId}", 'updated_at' => now(),
                    ]);
                    $this->evento($tenantId, $contratoId, 'mensalidade_estornada', null, null, null, ['cobranca' => $cob->numero, 'status' => $status]);
                }

                return 'estornada';
            }
            if ($status === 'OVERDUE') {
                if ($cob->status === 'pendente') {
                    DB::table('recorrencia_cobrancas')->where('id', $cob->id)->update(['status' => 'vencida', 'updated_at' => now()]);
                    DB::table('pagamentos_plano')->where('id', $cob->pagamento_plano_id)->whereIn('status_pagamento_id', [1, 3])->update(['status_pagamento_id' => 3, 'updated_at' => now()]);
                    if ($c->status === 'ativo') {
                        $this->transicionar($tenantId, $contratoId, 'pagamento_pendente', 'mensalidade_vencida', null, ['cobranca' => $cob->numero]);
                    }
                }

                return 'vencida';
            }
            if ($status === 'DELETED' || $evento === 'PAYMENT_DELETED') {
                if (in_array($cob->status, ['pendente', 'vencida'], true)) {
                    DB::table('recorrencia_cobrancas')->where('id', $cob->id)->update(['status' => 'cancelada', 'updated_at' => now()]);
                    DB::table('pagamentos_plano')->where('id', $cob->pagamento_plano_id)->whereIn('status_pagamento_id', [1, 3])
                        ->update(['status_pagamento_id' => 4, 'observacoes' => "Mensalidade {$cob->numero} removida no Asaas", 'updated_at' => now()]);
                }

                return 'cancelada';
            }

            return 'registrada';
        });

        if ($resultado !== null) {
            $c = DB::table('contratos_recorrencia')->where('id', $contratoId)->first(['matricula_id']);
            $this->acesso->atualizar($tenantId, (int) $c->matricula_id);
            if ($resultado === 'paga') {
                $this->verificarFimFidelizacao($tenantId, $contratoId);
            }
        }

        return $resultado;
    }

    /**
     * Mensalidade do contrato para a cobrança do gateway (cria parcela em pagamentos_plano na 1ª vez).
     * Número = mês do vencimento contado do início: a mesma cobrança sempre cai no mesmo número.
     *
     * @param  array<string, mixed>  $payment
     */
    private function registrarCobranca(object $c, array $payment): ?object
    {
        $paymentId = (string) $payment['id'];
        $existente = DB::table('recorrencia_cobrancas')->where('gateway_payment_id', $paymentId)->first();
        if ($existente) {
            if ((int) $existente->contrato_id !== (int) $c->id) {
                Log::warning('Recorrência: cobrança já pertence a outro contrato', ['payment_id' => $paymentId]);

                return null;
            }

            return $existente;
        }
        $vencimento = substr((string) ($payment['dueDate'] ?? AcademyDateTime::today()), 0, 10);
        $numero = max(1, $this->mesesEntre((string) $c->inicio_em, $vencimento) + 1);
        $valor = Calc::reais(Calc::centavos((string) ($payment['value'] ?? $c->valor_mensal_vigente)));

        $mesmoNumero = DB::table('recorrencia_cobrancas')->where('contrato_id', $c->id)->where('numero', $numero)->first();
        if ($mesmoNumero && $mesmoNumero->status !== 'cancelada') {
            // Duas cobranças no mesmo mês (ex.: gerada de novo no Asaas): vai para o próximo número livre
            $numero = (int) DB::table('recorrencia_cobrancas')->where('contrato_id', $c->id)->max('numero') + 1;
            $mesmoNumero = null;
            Log::warning('Recorrência: segunda cobrança no mesmo mês', ['contrato_id' => $c->id, 'payment_id' => $paymentId]);
        }

        $parcelaId = (int) DB::table('pagamentos_plano')->insertGetId([
            'tenant_id' => $c->tenant_id, 'matricula_id' => $c->matricula_id, 'aluno_id' => $c->aluno_id, 'plano_id' => $c->plano_id,
            'valor' => $valor, 'valor_original' => $valor, 'desconto' => 0, 'data_vencimento' => $vencimento, 'status_pagamento_id' => 1,
            'observacoes' => "Mensalidade {$numero} da assinatura · aguardando pagamento (Asaas {$paymentId})",
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $dados = [
            'tenant_id' => $c->tenant_id, 'contrato_id' => $c->id, 'numero' => $numero, 'vencimento' => $vencimento, 'valor' => $valor,
            'gateway_payment_id' => $paymentId, 'pagamento_plano_id' => $parcelaId, 'status' => 'pendente', 'pago_em' => null,
            'url' => $payment['invoiceUrl'] ?? null, 'updated_at' => now(),
        ];
        if ($mesmoNumero) {
            DB::table('recorrencia_cobrancas')->where('id', $mesmoNumero->id)->update($dados);
        } else {
            DB::table('recorrencia_cobrancas')->insert($dados + ['created_at' => now()]);
        }

        return DB::table('recorrencia_cobrancas')->where('gateway_payment_id', $paymentId)->first();
    }

    /**
     * Link/QR da mensalidade em aberto mais antiga (pagar ou trocar o cartão).
     *
     * @return array<string, mixed>
     */
    public function pagamentoAberto(int $tenantId, int $contratoId): array
    {
        $cob = DB::table('recorrencia_cobrancas')->where('tenant_id', $tenantId)->where('contrato_id', $contratoId)
            ->whereIn('status', ['pendente', 'vencida'])->orderBy('vencimento')->first();
        if (! $cob) {
            return ['pagamento' => null];
        }
        $metodo = DB::table('contratos_recorrencia')->where('id', $contratoId)->value('metodo');
        $pix = null;
        if ($metodo === 'pix' && $cob->gateway_payment_id) {
            try {
                $pix = $this->asaas($tenantId)->pixDaCobranca((string) $cob->gateway_payment_id);
            } catch (\Throwable $e) {
                Log::info('Recorrência: QR PIX indisponível', ['cobranca' => $cob->id, 'error' => $e->getMessage()]);
            }
        }

        return ['pagamento' => ['numero' => (int) $cob->numero, 'valor' => $cob->valor, 'vencimento' => $cob->vencimento, 'url' => $cob->url, 'metodo' => $metodo,
            'pix' => $pix ? ['qr_code' => $pix['payload'], 'qr_code_base64' => $pix['qr_code']] : null]];
    }

    /* ================================================================ fidelização e renovação */

    /** Depois da última mensalidade da fidelização: aplica a política de renovação aceita. */
    public function verificarFimFidelizacao(int $tenantId, int $contratoId, bool $porData = false): void
    {
        $c = DB::table('contratos_recorrencia')->where('id', $contratoId)->where('tenant_id', $tenantId)->first();
        if (! $c || ! $c->em_fidelizacao || ! in_array($c->status, self::ATIVOS, true)) {
            return;
        }
        $pagas = DB::table('recorrencia_cobrancas')->where('contrato_id', $contratoId)->where('status', 'paga')->where('numero', '<=', $c->fidelizacao_meses)->count();
        if ($pagas < (int) $c->fidelizacao_meses && ! ($porData && AcademyDateTime::today() >= $c->fim_fidelizacao_em)) {
            return;
        }
        $update = ['em_fidelizacao' => 0, 'updated_at' => now()];
        $dados = ['politica' => $c->politica_renovacao, 'mensalidades_pagas' => $pagas];
        try {
            switch ($c->politica_renovacao) {
                case 'encerrar':
                    if ($c->assinatura_gateway_id) {
                        $this->asaas($tenantId)->cancelarAssinatura((string) $c->assinatura_gateway_id);
                    }
                    $update['proxima_cobranca_em'] = null;
                    break;
                case 'mensal':
                    if ($c->assinatura_gateway_id && Calc::centavos((string) $c->valor_mensal_referencia) !== Calc::centavos((string) $c->valor_mensal_vigente)) {
                        $this->asaas($tenantId)->atualizarValorAssinatura((string) $c->assinatura_gateway_id, Calc::reais(Calc::centavos((string) $c->valor_mensal_referencia)));
                    }
                    $update['valor_mensal_vigente'] = Calc::reais(Calc::centavos((string) $c->valor_mensal_referencia));
                    break;
                case 'nova_fidelizacao':
                    $update['renovacao_oferecida_em'] = AcademyDateTime::nowFormatted();
                    break;
            }
        } catch (\Throwable $e) {
            // Sem confirmar no gateway não muda o contrato: a rotina diária tenta de novo
            Log::error('Recorrência: falha ao aplicar a renovação no Asaas', ['contrato_id' => $contratoId, 'error' => $e->getMessage()]);
            $this->evento($tenantId, $contratoId, 'renovacao_falhou', null, null, null, $dados + ['erro' => mb_substr($e->getMessage(), 0, 300)]);

            return;
        }
        DB::table('contratos_recorrencia')->where('id', $contratoId)->update($update);
        $this->evento($tenantId, $contratoId, 'fidelizacao_cumprida', null, null, null, $dados);
    }

    /**
     * Aluno aceita a nova fidelização oferecida (política nova_fidelizacao): novo contrato com novos
     * termos sobre a mesma assinatura; o anterior fica encerrado no histórico.
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function aceitarNovaFidelizacao(int $tenantId, int $usuarioId, int $contratoId, string $termosHash, array $origem = []): array
    {
        $c = $this->contratoDoUsuario($tenantId, $usuarioId, $contratoId);
        if (! $c || ! $c->renovacao_oferecida_em || $c->em_fidelizacao || ! in_array($c->status, self::ATIVOS, true)) {
            return $this->erro('Não há nova fidelização disponível para esta assinatura.', 404);
        }
        $ciclo = (array) DB::table('plano_ciclos')->where('id', $c->plano_ciclo_id)->first();
        $plano = (array) DB::table('planos as p')->leftJoin('modalidades as m', 'm.id', '=', 'p.modalidade_id')->where('p.id', $c->plano_id)->first(['p.id', 'p.nome', 'p.valor', 'p.modalidade_id', 'm.nome as modalidade_nome']);
        if (! $ciclo || ! $ciclo['ativo'] || ! $ciclo['cobranca_mensal']) {
            return $this->erro('Este plano não oferece mais fidelização.', 409);
        }
        $oferta = $this->oferta($tenantId, $plano, $ciclo);
        if (! hash_equals($oferta['termos_hash'], $termosHash)) {
            return $this->erro('Os termos foram atualizados. Leia e aceite de novo.', 409, 'TERMOS_DESATUALIZADOS', ['oferta' => $oferta]);
        }
        try {
            if ($c->assinatura_gateway_id && Calc::centavos($oferta['valor_mensal']) !== Calc::centavos((string) $c->valor_mensal_vigente)) {
                $this->asaas($tenantId)->atualizarValorAssinatura((string) $c->assinatura_gateway_id, $oferta['valor_mensal']);
            }
        } catch (\Throwable $e) {
            return $this->erro('O gateway não aceitou a renovação agora. Tente de novo.', 502);
        }
        $hoje = AcademyDateTime::today();
        $novoId = DB::transaction(function () use ($tenantId, $usuarioId, $c, $oferta, $hoje, $origem) {
            $proximo = (string) ($c->proxima_cobranca_em ?: $hoje);
            DB::table('contratos_recorrencia')->where('id', $c->id)->update(['assinatura_gateway_id' => null, 'updated_at' => now()]);
            $this->transicionar($tenantId, (int) $c->id, 'encerrado', 'renovado_com_nova_fidelizacao', $usuarioId);
            $novo = (array) $c;
            unset($novo['id']);
            $novoId = (int) DB::table('contratos_recorrencia')->insertGetId(array_merge($novo, [
                'valor_mensal_referencia' => $oferta['valor_mensal_referencia'], 'valor_mensal_contratado' => $oferta['valor_mensal'], 'valor_mensal_vigente' => $oferta['valor_mensal'],
                'fidelizacao_meses' => $oferta['fidelizacao_meses'], 'inicio_em' => $proximo, 'fim_fidelizacao_em' => Calc::maisMeses($proximo, $oferta['fidelizacao_meses']),
                'status' => 'ativo', 'em_fidelizacao' => 1, 'politica_renovacao' => $oferta['politica_renovacao'], 'renovacao_oferecida_em' => null, 'encerrado_em' => null,
                'termos_versao' => $oferta['termos_versao'], 'termos_aceitos' => $oferta['termos'], 'termos_hash' => $oferta['termos_hash'],
                'aceite_em' => AcademyDateTime::nowFormatted(), 'aceite_usuario_id' => $usuarioId,
                'aceite_ip' => $origem['ip'] ?? null, 'aceite_user_agent' => isset($origem['user_agent']) ? mb_substr((string) $origem['user_agent'], 0, 255) : null,
                'contrato_anterior_id' => $c->id, 'assinatura_gateway_id' => $c->assinatura_gateway_id, 'created_at' => now(), 'updated_at' => now(),
            ]));
            $this->evento($tenantId, $novoId, 'contratado', null, 'ativo', $usuarioId, ['renovacao_de' => $c->id, 'termos_versao' => $oferta['termos_versao']]);

            return $novoId;
        });

        return $this->ok(['contrato_id' => $novoId, 'message' => 'Nova fidelização aceita.']);
    }

    /* ================================================================ cancelamento */

    /**
     * @param  array{tipo?: ?string, data_efetiva?: ?string, isencao?: string|int|null}  $opcoes
     * @return array<string, mixed>|null
     */
    public function simularCancelamento(int $tenantId, int $contratoId, array $opcoes = []): ?array
    {
        $c = DB::table('contratos_recorrencia')->where('id', $contratoId)->where('tenant_id', $tenantId)->first();
        if (! $c) {
            return null;
        }
        $hoje = AcademyDateTime::today();
        $acessoAte = $this->acesso->acessoAte($contratoId);
        $dataEfetiva = (string) ($opcoes['data_efetiva'] ?? '') ?: ($acessoAte && $acessoAte > $hoje ? $acessoAte : $hoje);
        $cobrancas = DB::table('recorrencia_cobrancas')->where('contrato_id', $contratoId)->orderBy('numero')
            ->get(['numero', 'vencimento', 'valor', 'status'])->map(fn ($r) => ['numero' => (int) $r->numero, 'vencimento' => (string) $r->vencimento, 'valor' => (string) $r->valor, 'status' => (string) $r->status])->all();
        $calc = Calc::regularizacao([
            'valor_mensal_referencia' => (string) $c->valor_mensal_referencia, 'valor_mensal_contratado' => (string) $c->valor_mensal_contratado,
            'fidelizacao_meses' => (int) $c->fidelizacao_meses, 'em_fidelizacao' => (bool) $c->em_fidelizacao, 'fim_fidelizacao_em' => (string) $c->fim_fidelizacao_em,
            'aceite_em' => (string) $c->aceite_em, 'politica' => json_decode((string) $c->politica_cancelamento, true) ?: [],
        ], $cobrancas, $dataEfetiva, $hoje, $opcoes['tipo'] ?? null, Calc::centavos(isset($opcoes['isencao']) ? (string) $opcoes['isencao'] : null));
        $creditos = 0;
        try {
            $creditos = Calc::centavos((string) (DB::table('creditos_aluno')->where('tenant_id', $tenantId)->where('aluno_id', $c->aluno_id)
                ->where('status_credito_id', 1)->selectRaw('COALESCE(SUM(valor - COALESCE(valor_utilizado, 0)), 0) as saldo')->value('saldo') ?: '0'));
        } catch (\Throwable) {
            // tabela/colunas de crédito diferentes: só não mostra
        }

        return $calc + [
            'contrato_id' => $contratoId, 'acesso_ate' => $acessoAte, 'creditos_disponiveis' => Calc::reais($creditos),
            'valor_mensal_referencia' => (string) $c->valor_mensal_referencia, 'valor_mensal_contratado' => (string) $c->valor_mensal_contratado,
            'fidelizacao_meses' => (int) $c->fidelizacao_meses,
        ];
    }

    /**
     * Pedido de cancelamento (aluno ou admin). Para de gerar cobranças na hora; o acesso vai até o fim
     * do período pago. Sem valor a cobrar, já fica confirmado; com regularização, aguarda o admin
     * (que pode isentar). O pedido nunca depende de pagar antes.
     *
     * @param  array{motivo?: ?string, tipo?: ?string, data_efetiva?: ?string, isencao?: mixed, isencao_motivo?: ?string}  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function solicitarCancelamento(int $tenantId, int $contratoId, string $por, ?int $usuarioId, array $data = []): array
    {
        $c = DB::table('contratos_recorrencia')->where('id', $contratoId)->where('tenant_id', $tenantId)->first();
        if (! $c) {
            return $this->erro('Assinatura não encontrada.', 404);
        }
        if (! in_array($c->status, self::ATIVOS, true)) {
            $aberto = DB::table('cancelamentos_recorrencia')->where('contrato_id', $contratoId)->orderByDesc('id')->first();

            return $this->ok(['ja_cancelada' => true, 'cancelamento' => $aberto, 'message' => 'Esta assinatura já está em cancelamento ou encerrada.']);
        }
        $tipo = in_array($data['tipo'] ?? null, ['impossibilidade', 'administrativo'], true) && $por === 'admin' ? $data['tipo'] : null;
        $sim = $this->simularCancelamento($tenantId, $contratoId, ['tipo' => $tipo, 'data_efetiva' => $por === 'admin' ? ($data['data_efetiva'] ?? null) : null, 'isencao' => $por === 'admin' ? ($data['isencao'] ?? null) : null]);

        if ($c->assinatura_gateway_id) {
            try {
                $this->asaas($tenantId)->cancelarAssinatura((string) $c->assinatura_gateway_id);
            } catch (\Throwable $e) {
                Log::error('Recorrência: falha ao cancelar a assinatura no Asaas', ['contrato_id' => $contratoId, 'error' => $e->getMessage()]);

                return $this->erro('Não conseguimos cancelar no gateway de pagamento agora. Tente de novo em instantes.', 502, 'GATEWAY_ERRO');
            }
        }
        // Mensalidades futuras ainda não pagas não são devidas: cancela no Asaas e aqui
        $this->cancelarFuturas($tenantId, $contratoId, (string) $sim['data_efetiva']);

        $aConfirmar = Calc::centavos($sim['valor_regularizacao']) > 0 && $por === 'aluno';
        $cancelamentoId = DB::transaction(function () use ($tenantId, $contratoId, $c, $por, $usuarioId, $data, $sim, $aConfirmar) {
            $id = (int) DB::table('cancelamentos_recorrencia')->insertGetId([
                'tenant_id' => $tenantId, 'contrato_id' => $contratoId, 'tipo' => $sim['tipo'], 'solicitado_por' => $por, 'solicitado_por_usuario_id' => $usuarioId,
                'solicitado_em' => AcademyDateTime::nowFormatted(), 'data_efetiva' => $sim['data_efetiva'],
                'motivo' => isset($data['motivo']) ? mb_substr(trim((string) $data['motivo']), 0, 500) : null,
                'meses_usufruidos' => $sim['meses_usufruidos'], 'desconto_acumulado' => $sim['desconto_acumulado'],
                'valor_regularizacao' => $sim['regularizacao_calculada'], 'valor_isencao' => $sim['valor_isencao'],
                'isencao_motivo' => isset($data['isencao_motivo']) ? mb_substr((string) $data['isencao_motivo'], 0, 500) : null,
                'valor_em_aberto' => $sim['valor_em_aberto'], 'status' => 'solicitado', 'memoria_calculo' => json_encode($sim, JSON_UNESCAPED_UNICODE),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->transicionar($tenantId, $contratoId, 'cancelamento_solicitado', 'pedido_'.$por, $usuarioId, ['cancelamento' => $id, 'regularizacao' => $sim['valor_regularizacao']]);

            return $id;
        });

        if (! $aConfirmar) {
            return $this->confirmarCancelamento($tenantId, $cancelamentoId, $usuarioId, $por === 'admin' ? $data : []);
        }

        return $this->ok(['cancelamento_id' => $cancelamentoId, 'status' => 'solicitado', 'simulacao' => $sim,
            'message' => 'Pedido registrado. As cobranças futuras foram canceladas; a academia vai confirmar o valor de regularização.']);
    }

    /**
     * Admin confirma (com isenção opcional). Regularização > 0 vira parcela em pagamentos_plano.
     *
     * @param  array{isencao?: mixed, isencao_motivo?: ?string}  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function confirmarCancelamento(int $tenantId, int $cancelamentoId, ?int $usuarioId, array $data = []): array
    {
        return DB::transaction(function () use ($tenantId, $cancelamentoId, $usuarioId, $data) {
            $k = DB::table('cancelamentos_recorrencia')->where('id', $cancelamentoId)->where('tenant_id', $tenantId)->lockForUpdate()->first();
            if (! $k) {
                return $this->erro('Pedido de cancelamento não encontrado.', 404);
            }
            if ($k->status !== 'solicitado') {
                return $this->ok(['cancelamento_id' => $cancelamentoId, 'status' => $k->status, 'message' => 'Cancelamento já confirmado.']);
            }
            $calculado = Calc::centavos((string) $k->valor_regularizacao);
            $isencao = isset($data['isencao']) && $data['isencao'] !== '' ? min($calculado, max(0, Calc::centavos((string) $data['isencao']))) : Calc::centavos((string) $k->valor_isencao);
            if ($isencao > 0 && trim((string) ($data['isencao_motivo'] ?? $k->isencao_motivo ?? '')) === '') {
                return $this->erro('Informe o motivo da isenção.', 422);
            }
            $final = $calculado - $isencao;
            $c = DB::table('contratos_recorrencia')->where('id', $k->contrato_id)->first();
            $parcelaId = null;
            if ($final > 0) {
                $parcelaId = (int) DB::table('pagamentos_plano')->insertGetId([
                    'tenant_id' => $tenantId, 'matricula_id' => $c->matricula_id, 'aluno_id' => $c->aluno_id, 'plano_id' => $c->plano_id,
                    'valor' => Calc::reais($final), 'valor_original' => (string) $k->valor_regularizacao, 'desconto' => Calc::reais($isencao),
                    'motivo_desconto' => $isencao > 0 ? 'Isenção: '.mb_substr((string) ($data['isencao_motivo'] ?? $k->isencao_motivo), 0, 200) : null,
                    'data_vencimento' => AcademyDateTime::today(), 'status_pagamento_id' => 1, 'criado_por' => $usuarioId,
                    'observacoes' => "Regularização de fidelização (cancelamento #{$k->id}, contrato #{$c->id})", 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            DB::table('cancelamentos_recorrencia')->where('id', $k->id)->update([
                'status' => 'confirmado', 'efetivado_em' => AcademyDateTime::nowFormatted(), 'confirmado_por' => $usuarioId,
                'valor_isencao' => Calc::reais($isencao), 'isencao_motivo' => $isencao > 0 ? mb_substr((string) ($data['isencao_motivo'] ?? $k->isencao_motivo), 0, 500) : null,
                'pagamento_plano_id' => $parcelaId, 'updated_at' => now(),
            ]);
            $novo = $k->data_efetiva > AcademyDateTime::today() ? 'cancelamento_agendado' : 'cancelado';
            $this->transicionar($tenantId, (int) $c->id, $novo, 'cancelamento_confirmado', $usuarioId, ['cancelamento' => $k->id, 'regularizacao' => Calc::reais($final), 'isencao' => Calc::reais($isencao)]);

            return $this->ok(['cancelamento_id' => $k->id, 'status' => 'confirmado', 'contrato_status' => $novo, 'valor_regularizacao' => Calc::reais($final),
                'data_efetiva' => $k->data_efetiva, 'message' => $final > 0 ? 'Cancelamento confirmado. Regularização de '.Calc::brl($final).' lançada.' : 'Cancelamento confirmado.']);
        });
    }

    /** Mensalidades geradas depois da data efetiva e não pagas: canceladas no Asaas e aqui. */
    private function cancelarFuturas(int $tenantId, int $contratoId, string $dataEfetiva): void
    {
        $futuras = DB::table('recorrencia_cobrancas')->where('contrato_id', $contratoId)->where('status', 'pendente')->where('vencimento', '>=', $dataEfetiva)->get();
        foreach ($futuras as $f) {
            try {
                if ($f->gateway_payment_id) {
                    $this->asaas($tenantId)->cancelarCobranca((string) $f->gateway_payment_id);
                }
            } catch (\Throwable $e) {
                Log::warning('Recorrência: falha ao cancelar cobrança futura no Asaas', ['cobranca' => $f->id, 'error' => $e->getMessage()]);
            }
            DB::table('recorrencia_cobrancas')->where('id', $f->id)->update(['status' => 'cancelada', 'updated_at' => now()]);
            DB::table('pagamentos_plano')->where('id', $f->pagamento_plano_id)->whereIn('status_pagamento_id', [1, 3])
                ->update(['status_pagamento_id' => 4, 'observacoes' => "Mensalidade {$f->numero} cancelada (assinatura cancelada)", 'updated_at' => now()]);
        }
    }

    /* ================================================================ rotina diária */

    /** @return array<string, int> */
    public function rotina(?int $tenantId = null): array
    {
        $hoje = AcademyDateTime::today();
        $n = ['sincronizados' => 0, 'agendados_cancelados' => 0, 'encerrados' => 0, 'inadimplentes' => 0, 'fidelizacoes' => 0];
        $base = fn () => DB::table('contratos_recorrencia')->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId));

        foreach ($base()->whereIn('status', self::ATIVOS)->whereNotNull('assinatura_gateway_id')->get(['id', 'tenant_id']) as $c) {
            $this->sincronizar((int) $c->tenant_id, (int) $c->id);
            $n['sincronizados']++;
        }
        foreach ($base()->where('status', 'cancelamento_agendado')->get(['id', 'tenant_id']) as $c) {
            $efetiva = DB::table('cancelamentos_recorrencia')->where('contrato_id', $c->id)->where('status', 'confirmado')->orderByDesc('id')->value('data_efetiva');
            if ($efetiva && $efetiva < $hoje) {
                $this->transicionar((int) $c->tenant_id, (int) $c->id, 'cancelado', 'fim_do_acesso');
                $n['agendados_cancelados']++;
            }
        }
        foreach ($base()->whereIn('status', self::ATIVOS)->where('em_fidelizacao', 1)->where('fim_fidelizacao_em', '<=', $hoje)->get(['id', 'tenant_id']) as $c) {
            $this->verificarFimFidelizacao((int) $c->tenant_id, (int) $c->id, true);
            $n['fidelizacoes']++;
        }
        // Política "encerrar" cumprida: encerra quando o acesso pago acaba
        foreach ($base()->whereIn('status', self::ATIVOS)->where('em_fidelizacao', 0)->where('politica_renovacao', 'encerrar')->get(['id', 'tenant_id']) as $c) {
            $ate = $this->acesso->acessoAte((int) $c->id);
            if (! $ate || $ate < $hoje) {
                $this->transicionar((int) $c->tenant_id, (int) $c->id, 'encerrado', 'fidelizacao_encerrada');
                $n['encerrados']++;
            }
        }
        $limite = date('Y-m-d', strtotime($hoje.' -'.self::DIAS_INADIMPLENCIA.' days'));
        foreach ($base()->whereIn('status', ['ativo', 'pagamento_pendente'])->get(['id', 'tenant_id']) as $c) {
            if (DB::table('recorrencia_cobrancas')->where('contrato_id', $c->id)->whereIn('status', ['pendente', 'vencida'])->where('vencimento', '<=', $limite)->exists()) {
                $this->transicionar((int) $c->tenant_id, (int) $c->id, 'inadimplente', 'atraso');
                $n['inadimplentes']++;
            }
        }
        foreach (DB::table('contratos_recorrencia')->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->whereIn('status', ['cancelado', 'encerrado', 'cancelamento_agendado'])
            ->where('updated_at', '>=', now()->subDays(2))->get(['tenant_id', 'matricula_id']) as $c) {
            $this->acesso->atualizar((int) $c->tenant_id, (int) $c->matricula_id);
        }

        return $n;
    }

    /* ================================================================ apoio */

    public function contratoDoUsuario(int $tenantId, int $usuarioId, int $contratoId): ?object
    {
        return DB::table('contratos_recorrencia as c')->join('alunos as a', 'a.id', '=', 'c.aluno_id')
            ->where('c.id', $contratoId)->where('c.tenant_id', $tenantId)->where('a.usuario_id', $usuarioId)->first(['c.*']);
    }

    public function transicionar(int $tenantId, int $contratoId, string $para, string $motivo, ?int $usuarioId = null, array $dados = []): void
    {
        $de = DB::table('contratos_recorrencia')->where('id', $contratoId)->where('tenant_id', $tenantId)->value('status');
        if ($de === $para) {
            return;
        }
        $update = ['status' => $para, 'updated_at' => now()];
        if (in_array($para, ['cancelado', 'encerrado'], true)) {
            $update['encerrado_em'] = AcademyDateTime::nowFormatted();
            $update['proxima_cobranca_em'] = null;
        }
        if (in_array($para, ['cancelamento_solicitado', 'cancelamento_agendado', 'cancelado', 'encerrado'], true)) {
            $update['proxima_cobranca_em'] = null;
        }
        DB::table('contratos_recorrencia')->where('id', $contratoId)->where('tenant_id', $tenantId)->update($update);
        $this->evento($tenantId, $contratoId, $motivo, $de, $para, $usuarioId, $dados ?: null);
    }

    /** @param  array<string, mixed>|null  $dados */
    public function evento(int $tenantId, int $contratoId, string $tipo, ?string $de, ?string $para, ?int $usuarioId, ?array $dados = null): void
    {
        DB::table('recorrencia_eventos')->insert([
            'tenant_id' => $tenantId, 'contrato_id' => $contratoId, 'tipo' => $tipo, 'de_status' => $de, 'para_status' => $para,
            'usuario_id' => $usuarioId, 'dados' => $dados ? json_encode($dados, JSON_UNESCAPED_UNICODE) : null, 'created_at' => now(),
        ]);
    }

    private function mesesEntre(string $de, string $ate): int
    {
        [$y1, $m1, $d1] = array_map('intval', explode('-', substr($de, 0, 10)));
        [$y2, $m2, $d2] = array_map('intval', explode('-', substr($ate, 0, 10)));
        $meses = ($y2 - $y1) * 12 + ($m2 - $m1);
        // Vencimento alguns dias antes do dia de início (fim de semana/feriado no gateway) ainda é o mesmo mês
        if ($d2 + 5 < $d1 && $meses > 0) {
            $meses--;
        }

        return $meses;
    }

    private function formaPagamento(string $billingType): ?int
    {
        foreach (AsaasPagamentoStatus::formaPagamentoBusca($billingType) as $nome) {
            $id = DB::table('formas_pagamento')->whereRaw('LOWER(nome) = ?', [$nome])->value('id');
            if ($id) {
                return (int) $id;
            }
        }

        return null;
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function ok(array $data): array
    {
        return ['status' => 200, 'body' => ['success' => true, 'message' => $data['message'] ?? null, 'data' => $data]];
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function erro(string $mensagem, int $status, ?string $code = null, array $extra = []): array
    {
        return ['status' => $status, 'body' => ['success' => false, 'error' => $mensagem, 'message' => $mensagem, 'code' => $code] + $extra];
    }
}
