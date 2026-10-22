<?php

use App\Support\Papel;
use App\Support\Permissao;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Permissões pontuais por usuário em cada academia (além do papel). Concedidas pelo super admin
 * na ficha da academia; conferidas pelo middleware `permissao:<codigo>`. Catálogo em App\Support\Permissao.
 *
 * Substitui a regra fixa "usuário id 3" do painel: quem já tinha o acesso (id 3, admin das academias
 * em que é admin) recebe a permissão aqui.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenant_usuario_permissoes')) {
            Schema::create('tenant_usuario_permissoes', function (Blueprint $t) {
                $t->increments('id');
                $t->integer('tenant_id');
                $t->integer('usuario_id');
                $t->string('permissao', 60);
                $t->integer('concedido_por')->nullable();
                $t->timestamp('created_at')->useCurrent();

                $t->unique(['tenant_id', 'usuario_id', 'permissao'], 'uk_tup_permissao');
                $t->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $t->foreign('usuario_id')->references('id')->on('usuarios')->cascadeOnDelete();
            });
        }

        $tenants = DB::table('tenant_usuario_papel')
            ->where('usuario_id', 3)->where('papel_id', Papel::ADMIN)->where('ativo', 1)->pluck('tenant_id');
        foreach ($tenants as $tenantId) {
            DB::table('tenant_usuario_permissoes')->insertOrIgnore([
                'tenant_id' => $tenantId, 'usuario_id' => 3, 'permissao' => Permissao::FINANCEIRO_AVANCADO, 'created_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_usuario_permissoes');
    }
};
