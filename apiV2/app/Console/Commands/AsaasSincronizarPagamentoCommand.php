<?php

namespace App\Console\Commands;

use App\Services\Asaas\AsaasService;
use App\Services\Asaas\AsaasWebhookService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AsaasSincronizarPagamentoCommand extends Command
{
    protected $signature = 'asaas:sincronizar-pagamento
                            {paymentId : ID da cobrança Asaas (pay_...)}
                            {--tenant= : tenant_id (opcional se o PIX já estiver salvo)}';

    protected $description = 'Consulta a cobrança no Asaas e aplica o mesmo fluxo do webhook (baixa + matrícula)';

    public function handle(AsaasWebhookService $webhooks): int
    {
        $paymentId = (string) $this->argument('paymentId');
        $tenantOpt = $this->option('tenant');
        $tenantId = is_numeric($tenantOpt) ? (int) $tenantOpt : 0;

        if ($tenantId <= 0) {
            $tenantId = (int) (DB::table('pagamentos_pix')->where('payment_id', $paymentId)->value('tenant_id') ?? 0);
        }
        if ($tenantId <= 0) {
            $tenantId = (int) (DB::table('assinaturas')->where('gateway_preference_id', $paymentId)->value('tenant_id') ?? 0);
        }
        if ($tenantId <= 0) {
            $this->error('Informe --tenant=ID (PIX/assinatura não encontrados para este paymentId).');

            return self::FAILURE;
        }

        try {
            $payment = (new AsaasService($tenantId))->obterPagamento($paymentId);
        } catch (\Throwable $e) {
            $this->error('Falha ao consultar Asaas: '.$e->getMessage());

            return self::FAILURE;
        }

        $result = $webhooks->processar([
            'id' => 'cli-'.$paymentId,
            'event' => 'PAYMENT_RECEIVED',
            'payment' => $payment,
        ], null);

        $this->line(json_encode($result['body'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return ($result['status'] ?? 500) < 300 ? self::SUCCESS : self::FAILURE;
    }
}
