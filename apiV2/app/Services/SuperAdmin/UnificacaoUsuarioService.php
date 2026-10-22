<?php

namespace App\Services\SuperAdmin;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Unifica dois cadastros da mesma pessoa (ex.: admin e professor criados com e-mails diferentes).
 * Tudo do "origem" passa para o "destino"; o origem é desativado e anonimizado (não apagado).
 *
 * Bloqueia quando os dois têm histórico de aluno (matrículas, check-ins, pagamentos...): juntar dois
 * históricos de aluno é decisão manual. Ficha de aluno vazia é descartada; ficha de professor tem
 * turmas e academias movidas para a do destino.
 */
class UnificacaoUsuarioService
{
    /** Colunas que guardam id de usuário (referências simples: só trocar o valor) */
    private const COLUNAS = [
        'acessos_suporte.usuario_id', 'assinaturas_mercadopago.cancelado_por', 'checkins.admin_id',
        'cnpj_excecoes.autorizado_por', 'cnpj_excecoes.revogado_por', 'configuracoes_sistema.atualizado_por',
        'contas_receber.baixa_por', 'contas_receber.criado_por', 'contas_receber.usuario_id', 'creditos_aluno.criado_por',
        'email_logs.usuario_id', 'entidade_juridica_auditoria.usuario_id', 'entidades_juridicas.atualizado_por',
        'historico_planos.criado_por', 'historico_planos.usuario_id', 'inscricoes_turmas.usuario_id',
        'matricula_descontos.autorizado_por', 'matricula_descontos.criado_por', 'matriculas.cancelado_por', 'matriculas.criado_por',
        'notificacoes.usuario_id', 'pacote_contratos.pagante_usuario_id', 'pagamentos_mercadopago.usuario_id',
        'pagamentos_plano.baixado_por', 'pagamentos_plano.criado_por', 'parametros_tenant.atualizado_por',
        'prospeccao_agendamentos.criado_por', 'prospeccao_agendamentos.finalizado_por', 'prospeccao_agendamentos.responsavel_id',
        'prospeccao_historico_status.usuario_id', 'prospeccao_interacoes.usuario_id', 'prospeccao_modelos_mensagem.atualizado_por',
        'prospeccao_modelos_mensagem.criado_por', 'prospeccao_oportunidades.responsavel_id', 'prospeccao_oportunidades.ultimo_contato_por',
        'prospeccao_restricoes_contato.registrado_por', 'prospeccao_restricoes_contato.removido_por',
        'tenant_usuario_permissoes.concedido_por', 'tenants.admin_principal_id', 'wod_resultados.usuario_id', 'wods.criado_por',
    ];

