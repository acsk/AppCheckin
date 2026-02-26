<?php

declare(strict_types=1);

namespace Controllers;

/**
 * PreapprovalController - Gerencia assinaturas (preapproval) no formato Mercado Pago
 *
 * Endpoints:
 *   POST   /api/preapproval          - Criar assinatura
 *   GET    /api/preapproval/{id}     - Consultar assinatura
 *   GET    /api/preapproval          - Listar assinaturas
 *   PUT    /api/preapproval/{id}     - Atualizar assinatura (pause/cancel)
 *   POST   /api/preapproval/{id}/pay - Gerar pagamento da assinatura (simula cobrança recorrente)
 */
class PreapprovalController
{
    private const STATUSES = [
        'pending',
        'authorized',
        'paused',
        'cancelled',
    ];

    /**
     * POST /api/preapproval - Criar assinatura (preapproval)
     * Retorna exatamente no formato Mercado Pago
     */
    public function create(): void
    {
        $input = getJsonInput();

        // Se veio com preapproval_plan_id, buscar dados do plano
        $planId = $input['preapproval_plan_id'] ?? null;
        $plan = null;
        if ($planId) {
            $plans = readJsonFile('preapproval_plans.json');
            $plan = $plans[$planId] ?? null;
        }

        // Validações — reason pode vir do plano
        $reason = $input['reason'] ?? ($plan['reason'] ?? null);
        if (empty($reason)) {
            jsonResponse(['error' => 'Campo "reason" é obrigatório (descrição do plano).'], 422);
            return;
        }

        // auto_recurring pode vir do plano
        $autoRecurring = $input['auto_recurring'] ?? ($plan['auto_recurring'] ?? []);
        $transactionAmount = round((float) ($autoRecurring['transaction_amount'] ?? 0), 2);

        if ($transactionAmount <= 0 && !$plan) {
            jsonResponse(['error' => 'Campo "auto_recurring.transaction_amount" é obrigatório.'], 422);
            return;
        }

        $payerEmail = $input['payer_email'] ?? $input['payer']['email'] ?? null;
        if (empty($payerEmail)) {
            jsonResponse(['error' => 'Campo "payer_email" é obrigatório.'], 422);
            return;
        }

        // Gerar ID numérico como Mercado Pago real (para ctype_digit no webhook handler do app)
        $preapprovalId = (string) random_int(100000000000, 999999999999);

        $currencyId = $autoRecurring['currency_id'] ?? 'BRL';
        $frequency = (int) ($autoRecurring['frequency'] ?? 1);
        $frequencyType = $autoRecurring['frequency_type'] ?? 'months';
        $startDate = $autoRecurring['start_date'] ?? date('Y-m-d\TH:i:s.000-04:00');
        $endDate = $autoRecurring['end_date'] ?? date('Y-m-d\TH:i:s.000-04:00', strtotime('+1 year'));
        $repetitions = $autoRecurring['repetitions'] ?? null;
        $freeTrial = $autoRecurring['free_trial'] ?? null;

        // Status inicial
        $status = $input['_simulate_status'] ?? $input['status'] ?? 'authorized';
        if (!in_array($status, self::STATUSES)) {
            $status = 'authorized';
        }

        $baseUrl = $this->getBaseUrl();

        // Gerar payment_url no formato MP para assinatura
        $initPoint = $baseUrl . '/subscription/checkout/' . $preapprovalId;

        $now = date('Y-m-d\TH:i:s.000-04:00');

        // Objeto no formato real do Mercado Pago
        $preapproval = [
            'id' => $preapprovalId,
            'preapproval_plan_id' => $planId,
            'payer_id' => random_int(100000000, 999999999),
            'payer_email' => $payerEmail,
            'back_url' => $input['back_url'] ?? $input['back_urls']['success'] ?? '',
            'collector_id' => random_int(100000000, 999999999),
            'application_id' => $plan['application_id'] ?? random_int(1000000000, 9999999999),
            'status' => $status,
            'reason' => $reason,
            'external_reference' => $input['external_reference'] ?? '',
            'date_created' => $now,
            'last_modified' => $now,
            'init_point' => $initPoint,
            'sandbox_init_point' => $initPoint,
            'auto_recurring' => [
                'frequency' => $frequency,
                'frequency_type' => $frequencyType,
                'transaction_amount' => $transactionAmount,
                'currency_id' => $currencyId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'repetitions' => $repetitions,
                'free_trial' => $freeTrial,
            ],
            'payment_method_id' => $input['payment_method_id'] ?? null,
            'first_invoice_offset' => $input['first_invoice_offset'] ?? null,
            'subscription_id' => $preapprovalId, // auto-referência para facilitar lookups
            'notification_url' => $input['notification_url'] ?? null,
            'metadata' => $input['metadata'] ?? [],
            'live_mode' => false,
            'next_payment_date' => date('Y-m-d\TH:i:s.000-04:00', strtotime("+{$frequency} {$frequencyType}")),
            'summarized' => [
                'quotas' => $repetitions,
                'charged_quantity' => 0,
                'pending_charge_quantity' => $repetitions,
                'charged_amount' => 0,
                'pending_charge_amount' => $repetitions ? $repetitions * $transactionAmount : null,
                'semaphore' => 'green',
                'last_charged_date' => null,
                'last_charged_amount' => null,
            ],
        ];

        // Salvar assinatura
        $preapprovals = readJsonFile('preapprovals.json');
        $preapprovals[$preapprovalId] = $preapproval;
        writeJsonFile('preapprovals.json', $preapprovals);

        logActivity('preapproval.created', [
            'preapproval_id' => $preapprovalId,
            'reason' => $input['reason'],
            'amount' => $transactionAmount,
            'status' => $status,
        ]);

        // Disparar webhook
        $this->dispatchWebhook('subscription_preapproval', $preapproval);

        jsonResponse($preapproval, 201);
    }

