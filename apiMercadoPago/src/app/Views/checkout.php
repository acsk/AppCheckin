<?php
/**
 * Checkout Page - Simula a página de pagamento do Mercado Pago
 * O cliente é redirecionado para cá após criar uma preference
 */

$paymentId = $matches[1] ?? '';
$payments = readJsonFile('payments.json');
$payment = $payments[$paymentId] ?? null;
$isWaitingCheckout = $payment && $payment['status'] === 'pending' && ($payment['status_detail'] ?? '') === 'waiting_checkout';
$isCompleted = $payment && !$isWaitingCheckout;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Payment Gateway Simulator</title>
    <style>
        :root {
            --primary: #009ee3;
            --primary-dark: #007eb5;
            --success: #00a650;
            --danger: #f23d4f;
            --warning: #ff9800;
            --bg: #ebebeb;
            --white: #ffffff;
            --text: #333;
            --text-muted: #666;
            --border: #ddd;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, 'Helvetica Neue', Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
        }
        .header {
            background: var(--primary);
            width: 100%;
            padding: 0.8rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .header h1 { color: white; font-size: 1rem; font-weight: 600; }
        .header .secure { color: rgba(255,255,255,0.8); font-size: 0.8rem; }
        .container {
            max-width: 900px;
            margin: 1.5rem auto;
            padding: 0 1rem;
            display: grid;
            grid-template-columns: 1fr 340px;
            gap: 1.5rem;
        }
        @media (max-width: 768px) {
            .container { grid-template-columns: 1fr; }
        }
        .card {
            background: var(--white);
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
        .card-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 1.2rem;
            padding-bottom: 0.8rem;
            border-bottom: 1px solid #eee;
        }
        .form-group { margin-bottom: 1rem; }
        .form-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-muted);
            margin-bottom: 0.3rem;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 0.65rem 0.8rem;
            border: 1.5px solid var(--border);
            border-radius: 6px;
            font-size: 0.95rem;
            color: var(--text);
            transition: border-color 0.2s;
            outline: none;
        }
        .form-group input:focus, .form-group select:focus {
            border-color: var(--primary);
        }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 0.8rem; }
        .form-row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.8rem; }

        .pm-tabs { display: flex; gap: 0; margin-bottom: 1.2rem; border-radius: 8px; overflow: hidden; border: 1.5px solid var(--border); }
        .pm-tab {
            flex: 1;
            padding: 0.8rem;
            text-align: center;
            cursor: pointer;
            background: #f8f8f8;
            border: none;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.2s;
            border-right: 1px solid var(--border);
        }
        .pm-tab:last-child { border-right: none; }
        .pm-tab:hover { background: #e8f4fd; }
        .pm-tab.active { background: var(--primary); color: white; }
        .pm-tab .icon { display: block; font-size: 1.3rem; margin-bottom: 0.2rem; }

        .pm-form { display: none; }
        .pm-form.active { display: block; }

        .btn {
            width: 100%;
            padding: 0.9rem;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-pay { background: var(--primary); color: white; }
        .btn-pay:hover { background: var(--primary-dark); }
        .btn-pay:disabled { background: #ccc; cursor: not-allowed; }
        .btn-pix { background: #00b4a0; color: white; }
        .btn-pix:hover { background: #009688; }
        .btn-boleto { background: var(--text); color: white; }
        .btn-boleto:hover { opacity: 0.9; }

        .summary-amount {
            font-size: 2rem;
            font-weight: 700;
            text-align: center;
            margin: 0.5rem 0;
        }
        .item-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            font-size: 0.9rem;
            border-bottom: 1px solid #f0f0f0;
        }
        .item-row:last-child { border-bottom: none; }
        .total-row {
            display: flex;
            justify-content: space-between;
            font-weight: 700;
            padding-top: 0.8rem;
            margin-top: 0.5rem;
            border-top: 2px solid var(--text);
            font-size: 1.05rem;
        }
        .mono { font-family: monospace; font-size: 0.78rem; color: var(--text-muted); word-break: break-all; }

        .status-badge {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            border-radius: 2rem;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-approved { background: #e6f9ed; color: var(--success); }
        .status-rejected { background: #fde8ea; color: var(--danger); }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-cancelled { background: #e2e3e5; color: #383d41; }
        .status-error { background: #fde8ea; color: var(--danger); }

        .result-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 100;
            align-items: center;
            justify-content: center;
        }
        .result-overlay.show { display: flex; }
        .result-box {
            background: white;
            border-radius: 12px;
            padding: 2.5rem;
            text-align: center;
            max-width: 420px;
            width: 90%;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        .result-icon { font-size: 3.5rem; margin-bottom: 1rem; }
        .result-title { font-size: 1.3rem; font-weight: 700; margin-bottom: 0.5rem; }
        .result-detail { color: var(--text-muted); margin-bottom: 1.5rem; }
        .result-id { font-family: monospace; font-size: 0.8rem; color: var(--text-muted); margin-bottom: 1rem; }
        .btn-redirect {
            display: inline-block;
            padding: 0.7rem 2rem;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.95rem;
        }

        .spinner {
            width: 40px; height: 40px;
            border: 4px solid #eee;
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 0 auto;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        .error-page { text-align: center; padding: 3rem; }
        .error-page h2 { color: var(--danger); margin-bottom: 1rem; }
        .completed-page { text-align: center; }

        .footer { text-align: center; padding: 1.5rem; color: var(--text-muted); font-size: 0.8rem; }
        .footer a { color: var(--primary); }

        .test-hint {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 6px;
            padding: 0.6rem 0.8rem;
            font-size: 0.78rem;
            color: #856404;
            margin-top: 0.8rem;
        }
    </style>
</head>
<body>

<div class="header">
    <h1>💳 Payment Gateway Simulator</h1>
    <span class="secure">🔒 Ambiente Seguro de Teste</span>
</div>

<?php if (!$payment): ?>
<div style="max-width:500px;margin:3rem auto;padding:0 1rem;">
    <div class="card error-page">
        <h2>❌ Pagamento não encontrado</h2>
        <p>O ID <strong><?= htmlspecialchars($paymentId) ?></strong> não existe.</p>
        <br>
        <a href="/" class="btn-redirect">Voltar ao Dashboard</a>
    </div>
</div>

<?php elseif ($isCompleted): ?>
<div style="max-width:500px;margin:2rem auto;padding:0 1rem;">
    <div class="card completed-page">
        <?php
        $icon = match($payment['status']) {
            'approved' => '✅', 'rejected' => '❌', 'cancelled' => '🚫',
            'error' => '⚠️', 'pending' => '⏳', default => '📋'
        };
        $msg = match($payment['status']) {
            'approved' => 'Pagamento Aprovado!',
            'rejected' => 'Pagamento Rejeitado',
            'cancelled' => 'Pagamento Cancelado',
            'pending' => 'Pagamento Pendente',
            'in_process' => 'Em Processamento',
            'error' => 'Erro no Pagamento',
            default => $payment['status']
        };
        ?>
        <div style="font-size:3rem;margin-bottom:0.5rem"><?= $icon ?></div>
        <h2><?= $msg ?></h2>
        <p style="color:var(--text-muted);margin:0.5rem 0"><?= htmlspecialchars($payment['status_detail'] ?? '') ?></p>
        <div style="font-size:1.8rem;font-weight:700;margin:1rem 0">R$ <?= number_format($payment['amount'], 2, ',', '.') ?></div>
        <span class="status-badge status-<?= $payment['status'] ?>"><?= $payment['status'] ?></span>
        <div class="mono" style="margin-top:1rem"><?= $payment['id'] ?></div>
        <?php if (!empty($payment['payment_method'])): ?>
            <p style="margin-top:0.5rem;font-size:0.9rem;color:var(--text-muted)">
                <?= ucfirst(str_replace('_', ' ', $payment['payment_method'])) ?>
                <?php if (!empty($payment['card'])): ?>
                    — <?= $payment['card']['brand'] ?? '' ?> **** <?= $payment['card']['last_four_digits'] ?? '' ?>
                <?php endif; ?>
            </p>
        <?php endif; ?>
        <div style="margin-top:1.5rem">
            <a href="/" class="btn-redirect">Dashboard</a>
        </div>
    </div>
</div>

<?php else: ?>
<div class="container">
    <div class="col-form">
        <div class="card">
            <div class="card-title">Escolha a forma de pagamento</div>

            <div class="pm-tabs">
                <button class="pm-tab active" data-method="credit_card" onclick="selectMethod(this)">
                    <span class="icon">💳</span> Crédito
                </button>
                <button class="pm-tab" data-method="debit_card" onclick="selectMethod(this)">
                    <span class="icon">💳</span> Débito
                </button>
                <button class="pm-tab" data-method="pix" onclick="selectMethod(this)">
                    <span class="icon">📱</span> PIX
                </button>
                <button class="pm-tab" data-method="boleto" onclick="selectMethod(this)">
                    <span class="icon">📄</span> Boleto
                </button>
            </div>

            <!-- CARTÃO DE CRÉDITO -->
            <div id="form-credit_card" class="pm-form active">
                <div class="form-group">
                    <label>Número do Cartão</label>
                    <input type="text" id="card_number" placeholder="4111 1111 1111 0001" maxlength="19" oninput="formatCard(this)">
                </div>
                <div class="form-group">
                    <label>Nome no Cartão</label>
                    <input type="text" id="card_holder" placeholder="NOME COMO ESTÁ NO CARTÃO" style="text-transform:uppercase">
                </div>
                <div class="form-row-3">
                    <div class="form-group">
                        <label>Mês</label>
                        <select id="card_month">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= $m === 12 ? 'selected' : '' ?>><?= str_pad((string)$m, 2, '0', STR_PAD_LEFT) ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Ano</label>
                        <select id="card_year">
                            <?php for ($y = 2026; $y <= 2036; $y++): ?>
                            <option value="<?= $y ?>" <?= $y === 2028 ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>CVV</label>
                        <input type="text" id="card_cvv" placeholder="123" maxlength="4">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Parcelas</label>
                        <select id="installments">
                            <option value="1">1x de R$ <?= number_format($payment['amount'], 2, ',', '.') ?></option>
                            <?php for ($i = 2; $i <= 12; $i++): ?>
                            <option value="<?= $i ?>"><?= $i ?>x de R$ <?= number_format($payment['amount'] / $i, 2, ',', '.') ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>CPF do Titular</label>
                        <input type="text" id="card_cpf" placeholder="000.000.000-00" maxlength="14">
                    </div>
                </div>
                <button class="btn btn-pay" onclick="processPayment('credit_card')">Pagar R$ <?= number_format($payment['amount'], 2, ',', '.') ?></button>
                <div class="test-hint">
                    🧪 <strong>Cartões de teste:</strong> Final 0001=Aprovado | 0002=Rejeitado | 0003=Pendente | 0005=Cancelado
                </div>
            </div>

            <!-- CARTÃO DE DÉBITO -->
            <div id="form-debit_card" class="pm-form">
                <div class="form-group">
                    <label>Número do Cartão</label>
                    <input type="text" id="debit_number" placeholder="4111 1111 1111 0001" maxlength="19" oninput="formatCard(this)">
                </div>
                <div class="form-group">
                    <label>Nome no Cartão</label>
                    <input type="text" id="debit_holder" placeholder="NOME COMO ESTÁ NO CARTÃO" style="text-transform:uppercase">
                </div>
                <div class="form-row-3">
                    <div class="form-group">
                        <label>Mês</label>
                        <select id="debit_month">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= $m === 12 ? 'selected' : '' ?>><?= str_pad((string)$m, 2, '0', STR_PAD_LEFT) ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Ano</label>
                        <select id="debit_year">
                            <?php for ($y = 2026; $y <= 2036; $y++): ?>
                            <option value="<?= $y ?>" <?= $y === 2028 ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>CVV</label>
                        <input type="text" id="debit_cvv" placeholder="123" maxlength="4">
                    </div>
                </div>
                <button class="btn btn-pay" onclick="processPayment('debit_card')">Pagar R$ <?= number_format($payment['amount'], 2, ',', '.') ?></button>
                <div class="test-hint">
                    🧪 <strong>Cartões de teste:</strong> Final 0001=Aprovado | 0002=Rejeitado | 0003=Pendente
                </div>
            </div>

            <!-- PIX -->
            <div id="form-pix" class="pm-form">
                <div style="text-align:center;padding:1rem 0">
                    <div style="font-size:3rem;margin-bottom:0.5rem">📱</div>
                    <p style="font-size:0.95rem;color:var(--text-muted);margin-bottom:1rem">
                        Ao confirmar, um QR Code PIX será gerado.<br>
                        O pagamento é processado instantaneamente.
                    </p>
                    <div class="form-group" style="max-width:300px;margin:0 auto">
                        <label>Email</label>
                        <input type="email" id="pix_email" placeholder="seu@email.com" value="<?= htmlspecialchars($payment['payer']['email'] ?? '') ?>">
                    </div>
                    <div class="form-group" style="max-width:300px;margin:0 auto">
                        <label>CPF</label>
                        <input type="text" id="pix_cpf" placeholder="000.000.000-00" maxlength="14">
                    </div>
                </div>
                <button class="btn btn-pix" onclick="processPayment('pix')">Gerar PIX — R$ <?= number_format($payment['amount'], 2, ',', '.') ?></button>
            </div>

            <!-- BOLETO -->
            <div id="form-boleto" class="pm-form">
                <div style="text-align:center;padding:1rem 0">
                    <div style="font-size:3rem;margin-bottom:0.5rem">📄</div>
                    <p style="font-size:0.95rem;color:var(--text-muted);margin-bottom:1rem">
                        O boleto será gerado com vencimento em 3 dias úteis.<br>
                        O pagamento ficará pendente até a confirmação.
                    </p>
                    <div class="form-group" style="max-width:300px;margin:0 auto">
                        <label>Nome Completo</label>
                        <input type="text" id="boleto_name" placeholder="Seu nome completo" value="<?= htmlspecialchars($payment['payer']['name'] ?? '') ?>">
                    </div>
                    <div class="form-group" style="max-width:300px;margin:0 auto">
                        <label>CPF</label>
                        <input type="text" id="boleto_cpf" placeholder="000.000.000-00" maxlength="14">
                    </div>
                </div>
                <button class="btn btn-boleto" onclick="processPayment('boleto')">Gerar Boleto — R$ <?= number_format($payment['amount'], 2, ',', '.') ?></button>
            </div>
        </div>
    </div>

    <!-- SIDEBAR - Resumo -->
    <div class="col-summary">
        <div class="card">
            <div class="card-title">Resumo da compra</div>
            <?php if (!empty($payment['items'])): ?>
                <?php foreach ($payment['items'] as $item): ?>
                <div class="item-row">
                    <span><?= htmlspecialchars($item['title'] ?? 'Item') ?> <?= ($item['quantity'] ?? 1) > 1 ? 'x' . ($item['quantity'] ?? 1) : '' ?></span>
                    <span>R$ <?= number_format(($item['unit_price'] ?? 0) * ($item['quantity'] ?? 1), 2, ',', '.') ?></span>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="item-row">
                    <span><?= htmlspecialchars($payment['description'] ?: 'Pagamento') ?></span>
                    <span>R$ <?= number_format($payment['amount'], 2, ',', '.') ?></span>
                </div>
            <?php endif; ?>
            <div class="total-row">
                <span>Total</span>
                <span>R$ <?= number_format($payment['amount'], 2, ',', '.') ?></span>
            </div>
        </div>

        <div class="card">
            <div style="font-size:0.8rem;color:var(--text-muted)">
                <div style="margin-bottom:0.3rem"><strong>ID:</strong> <span class="mono"><?= $payment['id'] ?></span></div>
                <?php if (!empty($payment['external_reference'])): ?>
                <div style="margin-bottom:0.3rem"><strong>Ref:</strong> <?= htmlspecialchars($payment['external_reference']) ?></div>
                <?php endif; ?>
                <?php if (!empty($payment['payer']['name'])): ?>
                <div><strong>Pagador:</strong> <?= htmlspecialchars($payment['payer']['name']) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Overlay de resultado -->
<div class="result-overlay" id="resultOverlay">
    <div class="result-box">
        <div class="result-icon" id="resultIcon"></div>
        <div class="result-title" id="resultTitle"></div>
        <div class="result-detail" id="resultDetail"></div>
        <div class="result-id" id="resultId"></div>
        <div id="resultActions"></div>
    </div>
</div>

<div class="footer">
    Payment Gateway Simulator — Ambiente de Teste<br>
    <a href="/">Dashboard</a>
</div>

<script>
const paymentId = '<?= htmlspecialchars($paymentId) ?>';

function selectMethod(el) {
    document.querySelectorAll('.pm-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.pm-form').forEach(f => f.classList.remove('active'));
    el.classList.add('active');
    const method = el.dataset.method;
    document.getElementById('form-' + method).classList.add('active');
}

function formatCard(input) {
    let v = input.value.replace(/\D/g, '').substring(0, 16);
    v = v.replace(/(\d{4})(?=\d)/g, '$1 ');
    input.value = v;
}

async function processPayment(method) {
    const body = { payment_method: method };

    if (method === 'credit_card') {
        body.card = {
            number: document.getElementById('card_number').value.replace(/\s/g, ''),
            holder_name: document.getElementById('card_holder').value,
            expiration_month: parseInt(document.getElementById('card_month').value),
            expiration_year: parseInt(document.getElementById('card_year').value),
            cvv: document.getElementById('card_cvv').value
        };
        body.installments = parseInt(document.getElementById('installments').value);
        body.payer = { document: document.getElementById('card_cpf').value };
    } else if (method === 'debit_card') {
        body.card = {
            number: document.getElementById('debit_number').value.replace(/\s/g, ''),
            holder_name: document.getElementById('debit_holder').value,
            expiration_month: parseInt(document.getElementById('debit_month').value),
            expiration_year: parseInt(document.getElementById('debit_year').value),
            cvv: document.getElementById('debit_cvv').value
        };
    } else if (method === 'pix') {
        body.payer = {
            email: document.getElementById('pix_email').value,
            document: document.getElementById('pix_cpf').value
        };
    } else if (method === 'boleto') {
        body.payer = {
            name: document.getElementById('boleto_name').value,
            document: document.getElementById('boleto_cpf').value
        };
    }

    // Mostrar processando
    const overlay = document.getElementById('resultOverlay');
    overlay.classList.add('show');
    document.getElementById('resultIcon').innerHTML = '<div class="spinner"></div>';
    document.getElementById('resultTitle').textContent = 'Processando pagamento...';
    document.getElementById('resultDetail').textContent = 'Aguarde um momento';
    document.getElementById('resultId').textContent = '';
    document.getElementById('resultActions').innerHTML = '';

    try {
        const res = await fetch('/checkout/' + paymentId + '/process', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        });
        const data = await res.json();

        if (!res.ok) {
            showResult('⚠️', 'Erro', data.error || 'Erro desconhecido', '', null);
            return;
        }

        const payment = data.payment;
        const redirectUrl = data.redirect_url;

        const icons = { approved: '✅', rejected: '❌', pending: '⏳', in_process: '🔄', cancelled: '🚫', error: '⚠️' };
        const titles = {
            approved: 'Pagamento Aprovado!',
            rejected: 'Pagamento Rejeitado',
            pending: 'Pagamento Pendente',
            in_process: 'Em Processamento',
            cancelled: 'Pagamento Cancelado',
            error: 'Erro no Pagamento'
        };

        showResult(
            icons[payment.status] || '📋',
            titles[payment.status] || payment.status,
            payment.status_detail || '',
            payment.id,
            redirectUrl
        );
    } catch (err) {
        showResult('⚠️', 'Erro de rede', err.message, '', null);
    }
}

function showResult(icon, title, detail, id, redirectUrl) {
    document.getElementById('resultIcon').textContent = icon;
    document.getElementById('resultTitle').textContent = title;
    document.getElementById('resultDetail').textContent = detail;
    document.getElementById('resultId').textContent = id ? 'ID: ' + id : '';

    let actions = '';
    if (redirectUrl) {
        actions += '<a href="' + redirectUrl + '" class="btn-redirect">Continuar</a>';
        actions += '<p style="margin-top:0.8rem;font-size:0.8rem;color:#999">Redirecionando em 4 segundos...</p>';
    } else {
        actions += '<button onclick="location.reload()" class="btn-redirect" style="margin-right:0.5rem">OK</button> ';
        actions += '<a href="/" class="btn-redirect" style="background:#666">Dashboard</a>';
    }
    document.getElementById('resultActions').innerHTML = actions;

    if (redirectUrl) {
        setTimeout(function() { window.location.href = redirectUrl; }, 4000);
    }
}
</script>

</body>
</html>