    /** Tabelas que dão histórico a uma ficha de aluno */
    private const HISTORICO_ALUNO = [
        'assinaturas', 'assinaturas_mercadopago', 'checkins', 'creditos_aluno', 'matriculas', 'pacote_beneficiarios',
        'pagamentos_mercadopago', 'pagamentos_plano', 'prospeccao_oportunidades', 'recordes', 'recordes_pessoais',
    ];

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function previa(int $origemId, int $destinoId): array
    {
        $erro = $this->validar($origemId, $destinoId);
        if ($erro) {
            return $erro;
        }

        $mover = [];
        foreach ($this->colunasExistentes() as [$tabela, $coluna]) {
            $n = DB::table($tabela)->where($coluna, $origemId)->count();
            if ($n > 0) {
                $mover["{$tabela}.{$coluna}"] = $n;
            }
        }
        $papeis = DB::table('tenant_usuario_papel as tup')->join('tenants as t', 't.id', '=', 'tup.tenant_id')
            ->where('tup.usuario_id', $origemId)->where('tup.ativo', 1)
            ->get(['t.nome as academia', 'tup.papel_id'])->map(fn ($r) => (array) $r)->all();
        [$alunoAcao, $bloqueio] = $this->planoAluno($origemId, $destinoId);

        return ['status' => 200, 'body' => [
            'origem' => $this->resumo($origemId),
            'destino' => $this->resumo($destinoId),
            'papeis_movidos' => $papeis,
            'registros_movidos' => $mover,
            'ficha_aluno' => $alunoAcao,
            'ficha_professor' => $this->planoProfessor($origemId, $destinoId),
            'pode_unificar' => $bloqueio === null,
            'bloqueio' => $bloqueio,
        ]];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function unificar(int $origemId, int $destinoId, ?int $porUsuarioId): array
    {
        $previa = $this->previa($origemId, $destinoId);
        if ($previa['status'] !== 200) {
            return $previa;
        }
        if (! $previa['body']['pode_unificar']) {
            return ['status' => 422, 'body' => ['error' => $previa['body']['bloqueio'], 'code' => 'UNIFICACAO_BLOQUEADA']];
        }

        DB::transaction(function () use ($origemId, $destinoId) {
            // Papéis e permissões por academia: mescla (chaves únicas por tenant/usuário)
            foreach (DB::table('tenant_usuario_papel')->where('usuario_id', $origemId)->get() as $r) {
                $existe = DB::table('tenant_usuario_papel')->where('tenant_id', $r->tenant_id)->where('usuario_id', $destinoId)->where('papel_id', $r->papel_id)->first();
                if ($existe) {
                    if ((int) $r->ativo === 1) {
                        DB::table('tenant_usuario_papel')->where('id', $existe->id)->update(['ativo' => 1]);
                    }
                    DB::table('tenant_usuario_papel')->where('id', $r->id)->delete();
                } else {
                    DB::table('tenant_usuario_papel')->where('id', $r->id)->update(['usuario_id' => $destinoId]);
                }
            }
            if (Schema::hasTable('tenant_usuario_permissoes')) {
                foreach (DB::table('tenant_usuario_permissoes')->where('usuario_id', $origemId)->get() as $r) {
                    $existe = DB::table('tenant_usuario_permissoes')->where('tenant_id', $r->tenant_id)->where('usuario_id', $destinoId)->where('permissao', $r->permissao)->exists();
                    $existe
                        ? DB::table('tenant_usuario_permissoes')->where('id', $r->id)->delete()
                        : DB::table('tenant_usuario_permissoes')->where('id', $r->id)->update(['usuario_id' => $destinoId]);
                }
            }

            $this->unificarAluno($origemId, $destinoId);
            $this->unificarProfessor($origemId, $destinoId);

            foreach ($this->colunasExistentes() as [$tabela, $coluna]) {
                DB::table($tabela)->where($coluna, $origemId)->update([$coluna => $destinoId]);
            }

            // Origem fica desativado e anonimizado: libera e-mail e CPF e não entra mais
            DB::table('usuarios')->where('id', $origemId)->update([
                'nome' => "(unificado em #{$destinoId})",
                'email' => "unificado-{$origemId}@removido.invalid",
                'email_global' => null,
                'cpf' => null,
                'ativo' => 0,
                'senha_hash' => password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT),
                'updated_at' => now(),
            ]);
        });

        Log::warning('usuario.unificado', ['origem' => $origemId, 'destino' => $destinoId, 'por' => $porUsuarioId, 'resumo' => $previa['body']['registros_movidos']]);

        return ['status' => 200, 'body' => ['message' => "Cadastro #{$origemId} unificado em #{$destinoId}.", 'destino' => $this->resumo($destinoId)]];
    }

    /** Id do usuário a partir de id, CPF (11 dígitos) ou e-mail; 0 quando não encontra. */
    public function resolver(string $chave): int
    {
        $chave = trim($chave);
        if (str_contains($chave, '@')) {
            $email = mb_strtolower($chave, 'UTF-8');

            return (int) DB::table('usuarios')->where('email_global', $email)->orWhere('email', $email)->value('id');
        }
        $digitos = preg_replace('/\D/', '', $chave);
        if (strlen($digitos) === 11) {
            return (int) DB::table('usuarios')->where('cpf', $digitos)->value('id');
        }

        return (int) $digitos;
    }

    /** @return array{status: int, body: array<string, mixed>}|null */
    private function validar(int $origemId, int $destinoId): ?array
    {
        foreach (['origem' => $origemId, 'destino' => $destinoId] as $lado => $id) {
            if ($id <= 0 || ! DB::table('usuarios')->where('id', $id)->exists()) {
                return ['status' => 404, 'body' => ['error' => "Cadastro de {$lado} não encontrado. Use o id, o CPF ou o e-mail."]];
            }
        }
        if ($origemId === $destinoId) {
            return ['status' => 422, 'body' => ['error' => 'Escolha dois cadastros diferentes.']];
        }
        $superAdmins = DB::table('tenant_usuario_papel')->where('papel_id', 4)->pluck('usuario_id')->map(fn ($i) => (int) $i)->all();
        if (in_array($origemId, $superAdmins, true)) {
            return ['status' => 422, 'body' => ['error' => 'O cadastro de super admin não pode ser o que sai.']];
        }

        return null;
    }

