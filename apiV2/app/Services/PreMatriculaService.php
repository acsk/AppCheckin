<?php

namespace App\Services;

use App\Services\Admin\AdminMatriculaService;
use App\Support\AcademyDateTime;
use App\Support\Papel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PreMatriculaService
{
    private const PUBLICOS = ['sem_matricula', 'com_matricula', 'ambos'];

    /** @return array{configuracao: array<string, mixed>, solicitacoes: list<array<string, mixed>>} */
    public function painel(int $tenantId): array
    {
        $this->exigirTabelas();
        abort_unless(DB::table('tenants')->where('id', $tenantId)->exists(), 404, 'Academia não encontrada.');

        return [
            'configuracao' => $this->configuracao($tenantId),
            'solicitacoes' => $this->solicitacoes($tenantId),
        ];
    }

    /** @return array{solicitacoes: list<array<string, mixed>>, planos: list<array<string, mixed>>, turmas: list<array<string, mixed>>} */
    public function painelAcademia(int $tenantId): array
    {
        $this->exigirTabelas();
        abort_unless(DB::table('tenants')->where('id', $tenantId)->exists(), 404, 'Academia não encontrada.');

        return [
            'solicitacoes' => $this->solicitacoes($tenantId),
            'planos' => $this->planosDisponiveis($tenantId),
            'turmas' => $this->turmasDisponiveis($tenantId),
        ];
    }

    /** @param array<string, mixed> $dados
     *  @return array<string, mixed>
     */
    public function salvarConfiguracao(array $dados, ?int $userId): array
    {
        $this->exigirTabelas();
        $validados = Validator::make($dados, [
            'tenant_id' => 'required|integer|min:1|exists:tenants,id',
            'ativa' => 'required|boolean',
            'obrigatoria' => 'required|boolean',
            'publico' => 'required|string|in:'.implode(',', self::PUBLICOS),
        ], [
            'tenant_id.required' => 'Selecione uma academia.',
            'tenant_id.exists' => 'A academia selecionada não foi encontrada.',
            'ativa.required' => 'Informe se a pré-matrícula está ativa.',
            'obrigatoria.required' => 'Informe se o envio é obrigatório.',
            'publico.required' => 'Selecione quais alunos podem enviar a pré-matrícula.',
            'publico.in' => 'O público selecionado é inválido.',
        ])->validate();

        $tenantId = (int) $validados['tenant_id'];
        if ($validados['ativa']) {
            $ofertas = $this->planosDisponiveis($tenantId);
            if (! collect($ofertas)->contains(
                fn (array $plano) => $plano['dias_disponiveis'] >= $plano['checkins_semanais'],
            )) {
                throw ValidationException::withMessages(['ativa' => 'Cadastre planos de valor zero com aulas semanais e turmas fixas disponíveis antes de ativar a pré-matrícula.']);
            }
        }

        $linha = [
            'ativa' => (bool) $validados['ativa'],
            'obrigatoria' => (bool) $validados['obrigatoria'],
            'publico' => $validados['publico'],
            'atualizada_por' => $userId,
            'updated_at' => now(),
        ];
        DB::transaction(function () use ($tenantId, $linha) {
            $existente = DB::table('pre_matricula_configuracoes')->where('tenant_id', $tenantId)->lockForUpdate()->first();
            if ($existente) {
                DB::table('pre_matricula_configuracoes')->where('tenant_id', $tenantId)->update($linha);

                return;
            }
            DB::table('pre_matricula_configuracoes')->insert([
                'tenant_id' => $tenantId,
                ...$linha,
                'created_at' => now(),
            ]);
        });

        return $this->configuracao($tenantId);
    }

    /** @return array<string, mixed> */
    public function excluirConfiguracao(int $tenantId): array
    {
        $this->exigirTabelas();
        abort_unless(DB::table('tenants')->where('id', $tenantId)->exists(), 404, 'Academia não encontrada.');
        DB::table('pre_matricula_configuracoes')->where('tenant_id', $tenantId)->delete();

        return $this->configuracao($tenantId);
    }

    /** @return array<string, mixed>|null */
    public function paraAluno(int $tenantId, int $userId): ?array
    {
        // Sem as tabelas (migration pendente) o recurso simplesmente não é oferecido ao aluno.
        if (! $this->tabelasExistem()) {
            return null;
        }

        $config = DB::table('pre_matricula_configuracoes')->where('tenant_id', $tenantId)->where('ativa', 1)->first();
        if (! $config) {
            return null;
        }

        if (! $this->alunoDaAcademia($tenantId, $userId)) {
            return $this->indisponivel(
                $tenantId,
                (bool) $config->obrigatoria,
                'Esta conta não está vinculada como aluno ativo nesta academia. Peça à academia para conferir seu cadastro.',
            );
        }

        $ultima = DB::table('pre_matriculas')->where('tenant_id', $tenantId)->where('usuario_id', $userId)
            ->orderByDesc('id')->first();
        if ($ultima && in_array($ultima->status, ['pendente', 'aprovada'], true)) {
            return [
                'tenant_id' => $tenantId,
                'disponivel' => true,
                'obrigatoria' => (bool) $config->obrigatoria,
                'status' => (string) $ultima->status,
                'solicitacao_id' => (int) $ultima->id,
                'observacao' => $ultima->observacao,
                'matricula_id' => $this->matriculaIdDe($ultima),
                'planos' => [],
                'turmas' => [],
            ];
        }

        $temMatricula = $this->temMatriculaAtiva($tenantId, $userId);
        if (! $this->publicoAlvo((string) $config->publico, $temMatricula)) {
            $motivo = $config->publico === 'com_matricula'
                ? 'Esta pré-matrícula é destinada a alunos com matrícula ativa nesta academia.'
                : 'Esta pré-matrícula é destinada a alunos sem matrícula ativa nesta academia.';

            return $this->indisponivel($tenantId, (bool) $config->obrigatoria, $motivo);
        }

        return [
            'tenant_id' => $tenantId,
            'disponivel' => true,
            'obrigatoria' => (bool) $config->obrigatoria,
            'status' => $ultima?->status === 'reprovada' ? 'reprovada' : null,
            'solicitacao_id' => $ultima ? (int) $ultima->id : null,
            'observacao' => $ultima?->observacao,
            'planos' => $this->planosDisponiveis($tenantId),
            'turmas' => $this->turmasDisponiveis($tenantId),
        ];
    }

    /** @return array<string, mixed> */
    private function indisponivel(int $tenantId, bool $obrigatoria, string $motivo): array
    {
        return [
            'tenant_id' => $tenantId,
            'disponivel' => false,
            'obrigatoria' => $obrigatoria,
            'status' => null,
            'solicitacao_id' => null,
            'observacao' => null,
            'motivo' => $motivo,
            'planos' => [],
            'turmas' => [],
        ];
    }

    /** @param array<string, mixed> $dados
     *  @return array<string, mixed>
     */
    public function enviar(int $tenantId, int $userId, array $dados): array
    {
        $this->exigirTabelas();
        $validados = Validator::make($dados, [
            'plano_id' => 'required|integer|min:1',
            'grade' => 'required|array|min:1|max:7',
            'grade.*.turma_fixa_id' => 'required|integer|min:1',
            'grade.*.dia_semana' => 'required|integer|between:1,7',
            'dia_vencimento' => 'nullable|integer|between:1,31',
        ], [
            'plano_id.required' => 'Selecione um plano.',
            'grade.required' => 'Escolha seus dias e horários.',
            'grade.min' => 'Escolha seus dias e horários.',
            'grade.*.turma_fixa_id.required' => 'Escolha uma turma para cada dia.',
            'grade.*.dia_semana.required' => 'Escolha um dia para cada turma.',
            'dia_vencimento.integer' => 'Informe um dia de pagamento entre 1 e 31.',
            'dia_vencimento.between' => 'Informe um dia de pagamento entre 1 e 31.',
        ])->validate();

        return DB::transaction(function () use ($tenantId, $userId, $validados) {
            $config = DB::table('pre_matricula_configuracoes')->where('tenant_id', $tenantId)->where('ativa', 1)->lockForUpdate()->first();
            if (! $config || ! $this->alunoDaAcademia($tenantId, $userId)) {
                throw ValidationException::withMessages(['pre_matricula' => 'A pré-matrícula não está disponível para esta conta.']);
            }
            $anterior = DB::table('pre_matriculas')->where('tenant_id', $tenantId)->where('usuario_id', $userId)
                ->orderByDesc('id')->lockForUpdate()->first();
            if ($anterior && in_array($anterior->status, ['pendente', 'aprovada'], true)) {
                throw ValidationException::withMessages(['pre_matricula' => 'Você já enviou uma pré-matrícula para análise.']);
            }

            $plano = DB::table('planos')->where('tenant_id', $tenantId)->where('id', $validados['plano_id'])
                ->where('ativo', 1)->where('valor', 0)->where('checkins_semanais', '>', 0)->first();
            if (! $plano) {
                throw ValidationException::withMessages(['plano_id' => 'O plano selecionado não está disponível para pré-matrícula.']);
            }
            $frequencia = (int) $plano->checkins_semanais;
            $diasTurma = $this->normalizarGrade($tenantId, $plano, $validados['grade']);

            $id = (int) DB::table('pre_matriculas')->insertGetId([
                'tenant_id' => $tenantId,
                'usuario_id' => $userId,
                'plano_id' => (int) $plano->id,
                'plano_descricao' => $this->descricaoPlano($plano->descricao),
                'checkins_semanais' => $frequencia,
                'grade' => json_encode($diasTurma, JSON_THROW_ON_ERROR),
                'dia_vencimento' => $validados['dia_vencimento'] ?? null,
                'status' => 'pendente',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return ['id' => $id, 'status' => 'pendente', 'message' => 'Pré-matrícula enviada para análise. A matrícula só será criada após aprovação; ainda não há cobrança nem vaga reservada.'];
        }, 3);
    }

    /** @param array<string, mixed> $dados
     *  @return array{message: string}
     */
    public function atualizarSolicitacao(int $tenantId, int $id, array $dados): array
    {
        $this->exigirTabelas();
        $validados = Validator::make($dados, [
            'plano_id' => 'required|integer|min:1',
            'grade' => 'required|array|min:1|max:7',
            'grade.*.turma_fixa_id' => 'required|integer|min:1',
            'grade.*.dia_semana' => 'required|integer|between:1,7',
            'dia_vencimento' => 'nullable|integer|between:1,31',
        ], [
            'plano_id.required' => 'Selecione um plano.',
            'grade.required' => 'Escolha os dias e horários.',
            'grade.min' => 'Escolha os dias e horários.',
            'grade.*.turma_fixa_id.required' => 'Escolha uma turma para cada dia.',
            'grade.*.dia_semana.required' => 'Escolha um dia para cada turma.',
            'dia_vencimento.integer' => 'Informe um dia de pagamento entre 1 e 31.',
            'dia_vencimento.between' => 'Informe um dia de pagamento entre 1 e 31.',
        ])->validate();

        return DB::transaction(function () use ($tenantId, $id, $validados) {
            $solicitacao = DB::table('pre_matriculas')->where('tenant_id', $tenantId)->where('id', $id)
                ->lockForUpdate()->first();
            abort_unless($solicitacao, 404, 'Pré-matrícula não encontrada.');
            abort_unless($solicitacao->status === 'pendente', 409, 'Só é possível ajustar pedidos aguardando aprovação.');

            $plano = DB::table('planos')->where('tenant_id', $tenantId)->where('id', $validados['plano_id'])
                ->where('ativo', 1)->where('valor', 0)->where('checkins_semanais', '>', 0)->first();
            if (! $plano) {
                throw ValidationException::withMessages(['plano_id' => 'O plano selecionado não está disponível para pré-matrícula.']);
            }

            $grade = $this->normalizarGrade($tenantId, $plano, $validados['grade']);
            DB::table('pre_matriculas')->where('id', $id)->where('tenant_id', $tenantId)->update([
                'plano_id' => (int) $plano->id,
                'plano_descricao' => $this->descricaoPlano($plano->descricao),
                'checkins_semanais' => (int) $plano->checkins_semanais,
                'grade' => json_encode($grade, JSON_THROW_ON_ERROR),
                'dia_vencimento' => $validados['dia_vencimento'] ?? null,
                'updated_at' => now(),
            ]);

            return ['message' => 'Pré-matrícula ajustada. O pedido continua aguardando aprovação.'];
        }, 3);
    }

    public function avaliar(int $tenantId, int $id, int $userId, string $decisao, ?string $observacao = null): array
    {
        $this->exigirTabelas();
        abort_unless(in_array($decisao, ['aprovada', 'reprovada'], true), 422, 'Decisão inválida.');
        $observacao = trim((string) $observacao);
        if (mb_strlen($observacao) > 1000) {
            throw ValidationException::withMessages(['observacao' => 'A observação pode ter até 1000 caracteres.']);
        }

        return DB::transaction(function () use ($tenantId, $id, $userId, $decisao, $observacao) {
            $solicitacao = DB::table('pre_matriculas')->where('id', $id)->where('tenant_id', $tenantId)
                ->lockForUpdate()->first();
            abort_unless($solicitacao, 404, 'Pré-matrícula não encontrada.');
            abort_unless($solicitacao->status === 'pendente', 409, 'Esta pré-matrícula já foi analisada.');

            $matriculaId = null;
            if ($decisao === 'aprovada') {
                $matriculaId = $this->criarMatriculaAprovada($tenantId, $userId, $solicitacao);
            }

            DB::table('pre_matriculas')->where('id', $id)->where('tenant_id', $tenantId)->update([
                'status' => $decisao,
                'avaliada_por' => $userId,
                'avaliada_em' => now(),
                'observacao' => $observacao !== '' ? $observacao : null,
                ...(Schema::hasColumn('pre_matriculas', 'matricula_id') ? ['matricula_id' => $matriculaId] : []),
                'updated_at' => now(),
            ]);

            return [
                'message' => $decisao === 'aprovada'
                    ? 'Pré-matrícula aprovada e matrícula criada.'
                    : 'Pré-matrícula reprovada.',
                'matricula_id' => $matriculaId,
            ];
        }, 3);
    }

    private function criarMatriculaAprovada(int $tenantId, int $adminId, object $solicitacao): int
    {
        if (! $this->alunoDaAcademia($tenantId, (int) $solicitacao->usuario_id)) {
            throw ValidationException::withMessages([
                'usuario_id' => 'O aluno não está mais ativo nesta academia. Revise o cadastro antes de aprovar.',
            ]);
        }

        $plano = DB::table('planos')->where('tenant_id', $tenantId)->where('id', $solicitacao->plano_id)
            ->where('ativo', 1)->where('valor', 0)->where('checkins_semanais', '>', 0)->first();
        if (! $plano) {
            throw ValidationException::withMessages([
                'plano_id' => 'O plano gratuito solicitado não está mais ativo. Ajuste o plano antes de aprovar.',
            ]);
        }

        $gradeSolicitada = json_decode((string) $solicitacao->grade, true, 512, JSON_THROW_ON_ERROR);
        $grade = $this->normalizarGrade($tenantId, $plano, $gradeSolicitada);
        $resultado = app(AdminMatriculaService::class)->criar($tenantId, $adminId, [
            'usuario_id' => (int) $solicitacao->usuario_id,
            'plano_id' => (int) $plano->id,
            'tipo_acesso' => 'turma_fixa',
            'grade' => array_map(static fn (array $slot) => [
                'turma_fixa_id' => $slot['turma_fixa_id'],
                'dia_semana' => $slot['dia_semana'],
            ], $grade),
            'dia_vencimento' => $solicitacao->dia_vencimento === null ? null : (int) $solicitacao->dia_vencimento,
        ]);
        if (($resultado['status'] ?? 500) !== 201 || empty($resultado['body']['matricula']['id'])) {
            $body = $resultado['body'] ?? [];
            $mensagem = $body['message'] ?? $body['error'] ?? null;
            if (! is_string($mensagem) || $mensagem === '') {
                $erros = [];
                $errosResposta = is_array($body['errors'] ?? null) ? $body['errors'] : [];
                array_walk_recursive($errosResposta, static function ($erro) use (&$erros) {
                    if (is_string($erro) && $erro !== '') {
                        $erros[] = $erro;
                    }
                });
                $mensagem = $erros === []
                    ? 'Não foi possível criar a matrícula. O pedido continua aguardando aprovação.'
                    : implode(' ', $erros);
            }
            $status = (int) ($resultado['status'] ?? 500);
            abort($status >= 400 && $status <= 599 ? $status : 500, $mensagem);
        }

        return (int) $resultado['body']['matricula']['id'];
    }

    /** @return array<string, mixed> */
    private function configuracao(int $tenantId): array
    {
        $row = DB::table('pre_matricula_configuracoes')->where('tenant_id', $tenantId)->first();

        return [
            'tenant_id' => $tenantId,
            'configurada' => $row !== null,
            'ativa' => (bool) ($row->ativa ?? false),
            'obrigatoria' => (bool) ($row->obrigatoria ?? false),
            'publico' => (string) ($row->publico ?? 'sem_matricula'),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function solicitacoes(int $tenantId): array
    {
        $rows = DB::table('pre_matriculas as pm')
            ->join('usuarios as u', 'u.id', '=', 'pm.usuario_id')
            ->where('pm.tenant_id', $tenantId)->orderByDesc('pm.id')
            ->get(['pm.*', 'u.nome as usuario_nome', 'u.email', 'u.cpf']);
        $turmaIds = [];
        foreach ($rows as $row) {
            foreach (json_decode((string) $row->grade, true) ?: [] as $slot) {
                $turmaIds[] = (int) $slot['turma_fixa_id'];
            }
        }
        $turmas = $turmaIds === [] ? collect() : DB::table('turmas_fixas')->where('tenant_id', $tenantId)
            ->whereIn('id', array_unique($turmaIds))->get(['id', 'nome', 'horario_inicio', 'horario_fim'])->keyBy('id');

        return $rows->map(function ($row) use ($turmas) {
            $grade = json_decode((string) $row->grade, true) ?: [];
            $slots = array_map(function ($slot) use ($turmas) {
                $turma = $turmas->get((int) $slot['turma_fixa_id']);

                return [
                    'turma_fixa_id' => (int) $slot['turma_fixa_id'],
                    'turma_nome' => $slot['turma_nome'] ?? $turma->nome ?? 'Turma indisponível',
                    'dia_semana' => (int) $slot['dia_semana'],
                    'horario_inicio' => $slot['horario_inicio'] ?? $turma->horario_inicio ?? null,
                    'horario_fim' => $slot['horario_fim'] ?? $turma->horario_fim ?? null,
                ];
            }, $grade);

            return [
                'id' => (int) $row->id,
                'usuario_id' => (int) $row->usuario_id,
                'nome' => (string) $row->usuario_nome,
                'email' => (string) $row->email,
                'cpf' => $row->cpf,
                'plano_id' => (int) $row->plano_id,
                'matricula_id' => $this->matriculaIdDe($row),
                'plano_descricao' => $this->descricaoPlano($row->plano_descricao),
                'checkins_semanais' => (int) $row->checkins_semanais,
                'grade' => $slots,
                'dia_vencimento' => $row->dia_vencimento === null ? null : (int) $row->dia_vencimento,
                'status' => (string) $row->status,
                'observacao' => $row->observacao,
                'created_at' => $row->created_at,
                'avaliada_em' => $row->avaliada_em,
            ];
        })->all();
    }

    /** @return list<array<string, mixed>> */
    private function planosDisponiveis(int $tenantId): array
    {
        $diasPorModalidade = [];
        foreach ($this->turmasDisponiveis($tenantId) as $turma) {
            $diasPorModalidade[$turma['modalidade_id']] = [
                ...($diasPorModalidade[$turma['modalidade_id']] ?? []),
                ...$turma['dias_semana'],
            ];
        }

        return DB::table('planos as p')->join('modalidades as m', 'm.id', '=', 'p.modalidade_id')
            ->where('p.tenant_id', $tenantId)->where('p.ativo', 1)->where('p.valor', 0)->where('p.checkins_semanais', '>', 0)
            ->orderBy('p.checkins_semanais')->orderBy('p.id')
            ->get(['p.id', 'p.descricao', 'p.checkins_semanais', 'p.modalidade_id', 'm.nome as modalidade_nome'])
            ->map(fn ($plano) => [
                'id' => (int) $plano->id,
                'descricao' => $this->descricaoPlano($plano->descricao),
                'checkins_semanais' => (int) $plano->checkins_semanais,
                'modalidade_id' => (int) $plano->modalidade_id,
                'modalidade_nome' => (string) $plano->modalidade_nome,
                'dias_disponiveis' => count(array_unique($diasPorModalidade[(int) $plano->modalidade_id] ?? [])),
            ])->all();
    }

    /** @return list<array<string, mixed>> */
    private function turmasDisponiveis(int $tenantId): array
    {
        return DB::table('turmas_fixas')->where('tenant_id', $tenantId)->where('ativo', 1)->whereNull('deleted_at')
            ->where('vigencia_inicio', '<=', AcademyDateTime::today())
            ->where(fn ($query) => $query->whereNull('vigencia_fim')->orWhere('vigencia_fim', '>=', AcademyDateTime::today()))
            ->orderBy('horario_inicio')->orderBy('nome')
            ->get(['id', 'nome', 'modalidade_id', 'dias_semana', 'horario_inicio', 'horario_fim'])
            ->map(fn ($turma) => [
                'id' => (int) $turma->id,
                'nome' => (string) $turma->nome,
                'modalidade_id' => (int) $turma->modalidade_id,
                'dias_semana' => array_map('intval', json_decode((string) $turma->dias_semana, true, 512, JSON_THROW_ON_ERROR)),
                'horario_inicio' => (string) $turma->horario_inicio,
                'horario_fim' => (string) $turma->horario_fim,
            ])->all();
    }

    /** @param list<array{turma_fixa_id: int, dia_semana: int}> $grade
     *  @return list<array<string, mixed>>
     */
    private function normalizarGrade(int $tenantId, object $plano, array $grade): array
    {
        $frequencia = (int) $plano->checkins_semanais;
        if (count($grade) !== $frequencia) {
            throw ValidationException::withMessages(['grade' => "Escolha exatamente {$frequencia} dia(s) e horário(s) por semana, conforme o plano."]);
        }

        $dias = array_map(fn ($item) => (int) $item['dia_semana'], $grade);
        if (count(array_unique($dias)) !== count($dias)) {
            throw ValidationException::withMessages(['grade' => 'Escolha no máximo um horário por dia da semana.']);
        }

        $diasTurma = [];
        foreach ($grade as $item) {
            $turma = DB::table('turmas_fixas')->where('tenant_id', $tenantId)->where('id', $item['turma_fixa_id'])
                ->where('ativo', 1)->whereNull('deleted_at')->where('modalidade_id', $plano->modalidade_id)
                ->where('vigencia_inicio', '<=', AcademyDateTime::today())
                ->where(fn ($query) => $query->whereNull('vigencia_fim')->orWhere('vigencia_fim', '>=', AcademyDateTime::today()))
                ->first(['dias_semana', 'nome', 'horario_inicio', 'horario_fim']);
            $oferecidos = $turma ? json_decode((string) $turma->dias_semana, true, 512, JSON_THROW_ON_ERROR) : [];
            if (! $turma || ! in_array((int) $item['dia_semana'], array_map('intval', $oferecidos), true)) {
                throw ValidationException::withMessages(['grade' => 'Uma das turmas ou datas escolhidas não está mais disponível. Atualize os horários e tente novamente.']);
            }
            $diasTurma[] = [
                'turma_fixa_id' => (int) $item['turma_fixa_id'],
                'dia_semana' => (int) $item['dia_semana'],
                'turma_nome' => (string) $turma->nome,
                'horario_inicio' => (string) $turma->horario_inicio,
                'horario_fim' => (string) $turma->horario_fim,
            ];
        }

        return $diasTurma;
    }

    private function tabelasExistem(): bool
    {
        return Schema::hasTable('pre_matricula_configuracoes') && Schema::hasTable('pre_matriculas');
    }

    private function exigirTabelas(): void
    {
        abort_unless(
            $this->tabelasExistem(),
            503,
            'A pré-matrícula ainda não está disponível neste ambiente: a atualização do banco de dados está pendente.',
        );
    }

    private function matriculaIdDe(object $linha): ?int
    {
        return isset($linha->matricula_id) ? (int) $linha->matricula_id : null;
    }

    private function alunoDaAcademia(int $tenantId, int $userId): bool
    {
        return DB::table('usuarios as u')->join('tenant_usuario_papel as tup', 'tup.usuario_id', '=', 'u.id')
            ->where('u.id', $userId)->where('u.ativo', 1)->where('tup.tenant_id', $tenantId)
            ->where('tup.papel_id', Papel::ALUNO)->where('tup.ativo', 1)->exists();
    }

    private function temMatriculaAtiva(int $tenantId, int $userId): bool
    {
        return DB::table('matriculas as m')
            ->join('alunos as a', 'a.id', '=', 'm.aluno_id')
            ->join('status_matricula as sm', 'sm.id', '=', 'm.status_id')
            ->where('m.tenant_id', $tenantId)
            ->where('a.usuario_id', $userId)
            ->where('sm.codigo', 'ativa')
            ->exists();
    }

    private function publicoAlvo(string $publico, bool $temMatricula): bool
    {
        return $publico === 'ambos'
            || ($publico === 'com_matricula' && $temMatricula)
            || ($publico === 'sem_matricula' && ! $temMatricula);
    }

    private function descricaoPlano(?string $descricao): ?string
    {
        if ($descricao === null) {
            return null;
        }
        $descricao = (string) preg_replace('/\bFREE\b\s*(?:[-–—:·|]\s*)?/iu', '', $descricao);
        $descricao = trim($descricao, " \t\n\r\0\x0B-–—:·|");

        return $descricao === '' ? null : $descricao;
    }
}