    /**
     * GET /api/preapproval/{id} - Consultar assinatura
     */
    public function show(string $id): void
    {
        $preapprovals = readJsonFile('preapprovals.json');

        if (!isset($preapprovals[$id])) {
            jsonResponse(['error' => 'Assinatura não encontrada.', 'id' => $id], 404);
            return;
        }

        jsonResponse($preapprovals[$id]);
    }

    /**
     * GET /api/preapproval - Listar assinaturas
     */
    public function list(): void
    {
        $preapprovals = readJsonFile('preapprovals.json');

        $status = $_GET['status'] ?? null;
        $payerEmail = $_GET['payer_email'] ?? null;
        $limit = (int) ($_GET['limit'] ?? 50);
        $offset = (int) ($_GET['offset'] ?? 0);

        $filtered = array_values($preapprovals);

        if ($status) {
            $filtered = array_filter($filtered, fn($p) => $p['status'] === $status);
        }
        if ($payerEmail) {
            $filtered = array_filter($filtered, fn($p) => $p['payer_email'] === $payerEmail);
        }

        usort($filtered, fn($a, $b) => strcmp($b['date_created'], $a['date_created']));

        $total = count($filtered);
        $filtered = array_slice($filtered, $offset, $limit);

        jsonResponse([
            'results' => array_values($filtered),
            'paging' => [
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
            ],
        ]);
    }

