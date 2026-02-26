<?php

declare(strict_types=1);

namespace Controllers;

/**
 * PaymentController - Gerencia criação e operações de pagamentos
 */
class PaymentController
{
    private const STATUSES = [
        'approved',
        'rejected',
        'pending',
        'in_process',
        'cancelled',
        'refunded',
        'charged_back',
        'error',
    ];

    private const PAYMENT_METHODS = [
        'credit_card',
        'debit_card',
        'pix',
        'boleto',
        'bank_transfer',
    ];

    private const CARD_BRANDS = [
        'visa',
        'mastercard',
        'elo',
        'amex',
        'hipercard',
    ];

    /**
     * POST /api/preferences - Criar preferência de pagamento (como Mercado Pago)
     * Retorna payment_url para redirecionar o cliente ao checkout
     */
    public function createPreference(): void
    {
        $input = getJsonInput();

        // Validações mínimas - aceitar items, transaction_amount ou amount
        $rawAmount = $input['transaction_amount'] ?? $input['amount'] ?? null;
        if (empty($input['items']) && (empty($rawAmount) || !is_numeric($rawAmount))) {
            jsonResponse(['error' => 'Envie "items" ou "transaction_amount" com o valor.'], 422);
            return;
        }

        // Calcular valor total (por items ou transaction_amount/amount direto)
        $amount = 0;
        $items = $input['items'] ?? [];
        if (!empty($items)) {
            foreach ($items as $item) {
                $amount += ($item['unit_price'] ?? 0) * ($item['quantity'] ?? 1);
            }
        } else {
            $amount = round((float) $rawAmount, 2);
            $items = [[
                'title' => $input['description'] ?? 'Pagamento',
                'quantity' => 1,
                'unit_price' => $amount,
            ]];
        }
        $amount = round($amount, 2);

        $preferenceId = (string) random_int(100000000000, 999999999999);
        $paymentId = (string) random_int(100000000000, 999999999999);
        $gatewayAssinaturaId = (string) random_int(100000000000, 999999999999);
        $baseUrl = $this->getBaseUrl();

        $preference = [
            'id' => $preferenceId,
            'payment_id' => $paymentId,
            'gateway_assinatura_id' => $gatewayAssinaturaId,
            'external_reference' => $input['external_reference'] ?? null,
            'pacote_contrato_id' => $input['pacote_contrato_id'] ?? null,
            'items' => $items,
            'amount' => $amount,
            'currency' => $input['currency'] ?? 'BRL',
            'payer' => [
                'name' => $input['payer']['name'] ?? null,
                'email' => $input['payer']['email'] ?? null,
                'document' => $input['payer']['document'] ?? null,
            ],
            'description' => $input['description'] ?? ($items[0]['title'] ?? ''),
            'notification_url' => $input['notification_url'] ?? null,
            'back_urls' => $input['back_urls'] ?? [
                'success' => $input['back_url_success'] ?? null,
                'failure' => $input['back_url_failure'] ?? null,
                'pending' => $input['back_url_pending'] ?? null,
            ],
            'auto_return' => $input['auto_return'] ?? 'approved',
            'metadata' => $input['metadata'] ?? [],
            'installments' => (int) ($input['installments'] ?? 1),
            'payment_url' => $baseUrl . '/checkout/' . $paymentId,
            'sandbox_init_point' => $baseUrl . '/checkout/' . $paymentId,
            'init_point' => $baseUrl . '/checkout/' . $paymentId,
            'status' => 'pending_payment',
            'created_at' => date('Y-m-d\TH:i:s.vP'),
        ];

        // Salvar como pagamento pendente (aguardando checkout)
        $payment = [
            'id' => $paymentId,
            'gateway_assinatura_id' => $gatewayAssinaturaId,
            'pacote_contrato_id' => $input['pacote_contrato_id'] ?? null,
            'gateway_preference_id' => $preferenceId,
            'external_reference' => $input['external_reference'] ?? null,
            'payment_url' => $baseUrl . '/checkout/' . $paymentId,
            'status' => 'pending',
            'status_detail' => 'waiting_checkout',
            'amount' => $amount,
            'currency' => $input['currency'] ?? 'BRL',
            'payment_method' => null, // será preenchido no checkout
            'card' => null,
            'payer' => $preference['payer'],
            'description' => $preference['description'],
            'notification_url' => $preference['notification_url'],
            'metadata' => $preference['metadata'],
            'installments' => $preference['installments'],
            'items' => $items,
            'back_urls' => $preference['back_urls'],
            'captured' => false,
            'refunded' => false,
            'refund_amount' => 0,
            'created_at' => date('Y-m-d\TH:i:s.vP'),
            'updated_at' => date('Y-m-d\TH:i:s.vP'),
        ];

        $payments = readJsonFile('payments.json');
        $payments[$paymentId] = $payment;
        writeJsonFile('payments.json', $payments);

        // Salvar preferência separada
        $preferences = readJsonFile('preferences.json');
        $preferences[$preferenceId] = $preference;
        writeJsonFile('preferences.json', $preferences);

        logActivity('preference.created', [
            'preference_id' => $preferenceId,
            'payment_id' => $paymentId,
            'amount' => $amount,
        ]);

        jsonResponse($preference, 201);
    }

