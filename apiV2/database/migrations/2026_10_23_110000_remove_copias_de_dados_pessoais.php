<?php

use App\Services\SuperAdmin\ConsolidacaoPessoasService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 3 da consolidação: apaga as cópias dos dados pessoais em alunos/professores e colunas legadas de usuarios.
 *
 * - Trava: não roda se `php artisan pessoas:consolidar` ainda tiver campos para preencher (rode --aplicar antes).
 * - Foto: a de alunos é a mais nova (o app só gravava lá), então ela vai para usuarios antes de apagar.
 * - Backup: todo valor apagado fica em copias_dados_pessoais_removidas (tabela, registro, campo, valor);
 *   conflitos não decididos (ex.: telefone diferente) ficam lá. O down() devolve as colunas e os valores.
 * - O nome continua em alunos/professores (espelho gravado por UsuarioRepository::gravarDadosPessoais).
 */
return new class extends Migration
{
    /** tabela => [coluna => tipo para o down()] */
    private const COLUNAS = [
        'alunos' => [
            'telefone' => ['string', 20], 'whatsapp' => ['string', 32], 'cpf' => ['string', 14], 'data_nascimento' => ['date'],
            'sexo' => ['string', 20], 'cep' => ['string', 10], 'logradouro' => ['string', 255], 'numero' => ['string', 20],
            'complemento' => ['string', 100], 'bairro' => ['string', 100], 'cidade' => ['string', 100], 'estado' => ['string', 2],
            'foto_url' => ['string', 500], 'foto_base64' => ['longText'], 'foto_caminho' => ['string', 255],
        ],
        'professores' => [
            'cpf' => ['string', 14], 'telefone' => ['string', 20], 'whatsapp' => ['string', 11], 'data_nascimento' => ['date'], 'foto_url' => ['string', 500],
        ],
        'usuarios' => [
            'foto_base64' => ['longText'], 'role_id_bkp' => ['integer'], 'reset_token' => ['string', 100], 'reset_token_expiry' => ['dateTime'],
        ],
    ];

    private const BACKUP = 'copias_dados_pessoais_removidas';

    public function up(): void
    {
        $pendentes = array_filter(
            app(ConsolidacaoPessoasService::class)->analisar(),
            fn ($i) => $i['situacao'] === ConsolidacaoPessoasService::PREENCHER && ! in_array($i['campo'], ['nome', 'email'], true),
        );
        if ($pendentes !== []) {
            throw new RuntimeException(count($pendentes).' dado(s) pessoais ainda só existem em alunos/professores. '
                .'Rode "php artisan pessoas:consolidar" (relatório) e "php artisan pessoas:consolidar --aplicar" antes desta migration.');
        }

        if (! Schema::hasTable(self::BACKUP)) {
            Schema::create(self::BACKUP, function (Blueprint $t) {
                $t->increments('id');
                $t->string('tabela', 30);
                $t->unsignedInteger('registro_id');
                $t->unsignedInteger('usuario_id')->nullable();
                $t->string('campo', 40);
                $t->longText('valor');
                $t->timestamp('created_at')->nullable()->useCurrent();
                $t->index(['tabela', 'registro_id']);
            });
        }

        DB::transaction(function () {
            // Foto: a de alunos é a mais nova
            if (Schema::hasColumn('alunos', 'foto_caminho')) {
                DB::table('alunos')->whereNotNull('foto_caminho')->where('foto_caminho', '<>', '')->orderBy('id')
                    ->get(['usuario_id', 'foto_caminho'])
                    ->each(fn ($a) => DB::table('usuarios')->where('id', $a->usuario_id)->update(['foto_caminho' => $a->foto_caminho]));
            }

            foreach (self::COLUNAS as $tabela => $colunas) {
                $existentes = array_values(array_filter(array_keys($colunas), fn ($c) => Schema::hasColumn($tabela, $c)));
                if ($existentes === []) {
                    continue;
                }
                $usuarioCol = $tabela === 'usuarios' ? 'id' : 'usuario_id';
                // Idempotente: o MySQL não desfaz DDL, então uma execução interrompida pode ser repetida
                DB::table(self::BACKUP)->where('tabela', $tabela)->whereIn('campo', $existentes)->delete();
                DB::table($tabela)->orderBy('id')->select(['id', "{$usuarioCol} as usuario_id", ...$existentes])
                    ->chunk(500, function ($linhas) use ($tabela, $existentes) {
                        $backup = [];
                        foreach ($linhas as $l) {
                            foreach ($existentes as $c) {
                                if ($l->{$c} !== null && $l->{$c} !== '') {
                                    $backup[] = ['tabela' => $tabela, 'registro_id' => $l->id, 'usuario_id' => $l->usuario_id, 'campo' => $c, 'valor' => (string) $l->{$c}];
                                }
                            }
                        }
                        if ($backup !== []) {
                            DB::table(self::BACKUP)->insert($backup);
                        }
                    });
            }
        });

        foreach (self::COLUNAS as $tabela => $colunas) {
            $existentes = array_values(array_filter(array_keys($colunas), fn ($c) => Schema::hasColumn($tabela, $c)));
            if ($existentes === []) {
                continue;
            }
            // Chaves estrangeiras e índices nessas colunas (ex.: usuarios.role_id_bkp, professores.cpf UNIQUE) saem antes
            $fks = array_filter(Schema::getForeignKeys($tabela), fn ($f) => array_intersect($f['columns'], $existentes) !== []);
            if ($fks !== []) {
                Schema::table($tabela, function (Blueprint $t) use ($fks) {
                    foreach ($fks as $f) {
                        $t->dropForeign($f['name']);
                    }
                });
            }
            $indices = array_filter(Schema::getIndexes($tabela), fn ($i) => ! $i['primary'] && array_intersect($i['columns'], $existentes) !== []);
            Schema::table($tabela, function (Blueprint $t) use ($indices) {
                foreach ($indices as $i) {
                    $i['unique'] ? $t->dropUnique($i['name']) : $t->dropIndex($i['name']);
                }
            });
            Schema::table($tabela, fn (Blueprint $t) => $t->dropColumn($existentes));
        }
    }

    public function down(): void
    {
        foreach (self::COLUNAS as $tabela => $colunas) {
            Schema::table($tabela, function (Blueprint $t) use ($tabela, $colunas) {
                foreach ($colunas as $c => $tipo) {
                    if (! Schema::hasColumn($tabela, $c)) {
                        $t->{$tipo[0]}($c, ...array_slice($tipo, 1))->nullable();
                    }
                }
            });
        }

        if (Schema::hasTable(self::BACKUP)) {
            DB::table(self::BACKUP)->orderBy('id')->chunk(500, function ($linhas) {
                foreach ($linhas as $b) {
                    DB::table($b->tabela)->where('id', $b->registro_id)->update([$b->campo => $b->valor]);
                }
            });
            Schema::drop(self::BACKUP);
        }
    }
};
