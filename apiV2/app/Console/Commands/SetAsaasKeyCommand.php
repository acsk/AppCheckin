<?php

namespace App\Console\Commands;

use App\Services\EncryptionService;
use App\Support\AsaasApiKey;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SetAsaasKeyCommand extends Command
{
    protected $signature = 'payments:set-asaas-key
                            {tenantId : ID do tenant}
                            {--sandbox : Grava em access_token_test (padrão)}
                            {--production : Grava em access_token_prod}';

    protected $description = 'Grava a API key do Asaas criptografada em tenant_payment_credentials (não usa .env)';

    public function handle(EncryptionService $encryption): int
    {
        $tenantId = (int) $this->argument('tenantId');
        if ($tenantId <= 0) {
            $this->error('tenantId inválido');

            return self::FAILURE;
        }

        $tenant = DB::table('tenants')->where('id', $tenantId)->first(['id', 'nome']);
        if (! $tenant) {
            $this->error("Tenant {$tenantId} não encontrado");

            return self::FAILURE;
        }

        $key = (string) getenv('ASAAS_API_KEY');
        if ($key === '') {
            $key = (string) $this->secret('Cole a API key do Asaas (não será exibida)');
        }
        $key = AsaasApiKey::normalizar($key);
        if ($key === '') {
            $this->error('Informe a key via ASAAS_API_KEY=... ou no prompt.');

            return self::FAILURE;
        }

        $environment = $this->option('production') ? 'production' : 'sandbox';
        if (! AsaasApiKey::valida($key, $environment)) {
            $this->error(AsaasApiKey::mensagemInvalida($environment));

            return self::FAILURE;
        }

        $encrypted = $encryption->encrypt($key);
        $tokenColumn = $environment === 'production' ? 'access_token_prod' : 'access_token_test';

        $existe = DB::table('tenant_payment_credentials')->where('tenant_id', $tenantId)->exists();
        if ($existe) {
            DB::table('tenant_payment_credentials')->where('tenant_id', $tenantId)->update([
                'provider' => 'asaas',
                'environment' => $environment,
                $tokenColumn => $encrypted,
                'is_active' => 1,
                'updated_at' => now(),
            ]);
        } else {
            DB::table('tenant_payment_credentials')->insert([
                'tenant_id' => $tenantId,
                'provider' => 'asaas',
                'environment' => $environment,
                $tokenColumn => $encrypted,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->info("Asaas sandbox/prod key gravada criptografada para tenant {$tenantId} ({$tenant->nome}), ambiente {$environment}.");

        return self::SUCCESS;
    }
}
