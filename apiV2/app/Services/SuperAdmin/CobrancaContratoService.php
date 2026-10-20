<?php

namespace App\Services\SuperAdmin;

use App\Repositories\PagamentoContratoRepository;
use App\Repositories\TenantPlanoRepository;
use App\Services\Asaas\AsaasService;
use App\Support\Documento;
use Illuminate\Support\Facades\DB;

/**
 * Gera no Asaas da conta do sistema a cobrança de um pagamento de contrato (pagamentos_contrato).
 */
class CobrancaContratoService
{
    /** @var \Closure(int): AsaasService */
    private \Closure $asaasFactory;

    public function __construct(
        private readonly PagamentoContratoRepository $pagamentos,
        private readonly TenantPlanoRepository $contratos,
        ?\Closure $asaasFactory = null,
    ) {
        $this->asaasFactory = $asaasFactory ?? static fn (int $tenantId) => new AsaasService($tenantId);
    }

    public const BILLING_TYPES = ['PIX', 'BOLETO', 'CREDIT_CARD'];

    private const FORMA_PAGAMENTO_NOME = ['PIX' => 'Pix', 'BOLETO' => 'Boleto', 'CREDIT_CARD' => 'Cartão'];

    /** formas_pagamento.id do PIX, BOLETO ou CREDIT_CARD (pelo nome do catálogo). */
    public static function formaPagamentoId(string $billingType): ?int
    {
        $id = DB::table('formas_pagamento')->where('nome', self::FORMA_PAGAMENTO_NOME[strtoupper($billingType)] ?? '')->value('id');

        return $id ? (int) $id : null;
    }

    /**
     * Documento que vai para o cliente no Asaas: o CNPJ da academia se for válido, senão o CPF do responsável.
     *
     * @param  array<string, mixed>  $academia  linha de tenants
     * @return array{cpfCnpj: string, name: string}|null  null quando nenhum dos dois é válido
     */
    public static function documentoCobranca(array $academia): ?array
    {
        $cnpj = Documento::digitos((string) ($academia['cnpj'] ?? ''));
        if (strlen($cnpj) === 14 && Documento::cnpjValido($cnpj)) {
            return ['cpfCnpj' => $cnpj, 'name' => (string) ($academia['nome'] ?? '')];
        }
        $cpf = Documento::digitos((string) ($academia['responsavel_cpf'] ?? ''));
        if (strlen($cpf) === 11 && Documento::cpfValido($cpf)) {
            return ['cpfCnpj' => $cpf, 'name' => (string) ($academia['responsavel_nome'] ?: ($academia['nome'] ?? ''))];
        }

        return null;
    }

    public static function sistemaTenantId(): int
    {
        return (int) config('appcheckin.sistema_tenant_id', 1);
    }

    public function asaas(): AsaasService
    {
        return ($this->asaasFactory)(self::sistemaTenantId());
    }

    /**
     * @return list<string> PIX, BOLETO e/ou CREDIT_CARD
     */
    public function formasHabilitadas(): array
    {
        try {
            return $this->asaas()->formasHabilitadas();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array<string, mixed> cobrança criada (ver AsaasService::criarCobrancaContrato)
     */
    public function gerar(int $pagamentoId, string $billingType): array
    {
        $pagamento = $this->pagamentos->buscarPorId($pagamentoId);
        if (! $pagamento) {
            throw new \RuntimeException('Pagamento de contrato não encontrado');
        }
        $contrato = $this->contratos->buscarPorId((int) $pagamento['tenant_plano_id']);
        if (! $contrato) {
            throw new \RuntimeException('Contrato não encontrado');
        }
        $academia = (array) DB::table('tenants')->where('id', (int) $contrato['tenant_id'])->first();
        $documento = self::documentoCobranca($academia);
        if (! $documento) {
            throw new \RuntimeException('A academia não tem CNPJ nem CPF do responsável válido para a cobrança');
        }

        $cobranca = $this->asaas()->criarCobrancaContrato([
            'pagamento_id' => $pagamentoId,
            'valor' => (float) $pagamento['valor'],
            'billing_type' => $billingType,
            'vencimento' => max((string) $pagamento['data_vencimento'], date('Y-m-d')),
            'descricao' => 'AppCheckin '.($contrato['plano_nome'] ?? '').' — '.($academia['nome'] ?? ''),
            'cliente' => [
                'name' => $documento['name'],
                'cpfCnpj' => $documento['cpfCnpj'],
                'email' => $academia['responsavel_email'] ?? $academia['email'] ?? null,
                'mobilePhone' => $academia['responsavel_telefone'] ?? $academia['telefone'] ?? null,
            ],
        ]);

        $this->pagamentos->salvarGateway($pagamentoId, [
            'gateway' => 'asaas',
            'gateway_payment_id' => $cobranca['id'],
            'gateway_billing_type' => $cobranca['billing_type'],
            'gateway_url' => $cobranca['url'] ?? $cobranca['boleto_url'] ?? null,
        ]);

        return $cobranca;
    }

    /**
     * Cobra no Asaas o próximo pagamento pendente do contrato que ainda não tem cobrança.
     */
    public function gerarProxima(int $contratoId, string $billingType): ?array
    {
        $pendente = DB::table('pagamentos_contrato')
            ->where('tenant_plano_id', $contratoId)
            ->where('status_pagamento_id', 1)
            ->whereNull('gateway_payment_id')
            ->orderBy('data_vencimento')
            ->first();

        return $pendente ? $this->gerar((int) $pendente->id, $billingType) : null;
    }
}