    /**
     * PUT /api/preapproval/{id} - Atualizar assinatura (pause, cancel, reactivate)
     */
    public function update(string $id): void
    {
        $input = getJsonInput();
        $preapprovals = readJsonFile('preapprovals.json');

        if (!isset($preapprovals[$id])) {
            jsonResponse(['error' => 'Assinatura não encontrada.'], 404);
            return;
        }

        $preapproval = $preapprovals[$id];

        // Atualizar status se fornecido
        if (!empty($input['status']) && in_array($input['status'], self::STATUSES)) {
            $preapproval['status'] = $input['status'];
        }

        // Atualizar reason se fornecido
        if (!empty($input['reason'])) {
            $preapproval['reason'] = $input['reason'];
        }

        // Atualizar auto_recurring se fornecido
        if (!empty($input['auto_recurring'])) {
            $preapproval['auto_recurring'] = array_merge(
                $preapproval['auto_recurring'],
                $input['auto_recurring']
            );
        }

        // Atualizar external_reference se fornecido
        if (isset($input['external_reference'])) {
            $preapproval['external_reference'] = $input['external_reference'];
        }

        $preapproval['last_modified'] = date('Y-m-d\TH:i:s.000-04:00');

        $preapprovals[$id] = $preapproval;
        writeJsonFile('preapprovals.json', $preapprovals);

        logActivity('preapproval.updated', [
            'preapproval_id' => $id,
            'status' => $preapproval['status'],
        ]);

        $this->dispatchWebhook('subscription_preapproval', $preapproval);

        jsonResponse($preapproval);
    }

    /**
     * POST /api/preapproval/{id}/pay - Gerar pagamento a partir da assinatura
     * Simula a cobrança recorrente. O pagamento gerado inclui "subscription_id".
     */
    public function generatePayment(string $id): void
    {
        $input = getJsonInput();
        $preapprovals = readJsonFile('preapprovals.json');

        if (!isset($preapprovals[$id])) {
            jsonResponse(['error' => 'Assinatura não encontrada.'], 404);
            return;
        }

        $preapproval = $preapprovals[$id];

        // Em modo simulador, permitir pagamento mesmo se status não for 'authorized'
        // Se a assinatura estiver pending, ativar automaticamente ao gerar pagamento
        if ($preapproval['status'] === 'pending') {
            $preapproval['status'] = 'authorized';
            $preapprovals[$id] = $preapproval;
            writeJsonFile('preapprovals.json', $preapprovals);
        }

        // Resolver status do pagamento
        $simulateStatus = $input['_simulate_status'] ?? null;
        $status = $simulateStatus && in_array($simulateStatus, [
            'approved', 'rejected', 'pending', 'in_process', 'cancelled', 'error',
        ]) ? $simulateStatus : 'approved';

        $autoRecurring = $preapproval['auto_recurring'];
        $amount = round((float) ($input['amount'] ?? $autoRecurring['transaction_amount']), 2);
        $now = date('Y-m-d\TH:i:s.vP');
        $paymentId = random_int(10000000000, 99999999999); // ID numérico como MP

        $statusDetail = match ($status) {
            'approved' => 'accredited',
            'rejected' => 'cc_rejected_insufficient_amount',
            'pending' => 'pending_waiting_payment',
            'in_process' => 'pending_review_manual',
            'cancelled' => 'expired',
            'error' => 'internal_server_error',
            default => 'unknown',
        };

        // Pagamento no formato MP **com subscription_id**
        $payment = [
            'id' => $paymentId,
            'date_created' => $now,
            'date_approved' => $status === 'approved' ? $now : null,
            'date_last_updated' => $now,
            'money_release_date' => $status === 'approved' ? date('Y-m-d\TH:i:s.vP', strtotime('+14 days')) : null,
            'subscription_id' => $preapproval['id'],  // 🎯 CAMPO CHAVE
            'preapproval_id' => $preapproval['id'],   // alias para conveniência
            'issuer_id' => (string) random_int(1, 999),
            'payment_method_id' => $input['payment_method_id'] ?? 'account_money',
            'payment_type_id' => $input['payment_type_id'] ?? 'credit_card',
            'status' => $status,
            'status_detail' => $statusDetail,
            'currency_id' => $autoRecurring['currency_id'] ?? 'BRL',
            'description' => $preapproval['reason'],
            'collector_id' => $preapproval['collector_id'],
            'payer' => [
                'id' => $preapproval['payer_id'],
                'email' => $preapproval['payer_email'],
                'identification' => [
                    'type' => $input['payer']['identification']['type'] ?? 'CPF',
                    'number' => $input['payer']['identification']['number'] ?? '00000000000',
                ],
                'type' => 'customer',
            ],
            'transaction_amount' => $amount,
            'transaction_amount_refunded' => 0,
            'coupon_amount' => 0,
            'transaction_details' => [
                'net_received_amount' => round($amount * 0.955, 2), // ~4.5% taxa MP
                'total_paid_amount' => $amount,
                'overpaid_amount' => 0,
                'installment_amount' => $amount,
            ],
            'installments' => 1,
            'captured' => $status === 'approved',
            'live_mode' => false,
            'external_reference' => $preapproval['external_reference'],
            'metadata' => array_merge($preapproval['metadata'] ?? [], [
                'preapproval_id' => $preapproval['id'],
            ]),
            'additional_info' => [
                'items' => [[
                    'id' => $preapproval['id'],
                    'title' => $preapproval['reason'],
                    'quantity' => '1',
                    'unit_price' => (string) $amount,
                ]],
            ],
        ];

        // Salvar pagamento
        $payments = readJsonFile('payments.json');
        $payments[(string) $paymentId] = $payment;
        writeJsonFile('payments.json', $payments);

        // Atualizar summarized da assinatura
        $preapproval['summarized']['charged_quantity'] += ($status === 'approved' ? 1 : 0);
        $preapproval['summarized']['charged_amount'] += ($status === 'approved' ? $amount : 0);
        if ($preapproval['summarized']['pending_charge_quantity'] !== null) {
            $preapproval['summarized']['pending_charge_quantity'] = max(
                0,
                $preapproval['summarized']['pending_charge_quantity'] - 1
            );
        }
        $preapproval['summarized']['last_charged_date'] = $status === 'approved' ? $now : $preapproval['summarized']['last_charged_date'];
        $preapproval['summarized']['last_charged_amount'] = $status === 'approved' ? $amount : $preapproval['summarized']['last_charged_amount'];

        // Calcular próximo pagamento
        $freq = $autoRecurring['frequency'];
        $freqType = $autoRecurring['frequency_type'];
        $preapproval['next_payment_date'] = date('Y-m-d\TH:i:s.000-04:00', strtotime("+{$freq} {$freqType}"));
        $preapproval['last_modified'] = date('Y-m-d\TH:i:s.000-04:00');

        $preapprovals[$id] = $preapproval;
        writeJsonFile('preapprovals.json', $preapprovals);

        logActivity('preapproval.payment_generated', [
            'preapproval_id' => $id,
            'payment_id' => $paymentId,
            'amount' => $amount,
            'status' => $status,
            'subscription_id' => $preapproval['id'],
        ]);

        // Disparar webhook do pagamento (com subscription_id presente)
        $this->dispatchWebhook('payment', $payment);

        jsonResponse($payment, 201);
    }

