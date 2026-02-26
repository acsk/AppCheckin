<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compras Avulsas - Payment Gateway Simulator</title>
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
            --text: #cdd6f4;
            --text-muted: #a6adc8;
            --border: #585b70;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: var(--darker); color: var(--text); min-height: 100vh; }
        .header { background: var(--dark); border-bottom: 1px solid var(--border); padding: 1rem 2rem; display: flex; align-items: center; justify-content: space-between; }
        .header h1 { font-size: 1.25rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; }
        .header-links { display: flex; gap: 0.75rem; align-items: center; }
        .header-links a { color: var(--text-muted); text-decoration: none; padding: 0.4rem 0.8rem; background: var(--surface); border-radius: 0.5rem; font-size: 0.85rem; transition: all 0.2s; }
        .header-links a:hover { background: var(--primary); color: white; }
        .container { max-width: 1400px; margin: 0 auto; padding: 1.5rem; }
        .card { background: var(--dark); border: 1px solid var(--border); border-radius: 0.75rem; padding: 1.25rem; margin-bottom: 1rem; }
        .card-title { font-size: 1rem; font-weight: 600; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }

        /* Search/Filter bar */
        .filter-bar { display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: flex-end; margin-bottom: 1.5rem; }
        .filter-group { display: flex; flex-direction: column; gap: 0.25rem; }
        .filter-group label { font-size: 0.8rem; color: var(--text-muted); font-weight: 500; }
        .filter-group input, .filter-group select {
            padding: 0.5rem 0.75rem; background: var(--surface); border: 1px solid var(--border);
            border-radius: 0.5rem; color: var(--text); font-size: 0.85rem; outline: none;
        }
        .filter-group input:focus, .filter-group select:focus { border-color: var(--primary); }

        .btn { padding: 0.5rem 1rem; border: none; border-radius: 0.5rem; font-size: 0.85rem; font-weight: 500; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 0.4rem; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-outline { background: transparent; border: 1px solid var(--border); color: var(--text); }
        .btn-outline:hover { background: var(--surface); }
        .btn-sm { padding: 0.35rem 0.75rem; font-size: 0.8rem; }
        .btn-success { background: var(--success); color: white; }

        /* Table */
        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid var(--surface); font-size: 0.85rem; }
        th { color: var(--text-muted); font-weight: 600; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; }
        tr:hover td { background: var(--surface); }
        .mono { font-family: 'Fira Code', 'Consolas', monospace; font-size: 0.8rem; }

        /* Status badges */
        .status { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 1rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
        .status-approved { background: rgba(34,197,94,0.15); color: var(--success); }
        .status-rejected { background: rgba(239,68,68,0.15); color: var(--danger); }
        .status-pending { background: rgba(245,158,11,0.15); color: var(--warning); }
        .status-in_process { background: rgba(59,130,246,0.15); color: var(--info); }
        .status-cancelled { background: rgba(148,163,184,0.15); color: #94a3b8; }
        .status-error { background: rgba(239,68,68,0.15); color: var(--danger); }

        .empty-state { text-align: center; padding: 3rem 1rem; color: var(--text-muted); }
        .empty-state .icon { font-size: 3rem; margin-bottom: 1rem; }

        /* Stats */
        .stats-bar { display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
        .stat-mini { background: var(--surface); border-radius: 0.75rem; padding: 1rem 1.5rem; text-align: center; min-width: 120px; }
        .stat-mini .value { font-size: 1.5rem; font-weight: 700; }
        .stat-mini .label { font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; margin-top: 0.2rem; }
        .stat-mini.total .value { color: var(--info); }
        .stat-mini.approved .value { color: var(--success); }
        .stat-mini.pending .value { color: var(--warning); }
        .stat-mini.rejected .value { color: var(--danger); }

        /* Detail modal */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 1000; align-items: center; justify-content: center; }
        .modal-overlay.active { display: flex; }
        .modal { background: var(--dark); border: 1px solid var(--border); border-radius: 0.75rem; padding: 1.5rem; max-width: 700px; width: 90%; max-height: 80vh; overflow-y: auto; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        .modal-close { background: none; border: none; color: var(--text-muted); font-size: 1.5rem; cursor: pointer; }
        .json-viewer { background: var(--darker); border: 1px solid var(--border); border-radius: 0.5rem; padding: 1rem; font-family: 'Fira Code', monospace; font-size: 0.8rem; white-space: pre-wrap; word-wrap: break-word; max-height: 400px; overflow: auto; color: #89b4fa; }

        .toast-container { position: fixed; top: 1rem; right: 1rem; z-index: 9999; display: flex; flex-direction: column; gap: 0.5rem; }
        .toast { padding: 0.75rem 1.25rem; border-radius: 0.5rem; font-size: 0.85rem; font-weight: 500; min-width: 280px; animation: slideIn 0.3s ease; box-shadow: 0 4px 12px rgba(0,0,0,0.3); }
        .toast-success { background: var(--success); color: white; }
        .toast-error { background: var(--danger); color: white; }
        .toast-info { background: var(--info); color: white; }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
    </style>
</head>
<body>

<div class="header">
    <h1>🛒 Compras Avulsas</h1>
    <div class="header-links">
        <a href="/">💳 Dashboard</a>
        <a href="/recurring">🔄 Recorrentes</a>
    </div>
</div>

<div class="container">
    <!-- Stats -->
    <div class="stats-bar" id="stats-bar">
        <div class="stat-mini total"><div class="value" id="stat-total">0</div><div class="label">Total</div></div>
        <div class="stat-mini approved"><div class="value" id="stat-approved">0</div><div class="label">Aprovados</div></div>
        <div class="stat-mini pending"><div class="value" id="stat-pending">0</div><div class="label">Pendentes</div></div>
        <div class="stat-mini rejected"><div class="value" id="stat-rejected">0</div><div class="label">Rejeitados</div></div>
    </div>

    <!-- Filters -->
    <div class="card">
        <div class="filter-bar">
            <div class="filter-group">
                <label>External Reference</label>
                <input type="text" id="filter-ref" placeholder="MAT-190-..." style="min-width:220px">
            </div>
            <div class="filter-group">
                <label>Status</label>
                <select id="filter-status">
                    <option value="">Todos</option>
                    <option value="approved">Aprovado</option>
                    <option value="pending">Pendente</option>
                    <option value="rejected">Rejeitado</option>
                    <option value="in_process">Em Processo</option>
                    <option value="cancelled">Cancelado</option>
                    <option value="error">Erro</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Método</label>
                <select id="filter-method">
                    <option value="">Todos</option>
                    <option value="pix">PIX</option>
                    <option value="credit_card">Cartão de Crédito</option>
                    <option value="debit_card">Cartão de Débito</option>
                    <option value="boleto">Boleto</option>
                </select>
            </div>
            <div class="filter-group">
                <label>&nbsp;</label>
                <div style="display:flex;gap:0.5rem">
                    <button class="btn btn-primary" onclick="loadPurchases()">🔍 Buscar</button>
                    <button class="btn btn-outline" onclick="clearFilters()">🗑️ Limpar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Results -->
    <div class="card">
        <div class="card-title" style="justify-content: space-between;">
            <span>📋 Pagamentos Avulsos (sem assinatura)</span>
            <button class="btn btn-sm btn-outline" onclick="loadPurchases()">🔄 Atualizar</button>
        </div>
        <div class="table-container" id="purchases-table">
            <div class="empty-state"><div class="icon">📭</div><p>Carregando...</p></div>
        </div>
    </div>
</div>

<!-- Detail Modal -->
<div class="modal-overlay" id="detail-modal">
    <div class="modal">
        <div class="modal-header">
            <h3>📄 Detalhes do Pagamento</h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div id="detail-content"></div>
    </div>
</div>

<div class="toast-container" id="toast-container"></div>

<script>
function toast(msg, type = 'info') {
    const c = document.getElementById('toast-container');
    const el = document.createElement('div');
    el.className = `toast toast-${type}`;
    el.textContent = msg;
    c.appendChild(el);
    setTimeout(() => el.remove(), 4000);
}

function formatDate(d) {
    if (!d) return '-';
    return new Date(d).toLocaleString('pt-BR');
}

function formatMoney(v) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v || 0);
}

function statusBadge(s) {
    return `<span class="status status-${s}">${s}</span>`;
}

function methodLabel(m) {
    const map = { pix: '🟢 PIX', credit_card: '💳 Crédito', debit_card: '💳 Débito', boleto: '📄 Boleto', bank_transfer: '🏦 Transferência', account_money: '💰 Saldo' };
    return map[m] || m || '-';
}

async function loadPurchases() {
    const ref = document.getElementById('filter-ref').value.trim();
    const status = document.getElementById('filter-status').value;
    const method = document.getElementById('filter-method').value;

    const params = new URLSearchParams();
    if (ref) params.set('external_reference', ref);
    if (status) params.set('status', status);
    if (method) params.set('payment_method', method);
    params.set('limit', '100');

    try {
        const res = await fetch('/api/purchases?' + params.toString());
        const data = await res.json();
        const items = data.data || [];

        // Stats
        const total = items.length;
        const approved = items.filter(p => p.status === 'approved').length;
        const pending = items.filter(p => ['pending', 'in_process'].includes(p.status)).length;
        const rejected = items.filter(p => ['rejected', 'error', 'cancelled'].includes(p.status)).length;

        document.getElementById('stat-total').textContent = total;
        document.getElementById('stat-approved').textContent = approved;
        document.getElementById('stat-pending').textContent = pending;
        document.getElementById('stat-rejected').textContent = rejected;

        if (!items.length) {
            document.getElementById('purchases-table').innerHTML = '<div class="empty-state"><div class="icon">📭</div><p>Nenhuma compra avulsa encontrada.</p></div>';
            return;
        }

        document.getElementById('purchases-table').innerHTML = `
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>External Ref</th>
                        <th>Status</th>
                        <th>Valor</th>
                        <th>Método</th>
                        <th>Pagador</th>
                        <th>Descrição</th>
                        <th>Data</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>${items.map(p => `
                    <tr>
                        <td class="mono" style="font-size:0.75rem">${p.id}</td>
                        <td class="mono" style="font-size:0.75rem">${p.external_reference || '-'}</td>
                        <td>${statusBadge(p.status)}</td>
                        <td>${formatMoney(p.transaction_amount || p.amount)}</td>
                        <td>${methodLabel(p.payment_method || p.payment_method_id)}</td>
                        <td>${p.payer?.email || p.payer?.name || '-'}</td>
                        <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${p.description || '-'}</td>
                        <td style="white-space:nowrap">${formatDate(p.created_at || p.date_created)}</td>
                        <td><button class="btn btn-sm btn-outline" onclick='showDetail(${JSON.stringify(p).replace(/'/g, "&#39;")})'>👁️</button></td>
                    </tr>
                `).join('')}</tbody>
            </table>`;
    } catch (err) {
        toast('Erro ao buscar: ' + err.message, 'error');
    }
}

