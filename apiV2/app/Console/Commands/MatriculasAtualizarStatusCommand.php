<?php

namespace App\Console\Commands;

use App\Services\MatriculaStatusAtualizacaoService;
use Illuminate\Console\Command;

/** Recalcula o status das matrículas ativas/pendentes/vencidas de todas as academias (status parado no painel). */
class MatriculasAtualizarStatusCommand extends Command
{
    protected $signature = 'matriculas:atualizar-status
        {--tenant= : Só uma academia}
        {--simular : Só lista o que mudaria, sem gravar}';

    protected $description = 'Recalcula o status das matrículas pela regra de pagamentos (vencida, cancelada, pendente, ativa)';

    public function handle(MatriculaStatusAtualizacaoService $service): int
    {
        $simular = (bool) $this->option('simular');
        $tenant = $this->option('tenant');
        $tenants = $service->tenantsAtivos(is_numeric($tenant) ? (int) $tenant : null);

        $total = 0;
        $porTransicao = [];
        $erros = [];
        foreach ($tenants as $tenantId) {
            $r = $service->processarTenant($tenantId, $simular);
            if ($r['erro'] !== null) {
                $erros[] = "academia {$tenantId}: {$r['erro']}";
                $this->error("Academia {$tenantId}: {$r['erro']}");

                continue;
            }
            if ($r['mudancas'] === []) {
                continue;
            }
            $this->line("Academia {$tenantId}: {$r['conferidas']} conferidas, ".count($r['mudancas']).($simular ? ' mudariam' : ' mudaram'));
            foreach ($r['mudancas'] as $m) {
                $this->line(sprintf('  #%d %s: %s → %s', $m['matricula_id'], $m['aluno'] ?? '?', $m['de'], $m['para']));
                $chave = "{$m['de']} → {$m['para']}";
                $porTransicao[$chave] = ($porTransicao[$chave] ?? 0) + 1;
                $total++;
            }
        }

        ksort($porTransicao);
        foreach ($porTransicao as $t => $n) {
            $this->line("  {$t}: {$n}");
        }
        $this->info(sprintf('%s: %d academias, %d matrículas %s.', $simular ? 'Simulação' : 'Atualização', count($tenants), $total, $simular ? 'mudariam de status' : 'mudaram de status'));

        if ($erros !== [] && count($erros) === count($tenants)) {
            report(new \RuntimeException('matriculas:atualizar-status falhou em todas as academias. '.implode(' | ', $erros)));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
