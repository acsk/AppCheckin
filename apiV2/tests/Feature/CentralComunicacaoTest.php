<?php

namespace Tests\Feature;

use App\Models\Parametro;
use App\Repositories\AlunoRepository;
use App\Repositories\CheckinRepository;
use App\Repositories\MatriculaRepository;
use App\Repositories\TurmaRepository;
use App\Repositories\UsuarioRepository;
use App\Services\AppConfigService;
use App\Services\Comunicacao\ComunicacaoRelatorioService;
use App\Services\Comunicacao\ComunicacaoService;
use App\Services\Comunicacao\MobileComunicacaoService;
use App\Services\Comunicacao\PendenciaComunicacaoService;
use App\Services\Mobile\MobileCheckinService;
use App\Services\TurmaCheckinBloqueioService;
use App\Support\AcademyDateTime;
use App\Support\CheckinToleranciaAntes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\Concerns\RequiresSqlite;
use Tests\TestCase;

/**
 * Central de Comunicação: publicação, vigência, confirmação, enquetes e a trava do check-in (SQLite).
 */
class CentralComunicacaoTest extends TestCase
{
    use RequiresSqlite;

    private const T = 3;

    private const OUTRO_TENANT = 4;

    /** usuario => aluno */
    private const ANA = [10, 100];

    private const BIA = [11, 101];

    private const ADMIN = 1;

    private bool $centralAtiva = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireSqlite();
        PendenciaComunicacaoService::esquecerSchema();