function showDetail(payment) {
    document.getElementById('detail-content').innerHTML = `
        <div style="margin-bottom:1rem;display:flex;gap:1rem;flex-wrap:wrap">
            <div><strong>ID:</strong> <span class="mono">${payment.id}</span></div>
            <div><strong>Status:</strong> ${statusBadge(payment.status)}</div>
            <div><strong>Valor:</strong> ${formatMoney(payment.transaction_amount || payment.amount)}</div>
            <div><strong>Método:</strong> ${methodLabel(payment.payment_method || payment.payment_method_id)}</div>
        </div>
        <div style="margin-bottom:1rem;display:flex;gap:1rem;flex-wrap:wrap">
            <div><strong>External Ref:</strong> <span class="mono">${payment.external_reference || '-'}</span></div>
            <div><strong>Data:</strong> ${formatDate(payment.created_at || payment.date_created)}</div>
            ${payment.date_approved ? `<div><strong>Aprovado em:</strong> ${formatDate(payment.date_approved)}</div>` : ''}
        </div>
        <div class="json-viewer">${JSON.stringify(payment, null, 2)}</div>
    `;
    document.getElementById('detail-modal').classList.add('active');
}

function closeModal() {
    document.getElementById('detail-modal').classList.remove('active');
}

function clearFilters() {
    document.getElementById('filter-ref').value = '';
    document.getElementById('filter-status').value = '';
    document.getElementById('filter-method').value = '';
    loadPurchases();
}

// Fechar modal ao clicar fora
document.getElementById('detail-modal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

// Auto-preencher filtro da URL
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('ref') || urlParams.get('external_reference')) {
    document.getElementById('filter-ref').value = urlParams.get('ref') || urlParams.get('external_reference');
}

// Carregar ao iniciar
loadPurchases();
</script>
</body>
</html>
