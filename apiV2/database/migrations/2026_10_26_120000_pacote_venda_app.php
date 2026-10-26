<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pacote vendido pelo app.
 * - pacotes.venda_app: nao | liberados (só alunos de pacote_liberacoes) | todos.
 * - pacote_liberacoes: aluno que pode contratar um pacote pelo app (ex.: depois de uma negociação), com validade opcional.
 * - pacote_contratos.origem: admin (contratado no painel) | app (o próprio aluno contratou).
 * - pacote_cobrancas: parcela do pagante cobrada como pagamento do pacote inteiro. Quando ela é paga
 *   (por qualquer caminho: webhook Asaas, Mercado Pago na API Slim ou baixa manual), as parcelas dos
 *   demais no mesmo vencimento são quitadas (PacoteConciliacaoService).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('pacotes', 'venda_app')) {
            Schema::table('pacotes', function (Blueprint $t) {
                $t->string('venda_app', 12)->default('nao')->after('ativo')
                    ->comment('nao | liberados | todos');
            });
        }

        if (! Schema::hasColumn('pacote_contratos', 'origem')) {
            Schema::table('pacote_contratos', function (Blueprint $t) {
                $t->string('origem', 10)->default('admin')->after('status')->comment('admin | app');
            });
        }

        if (! Schema::hasTable('pacote_liberacoes')) {
            Schema::create('pacote_liberacoes', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('tenant_id');
                $t->unsignedInteger('pacote_id');
                $t->unsignedInteger('aluno_id');
                $t->date('valida_ate')->nullable();
                $t->string('observacao', 255)->nullable();
                $t->unsignedInteger('criado_por')->nullable();
                $t->timestamps();
                $t->unique(['pacote_id', 'aluno_id']);
                $t->index(['tenant_id', 'aluno_id']);
            });
        }

        if (! Schema::hasTable('pacote_cobrancas')) {
            Schema::create('pacote_cobrancas', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('tenant_id');
                $t->unsignedInteger('pacote_contrato_id');
                $t->unsignedInteger('pagamento_plano_id')->unique();
                $t->decimal('valor', 10, 2);
                $t->string('payment_id', 100)->nullable();
                $t->timestamps();
                $t->index(['tenant_id', 'pacote_contrato_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pacote_cobrancas');
        Schema::dropIfExists('pacote_liberacoes');
        if (Schema::hasColumn('pacote_contratos', 'origem')) {
            Schema::table('pacote_contratos', fn (Blueprint $t) => $t->dropColumn('origem'));
        }
        if (Schema::hasColumn('pacotes', 'venda_app')) {
            Schema::table('pacotes', fn (Blueprint $t) => $t->dropColumn('venda_app'));
        }
    }
};
