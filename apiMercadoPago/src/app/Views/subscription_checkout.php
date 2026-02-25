<?php
/**
 * Subscription Checkout Page
 * Simula a página de checkout de assinatura do Mercado Pago
 */

require_once __DIR__ . '/../helpers.php';

// $isPlan e $subscriptionId vêm do router (index.php)

$subscription = null;
$plan = null;
$errorMsg = null;

if ($isPlan) {
    // Checkout de plano - precisa criar uma assinatura
    $plans = readJsonFile('preapproval_plans.json');
    if (isset($plans[$subscriptionId])) {
        $plan = $plans[$subscriptionId];
    } else {
        $errorMsg = "Plano não encontrado: {$subscriptionId}";
    }
} else {
    // Checkout de assinatura existente
    $preapprovals = readJsonFile('preapprovals.json');
    if (isset($preapprovals[$subscriptionId])) {
        $subscription = $preapprovals[$subscriptionId];
    } else {
        $errorMsg = "Assinatura não encontrada: {$subscriptionId}";
    }
}

$item = $subscription ?? $plan;
$reason = $item['reason'] ?? 'Assinatura';
$amount = $item['auto_recurring']['transaction_amount'] ?? 0;
$currency = $item['auto_recurring']['currency_id'] ?? 'BRL';
$frequency = $item['auto_recurring']['frequency'] ?? 1;
$frequencyType = $item['auto_recurring']['frequency_type'] ?? 'months';

