<?php
/**
 * PIX Checkout Page
 * Simula a página de pagamento PIX do Mercado Pago (ticket_url)
 */

require_once __DIR__ . '/../helpers.php';

// $pixPaymentId vem do router (index.php)
$payments = readJsonFile('payments.json');
$payment = $payments[$pixPaymentId] ?? null;

$pixCode = '';
$amount = 0;
$status = '';
$errorMsg = null;

if (!$payment) {
    $errorMsg = "Pagamento não encontrado: {$pixPaymentId}";
} else {
    $amount = $payment['transaction_amount'] ?? $payment['amount'] ?? 0;
    $status = $payment['status'] ?? 'pending';
    $pixCode = $payment['point_of_interaction']['transaction_data']['qr_code'] ?? 'PIX_CODE_SIMULADO_' . $pixPaymentId;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagar com PIX</title>
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
        .container {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.1);
            max-width: 440px;
            width: 100%;
            margin: 20px;
            overflow: hidden;
        }
        .header {
            background: #00b4d8;
            color: #fff;
            padding: 20px 24px;
            text-align: center;
        }
        .header h1 { font-size: 20px; font-weight: 600; }
        .header .sub { font-size: 13px; opacity: 0.85; margin-top: 4px; }

        .amount-box {
            text-align: center;
            padding: 24px;
            border-bottom: 1px solid #e8e8e8;
        }
        .amount-label { font-size: 14px; color: #666; }
        .amount-value {
            font-size: 36px;
            font-weight: 700;
            color: #333;
            margin-top: 4px;
        }
        .amount-value .currency { font-size: 18px; vertical-align: super; color: #666; }

        .pix-section {
            padding: 24px;
            text-align: center;
        }
        .qr-placeholder {
            width: 200px;
            height: 200px;
            margin: 0 auto 16px;
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            color: #666;
        }
        .qr-placeholder svg { margin-bottom: 8px; }
        .qr-placeholder span { font-size: 12px; }

        .pix-code-box {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 12px;
            margin: 16px 0;
            word-break: break-all;
            font-family: monospace;
            font-size: 11px;
            color: #495057;
            max-height: 80px;
            overflow-y: auto;
            cursor: pointer;
            position: relative;
        }
        .pix-code-box:hover { background: #e9ecef; }
        .copy-hint {
            font-size: 11px;
            color: #999;
            margin-top: 4px;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            margin: 12px 0;
        }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }

        .btn-pay {
            width: 100%;
            padding: 16px;
            background: #00b4d8;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
            margin-top: 16px;
        }
        .btn-pay:hover { background: #0096c7; }
        .btn-pay:disabled { background: #ccc; cursor: not-allowed; }
        .btn-pay.success { background: #27ae60; }

        .instructions {
            padding: 16px 24px;
            border-top: 1px solid #e8e8e8;
        }
        .instructions h3 { font-size: 14px; color: #333; margin-bottom: 10px; }
        .instructions ol {
            padding-left: 20px;
            font-size: 13px;
            color: #666;
            line-height: 1.8;
        }

        .footer {
            text-align: center;
            padding: 16px 24px;
            font-size: 12px;
            color: #999;
            border-top: 1px solid #e8e8e8;
        }
        .footer svg { vertical-align: middle; margin-right: 4px; }

        .error-container {
            padding: 40px 24px;
            text-align: center;
        }
        .error-container .icon { font-size: 48px; margin-bottom: 16px; }
        .error-container h2 { color: #e74c3c; margin-bottom: 8px; }
        .error-container p { color: #666; font-size: 14px; }

        .spinner {
            display: none;
            width: 22px;
            height: 22px;
            border: 3px solid rgba(255,255,255,0.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 0 auto;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        .timer { font-size: 13px; color: #999; margin-top: 8px; }
    </style>
</head>
<body>

<?php if ($errorMsg): ?>
    <div class="container">
        <div class="header">
            <h1>Pagamento PIX</h1>
            <div class="sub">Mercado Pago (Simulador)</div>
        </div>
        <div class="error-container">
            <div class="icon">&#10060;</div>
            <h2>Erro</h2>
            <p><?= htmlspecialchars($errorMsg) ?></p>
        </div>
    </div>

<?php elseif ($status === 'approved'): ?>
    <div class="container">
        <div class="header">
            <h1>Pagamento PIX</h1>
            <div class="sub">Mercado Pago (Simulador)</div>
        </div>
        <div class="amount-box">
            <div class="amount-label">Valor pago</div>
            <div class="amount-value">
                <span class="currency">R$</span> <?= number_format($amount, 2, ',', '.') ?>
            </div>
            <div class="status-badge status-approved">&#10003; Pagamento Aprovado</div>
        </div>
        <div class="pix-section">
            <p style="color:#155724;font-size:15px;font-weight:600;">Este pagamento j&aacute; foi confirmado!</p>
            <p style="color:#666;font-size:13px;margin-top:8px;">ID: <?= htmlspecialchars($pixPaymentId) ?></p>
        </div>
        <div class="footer">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#999" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            Pagamento simulado &mdash; Gateway Simulator v1.0
        </div>
    </div>

<?php else: ?>
    <div class="container">
        <div class="header">
            <h1>Pagamento PIX</h1>
            <div class="sub">Mercado Pago (Simulador)</div>
        </div>

        <div class="amount-box">
            <div class="amount-label">Valor a pagar</div>
            <div class="amount-value">
                <span class="currency">R$</span> <?= number_format($amount, 2, ',', '.') ?>
            </div>
            <div class="status-badge status-pending" id="statusBadge">&#9203; Aguardando Pagamento</div>
        </div>

        <div class="pix-section">
            <div class="qr-placeholder">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#adb5bd" stroke-width="1.5">
                    <rect x="2" y="2" width="8" height="8" rx="1"/>
                    <rect x="14" y="2" width="8" height="8" rx="1"/>
                    <rect x="2" y="14" width="8" height="8" rx="1"/>
                    <rect x="14" y="14" width="4" height="4" rx="0.5"/>
                    <rect x="20" y="14" width="2" height="2"/>
                    <rect x="14" y="20" width="2" height="2"/>
                    <rect x="18" y="18" width="4" height="4" rx="0.5"/>
                </svg>
                <span>QR Code Simulado</span>
            </div>

            <div class="pix-code-box" id="pixCodeBox" onclick="copyPixCode()" title="Clique para copiar">
                <?= htmlspecialchars($pixCode) ?>
            </div>
            <div class="copy-hint" id="copyHint">Clique para copiar o c&oacute;digo Pix Copia e Cola</div>

            <button class="btn-pay" id="btnPay" onclick="confirmPayment()">
                <span id="btnText">Simular Pagamento PIX</span>
                <div class="spinner" id="btnSpinner"></div>
            </button>

            <div class="timer" id="timer">Expira em 30:00</div>
        </div>

        <div class="instructions">
            <h3>Como pagar (ambiente real):</h3>
            <ol>
                <li>Abra o app do seu banco</li>
                <li>Acesse a &aacute;rea PIX e escaneie o QR Code</li>
                <li>Ou copie o c&oacute;digo e use "Pix Copia e Cola"</li>
                <li>Confirme o pagamento</li>
            </ol>
            <p style="margin-top:12px;font-size:12px;color:#999;"><strong>Simulador:</strong> Clique em "Simular Pagamento PIX" para aprovar.</p>
        </div>

        <div class="footer">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#999" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            Pagamento simulado &mdash; Gateway Simulator v1.0
        </div>
    </div>

    <script>
        function copyPixCode() {
            const code = document.getElementById('pixCodeBox').textContent.trim();
            navigator.clipboard.writeText(code).then(() => {
                document.getElementById('copyHint').textContent = 'Copiado!';
                setTimeout(() => {
                    document.getElementById('copyHint').textContent = 'Clique para copiar o código Pix Copia e Cola';
                }, 2000);
            });
        }

        async function confirmPayment() {
            const btn = document.getElementById('btnPay');
            const btnText = document.getElementById('btnText');
            const spinner = document.getElementById('btnSpinner');

            btn.disabled = true;
            btnText.style.display = 'none';
            spinner.style.display = 'block';

            try {
                const response = await fetch('/pix/<?= htmlspecialchars($pixPaymentId) ?>/confirm', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ status: 'approved' })
                });
                const data = await response.json();

                if (data.status === 'approved') {
                    btnText.textContent = 'Pagamento Aprovado!';
                    btnText.style.display = 'inline';
                    spinner.style.display = 'none';
                    btn.classList.add('success');
                    btn.disabled = true;

                    const badge = document.getElementById('statusBadge');
                    badge.className = 'status-badge status-approved';
                    badge.innerHTML = '&#10003; Pagamento Aprovado';

                    document.getElementById('timer').style.display = 'none';

                    // Redirecionar após 2 segundos se tiver back_url
                    <?php
                    $backUrl = $payment['back_urls']['success'] ?? $payment['back_url'] ?? null;
                    if ($backUrl): ?>
                    setTimeout(() => {
                        window.location.href = '<?= addslashes($backUrl) ?>';
                    }, 2500);
                    <?php endif; ?>
                } else {
                    btnText.textContent = data.status_detail || 'Erro ao processar';
                    btnText.style.display = 'inline';
                    spinner.style.display = 'none';
                    btn.disabled = false;
                }
            } catch (err) {
                btnText.textContent = 'Erro: ' + err.message;
                btnText.style.display = 'inline';
                spinner.style.display = 'none';
                btn.disabled = false;
            }
        }

        // Timer countdown (30 min)
        let seconds = 30 * 60;
        const timerEl = document.getElementById('timer');
        const timerInterval = setInterval(() => {
            seconds--;
            if (seconds <= 0) {
                clearInterval(timerInterval);
                timerEl.textContent = 'Expirado';
                return;
            }
            const m = Math.floor(seconds / 60);
            const s = seconds % 60;
            timerEl.textContent = `Expira em ${m}:${s.toString().padStart(2, '0')}`;
        }, 1000);
    </script>
<?php endif; ?>

</body>
</html>