    /**
     * POST /checkout/{id}/process - Processa pagamento do checkout
     */
    public function processCheckout(string $paymentId): void
    {
        $input = getJsonInput();
        $payments = readJsonFile('payments.json');

        if (!isset($payments[$paymentId])) {
            jsonResponse(['error' => 'Pagamento não encontrado.'], 404);
            return;
        }

        $payment = $payments[$paymentId];

        if ($payment['status'] !== 'pending' || ($payment['status_detail'] ?? '') !== 'waiting_checkout') {
            jsonResponse(['error' => 'Este pagamento já foi processado.', 'status' => $payment['status']], 400);
            return;
        }

        $paymentMethod = $input['payment_method'] ?? 'credit_card';
        if (!in_array($paymentMethod, self::PAYMENT_METHODS)) {
            jsonResponse(['error' => 'Método de pagamento inválido.', 'valid_methods' => self::PAYMENT_METHODS], 422);
            return;
        }

        // Atualizar dados do pagador se preenchidos no checkout
        if (!empty($input['payer'])) {
            $payment['payer'] = array_merge($payment['payer'] ?? [], $input['payer']);
        }

        // Resolver status
        $status = $this->resolveStatus($input);
        $statusDetail = $this->getStatusDetail($status, $paymentMethod);

        $payment['payment_method'] = $paymentMethod;
        $payment['payment_method_id'] = $this->resolvePaymentMethodId($paymentMethod, $input);
        $payment['payment_type_id'] = $paymentMethod;
        $payment['card'] = $this->buildCardInfo($input);
        $payment['status'] = $status;
        $payment['status_detail'] = $statusDetail;
        $payment['date_approved'] = $status === 'approved' ? date('Y-m-d\TH:i:s.vP') : null;
        $payment['date_last_updated'] = date('Y-m-d\TH:i:s.vP');
        $payment['captured'] = $status === 'approved';
        $payment['installments'] = (int) ($input['installments'] ?? $payment['installments'] ?? 1);
        $payment['transaction_amount'] = $payment['amount'];
        $payment['transaction_details'] = [
            'net_received_amount' => round($payment['amount'] * 0.955, 2),
            'total_paid_amount' => $payment['amount'],
            'overpaid_amount' => 0,
            'installment_amount' => round($payment['amount'] / max(1, $payment['installments']), 2),
        ];
        $payment['live_mode'] = false;
        $payment['point_of_interaction'] = $paymentMethod === 'pix' ? $this->buildPixData($payment['id'], $payment['amount']) : null;
        $payment['updated_at'] = date('Y-m-d\TH:i:s.vP');

        $payments[$paymentId] = $payment;
        writeJsonFile('payments.json', $payments);

        logActivity('payment.checkout_completed', [
            'payment_id' => $paymentId,
            'status' => $status,
            'payment_method' => $paymentMethod,
        ]);

        // Disparar webhook
        $this->dispatchWebhook('payment.created', $payment);

        // Determinar URL de redirecionamento (back_urls)
        $backUrls = $payment['back_urls'] ?? [];
        $redirectUrl = match ($status) {
            'approved' => $backUrls['success'] ?? null,
            'rejected', 'error', 'cancelled' => $backUrls['failure'] ?? null,
            default => $backUrls['pending'] ?? null,
        };

        jsonResponse([
            'payment' => $payment,
            'redirect_url' => $redirectUrl,
        ]);
    }