    // ============================================================
    // Métodos de Plano (preapproval_plan)
    // ============================================================

    /**
     * POST /preapproval_plan - Criar plano de assinatura
     * Retorna no formato Mercado Pago (subscription plan)
     */
    public function createPlan(): void
    {
        $input = getJsonInput();

        if (empty($input['reason'])) {
            jsonResponse(['error' => 'Campo "reason" é obrigatório.'], 422);
            return;
        }

        $planId = bin2hex(random_bytes(16));
        $autoRecurring = $input['auto_recurring'] ?? [];
        $now = date('Y-m-d\TH:i:s.000-04:00');

        $plan = [
            'id' => $planId,
            'status' => 'active',
            'reason' => $input['reason'],
            'date_created' => $now,
            'last_modified' => $now,
            'auto_recurring' => [
                'frequency' => (int) ($autoRecurring['frequency'] ?? 1),
                'frequency_type' => $autoRecurring['frequency_type'] ?? 'months',
                'transaction_amount' => isset($autoRecurring['transaction_amount'])
                    ? round((float) $autoRecurring['transaction_amount'], 2)
                    : null,
                'currency_id' => $autoRecurring['currency_id'] ?? 'BRL',
                'repetitions' => $autoRecurring['repetitions'] ?? null,
                'free_trial' => $autoRecurring['free_trial'] ?? null,
                'billing_day' => $autoRecurring['billing_day'] ?? null,
                'billing_day_proportional' => $autoRecurring['billing_day_proportional'] ?? null,
            ],
            'back_url' => $input['back_url'] ?? '',
            'external_reference' => $input['external_reference'] ?? '',
            'payment_methods_allowed' => $input['payment_methods_allowed'] ?? null,
            'notification_url' => $input['notification_url'] ?? null,
            'application_id' => random_int(1000000000, 9999999999),
            'collector_id' => random_int(100000000, 999999999),
            'init_point' => $this->getBaseUrl() . '/subscription/checkout/plan/' . $planId,
            'sandbox_init_point' => $this->getBaseUrl() . '/subscription/checkout/plan/' . $planId,
            'live_mode' => false,
        ];

        // Salvar plano
        $plans = readJsonFile('preapproval_plans.json');
        $plans[$planId] = $plan;
        writeJsonFile('preapproval_plans.json', $plans);

        logActivity('preapproval_plan.created', [
            'plan_id' => $planId,
            'reason' => $input['reason'],
        ]);

        jsonResponse($plan, 201);
    }

