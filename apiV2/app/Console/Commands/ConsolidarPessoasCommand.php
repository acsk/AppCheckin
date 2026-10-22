<?php

namespace App\Console\Commands;

use App\Services\SuperAdmin\ConsolidacaoPessoasService as C;
use Illuminate\Console\Command;

/**
 * Fase 1 da consolidação de alunos/professores → usuarios.
 *
 * php artisan pessoas:consolidar                  relatório (não grava nada)
 * php artisan pessoas:consolidar --csv=/tmp/c.csv relatório + todos os itens em CSV
 * php artisan pessoas:consolidar --aplicar        preenche em usuarios só o que está vazio (pede confirmação)
 */
class ConsolidarPessoasCommand extends Command
{
    protected $signature = 'pessoas:consolidar
                            {--aplicar : Grava em usuarios os campos vazios que têm valor único em alunos/professores}
                            {--csv= : Caminho de um CSV com todos os itens do relatório}
                            {--limite=50 : Quantas linhas de conflito/bloqueio mostrar na tela}
                            {--force : Não pede confirmação ao aplicar}';

    protected $description = 'Compara usuarios com as cópias em alunos/professores e consolida os dados pessoais em usuarios';

    public function handle(C $service): int
    {
        $itens = $service->analisar();

        $resumo = [];
        foreach ($itens as $i) {
            $resumo[$i['campo']] ??= [C::PREENCHER => 0, C::CONFLITO => 0, C::BLOQUEADO => 0];
            $resumo[$i['campo']][$i['situacao']]++;
        }
        ksort($resumo);
        $this->info('Resumo por campo (preencher = vai para usuarios com --aplicar; nome e e-mail nunca são gravados)');
        $this->table(['Campo', 'Preencher', 'Conflito', 'Bloqueado'],
            array_map(fn ($c, $r) => [$c, $r[C::PREENCHER], $r[C::CONFLITO], $r[C::BLOQUEADO]], array_keys($resumo), $resumo));

        $problemas = array_values(array_filter($itens, fn ($i) => $i['situacao'] !== C::PREENCHER));
        if ($problemas !== []) {
            $limite = max(0, (int) $this->option('limite'));
            $this->warn(count($problemas).' conflito(s)/bloqueio(s) para decidir à mão'.(count($problemas) > $limite ? " (mostrando {$limite}; use --csv para todos)" : '').':');
            $this->table(['Usuário', 'Nome', 'Campo', 'Situação', 'Em usuarios', 'Em '.'alunos/professores', 'Origem', 'Motivo'],
                array_map(fn ($i) => [$i['usuario_id'], mb_strimwidth($i['nome'], 0, 28, '…'), $i['campo'], $i['situacao'], $i['atual'] ?? '—', $i['novo'], $i['origem'], $i['motivo'] ?? ''],
                    array_slice($problemas, 0, $limite)));
        }

        if ($csv = $this->option('csv')) {
            $f = fopen($csv, 'w');
            fputcsv($f, ['usuario_id', 'nome', 'campo', 'situacao', 'em_usuarios', 'em_alunos_professores', 'origem', 'motivo']);
            foreach ($itens as $i) {
                fputcsv($f, [$i['usuario_id'], $i['nome'], $i['campo'], $i['situacao'], $i['atual'], $i['novo'], $i['origem'], $i['motivo']]);
            }
            fclose($f);
            $this->line("CSV: {$csv}");
        }

        if (! $this->option('aplicar')) {
            $this->line('Nada foi gravado. Rode com --aplicar para preencher os campos vazios.');

            return self::SUCCESS;
        }

        $preencher = count(array_filter($itens, fn ($i) => $i['situacao'] === C::PREENCHER && ! in_array($i['campo'], ['nome', 'email'], true)));
        if ($preencher === 0) {
            $this->info('Nada para preencher.');

            return self::SUCCESS;
        }
        if (! $this->option('force') && ! $this->confirm("Preencher {$preencher} campo(s) vazio(s) em usuarios? Nenhum valor existente é alterado.")) {
            return self::FAILURE;
        }

        $this->info($service->aplicar($itens).' usuário(s) atualizados.');

        return self::SUCCESS;
    }
}
