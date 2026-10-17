<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Antecipação automática Asaas (D+2): toggle + taxas mensais (1,15% à vista / 1,6% parcelado)
 * e persistência da tarifa de cartão vs sobretaxa em pagamentos_plano.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tenant_payment_credentials') && (
            ! Schema::hasColumn('tenant_payment_credentials', 'enable_sobretaxa_antecipacao')
            || ! Schema::hasColumn('tenant_payment_credentials', 'sobretaxa_antecipacao_avista_percentual')
            || ! Schema::hasColumn('tenant_payment_credentials', 'sobretaxa_antecipacao_parcelada_percentual')
        )) {
            Schema::table('tenant_payment_credentials', function (Blueprint $table) {
                if (! Schema::hasColumn('tenant_payment_credentials', 'enable_sobretaxa_antecipacao')) {
                    $col = $table->boolean('enable_sobretaxa_antecipacao')->default(false)
                        ->comment('Repassa taxa de antecipação automática Asaas (D+2) no cartão');
                    if (Schema::hasColumn('tenant_payment_credentials', 'enable_credit_card')) {
                        $col->after('enable_credit_card');
                    }
                }
                if (! Schema::hasColumn('tenant_payment_credentials', 'sobretaxa_antecipacao_avista_percentual')) {
                    $table->decimal('sobretaxa_antecipacao_avista_percentual', 5, 2)
                        ->default(1.15)
                        ->after('enable_sobretaxa_antecipacao')
                        ->comment('Percentual ao mês à vista (Asaas: 1,15%)');
                }
                if (! Schema::hasColumn('tenant_payment_credentials', 'sobretaxa_antecipacao_parcelada_percentual')) {
                    $table->decimal('sobretaxa_antecipacao_parcelada_percentual', 5, 2)
                        ->default(1.60)
                        ->after('sobretaxa_antecipacao_avista_percentual')
                        ->comment('Percentual ao mês no parcelado (Asaas: 1,6%)');
                }
            });
        }

        if (Schema::hasTable('pagamentos_plano') && (
            ! Schema::hasColumn('pagamentos_plano', 'tarifa_cartao')
            || ! Schema::hasColumn('pagamentos_plano', 'sobretaxa_antecipacao')
        )) {
            Schema::table('pagamentos_plano', function (Blueprint $table) {
                if (! Schema::hasColumn('pagamentos_plano', 'tarifa_cartao')) {
                    $table->decimal('tarifa_cartao', 10, 2)
                        ->nullable()
                        ->default(0)
                        ->comment('Taxa de cartão do Asaas embutida (não misturar com desconto_provedor)');
                }
                if (! Schema::hasColumn('pagamentos_plano', 'sobretaxa_antecipacao')) {
                    $table->decimal('sobretaxa_antecipacao', 10, 2)
                        ->nullable()
                        ->default(0)
                        ->comment('Acréscimo complementar da antecipação automática');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pagamentos_plano')) {
            $drop = array_values(array_filter(
                ['sobretaxa_antecipacao', 'tarifa_cartao'],
                fn (string $c) => Schema::hasColumn('pagamentos_plano', $c)
            ));
            if ($drop !== []) {
                Schema::table('pagamentos_plano', function (Blueprint $table) use ($drop) {
                    $table->dropColumn($drop);
                });
            }
        }

        if (Schema::hasTable('tenant_payment_credentials')) {
            $drop = array_values(array_filter(
                [
                    'sobretaxa_antecipacao_parcelada_percentual',
                    'sobretaxa_antecipacao_avista_percentual',
                    'enable_sobretaxa_antecipacao',
                ],
                fn (string $c) => Schema::hasColumn('tenant_payment_credentials', $c)
            ));
            if ($drop !== []) {
                Schema::table('tenant_payment_credentials', function (Blueprint $table) use ($drop) {
                    $table->dropColumn($drop);
                });
            }
        }
    }
};
