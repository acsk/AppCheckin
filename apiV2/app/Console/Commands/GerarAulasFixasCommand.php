<?php

namespace App\Console\Commands;

use App\Services\Admin\AdminTurmaFixaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GerarAulasFixasCommand extends Command
{
    protected $signature = 'turmas:gerar-aulas-fixas';

    protected $description = 'Completa os próximos 90 dias de aulas das turmas fixas, preservando aulas existentes';

    public function handle(AdminTurmaFixaService $service): int
    {
        if (! Schema::hasTable('turmas_fixas')) {
            $this->warn('Execute a migração de acesso híbrido antes de gerar aulas.');

            return self::FAILURE;
        }
        $total = 0;
        foreach (DB::table('turmas_fixas')->where('ativo', 1)->orderBy('id')->get(['id', 'tenant_id']) as $turma) {
            $total += $service->gerarAulas((int) $turma->tenant_id, (int) $turma->id);
        }
        $this->info("{$total} aulas criadas.");

        return self::SUCCESS;
    }
}
