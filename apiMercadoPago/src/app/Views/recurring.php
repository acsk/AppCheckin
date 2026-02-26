<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simulador de Baixas Recorrentes</title>
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --success: #22c55e;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --dark: #1e1e2e;
            --darker: #181825;
            --surface: #313244;
            --surface-light: #45475a;
            --text: #cdd6f4;
            --text-muted: #a6adc8;
            --border: #585b70;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: var(--darker);
            color: var(--text);
            min-height: 100vh;
        }

        .header {
            background: var(--dark);
            border-bottom: 1px solid var(--border);
            padding: 1rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .header h1 {
            font-size: 1.25rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .header h1 .icon { font-size: 1.5rem; }

        .header .badge {
            background: var(--primary);
            color: white;
            padding: 0.2rem 0.7rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .header-actions {
            display: flex; gap: 0.75rem; align-items: center;
        }

        .header-actions a {
            color: var(--text-muted);
            text-decoration: none;
            padding: 0.4rem 0.8rem;
            border-radius: 0.5rem;
            font-size: 0.85rem;
            transition: all 0.2s;
        }

        .header-actions a:hover {
            background: var(--surface);
            color: var(--text);
        }

        .container {
            max-width: 960px;
            margin: 0 auto;
            padding: 2rem 1.5rem;
        }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .card h2 {
            font-size: 1.1rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 0.3rem;
            font-weight: 500;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.65rem 0.9rem;
            background: var(--dark);
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            color: var(--text);
            font-size: 0.9rem;
            outline: none;
            transition: border-color 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: var(--primary);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.7rem 1.5rem;
            border: none;
            border-radius: 0.5rem;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            width: 100%;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #16a34a;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Subscription Info Card */
        .sub-info {
            display: none;
            animation: fadeIn 0.3s ease;
        }

        .sub-info.visible {
            display: block;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
        }

        .info-item {
            background: var(--dark);
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            border-left: 3px solid var(--primary);
        }

        .info-item.full {
            grid-column: 1 / -1;
        }

        .info-item .label {
            font-size: 0.75rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.2rem;
        }

        .info-item .value {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text);
        }

        .info-item .value.success { color: var(--success); }
        .info-item .value.warning { color: var(--warning); }
        .info-item .value.danger { color: var(--danger); }
        .info-item .value.info { color: var(--info); }

        /* Status badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.15rem 0.6rem;
            border-radius: 1rem;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-badge.authorized { background: rgba(34, 197, 94, 0.15); color: var(--success); }
        .status-badge.pending { background: rgba(245, 158, 11, 0.15); color: var(--warning); }
        .status-badge.paused { background: rgba(59, 130, 246, 0.15); color: var(--info); }
        .status-badge.cancelled { background: rgba(239, 68, 68, 0.15); color: var(--danger); }

        /* Webhook Log */
        .log-section {
            display: none;
        }

        .log-section.visible {
            display: block;
        }

        .log-entry {
            background: var(--dark);
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 0.75rem;
            animation: slideIn 0.3s ease;
        }

        .log-entry .log-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        .log-entry .log-title {
            font-weight: 600;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .log-entry .log-time {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .log-entry .log-details {
            font-size: 0.8rem;
            color: var(--text-muted);
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.3rem;
        }

        .log-entry .log-details span {
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .http-code {
            display: inline-flex;
            align-items: center;
            padding: 0.1rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 700;
            font-family: monospace;
        }

        .http-code.success { background: rgba(34,197,94,0.2); color: var(--success); }
        .http-code.error { background: rgba(239,68,68,0.2); color: var(--danger); }

        /* Loading Spinner */
        .spinner {
            display: inline-block;
            width: 1rem;
            height: 1rem;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }

        .empty-state {
            text-align: center;
            padding: 2rem;
            color: var(--text-muted);
        }

        .empty-state .icon {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }

        /* Alert */
        .alert {
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            font-size: 0.85rem;
            display: none;
        }

        .alert.visible {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            animation: fadeIn 0.3s ease;
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
        }

        .alert-success {
            background: rgba(34, 197, 94, 0.15);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #86efac;
        }

        .alert-warning {
            background: rgba(245, 158, 11, 0.15);
            border: 1px solid rgba(245, 158, 11, 0.3);
            color: #fcd34d;
        }

        .divider {
            height: 1px;
            background: var(--border);
            margin: 1.25rem 0;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        @media (max-width: 640px) {
            .info-grid { grid-template-columns: 1fr; }
            .form-row { grid-template-columns: 1fr; }
            .log-entry .log-details { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="header">
    <h1>
        <span class="icon">🔄</span>
        Simulador de Baixas Recorrentes
        <span class="badge">MP Simulator</span>
    </h1>
    <div class="header-actions">
        <a href="/">📊 Dashboard</a>
    </div>
</div>

<div class="container">

    <!-- Buscar Assinatura -->
    <div class="card">
        <h2>🔍 Buscar Assinatura</h2>
        <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">
            Informe o <strong>external_reference</strong> (ex: MAT-189-1772063327) para buscar a assinatura e simular cobranças recorrentes.
        </p>

        <div id="alertSearch" class="alert alert-danger">
            <span>⚠️</span>
            <span id="alertSearchText"></span>
        </div>

        <div class="form-group">
            <label for="externalRef">External Reference</label>
            <input type="text" id="externalRef" placeholder="MAT-189-1772063327" autocomplete="off">
        </div>

        <button class="btn btn-primary" id="btnSearch" onclick="searchSubscription()">
            🔍 Buscar Assinatura
        </button>
    </div>

    <!-- Info da Assinatura -->
    <div class="card sub-info" id="subInfoCard">
        <h2>📋 Assinatura Encontrada</h2>

        <div class="info-grid">
            <div class="info-item">
                <div class="label">ID Assinatura</div>
                <div class="value" id="infoId">—</div>
            </div>
            <div class="info-item">
                <div class="label">Status</div>
                <div class="value" id="infoStatus">—</div>
            </div>
            <div class="info-item full">
                <div class="label">Plano</div>
                <div class="value" id="infoReason">—</div>
            </div>
            <div class="info-item">
                <div class="label">External Reference</div>
                <div class="value info" id="infoExtRef">—</div>
            </div>
            <div class="info-item">
                <div class="label">E-mail do Pagador</div>
                <div class="value" id="infoPayer">—</div>
            </div>
            <div class="info-item">
                <div class="label">Valor Recorrente</div>
                <div class="value success" id="infoAmount">—</div>
            </div>
            <div class="info-item">
                <div class="label">Frequência</div>
                <div class="value" id="infoFrequency">—</div>
            </div>
            <div class="info-item">
                <div class="label">Próximo Vencimento</div>
                <div class="value warning" id="infoNextDate">—</div>
            </div>
            <div class="info-item">
                <div class="label">Cobranças Realizadas</div>
                <div class="value" id="infoCharged">—</div>
            </div>
            <div class="info-item">
                <div class="label">Total Cobrado</div>
                <div class="value success" id="infoChargedAmount">—</div>
            </div>
            <div class="info-item">
                <div class="label">Última Cobrança</div>
                <div class="value" id="infoLastCharged">—</div>
            </div>
        </div>

        <div class="divider"></div>

        <h2>⚡ Simular Baixa Recorrente</h2>
        <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">
            Clique no botão para simular uma cobrança automática. O pagamento será gerado e o webhook será enviado para o cliente.
        </p>

        <div id="alertAction" class="alert alert-success">
            <span id="alertActionIcon">✅</span>
            <span id="alertActionText"></span>
        </div>

        <div class="form-row" style="margin-bottom: 1rem;">
            <div class="form-group" style="margin-bottom: 0;">
                <label for="simulateStatus">Status do Pagamento</label>
                <select id="simulateStatus">
                    <option value="approved" selected>✅ Aprovado (accredited)</option>
                    <option value="rejected">❌ Rejeitado (cc_rejected)</option>
                    <option value="pending">⏳ Pendente (pending_waiting)</option>
                    <option value="in_process">🔄 Em Processamento</option>
                    <option value="cancelled">🚫 Cancelado (expired)</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label for="paymentMethod">Método de Pagamento</label>
                <select id="paymentMethod">
                    <option value="account_money" selected>💰 Saldo em Conta</option>
                    <option value="visa">💳 Visa</option>
                    <option value="master">💳 Mastercard</option>
                    <option value="elo">💳 Elo</option>
                    <option value="pix">📱 PIX</option>
                </select>
            </div>
        </div>

        <button class="btn btn-success" id="btnCharge" onclick="simulateCharge()">
            ⚡ Gerar Cobrança e Enviar Webhook
        </button>
    </div>

    <!-- Log de Webhooks -->
    <div class="card log-section" id="logSection">
        <h2>📡 Log de Webhooks Enviados</h2>
        <div id="logContainer">
            <div class="empty-state">
                <div class="icon">📭</div>
                <p>Nenhum webhook enviado ainda</p>
            </div>
        </div>
    </div>

</div>

<script>
let currentSubscription = null;
let webhookLogs = [];

async function searchSubscription() {
    const extRef = document.getElementById('externalRef').value.trim();
    if (!extRef) {
        showAlert('alertSearch', 'alert-danger', '⚠️', 'Informe o external_reference');
        return;
    }

    const btn = document.getElementById('btnSearch');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Buscando...';
    hideAlert('alertSearch');
    hideAlert('alertAction');

    try {
        // Buscar assinaturas com esse external_reference
        const resp = await fetch('/api/recurring/search?external_reference=' + encodeURIComponent(extRef));
        const data = await resp.json();

        if (!resp.ok || !data.subscription) {
            showAlert('alertSearch', 'alert-danger', '⚠️', data.error || 'Assinatura não encontrada para esse external_reference.');
            document.getElementById('subInfoCard').classList.remove('visible');
            document.getElementById('logSection').classList.remove('visible');
            return;
        }

        currentSubscription = data.subscription;
        populateSubscriptionInfo(data.subscription);
        document.getElementById('subInfoCard').classList.add('visible');
        document.getElementById('logSection').classList.add('visible');

        // Carregar logs de webhooks relacionados
        loadWebhookLogs();

    } catch (err) {
        showAlert('alertSearch', 'alert-danger', '⚠️', 'Erro ao buscar: ' + err.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = '🔍 Buscar Assinatura';
    }
}

function populateSubscriptionInfo(sub) {
    document.getElementById('infoId').textContent = sub.id;
    
    const statusMap = {
        authorized: { text: '✅ Autorizada', cls: 'success' },
        pending: { text: '⏳ Pendente', cls: 'warning' },
        paused: { text: '⏸️ Pausada', cls: 'info' },
        cancelled: { text: '❌ Cancelada', cls: 'danger' },
    };
    const st = statusMap[sub.status] || { text: sub.status, cls: '' };
    const statusEl = document.getElementById('infoStatus');
    statusEl.innerHTML = `<span class="status-badge ${sub.status}">${st.text}</span>`;

    document.getElementById('infoReason').textContent = sub.reason || '—';
    document.getElementById('infoExtRef').textContent = sub.external_reference || '—';
    document.getElementById('infoPayer').textContent = sub.payer_email || '—';

    const ar = sub.auto_recurring || {};
    const amount = parseFloat(ar.transaction_amount || 0);
    document.getElementById('infoAmount').textContent = 'R$ ' + amount.toFixed(2);

    const freqType = { days: 'dia(s)', months: 'mês(es)', years: 'ano(s)' };
    document.getElementById('infoFrequency').textContent = 
        `A cada ${ar.frequency || 1} ${freqType[ar.frequency_type] || ar.frequency_type || 'mês(es)'}`;

    const nextDate = sub.next_payment_date;
    if (nextDate) {
        const d = new Date(nextDate);
        document.getElementById('infoNextDate').textContent = d.toLocaleDateString('pt-BR') + ' ' + d.toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'});
    } else {
        document.getElementById('infoNextDate').textContent = '—';
    }

    const summ = sub.summarized || {};
    document.getElementById('infoCharged').textContent = 
        `${summ.charged_quantity || 0} de ${summ.quotas || '∞'}`;
    document.getElementById('infoChargedAmount').textContent = 
        'R$ ' + (summ.charged_amount || 0).toFixed(2);

    if (summ.last_charged_date) {
        const lcd = new Date(summ.last_charged_date);
        document.getElementById('infoLastCharged').textContent = 
            lcd.toLocaleDateString('pt-BR') + ' — R$ ' + (summ.last_charged_amount || 0).toFixed(2);
    } else {
        document.getElementById('infoLastCharged').textContent = 'Nenhuma';
    }
}

async function simulateCharge() {
    if (!currentSubscription) return;

    const btn = document.getElementById('btnCharge');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Processando cobrança...';
    hideAlert('alertAction');

    const status = document.getElementById('simulateStatus').value;
    const paymentMethod = document.getElementById('paymentMethod').value;

    try {
        const resp = await fetch('/api/recurring/charge', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                preapproval_id: currentSubscription.id,
                _simulate_status: status,
                payment_method_id: paymentMethod,
            }),
        });

        const data = await resp.json();

        if (!resp.ok) {
            showAlert('alertAction', 'alert-danger', '⚠️', data.error || 'Erro ao gerar cobrança.');
            return;
        }

        // Sucesso
        const statusEmoji = {
            approved: '✅', rejected: '❌', pending: '⏳', 
            in_process: '🔄', cancelled: '🚫', error: '⚠️'
        };

        showAlert('alertAction', 
            status === 'approved' ? 'alert-success' : (status === 'rejected' ? 'alert-danger' : 'alert-warning'),
            statusEmoji[status] || '📦',
            `Pagamento #${data.payment.id} gerado com status "${data.payment.status}" — webhook ${data.webhook_sent ? 'enviado' : 'não enviado'}`
        );

        // Atualizar dados da assinatura
        if (data.subscription) {
            currentSubscription = data.subscription;
            populateSubscriptionInfo(data.subscription);
        }

        // Adicionar log
        if (data.webhook_log) {
            addLogEntry(data.webhook_log, data.payment);
        }

        // Carregar logs atualizados
        await loadWebhookLogs();

    } catch (err) {
        showAlert('alertAction', 'alert-danger', '⚠️', 'Erro: ' + err.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = '⚡ Gerar Cobrança e Enviar Webhook';
    }
}

function addLogEntry(log, payment) {
    const container = document.getElementById('logContainer');
    
    // Remover empty state
    const empty = container.querySelector('.empty-state');
    if (empty) empty.remove();

    const isSuccess = log.http_status >= 200 && log.http_status < 300;
    const entry = document.createElement('div');
    entry.className = 'log-entry';
    entry.innerHTML = `
        <div class="log-header">
            <div class="log-title">
                ${isSuccess ? '✅' : '❌'} Webhook ${log.event || 'payment'}
                <span class="http-code ${isSuccess ? 'success' : 'error'}">HTTP ${log.http_status}</span>
            </div>
            <div class="log-time">${new Date().toLocaleTimeString('pt-BR')}</div>
        </div>
        <div class="log-details">
            <span>💳 Payment ID: ${payment?.id || log.resource_id || '—'}</span>
            <span>📊 Status: ${payment?.status || '—'}</span>
            <span>🌐 URL: ${log.url || '—'}</span>
            <span>💰 Valor: R$ ${payment?.transaction_amount?.toFixed(2) || '—'}</span>
        </div>
        ${log.response_body ? `<details style="margin-top: 0.5rem"><summary style="font-size: 0.75rem; color: var(--text-muted); cursor: pointer;">Resposta do servidor</summary><pre style="font-size: 0.7rem; color: var(--text-muted); margin-top: 0.3rem; white-space: pre-wrap; word-break: break-all; max-height: 100px; overflow-y: auto;">${escapeHtml(log.response_body)}</pre></details>` : ''}
    `;

    container.insertBefore(entry, container.firstChild);
}

async function loadWebhookLogs() {
    try {
        const resp = await fetch('/api/webhook-logs');
        const logs = await resp.json();

        // Filtrar logs relevantes para esta assinatura
        if (!currentSubscription) return;

        const container = document.getElementById('logContainer');
        container.innerHTML = '';

        const relevantLogs = logs.filter(l => {
            // Buscar nos payments que tem subscription_id correspondente
            return l.event === 'payment' || l.event === 'subscription_preapproval';
        }).slice(0, 20); // Últimos 20

        if (relevantLogs.length === 0) {
            container.innerHTML = '<div class="empty-state"><div class="icon">📭</div><p>Nenhum webhook enviado ainda</p></div>';
            return;
        }

        relevantLogs.forEach(log => {
            const isSuccess = log.success || (log.http_status >= 200 && log.http_status < 300);
            const entry = document.createElement('div');
            entry.className = 'log-entry';
            entry.innerHTML = `
                <div class="log-header">
                    <div class="log-title">
                        ${isSuccess ? '✅' : '❌'} Webhook ${log.event || '—'}
                        <span class="http-code ${isSuccess ? 'success' : 'error'}">HTTP ${log.http_status}</span>
                    </div>
                    <div class="log-time">${log.sent_at ? new Date(log.sent_at).toLocaleTimeString('pt-BR') : '—'}</div>
                </div>
                <div class="log-details">
                    <span>🆔 Resource: ${log.resource_id || '—'}</span>
                    <span>🌐 URL: ${(log.url || '—').replace('host.docker.internal', 'localhost')}</span>
                </div>
                ${log.response_body ? `<details style="margin-top: 0.5rem"><summary style="font-size: 0.75rem; color: var(--text-muted); cursor: pointer;">Resposta</summary><pre style="font-size: 0.7rem; color: var(--text-muted); margin-top: 0.3rem; white-space: pre-wrap; word-break: break-all; max-height: 100px; overflow-y: auto;">${escapeHtml(log.response_body)}</pre></details>` : ''}
            `;
            container.appendChild(entry);
        });

    } catch (err) {
        console.error('Erro ao carregar logs:', err);
    }
}

function showAlert(id, cls, icon, text) {
    const el = document.getElementById(id);
    el.className = `alert ${cls} visible`;
    const iconEl = el.querySelector('span:first-child') || document.getElementById(id + 'Icon');
    const textEl = el.querySelector('span:last-child') || document.getElementById(id + 'Text');
    if (iconEl) iconEl.textContent = icon;
    if (textEl) textEl.textContent = text;
}

function hideAlert(id) {
    document.getElementById(id).classList.remove('visible');
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// Permitir buscar com Enter
document.getElementById('externalRef').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') searchSubscription();
});

// Se tiver external_reference na URL, preencher
const urlParams = new URLSearchParams(window.location.search);
const refFromUrl = urlParams.get('ref') || urlParams.get('external_reference');
if (refFromUrl) {
    document.getElementById('externalRef').value = refFromUrl;
    searchSubscription();
}
</script>

</body>
</html>
