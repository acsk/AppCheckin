<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modelo de cobrança dos planos do SaaS (App\Support\TipoCobrancaPlanoSistema):
 * gratuito | fixo (valor mensal) | por_aluno (valor_por_aluno x alunos ativos).
 * codigo identifica planos usados pelo sistema (ex.: 'free' para o contrato padrão de novos tenants).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planos_sistema', function (Blueprint $table) {
            if (! Schema::hasColumn('planos_sistema', 'codigo')) {
                $table->string('codigo', 50)->nullable()->unique()->after('id');
            }
            if (! Schema::hasColumn('planos_sistema', 'tipo_cobranca')) {
                $table->string('tipo_cobranca', 20)
                    ->default('fixo')
                    ->after('descricao')
                    ->comment('gratuito | fixo | por_aluno');
            }
            if (! Schema::hasColumn('planos_sistema', 'valor_por_aluno')) {
                $table->decimal('valor_por_aluno', 10, 2)->nullable()->after('valor');
            }
        });
    }

    public function down(): void
    {
        Schema::table('planos_sistema', function (Blueprint $table) {
            if (Schema::hasColumn('planos_sistema', 'codigo')) {
                $table->dropUnique(['codigo']);
                $table->dropColumn('codigo');
            }
            if (Schema::hasColumn('planos_sistema', 'tipo_cobranca')) {
                $table->dropColumn('tipo_cobranca');
            }
            if (Schema::hasColumn('planos_sistema', 'valor_por_aluno')) {
                $table->dropColumn('valor_por_aluno');
            }
        });
    }
};