    /**
     * POST /api/payments - Criar novo pagamento
     */
    public function create(): void
    {
        $input = getJsonInput();

        // Validações - aceitar transaction_amount (MP real) ou amount (legacy)
        $rawAmount = $input['transaction_amount'] ?? $input['amount'] ?? null;
        if (empty($rawAmount) || !is_numeric($rawAmount) || $rawAmount <= 0) {
            jsonResponse(['error' => 'Campo "transaction_amount" é obrigatório e deve ser um valor positivo.'], 422);
            return;
        }

        // Aceitar payment_method_id (MP real) ou payment_method (legacy)
        $paymentMethod = $input['payment_method_id'] ?? $input['payment_method'] ?? 'credit_card';
        // Mapear nomes do MP real para nomes internos
        $methodMap = [
            'visa' => 'credit_card',
            'master' => 'credit_card',
            'elo' => 'credit_card',
            'amex' => 'credit_card',
            'hipercard' => 'credit_card',
            'debvisa' => 'debit_card',
            'debmaster' => 'debit_card',
            'debelo' => 'debit_card',
            'pix' => 'pix',
            'bolbradesco' => 'boleto',
            'pec' => 'boleto',
            'account_money' => 'bank_transfer',
            'bank_transfer' => 'bank_transfer',
        ];
        $paymentMethodId = $paymentMethod; // guardar o nome original do MP
        $paymentMethod = $methodMap[$paymentMethod] ?? $paymentMethod;
        if (!in_array($paymentMethod, self::PAYMENT_METHODS)) {
            // Se não bater, aceitar mesmo assim como credit_card
            $paymentMethod = 'credit_card';
        }

        $paymentId = (string) random_int(100000000000, 999999999999);
        $amount = round((float) $rawAmount, 2);

        // Determinar status baseado nas regras de simulação
        $status = $this->resolveStatus($input);
        $statusDetail = $this->getStatusDetail($status, $paymentMethod);

        // PIX: sempre começa como pending (aguardando pagamento)
        // A aprovação acontece quando o cliente paga na tela de PIX
        // ou via POST /pix/{id}/confirm
        if ($paymentMethod === 'pix' && empty($input['_simulate_status'])) {
            $status = 'pending';
            $statusDetail = 'pending_waiting_transfer';
        }

        // Gerar IDs do gateway (simulando Mercado Pago) - numéricos como MP real
        $gatewayAssinaturaId = (string) random_int(100000000000, 999999999999);
        $gatewayPreferenceId = (string) random_int(100000000000, 999999999999);
        $pacoteContratoId = $input['pacote_contrato_id'] ?? null;

        // Gerar external_reference se não fornecido
        $externalReference = $input['external_reference'] ?? null;

        // Gerar payment_url (URL de checkout simulada)
        $baseUrl = $this->getBaseUrl();
        $paymentUrl = $baseUrl . '/checkout/' . $paymentId;

        // subscription_id: se veio de uma assinatura, incluir no pagamento
        $subscriptionId = $input['subscription_id'] ?? $input['preapproval_id'] ?? null;

        $payment = [
            'id' => $paymentId,
            'date_created' => date('Y-m-d\TH:i:s.vP'),
            'date_approved' => $status === 'approved' ? date('Y-m-d\TH:i:s.vP') : null,
            'date_last_updated' => date('Y-m-d\TH:i:s.vP'),
            'money_release_date' => $status === 'approved' ? date('Y-m-d\TH:i:s.vP', strtotime('+14 days')) : null,
            'subscription_id' => $subscriptionId,
            'gateway_assinatura_id' => $gatewayAssinaturaId,
            'pacote_contrato_id' => $pacoteContratoId,
            'gateway_preference_id' => $gatewayPreferenceId,
            'external_reference' => $externalReference,
            'payment_url' => $paymentUrl,
            'issuer_id' => $input['issuer_id'] ?? (string) random_int(1, 999),
            'payment_method_id' => $paymentMethodId !== $paymentMethod ? $paymentMethodId : $this->resolvePaymentMethodId($paymentMethod, $input),
            'payment_type_id' => $paymentMethod,
            'status' => $status,
            'status_detail' => $statusDetail,
            'currency_id' => $input['currency'] ?? 'BRL',
            'transaction_amount' => $amount,
            'transaction_details' => [
                'net_received_amount' => round($amount * 0.955, 2),
                'total_paid_amount' => $amount,
                'overpaid_amount' => 0,
                'installment_amount' => round($amount / max(1, (int) ($input['installments'] ?? 1)), 2),
            ],
            'payment_method' => $paymentMethod,
            'card' => $this->buildCardInfo($input),
            'payer' => [
                'id' => random_int(100000000, 999999999),
                'email' => $input['payer']['email'] ?? 'simulado@gateway.test',
                'identification' => [
                    'type' => $input['payer']['identification']['type'] ?? 'CPF',
                    'number' => $input['payer']['identification']['number'] ?? $input['payer']['document'] ?? '00000000000',
                ],
                'first_name' => $input['payer']['name'] ?? $input['payer']['first_name'] ?? 'Simulado',
                'last_name' => $input['payer']['last_name'] ?? '',
                'type' => 'customer',
            ],
            'description' => $input['description'] ?? '',
            'notification_url' => $input['notification_url'] ?? null,
            'metadata' => $input['metadata'] ?? [],
            'installments' => (int) ($input['installments'] ?? 1),
            'captured' => $status === 'approved',
            'live_mode' => false,
            'point_of_interaction' => $paymentMethod === 'pix' ? $this->buildPixData($paymentId, $amount) : null,
            'refunded' => false,
            'refund_amount' => 0,
            'created_at' => date('Y-m-d\TH:i:s.vP'),
            'updated_at' => date('Y-m-d\TH:i:s.vP'),
        ];

        // Salvar pagamento
        $payments = readJsonFile('payments.json');
        $payments[$paymentId] = $payment;
        writeJsonFile('payments.json', $payments);

        logActivity('payment.created', [
            'payment_id' => $paymentId,
            'status' => $status,
            'amount' => $amount,
        ]);

        // Disparar webhook
        $this->dispatchWebhook('payment.created', $payment);

        jsonResponse($payment, 201);
    }