        Schema::create('usuarios', function (Blueprint $t) {
            $t->increments('id');
            $t->string('nome');
            $t->string('email')->nullable();
            $t->string('telefone')->nullable();
            $t->boolean('ativo')->default(true);
        });
        Schema::create('alunos', fn (Blueprint $t) => [$t->increments('id'), $t->integer('usuario_id')]);
        Schema::create('tenant_usuario_papel', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tenant_id');
            $t->integer('usuario_id');
            $t->integer('papel_id');
            $t->boolean('ativo')->default(true);
        });
        Schema::create('planos', fn (Blueprint $t) => [$t->increments('id'), $t->integer('tenant_id'), $t->integer('modalidade_id'), $t->string('nome'), $t->boolean('ativo')->default(true)]);
        Schema::create('status_matricula', fn (Blueprint $t) => [$t->increments('id'), $t->string('codigo')]);
        Schema::create('matriculas', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tenant_id');
            $t->integer('aluno_id');
            $t->integer('plano_id');
            $t->integer('status_id');
            $t->date('data_vencimento')->nullable();
            $t->date('proxima_data_vencimento')->nullable();
        });
        Schema::create('turmas', fn (Blueprint $t) => [$t->increments('id'), $t->integer('tenant_id'), $t->integer('modalidade_id'), $t->time('horario_inicio'), $t->boolean('ativo')->default(true)]);
        Schema::create('checkins', fn (Blueprint $t) => [$t->increments('id'), $t->integer('tenant_id'), $t->integer('aluno_id'), $t->integer('turma_id'), $t->dateTime('data_checkin')]);
        Schema::create('pagamentos_plano', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tenant_id');
            $t->integer('aluno_id');
            $t->integer('status_pagamento_id');
            $t->date('data_vencimento');
            $t->date('data_pagamento')->nullable();
        });
        (require database_path('migrations/2026_10_27_120000_central_comunicacao.php'))->up();

        DB::table('status_matricula')->insert([['id' => 1, 'codigo' => 'ativa'], ['id' => 2, 'codigo' => 'vencida']]);
        DB::table('usuarios')->insert(['id' => self::ADMIN, 'nome' => 'Admin']);
        foreach ([self::ANA, self::BIA] as [$u, $a]) {
            DB::table('usuarios')->insert(['id' => $u, 'nome' => "Aluna {$u}", 'email' => "u{$u}@x.com"]);
            DB::table('alunos')->insert(['id' => $a, 'usuario_id' => $u]);
            DB::table('tenant_usuario_papel')->insert(['tenant_id' => self::T, 'usuario_id' => $u, 'papel_id' => 1]);
        }
        DB::table('planos')->insert(['id' => 7, 'tenant_id' => self::T, 'modalidade_id' => 1, 'nome' => 'Natação 2x']);
        DB::table('turmas')->insert([
            ['id' => 50, 'tenant_id' => self::T, 'modalidade_id' => 1, 'horario_inicio' => '18:00:00'],
            ['id' => 51, 'tenant_id' => self::T, 'modalidade_id' => 1, 'horario_inicio' => '07:00:00'],
        ]);

        $config = Mockery::mock(AppConfigService::class);
        $config->shouldReceive('funcionalidadeAtiva')->andReturnUsing(fn () => $this->centralAtiva);
        $this->app->instance(AppConfigService::class, $config);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ------------------------------------------------------------------ apoio

    private function admin(): ComunicacaoService
    {
        return app(ComunicacaoService::class);
    }

    private function app_(): MobileComunicacaoService
    {
        return app(MobileComunicacaoService::class);
    }

    private function pendencias(int $alunoId = self::ANA[1], int $tenant = self::T): array
    {
        return app(PendenciaComunicacaoService::class)->listarPendenciasObrigatorias($tenant, $alunoId);
    }

    private function agora(string $mod = '+0 minutes'): string
    {
        return AcademyDateTime::now()->modify($mod)->format('Y-m-d H:i');
    }

    /** Cria e publica; devolve o id. */
    private function publicar(array $dados = [], int $tenant = self::T): int
    {
        $r = $this->admin()->criarComunicacao($tenant, $dados + [
            'tipo' => 'comunicado', 'titulo' => 'Novas regras', 'conteudo' => 'Respeite os horários.',
            'obrigatoria' => true, 'termino_em' => $this->agora('+7 days'), 'publico' => ['tipo' => 'todos'], 'publicar' => true,
        ], self::ADMIN);
        $this->assertSame(201, $r['status'], json_encode($r['body'], JSON_UNESCAPED_UNICODE));

        return (int) $r['body']['comunicacao']['id'];
    }

    private function enquete(array $extra = []): int
    {
        return $this->publicar($extra + [
            'tipo' => 'enquete', 'titulo' => 'Pesquisa de satisfação', 'conteudo' => null,
            'perguntas' => [
                ['pergunta' => 'Qual horário prefere?', 'tipo_resposta' => 'unica', 'alternativas' => ['Manhã', 'Noite']],
                ['pergunta' => 'Nota para a piscina', 'tipo_resposta' => 'avaliacao'],
                ['pergunta' => 'Sugestões', 'tipo_resposta' => 'aberta', 'obrigatoria' => false],
            ],
        ]);
    }

    /** Check-in pelo mesmo serviço usado por POST /v2/mobile/checkin (repositórios simulados). */
    private function checkin(array $usuario = self::ANA, ?callable $antesDeGravar = null, ?bool &$gravou = null): array
    {
        $gravou = false;
        $usuarios = Mockery::mock(UsuarioRepository::class);
        $usuarios->shouldReceive('findById')->andReturn(['nome' => 'Ana', 'email' => 'a@x.com']);
        $alunos = Mockery::mock(AlunoRepository::class);
        $alunos->shouldReceive('findForTenant')->andReturn(['id' => $usuario[1], 'foto_caminho' => null]);
        $alunos->shouldReceive('usuarioTemAcessoTenant')->andReturn(true);
        $matriculas = Mockery::mock(MatriculaRepository::class);
        $matriculas->shouldReceive('atualizarStatusMatriculasVencidas');
        $matriculas->shouldReceive('findElegivelParaCheckin')->andReturn(['id' => 1, 'proxima_data_vencimento' => date('Y-m-d', strtotime('+30 days')), 'data_vencimento' => null]);
        $matriculas->shouldReceive('marcarPendenteSeLimiteCicloEsgotado');
        $turmas = Mockery::mock(TurmaRepository::class);
        $turmas->shouldReceive('findById')->andReturn(['id' => 50, 'nome' => 'Natação 18h', 'professor_nome' => 'Prof', 'modalidade_nome' => 'Natação', 'modalidade_id' => 1, 'limite_alunos' => 20]);
        $turmas->shouldReceive('contarAlunosInscritos')->andReturnUsing(function () use ($antesDeGravar) {
            $antesDeGravar && $antesDeGravar();

            return 0;
        });
        $checkins = Mockery::mock(CheckinRepository::class);
        $checkins->shouldReceive('usuarioTemCheckinNaTurma')->andReturn(false);
        $checkins->shouldReceive('usuarioTemCheckinNoDiaNaModalidade')->andReturn(['total' => 0, 'ultimo_checkin_id' => null]);
        $checkins->shouldReceive('obterLimiteCheckinsPlano')->andReturn(['tem_plano' => true, 'limite' => 0, 'permite_reposicao' => false]);
        $checkins->shouldReceive('createEmTurma')->andReturnUsing(function () use (&$gravou) {
            $gravou = true;

            return 999;
        });
        $bloqueios = Mockery::mock(TurmaCheckinBloqueioService::class);
        $bloqueios->shouldReceive('isBloqueada')->andReturn(false);

        $svc = new MobileCheckinService($usuarios, $alunos, $matriculas, $turmas, $checkins, $bloqueios,
            new CheckinToleranciaAntes(Mockery::mock(Parametro::class)), app(PendenciaComunicacaoService::class));

        return $svc->registrar($usuario[0], self::T, ['turma_id' => 50], $usuario[1]);
    }

    private function respostasCompletas(int $id): array
    {
        $perguntas = $this->admin()->perguntas(self::T, $id);

        return [
            ['pergunta_id' => $perguntas[0]['id'], 'alternativas' => [$perguntas[0]['alternativas'][1]['id']]],
            ['pergunta_id' => $perguntas[1]['id'], 'valor' => 4],
            ['pergunta_id' => $perguntas[2]['id'], 'valor' => 'Mais raias à noite'],
        ];
    }

    // ------------------------------------------------------------------ cenários obrigatórios

    public function test_01_aluno_sem_pendencias_faz_checkin(): void
    {
        $r = $this->checkin(gravou: $gravou);

        $this->assertSame(201, $r['status'], json_encode($r['body'], JSON_UNESCAPED_UNICODE));
        $this->assertTrue($gravou);
    }

    public function test_02_comunicado_obrigatorio_nao_confirmado_bloqueia_com_409(): void
    {
        $id = $this->publicar();

        $r = $this->checkin(gravou: $gravou);

        $this->assertSame(409, $r['status']);
        $this->assertFalse($gravou);
        $this->assertSame('COMUNICACOES_OBRIGATORIAS_PENDENTES', $r['body']['codigo']);
        $this->assertFalse($r['body']['checkin_permitido']);
        $this->assertSame(1, $r['body']['total_pendencias']);
        $this->assertSame(['id' => $id, 'tipo' => 'comunicado', 'titulo' => 'Novas regras', 'acao_exigida' => 'confirmar_leitura'], $r['body']['pendencias'][0]);
    }

    public function test_03_confirmar_leitura_libera_o_checkin_na_hora(): void
    {
        $id = $this->publicar();

        $c = $this->app_()->confirmarLeitura(self::ANA[0], self::T, $id);

        $this->assertSame(200, $c['status']);
        $this->assertTrue($c['body']['data']['checkin_permitido']);
        $this->assertSame(201, $this->checkin()['status']);
    }

    public function test_04_enquete_obrigatoria_so_libera_com_envio_completo(): void
    {
        $id = $this->enquete();
        $perguntas = $this->admin()->perguntas(self::T, $id);

        $this->assertSame('responder', $this->pendencias()[0]['acao_exigida']);
        $parcial = $this->app_()->responderEnquete(self::ANA[0], self::T, $id, [
            ['pergunta_id' => $perguntas[0]['id'], 'alternativas' => [$perguntas[0]['alternativas'][0]['id']]],
        ]);
        $this->assertSame(422, $parcial['status'], 'falta a nota obrigatória');
        $this->assertSame(0, DB::table('enquete_respostas')->count(), 'envio parcial não grava nada');
        $this->assertSame(409, $this->checkin()['status']);

        $this->app_()->detalhe(self::ANA[0], self::T, $id); // abrir não conclui
        $this->assertSame(409, $this->checkin()['status']);

        $ok = $this->app_()->responderEnquete(self::ANA[0], self::T, $id, $this->respostasCompletas($id));
        $this->assertSame(200, $ok['status'], json_encode($ok['body'], JSON_UNESCAPED_UNICODE));
        $this->assertSame(201, $this->checkin()['status']);
    }

    public function test_05_comunicacao_opcional_nao_bloqueia(): void
    {
        $this->publicar(['obrigatoria' => false]);

        $this->assertSame(201, $this->checkin()['status']);
    }

    public function test_06_comunicacao_expirada_nao_bloqueia(): void
    {
        $id = $this->publicar();
        DB::table('comunicacoes')->where('id', $id)->update(['termino_em' => AcademyDateTime::now()->modify('-1 minute')->format('Y-m-d H:i:s')]);

        $this->assertSame(201, $this->checkin()['status']);
    }

    public function test_07_comunicacao_agendada_nao_bloqueia_ate_comecar(): void
    {
        $id = $this->publicar(['inicio_em' => $this->agora('+1 day'), 'termino_em' => $this->agora('+8 days')]);

        $this->assertSame('agendada', $this->admin()->obter(self::T, $id)['body']['comunicacao']['status']);
        $this->assertSame(201, $this->checkin()['status']);
        $this->assertSame([], $this->app_()->listar(self::ANA[0], self::T)['body']['data']['comunicacoes'], 'antes do início não aparece');

        DB::table('comunicacoes')->where('id', $id)->update(['inicio_em' => AcademyDateTime::now()->modify('-1 minute')->format('Y-m-d H:i:s')]);
        $this->assertSame(409, $this->checkin()['status']);
    }

    public function test_08_comunicacao_para_outra_turma_nao_bloqueia(): void
    {
        $ontem = AcademyDateTime::now()->modify('-1 day')->format('Y-m-d 18:05:00');
        DB::table('checkins')->insert(['tenant_id' => self::T, 'aluno_id' => self::BIA[1], 'turma_id' => 50, 'data_checkin' => $ontem]);
        $this->publicar(['publico' => ['tipo' => 'segmento', 'horarios' => [['modalidade_id' => 1, 'horario' => '18:00']]]]);

        $this->assertSame(201, $this->checkin(self::ANA)['status'], 'Ana não frequenta a turma das 18h');
        $this->assertSame(409, $this->checkin(self::BIA)['status']);
    }

    public function test_09_duas_pendencias_exigem_duas_conclusoes(): void
    {
        $a = $this->publicar(['titulo' => 'Regra A']);
        $b = $this->enquete();

        $r = $this->checkin();
        $this->assertSame(2, $r['body']['total_pendencias']);

        $this->app_()->confirmarLeitura(self::ANA[0], self::T, $a);
        $this->assertSame([$b], array_column($this->checkin()['body']['pendencias'], 'id'));

        $this->app_()->responderEnquete(self::ANA[0], self::T, $b, $this->respostasCompletas($b));
        $this->assertSame(201, $this->checkin()['status']);
    }

    public function test_10_confirmacao_duplicada_e_idempotente(): void
    {
        $id = $this->publicar();

        $primeira = $this->app_()->confirmarLeitura(self::ANA[0], self::T, $id);
        $confirmadaEm = DB::table('comunicacao_destinatarios')->where('aluno_id', self::ANA[1])->value('confirmada_em');
        $segunda = $this->app_()->confirmarLeitura(self::ANA[0], self::T, $id);

        $this->assertFalse($primeira['body']['data']['ja_confirmada']);
        $this->assertTrue($segunda['body']['data']['ja_confirmada']);
        $this->assertSame(1, DB::table('comunicacao_destinatarios')->where('comunicacao_id', $id)->where('aluno_id', self::ANA[1])->count());
        $this->assertSame($confirmadaEm, DB::table('comunicacao_destinatarios')->where('aluno_id', self::ANA[1])->value('confirmada_em'));
        $this->assertSame(1, DB::table('comunicacao_auditoria')->where('acao', 'leitura_confirmada')->count());

        $e = $this->enquete();
        $this->app_()->responderEnquete(self::ANA[0], self::T, $e, $this->respostasCompletas($e));
        $denovo = $this->app_()->responderEnquete(self::ANA[0], self::T, $e, $this->respostasCompletas($e));
        $this->assertTrue($denovo['body']['data']['ja_respondida']);
        $this->assertSame(3, DB::table('enquete_respostas')->where('comunicacao_id', $e)->count(), 'sem respostas duplicadas');
    }

    public function test_11_um_tenant_nao_acessa_dados_de_outro(): void
    {
        DB::table('usuarios')->insert(['id' => 99, 'nome' => 'De fora']);
        DB::table('alunos')->insert(['id' => 199, 'usuario_id' => 99]);
        DB::table('tenant_usuario_papel')->insert(['tenant_id' => self::OUTRO_TENANT, 'usuario_id' => 99, 'papel_id' => 1]);
        $deFora = $this->publicar([], self::OUTRO_TENANT);

        $this->assertSame([], $this->pendencias(), 'publicação do outro tenant não alcança alunos daqui');
        $this->assertSame(0, DB::table('comunicacao_destinatarios')->where('comunicacao_id', $deFora)->whereIn('aluno_id', [self::ANA[1], self::BIA[1]])->count());
        $this->assertSame(404, $this->admin()->obter(self::T, $deFora)['status']);
        $this->assertSame(404, $this->admin()->encerrarComunicacao(self::T, $deFora, self::ADMIN)['status']);
        $this->assertSame(404, $this->app_()->confirmarLeitura(self::ANA[0], self::T, $deFora)['status']);
        $this->assertSame(404, app(ComunicacaoRelatorioService::class)->destinatarios(self::T, $deFora)['status']);

        // Alunos de outro tenant escolhidos à mão são ignorados
        $id = $this->publicar(['publico' => ['tipo' => 'alunos', 'alunos' => [self::ANA[1], 555]]]);
        $this->assertSame([self::ANA[1]], DB::table('comunicacao_destinatarios')->where('comunicacao_id', $id)->pluck('aluno_id')->map(fn ($v) => (int) $v)->all());
    }

    public function test_12_chamada_direta_a_api_nao_contorna_a_trava(): void
    {
        $this->publicar();

        // O app nem consultou /comunicacoes/resumo: a API recusa do mesmo jeito
        $this->assertSame(409, $this->checkin()['status']);
        // Check-in registrado pela equipe também respeita a pendência
        $prof = app(PendenciaComunicacaoService::class)->verificarPermissaoCheckin(self::T, self::ANA[1], self::ANA[0], registradoPelaEquipe: true);
        $this->assertSame(409, $prof['status']);
        $this->assertStringContainsString('O aluno tem 1 comunicação obrigatória pendente', $prof['body']['mensagem']);
    }

    public function test_13_encerramento_antecipado_libera_o_checkin(): void
    {
        $id = $this->publicar();
        $this->assertSame(409, $this->checkin()['status']);

        $r = $this->admin()->encerrarComunicacao(self::T, $id, self::ADMIN);

        $this->assertSame('encerrada', $r['body']['comunicacao']['status']);
        $this->assertSame(201, $this->checkin()['status']);
        $this->assertSame(1, DB::table('comunicacao_auditoria')->where('comunicacao_id', $id)->where('acao', 'encerrada')->count());
    }

    public function test_14_confirmacao_vale_depois_de_novo_login(): void
    {
        $id = $this->publicar();
        $this->app_()->confirmarLeitura(self::ANA[0], self::T, $id);

        // Nova sessão: serviços novos, nada em memória; a confirmação está no banco
        $config = Mockery::mock(AppConfigService::class);
        $config->shouldReceive('funcionalidadeAtiva')->andReturn(true);
        $novaSessao = new PendenciaComunicacaoService($config);

        $this->assertSame([], $novaSessao->listarPendenciasObrigatorias(self::T, self::ANA[1]));
        $this->assertNull($novaSessao->verificarPermissaoCheckin(self::T, self::ANA[1], self::ANA[0]));
    }

    public function test_15_publicacao_entre_a_verificacao_e_a_gravacao_nao_passa(): void
    {
        // Pendência criada depois da primeira verificação: a conferência dentro da transação do INSERT barra
        $r = $this->checkin(self::ANA, fn () => $this->publicar(['titulo' => 'Publicada no meio']), $gravou);

        $this->assertSame(409, $r['status']);
        $this->assertFalse($gravou);
    }

    public function test_16_aluno_nao_confirma_nem_responde_por_outro(): void
    {
        $id = $this->publicar(['publico' => ['tipo' => 'alunos', 'alunos' => [self::ANA[1]]]]);
        $e = $this->enquete(['publico' => ['tipo' => 'alunos', 'alunos' => [self::ANA[1]]]]);

        $this->assertSame(404, $this->app_()->confirmarLeitura(self::BIA[0], self::T, $id)['status']);
        $this->assertSame(404, $this->app_()->responderEnquete(self::BIA[0], self::T, $e, $this->respostasCompletas($e))['status']);
        $this->assertSame(404, $this->app_()->detalhe(self::BIA[0], self::T, $id)['status']);
        $this->assertCount(2, $this->pendencias(self::ANA[1]), 'pendências da Ana seguem abertas');
        $this->assertSame(0, DB::table('enquete_respostas')->count());
    }

    // ------------------------------------------------------------------ regras complementares

    public function test_enquete_anonima_nao_identifica_respostas(): void
    {
        $id = $this->enquete(['configuracoes' => ['enquete' => ['anonima' => true, 'permitir_alterar' => true]]]);
        $this->app_()->responderEnquete(self::ANA[0], self::T, $id, $this->respostasCompletas($id));

        $this->assertSame(0, DB::table('enquete_respostas')->whereNotNull('aluno_id')->count());
        $this->assertSame(0, DB::table('enquete_respostas')->whereNotNull('respondida_em')->count());
        $this->assertFalse($this->admin()->obter(self::T, $id)['body']['comunicacao']['configuracoes']['enquete']['permitir_alterar']);
        $rel = app(ComunicacaoRelatorioService::class);
        $resultados = $rel->resultadosEnquete(self::T, $id, true)['body'];
        $this->assertSame(1, $resultados['participantes']);
        $this->assertSame([['texto' => 'Mais raias à noite']], $resultados['perguntas'][2]['textos'], 'texto sem nome');
        $this->assertSame(403, $rel->respostasIndividuais(self::T, $id)['status']);
    }

    public function test_resultados_e_indicadores(): void
    {
        $id = $this->enquete(['obrigatoria' => false]);
        $c = $this->publicar();
        $this->app_()->responderEnquete(self::ANA[0], self::T, $id, $this->respostasCompletas($id));
        $this->app_()->confirmarLeitura(self::BIA[0], self::T, $c);

        $res = app(ComunicacaoRelatorioService::class)->resultadosEnquete(self::T, $id, true)['body'];
        $this->assertSame([0, 1], array_column($res['perguntas'][0]['opcoes'], 'total'));
        $this->assertSame(4.0, $res['perguntas'][1]['media']);
        $this->assertSame('Aluna 10', $res['perguntas'][2]['textos'][0]['aluno']);

        $ind = app(ComunicacaoRelatorioService::class)->indicadores(self::T)['body'];
        $this->assertSame(2, $ind['publicadas']);
        $this->assertSame(2, $ind['alunos_alcancados']);
        $this->assertSame(50.0, $ind['taxa_confirmacao']);
        $this->assertSame(50.0, $ind['taxa_participacao']);
        $this->assertSame(1, $ind['pendencias_obrigatorias'], 'Ana ainda não confirmou o comunicado');

        $pend = app(ComunicacaoRelatorioService::class)->destinatarios(self::T, $c, 'pendentes')['body']['destinatarios'];
        $this->assertSame([self::ANA[1]], array_column($pend, 'aluno_id'));
        $this->assertSame(['id' => $c, 'leituras' => 1, 'pendentes' => 1], array_intersect_key($this->admin()->obter(self::T, $c)['body']['comunicacao'], ['id' => 1, 'leituras' => 1, 'pendentes' => 1]));
    }

    public function test_central_desligada_nao_trava_e_validacoes_de_cadastro(): void
    {
        $this->publicar();
        $this->centralAtiva = false;
        $this->assertSame(201, $this->checkin()['status'], 'sem a Central o aluno não teria como resolver');

        $sem = $this->admin()->criarComunicacao(self::T, ['tipo' => 'comunicado', 'titulo' => 'X', 'conteudo' => 'Y', 'obrigatoria' => true], self::ADMIN);
        $this->assertSame(422, $sem['status'], 'obrigatória precisa de término');
        $vazia = $this->admin()->criarComunicacao(self::T, ['tipo' => 'enquete', 'titulo' => 'X', 'perguntas' => []], self::ADMIN);
        $this->assertSame(422, $vazia['status']);
        $ninguem = $this->admin()->criarComunicacao(self::T, ['tipo' => 'comunicado', 'titulo' => 'X', 'conteudo' => 'Y', 'publico' => ['tipo' => 'segmento', 'inadimplentes' => true], 'publicar' => true], self::ADMIN);
        $this->assertSame(422, $ninguem['status'], 'ninguém inadimplente');
        $this->assertTrue($ninguem['body']['salva_como_rascunho']);
    }

    public function test_publicada_trava_publico_e_inclusao_de_novos_alunos(): void
    {
        DB::table('matriculas')->insert(['tenant_id' => self::T, 'aluno_id' => self::ANA[1], 'plano_id' => 7, 'status_id' => 1, 'data_vencimento' => date('Y-m-d', strtotime('+10 days'))]);
        $id = $this->publicar(['publico' => ['tipo' => 'ativos'], 'obrigatoria' => false, 'termino_em' => null]);
        $this->assertSame(1, DB::table('comunicacao_destinatarios')->where('comunicacao_id', $id)->count(), 'só a Ana tem matrícula ativa');

        DB::table('matriculas')->insert(['tenant_id' => self::T, 'aluno_id' => self::BIA[1], 'plano_id' => 7, 'status_id' => 1, 'data_vencimento' => date('Y-m-d', strtotime('+10 days'))]);
        $upd = $this->admin()->atualizarComunicacao(self::T, $id, ['titulo' => 'Novo título', 'publico' => ['tipo' => 'todos'], 'tipo' => 'notificacao'], self::ADMIN);
        $this->assertSame(200, $upd['status']);
        $this->assertSame('Novo título', $upd['body']['comunicacao']['titulo']);
        $this->assertSame(['tipo' => 'ativos'], $upd['body']['comunicacao']['publico'], 'público fixo após publicar');
        $this->assertSame('comunicado', $upd['body']['comunicacao']['tipo']);

        $inc = $this->admin()->incluirNovosDestinatarios(self::T, $id, self::ADMIN);
        $this->assertSame(1, $inc['body']['novos']);
        $this->assertSame(0, $this->admin()->incluirNovosDestinatarios(self::T, $id, self::ADMIN)['body']['novos']);
    }
}
