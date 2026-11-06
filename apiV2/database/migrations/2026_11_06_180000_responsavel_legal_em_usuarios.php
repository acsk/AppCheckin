<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Responsável legal do aluno menor de idade (App\Support\ResponsavelLegal).
 * Fica em usuarios, como os outros dados pessoais (App\Support\Pessoa).
 */
return new class extends Migration
{
    private const COLUNAS = [
        'responsavel_nome' => 150,
        'responsavel_cpf' => 11,
        'responsavel_email' => 255,
        'responsavel_telefone' => 20,
        'responsavel_parentesco' => 20,
    ];

    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $t) {
            foreach (self::COLUNAS as $coluna => $tamanho) {
                if (! Schema::hasColumn('usuarios', $coluna)) {
                    $t->string($coluna, $tamanho)->nullable();
                }
            }
        });

        if (! $this->temIndice()) {
            Schema::table('usuarios', function (Blueprint $t) {
                $t->index('responsavel_cpf', 'idx_usuarios_responsavel_cpf');
            });
        }
    }

    public function down(): void
    {
        if ($this->temIndice()) {
            Schema::table('usuarios', function (Blueprint $t) {
                $t->dropIndex('idx_usuarios_responsavel_cpf');
            });
        }
        Schema::table('usuarios', function (Blueprint $t) {
            $existentes = array_values(array_filter(array_keys(self::COLUNAS), fn ($c) => Schema::hasColumn('usuarios', $c)));
            if ($existentes !== []) {
                $t->dropColumn($existentes);
            }
        });
    }

    private function temIndice(): bool
    {
        foreach (Schema::getIndexes('usuarios') as $indice) {
            if (($indice['name'] ?? '') === 'idx_usuarios_responsavel_cpf') {
                return true;
            }
        }

        return false;
    }
};
