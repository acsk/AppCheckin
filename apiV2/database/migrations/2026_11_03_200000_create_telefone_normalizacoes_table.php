<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('telefone_normalizacoes')) {
            Schema::create('telefone_normalizacoes', function (Blueprint $t) {
                $t->id();
                $t->string('tabela', 64);
                $t->string('coluna', 64);
                $t->unsignedBigInteger('registro_id');
                $t->text('valor_original');
                $t->string('valor_normalizado', 20);
                $t->string('hash_original', 64);
                $t->timestamp('created_at');
                $t->unique(['tabela', 'coluna', 'registro_id', 'hash_original'], 'telefone_normalizacao_origem_unique');
            });
        }
    }

    public function down(): void
    {
        // Não apagar os valores originais ao reverter a aplicação.
    }
};
