<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Planos do SaaS mais flexíveis e cobrança do contrato no gateway do Super Admin:
 * - planos_sistema.prazo_dias: duração padrão do contrato (null = sem prazo);
 * - pagamentos_contrato: dados da cobrança gerada no Asaas do tenant do sistema;
 * - plano Livre (cortesia, sem cobrança e sem limites).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('planos_sistema', 'prazo_dias')) {
            Schema::table('planos_sistema', function (Blueprint $table) {
                $table->unsignedInteger('prazo_dias')->nullable()->after('duracao_dias');
            });
        }

        Schema::table('pagamentos_contrato', function (Blueprint $table) {
            if (! Schema::hasColumn('pagamentos_contrato', 'gateway')) {
                $table->string('gateway', 30)->nullable()->after('forma_pagamento_id');
            }
            if (! Schema::hasColumn('pagamentos_contrato', 'gateway_payment_id')) {
                $table->string('gateway_payment_id', 100)->nullable()->after('gateway');
            }
            if (! Schema::hasColumn('pagamentos_contrato', 'gateway_billing_type')) {
                $table->string('gateway_billing_type', 30)->nullable()->after('gateway_payment_id');
            }
            if (! Schema::hasColumn('pagamentos_contrato', 'gateway_url')) {
                $table->string('gateway_url', 500)->nullable()->after('gateway_billing_type');
            }
        });

        if (! DB::table('planos_sistema')->where('codigo', 'livre')->exists()) {
            $agora = now();
            DB::table('planos_sistema')->insert([
                'codigo' => 'livre',
                'nome' => 'Livre',
                'descricao' => 'Cortesia sem cobrança, sem limite de alunos e sem prazo. Para parceiros.',
                'tipo_cobranca' => 'gratuito',
                'valor' => 0,
                'valor_por_aluno' => null,
                'duracao_dias' => 30,
                'prazo_dias' => null,
                'max_alunos' => null,
                'max_admins' => null,
                'ativo' => 1,
                'atual' => 1,
                'ordem' => 2,
                'created_at' => $agora,
                'updated_at' => $agora,
            ]);
        }
    }

    public function down(): void
    {
        $livre = DB::table('planos_sistema')->where('codigo', 'livre')->first();
        if ($livre && ! DB::table('tenant_planos_sistema')->where('plano_sistema_id', $livre->id)->exists()) {
            DB::table('planos_sistema')->where('id', $livre->id)->delete();
        }

        Schema::table('pagamentos_contrato', function (Blueprint $table) {
            foreach (['gateway_url', 'gateway_billing_type', 'gateway_payment_id', 'gateway'] as $coluna) {
                if (Schema::hasColumn('pagamentos_contrato', $coluna)) {
                    $table->dropColumn($coluna);
                }
            }
        });

        if (Schema::hasColumn('planos_sistema', 'prazo_dias')) {
            Schema::table('planos_sistema', function (Blueprint $table) {
                $table->dropColumn('prazo_dias');
            });
        }
    }
};