    /**
     * GET /preapproval_plan/{id} - Consultar plano
     */
    public function showPlan(string $id): void
    {
        $plans = readJsonFile('preapproval_plans.json');

        if (!isset($plans[$id])) {
            jsonResponse(['error' => 'Plano não encontrado.', 'id' => $id], 404);
            return;
        }

        jsonResponse($plans[$id]);
    }

    // ============================================================
    // Recorrência — buscar por external_reference e gerar cobrança
    // ============================================================

    /**
     * GET /api/recurring/search?external_reference=MAT-XXX
     * Busca assinatura pelo external_reference
     */
    public function searchByExternalReference(): void
    {
        $extRef = $_GET['external_reference'] ?? '';
        if (empty($extRef)) {
            jsonResponse(['error' => 'Parâmetro "external_reference" é obrigatório.'], 422);
            return;
        }

        $preapprovals = readJsonFile('preapprovals.json');
        $found = null;

        foreach ($preapprovals as $p) {
            if (($p['external_reference'] ?? '') === $extRef) {
                $found = $p;
                break;
            }
        }

        if (!$found) {
            jsonResponse(['error' => 'Nenhuma assinatura encontrada com external_reference: ' . $extRef], 404);
            return;
        }

        jsonResponse(['subscription' => $found]);
    }

