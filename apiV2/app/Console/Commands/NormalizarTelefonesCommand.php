<?php

namespace App\Console\Commands;

use App\Support\TelefoneLegado;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use libphonenumber\PhoneNumberUtil;

class NormalizarTelefonesCommand extends Command
{
    protected $signature = 'telefones:normalizar
        {--aplicar : Efetiva somente conversões seguras, guardando o original}
        {--pais-legado= : ISO do país confirmado após auditoria dos registros sem DDI}
        {--tabela= : Restringe a uma tabela operacional}
        {--id= : Restringe a um registro da tabela indicada}
        {--detalhes : Mostra IDs e classificação, sem expor os números}';

    protected $description = 'Audita telefones em modo leitura por padrão; não presume país para legados sem DDI';

    public function handle(): int
    {
        $pais = $this->option('pais-legado') ? strtoupper((string) $this->option('pais-legado')) : null;
        if ($pais !== null && ! in_array($pais, PhoneNumberUtil::getInstance()->getSupportedRegions(), true)) {
            $this->error('País inválido. Use o código ISO, como BR, DE, US ou PT.');

            return self::FAILURE;
        }
        $colunas = TelefoneLegado::colunas();
        $tabelaEscolhida = $this->option('tabela');
        if ($tabelaEscolhida && ! isset($colunas[$tabelaEscolhida])) {
            $this->error('Tabela não suportada ou sem colunas de telefone.');

            return self::FAILURE;
        }
        if ($this->option('id') && (! $tabelaEscolhida || ! ctype_digit((string) $this->option('id')))) {
            $this->error('--id exige um identificador numérico e --tabela.');

            return self::FAILURE;
        }
        if ($this->option('aplicar') && ! Schema::hasTable('telefone_normalizacoes')) {
            $this->error('Execute primeiro a migração create_telefone_normalizacoes_table. A auditoria sem --aplicar não precisa dela.');

            return self::FAILURE;
        }

        $this->info($this->option('aplicar') ? 'Aplicação explícita; originais preservados no banco.' : 'AUDITORIA SOMENTE LEITURA. Nenhum dado será alterado.');
        foreach ($colunas as $tabela => $campos) {
            if ($tabelaEscolhida && $tabelaEscolhida !== $tabela) {
                continue;
            }
            $indices = Schema::getIndexes($tabela);
            $unicos = array_filter($indices, fn ($i) => $i['unique'] && array_intersect($i['columns'], $campos) !== []);
            $this->line("{$tabela}: índices de telefone: ".implode(', ', array_column(array_filter($indices, fn ($i) => array_intersect($i['columns'], $campos) !== []), 'name')));
            // Analisa a tabela inteira mesmo com --id: colisões fora do filtro também importam.
            $registros = [];
            $contagens = [];
            $grupos = [];
            foreach (DB::table($tabela)->select(['id', ...$campos])->orderBy('id')->cursor() as $row) {
                foreach ($campos as $campo) {
                    $analise = TelefoneLegado::classificar($row->{$campo}, $pais);
                    $item = $analise + ['id' => (int) $row->id, 'campo' => $campo, 'original' => $row->{$campo}];
                    $registros[] = $item;
                    if ($analise['normalizado'] !== null) {
                        $grupos[$campo][$analise['normalizado']][] = (int) $row->id;
                    }
                }
            }
            $duplicados = [];
            foreach ($grupos as $campo => $numeros) {
                foreach ($numeros as $ids) {
                    if (count($ids) > 1) {
                        $this->warn("{$tabela}.{$campo}: número equivalente em ".count($ids).' registros (não serão unificados).');
                        foreach ($ids as $id) {
                            $duplicados[$campo][$id] = true;
                        }
                    }
                }
            }
            foreach ($registros as $item) {
                if ($this->option('id') && $item['id'] !== (int) $this->option('id')) {
                    continue;
                }
                $campo = $item['campo'];
                $situacao = $item['situacao'];
                $temIndiceUnico = array_filter($unicos, fn ($i) => in_array($campo, $i['columns'], true)) !== [];
                if ($situacao === 'converter' && $temIndiceUnico && isset($duplicados[$campo][$item['id']])) {
                    $situacao = 'colisao_indice_unico_revisar';
                }
                $contagens[$situacao] = ($contagens[$situacao] ?? 0) + 1;
                if ($this->option('detalhes') && ! in_array($situacao, ['vazio', 'normalizado'], true)) {
                    $this->line("{$tabela}#{$item['id']}.{$campo}: {$situacao}");
                }
                if ($this->option('aplicar') && $situacao === 'converter') {
                    DB::transaction(function () use ($tabela, $campo, $item) {
                        $atual = DB::table($tabela)->where('id', $item['id'])->lockForUpdate()->first([$campo]);
                        if (! $atual || $atual->{$campo} !== $item['original']) {
                            throw new \RuntimeException("{$tabela}#{$item['id']}.{$campo} mudou durante a auditoria. Repita o comando.");
                        }
                        DB::table('telefone_normalizacoes')->insert([
                            'tabela' => $tabela, 'coluna' => $campo, 'registro_id' => $item['id'],
                            'valor_original' => $item['original'], 'valor_normalizado' => $item['normalizado'],
                            'hash_original' => hash('sha256', $item['original']), 'created_at' => now(),
                        ]);
                        DB::table($tabela)->where('id', $item['id'])->update([$campo => $item['normalizado']]);
                    });
                }
            }
            foreach ($contagens as $situacao => $quantidade) {
                $this->line("{$tabela}: {$situacao} = {$quantidade}");
            }
        }
        $this->info('Concluído. Casos para revisão e números compartilhados não foram apagados nem unificados.');

        return self::SUCCESS;
    }
}
