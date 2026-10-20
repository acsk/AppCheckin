<?php

namespace App\Services;

use App\Services\Asaas\AsaasService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Cobranças em aberto no provedor (Asaas ou Mercado Pago) que perderam a parcela no Check-in, como o
 * Pix de R$ 70 que fica para trás quando o aluno troca para o ciclo de R$ 120 no app.
 * Cancela no provedor e marca o Pix local como cancelado, para o aluno não pagar por engano.
 */
class CobrancaAnteriorService
{
    private const ABERTAS_ASAAS = ['PENDING', 'OVERDUE', 'AWAITING_RISK_ANALYSIS'];

    private const ABERTAS_MP = ['pending', 'in_process', 'authorized'];

    /** @var \Closure(int, string): object */
    private \Closure $gateway;

    public function __construct(?\Closure $gateway = null)
    {
        $this->gateway = $gateway ?? static fn (int $tenantId, string $provedor) => $provedor === 'asaas'
            ? new AsaasService($tenantId)
            : new MercadoPagoService($tenantId);
    }

    public static function provedorDoId(string $paymentId): string
    {
        return str_starts_with($paymentId, 'pay_') ? 'asaas' : 'mercadopago';
    }

    /**
     * Ids de cobranças da matrícula que ainda podem estar abertas no provedor. Chamar antes de gerar a nova
     * cobrança: a assinatura guarda só a última, e a compra sobrescreve.
     *
     * @return list<string>
     */
    public function idsEmAberto(int $tenantId, int $matriculaId): array
    {
        $ids = DB::table('pagamentos_pix')
            ->where('tenant_id', $tenantId)
            ->where('matricula_id', $matriculaId)
            ->whereNotIn('status', ['approved', 'cancelled', 'canceled', 'rejected', 'refunded'])
            ->pluck('payment_id')
            ->map(fn ($v) => (string) $v)
            ->all();

        $pref = (string) (DB::table('assinaturas')
            ->where('tenant_id', $tenantId)
            ->where('matricula_id', $matriculaId)
            ->whereNotNull('gateway_preference_id')
            ->whereNotIn('status_gateway', ['approved'])
            ->orderByDesc('id')
            ->value('gateway_preference_id') ?? '');
        if (str_starts_with($pref, 'pay_')) {
            $ids[] = $pref;
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * Cancela no provedor as cobranças ainda abertas, menos as que devem continuar (a recém-criada).
     * Nunca lança: falha vira item em "falhas" e a cobrança aparece como pendência na conciliação.
     *
     * @param  list<string>  $ids
     * @param  list<string>  $manter
     * @return array{canceladas: list<array<string, mixed>>, falhas: list<array<string, mixed>>}
     */
    public function cancelar(int $tenantId, array $ids, array $manter = []): array
    {
        $res = ['canceladas' => [], 'falhas' => []];
        foreach (array_diff($ids, array_filter($manter)) as $paymentId) {
            $provedor = self::provedorDoId($paymentId);
            try {
                $gw = ($this->gateway)($tenantId, $provedor);
                if ($provedor === 'asaas') {
                    $pg = $gw->obterPagamento($paymentId);
                    $status = strtoupper((string) ($pg['status'] ?? ''));
                    if (($pg['deleted'] ?? false) === true || ! in_array($status, self::ABERTAS_ASAAS, true)) {
                        $this->marcarPixCancelado($tenantId, $paymentId, ($pg['deleted'] ?? false) === true);

                        continue;
                    }
                    $gw->cancelarCobranca($paymentId);
                    $valor = (float) ($pg['value'] ?? 0);
                    $forma = (string) ($pg['billingType'] ?? '');
                } else {
                    $pg = $gw->buscarPagamento($paymentId);
                    $status = strtolower((string) ($pg['status'] ?? ''));
                    if (! in_array($status, self::ABERTAS_MP, true)) {
                        continue;
                    }
                    $gw->cancelarPagamento($paymentId);
                    $valor = (float) ($pg['transaction_amount'] ?? 0);
                    $forma = (string) ($pg['payment_method_id'] ?? '');
                }
                $this->marcarPixCancelado($tenantId, $paymentId, true);
                $res['canceladas'][] = ['payment_id' => $paymentId, 'provedor' => $provedor, 'valor' => $valor, 'forma' => self::formaLegivel($forma)];
                Log::info('Cobrança anterior cancelada no provedor', ['tenant_id' => $tenantId, 'payment_id' => $paymentId, 'valor' => $valor]);
            } catch (\Throwable $e) {
                $res['falhas'][] = ['payment_id' => $paymentId, 'provedor' => $provedor, 'erro' => $e->getMessage()];
                Log::warning('Não foi possível cancelar cobrança anterior no provedor', ['tenant_id' => $tenantId, 'payment_id' => $paymentId, 'error' => $e->getMessage()]);
            }
        }

        return $res;
    }

    /** Aviso para o app: "Sua cobrança anterior de R$ 70,00 (Pix) foi cancelada e substituída por esta." */
    public static function aviso(array $canceladas): ?string
    {
        if ($canceladas === []) {
            return null;
        }
        $partes = array_map(fn ($c) => 'R$ '.number_format((float) $c['valor'], 2, ',', '.').($c['forma'] ? ' ('.$c['forma'].')' : ''), $canceladas);

        return count($canceladas) === 1
            ? 'Sua cobrança anterior de '.$partes[0].' foi cancelada e substituída por esta.'
            : 'Suas cobranças anteriores ('.implode(', ', $partes).') foram canceladas e substituídas por esta.';
    }

    private function marcarPixCancelado(int $tenantId, string $paymentId, bool $cancelada): void
    {
        if (! $cancelada) {
            return;
        }
        DB::table('pagamentos_pix')
            ->where('tenant_id', $tenantId)
            ->where('payment_id', $paymentId)
            ->whereNotIn('status', ['approved'])
            ->update(['status' => 'cancelled', 'updated_at' => now()]);
    }

    private static function formaLegivel(string $forma): string
    {
        return match (strtolower($forma)) {
            'pix' => 'Pix',
            'credit_card', 'credito', 'visa', 'master' => 'Cartão',
            'boleto', 'bolbradesco' => 'Boleto',
            default => '',
        };
    }
}
