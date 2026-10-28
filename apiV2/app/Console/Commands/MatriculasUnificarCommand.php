<?php

namespace App\Console\Commands;

use App\Services\MatriculaUnificacaoService;
use Illuminate\Console\Command;

class MatriculasUnificarCommand extends Command
{
    protected $signature = 'matriculas:unificar
                            {--tenant= : só este tenant_id}
                            {--aluno= : só este aluno_id}
                            {--aplicar : grava a unificação (sem isso só lista)}';

    protected $description = 'Junta na matrícula atual os ciclos do mesmo aluno e modalidade que viraram matrículas separadas';

    public function handle(MatriculaUnificacaoService $unificacao): int
    {
        $aplicar = (bool) $this->option('aplicar');
        $tenant = is_numeric($this->option('tenant')) ? (int) $this->option('tenant') : null;
        $aluno = is_numeric($this->option('aluno')) ? (int) $this->option('aluno') : null;

        $unificados = 0;
        $removidas = 0;
        $conflitos = 0;
        foreach ($unificacao->grupos($tenant, $aluno) as $grupo) {
            $r = $unificacao->unificar($grupo, ! $aplicar);
            $ids = implode(', ', array_map(fn (array $m) => "#{$m['id']} {$m['status']}", $grupo['matriculas']));
            $rotulo = "tenant {$grupo['tenant_id']} · aluno {$grupo['aluno_id']} · modalidade {$grupo['modalidade_id']}";

            if ($r['conflito']) {
                $conflitos++;
                $this->warn("{$rotulo}: {$ids} → pulado ({$r['conflito']})");

                continue;
            }

            $unificados++;
            $removidas += count($r['removidas']);
            $this->line("{$rotulo}: {$ids} → fica #{$r['atual']}, ".count($r['removidas'])." removida(s), {$r['linhas_movidas']} registro(s) movido(s)");
        }

        $this->info($aplicar
            ? "{$unificados} aluno(s)/modalidade(s) unificado(s), {$removidas} matrícula(s) removida(s), {$conflitos} pulado(s)."
            : "{$unificados} aluno(s)/modalidade(s) para unificar ({$removidas} matrícula(s) a remover), {$conflitos} pulado(s). Rode com --aplicar para gravar.");

        return self::SUCCESS;
    }
}
