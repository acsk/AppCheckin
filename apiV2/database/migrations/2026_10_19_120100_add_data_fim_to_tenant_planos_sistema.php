<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fim do contrato do tenant. NULL = prazo indeterminado (ex.: contrato Free).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('tenant_planos_sistema', 'data_fim')) {
            return;
        }

        Schema::table('tenant_planos_sistema', function (Blueprint $table) {
            $table->date('data_fim')
                ->nullable()
                ->after('data_inicio')
                ->comment('NULL = prazo indeterminado');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('tenant_planos_sistema', 'data_fim')) {
            return;
        }

        Schema::table('tenant_planos_sistema', function (Blueprint $table) {
            $table->dropColumn('data_fim');
        });
    }
};