    /**
     * GET /api/payments/{id} - Consultar pagamento
     */
    public function show(string $id): void
    {
        $payments = readJsonFile('payments.json');

        if (!isset($payments[$id])) {
            jsonResponse(['error' => 'Pagamento não encontrado.', 'id' => $id], 404);
            return;
        }

        jsonResponse($payments[$id]);
    }

    /**
     * GET /api/payments - Listar pagamentos
     */
    public function list(): void
    {
        $payments = readJsonFile('payments.json');

        // Filtros opcionais via query string
        $status = $_GET['status'] ?? null;
        $method = $_GET['payment_method'] ?? null;
        $limit = (int) ($_GET['limit'] ?? 50);
        $offset = (int) ($_GET['offset'] ?? 0);

        $filtered = array_values($payments);

        if ($status) {
            $filtered = array_filter($filtered, fn($p) => $p['status'] === $status);
        }
        if ($method) {
            $filtered = array_filter($filtered, fn($p) => $p['payment_method'] === $method);
        }

        // Ordenar por data decrescente (suporta created_at e date_created)
        usort($filtered, fn($a, $b) => strcmp(
            $b['created_at'] ?? $b['date_created'] ?? '',
            $a['created_at'] ?? $a['date_created'] ?? ''
        ));

        $total = count($filtered);
        $filtered = array_slice($filtered, $offset, $limit);

        jsonResponse([
            'data' => array_values($filtered),
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    /**
     * POST /api/payments/{id}/capture - Capturar pagamento
     */
    public function capture(string $id): void
    {
        $payments = readJsonFile('payments.json');

        if (!isset($payments[$id])) {
            jsonResponse(['error' => 'Pagamento não encontrado.'], 404);
            return;
        }

        $payment = &$payments[$id];

        if ($payment['status'] !== 'pending' && $payment['status'] !== 'in_process') {
            jsonResponse(['error' => 'Pagamento não pode ser capturado no status atual: ' . $payment['status']], 400);
            return;
        }

        $payment['status'] = 'approved';
        $payment['status_detail'] = 'accredited';
        $payment['captured'] = true;
        $payment['updated_at'] = date('Y-m-d\TH:i:s.vP');

        writeJsonFile('payments.json', $payments);

        logActivity('payment.captured', ['payment_id' => $id]);
        $this->dispatchWebhook('payment.updated', $payment);

        jsonResponse($payment);
    }

    /**
     * POST /api/payments/{id}/cancel - Cancelar pagamento
     */
    public function cancel(string $id): void
    {
        $payments = readJsonFile('payments.json');

        if (!isset($payments[$id])) {
            jsonResponse(['error' => 'Pagamento não encontrado.'], 404);
            return;
        }

        $payment = &$payments[$id];

        if (in_array($payment['status'], ['cancelled', 'refunded', 'charged_back'])) {
            jsonResponse(['error' => 'Pagamento já está finalizado: ' . $payment['status']], 400);
            return;
        }

        $payment['status'] = 'cancelled';
        $payment['status_detail'] = 'by_admin';
        $payment['updated_at'] = date('Y-m-d\TH:i:s.vP');

        writeJsonFile('payments.json', $payments);

        logActivity('payment.cancelled', ['payment_id' => $id]);
        $this->dispatchWebhook('payment.updated', $payment);

        jsonResponse($payment);
    }

    /**
     * POST /api/payments/{id}/refund - Reembolsar pagamento
     */
    public function refund(string $id): void
    {
        $input = getJsonInput();
        $payments = readJsonFile('payments.json');

        if (!isset($payments[$id])) {
            jsonResponse(['error' => 'Pagamento não encontrado.'], 404);
            return;
        }

        $payment = &$payments[$id];

        if ($payment['status'] !== 'approved') {
            jsonResponse(['error' => 'Somente pagamentos aprovados podem ser reembolsados.'], 400);
            return;
        }

        $refundAmount = isset($input['amount']) ? round((float) $input['amount'], 2) : $payment['amount'];

        if ($refundAmount > $payment['amount'] - $payment['refund_amount']) {
            jsonResponse(['error' => 'Valor de reembolso excede o valor disponível.'], 400);
            return;
        }

        $payment['refund_amount'] += $refundAmount;
        $payment['refunded'] = true;

        if ($payment['refund_amount'] >= $payment['amount']) {
            $payment['status'] = 'refunded';
            $payment['status_detail'] = 'refunded';
        } else {
            $payment['status_detail'] = 'partially_refunded';
        }

        $payment['updated_at'] = date('Y-m-d\TH:i:s.vP');

        writeJsonFile('payments.json', $payments);

        logActivity('payment.refunded', [
            'payment_id' => $id,
            'refund_amount' => $refundAmount,
        ]);
        $this->dispatchWebhook('payment.refunded', $payment);

        jsonResponse($payment);
    }

    /**
     * POST /pix/{id}/confirm - Confirmar pagamento PIX (simular que o cliente pagou)
     */
    public function confirmPix(string $id): void
    {
        $payments = readJsonFile('payments.json');

        if (!isset($payments[$id])) {
            jsonResponse(['error' => 'Pagamento não encontrado.', 'id' => $id], 404);
            return;
        }

        $payment = &$payments[$id];

        if ($payment['status'] === 'approved') {
            jsonResponse($payment);
            return;
        }

        $payment['status'] = 'approved';
        $payment['status_detail'] = 'accredited';
        $payment['date_approved'] = date('Y-m-d\TH:i:s.vP');
        $payment['date_last_updated'] = date('Y-m-d\TH:i:s.vP');
        $payment['captured'] = true;
        $payment['money_release_date'] = date('Y-m-d\TH:i:s.vP', strtotime('+14 days'));
        $payment['updated_at'] = date('Y-m-d\TH:i:s.vP');

        writeJsonFile('payments.json', $payments);

        // Auto-criar preapproval (assinatura) para pagamentos PIX com external_reference de matrícula
        // Isso permite que o /recurring encontre a assinatura e simule cobranças recorrentes
        $extRef = $payment['external_reference'] ?? '';
        $assinaturaId = $payment['gateway_assinatura_id'] ?? null;
        if ($extRef && $assinaturaId && str_starts_with($extRef, 'MAT-')) {
            $preapprovals = readJsonFile('preapprovals.json');
            // Só criar se ainda não existir
            $alreadyExists = false;
            foreach ($preapprovals as $p) {
                if (($p['external_reference'] ?? '') === $extRef) {
                    $alreadyExists = true;
                    break;
                }
            }
            if (!$alreadyExists) {
                $metadata = $payment['metadata'] ?? [];
                $amount = $payment['transaction_amount'] ?? $payment['amount'] ?? 0;
                $now = date('Y-m-d\TH:i:s.000-04:00');
                $preapproval = [
                    'id' => $assinaturaId,
                    'preapproval_plan_id' => null,
                    'payer_id' => $payment['payer']['id'] ?? random_int(100000000, 999999999),
                    'payer_email' => $payment['payer']['email'] ?? '',
                    'back_url' => $payment['notification_url'] ?? '',
                    'collector_id' => random_int(100000000, 999999999),
                    'application_id' => random_int(1000000000, 9999999999),
                    'status' => 'authorized',
                    'reason' => $payment['description'] ?? 'Assinatura PIX',
                    'external_reference' => $extRef,
                    'date_created' => $now,
                    'last_modified' => $now,
                    'init_point' => '',
                    'sandbox_init_point' => '',
                    'auto_recurring' => [
                        'frequency' => 1,
                        'frequency_type' => 'months',
                        'transaction_amount' => round((float) $amount, 2),
                        'currency_id' => $payment['currency_id'] ?? 'BRL',
                        'start_date' => $now,
                        'end_date' => date('Y-m-d\TH:i:s.000-04:00', strtotime('+1 year')),
                        'repetitions' => null,
                        'free_trial' => null,
                    ],
                    'payment_method_id' => 'pix',
                    'first_invoice_offset' => null,
                    'subscription_id' => $assinaturaId,
                    'notification_url' => $payment['notification_url'] ?? null,
                    'metadata' => $metadata,
                    'live_mode' => false,
                    'next_payment_date' => date('Y-m-d\TH:i:s.000-04:00', strtotime('+1 month')),
                    'summarized' => [
                        'quotas' => null,
                        'charged_quantity' => 1,
                        'pending_charge_quantity' => null,
                        'charged_amount' => round((float) $amount, 2),
                        'pending_charge_amount' => null,
                        'semaphore' => 'green',
                        'last_charged_date' => $now,
                        'last_charged_amount' => round((float) $amount, 2),
                    ],
                ];
                $preapprovals[$assinaturaId] = $preapproval;
                writeJsonFile('preapprovals.json', $preapprovals);

                // Vincular o subscription_id no pagamento
                $payment['subscription_id'] = $assinaturaId;
                $payment['preapproval_id'] = $assinaturaId;
                $payments[$id] = $payment;
                writeJsonFile('payments.json', $payments);

                logActivity('preapproval.auto_created_from_pix', [
                    'preapproval_id' => $assinaturaId,
                    'payment_id' => $id,
                    'external_reference' => $extRef,
                    'amount' => $amount,
                ]);
            }
        }

        logActivity('payment.pix_confirmed', [
            'payment_id' => $id,
            'amount' => $payment['transaction_amount'] ?? $payment['amount'] ?? 0,
        ]);

        // Disparar webhook
        $this->dispatchWebhook('payment.updated', $payment);

        jsonResponse($payment);
    }

    // ============================================================
    // Métodos privados
    // ============================================================

    /**
     * Resolve o status do pagamento baseado nas regras de simulação
     */
    private function resolveStatus(array $input): string
    {
        // 1. Se o cliente enviou um status desejado explicitamente
        if (!empty($input['_simulate_status']) && in_array($input['_simulate_status'], self::STATUSES)) {
            return $input['_simulate_status'];
        }

        // 2. Verificar regras de simulação salvas
        $rules = readJsonFile('simulation_rules.json');
        foreach ($rules as $rule) {
            if ($this->ruleMatches($rule, $input)) {
                return $rule['status'];
            }
        }

        // 3. Regras baseadas em número de cartão (convenção)
        $cardNumber = $input['card']['number'] ?? $input['card_number'] ?? '';
        $lastFour = substr(preg_replace('/\D/', '', $cardNumber), -4);

        return match (true) {
            $lastFour === '0001' => 'approved',
            $lastFour === '0002' => 'rejected',
            $lastFour === '0003' => 'pending',
            $lastFour === '0004' => 'in_process',
            $lastFour === '0005' => 'cancelled',
            $lastFour === '0006' => 'error',
            $lastFour === '0007' => 'charged_back',
            default => 'approved', // Default: aprovar
        };
    }

    /**
     * Verifica se uma regra de simulação se aplica ao pagamento
     */
    private function ruleMatches(array $rule, array $input): bool
    {
        $conditions = $rule['conditions'] ?? [];

        foreach ($conditions as $field => $value) {
            $actual = $this->getNestedValue($input, $field);
            if ($actual === null || (string) $actual !== (string) $value) {
                return false;
            }
        }

        return !empty($conditions);
    }

    /**
     * Obtém valor aninhado de um array usando notação de ponto
     */
    private function getNestedValue(array $data, string $key): mixed
    {
        $keys = explode('.', $key);
        $current = $data;

        foreach ($keys as $k) {
            if (!is_array($current) || !isset($current[$k])) {
                return null;
            }
            $current = $current[$k];
        }

        return $current;
    }

    /**
     * Gera um detalhe de status realista
     */
    private function getStatusDetail(string $status, string $paymentMethod): string
    {
        return match ($status) {
            'approved' => 'accredited',
            'rejected' => match (true) {
                $paymentMethod === 'credit_card' => ['cc_rejected_insufficient_amount', 'cc_rejected_bad_filled_security_code', 'cc_rejected_high_risk', 'cc_rejected_card_disabled'][array_rand([0, 1, 2, 3])],
                default => 'rejected',
            },
            'pending' => 'pending_waiting_payment',
            'in_process' => 'pending_review_manual',
            'cancelled' => 'expired',
            'refunded' => 'refunded',
            'charged_back' => 'settled',
            'error' => 'internal_server_error',
            default => 'unknown',
        };
    }

    /**
     * Monta info do cartão (mascarada)
     */
    private function buildCardInfo(array $input): ?array
    {
        $cardNumber = $input['card']['number'] ?? $input['card_number'] ?? null;

        if (!$cardNumber) {
            return null;
        }

        $clean = preg_replace('/\D/', '', $cardNumber);
        $lastFour = substr($clean, -4);
        $firstSix = substr($clean, 0, 6);

        return [
            'first_six_digits' => $firstSix,
            'last_four_digits' => $lastFour,
            'brand' => $input['card']['brand'] ?? $this->detectBrand($firstSix),
            'holder_name' => $input['card']['holder_name'] ?? $input['payer']['name'] ?? 'TITULAR',
            'expiration_month' => $input['card']['expiration_month'] ?? 12,
            'expiration_year' => $input['card']['expiration_year'] ?? 2030,
        ];
    }

    /**
     * Detecta a bandeira do cartão pelo BIN
     */
    private function detectBrand(string $firstSix): string
    {
        $first = (int) substr($firstSix, 0, 1);
        $firstTwo = (int) substr($firstSix, 0, 2);

        return match (true) {
            $first === 4 => 'visa',
            $firstTwo >= 51 && $firstTwo <= 55 => 'mastercard',
            $firstTwo === 34 || $firstTwo === 37 => 'amex',
            $firstTwo === 36 || $firstTwo === 38 => 'diners',
            $firstTwo === 63 || $firstTwo === 64 => 'elo',
            default => 'unknown',
        };
    }

    /**
     * Resolve payment_method_id no formato MP (visa, master, pix, bolbradesco, etc.)
     */
    private function resolvePaymentMethodId(string $paymentMethod, array $input): string
    {
        if (!empty($input['payment_method_id'])) {
            return $input['payment_method_id'];
        }

        return match ($paymentMethod) {
            'credit_card', 'debit_card' => $this->buildCardInfo($input)['brand'] ?? 'visa',
            'pix' => 'pix',
            'boleto' => 'bolbradesco',
            'bank_transfer' => 'pix',
            default => 'account_money',
        };
    }

    /**
     * Gera dados do PIX (QR Code simulado) no formato MP
     */
    private function buildPixData(string $paymentId, float $amount): array
    {
        $pixCode = '00020126580014br.gov.bcb.pix0136' . bin2hex(random_bytes(16)) . '5204000053039865802BR5925SIMULADOR GATEWAY6009SAO PAULO62070503***6304' . strtoupper(bin2hex(random_bytes(2)));

        return [
            'type' => 'PIX',
            'business_info' => [
                'unit' => 'online_payments',
                'sub_unit' => 'checkout_pro',
            ],
            'transaction_data' => [
                'qr_code' => $pixCode,
                'qr_code_base64' => 'iVBORw0KGgoAAAANSUhEUgAAASwAAAEsCAYAAAB5fY51AAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAAJcEhZcwAADsMAAA7DAc...(simulado)',
                'ticket_url' => $this->getBaseUrl() . '/pix/' . $paymentId,
                'bank_transfer_id' => random_int(100000, 999999),
                'financial_institution' => null,
            ],
        ];
    }

    /**
     * Dispara webhook para URLs registradas
     */
    private function dispatchWebhook(string $event, array $payment): void
    {
        $webhooks = readJsonFile('webhooks.json');
        $notificationUrl = $payment['notification_url'] ?? null;

        // Coletar URLs para notificar (normalizando localhost→host.docker.internal)
        $normalizeUrl = fn(string $u): string => str_replace('://localhost', '://host.docker.internal', $u);
        $urls = [];

        // URLs registradas globalmente
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

        // URL do pagamento individual
        if ($notificationUrl) {
            $normalized = $normalizeUrl($notificationUrl);
            if (!in_array($normalized, $urls)) {
                $urls[] = $normalized;
            }
        }

        if (empty($urls)) {
            return;
        }

        // Formato real do webhook Mercado Pago:
        // { id, live_mode, type: "payment", date_created, user_id, api_version, action: "payment.created", data: { id } }
        // O app recebe isso e faz GET /v1/payments/{id} para buscar os dados
        $action = match(true) {
            str_contains($event, 'updated') => 'payment.updated',
            str_contains($event, 'refund') => 'payment.updated',
            default => 'payment.created',
        };

        $payload = [
            'id' => random_int(10000000000, 99999999999),
            'live_mode' => false,
            'type' => 'payment',
            'date_created' => date('Y-m-d\TH:i:s.000-04:00'),
            'user_id' => $payment['payer']['id'] ?? random_int(100000000, 999999999),
            'api_version' => 'v1',
            'action' => $action,
            'data' => [
                'id' => (string) $payment['id'],
            ],
        ];

        foreach ($urls as $url) {
            $this->sendWebhook($url, $payload, $event);
        }
    }

    /**
     * Envia notificação webhook via cURL
     */
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
                'User-Agent: PaymentGatewaySimulator/1.0',
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

        // Logar resultado do webhook
        $webhookLogs = readJsonFile('webhook_logs.json');
        $webhookLogs[] = [
            'id' => generateTransactionId('whl'),
            'url' => $url,
            'event' => $event,
            'payment_id' => $payload['data']['id'],
            'http_status' => $httpCode,
            'success' => $httpCode >= 200 && $httpCode < 300,
            'error' => $error ?: null,
            'response_body' => substr((string) $response, 0, 500),
            'sent_at' => date('Y-m-d\TH:i:s.vP'),
        ];

        $webhookLogs = array_slice($webhookLogs, -200);
        writeJsonFile('webhook_logs.json', $webhookLogs);
    }

    /**
     * GET /api/purchases - Listar compras avulsas (pagamentos SEM subscription_id)
     * Suporta filtros: ?external_reference=MAT-190&status=approved
     */
    public function listPurchases(): void
    {
        $payments = readJsonFile('payments.json');

        // Filtrar apenas pagamentos avulsos (sem subscription_id)
        $filtered = array_filter(array_values($payments), function ($p) {
            $subId = $p['subscription_id'] ?? null;
            return empty($subId) || $subId === null || $subId === 'null';
        });

        // Filtros opcionais
        $extRef = $_GET['external_reference'] ?? null;
        $status = $_GET['status'] ?? null;
        $method = $_GET['payment_method'] ?? null;

        if ($extRef) {
            $filtered = array_filter($filtered, fn($p) => ($p['external_reference'] ?? '') === $extRef);
        }
        if ($status) {
            $filtered = array_filter($filtered, fn($p) => ($p['status'] ?? '') === $status);
        }
        if ($method) {
            $filtered = array_filter($filtered, fn($p) =>
                ($p['payment_method'] ?? $p['payment_method_id'] ?? '') === $method
            );
        }

        // Ordenar por data decrescente
        $filtered = array_values($filtered);
        usort($filtered, fn($a, $b) => strcmp(
            $b['created_at'] ?? $b['date_created'] ?? '',
            $a['created_at'] ?? $a['date_created'] ?? ''
        ));

        $limit = (int) ($_GET['limit'] ?? 50);
        $offset = (int) ($_GET['offset'] ?? 0);
        $total = count($filtered);
        $filtered = array_slice($filtered, $offset, $limit);

        jsonResponse([
            'data' => array_values($filtered),
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    /**
     * Retorna a base URL do simulador
     */
    private function getBaseUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8085';
        // host.docker.internal só funciona dentro de containers, não no navegador
        $host = str_replace('host.docker.internal', 'localhost', $host);
        return $scheme . '://' . $host;
    }
}
