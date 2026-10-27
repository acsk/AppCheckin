<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Planos com fidelização: assinatura mensal com preço promocional condicionado à permanência mínima.
 * Não é venda parcelada: cada mês é uma cobrança independente no gateway (assinatura Asaas).
 *
 * - plano_ciclos.cobranca_mensal: 0 = à vista (o ciclo inteiro numa cobrança, como sempre foi);
 *   1 = fidelização mensal. Mensalidade = plano_ciclos.valor_mensal_equivalente (valor ÷ meses);
 *   referência = planos.valor (o mensal sem desconto). Nada é duplicado no cadastro.
 * - contratos_recorrencia congela valores, prazo, políticas e o aceite dos termos: mudar o preço do
 *   ciclo depois não altera contratos existentes.
 * - recorrencia_cobrancas: uma linha por mensalidade (número único por contrato e id único do
 *   gateway) ligada à parcela de pagamentos_plano. É a trava contra cobrança/baixa duplicada.
 * - cancelamentos_recorrencia guarda a memória de cálculo da regularização.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plano_ciclos', function (Blueprint $t) {
            if (! Schema::hasColumn('plano_ciclos', 'cobranca_mensal')) {
                $t->boolean('cobranca_mensal')->default(false)->after('permite_reposicao')->comment('0 à vista | 1 fidelização com cobrança mensal');
                $t->unsignedTinyInteger('fidelizacao_meses')->nullable()->after('cobranca_mensal')->comment('padrão = meses');
                $t->string('politica_renovacao', 20)->default('mensal')->after('fidelizacao_meses')->comment('encerrar | mensal | promocional | nova_fidelizacao');
                $t->boolean('regularizacao_ativa')->default(false)->after('politica_renovacao')->comment('cobra o desconto usufruído no cancelamento antecipado');
                $t->boolean('regularizacao_proporcional')->default(false)->after('regularizacao_ativa');
                $t->unsignedTinyInteger('arrependimento_dias')->default(7)->after('regularizacao_proporcional');
                $t->text('termos')->nullable()->after('arrependimento_dias')->comment('texto adicional mostrado no aceite');
                $t->unsignedSmallInteger('termos_versao')->default(1)->after('termos');
            }
        });

        if (! Schema::hasTable('contratos_recorrencia')) {
            Schema::create('contratos_recorrencia', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('tenant_id');
                $t->unsignedInteger('aluno_id');
                $t->unsignedInteger('matricula_id');
                $t->unsignedInteger('plano_id');
                $t->unsignedInteger('plano_ciclo_id');
                $t->string('gateway', 20)->default('asaas');
                $t->string('assinatura_gateway_id', 100)->nullable();
                $t->string('gateway_cliente_id', 100)->nullable();
                $t->string('metodo', 12)->comment('cartao | pix | boleto');
                $t->decimal('valor_mensal_referencia', 10, 2);
                $t->decimal('valor_mensal_contratado', 10, 2)->comment('preço promocional aceito (não muda)');
                $t->decimal('valor_mensal_vigente', 10, 2)->comment('o que o gateway cobra hoje (ex.: referência depois da fidelização)');
                $t->unsignedTinyInteger('fidelizacao_meses');
                $t->date('inicio_em');
                $t->date('fim_fidelizacao_em');
                $t->date('proxima_cobranca_em')->nullable();
                $t->string('status', 30)->default('pendente_ativacao')
                    ->comment('pendente_ativacao | ativo | pagamento_pendente | inadimplente | cancelamento_solicitado | cancelamento_agendado | encerrado | cancelado');
                $t->boolean('em_fidelizacao')->default(true)->comment('0 depois de cumprir o prazo ou renovar sem novo compromisso');
                $t->string('politica_renovacao', 20);
                $t->json('politica_cancelamento')->comment('regularizacao_ativa, proporcional, arrependimento_dias no aceite');
                $t->unsignedSmallInteger('termos_versao');
                $t->text('termos_aceitos')->comment('texto exato aceito');
                $t->char('termos_hash', 64);
                $t->dateTime('aceite_em');
                $t->unsignedInteger('aceite_usuario_id');
                $t->string('aceite_ip', 45)->nullable();
                $t->string('aceite_user_agent', 255)->nullable();
                $t->dateTime('renovacao_oferecida_em')->nullable()->comment('nova fidelização aguardando aceite do aluno');
                $t->unsignedBigInteger('contrato_anterior_id')->nullable();
                $t->dateTime('encerrado_em')->nullable();
                $t->timestamps();
                $t->index(['tenant_id', 'status']);
                $t->index(['tenant_id', 'aluno_id']);
                $t->index('matricula_id');
                $t->unique('assinatura_gateway_id');
            });
        }

        if (! Schema::hasTable('recorrencia_cobrancas')) {
            Schema::create('recorrencia_cobrancas', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('tenant_id');
                $t->unsignedBigInteger('contrato_id');
                $t->unsignedSmallInteger('numero')->comment('nº da mensalidade no contrato');
                $t->date('vencimento')->comment('início do mês que a mensalidade paga');
                $t->decimal('valor', 10, 2);
                $t->string('gateway_payment_id', 100)->nullable();
                $t->unsignedInteger('pagamento_plano_id')->nullable();
                $t->string('status', 12)->default('pendente')->comment('pendente | paga | vencida | estornada | cancelada');
                $t->date('pago_em')->nullable();
                $t->unsignedTinyInteger('tentativas')->default(0);
                $t->string('url', 500)->nullable();
                $t->timestamps();
                $t->unique(['contrato_id', 'numero']);
                $t->unique('gateway_payment_id');
                $t->index(['tenant_id', 'status']);
                $t->foreign('contrato_id')->references('id')->on('contratos_recorrencia');
            });
        }

        if (! Schema::hasTable('cancelamentos_recorrencia')) {
            Schema::create('cancelamentos_recorrencia', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('tenant_id');
                $t->unsignedBigInteger('contrato_id');
                $t->string('tipo', 20)->comment('antecipado | fim_prazo | arrependimento | impossibilidade | administrativo');
                $t->string('solicitado_por', 10)->comment('aluno | admin');
                $t->unsignedInteger('solicitado_por_usuario_id')->nullable();
                $t->dateTime('solicitado_em');
                $t->date('data_efetiva')->comment('até quando o aluno tem acesso (fim do período pago)');
                $t->dateTime('efetivado_em')->nullable();
                $t->string('motivo', 500)->nullable();
                $t->unsignedTinyInteger('meses_usufruidos');
                $t->decimal('desconto_acumulado', 10, 2);
                $t->decimal('valor_regularizacao', 10, 2)->comment('calculado pela política');
                $t->decimal('valor_isencao', 10, 2)->default(0);
                $t->string('isencao_motivo', 500)->nullable();
                $t->decimal('valor_em_aberto', 10, 2)->default(0)->comment('mensalidades vencidas não pagas, à parte');
                $t->unsignedInteger('pagamento_plano_id')->nullable()->comment('cobrança da regularização');
                $t->string('status', 12)->default('solicitado')->comment('solicitado | confirmado | recusado');
                $t->unsignedInteger('confirmado_por')->nullable();
                $t->json('memoria_calculo');
                $t->timestamps();
                $t->index(['tenant_id', 'status']);
                $t->foreign('contrato_id')->references('id')->on('contratos_recorrencia');
            });
        }

        if (! Schema::hasTable('recorrencia_eventos')) {
            Schema::create('recorrencia_eventos', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('tenant_id');
                $t->unsignedBigInteger('contrato_id');
                $t->string('tipo', 40);
                $t->string('de_status', 30)->nullable();
                $t->string('para_status', 30)->nullable();
                $t->unsignedInteger('usuario_id')->nullable();
                $t->json('dados')->nullable();
                $t->dateTime('created_at');
                $t->index(['tenant_id', 'contrato_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('recorrencia_eventos');
        Schema::dropIfExists('cancelamentos_recorrencia');
        Schema::dropIfExists('recorrencia_cobrancas');
        Schema::dropIfExists('contratos_recorrencia');
        Schema::table('plano_ciclos', function (Blueprint $t) {
            foreach (['termos_versao', 'termos', 'arrependimento_dias', 'regularizacao_proporcional', 'regularizacao_ativa', 'politica_renovacao', 'fidelizacao_meses', 'cobranca_mensal'] as $c) {
                if (Schema::hasColumn('plano_ciclos', $c)) {
                    $t->dropColumn($c);
                }
            }
        });
    }
};
