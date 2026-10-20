<?php

namespace App\Services\Admin;

use App\Services\Asaas\AsaasService;
use App\Services\Asaas\AsaasWebhookService;
use App\Services\MercadoPagoService;
use App\Support\AsaasPagamentoStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Conciliação bancária: o que entrou na conta do provedor (Asaas / Mercado Pago) no período,
 * com bruto, taxa e líquido, casado com as parcelas pagas no Check-in.
 *
 * Situações de cada linha:
 * - conciliado: pagamento do provedor com parcela paga de mesmo valor (e mesmo líquido, quando gravado);
 * - divergente: casou com a parcela, mas valor ou líquido não batem;
 * - sem_baixa: o provedor recebeu e nenhuma parcela paga no Check-in aponta para o pagamento;
 * - sem_recebimento: parcela com baixa automática no período sem pagamento correspondente no provedor;
 * - sem_vinculo: recebimento no provedor sem matrícula (cobrança criada direto no provedor, pacote etc.);
 * - manual: parcela paga fora do provedor (dinheiro, maquininha), só para conferência do caixa.
 */
class ConciliacaoBancariaService
{
    public const MAX_DIAS = 186;

    private const BAIXA_INTEGRACAO = 4;

    private const MAX_CONSULTAS_AVULSAS = 30;

    /** @var \Closure(string, int): object */
    private \Closure $gateway;

