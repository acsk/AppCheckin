<?php

use App\Support\Papel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Estrutura de permissões (fase 1, sem mudança visível):
 * - papel Financeiro (6);
 * - tenants.admin_principal_id: o admin titular da academia (só o super admin troca);
 * - tenant_usuario_permissoes.efeito: conceder | retirar (ajuste individual sobre o padrão do papel).
 * Regras em App\Services\PermissaoService e App\Support\Permissao.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('papeis')->where('id', Papel::FINANCEIRO)->exists()) {
            DB::table('papeis')->insert([
                'id' => Papel::FINANCEIRO, 'nome' => 'financeiro', 'descricao' => 'Equipe financeira: alunos, matrículas e cobranças',
                'nivel' => 60, 'ativo' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        if (! Schema::hasColumn('tenant_usuario_permissoes', 'efeito')) {
            Schema::table('tenant_usuario_permissoes', function (Blueprint $t) {
                $t->string('efeito', 10)->default('conceder')->comment('conceder | retirar (sobre o padrão do papel)');
            });
        }

        if (! Schema::hasColumn('tenants', 'admin_principal_id')) {
            Schema::table('tenants', function (Blueprint $t) {
                $t->integer('admin_principal_id')->nullable()->comment('Admin titular: só o super admin altera; outros admins não o modificam');
                $t->foreign('admin_principal_id', 'fk_tenants_admin_principal')->references('id')->on('usuarios')->nullOnDelete();
            });
        }

        $this->definirAdminsPrincipais();
    }

    /**
     * Para cada academia sem titular: o admin ativo cujo e-mail é o do responsável (ou o da academia);
     * sem correspondência, o admin vinculado há mais tempo. Super admins ficam de fora.
     */
    private function definirAdminsPrincipais(): void
    {
        $superAdmins = DB::table('tenant_usuario_papel')->where('papel_id', Papel::SUPER_ADMIN)->pluck('usuario_id')->all();

        foreach (DB::table('tenants')->where('id', '<>', 1)->whereNull('admin_principal_id')->get(['id', 'email', 'responsavel_email']) as $t) {
            $admins = DB::table('tenant_usuario_papel as tup')
                ->join('usuarios as u', 'u.id', '=', 'tup.usuario_id')
                ->where('tup.tenant_id', $t->id)->where('tup.papel_id', Papel::ADMIN)->where('tup.ativo', 1)
                ->whereNotIn('u.id', $superAdmins ?: [0])
                ->orderBy('tup.created_at')->orderBy('tup.id')
                ->get(['u.id', 'u.email']);
            if ($admins->isEmpty()) {
                continue;
            }

            $emails = array_filter([mb_strtolower(trim((string) $t->responsavel_email)), mb_strtolower(trim((string) $t->email))]);
            $principal = $admins->first(fn ($a) => in_array(mb_strtolower(trim((string) $a->email)), $emails, true)) ?? $admins->first();

            DB::table('tenants')->where('id', $t->id)->update(['admin_principal_id' => $principal->id]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tenants', 'admin_principal_id')) {
            Schema::table('tenants', function (Blueprint $t) {
                $t->dropForeign('fk_tenants_admin_principal');
                $t->dropColumn('admin_principal_id');
            });
        }
        if (Schema::hasColumn('tenant_usuario_permissoes', 'efeito')) {
            Schema::table('tenant_usuario_permissoes', fn (Blueprint $t) => $t->dropColumn('efeito'));
        }
        DB::table('papeis')->where('id', Papel::FINANCEIRO)->delete();
    }
};
