<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monetizacao_ambientes', function (Blueprint $t) {
            $t->string('ambiente', 20)->primary();
            $t->boolean('habilitado')->default(false);
            $t->unsignedInteger('versao')->default(1);
            $t->timestamps();
        });
        Schema::create('monetizacao_aplicacoes', function (Blueprint $t) {
            $t->id();
            $t->string('identificador')->unique();
            $t->string('nome');
            $t->string('plataforma', 10);
            $t->timestamps();
        });
        Schema::create('monetizacao_provedores', function (Blueprint $t) {
            $t->id();
            $t->foreignId('aplicacao_id')->constrained('monetizacao_aplicacoes')->cascadeOnDelete();
            $t->string('provedor', 10);
            $t->string('ambiente', 20);
            $t->boolean('habilitado')->default(false);
            $t->string('publisher_id')->nullable();
            $t->string('app_id')->nullable();
            $t->string('dominio')->nullable();
            $t->json('configuracoes');
            $t->unsignedInteger('versao')->default(1);
            $t->timestamps();
            $t->unique(['aplicacao_id', 'ambiente']);
        });
        Schema::create('monetizacao_posicionamentos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('provedor_id')->constrained('monetizacao_provedores')->cascadeOnDelete();
            $t->string('identificador', 80);
            $t->string('nome', 120);
            $t->string('formato', 30);
            $t->string('unidade_id')->nullable();
            $t->string('rota')->nullable();
            $t->string('evento', 80)->nullable();
            $t->boolean('habilitado')->default(false);
            $t->unsignedInteger('prioridade')->default(0);
            $t->timestamps();
            $t->unique(['provedor_id', 'identificador']);
        });
        Schema::create('monetizacao_auditoria', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('usuario_id')->nullable();
            $t->foreignId('provedor_id')->constrained('monetizacao_provedores')->cascadeOnDelete();
            $t->json('antes');
            $t->json('depois');
            $t->timestamp('created_at');
        });
        $apps = [
            ['painel_web', 'Painel administrativo', 'web', ['web_dashboard' => 'Dashboard']],
            ['mobile_web', 'App do aluno — Web', 'web', ['web_aluno_inicio' => 'Início do aluno', 'web_aluno_rodape' => 'Rodapé do aluno']],
            ['mobile_android', 'App do aluno — Android', 'android', ['mobile_inicio' => 'Início', 'mobile_aulas' => 'Aulas', 'mobile_navegacao' => 'Navegação', 'mobile_recompensa' => 'Recompensa']],
            ['mobile_ios', 'App do aluno — iOS', 'ios', ['mobile_inicio' => 'Início', 'mobile_aulas' => 'Aulas', 'mobile_navegacao' => 'Navegação', 'mobile_recompensa' => 'Recompensa']],
        ];
        foreach (['development', 'production'] as $ambiente) {
            DB::table('monetizacao_ambientes')->insert(['ambiente' => $ambiente, 'habilitado' => false, 'created_at' => now(), 'updated_at' => now()]);
        }
        foreach ($apps as [$key, $nome, $plataforma, $posicoes]) {
            $app = DB::table('monetizacao_aplicacoes')->insertGetId(['identificador' => $key, 'nome' => $nome, 'plataforma' => $plataforma, 'created_at' => now(), 'updated_at' => now()]);
            foreach (['development', 'production'] as $ambiente) {
                $provedor = DB::table('monetizacao_provedores')->insertGetId([
                    'aplicacao_id' => $app, 'provedor' => $plataforma === 'web' ? 'adsense' : 'admob', 'ambiente' => $ambiente,
                    'configuracoes' => json_encode(['modo' => 'manual', 'gratuitos' => true, 'premium' => false, 'desktop' => true, 'mobile_web' => true, 'exigir_consentimento' => true, 'somente_maiores' => true, 'rotas_permitidas' => [], 'rotas_bloqueadas' => []]),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                foreach ($posicoes as $identificador => $titulo) {
                    DB::table('monetizacao_posicionamentos')->insert([
                        'provedor_id' => $provedor, 'identificador' => $identificador, 'nome' => $titulo,
                        'formato' => $identificador === 'mobile_navegacao' ? 'interstitial' : ($identificador === 'mobile_recompensa' ? 'rewarded' : ($identificador === 'mobile_inicio' ? 'adaptativo' : 'banner')),
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        foreach (['monetizacao_auditoria', 'monetizacao_posicionamentos', 'monetizacao_provedores', 'monetizacao_aplicacoes', 'monetizacao_ambientes'] as $tabela) {
            Schema::dropIfExists($tabela);
        }
    }
};
