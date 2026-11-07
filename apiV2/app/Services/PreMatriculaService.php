<?php

namespace App\Services;

use App\Services\Admin\AdminMatriculaService;
use App\Support\AcademyDateTime;
use App\Support\Papel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
            'planos' => $this->planosDisponiveis($tenantId, true),
            'turmas' => $this->turmasDisponiveis($tenantId),
            'catalogo' => $this->catalogoPlanos($tenantId),
        ];
    }

    /** @return array{solicitacoes: list<array<string, mixed>>, planos: list<array<string, mixed>>, turmas: list<array<string, mixed>>} */
    public function painelAcademia(int $tenantId): array
    {
        $this->exigirTabelas();
        abort_unless(DB::table('tenants')->where('id', $tenantId)->exists(), 404, 'Academia não encontrada.');

        return [
            'solicitacoes' => $this->solicitacoes($tenantId),
            'planos' => $this->planosDisponiveis($tenantId, true),
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
        $paresInformados = array_key_exists('pares', $dados) ? $this->normalizarPares($tenantId, $dados['pares']) : null;
        if ($validados['ativa']) {
            $dias = $this->diasPorModalidade($tenantId);
            $pares = $paresInformados ?? array_map(
                static fn (array $par) => ['modalidade_id' => $par['modalidade_id'], 'checkins_semanais' => $par['checkins_semanais']],
                array_filter($this->pares($tenantId), static fn (array $par) => $par['valido']),
            );
            if ($pares === []) {
                throw ValidationException::withMessages(['ativa' => 'Vincule ao menos um plano gratuito de migração a um plano pago correspondente antes de ativar a pré-matrícula.']);
            }
            if (! collect($pares)->contains(
                fn (array $par) => count(array_unique($dias[$par['modalidade_id']] ?? [])) >= $par['checkins_semanais'],
            )) {
                throw ValidationException::withMessages(['ativa' => 'Nenhum plano vinculado tem turmas fixas disponíveis em dias distintos suficientes para a frequência semanal.']);
            }
        }

        $linha = [
            'ativa' => (bool) $validados['ativa'],
            'obrigatoria' => (bool) $validados['obrigatoria'],
            'publico' => $validados['publico'],
            'atualizada_por' => $userId,
            'updated_at' => now(),
        ];
        DB::transaction(function () use ($tenantId, $linha, $paresInformados) {
            if ($paresInformados !== null) {
                DB::table('pre_matricula_planos')->where('tenant_id', $tenantId)->delete();
                foreach ($paresInformados as $par) {
                    DB::table('pre_matricula_planos')->insert([
                        'tenant_id' => $tenantId,
                        'plano_free_id' => $par['plano_free_id'],
                        'plano_pago_id' => $par['plano_pago_id'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
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

        $ultima = $this->ultimaSolicitacao($tenantId, $userId);
        if ($ultima && $this->emAndamento($ultima)) {
            return [
                'tenant_id' => $tenantId,
                'disponivel' => true,
                'obrigatoria' => (bool) $config->obrigatoria,
                'status' => (string) $ultima->status,
                'solicitacao_id' => (int) $ultima->id,
                'observacao' => $ultima->observacao,
                'matricula_id' => $this->matriculaIdDe($ultima),
                'periodo_gratuito' => $ultima->status === 'aprovada' ? $this->periodoGratuito($tenantId, $ultima) : null,
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

    private function ultimaSolicitacao(int $tenantId, int $userId, bool $bloquear = false): ?object
    {
        $consulta = DB::table('pre_matriculas')->where('tenant_id', $tenantId)->where('usuario_id', $userId)
            ->whereNull('excluida_em')->orderByDesc('id');
        if ($bloquear) {
            $consulta->lockForUpdate();
        }

        return $consulta->first();
    }

    /** Pendente, ou aprovada cuja matrícula não foi cancelada (cancelada libera um novo envio). */
    private function emAndamento(object $solicitacao): bool
    {
        if ($solicitacao->status === 'pendente') {
            return true;
        }
        if ($solicitacao->status !== 'aprovada') {
            return false;
        }
        if (! isset($solicitacao->matricula_id)) {
            return true;
        }
        $status = DB::table('matriculas as m')->join('status_matricula as sm', 'sm.id', '=', 'm.status_id')
            ->where('m.id', $solicitacao->matricula_id)->value('sm.codigo');

        return $status !== null && $status !== 'cancelada';
    }

    /**
     * Estado do período gratuito da matrícula criada pela aprovação. Fica encerrado quando a matrícula
     * ainda está no plano de valor zero e já venceu; a compra reaproveita a matrícula e a grade.
     *
     * @return array{encerrado: bool, fim: ?string, plano_destino: ?array{id: int, descricao: string}}|null
     */
    private function periodoGratuito(int $tenantId, object $solicitacao): ?array
    {
        if (! isset($solicitacao->matricula_id)) {
            return null;
        }
        $matricula = DB::table('matriculas as m')->join('planos as p', 'p.id', '=', 'm.plano_id')
            ->join('status_matricula as sm', 'sm.id', '=', 'm.status_id')
            ->where('m.id', $solicitacao->matricula_id)->where('m.tenant_id', $tenantId)
            ->first(['m.plano_destino_id', 'm.data_vencimento', 'm.proxima_data_vencimento', 'p.valor', 'sm.codigo as status']);
        if (! $matricula) {
            return null;
        }
        $fim = $matricula->proxima_data_vencimento ?: $matricula->data_vencimento;
        $fim = $fim ? substr((string) $fim, 0, 10) : null;
        $encerrado = (float) $matricula->valor == 0.0 && $matricula->status !== 'cancelada'
            && ($matricula->status === 'vencida' || ($fim !== null && $fim < AcademyDateTime::today()));
        $destino = $matricula->plano_destino_id ? DB::table('planos')->where('tenant_id', $tenantId)
            ->where('id', $matricula->plano_destino_id)->where('ativo', 1)->where('valor', '>', 0)->first() : null;

        return [
            'encerrado' => $encerrado,
            'fim' => $fim,
            'plano_destino' => $destino ? [
                'id' => (int) $destino->id,
                'descricao' => (string) ($destino->nome ?: $this->descricaoPlano($destino->descricao) ?: 'Plano'),
            ] : null,
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
            $anterior = $this->ultimaSolicitacao($tenantId, $userId, true);
            if ($anterior && $this->emAndamento($anterior)) {
                throw ValidationException::withMessages(['pre_matricula' => 'Você já enviou uma pré-matrícula para análise.']);
            }

            [$plano, $par] = $this->planoVinculado($tenantId, (int) $validados['plano_id'], 'O plano selecionado não está disponível para pré-matrícula.');
            $frequencia = (int) $plano->checkins_semanais;
            $diasTurma = $this->normalizarGrade($tenantId, $plano, $validados['grade']);

            $id = (int) DB::table('pre_matriculas')->insertGetId([
                'tenant_id' => $tenantId,
                'usuario_id' => $userId,
                'plano_id' => (int) $plano->id,
                'plano_descricao' => $this->descricaoPlano($plano->descricao),
                'plano_destino_id' => $par['plano_pago_id'],
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
            $solicitacao = $this->solicitacaoDaAcademia($tenantId, $id);
            [$plano, $par] = $this->planoVinculado($tenantId, (int) $validados['plano_id'], 'O plano selecionado não está disponível para pré-matrícula.');

            $grade = $this->normalizarGrade($tenantId, $plano, $validados['grade']);
            $aprovada = $solicitacao->status === 'aprovada';
            DB::table('pre_matriculas')->where('id', $id)->where('tenant_id', $tenantId)->update([
                'plano_id' => (int) $plano->id,
                'plano_descricao' => $this->descricaoPlano($plano->descricao),
                // O destino da matrícula já criada não muda: o pedido guarda só o que foi aprovado
                ...($aprovada ? [] : ['plano_destino_id' => $par['plano_pago_id']]),
                'checkins_semanais' => (int) $plano->checkins_semanais,
                'grade' => json_encode($grade, JSON_THROW_ON_ERROR),
                'dia_vencimento' => $validados['dia_vencimento'] ?? null,
                'updated_at' => now(),
            ]);

            return ['message' => match ($solicitacao->status) {
                'aprovada' => 'Dados da solicitação atualizados. A matrícula já criada não foi alterada; use a gestão de matrículas para mudar plano, grade ou cobrança.',
                'reprovada' => 'Pré-matrícula ajustada. O pedido continua reprovado.',
                default => 'Pré-matrícula ajustada. O pedido continua aguardando aprovação.',
            }];
        }, 3);
    }

    /** @return array{message: string} */
    public function excluirSolicitacao(int $tenantId, int $id, int $userId): array
    {
        $this->exigirTabelas();

        return DB::transaction(function () use ($tenantId, $id, $userId) {
            $solicitacao = $this->solicitacaoDaAcademia($tenantId, $id);
            if ($solicitacao->status === 'pendente') {
                abort(409, 'Pedidos aguardando aprovação não podem ser excluídos. Aprove ou reprove antes.');
            }
            DB::table('pre_matriculas')->where('id', $id)->where('tenant_id', $tenantId)->update([
                'excluida_em' => now(),
                'excluida_por' => $userId,
                'updated_at' => now(),
            ]);
            Log::info('Pré-matrícula excluída', [
                'pre_matricula_id' => $id, 'tenant_id' => $tenantId, 'usuario_id' => $userId,
                'status' => $solicitacao->status, 'matricula_id' => $this->matriculaIdDe($solicitacao),
            ]);

            return ['message' => 'Pré-matrícula excluída. A matrícula vinculada, quando houver, foi preservada.'];
        }, 3);
    }

    /** Pedido da academia, ainda não excluído, com bloqueio de linha para a operação. */
    private function solicitacaoDaAcademia(int $tenantId, int $id): object
    {
        $solicitacao = DB::table('pre_matriculas')->where('tenant_id', $tenantId)->where('id', $id)
            ->whereNull('excluida_em')->lockForUpdate()->first();
        abort_unless($solicitacao, 404, 'Pré-matrícula não encontrada.');

        return $solicitacao;
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
            $solicitacao = $this->solicitacaoDaAcademia($tenantId, $id);
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

        [$plano, $par] = $this->planoVinculado(
            $tenantId,
            (int) $solicitacao->plano_id,
            'O plano gratuito solicitado não está mais ativo ou não tem plano pago vinculado. Ajuste o plano antes de aprovar.',
        );

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

        $matriculaId = (int) $resultado['body']['matricula']['id'];
        // O destino fica na matrícula: mudar a configuração depois não troca o plano pago de quem já foi aprovado
        DB::table('matriculas')->where('id', $matriculaId)->where('tenant_id', $tenantId)
            ->update(['plano_destino_id' => $par['plano_pago_id']]);
        DB::table('pre_matriculas')->where('id', $solicitacao->id)->update(['plano_destino_id' => $par['plano_pago_id']]);

        return $matriculaId;
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
            'pares' => $this->pares($tenantId),
        ];
    }

    /**
     * @param  mixed  $pares
     * @return list<array{plano_free_id: int, plano_pago_id: int, modalidade_id: int, checkins_semanais: int}>
     */
    private function normalizarPares(int $tenantId, mixed $pares): array
    {
        if (! is_array($pares) || ! array_is_list($pares)) {
            throw ValidationException::withMessages(['pares' => 'Informe a lista de planos vinculados.']);
        }
        $saida = [];
        $vistos = [];
        foreach ($pares as $indice => $par) {
            $n = $indice + 1;
            $freeId = is_array($par) ? filter_var($par['plano_free_id'] ?? null, FILTER_VALIDATE_INT) : false;
            $pagoId = is_array($par) ? filter_var($par['plano_pago_id'] ?? null, FILTER_VALIDATE_INT) : false;
            if (! $freeId || ! $pagoId) {
                throw ValidationException::withMessages(['pares' => "Vínculo {$n}: selecione o plano gratuito e o plano pago."]);
            }
            if (isset($vistos[$freeId])) {
                throw ValidationException::withMessages(['pares' => "Vínculo {$n}: o mesmo plano gratuito não pode ter dois planos pagos."]);
            }
            $free = DB::table('planos')->where('tenant_id', $tenantId)->where('id', $freeId)
                ->where('ativo', 1)->where('valor', 0)->where('checkins_semanais', '>', 0)->first();
            if (! $free) {
                throw ValidationException::withMessages(['pares' => "Vínculo {$n}: o plano gratuito deve ser da academia, estar ativo, ter valor zero e aulas semanais."]);
            }
            $pago = DB::table('planos')->where('tenant_id', $tenantId)->where('id', $pagoId)
                ->where('ativo', 1)->where('valor', '>', 0)->first();
            if (! $pago) {
                throw ValidationException::withMessages(['pares' => "Vínculo {$n}: o plano pago deve ser da academia, estar ativo e ter valor maior que zero."]);
            }
            if ((int) $pago->modalidade_id !== (int) $free->modalidade_id
                || (int) $pago->checkins_semanais !== (int) $free->checkins_semanais) {
                throw ValidationException::withMessages(['pares' => "Vínculo {$n}: o plano pago precisa ter a mesma modalidade e a mesma frequência semanal do plano gratuito."]);
            }
            $vistos[$freeId] = true;
            $saida[] = [
                'plano_free_id' => (int) $freeId,
                'plano_pago_id' => (int) $pagoId,
                'modalidade_id' => (int) $free->modalidade_id,
                'checkins_semanais' => (int) $free->checkins_semanais,
            ];
        }

        return $saida;
    }

    /** @return list<array<string, mixed>> */
    private function pares(int $tenantId): array
    {
        return DB::table('pre_matricula_planos as pp')
            ->join('planos as pf', 'pf.id', '=', 'pp.plano_free_id')
            ->join('planos as pg', 'pg.id', '=', 'pp.plano_pago_id')
            ->leftJoin('modalidades as m', 'm.id', '=', 'pf.modalidade_id')
            ->where('pp.tenant_id', $tenantId)
            ->orderBy('pf.checkins_semanais')->orderBy('pp.id')
            ->get([
                'pp.plano_free_id', 'pp.plano_pago_id', 'pf.nome as free_nome', 'pf.descricao as free_descricao',
                'pg.nome as pago_nome', 'pg.descricao as pago_descricao', 'pf.checkins_semanais', 'pf.modalidade_id',
                'm.nome as modalidade_nome', 'pf.tenant_id as free_tenant', 'pf.ativo as free_ativo', 'pf.valor as free_valor',
                'pg.tenant_id as pago_tenant', 'pg.ativo as pago_ativo', 'pg.valor as pago_valor',
                'pg.modalidade_id as pago_modalidade', 'pg.checkins_semanais as pago_checkins',
            ])->map(fn ($par) => [
                'plano_free_id' => (int) $par->plano_free_id,
                'plano_pago_id' => (int) $par->plano_pago_id,
                'plano_free_nome' => (string) ($par->free_nome ?: $this->descricaoPlano($par->free_descricao) ?: 'Plano gratuito'),
                'plano_pago_nome' => (string) ($par->pago_nome ?: $par->pago_descricao ?: 'Plano pago'),
                'descricao' => $this->descricaoPlano($par->free_descricao),
                'checkins_semanais' => (int) $par->checkins_semanais,
                'modalidade_id' => (int) $par->modalidade_id,
                'modalidade_nome' => (string) $par->modalidade_nome,
                // Plano alterado depois do vínculo (inativo, preço, frequência): o par deixa de ser oferecido
                'valido' => (int) $par->free_tenant === $tenantId && (int) $par->pago_tenant === $tenantId
                    && (bool) $par->free_ativo && (bool) $par->pago_ativo
                    && (float) $par->free_valor == 0.0 && (float) $par->pago_valor > 0
                    && (int) $par->pago_modalidade === (int) $par->modalidade_id
                    && (int) $par->pago_checkins === (int) $par->checkins_semanais,
            ])->all();
    }

    /** @return array<string, mixed>|null */
    private function parValido(int $tenantId, int $planoFreeId): ?array
    {
        foreach ($this->pares($tenantId) as $par) {
            if ($par['valido'] && $par['plano_free_id'] === $planoFreeId) {
                return $par;
            }
        }

        return null;
    }

    /** @return array{gratuitos: list<array<string, mixed>>, pagos: list<array<string, mixed>>} */
    private function catalogoPlanos(int $tenantId): array
    {
        $planos = DB::table('planos as p')->leftJoin('modalidades as m', 'm.id', '=', 'p.modalidade_id')
            ->where('p.tenant_id', $tenantId)->where('p.ativo', 1)->where('p.checkins_semanais', '>', 0)
            ->orderBy('p.modalidade_id')->orderBy('p.checkins_semanais')->orderBy('p.id')
            ->get(['p.id', 'p.nome', 'p.descricao', 'p.valor', 'p.checkins_semanais', 'p.modalidade_id', 'm.nome as modalidade_nome'])
            ->map(fn ($p) => [
                'id' => (int) $p->id,
                'nome' => (string) ($p->nome ?: $p->descricao ?: 'Plano #'.$p->id),
                'checkins_semanais' => (int) $p->checkins_semanais,
                'modalidade_id' => (int) $p->modalidade_id,
                'modalidade_nome' => (string) $p->modalidade_nome,
                'gratuito' => (float) $p->valor == 0.0,
            ]);

        return [
            'gratuitos' => $planos->where('gratuito', true)->values()->all(),
            'pagos' => $planos->where('gratuito', false)->values()->all(),
        ];
    }

    /** @return array<int, list<int>> */
    private function diasPorModalidade(int $tenantId): array
    {
        $dias = [];
        foreach ($this->turmasDisponiveis($tenantId) as $turma) {
            $dias[$turma['modalidade_id']] = [...($dias[$turma['modalidade_id']] ?? []), ...$turma['dias_semana']];
        }

        return $dias;
    }

    /** @return list<array<string, mixed>> */
    private function solicitacoes(int $tenantId): array
    {
        $telefone = Schema::hasColumn('usuarios', 'telefone') ? ['u.telefone'] : [];
        $rows = DB::table('pre_matriculas as pm')
            ->join('usuarios as u', 'u.id', '=', 'pm.usuario_id')
            ->leftJoin('matriculas as m', 'm.id', '=', 'pm.matricula_id')
            ->leftJoin('status_matricula as sm', 'sm.id', '=', 'm.status_id')
            ->where('pm.tenant_id', $tenantId)->whereNull('pm.excluida_em')->orderByDesc('pm.id')
            ->get(['pm.*', 'u.nome as usuario_nome', 'u.email', 'u.cpf', 'sm.codigo as matricula_status', ...$telefone]);
        $nomesDestino = DB::table('planos')->where('tenant_id', $tenantId)
            ->whereIn('id', $rows->pluck('plano_destino_id')->filter()->unique()->all())->pluck('nome', 'id');
        $turmaIds = [];
        foreach ($rows as $row) {
            foreach (json_decode((string) $row->grade, true) ?: [] as $slot) {
                $turmaIds[] = (int) $slot['turma_fixa_id'];
            }
        }
        $turmas = $turmaIds === [] ? collect() : DB::table('turmas_fixas')->where('tenant_id', $tenantId)
            ->whereIn('id', array_unique($turmaIds))->get(['id', 'nome', 'horario_inicio', 'horario_fim'])->keyBy('id');

        return $rows->map(function ($row) use ($turmas, $nomesDestino) {
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
                'telefone' => $row->telefone ?? null,
                'plano_id' => (int) $row->plano_id,
                'plano_destino_id' => $row->plano_destino_id === null ? null : (int) $row->plano_destino_id,
                'plano_destino_nome' => $row->plano_destino_id === null ? null : ($nomesDestino[$row->plano_destino_id] ?? null),
                'matricula_status' => $row->matricula_status,
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

    /**
     * Só planos gratuitos com vínculo válido a um plano pago. Para o aluno, sem identificar o plano pago.
     *
     * @return list<array<string, mixed>>
     */
    private function planosDisponiveis(int $tenantId, bool $comPlanoPago = false): array
    {
        $dias = $this->diasPorModalidade($tenantId);

        return array_values(array_map(function (array $par) use ($dias, $comPlanoPago) {
            $plano = [
                'id' => $par['plano_free_id'],
                'descricao' => $par['descricao'],
                'checkins_semanais' => $par['checkins_semanais'],
                'modalidade_id' => $par['modalidade_id'],
                'modalidade_nome' => $par['modalidade_nome'],
                'dias_disponiveis' => count(array_unique($dias[$par['modalidade_id']] ?? [])),
            ];

            return $comPlanoPago
                ? $plano + ['plano_pago_id' => $par['plano_pago_id'], 'plano_pago_nome' => $par['plano_pago_nome']]
                : $plano;
        }, array_filter($this->pares($tenantId), static fn (array $par) => $par['valido'])));
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
        return Schema::hasTable('pre_matricula_configuracoes') && Schema::hasTable('pre_matriculas')
            && Schema::hasTable('pre_matricula_planos')
            && Schema::hasColumn('pre_matriculas', 'excluida_em')
            && Schema::hasColumn('pre_matriculas', 'matricula_id')
            && Schema::hasColumn('matriculas', 'plano_destino_id');
    }

    /** @return array{0: object, 1: array<string, mixed>} plano gratuito e vínculo com o plano pago */
    private function planoVinculado(int $tenantId, int $planoId, string $mensagem): array
    {
        $par = $this->parValido($tenantId, $planoId);
        $plano = $par ? DB::table('planos')->where('tenant_id', $tenantId)->where('id', $planoId)
            ->where('ativo', 1)->where('valor', 0)->where('checkins_semanais', '>', 0)->first() : null;
        if (! $par || ! $plano) {
            throw ValidationException::withMessages(['plano_id' => $mensagem]);
        }

        return [$plano, $par];
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
