<?php

use App\Support\TelefoneLegado;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $colunas = TelefoneLegado::colunas();
        // Pré-validação global antes de qualquer ALTER: nunca truncar um legado não resolvido.
        foreach ($colunas as $tabela => $campos) {
            foreach ($campos as $campo) {
                if (DB::table($tabela)->whereRaw('LENGTH('.DB::connection()->getQueryGrammar()->wrap($campo).') > 20')->exists()) {
                    throw new RuntimeException("{$tabela}.{$campo} contém valores maiores que 20 caracteres. Execute telefones:normalizar, revise os casos e repita a migração; nenhum telefone foi truncado.");
                }
            }
        }
        foreach ($colunas as $tabela => $campos) {
            $definicoes = array_column(Schema::getColumns($tabela), null, 'name');
            Schema::table($tabela, function (Blueprint $t) use ($campos, $definicoes) {
                foreach ($campos as $campo) {
                    $definicao = $definicoes[$campo];
                    $coluna = $t->string($campo, 20)->nullable($definicao['nullable'])
                        ->comment('E.164; legados não resolvidos preservados até revisão');
                    if ($definicao['default'] !== null) {
                        $coluna->default($definicao['default']);
                    }
                    $coluna->change();
                }
            });
        }
    }

    public function down(): void
    {
        // Retornar a VARCHAR(11) destruiria números internacionais já cadastrados.
    }
};