    /**
     * POST /api/recurring/charge
     * Gera cobrança recorrente e despacha webhook.
     * Body: { preapproval_id, _simulate_status, payment_method_id }
     */
    public function chargeRecurring(): void
    {
        $input = getJsonInput();
        $preapprovalId = $input['preapproval_id'] ?? '';

        if (empty($preapprovalId)) {
            jsonResponse(['error' => 'Campo "preapproval_id" é obrigatório.'], 422);
            return;
        }

        $preapprovals = readJsonFile('preapprovals.json');

        if (!isset($preapprovals[$preapprovalId])) {
            jsonResponse(['error' => 'Assinatura não encontrada.'], 404);
            return;
        }

        $preapproval = $preapprovals[$preapprovalId];

        // Auto-upgrade pending → authorized
        if ($preapproval['status'] === 'pending') {
            $preapproval['status'] = 'authorized';
        }

        // Resolver status do pagamento
        $simulateStatus = $input['_simulate_status'] ?? 'approved';
        $status = in_array($simulateStatus, [
            'approved', 'rejected', 'pending', 'in_process', 'cancelled', 'error',
        ]) ? $simulateStatus : 'approved';

        $autoRecurring = $preapproval['auto_recurring'];
        $amount = round((float) ($autoRecurring['transaction_amount']), 2);
        $now = date('Y-m-d\TH:i:s.vP');
        $paymentId = random_int(10000000000, 99999999999);

        $statusDetail = match ($status) {
            'approved' => 'accredited',
            'rejected' => 'cc_rejected_insufficient_amount',
            'pending' => 'pending_waiting_payment',
            'in_process' => 'pending_review_manual',
            'cancelled' => 'expired',
            'error' => 'internal_server_error',
            default => 'unknown',
        };

        $paymentMethodId = $input['payment_method_id'] ?? 'account_money';
        $paymentTypeMap = [
            'visa' => 'credit_card', 'master' => 'credit_card', 'elo' => 'credit_card',
            'amex' => 'credit_card', 'hipercard' => 'credit_card',
            'pix' => 'bank_transfer', 'account_money' => 'account_money',
        ];

        $payment = [
            'id' => $paymentId,
            'date_created' => $now,
            'date_approved' => $status === 'approved' ? $now : null,
            'date_last_updated' => $now,
            'money_release_date' => $status === 'approved' ? date('Y-m-d\TH:i:s.vP', strtotime('+14 days')) : null,
            'subscription_id' => $preapproval['id'],
            'preapproval_id' => $preapproval['id'],
            'issuer_id' => (string) random_int(1, 999),
            'payment_method_id' => $paymentMethodId,
            'payment_type_id' => $paymentTypeMap[$paymentMethodId] ?? 'credit_card',
            'status' => $status,
            'status_detail' => $statusDetail,
            'currency_id' => $autoRecurring['currency_id'] ?? 'BRL',
            'description' => $preapproval['reason'] . ' (recorrência automática)',
            'collector_id' => $preapproval['collector_id'],
            'payer' => [
                'id' => $preapproval['payer_id'],
                'email' => $preapproval['payer_email'],
                'identification' => ['type' => 'CPF', 'number' => '00000000000'],
                'type' => 'customer',
            ],
            'transaction_amount' => $amount,
            'transaction_amount_refunded' => 0,
            'coupon_amount' => 0,
            'transaction_details' => [
                'net_received_amount' => round($amount * 0.955, 2),
                'total_paid_amount' => $amount,
                'overpaid_amount' => 0,
                'installment_amount' => $amount,
            ],
            'installments' => 1,
            'captured' => $status === 'approved',
            'live_mode' => false,
            'external_reference' => $preapproval['external_reference'],
            'metadata' => array_merge($preapproval['metadata'] ?? [], [
                'preapproval_id' => $preapproval['id'],
                'recurring_charge' => true,
            ]),
            'additional_info' => [
                'items' => [[
                    'id' => $preapproval['id'],
                    'title' => $preapproval['reason'],
                    'quantity' => '1',
                    'unit_price' => (string) $amount,
                ]],
            ],
        ];

        // Salvar pagamento
        $payments = readJsonFile('payments.json');
        $payments[(string) $paymentId] = $payment;
        writeJsonFile('payments.json', $payments);

        // Atualizar summarized da assinatura
        $preapproval['summarized']['charged_quantity'] += ($status === 'approved' ? 1 : 0);
        $preapproval['summarized']['charged_amount'] += ($status === 'approved' ? $amount : 0);
        if ($preapproval['summarized']['pending_charge_quantity'] !== null) {
            $preapproval['summarized']['pending_charge_quantity'] = max(
                0, $preapproval['summarized']['pending_charge_quantity'] - 1
            );
        }
        $preapproval['summarized']['last_charged_date'] = $status === 'approved' ? $now : $preapproval['summarized']['last_charged_date'];
        $preapproval['summarized']['last_charged_amount'] = $status === 'approved' ? $amount : $preapproval['summarized']['last_charged_amount'];

        // Calcular próximo pagamento
        $freq = $autoRecurring['frequency'];
        $freqType = $autoRecurring['frequency_type'];
        $preapproval['next_payment_date'] = date('Y-m-d\TH:i:s.000-04:00', strtotime("+{$freq} {$freqType}"));
        $preapproval['last_modified'] = date('Y-m-d\TH:i:s.000-04:00');

        $preapprovals[$preapprovalId] = $preapproval;
        writeJsonFile('preapprovals.json', $preapprovals);

        logActivity('recurring.charge', [
            'preapproval_id' => $preapprovalId,
            'payment_id' => $paymentId,
            'amount' => $amount,
            'status' => $status,
            'external_reference' => $preapproval['external_reference'],
        ]);

        // Disparar webhook do pagamento
        $this->dispatchWebhook('payment', $payment);

        // Pegar último log do webhook para retornar ao frontend
        $webhookLogs = readJsonFile('webhook_logs.json');
        $lastLog = !empty($webhookLogs) ? end($webhookLogs) : null;

        jsonResponse([
            'payment' => $payment,
            'subscription' => $preapproval,
            'webhook_sent' => $lastLog && $lastLog['resource_id'] === (string) $paymentId,
            'webhook_log' => $lastLog,
        ], 201);
    }

