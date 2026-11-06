<?php

namespace App\Console\Commands;

use App\Services\ReajustePrecoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReajustarContratosPlanoCommand extends Command
{
    protected $signature = 'planos:reajustar-contratos {--tenant=} {--ciclo=} {--valor-anterior=} {--confirmar}';

    protected $description = 'Ação explícita de reajuste: somente contratos avulsos no preço anterior de tabela';

    public function handle(ReajustePrecoService $service): int
    {
        if (! ctype_digit((string) $this->option('tenant')) || ! ctype_digit((string) $this->option('ciclo'))
            || ! is_numeric($this->option('valor-anterior'))
            || ! is_finite((float) $this->option('valor-anterior')) || (float) $this->option('valor-anterior') < 0) {
            $this->error('Informe --tenant, --ciclo e --valor-anterior. Sem --confirmar, apenas simula.');

            return self::FAILURE;
        }
        $tenant = (int) $this->option('tenant');
        $ciclo = DB::table('plano_ciclos')->where('tenant_id', $tenant)->where('id', (int) $this->option('ciclo'))->first();
        if (! $ciclo) {
            $this->error('Ciclo não encontrado neste tenant.');

            return self::FAILURE;
        }
        DB::beginTransaction();
        try {
            $resultado = $service->reajustarCiclo($tenant, (int) $ciclo->id, (float) $this->option('valor-anterior'), (float) $ciclo->valor);
            $this->option('confirmar') ? DB::commit() : DB::rollBack();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        $this->info(($this->option('confirmar') ? 'Aplicado' : 'Simulação').': '.json_encode($resultado));

        return self::SUCCESS;
    }
}
