<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Gateway Simulator</title>
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

        /* Header */
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

        /* Layout */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1.5rem;
        }

        .tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid var(--surface);
            padding-bottom: 0;
        }

        .tab {
            padding: 0.75rem 1.25rem;
            background: transparent;
            color: var(--text-muted);
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            transition: all 0.2s;
        }

        .tab:hover { color: var(--text); }
        .tab.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }

        .tab-content { display: none; }
        .tab-content.active { display: block; }

        /* Cards & Panels */
        .card {
            background: var(--dark);
            border: 1px solid var(--border);
            border-radius: 0.75rem;
            padding: 1.25rem;
            margin-bottom: 1rem;
        }

        .card-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; }
        .grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; }

        @media (max-width: 768px) {
            .grid-2, .grid-3, .grid-4 { grid-template-columns: 1fr; }
        }

        /* Stats */
        .stat-card {
            background: var(--surface);
            border-radius: 0.75rem;
            padding: 1.25rem;
            text-align: center;
        }

        .stat-card .value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .stat-card .label {
            color: var(--text-muted);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .stat-card.approved .value { color: var(--success); }
        .stat-card.rejected .value { color: var(--danger); }
        .stat-card.pending .value { color: var(--warning); }
        .stat-card.total .value { color: var(--info); }

        /* Forms */
        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 500;
            margin-bottom: 0.35rem;
            color: var(--text-muted);
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.6rem 0.85rem;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            color: var(--text);
            font-size: 0.9rem;
            outline: none;
            transition: border 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: var(--primary);
        }

        .form-group textarea {
            min-height: 80px;
            resize: vertical;
        }

        /* Buttons */
        .btn {
            padding: 0.6rem 1.25rem;
            border: none;
            border-radius: 0.5rem;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }

        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-success { background: var(--success); color: white; }
        .btn-success:hover { opacity: 0.9; }
        .btn-danger { background: var(--danger); color: white; }
        .btn-danger:hover { opacity: 0.9; }
        .btn-warning { background: var(--warning); color: #000; }
        .btn-warning:hover { opacity: 0.9; }
        .btn-sm { padding: 0.35rem 0.75rem; font-size: 0.8rem; }
        .btn-outline {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text);
        }
        .btn-outline:hover { background: var(--surface); }

        /* Table */
        .table-container { overflow-x: auto; }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 0.75rem 1rem;
            text-align: left;
            border-bottom: 1px solid var(--surface);
            font-size: 0.85rem;
        }

        th {
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
        }

        tr:hover td { background: var(--surface); }

        /* Status badges */
        .status {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-approved { background: rgba(34,197,94,0.15); color: var(--success); }
        .status-rejected { background: rgba(239,68,68,0.15); color: var(--danger); }
        .status-pending { background: rgba(245,158,11,0.15); color: var(--warning); }
        .status-in_process { background: rgba(59,130,246,0.15); color: var(--info); }
        .status-cancelled { background: rgba(148,163,184,0.15); color: #94a3b8; }
        .status-refunded { background: rgba(168,85,247,0.15); color: #a855f7; }
        .status-charged_back { background: rgba(236,72,153,0.15); color: #ec4899; }
        .status-error { background: rgba(239,68,68,0.15); color: var(--danger); }

        /* Webhook log status */
        .wh-success { color: var(--success); font-weight: 600; }
        .wh-fail { color: var(--danger); font-weight: 600; }

        /* Toast */
        .toast-container {
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .toast {
            padding: 0.75rem 1.25rem;
            border-radius: 0.5rem;
            font-size: 0.85rem;
            font-weight: 500;
            min-width: 280px;
            animation: slideIn 0.3s ease;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }

        .toast-success { background: var(--success); color: white; }
        .toast-error { background: var(--danger); color: white; }
        .toast-info { background: var(--info); color: white; }

        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        /* JSON viewer */
        .json-viewer {
            background: var(--darker);
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            padding: 1rem;
            font-family: 'Fira Code', 'Consolas', monospace;
            font-size: 0.8rem;
            white-space: pre-wrap;
            word-wrap: break-word;
            max-height: 400px;
            overflow: auto;
            color: #89b4fa;
        }

        /* Quick Actions */
        .quick-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-muted);
        }

        .empty-state .icon { font-size: 3rem; margin-bottom: 1rem; }
        .empty-state p { font-size: 0.9rem; }

        /* Monospace */
        .mono { font-family: 'Fira Code', 'Consolas', monospace; font-size: 0.8rem; }

        /* Section spacing */
        .section { margin-bottom: 1.5rem; }

        .flex-between {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .mb-1 { margin-bottom: 1rem; }
        .mt-1 { margin-top: 1rem; }
    </style>
</head>
<body>

<div class="header">
    <h1>
        <span class="icon">💳</span>
        Payment Gateway Simulator
    </h1>
    <div style="display: flex; gap: 0.75rem; align-items: center;">
        <a href="/recurring" style="color: #a6adc8; text-decoration: none; padding: 0.4rem 0.8rem; background: #313244; border-radius: 0.5rem; font-size: 0.85rem; transition: all 0.2s;">🔄 Baixas Recorrentes</a>
        <span class="badge">v1.0</span>
    </div>
</div>

<div class="container">
    <!-- Tabs -->
    <div class="tabs">
        <button class="tab active" onclick="switchTab('dashboard')">📊 Dashboard</button>
        <button class="tab" onclick="switchTab('create-payment')">💰 Novo Pagamento</button>
        <button class="tab" onclick="switchTab('payments')">📋 Pagamentos</button>
        <button class="tab" onclick="switchTab('simulator')">🎮 Simulador</button>
        <button class="tab" onclick="switchTab('webhooks')">🔗 Webhooks</button>
        <button class="tab" onclick="switchTab('webhook-logs')">📝 Logs Webhook</button>
        <button class="tab" onclick="switchTab('rules')">⚙️ Regras</button>
        <button class="tab" onclick="switchTab('docs')">📖 API Docs</button>
    </div>

    <!-- ============= DASHBOARD ============= -->
    <div id="tab-dashboard" class="tab-content active">
        <div class="grid-4 section" id="stats">
            <div class="stat-card total"><div class="value" id="stat-total">0</div><div class="label">Total</div></div>
            <div class="stat-card approved"><div class="value" id="stat-approved">0</div><div class="label">Aprovados</div></div>
            <div class="stat-card rejected"><div class="value" id="stat-rejected">0</div><div class="label">Rejeitados</div></div>
            <div class="stat-card pending"><div class="value" id="stat-pending">0</div><div class="label">Pendentes</div></div>
        </div>

        <div class="card">
            <div class="card-title">🕐 Últimos Pagamentos</div>
            <div class="table-container" id="recent-payments">
                <div class="empty-state"><div class="icon">📭</div><p>Nenhum pagamento ainda.</p></div>
            </div>
        </div>

        <div class="card">
            <div class="card-title">📡 Últimos Webhooks Enviados</div>
            <div class="table-container" id="recent-webhooks">
                <div class="empty-state"><div class="icon">📭</div><p>Nenhum webhook enviado.</p></div>
            </div>
        </div>
    </div>

    <!-- ============= CRIAR PAGAMENTO ============= -->
    <div id="tab-create-payment" class="tab-content">
        <div class="grid-2">
            <div class="card">
                <div class="card-title">💰 Criar Pagamento</div>
                <form id="payment-form" onsubmit="createPayment(event)">
                    <div class="grid-2">
                        <div class="form-group">
                            <label>Valor (R$) *</label>
                            <input type="number" id="pay-amount" step="0.01" min="0.01" value="150.00" required>
                        </div>
                        <div class="form-group">
                            <label>Moeda</label>
                            <select id="pay-currency">
                                <option value="BRL">BRL</option>
                                <option value="USD">USD</option>
                                <option value="EUR">EUR</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Método de Pagamento</label>
                        <select id="pay-method">
                            <option value="credit_card">Cartão de Crédito</option>
                            <option value="debit_card">Cartão de Débito</option>
                            <option value="pix">PIX</option>
                            <option value="boleto">Boleto</option>
                            <option value="bank_transfer">Transferência Bancária</option>
                        </select>
                    </div>

                    <div id="card-fields">
                        <div class="grid-2">
                            <div class="form-group">
                                <label>Número do Cartão</label>
                                <input type="text" id="pay-card-number" value="4111111111110001" placeholder="4111111111110001">
                            </div>
                            <div class="form-group">
                                <label>Titular</label>
                                <input type="text" id="pay-card-holder" value="JOHN DOE">
                            </div>
                        </div>
                        <div class="grid-3">
                            <div class="form-group">
                                <label>Mês Exp.</label>
                                <input type="text" id="pay-card-exp-month" value="12" maxlength="2">
                            </div>
                            <div class="form-group">
                                <label>Ano Exp.</label>
                                <input type="text" id="pay-card-exp-year" value="2030" maxlength="4">
                            </div>
                            <div class="form-group">
                                <label>Parcelas</label>
                                <input type="number" id="pay-installments" value="1" min="1" max="12">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Descrição</label>
                        <input type="text" id="pay-description" value="Pagamento de teste">
                    </div>

                    <div class="grid-2">
                        <div class="form-group">
                            <label>Nome do Pagador</label>
                            <input type="text" id="pay-payer-name" value="João Silva">
                        </div>
                        <div class="form-group">
                            <label>Email do Pagador</label>
                            <input type="email" id="pay-payer-email" value="joao@teste.com">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Forçar Status (opcional)</label>
                        <select id="pay-force-status">
                            <option value="">-- Automático (baseado nas regras) --</option>
                            <option value="approved">✅ Aprovado</option>
                            <option value="rejected">❌ Rejeitado</option>
                            <option value="pending">⏳ Pendente</option>
                            <option value="in_process">🔄 Em Processamento</option>
                            <option value="cancelled">🚫 Cancelado</option>
                            <option value="error">⚠️ Erro</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Notification URL (webhook individual)</label>
                        <input type="url" id="pay-notification-url" placeholder="https://seu-servidor.com/webhook">
                    </div>

                    <button type="submit" class="btn btn-primary">💳 Processar Pagamento</button>
                </form>
            </div>

            <div class="card">
                <div class="card-title">📋 Resultado</div>
                <div id="payment-result">
                    <div class="empty-state">
                        <div class="icon">💳</div>
                        <p>Envie um pagamento para ver o resultado aqui.</p>
                    </div>
                </div>

                <div class="mt-1">
                    <div class="card-title">⚡ Pagamentos Rápidos</div>
                    <div class="quick-actions">
                        <button class="btn btn-sm btn-success" onclick="quickPayment('approved')">✅ Aprovado</button>
                        <button class="btn btn-sm btn-danger" onclick="quickPayment('rejected')">❌ Rejeitado</button>
                        <button class="btn btn-sm btn-warning" onclick="quickPayment('pending')">⏳ Pendente</button>
                        <button class="btn btn-sm btn-outline" onclick="quickPayment('in_process')">🔄 Em Process.</button>
                        <button class="btn btn-sm btn-outline" onclick="quickPayment('cancelled')">🚫 Cancelado</button>
                        <button class="btn btn-sm btn-outline" onclick="quickPayment('error')">⚠️ Erro</button>
                    </div>
                </div>

                <div class="mt-1">
                    <div class="card-title">🃏 Cartões de Teste</div>
                    <table>
                        <thead>
                            <tr><th>Final</th><th>Status</th><th>Cartão Completo</th></tr>
                        </thead>
                        <tbody>
                            <tr><td>0001</td><td><span class="status status-approved">Aprovado</span></td><td class="mono">4111 1111 1111 0001</td></tr>
                            <tr><td>0002</td><td><span class="status status-rejected">Rejeitado</span></td><td class="mono">4111 1111 1111 0002</td></tr>
                            <tr><td>0003</td><td><span class="status status-pending">Pendente</span></td><td class="mono">4111 1111 1111 0003</td></tr>
                            <tr><td>0004</td><td><span class="status status-in_process">Em Process.</span></td><td class="mono">4111 1111 1111 0004</td></tr>
                            <tr><td>0005</td><td><span class="status status-cancelled">Cancelado</span></td><td class="mono">4111 1111 1111 0005</td></tr>
                            <tr><td>0006</td><td><span class="status status-error">Erro</span></td><td class="mono">4111 1111 1111 0006</td></tr>
                            <tr><td>0007</td><td><span class="status status-charged_back">Chargeback</span></td><td class="mono">4111 1111 1111 0007</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ============= LISTA DE PAGAMENTOS ============= -->
    <div id="tab-payments" class="tab-content">
        <div class="card">
            <div class="flex-between mb-1">
                <div class="card-title">📋 Todos os Pagamentos</div>
                <div style="display:flex;gap:0.5rem">
                    <select id="filter-status" onchange="loadPayments()" style="padding:0.4rem;background:var(--surface);border:1px solid var(--border);border-radius:0.5rem;color:var(--text);font-size:0.85rem">
                        <option value="">Todos Status</option>
                        <option value="approved">Aprovado</option>
                        <option value="rejected">Rejeitado</option>
                        <option value="pending">Pendente</option>
                        <option value="in_process">Em Processo</option>
                        <option value="cancelled">Cancelado</option>
                        <option value="refunded">Reembolsado</option>
                    </select>
                    <button class="btn btn-sm btn-outline" onclick="loadPayments()">🔄 Atualizar</button>
                </div>
            </div>
            <div class="table-container" id="payments-table">
                <div class="empty-state"><div class="icon">📭</div><p>Nenhum pagamento encontrado.</p></div>
            </div>
        </div>
    </div>

    <!-- ============= SIMULADOR ============= -->
    <div id="tab-simulator" class="tab-content">
        <div class="grid-2">
            <div class="card">
                <div class="card-title">🎮 Alterar Status de um Pagamento</div>
                <p style="color:var(--text-muted);font-size:0.85rem;margin-bottom:1rem">
                    Force a mudança de status de um pagamento existente. O webhook será disparado automaticamente.
                </p>
                <form onsubmit="simulateStatus(event)">
                    <div class="form-group">
                        <label>ID do Pagamento *</label>
                        <input type="text" id="sim-payment-id" placeholder="pay_..." required>
                    </div>
                    <div class="form-group">
                        <label>Novo Status *</label>
                        <select id="sim-status" required>
                            <option value="approved">✅ Aprovado</option>
                            <option value="rejected">❌ Rejeitado</option>
                            <option value="pending">⏳ Pendente</option>
                            <option value="in_process">🔄 Em Processamento</option>
                            <option value="cancelled">🚫 Cancelado</option>
                            <option value="refunded">💸 Reembolsado</option>
                            <option value="charged_back">⚡ Chargeback</option>
                            <option value="error">⚠️ Erro</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">🎯 Simular</button>
                </form>

                <div class="mt-1" id="sim-result"></div>
            </div>

            <div class="card">
                <div class="card-title">📋 Pagamentos Disponíveis</div>
                <div id="sim-payments-list">
                    <div class="empty-state"><p>Carregando...</p></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============= WEBHOOKS ============= -->
    <div id="tab-webhooks" class="tab-content">
        <div class="grid-2">
            <div class="card">
                <div class="card-title">🔗 Registrar Webhook</div>
                <form onsubmit="registerWebhook(event)">
                    <div class="form-group">
                        <label>URL *</label>
                        <input type="url" id="wh-url" placeholder="https://seu-servidor.com/webhook" required>
                    </div>
                    <div class="form-group">
                        <label>Descrição</label>
                        <input type="text" id="wh-description" placeholder="Webhook principal">
                    </div>
                    <div class="form-group">
                        <label>Eventos (separar com vírgula, ou * para todos)</label>
                        <input type="text" id="wh-events" value="*" placeholder="payment.created, payment.updated">
                    </div>
                    <button type="submit" class="btn btn-primary">📡 Registrar</button>

                    <div class="mt-1" style="padding:0.75rem;background:var(--surface);border-radius:0.5rem;font-size:0.8rem;color:var(--text-muted)">
                        <strong>💡 Dica:</strong> Use <code style="color:var(--primary)">/api/test-webhook-receiver</code> como URL para testar localmente. 
                        O endpoint completo seria: <code style="color:var(--primary)">http://localhost:8080/api/test-webhook-receiver</code>
                    </div>
                </form>
            </div>

            <div class="card">
                <div class="card-title">📋 Webhooks Registrados</div>
                <div id="webhooks-list">
                    <div class="empty-state"><div class="icon">📭</div><p>Nenhum webhook registrado.</p></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============= WEBHOOK LOGS ============= -->
    <div id="tab-webhook-logs" class="tab-content">
        <div class="card">
            <div class="flex-between mb-1">
                <div class="card-title">📝 Log de Webhooks Enviados</div>
                <button class="btn btn-sm btn-outline" onclick="loadWebhookLogs()">🔄 Atualizar</button>
            </div>
            <div class="table-container" id="webhook-logs-table">
                <div class="empty-state"><div class="icon">📭</div><p>Nenhum log encontrado.</p></div>
            </div>
        </div>

        <div class="card">
            <div class="flex-between mb-1">
                <div class="card-title">📥 Webhooks Recebidos (Endpoint de Teste)</div>
                <button class="btn btn-sm btn-outline" onclick="loadReceivedWebhooks()">🔄 Atualizar</button>
            </div>
            <div id="received-webhooks">
                <div class="empty-state"><div class="icon">📭</div><p>Nenhum webhook recebido ainda.</p></div>
            </div>
        </div>
    </div>

    <!-- ============= REGRAS ============= -->
    <div id="tab-rules" class="tab-content">
        <div class="grid-2">
            <div class="card">
                <div class="card-title">⚙️ Criar Regra de Simulação</div>
                <p style="color:var(--text-muted);font-size:0.85rem;margin-bottom:1rem">
                    Crie regras automáticas. Quando um pagamento for criado e corresponder às condições, o status definido será aplicado automaticamente.
                </p>
                <form onsubmit="createRule(event)">
                    <div class="form-group">
                        <label>Nome da Regra</label>
                        <input type="text" id="rule-name" placeholder="Ex: Rejeitar valores acima de 1000">
                    </div>
                    <div class="form-group">
                        <label>Status a Aplicar *</label>
                        <select id="rule-status" required>
                            <option value="approved">✅ Aprovado</option>
                            <option value="rejected">❌ Rejeitado</option>
                            <option value="pending">⏳ Pendente</option>
                            <option value="in_process">🔄 Em Processamento</option>
                            <option value="error">⚠️ Erro</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Condições (JSON) *</label>
                        <textarea id="rule-conditions" placeholder='{"amount": "1000.00"}'></textarea>
                        <small style="color:var(--text-muted)">
                            Use notação de ponto para campos aninhados. Ex: <code>payer.email</code>, <code>payment_method</code>
                        </small>
                    </div>
                    <div class="form-group">
                        <label>Prioridade</label>
                        <input type="number" id="rule-priority" value="0" min="0" max="100">
                    </div>
                    <button type="submit" class="btn btn-primary">✨ Criar Regra</button>
                </form>
            </div>

            <div class="card">
                <div class="card-title">📋 Regras Ativas</div>
                <div id="rules-list">
                    <div class="empty-state"><div class="icon">📭</div><p>Nenhuma regra criada.</p></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============= DOCS ============= -->
    <div id="tab-docs" class="tab-content">
        <div class="card">
            <div class="card-title">📖 Documentação da API</div>

            <h3 style="margin:1rem 0 0.5rem;color:var(--primary)">Endpoints</h3>

            <table>
                <thead>
                    <tr><th>Método</th><th>Endpoint</th><th>Descrição</th></tr>
                </thead>
                <tbody>
                    <tr><td><span class="status status-approved">POST</span></td><td class="mono">/api/payments</td><td>Criar novo pagamento</td></tr>
                    <tr><td><span class="status status-in_process">GET</span></td><td class="mono">/api/payments</td><td>Listar pagamentos</td></tr>
                    <tr><td><span class="status status-in_process">GET</span></td><td class="mono">/api/payments/{id}</td><td>Consultar pagamento</td></tr>
                    <tr><td><span class="status status-approved">POST</span></td><td class="mono">/api/payments/{id}/capture</td><td>Capturar pagamento pendente</td></tr>
                    <tr><td><span class="status status-approved">POST</span></td><td class="mono">/api/payments/{id}/cancel</td><td>Cancelar pagamento</td></tr>
                    <tr><td><span class="status status-approved">POST</span></td><td class="mono">/api/payments/{id}/refund</td><td>Reembolsar pagamento</td></tr>
                    <tr><td><span class="status status-approved">POST</span></td><td class="mono">/api/webhooks</td><td>Registrar webhook</td></tr>
                    <tr><td><span class="status status-in_process">GET</span></td><td class="mono">/api/webhooks</td><td>Listar webhooks</td></tr>
                    <tr><td><span class="status status-rejected">DELETE</span></td><td class="mono">/api/webhooks/{id}</td><td>Remover webhook</td></tr>
                    <tr><td><span class="status status-in_process">GET</span></td><td class="mono">/api/webhook-logs</td><td>Logs de webhooks enviados</td></tr>
                    <tr><td><span class="status status-approved">POST</span></td><td class="mono">/api/simulate</td><td>Forçar status de pagamento</td></tr>
                    <tr><td><span class="status status-approved">POST</span></td><td class="mono">/api/rules</td><td>Criar regra de simulação</td></tr>
                    <tr><td><span class="status status-in_process">GET</span></td><td class="mono">/api/rules</td><td>Listar regras</td></tr>
                    <tr><td><span class="status status-rejected">DELETE</span></td><td class="mono">/api/rules/{id}</td><td>Remover regra</td></tr>
                </tbody>
            </table>

            <h3 style="margin:1.5rem 0 0.5rem;color:var(--primary)">Exemplo: Criar Pagamento</h3>
            <div class="json-viewer">POST /api/payments
Content-Type: application/json

{
    "amount": 150.00,
    "currency": "BRL",
    "payment_method": "credit_card",
    "card": {
        "number": "4111111111110001",
        "holder_name": "JOHN DOE",
        "expiration_month": 12,
        "expiration_year": 2030
    },
    "payer": {
        "name": "João Silva",
        "email": "joao@teste.com",
        "document": "123.456.789-00"
    },
    "description": "Compra #12345",
    "notification_url": "https://meu-site.com/webhook",
    "installments": 3,
    "_simulate_status": "approved"
}</div>

            <h3 style="margin:1.5rem 0 0.5rem;color:var(--primary)">Exemplo: Registrar Webhook</h3>
            <div class="json-viewer">POST /api/webhooks
Content-Type: application/json

{
    "url": "https://meu-site.com/webhook",
    "events": ["payment.created", "payment.updated"],
    "description": "Webhook principal"
}</div>

            <h3 style="margin:1.5rem 0 0.5rem;color:var(--primary)">Payload do Webhook</h3>
            <div class="json-viewer">{
    "id": "evt_...",
    "type": "payment.created",
    "api_version": "v1",
    "date_created": "2026-02-25T10:00:00.000-03:00",
    "data": {
        "id": "pay_..."
    },
    "payment": {
        "id": "pay_...",
        "status": "approved",
        "amount": 150.00,
        ...
    }
}

Headers:
  X-Gateway-Event: payment.created
  X-Gateway-Signature: hmac-sha256-hash
  X-Gateway-Delivery: dlv_...</div>

            <h3 style="margin:1.5rem 0 0.5rem;color:var(--primary)">Cartões de Teste</h3>
            <table>
                <thead><tr><th>Número</th><th>Status Resultante</th></tr></thead>
                <tbody>
                    <tr><td class="mono">4111 1111 1111 0001</td><td><span class="status status-approved">Aprovado</span></td></tr>
                    <tr><td class="mono">4111 1111 1111 0002</td><td><span class="status status-rejected">Rejeitado</span></td></tr>
                    <tr><td class="mono">4111 1111 1111 0003</td><td><span class="status status-pending">Pendente</span></td></tr>
                    <tr><td class="mono">4111 1111 1111 0004</td><td><span class="status status-in_process">Em Processamento</span></td></tr>
                    <tr><td class="mono">4111 1111 1111 0005</td><td><span class="status status-cancelled">Cancelado</span></td></tr>
                    <tr><td class="mono">4111 1111 1111 0006</td><td><span class="status status-error">Erro</span></td></tr>
                    <tr><td class="mono">4111 1111 1111 0007</td><td><span class="status status-charged_back">Chargeback</span></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="toast-container" id="toast-container"></div>

<script>
const API = '';

// ============= UTILS =============
function toast(message, type = 'info') {
    const container = document.getElementById('toast-container');
    const el = document.createElement('div');
    el.className = `toast toast-${type}`;
    el.textContent = message;
    container.appendChild(el);
    setTimeout(() => el.remove(), 4000);
}

async function api(endpoint, options = {}) {
    try {
        const res = await fetch(API + endpoint, {
            headers: { 'Content-Type': 'application/json', ...options.headers },
            ...options,
        });
        const data = await res.json();
        return { ok: res.ok, status: res.status, data };
    } catch (err) {
        toast('Erro de conexão: ' + err.message, 'error');
        return { ok: false, data: null };
    }
}

function statusBadge(status) {
    return `<span class="status status-${status}">${status}</span>`;
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return d.toLocaleString('pt-BR');
}

function formatMoney(value, currency = 'BRL') {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency }).format(value);
}

// ============= TABS =============
function switchTab(tabId) {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    
    document.getElementById('tab-' + tabId).classList.add('active');
    event.target.classList.add('active');

    // Load data for tab
    if (tabId === 'dashboard') loadDashboard();
    if (tabId === 'payments') loadPayments();
    if (tabId === 'simulator') loadSimPayments();
    if (tabId === 'webhooks') loadWebhooks();
    if (tabId === 'webhook-logs') { loadWebhookLogs(); loadReceivedWebhooks(); }
    if (tabId === 'rules') loadRules();
}

// ============= DASHBOARD =============
async function loadDashboard() {
    const { data } = await api('/api/payments?limit=200');
    if (!data) return;

    const payments = data.data || [];
    const total = payments.length;
    const approved = payments.filter(p => p.status === 'approved').length;
    const rejected = payments.filter(p => ['rejected', 'error'].includes(p.status)).length;
    const pending = payments.filter(p => ['pending', 'in_process'].includes(p.status)).length;

    document.getElementById('stat-total').textContent = total;
    document.getElementById('stat-approved').textContent = approved;
    document.getElementById('stat-rejected').textContent = rejected;
    document.getElementById('stat-pending').textContent = pending;

    // Recent payments
    const recent = payments.slice(0, 10);
    if (recent.length) {
        document.getElementById('recent-payments').innerHTML = `
            <table>
                <thead><tr><th>ID</th><th>Status</th><th>Valor</th><th>Método</th><th>Data</th></tr></thead>
                <tbody>${recent.map(p => `
                    <tr>
                        <td class="mono">${p.id.substring(0, 20)}...</td>
                        <td>${statusBadge(p.status)}</td>
                        <td>${formatMoney(p.amount, p.currency)}</td>
                        <td>${p.payment_method}</td>
                        <td>${formatDate(p.created_at)}</td>
                    </tr>
                `).join('')}</tbody>
            </table>`;
    }

    // Recent webhook logs
    const { data: whData } = await api('/api/webhook-logs?limit=10');
    if (whData && whData.data && whData.data.length) {
        document.getElementById('recent-webhooks').innerHTML = `
            <table>
                <thead><tr><th>Evento</th><th>URL</th><th>Status HTTP</th><th>Resultado</th><th>Data</th></tr></thead>
                <tbody>${whData.data.map(l => `
                    <tr>
                        <td>${l.event}</td>
                        <td class="mono" style="max-width:200px;overflow:hidden;text-overflow:ellipsis">${l.url}</td>
                        <td>${l.http_status}</td>
                        <td class="${l.success ? 'wh-success' : 'wh-fail'}">${l.success ? '✅ OK' : '❌ Falha'}</td>
                        <td>${formatDate(l.sent_at)}</td>
                    </tr>
                `).join('')}</tbody>
            </table>`;
    }
}

// ============= CRIAR PAGAMENTO =============
async function createPayment(e) {
    e.preventDefault();

    const body = {
        amount: parseFloat(document.getElementById('pay-amount').value),
        currency: document.getElementById('pay-currency').value,
        payment_method: document.getElementById('pay-method').value,
        card: {
            number: document.getElementById('pay-card-number').value,
            holder_name: document.getElementById('pay-card-holder').value,
            expiration_month: parseInt(document.getElementById('pay-card-exp-month').value),
            expiration_year: parseInt(document.getElementById('pay-card-exp-year').value),
        },
        payer: {
            name: document.getElementById('pay-payer-name').value,
            email: document.getElementById('pay-payer-email').value,
        },
        description: document.getElementById('pay-description').value,
        installments: parseInt(document.getElementById('pay-installments').value),
        notification_url: document.getElementById('pay-notification-url').value || undefined,
    };

    const forceStatus = document.getElementById('pay-force-status').value;
    if (forceStatus) body._simulate_status = forceStatus;

    const { ok, data } = await api('/api/payments', {
        method: 'POST',
        body: JSON.stringify(body),
    });

    if (ok) {
        toast(`Pagamento ${data.status}! ID: ${data.id}`, data.status === 'approved' ? 'success' : 'info');
        document.getElementById('payment-result').innerHTML = `
            <div style="margin-bottom:0.75rem">
                <strong>Status:</strong> ${statusBadge(data.status)}
                <span style="margin-left:1rem"><strong>ID:</strong> <span class="mono">${data.id}</span></span>
            </div>
            <div class="json-viewer">${JSON.stringify(data, null, 2)}</div>`;
    } else {
        toast('Erro ao criar pagamento: ' + (data?.error || 'desconhecido'), 'error');
        document.getElementById('payment-result').innerHTML = `<div class="json-viewer" style="color:var(--danger)">${JSON.stringify(data, null, 2)}</div>`;
    }
}

async function quickPayment(status) {
    const body = {
        amount: 100 + Math.random() * 900,
        currency: 'BRL',
        payment_method: 'credit_card',
        card: { number: '4111111111110001', holder_name: 'TESTE' },
        payer: { name: 'Teste Rápido', email: 'teste@teste.com' },
        description: 'Pagamento rápido de teste',
        _simulate_status: status,
    };

    const { ok, data } = await api('/api/payments', {
        method: 'POST',
        body: JSON.stringify(body),
    });

    if (ok) {
        toast(`Pagamento ${status}: ${data.id}`, status === 'approved' ? 'success' : 'info');
        document.getElementById('payment-result').innerHTML = `
            <div style="margin-bottom:0.75rem">
                <strong>Status:</strong> ${statusBadge(data.status)}
            </div>
            <div class="json-viewer">${JSON.stringify(data, null, 2)}</div>`;
    }
}

// Toggle card fields
document.getElementById('pay-method').addEventListener('change', function() {
    document.getElementById('card-fields').style.display =
        ['credit_card', 'debit_card'].includes(this.value) ? 'block' : 'none';
});

// ============= LISTAR PAGAMENTOS =============
async function loadPayments() {
    const status = document.getElementById('filter-status').value;
    const qs = status ? `?status=${status}&limit=100` : '?limit=100';
    const { data } = await api('/api/payments' + qs);
    if (!data) return;

    const payments = data.data || [];
    if (!payments.length) {
        document.getElementById('payments-table').innerHTML = '<div class="empty-state"><div class="icon">📭</div><p>Nenhum pagamento encontrado.</p></div>';
        return;
    }

    document.getElementById('payments-table').innerHTML = `
        <table>
            <thead>
                <tr><th>ID</th><th>Status</th><th>Valor</th><th>Método</th><th>Pagador</th><th>Data</th><th>Ações</th></tr>
            </thead>
            <tbody>${payments.map(p => `
                <tr>
                    <td class="mono" style="font-size:0.75rem">${p.id}</td>
                    <td>${statusBadge(p.status)}</td>
                    <td>${formatMoney(p.amount, p.currency)}</td>
                    <td>${p.payment_method}</td>
                    <td>${p.payer?.name || '-'}</td>
                    <td>${formatDate(p.created_at)}</td>
                    <td>
                        <div style="display:flex;gap:0.25rem;flex-wrap:wrap">
                            ${p.status === 'pending' || p.status === 'in_process' ? `<button class="btn btn-sm btn-success" onclick="actionPayment('${p.id}','capture')">Capturar</button>` : ''}
                            ${!['cancelled','refunded','charged_back'].includes(p.status) ? `<button class="btn btn-sm btn-danger" onclick="actionPayment('${p.id}','cancel')">Cancelar</button>` : ''}
                            ${p.status === 'approved' ? `<button class="btn btn-sm btn-warning" onclick="actionPayment('${p.id}','refund')">Reembolsar</button>` : ''}
                        </div>
                    </td>
                </tr>
            `).join('')}</tbody>
        </table>`;
}

async function actionPayment(id, action) {
    const { ok, data } = await api(`/api/payments/${id}/${action}`, { method: 'POST', body: '{}' });
    if (ok) {
        toast(`${action} realizado com sucesso!`, 'success');
        loadPayments();
    } else {
        toast(data?.error || 'Erro', 'error');
    }
}

// ============= SIMULADOR =============
async function loadSimPayments() {
    const { data } = await api('/api/payments?limit=20');
    if (!data || !(data.data || []).length) {
        document.getElementById('sim-payments-list').innerHTML = '<div class="empty-state"><p>Crie um pagamento primeiro.</p></div>';
        return;
    }

    document.getElementById('sim-payments-list').innerHTML = `
        <table>
            <thead><tr><th>ID</th><th>Status</th><th>Valor</th><th>Selecionar</th></tr></thead>
            <tbody>${data.data.map(p => `
                <tr>
                    <td class="mono" style="font-size:0.7rem">${p.id}</td>
                    <td>${statusBadge(p.status)}</td>
                    <td>${formatMoney(p.amount)}</td>
                    <td><button class="btn btn-sm btn-outline" onclick="document.getElementById('sim-payment-id').value='${p.id}'">Usar</button></td>
                </tr>
            `).join('')}</tbody>
        </table>`;
}

async function simulateStatus(e) {
    e.preventDefault();
    const body = {
        payment_id: document.getElementById('sim-payment-id').value,
        status: document.getElementById('sim-status').value,
    };

    const { ok, data } = await api('/api/simulate', { method: 'POST', body: JSON.stringify(body) });

    if (ok) {
        toast(`Status alterado: ${data.old_status} → ${data.new_status}`, 'success');
        document.getElementById('sim-result').innerHTML = `<div class="json-viewer">${JSON.stringify(data, null, 2)}</div>`;
        loadSimPayments();
    } else {
        toast(data?.error || 'Erro', 'error');
    }
}

// ============= WEBHOOKS =============
async function registerWebhook(e) {
    e.preventDefault();
    const events = document.getElementById('wh-events').value.split(',').map(s => s.trim()).filter(Boolean);

    const body = {
        url: document.getElementById('wh-url').value,
        description: document.getElementById('wh-description').value,
        events: events.length ? events : ['*'],
    };

    const { ok, data } = await api('/api/webhooks', { method: 'POST', body: JSON.stringify(body) });
    if (ok) {
        toast('Webhook registrado!', 'success');
        loadWebhooks();
    } else {
        toast(data?.error || 'Erro', 'error');
    }
}

async function loadWebhooks() {
    const { data } = await api('/api/webhooks');
    if (!data || !(data.data || []).length) {
        document.getElementById('webhooks-list').innerHTML = '<div class="empty-state"><div class="icon">📭</div><p>Nenhum webhook registrado.</p></div>';
        return;
    }

    document.getElementById('webhooks-list').innerHTML = `
        <table>
            <thead><tr><th>URL</th><th>Eventos</th><th>Descrição</th><th>Ações</th></tr></thead>
            <tbody>${data.data.map(w => `
                <tr>
                    <td class="mono" style="font-size:0.75rem;max-width:250px;overflow:hidden;text-overflow:ellipsis">${w.url}</td>
                    <td>${(w.events || []).join(', ')}</td>
                    <td>${w.description || '-'}</td>
                    <td><button class="btn btn-sm btn-danger" onclick="deleteWebhook('${w.id}')">🗑️</button></td>
                </tr>
            `).join('')}</tbody>
        </table>`;
}

async function deleteWebhook(id) {
    const { ok } = await api(`/api/webhooks/${id}`, { method: 'DELETE' });
    if (ok) {
        toast('Webhook removido.', 'success');
        loadWebhooks();
    }
}

// ============= WEBHOOK LOGS =============
async function loadWebhookLogs() {
    const { data } = await api('/api/webhook-logs?limit=50');
    if (!data || !(data.data || []).length) {
        document.getElementById('webhook-logs-table').innerHTML = '<div class="empty-state"><div class="icon">📭</div><p>Nenhum log.</p></div>';
        return;
    }

    document.getElementById('webhook-logs-table').innerHTML = `
        <table>
            <thead><tr><th>Evento</th><th>Payment ID</th><th>URL</th><th>HTTP</th><th>Status</th><th>Erro</th><th>Data</th></tr></thead>
            <tbody>${data.data.map(l => `
                <tr>
                    <td>${l.event}</td>
                    <td class="mono" style="font-size:0.7rem">${l.payment_id}</td>
                    <td class="mono" style="font-size:0.7rem;max-width:200px;overflow:hidden;text-overflow:ellipsis">${l.url}</td>
                    <td>${l.http_status}</td>
                    <td class="${l.success ? 'wh-success' : 'wh-fail'}">${l.success ? '✅' : '❌'}</td>
                    <td style="color:var(--danger);font-size:0.75rem">${l.error || '-'}</td>
                    <td>${formatDate(l.sent_at)}</td>
                </tr>
            `).join('')}</tbody>
        </table>`;
}

async function loadReceivedWebhooks() {
    const { data } = await api('/api/test-webhook-receiver');
    if (!data || !data.length) {
        document.getElementById('received-webhooks').innerHTML = '<div class="empty-state"><div class="icon">📭</div><p>Nenhum webhook recebido.</p></div>';
        return;
    }

    document.getElementById('received-webhooks').innerHTML = data.slice(0, 20).map(w => `
        <div class="card" style="margin-bottom:0.5rem;padding:0.75rem">
            <div style="font-size:0.75rem;color:var(--text-muted);margin-bottom:0.5rem">📅 ${w.received_at}</div>
            <div class="json-viewer" style="max-height:200px">${JSON.stringify(w.payload, null, 2)}</div>
        </div>
    `).join('');
}

// ============= REGRAS =============
async function createRule(e) {
    e.preventDefault();

    let conditions;
    try {
        conditions = JSON.parse(document.getElementById('rule-conditions').value);
    } catch {
        toast('JSON das condições é inválido.', 'error');
        return;
    }

    const body = {
        name: document.getElementById('rule-name').value,
        status: document.getElementById('rule-status').value,
        conditions,
        priority: parseInt(document.getElementById('rule-priority').value || '0'),
    };

    const { ok, data } = await api('/api/rules', { method: 'POST', body: JSON.stringify(body) });
    if (ok) {
        toast('Regra criada!', 'success');
        loadRules();
    } else {
        toast(data?.error || 'Erro', 'error');
    }
}

async function loadRules() {
    const { data } = await api('/api/rules');
    if (!data || !(data.data || []).length) {
        document.getElementById('rules-list').innerHTML = '<div class="empty-state"><div class="icon">📭</div><p>Nenhuma regra.</p></div>';
        return;
    }

    document.getElementById('rules-list').innerHTML = `
        <table>
            <thead><tr><th>Nome</th><th>Status</th><th>Condições</th><th>Prioridade</th><th>Ações</th></tr></thead>
            <tbody>${data.data.map(r => `
                <tr>
                    <td>${r.name}</td>
                    <td>${statusBadge(r.status)}</td>
                    <td class="mono" style="font-size:0.7rem">${JSON.stringify(r.conditions)}</td>
                    <td>${r.priority}</td>
                    <td><button class="btn btn-sm btn-danger" onclick="deleteRule('${r.id}')">🗑️</button></td>
                </tr>
            `).join('')}</tbody>
        </table>`;
}

async function deleteRule(id) {
    const { ok } = await api(`/api/rules/${id}`, { method: 'DELETE' });
    if (ok) {
        toast('Regra removida.', 'success');
        loadRules();
    }
}

// ============= INIT =============
loadDashboard();
</script>
</body>
</html>