$frequencyLabel = match($frequencyType) {
    'days' => $frequency == 1 ? 'dia' : "{$frequency} dias",
    'months' => $frequency == 1 ? 'mês' : "{$frequency} meses",
    'years' => $frequency == 1 ? 'ano' : "{$frequency} anos",
    default => "{$frequency} {$frequencyType}"
};
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - <?= htmlspecialchars($reason) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f0f2f5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .checkout-container {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.1);
            max-width: 480px;
            width: 100%;
            margin: 20px;
            overflow: hidden;
        }
        .header {
            background: #009ee3;
            color: #fff;
            padding: 24px;
            text-align: center;
        }
        .header h1 {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 4px;
        }
        .header .subtitle {
            font-size: 13px;
            opacity: 0.85;
        }
        .plan-info {
            padding: 24px;
            border-bottom: 1px solid #e8e8e8;
            text-align: center;
        }
        .plan-name {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        .plan-price {
            font-size: 32px;
            font-weight: 700;
            color: #009ee3;
        }
        .plan-price .currency {
            font-size: 16px;
            vertical-align: super;
        }
        .plan-frequency {
            font-size: 14px;
            color: #666;
            margin-top: 4px;
        }
        .plan-id {
            font-size: 11px;
            color: #999;
            margin-top: 8px;
            word-break: break-all;
        }
        .form-section {
            padding: 24px;
        }
        .form-section h2 {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 16px;
        }
        .form-group {
            margin-bottom: 16px;
        }
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: #555;
            margin-bottom: 6px;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.2s;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #009ee3;
            box-shadow: 0 0 0 3px rgba(0,158,227,0.15);
        }
        .row {
            display: flex;
            gap: 12px;
        }
        .row .form-group {
            flex: 1;
        }
        .btn-subscribe {
            width: 100%;
            padding: 16px;
            background: #009ee3;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
            margin-top: 8px;
        }
        .btn-subscribe:hover {
            background: #0084c2;
        }
        .btn-subscribe:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .security-note {
            text-align: center;
            padding: 16px 24px;
            font-size: 12px;
            color: #999;
            border-top: 1px solid #e8e8e8;
        }
        .security-note svg {
            vertical-align: middle;
            margin-right: 4px;
        }
        .error-container {
            padding: 40px 24px;
            text-align: center;
        }
        .error-container .icon {
            font-size: 48px;
            margin-bottom: 16px;
        }
        .error-container h2 {
            color: #e74c3c;
            margin-bottom: 8px;
        }
        .error-container p {
            color: #666;
        }
        .result-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        .result-card {
            background: #fff;
            border-radius: 12px;
            padding: 40px;
            text-align: center;
            max-width: 400px;
            width: 90%;
        }
        .result-card.success .icon { color: #27ae60; font-size: 48px; }
        .result-card.error .icon { color: #e74c3c; font-size: 48px; }
        .result-card h3 { margin: 16px 0 8px; font-size: 20px; }
        .result-card p { color: #666; font-size: 14px; margin-bottom: 8px; }
        .result-card .detail { font-size: 12px; color: #999; word-break: break-all; }
        .result-card button {
            margin-top: 20px;
            padding: 12px 32px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            background: #009ee3;
            color: #fff;
        }
        .spinner {
            display: none;
            width: 24px;
            height: 24px;
            border: 3px solid rgba(255,255,255,0.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 0 auto;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>

<?php if ($errorMsg): ?>
    <div class="checkout-container">
        <div class="header">
            <h1>Mercado Pago (Simulador)</h1>
            <div class="subtitle">Checkout de Assinatura</div>
        </div>
        <div class="error-container">
            <div class="icon">❌</div>
            <h2>Erro</h2>
            <p><?= htmlspecialchars($errorMsg) ?></p>
        </div>
    </div>
<?php else: ?>

    <div class="checkout-container">
        <div class="header">
            <h1>Mercado Pago (Simulador)</h1>
            <div class="subtitle">Checkout de Assinatura Recorrente</div>
        </div>

        <div class="plan-info">
            <div class="plan-name"><?= htmlspecialchars($reason) ?></div>
            <div class="plan-price">
                <span class="currency"><?= htmlspecialchars($currency) ?></span>
                <?= number_format($amount, 2, ',', '.') ?>
            </div>
            <div class="plan-frequency">a cada <?= $frequencyLabel ?></div>
            <div class="plan-id">ID: <?= htmlspecialchars($subscriptionId) ?></div>
        </div>

        <form id="subscriptionForm" class="form-section">
            <h2>Dados do Cartão</h2>

            <div class="form-group">
                <label>Nome no cartão</label>
                <input type="text" id="cardName" placeholder="Como está no cartão" value="APPMERCADO SIMULADOR" required>
            </div>

            <div class="form-group">
                <label>Número do cartão</label>
                <input type="text" id="cardNumber" placeholder="0000 0000 0000 0000" maxlength="19"
                       value="5031 4332 1540 6351" required>
            </div>

            <div class="row">
                <div class="form-group">
                    <label>Validade</label>
                    <input type="text" id="cardExpiry" placeholder="MM/AA" maxlength="5" value="11/30" required>
                </div>
                <div class="form-group">
                    <label>CVV</label>
                    <input type="text" id="cardCvv" placeholder="123" maxlength="4" value="123" required>
                </div>
            </div>

            <div class="form-group">
                <label>E-mail</label>
                <input type="email" id="email"
                       value="<?= htmlspecialchars($subscription['payer_email'] ?? $plan['payer_email'] ?? 'teste@email.com') ?>"
                       required>
            </div>

            <div class="form-group">
                <label>CPF</label>
                <input type="text" id="cpf" placeholder="000.000.000-00" maxlength="14" value="12345678909">
            </div>

            <div class="form-group">
                <label>Simular Resultado do Pagamento</label>
                <select id="simulateStatus" style="background:#f8f9fa;">
                    <option value="approved" selected>✅ Aprovado (approved)</option>
                    <option value="rejected">❌ Rejeitado (rejected)</option>
                    <option value="pending">⏳ Pendente (pending)</option>
                    <option value="in_process">🔄 Em Processamento (in_process)</option>
                    <option value="cancelled">🚫 Cancelado (cancelled)</option>
                    <option value="error">⚠️ Erro (error)</option>
                </select>
            </div>

            <button type="submit" class="btn-subscribe" id="btnSubmit">
                <span id="btnText">Assinar — <?= htmlspecialchars($currency) ?> <?= number_format($amount, 2, ',', '.') ?>/<?= $frequencyLabel ?></span>
                <div class="spinner" id="btnSpinner"></div>
            </button>
        </form>

        <div class="security-note">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#999" stroke-width="2">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
            Pagamento simulado — Gateway Simulator v1.0
        </div>
    </div>

    <div class="result-overlay" id="resultOverlay">
        <div class="result-card" id="resultCard">
            <div class="icon" id="resultIcon"></div>
            <h3 id="resultTitle"></h3>
            <p id="resultMessage"></p>
            <div class="detail" id="resultDetail"></div>
            <button id="resultBtn" onclick="handleResultClose()">OK</button>
        </div>
    </div>

    <script>
        function handleResultClose() {
            const backUrl = '<?= addslashes($item['back_url'] ?? '') ?>';
            if (backUrl) {
                window.location.href = backUrl;
            } else {
                window.close();
                location.href = '/';
            }
        }

        // Formatar número do cartão
        document.getElementById('cardNumber').addEventListener('input', function(e) {
            let v = e.target.value.replace(/\D/g, '').substring(0, 16);
            v = v.replace(/(\d{4})(?=\d)/g, '$1 ');
            e.target.value = v;
        });

        // Formatar validade
        document.getElementById('cardExpiry').addEventListener('input', function(e) {
            let v = e.target.value.replace(/\D/g, '').substring(0, 4);
            if (v.length > 2) v = v.substring(0, 2) + '/' + v.substring(2);
            e.target.value = v;
        });

        // Formatar CPF
        document.getElementById('cpf').addEventListener('input', function(e) {
            let v = e.target.value.replace(/\D/g, '').substring(0, 11);
            if (v.length > 9) v = v.replace(/(\d{3})(\d{3})(\d{3})(\d{1,2})/, '$1.$2.$3-$4');
            else if (v.length > 6) v = v.replace(/(\d{3})(\d{3})(\d{1,3})/, '$1.$2.$3');
            else if (v.length > 3) v = v.replace(/(\d{3})(\d{1,3})/, '$1.$2');
            e.target.value = v;
        });

        document.getElementById('subscriptionForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('btnSubmit');
            const btnText = document.getElementById('btnText');
            const spinner = document.getElementById('btnSpinner');

            btn.disabled = true;
            btnText.style.display = 'none';
            spinner.style.display = 'block';

            const cardNumber = document.getElementById('cardNumber').value.replace(/\s/g, '');
            const isPlan = <?= $isPlan ? 'true' : 'false' ?>;
            const subscriptionId = '<?= htmlspecialchars($subscriptionId) ?>';

            try {
                let response;

                const simulateStatus = document.getElementById('simulateStatus').value;

                if (isPlan) {
                    // Criar assinatura a partir do plano
                    response = await fetch('/api/preapproval', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            preapproval_plan_id: subscriptionId,
                            payer_email: document.getElementById('email').value,
                            card_token_id: 'tok_sim_' + Date.now(),
                            reason: '<?= addslashes($reason) ?>',
                            _simulate_status: simulateStatus === 'approved' ? 'authorized' : simulateStatus
                        })
                    });
                } else {
                    // Gerar pagamento para assinatura existente
                    response = await fetch('/api/preapproval/' + subscriptionId + '/pay', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            card_number: cardNumber,
                            card_holder: document.getElementById('cardName').value,
                            card_expiry: document.getElementById('cardExpiry').value,
                            card_cvv: document.getElementById('cardCvv').value,
                            payer_email: document.getElementById('email').value,
                            payer_document: document.getElementById('cpf').value.replace(/\D/g, ''),
                            _simulate_status: simulateStatus
                        })
                    });
                }

                const data = await response.json();

                const overlay = document.getElementById('resultOverlay');
                const card = document.getElementById('resultCard');
                const icon = document.getElementById('resultIcon');
                const title = document.getElementById('resultTitle');
                const msg = document.getElementById('resultMessage');
                const detail = document.getElementById('resultDetail');

                if (data.status === 'authorized' || data.status === 'approved') {
                    card.className = 'result-card success';
                    icon.textContent = '✅';
                    title.textContent = isPlan ? 'Assinatura Ativada!' : 'Pagamento Aprovado!';
                    msg.textContent = isPlan
                        ? 'Sua assinatura foi criada e está ativa.'
                        : 'Pagamento da assinatura processado com sucesso.';
                    detail.textContent = 'ID: ' + (data.id || data.payment_id || '');
                } else if (data.status === 'pending' || data.status === 'in_process') {
                    card.className = 'result-card success';
                    icon.textContent = '⏳';
                    title.textContent = 'Pagamento Pendente';
                    msg.textContent = data.status_detail || 'Aguardando processamento.';
                    detail.textContent = 'Status: ' + (data.status || '') + '\nID: ' + (data.id || '');
                } else {
                    card.className = 'result-card error';
                    icon.textContent = '❌';
                    title.textContent = isPlan ? 'Erro na Assinatura' : 'Pagamento Recusado';
                    msg.textContent = data.status_detail || data.error || 'Ocorreu um erro.';
                    detail.textContent = 'Status: ' + (data.status || 'erro') + '\nID: ' + (data.id || '');
                }

                overlay.style.display = 'flex';

                // Redirecionar pro back_url após 3s se aprovado e tiver back_url
                const backUrl = '<?= addslashes($item['back_url'] ?? '') ?>';
                if (backUrl && (data.status === 'authorized' || data.status === 'approved')) {
                    const okBtn = document.getElementById('resultBtn');
                    okBtn.textContent = 'Redirecionando...';
                    okBtn.onclick = function() { window.location.href = backUrl; };
                    setTimeout(() => { window.location.href = backUrl; }, 3000);
                }
            } catch (err) {
                alert('Erro: ' + err.message);
            } finally {
                btn.disabled = false;
                btnText.style.display = 'inline';
                spinner.style.display = 'none';
            }
        });
    </script>
<?php endif; ?>

</body>
</html>