    /**
     * @return array{0: string, 1: ?string}  [ação, motivo do bloqueio]
     */
    private function planoAluno(int $origemId, int $destinoId): array
    {
        $o = DB::table('alunos')->where('usuario_id', $origemId)->value('id');
        $d = DB::table('alunos')->where('usuario_id', $destinoId)->value('id');
        if (! $o) {
            return ['nenhuma', null];
        }
        if (! $d) {
            return ['mover', null];
        }
        $histO = $this->temHistoricoAluno((int) $o);
        $histD = $this->temHistoricoAluno((int) $d);
        if ($histO && $histD) {
            return ['bloqueada', 'Os dois cadastros têm histórico de aluno (matrículas, check-ins ou pagamentos). Junte esses históricos manualmente antes de unificar.'];
        }

        return [$histO ? 'substituir_ficha_vazia_do_destino' : 'descartar_ficha_vazia', null];
    }

    private function unificarAluno(int $origemId, int $destinoId): void
    {
        $o = DB::table('alunos')->where('usuario_id', $origemId)->value('id');
        $d = DB::table('alunos')->where('usuario_id', $destinoId)->value('id');
        if (! $o) {
            return;
        }
        if (! $d) {
            DB::table('alunos')->where('id', $o)->update(['usuario_id' => $destinoId]);

            return;
        }
        if ($this->temHistoricoAluno((int) $o)) {
            DB::table('alunos')->where('id', $d)->delete();
            DB::table('alunos')->where('id', $o)->update(['usuario_id' => $destinoId]);
        } else {
            DB::table('alunos')->where('id', $o)->delete();
        }
    }

    private function planoProfessor(int $origemId, int $destinoId): string
    {
        $o = DB::table('professores')->where('usuario_id', $origemId)->value('id');
        if (! $o) {
            return 'nenhuma';
        }

        return DB::table('professores')->where('usuario_id', $destinoId)->exists() ? 'mesclar_turmas_e_academias' : 'mover';
    }

    private function unificarProfessor(int $origemId, int $destinoId): void
    {
        $o = DB::table('professores')->where('usuario_id', $origemId)->value('id');
        if (! $o) {
            return;
        }
        $d = DB::table('professores')->where('usuario_id', $destinoId)->value('id');
        if (! $d) {
            DB::table('professores')->where('id', $o)->update(['usuario_id' => $destinoId]);

            return;
        }
        DB::table('turmas')->where('professor_id', $o)->update(['professor_id' => $d]);
        foreach (DB::table('tenant_professor')->where('professor_id', $o)->get() as $r) {
            $existe = DB::table('tenant_professor')->where('professor_id', $d)->where('tenant_id', $r->tenant_id)->exists();
            $existe
                ? DB::table('tenant_professor')->where('id', $r->id)->delete()
                : DB::table('tenant_professor')->where('id', $r->id)->update(['professor_id' => $d]);
        }
        DB::table('professores')->where('id', $o)->delete();
    }

    private function temHistoricoAluno(int $alunoId): bool
    {
        foreach (self::HISTORICO_ALUNO as $tabela) {
            if (Schema::hasTable($tabela) && Schema::hasColumn($tabela, 'aluno_id') && DB::table($tabela)->where('aluno_id', $alunoId)->exists()) {
                return true;
            }
        }

        return false;
    }

    /** @return list<array{0: string, 1: string}> */
    private function colunasExistentes(): array
    {
        $out = [];
        foreach (self::COLUNAS as $c) {
            [$t, $col] = explode('.', $c);
            if (Schema::hasTable($t) && Schema::hasColumn($t, $col)) {
                $out[] = [$t, $col];
            }
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function resumo(int $id): array
    {
        $u = DB::table('usuarios')->where('id', $id)->first(['id', 'nome', 'email', 'cpf', 'ativo']);
        $academias = DB::table('tenant_usuario_papel as tup')->join('tenants as t', 't.id', '=', 'tup.tenant_id')
            ->where('tup.usuario_id', $id)->where('tup.ativo', 1)->groupBy('t.id', 't.nome')
            ->selectRaw('t.nome, GROUP_CONCAT(tup.papel_id) as papeis')->get()->map(fn ($r) => (array) $r)->all();

        return ['id' => (int) $u->id, 'nome' => $u->nome, 'email' => $u->email, 'cpf' => $u->cpf, 'ativo' => (bool) $u->ativo, 'academias' => $academias];
    }
}
