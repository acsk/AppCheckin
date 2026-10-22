<?php

use Database\Seeders\MotivoAcessoSuporteSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Motivo do "Acessar como academia" passa a ser escolhido de uma lista (motivos_acesso_suporte):
 * acessos_suporte guarda só motivo_id e, quando precisa, um detalhe curto (obrigatório em "Outro").
 * Os textos livres já gravados viram "Outro" + detalhe; a coluna motivo sai.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('motivos_acesso_suporte')) {
            Schema::create('motivos_acesso_suporte', function (Blueprint $t) {
                $t->increments('id');
                $t->string('nome', 120)->unique();
                $t->boolean('exige_detalhe')->default(false)->comment('Pede o detalhe ao escolher (ex.: Outro)');
                $t->unsignedSmallInteger('ordem')->default(0);
                $t->boolean('ativo')->default(true)->comment('Inativo some da lista; acessos antigos continuam com ele');
                $t->timestamps();
            });
        }
        (new MotivoAcessoSuporteSeeder)->run();

        if (! Schema::hasColumn('acessos_suporte', 'motivo_id')) {
            Schema::table('acessos_suporte', function (Blueprint $t) {
                $t->unsignedInteger('motivo_id')->nullable()->after('usuario_id');
                $t->string('detalhe', 500)->nullable()->after('motivo_id')->comment('Complemento do motivo (obrigatório quando o motivo exige)');
                $t->foreign('motivo_id')->references('id')->on('motivos_acesso_suporte')->restrictOnDelete();
            });
        }

        if (Schema::hasColumn('acessos_suporte', 'motivo')) {
            $outro = (int) DB::table('motivos_acesso_suporte')->where('nome', MotivoAcessoSuporteSeeder::OUTRO)->value('id');
            DB::table('acessos_suporte')->whereNull('motivo_id')->orderBy('id')->each(function ($a) use ($outro) {
                DB::table('acessos_suporte')->where('id', $a->id)
                    ->update(['motivo_id' => $outro, 'detalhe' => mb_substr(trim((string) $a->motivo), 0, 500) ?: null]);
            });
            Schema::table('acessos_suporte', function (Blueprint $t) {
                $t->dropColumn('motivo');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('acessos_suporte', 'motivo')) {
            Schema::table('acessos_suporte', function (Blueprint $t) {
                $t->text('motivo')->nullable();
            });
        }
        DB::table('acessos_suporte as a')->join('motivos_acesso_suporte as m', 'm.id', '=', 'a.motivo_id')
            ->get(['a.id', 'm.nome', 'a.detalhe'])
            ->each(fn ($r) => DB::table('acessos_suporte')->where('id', $r->id)
                ->update(['motivo' => $r->detalhe && $r->nome === MotivoAcessoSuporteSeeder::OUTRO ? $r->detalhe : trim($r->nome.($r->detalhe ? ': '.$r->detalhe : ''))]));
        if (Schema::hasColumn('acessos_suporte', 'motivo_id')) {
            Schema::table('acessos_suporte', function (Blueprint $t) {
                $t->dropForeign(['motivo_id']);
                $t->dropColumn(['motivo_id', 'detalhe']);
            });
        }
        Schema::dropIfExists('motivos_acesso_suporte');
    }
};