    // ============================================================
    // Métodos privados
    // ============================================================

    private function dispatchWebhook(string $event, array $data): void
    {
        $webhooks = readJsonFile('webhooks.json');
        $notificationUrl = $data['notification_url'] ?? null;

        // Normalizar localhost→host.docker.internal para envio dentro do Docker
        $normalizeUrl = fn(string $u): string => str_replace('://localhost', '://host.docker.internal', $u);
        $urls = [];
        foreach ($webhooks as $wh) {
            if (!empty($wh['url']) && ($wh['active'] ?? true)) {
                $events = $wh['events'] ?? ['*'];
                if (in_array('*', $events) || in_array($event, $events)) {
                    $normalized = $normalizeUrl($wh['url']);
                    if (!in_array($normalized, $urls)) {
                        $urls[] = $normalized;
                    }
                }
            }
        }

        if ($notificationUrl) {
            $normalized = $normalizeUrl($notificationUrl);
            if (!in_array($normalized, $urls)) {
                $urls[] = $normalized;
            }
        }

        if (empty($urls)) {
            return;
        }

        // Formato do webhook do Mercado Pago
        $isSubscription = str_starts_with($event, 'subscription');

        // Determinar action correta baseada no evento
        if ($isSubscription) {
            $type = 'subscription_preapproval';
            $action = match(true) {
                str_contains($event, 'updated') || str_contains($event, 'paused') || str_contains($event, 'cancelled') => 'updated',
                default => 'created',
            };
        } else {
            $type = 'payment';
            $action = str_contains($event, 'updated') ? 'payment.updated' : 'payment.created';
        }

        $payload = [
            'id' => random_int(10000000000, 99999999999),
            'live_mode' => false,
            'type' => $type,
            'date_created' => date('Y-m-d\TH:i:s.000-04:00'),
            'user_id' => $data['collector_id'] ?? random_int(100000000, 999999999),
            'api_version' => 'v1',
            'action' => $action,
            'data' => [
                'id' => (string) $data['id'],
            ],
        ];

        foreach ($urls as $url) {
            $this->sendWebhook($url, $payload, $event);
        }
    }

    private function sendWebhook(string $url, array $payload, string $event): void
    {
        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $signature = hash_hmac('sha256', $jsonPayload, 'gateway_simulator_secret');

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-Gateway-Event: ' . $event,
                'X-Gateway-Signature: ' . $signature,
                'X-Gateway-Delivery: ' . generateTransactionId('dlv'),
                'User-Agent: MercadoPago/WebhookSimulator/1.0',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $webhookLogs = readJsonFile('webhook_logs.json');
        $webhookLogs[] = [
            'id' => generateTransactionId('whl'),
            'url' => $url,
            'event' => $event,
            'resource_id' => (string) ($payload['data']['id'] ?? ''),
            'http_status' => $httpCode,
            'success' => $httpCode >= 200 && $httpCode < 300,
            'error' => $error ?: null,
            'response_body' => substr((string) $response, 0, 500),
            'sent_at' => date('Y-m-d\TH:i:s.vP'),
        ];

        $webhookLogs = array_slice($webhookLogs, -200);
        writeJsonFile('webhook_logs.json', $webhookLogs);
    }

    private function getBaseUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8085';
        // host.docker.internal só funciona dentro de containers, não no navegador
        $host = str_replace('host.docker.internal', 'localhost', $host);
        return $scheme . '://' . $host;
    }
}