    public function __construct(?\Closure $gateway = null)
    {
        $this->gateway = $gateway ?? fn (string $provedor, int $tenantId) => $provedor === 'asaas'
            ? new AsaasService($tenantId)
            : new MercadoPagoService($tenantId);
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function conciliar(int $tenantId, ?string $inicio, ?string $fim): array
    {
        $periodo = $this->periodo($inicio, $fim);
        if (isset($periodo['erro'])) {
            return ['status' => 422, 'body' => ['success' => false, 'error' => $periodo['erro']]];
        }
        [$inicio, $fim] = $periodo;

        $conectados = $this->provedoresConectados($tenantId);
        $provedores = array_keys($conectados);
        $pagamentos = [];
        $erros = [];
        $truncado = false;
        foreach ($provedores as $provedor) {
            try {
                $lista = $this->buscarNoProvedor($tenantId, $provedor, $inicio, $fim);
                $truncado = $truncado || count($lista) >= 1000;
                array_push($pagamentos, ...$lista);
            } catch (Throwable $e) {
                $erros[] = ['provedor' => $provedor, 'provedor_nome' => self::nomeProvedor($provedor), 'erro' => $e->getMessage()];
            }
        }

        $locais = $this->parcelasLocais(
            $tenantId,
            $inicio,
            $fim,
            array_column($pagamentos, 'payment_id'),
            array_values(array_filter(array_column($pagamentos, 'matricula_id'))),
        );
        $pix = $this->matriculasDoPix($tenantId, array_column($pagamentos, 'payment_id'));
        $consultados = array_values(array_diff($provedores, array_column($erros, 'provedor')));
        $resultado = self::montar($pagamentos, $locais, $pix, $inicio, $fim, $consultados);

        // Parcela baixada pela integração que não apareceu no período: o pagamento pode ter outra data no provedor
        ['pagos' => $avulsos, 'situacao' => $situacaoCitados] = $this->consultarCitados($tenantId, $resultado['linhas'], $consultados);
        if ($avulsos !== []) {
            $resultado = self::montar(array_merge($pagamentos, $avulsos), $locais, $pix, $inicio, $fim, $consultados);
        }
        // O provedor tem a cobrança, mas não paga: diz em que situação ela está (estornada, removida…)
        foreach ($resultado['linhas'] as &$linha) {
            $sit = $linha['status'] === 'sem_recebimento' ? ($situacaoCitados[(string) $linha['payment_id']] ?? null) : null;
            if ($sit) {
                $linha['motivo'] = $sit;
            }
        }
        unset($linha);

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'academia' => DB::table('tenants')->where('id', $tenantId)->value('nome'),
                'periodo' => ['inicio' => $inicio, 'fim' => $fim],
                'provedores' => array_map(fn ($p) => ['id' => $p, 'nome' => self::nomeProvedor($p), 'ativo' => $conectados[$p]], $provedores),
                'erros' => $erros,
                'truncado' => $truncado,
            ] + $resultado,
        ];
    }

    /**
     * Reaplica no Check-in o pagamento do Asaas (mesmo fluxo do webhook): dá a baixa que faltou
     * ou regrava líquido e taxa da parcela.
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function reprocessar(int $tenantId, string $paymentId): array
    {
        if (! str_starts_with($paymentId, 'pay_')) {
            return ['status' => 422, 'body' => ['success' => false, 'error' => 'Só pagamentos do Asaas podem ser reprocessados por aqui.']];
        }
        if (! in_array('asaas', $this->provedoresAtivos($tenantId), true)) {
            return ['status' => 422, 'body' => ['success' => false, 'error' => 'O Asaas não está ativo nesta academia.']];
        }

        try {
            $payment = ($this->gateway)('asaas', $tenantId)->obterPagamento($paymentId);
        } catch (Throwable $e) {
            return ['status' => 502, 'body' => ['success' => false, 'error' => 'Não foi possível consultar o Asaas: '.$e->getMessage()]];
        }
        if (! AsaasPagamentoStatus::pago((string) ($payment['status'] ?? '')) || ($payment['deleted'] ?? false) === true) {
            return ['status' => 422, 'body' => ['success' => false, 'error' => 'Este pagamento não está pago no Asaas.']];
        }

        $matriculaId = self::matriculaDaReferencia((string) ($payment['externalReference'] ?? ''))
            ?? $this->matriculasDoPix($tenantId, [$paymentId])[$paymentId] ?? null;
        $daAcademia = $matriculaId !== null && DB::table('matriculas')->where('id', $matriculaId)->where('tenant_id', $tenantId)->exists();
        if (! $daAcademia) {
            return ['status' => 422, 'body' => ['success' => false, 'error' => 'O pagamento não está ligado a uma matrícula desta academia. Dê a baixa manualmente na matrícula.']];
        }

        $result = app(AsaasWebhookService::class)->processar([
            'id' => 'conciliacao-'.$paymentId,
            'event' => 'PAYMENT_RECEIVED',
            'payment' => $payment,
        ], null);
        $ok = ($result['status'] ?? 500) < 300;

        return [
            'status' => $ok ? 200 : 422,
            'body' => $ok
                ? ['success' => true, 'message' => 'Pagamento aplicado na matrícula #'.$matriculaId.'.']
                : ['success' => false, 'error' => (string) ($result['body']['error'] ?? $result['body']['message'] ?? 'O Check-in não aceitou o pagamento.')],
        ];
    }

    /**
     * Casa os pagamentos do provedor com as parcelas do Check-in. Sem I/O, para testar.
     *
     * @param  list<array<string, mixed>>  $pagamentos  normalizados (normalizarAsaas / normalizarMercadoPago)
     * @param  list<array<string, mixed>>  $locais  parcelas de pagamentos_plano
     * @param  array<string, int>  $pix  payment_id => matricula_id (pagamentos_pix)
     * @return array{resumo: array<string, mixed>, linhas: list<array<string, mixed>>}
     */
    public static function montar(array $pagamentos, array $locais, array $pix, string $inicio, string $fim, ?array $consultados = null): array
    {
        usort($pagamentos, fn ($a, $b) => strcmp((string) $a['data'], (string) $b['data']) ?: strcmp((string) $a['payment_id'], (string) $b['payment_id']));
        $usadas = [];
        $porParcelamento = [];
        $linhas = [];

        $livres = function (callable $filtro) use ($locais, &$usadas) {
            foreach ($locais as $l) {
                if (! isset($usadas[$l['id']]) && $filtro($l)) {
                    return $l;
                }
            }

            return null;
        };
        $pago = fn (array $l) => (int) $l['status_pagamento_id'] === 2;
        $aberto = fn (array $l) => in_array((int) $l['status_pagamento_id'], [1, 3], true);
        $cita = fn (array $l, string $id) => preg_match('/(?<![A-Za-z0-9_])'.preg_quote($id, '/').'(?![A-Za-z0-9])/', (string) ($l['observacoes'] ?? '')) === 1;
        $mesmoValor = fn (array $l, float $v) => abs((float) $l['valor'] - $v) < 0.02;
        $parcelaDoCartao = function (array $l, array $p) {
            $v = (float) $p['valor'];
            if ($v <= 0 || empty($p['parcelamento'])) {
                return false;
            }
            $n = (int) round((float) $l['valor'] / $v);

            return $n >= 2 && $n <= 12 && abs((float) $l['valor'] - $n * $v) <= 0.05 * $n;
        };

        foreach ($pagamentos as $p) {
            $id = (string) $p['payment_id'];
            $matricula = $p['matricula_id'] ?? $pix[$id] ?? null;
            $parcelamento = $p['parcelamento'] ?? null;

            $l = $livres(fn ($l) => $cita($l, $id));
            // Demais parcelas do mesmo cartão parcelado apontam para a parcela local do primeiro pagamento
            if (! $l && $parcelamento && isset($porParcelamento[$parcelamento])) {
                $l = $porParcelamento[$parcelamento];
            }
            if (! $l && $matricula) {
                $l = $livres(fn ($l) => $pago($l) && (int) $l['matricula_id'] === (int) $matricula
                    && ($mesmoValor($l, (float) $p['valor']) || $parcelaDoCartao($l, $p)));
            }
            $aberta = null;
            if (! $l && $matricula) {
                $aberta = $livres(fn ($l) => $aberto($l) && (int) $l['matricula_id'] === (int) $matricula && $mesmoValor($l, (float) $p['valor']));
            }

            if ($l) {
                $usadas[$l['id']] = true;
                if ($parcelamento) {
                    $porParcelamento[$parcelamento] = $l;
                }
            }
            if ($aberta) {
                $usadas[$aberta['id']] = true;
            }

            $linhas[] = self::linhaDoProvedor($p, $l ?? $aberta, $matricula, $parcelaDoCartao);
        }

        foreach ($locais as $l) {
            if (isset($usadas[$l['id']]) || ! $pago($l)) {
                continue;
            }
            $dia = substr((string) ($l['data_pagamento'] ?? ''), 0, 10);
            if ($dia < $inicio || $dia > $fim) {
                continue;
            }
            $integracao = (int) ($l['tipo_baixa_id'] ?? 0) === self::BAIXA_INTEGRACAO;
            $status = $integracao ? 'sem_recebimento' : 'manual';
            // Baixa de um provedor que não foi consultado (sem credencial ou fora do ar): não dá para conferir
            $citado = self::idCitado((string) ($l['observacoes'] ?? ''));
            $provedorDaBaixa = $citado !== null ? self::provedorDoId($citado) : null;
            if ($integracao && $consultados !== null && $provedorDaBaixa !== null && ! in_array($provedorDaBaixa, $consultados, true)) {
                $status = 'nao_conferido';
            }
            $linhas[] = self::linhaLocal($l, $status);
        }

        $linhas = array_values(array_filter($linhas, fn ($l) => $l['status'] !== 'ignorar'));
        foreach ($linhas as &$linha) {
            $linha += self::baixaDaLinha($linha['parcela']);
        }
        unset($linha);
        usort($linhas, fn ($a, $b) => strcmp((string) $b['data'], (string) $a['data']) ?: strcmp((string) $a['id'], (string) $b['id']));

        return ['resumo' => self::resumo($linhas), 'linhas' => $linhas];
    }

    /**
     * @param  array<string, mixed>  $p
     * @return array<string, mixed>
     */
    public static function normalizarAsaas(array $p): array
    {
        $status = strtoupper((string) ($p['status'] ?? ''));
        $valor = round((float) ($p['value'] ?? 0), 2);
        $liquido = isset($p['netValue']) && $p['netValue'] !== '' ? round((float) $p['netValue'], 2) : null;
        $credito = substr((string) ($p['creditDate'] ?? $p['estimatedCreditDate'] ?? ''), 0, 10) ?: null;

        return [
            'provedor' => 'asaas',
            'payment_id' => (string) ($p['id'] ?? ''),
            'status' => $status,
            'status_label' => AsaasPagamentoStatus::rotulo($status),
            'data' => substr((string) ($p['paymentDate'] ?? $p['confirmedDate'] ?? $p['clientPaymentDate'] ?? ''), 0, 10),
            'data_credito' => $credito,
            'a_receber' => $status === 'CONFIRMED',
            'em_dinheiro' => $status === 'RECEIVED_IN_CASH',
            'forma' => self::formaAsaas((string) ($p['billingType'] ?? ''), $status),
            'valor' => $valor,
            'liquido' => $liquido,
            'taxa' => $liquido === null ? null : max(0.0, round($valor - $liquido, 2)),
            'antecipado' => ! empty($p['anticipation']),
            'parcelamento' => $p['installment'] ?? null,
            'parcela_numero' => isset($p['installmentNumber']) ? (int) $p['installmentNumber'] : null,
            'descricao' => $p['description'] ?? null,
            'referencia' => $p['externalReference'] ?? null,
            'matricula_id' => self::matriculaDaReferencia((string) ($p['externalReference'] ?? '')),
            'tipo_referencia' => self::tipoReferencia((string) ($p['externalReference'] ?? '')),
            'invoice_url' => $p['invoiceUrl'] ?? null,
            'fora_do_periodo' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $p
     * @return array<string, mixed>
     */
    public static function normalizarMercadoPago(array $p): array
    {
        $valor = round((float) ($p['transaction_amount'] ?? 0), 2);
        $liquido = $p['transaction_details']['net_received_amount'] ?? null;
        $liquido = $liquido === null || $liquido === '' ? null : round((float) $liquido, 2);
        $taxas = 0.0;
        foreach ((array) ($p['fee_details'] ?? []) as $fee) {
            $taxas += (float) ($fee['amount'] ?? 0);
        }
        $taxa = $liquido !== null ? max(0.0, round($valor - $liquido, 2)) : ($taxas > 0 ? round($taxas, 2) : null);
        $liberacao = self::diaBrasilia($p['money_release_date'] ?? null);

        return [
            'provedor' => 'mercadopago',
            'payment_id' => (string) ($p['id'] ?? ''),
            'status' => strtoupper((string) ($p['status'] ?? '')),
            'status_label' => ($p['money_release_status'] ?? '') === 'released' ? 'Liberado' : 'Aprovado',
            'data' => self::diaBrasilia($p['date_approved'] ?? $p['date_created'] ?? null) ?? '',
            'data_credito' => $liberacao,
            'a_receber' => ($p['money_release_status'] ?? '') !== 'released' && $liberacao !== null && $liberacao > date('Y-m-d'),
            'em_dinheiro' => false,
            'forma' => self::formaMercadoPago((string) ($p['payment_type_id'] ?? ''), (string) ($p['payment_method_id'] ?? '')),
            'valor' => $valor,
            'liquido' => $liquido ?? ($taxa !== null ? round($valor - $taxa, 2) : null),
            'taxa' => $taxa,
            'antecipado' => false,
            'parcelamento' => null,
            'parcela_numero' => null,
            'descricao' => $p['description'] ?? null,
            'referencia' => $p['external_reference'] ?? null,
            'matricula_id' => self::matriculaDaReferencia((string) ($p['external_reference'] ?? '')),
            'tipo_referencia' => self::tipoReferencia((string) ($p['external_reference'] ?? '')),
            'invoice_url' => null,
            'fora_do_periodo' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $p
     * @param  array<string, mixed>|null  $l
     * @return array<string, mixed>
     */
    private static function linhaDoProvedor(array $p, ?array $l, ?int $matricula, callable $parcelaDoCartao): array
    {
        $nome = self::nomeProvedor($p['provedor']);
        $motivo = null;
        $nota = null;

        if ($l && (int) $l['status_pagamento_id'] !== 2) {
            $status = 'sem_baixa';
            $motivo = sprintf('O %s recebeu, mas a parcela #%d continua %s no Check-in.', $nome, $l['id'], (int) $l['status_pagamento_id'] === 3 ? 'atrasada' : 'em aberto');
        } elseif ($l) {
            $status = 'conciliado';
            $doCartao = $parcelaDoCartao($l, $p) && abs((float) $l['valor'] - (float) $p['valor']) >= 0.02;
            $liquidoLocal = isset($l['valor_liquido']) && $l['valor_liquido'] !== null && (float) $l['valor_liquido'] != 0.0 ? (float) $l['valor_liquido'] : null;
            if ($doCartao) {
                $nota = 'Parcela'.($p['parcela_numero'] ? ' '.$p['parcela_numero'] : '').' do cartão parcelado; no Check-in a parcela é o valor cheio.';
            } elseif (abs((float) $l['valor'] - (float) $p['valor']) >= 0.02) {
                $status = 'divergente';
                $motivo = sprintf('Valor de R$ %s no %s e R$ %s no Check-in.', self::brl($p['valor']), $nome, self::brl($l['valor']));
            } elseif ($liquidoLocal !== null && $p['liquido'] !== null && abs($liquidoLocal - (float) $p['liquido']) >= 0.02) {
                $status = 'divergente';
                $motivo = sprintf('Líquido de R$ %s no %s e R$ %s gravado no Check-in.', self::brl($p['liquido']), $nome, self::brl($liquidoLocal));
            } elseif ($liquidoLocal === null && $p['liquido'] !== null) {
                $nota = 'O líquido ainda não foi gravado na parcela.';
            }
            if ($p['fora_do_periodo']) {
                $nota = trim(sprintf('Recebido em %s no %s; a baixa no Check-in foi em %s.', self::dataBr($p['data']), $nome, self::dataBr($l['data_pagamento'])).' '.($nota ?? ''));
            }
        } elseif ($p['fora_do_periodo']) {
            // Consultado só para explicar uma parcela local: sem par, não entra na lista
            $status = 'ignorar';
        } elseif ($matricula && $p['tipo_referencia'] !== 'PAC') {
            $status = 'sem_baixa';
            $motivo = sprintf('Nenhuma parcela paga da matrícula #%d aponta para este pagamento.', $matricula);
        } else {
            $status = 'sem_vinculo';
            $motivo = match ($p['tipo_referencia']) {
                'PAC' => 'Pagamento de pacote.',
                'CTR' => 'Cobrança de contrato do sistema.',
                default => 'Cobrança sem matrícula ligada (criada direto no '.$nome.'?).',
            };
        }

        return [
            'id' => $p['provedor'].':'.$p['payment_id'],
            'origem' => 'provedor',
            'status' => $status,
            'motivo' => $motivo,
            'nota' => $nota,
            'provedor' => $p['provedor'],
            'provedor_nome' => $nome,
            'payment_id' => $p['payment_id'],
            'status_provedor' => $p['status'],
            'status_provedor_label' => $p['status_label'],
            'data' => $p['data'],
            'data_credito' => $p['data_credito'],
            'a_receber' => (bool) $p['a_receber'],
            'em_dinheiro' => (bool) $p['em_dinheiro'],
            'antecipado' => (bool) $p['antecipado'],
            'fora_do_periodo' => (bool) $p['fora_do_periodo'],
            'forma' => $p['forma'],
            'parcela_numero' => $p['parcela_numero'],
            'valor' => (float) $p['valor'],
            'taxa' => $p['taxa'],
            'liquido' => $p['liquido'],
            'descricao' => $p['descricao'],
            'invoice_url' => $p['invoice_url'],
            'matricula_id' => $l['matricula_id'] ?? $matricula,
            'aluno_nome' => $l['aluno_nome'] ?? null,
            'plano_nome' => $l['plano_nome'] ?? null,
            'parcela' => $l ? self::resumoParcela($l) : null,
            'pode_reprocessar' => $p['provedor'] === 'asaas' && $matricula !== null
                && ($status === 'sem_baixa' || ($status === 'divergente' && str_starts_with((string) $motivo, 'Líquido')) || $nota === 'O líquido ainda não foi gravado na parcela.'),
        ];
    }

    /**
     * @param  array<string, mixed>  $l
     * @return array<string, mixed>
     */
    private static function linhaLocal(array $l, string $status): array
    {
        return [
            'id' => 'checkin:'.$l['id'],
            'origem' => 'checkin',
            'status' => $status,
            'motivo' => match ($status) {
                'sem_recebimento' => 'Baixa automática no Check-in sem pagamento correspondente no provedor neste período.',
                'nao_conferido' => sprintf('Baixa pelo %s, que não pôde ser consultado. Não dá para conferir.', self::nomeProvedor(self::provedorDoId((string) self::idCitado((string) ($l['observacoes'] ?? ''))))),
                default => null,
            },
            'nota' => null,
            'provedor' => null,
            'provedor_nome' => null,
            'payment_id' => self::idCitado((string) ($l['observacoes'] ?? '')),
            'status_provedor' => null,
            'status_provedor_label' => null,
            'data' => substr((string) $l['data_pagamento'], 0, 10),
            'data_credito' => null,
            'a_receber' => false,
            'em_dinheiro' => false,
            'antecipado' => false,
            'fora_do_periodo' => false,
            'forma' => $l['forma_pagamento_nome'] ?? null,
            'valor' => (float) $l['valor'],
            'taxa' => null,
            'liquido' => null,
            'descricao' => null,
            'invoice_url' => null,
            'matricula_id' => (int) $l['matricula_id'],
            'aluno_nome' => $l['aluno_nome'] ?? null,
            'plano_nome' => $l['plano_nome'] ?? null,
            'parcela' => self::resumoParcela($l),
            'pode_reprocessar' => false,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $linhas
     * @return array<string, mixed>
     */
    private static function resumo(array $linhas): array
    {
        $contagem = array_fill_keys(['conciliado', 'divergente', 'sem_baixa', 'sem_recebimento', 'sem_vinculo', 'manual', 'nao_conferido'], 0);
        $total = ['quantidade' => 0, 'bruto' => 0.0, 'taxas' => 0.0, 'liquido' => 0.0, 'a_receber' => 0.0, 'sem_liquido' => 0];
        $porProvedor = [];
        $porForma = [];
        $checkin = ['integracao' => 0.0, 'manual' => 0.0, 'manual_quantidade' => 0];
        $manualPorForma = [];

        foreach ($linhas as $linha) {
            $contagem[$linha['status']]++;

            if ($linha['origem'] === 'checkin') {
                if ($linha['status'] === 'manual') {
                    $checkin['manual'] += $linha['valor'];
                    $checkin['manual_quantidade']++;
                    $forma = $linha['forma'] ?: 'Não informada';
                    $manualPorForma[$forma] ??= ['forma' => $forma, 'quantidade' => 0, 'valor' => 0.0];
                    $manualPorForma[$forma]['quantidade']++;
                    $manualPorForma[$forma]['valor'] += $linha['valor'];
                } else {
                    $checkin['integracao'] += $linha['valor'];
                }

                continue;
            }
            if ($linha['fora_do_periodo'] || $linha['em_dinheiro']) {
                continue;
            }

            $liquido = $linha['liquido'] ?? $linha['valor'];
            $taxa = $linha['taxa'] ?? 0.0;
            $total['quantidade']++;
            $total['bruto'] += $linha['valor'];
            $total['taxas'] += $taxa;
            $total['liquido'] += $liquido;
            if ($linha['liquido'] === null) {
                $total['sem_liquido']++;
            }
            if ($linha['a_receber']) {
                $total['a_receber'] += $liquido;
            }

            $prov = $linha['provedor'];
            $porProvedor[$prov] ??= ['provedor' => $prov, 'provedor_nome' => $linha['provedor_nome'], 'quantidade' => 0, 'bruto' => 0.0, 'taxas' => 0.0, 'liquido' => 0.0];
            $porProvedor[$prov]['quantidade']++;
            $porProvedor[$prov]['bruto'] += $linha['valor'];
            $porProvedor[$prov]['taxas'] += $taxa;
            $porProvedor[$prov]['liquido'] += $liquido;

            $forma = $linha['forma'] ?: 'Outros';
            $porForma[$forma] ??= ['forma' => $forma, 'quantidade' => 0, 'bruto' => 0.0, 'taxas' => 0.0];
            $porForma[$forma]['quantidade']++;
            $porForma[$forma]['bruto'] += $linha['valor'];
            $porForma[$forma]['taxas'] += $taxa;
        }

        // Baixas automáticas do período que casaram com o provedor (uma vez por parcela)
        $parcelas = [];
        foreach ($linhas as $linha) {
            $pc = $linha['parcela'];
            if ($linha['origem'] === 'provedor' && $pc && (int) $pc['status_pagamento_id'] === 2) {
                $parcelas[$pc['id']] = (float) $pc['valor'];
            }
        }
        $checkin['integracao'] += array_sum($parcelas);

        $arred = fn (array $a) => array_map(fn ($v) => is_float($v) ? round($v, 2) : $v, $a);
        $formas = array_values(array_map($arred, $porForma));
        usort($formas, fn ($a, $b) => $b['bruto'] <=> $a['bruto']);

        return [
            'contagem' => $contagem,
            'pendencias' => $contagem['divergente'] + $contagem['sem_baixa'] + $contagem['sem_recebimento'],
            'provedor' => $arred($total),
            'por_provedor' => array_values(array_map($arred, $porProvedor)),
            'por_forma' => $formas,
            'checkin' => $arred($checkin) + ['manual_por_forma' => array_values(array_map($arred, $manualPorForma))],
        ];
    }

    /**
     * Como a parcela foi baixada: pela integração com o provedor, manualmente (dinheiro, Pix direto,
     * maquininha, importação) ou ainda sem baixa.
     *
     * @param  array<string, mixed>|null  $parcela
     * @return array{baixa: string, baixa_nome: string}
     */
    private static function baixaDaLinha(?array $parcela): array
    {
        if (! $parcela || (int) $parcela['status_pagamento_id'] !== 2) {
            return ['baixa' => 'sem_baixa', 'baixa_nome' => 'Sem baixa'];
        }
        if ((int) $parcela['tipo_baixa_id'] === self::BAIXA_INTEGRACAO) {
            return ['baixa' => 'automatica', 'baixa_nome' => 'Baixa automática'];
        }
        $tipo = trim((string) ($parcela['tipo_baixa_nome'] ?? ''));

        return ['baixa' => 'manual', 'baixa_nome' => $tipo === '' || strcasecmp($tipo, 'Manual') === 0 ? 'Baixa manual' : 'Baixa por '.mb_strtolower($tipo)];
    }

    /**
     * @param  array<string, mixed>  $l
     * @return array<string, mixed>
     */
    private static function resumoParcela(array $l): array
    {
        return [
            'id' => (int) $l['id'],
            'valor' => (float) $l['valor'],
            'valor_liquido' => isset($l['valor_liquido']) ? (float) $l['valor_liquido'] : null,
            'acrescimo' => round((float) ($l['tarifa_cartao'] ?? 0), 2),
            'status_pagamento_id' => (int) $l['status_pagamento_id'],
            'data_pagamento' => $l['data_pagamento'] ? substr((string) $l['data_pagamento'], 0, 10) : null,
            'data_vencimento' => $l['data_vencimento'] ? substr((string) $l['data_vencimento'], 0, 10) : null,
            'forma' => $l['forma_pagamento_nome'] ?? null,
            'tipo_baixa_id' => isset($l['tipo_baixa_id']) ? (int) $l['tipo_baixa_id'] : null,
            'tipo_baixa_nome' => $l['tipo_baixa_nome'] ?? null,
        ];
    }

    /**
     * @return array{0: string, 1: string}|array{erro: string}
     */
    private function periodo(?string $inicio, ?string $fim): array
    {
        $valida = fn (?string $d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) === 1 && strtotime($d) !== false;
        $inicio = $valida($inicio) ? $inicio : date('Y-m-01');
        $fim = $valida($fim) ? $fim : date('Y-m-t');
        if ($inicio > $fim) {
            return ['erro' => 'A data final precisa ser igual ou posterior à inicial.'];
        }
        if ((strtotime($fim) - strtotime($inicio)) / 86400 > self::MAX_DIAS) {
            return ['erro' => 'Escolha um período de até 6 meses.'];
        }

        return [$inicio, $fim];
    }

    /**
     * @return list<string>
     */
    private function provedoresAtivos(int $tenantId): array
    {
        return array_keys($this->provedoresConectados($tenantId));
    }

    /**
     * Provedores com credencial da academia, ativos ou não: uma conta desativada pode ter recebido
     * pagamentos no período (ex.: academia que trocou o Mercado Pago pelo Asaas no meio do mês).
     *
     * @return array<string, bool> provedor => ativo
     */
    private function provedoresConectados(int $tenantId): array
    {
        $out = [];
        $rows = DB::table('tenant_payment_credentials')
            ->where('tenant_id', $tenantId)
            ->whereIn('provider', ['asaas', 'mercadopago'])
            ->get();
        foreach ($rows as $row) {
            $token = ($row->environment ?? '') === 'production' ? $row->access_token_prod : $row->access_token_test;
            if (trim((string) $token) === '') {
                continue;
            }
            $provedor = strtolower((string) $row->provider);
            $out[$provedor] = ($out[$provedor] ?? false) || in_array($row->is_active, [1, true, '1'], true);
        }
        ksort($out);

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buscarNoProvedor(int $tenantId, string $provedor, string $inicio, string $fim): array
    {
        $gw = $this->gatewayDoTenant($provedor, $tenantId);
        if ($provedor !== 'asaas') {
            return array_map([self::class, 'normalizarMercadoPago'], $gw->listarPagamentosAprovados($inicio, $fim));
        }

        $consultas = 0;
        $lista = [];
        foreach ($gw->listarPagamentosDoPeriodo($inicio, $fim) as $p) {
            // Antecipado: o líquido real (com a taxa de antecipação) está na antecipação, não no netValue
            if (($p['anticipated'] ?? false) === true && $consultas < 50) {
                $consultas++;
                try {
                    $p = AsaasPagamentoStatus::aplicarAntecipacao($p, $gw->obterAntecipacaoAtiva((string) $p['id']));
                } catch (Throwable) {
                    // segue com o netValue da cobrança
                }
            }
            $lista[] = self::normalizarAsaas($p);
        }

        return $lista;
    }

    /**
     * Consulta, um a um, os pagamentos citados nas parcelas "sem recebimento" para saber se o
     * provedor registrou o recebimento em outra data.
     *
     * @param  list<array<string, mixed>>  $linhas
     * @param  list<string>  $provedores
     * @return array{pagos: list<array<string, mixed>>, situacao: array<string, string>}
     */
    private function consultarCitados(int $tenantId, array $linhas, array $provedores): array
    {
        $pagos = [];
        $situacao = [];
        $consultas = 0;
        foreach ($linhas as $linha) {
            if ($linha['status'] !== 'sem_recebimento' || ! $linha['payment_id'] || $consultas >= self::MAX_CONSULTAS_AVULSAS) {
                continue;
            }
            $id = (string) $linha['payment_id'];
            $provedor = self::provedorDoId($id);
            if (! in_array($provedor, $provedores, true)) {
                continue;
            }
            $consultas++;
            try {
                $gw = $this->gatewayDoTenant($provedor, $tenantId);
                $bruto = $provedor === 'asaas' ? $gw->obterPagamento($id) : $gw->buscarPagamento($id);
            } catch (Throwable $e) {
                $situacao[$id] = sprintf('O %s não encontrou este pagamento (%s).', self::nomeProvedor($provedor), $e->getMessage());

                continue;
            }
            $p = $provedor === 'asaas' ? self::normalizarAsaas($bruto) : self::normalizarMercadoPago($bruto);
            $removida = $provedor === 'asaas' && ($bruto['deleted'] ?? false) === true;
            $pagoNoProvedor = ! $removida && ($provedor === 'asaas' ? AsaasPagamentoStatus::pago($p['status']) : $p['status'] === 'APPROVED');
            if ($pagoNoProvedor && $p['payment_id'] !== '') {
                $pagos[] = ['fora_do_periodo' => true] + $p;
            } else {
                $situacao[$id] = sprintf(
                    'O %s mostra este pagamento como %s, mas a parcela está paga no Check-in.',
                    self::nomeProvedor($provedor),
                    $removida ? 'removido' : self::rotuloStatus($provedor, $p['status']),
                );
            }
        }

        return ['pagos' => $pagos, 'situacao' => $situacao];
    }

    /**
     * Instância do provedor com a credencial da academia. O MercadoPagoService cai na credencial
     * global do .env quando não consegue ler a do tenant: aí listaria a conta do sistema.
     */
    private function gatewayDoTenant(string $provedor, int $tenantId): object
    {
        $gw = ($this->gateway)($provedor, $tenantId);
        if ($gw instanceof MercadoPagoService && ! $gw->usaCredencialDoTenant()) {
            throw new \RuntimeException('não foi possível ler a credencial do Mercado Pago desta academia');
        }

        return $gw;
    }

    public static function provedorDoId(string $id): string
    {
        return str_starts_with($id, 'pay_') ? 'asaas' : 'mercadopago';
    }

    private static function rotuloStatus(string $provedor, string $status): string
    {
        if ($provedor === 'asaas') {
            return match (strtoupper($status)) {
                'REFUNDED', 'REFUND_REQUESTED', 'REFUND_IN_PROGRESS' => 'estornado',
                'CHARGEBACK_REQUESTED', 'CHARGEBACK_DISPUTE', 'AWAITING_CHARGEBACK_REVERSAL' => 'em chargeback',
                'PENDING', 'OVERDUE' => 'em aberto ('.strtoupper($status).')',
                'CANCELLED', 'CANCELED' => 'cancelado',
                default => strtoupper($status),
            };
        }

        return match (strtolower($status)) {
            'refunded' => 'estornado',
            'charged_back' => 'em chargeback',
            'cancelled' => 'cancelado',
            'pending', 'in_process' => 'pendente',
            'rejected' => 'recusado',
            default => strtoupper($status),
        };
    }

    /**
     * Parcelas pagas no período (com folga para baixa em data diferente), as que citam algum dos
     * pagamentos e as abertas das matrículas que aparecem nas referências.
     *
     * @param  list<string>  $paymentIds
     * @param  list<int>  $matriculasRef
     * @return list<array<string, mixed>>
     */
    private function parcelasLocais(int $tenantId, string $inicio, string $fim, array $paymentIds, array $matriculasRef = []): array
    {
        // valor_liquido vem da migration SQL 071 (api/); banco sem ela segue sem comparar o líquido
        $liquido = Schema::hasColumn('pagamentos_plano', 'valor_liquido') ? 'pp.valor_liquido' : DB::raw('NULL as valor_liquido');
        // tarifa_cartao: acréscimo cobrado do aluno para cobrir a taxa do cartão (o valor da parcela já o inclui)
        $acrescimo = Schema::hasColumn('pagamentos_plano', 'tarifa_cartao') ? 'pp.tarifa_cartao' : DB::raw('NULL as tarifa_cartao');
        $base = fn () => DB::table('pagamentos_plano as pp')
            ->leftJoin('alunos as a', 'a.id', '=', 'pp.aluno_id')
            ->leftJoin('planos as p', 'p.id', '=', 'pp.plano_id')
            ->leftJoin('formas_pagamento as fp', 'fp.id', '=', 'pp.forma_pagamento_id')
            ->leftJoin('tipos_baixa as tb', 'tb.id', '=', 'pp.tipo_baixa_id')
            ->where('pp.tenant_id', $tenantId)
            ->select([
                'pp.id', 'pp.matricula_id', 'pp.valor', $liquido, $acrescimo, 'pp.status_pagamento_id', 'pp.data_pagamento',
                'pp.data_vencimento', 'pp.tipo_baixa_id', 'pp.observacoes', 'a.nome as aluno_nome', 'p.nome as plano_nome',
                'fp.nome as forma_pagamento_nome', 'tb.nome as tipo_baixa_nome',
            ]);

        $porId = [];
        $guardar = function ($rows) use (&$porId) {
            foreach ($rows as $r) {
                $porId[$r->id] = (array) $r;
            }
        };

        $guardar($base()->where('pp.status_pagamento_id', 2)
            ->whereBetween('pp.data_pagamento', [date('Y-m-d', strtotime($inicio.' -10 days')), date('Y-m-d', strtotime($fim.' +10 days'))])
            ->get());

        foreach (array_chunk(array_values(array_filter(array_unique($paymentIds))), 40) as $ids) {
            $guardar($base()->where(function ($q) use ($ids) {
                foreach ($ids as $id) {
                    $q->orWhere('pp.observacoes', 'like', '%'.$id.'%');
                }
            })->get());
        }

        // Abertas das matrículas dos pagamentos (referência MAT- ou Pix salvo): o aluno pagou e a parcela ficou sem baixa
        $matriculas = array_values(array_unique(array_merge(
            array_map('intval', $matriculasRef),
            array_values($this->matriculasDoPix($tenantId, $paymentIds)),
        )));
        if ($matriculas !== []) {
            $guardar($base()->whereIn('pp.status_pagamento_id', [1, 3])->whereIn('pp.matricula_id', $matriculas)->get());
        }

        return array_values($porId);
    }

    /**
     * @param  list<string>  $paymentIds
     * @return array<string, int>
     */
    private function matriculasDoPix(int $tenantId, array $paymentIds): array
    {
        $paymentIds = array_values(array_filter($paymentIds));
        if ($paymentIds === []) {
            return [];
        }

        return DB::table('pagamentos_pix')
            ->where('tenant_id', $tenantId)
            ->whereIn('payment_id', $paymentIds)
            ->pluck('matricula_id', 'payment_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    public static function matriculaDaReferencia(string $ref): ?int
    {
        return preg_match('/^MAT-(\d+)-/', $ref, $m) === 1 ? (int) $m[1] : null;
    }

    private static function tipoReferencia(string $ref): ?string
    {
        return preg_match('/^(MAT|PAC|CTR)-/', $ref, $m) === 1 ? $m[1] : null;
    }

    private static function idCitado(string $obs): ?string
    {
        if (preg_match('/\b(pay_[A-Za-z0-9]+)/', $obs, $m) === 1) {
            return $m[1];
        }
        if (preg_match('/(?:Payment #|ID: )(\d{6,})/', $obs, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    private static function formaAsaas(string $billingType, string $status): ?string
    {
        if ($status === 'RECEIVED_IN_CASH') {
            return 'Dinheiro';
        }

        return match (strtoupper($billingType)) {
            'PIX' => 'Pix',
            'CREDIT_CARD' => 'Cartão de crédito',
            'DEBIT_CARD' => 'Cartão de débito',
            'BOLETO' => 'Boleto',
            default => null,
        };
    }

    private static function formaMercadoPago(string $tipo, string $metodo): ?string
    {
        if ($metodo === 'pix' || $tipo === 'bank_transfer') {
            return 'Pix';
        }

        return match ($tipo) {
            'credit_card' => 'Cartão de crédito',
            'debit_card', 'prepaid_card' => 'Cartão de débito',
            'ticket' => 'Boleto',
            'account_money' => 'Saldo Mercado Pago',
            default => null,
        };
    }

    public static function nomeProvedor(?string $provedor): string
    {
        return $provedor === 'asaas' ? 'Asaas' : 'Mercado Pago';
    }

    private static function diaBrasilia(mixed $iso): ?string
    {
        if (! is_string($iso) || $iso === '') {
            return null;
        }
        try {
            return Carbon::parse($iso)->setTimezone('America/Sao_Paulo')->format('Y-m-d');
        } catch (Throwable) {
            return substr($iso, 0, 10);
        }
    }

    private static function brl(mixed $v): string
    {
        return number_format((float) $v, 2, ',', '.');
    }

    private static function dataBr(mixed $iso): string
    {
        return preg_match('/^(\d{4})-(\d{2})-(\d{2})/', (string) $iso, $m) === 1 ? $m[3].'/'.$m[2].'/'.$m[1] : '—';
    }
}
