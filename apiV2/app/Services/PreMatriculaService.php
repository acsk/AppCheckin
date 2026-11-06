<?php

namespace App\Services;

use App\Support\AcademyDateTime;
use App\Support\Papel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PreMatriculaService
{
    private const PUBLICOS = ['sem_matricula', 'com_matricula', 'ambos'];

    /** @return array{configuracao: array<string, mixed>, solicitacoes: list<array<string, mixed>>} */
    public function painel(int $tenantId): array
    {
        abort_unless(DB::table('tenants')->where('id', $tenantId)->exists(), 404, 'Academia não encontrada.');

        return [
            'configuracao' => $this->configuracao($tenantId),
            'solicitacoes' => $this->solicitacoes($tenantId),
        ];
    }

    /** @param array<string, mixed> $dados
     *  @return array<string, mixed>
     */
    public function salvarConfiguracao(array $dados, ?int $userId): array
    {
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
            if ($ofertas === []) {
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
        abort_unless(DB::table('tenants')->where('id', $tenantId)->exists(), 404, 'Academia não encontrada.');
        DB::table('pre_matricula_configuracoes')->where('tenant_id', $tenantId)->delete();

        return $this->configuracao($tenantId);
    }

    /** @return array<string, mixed>|null */
    public function paraAluno(int $tenantId, int $userId): ?array
    {
        $config = DB::table('pre_matricula_configuracoes')->where('tenant_id', $tenantId)->where('ativa', 1)->first();
        if (! $config || ! $this->alunoDaAcademia($tenantId, $userId)) {
            return null;
        }

        $temMatricula = $this->temMatriculaAtiva($tenantId, $userId);
        if (! $this->publicoAlvo((string) $config->publico, $temMatricula)) {
            return null;
        }

        $ultima = DB::table('pre_matriculas')->where('tenant_id', $tenantId)->where('usuario_id', $userId)
            ->orderByDesc('id')->first();
        if ($ultima && in_array($ultima->status, ['pendente', 'aprovada'], true)) {
            return [
                'tenant_id' => $tenantId,
                'obrigatoria' => (bool) $config->obrigatoria,
                'status' => (string) $ultima->status,
                'solicitacao_id' => (int) $ultima->id,
                'observacao' => $ultima->observacao,
                'planos' => [],
                'turmas' => [],
            ];
        }

        return [
            'tenant_id' => $tenantId,
            'obrigatoria' => (bool) $config->obrigatoria,
            'status' => $ultima?->status === 'reprovada' ? 'reprovada' : null,
            'solicitacao_id' => $ultima ? (int) $ultima->id : null,
            'observacao' => $ultima?->observacao,
            'planos' => $this->planosDisponiveis($tenantId),
            'turmas' => $this->turmasDisponiveis($tenantId),
        ];
    }

    /** @param array<string, mixed> $dados
     *  @return array<string, mixed>
     */
    public function enviar(int $tenantId, int $userId, array $dados): array
    {
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
            if (! $this->publicoAlvo((string) $config->publico, $this->temMatriculaAtiva($tenantId, $userId))) {
                throw ValidationException::withMessages(['pre_matricula' => 'Sua conta não faz parte do público desta pré-matrícula.']);
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
            $grade = $validados['grade'];
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

            return ['id' => $id, 'status' => 'pendente', 'message' => 'Pré-matrícula enviada para análise. Ela não cria matrícula, não gera cobrança e não reserva vaga.'];
        }, 3);
    }

    public function avaliar(int $tenantId, int $id, int $userId, string $decisao, ?string $observacao = null): array
    {
        abort_unless(in_array($decisao, ['aprovada', 'reprovada'], true), 422, 'Decisão inválida.');
        $observacao = trim((string) $observacao);
        if (mb_strlen($observacao) > 1000) {
            throw ValidationException::withMessages(['observacao' => 'A observação pode ter até 1000 caracteres.']);
        }

        $alteradas = DB::table('pre_matriculas')->where('id', $id)->where('tenant_id', $tenantId)->where('status', 'pendente')
            ->update([
                'status' => $decisao,
                'avaliada_por' => $userId,
                'avaliada_em' => now(),
                'observacao' => $observacao !== '' ? $observacao : null,
                'updated_at' => now(),
            ]);
        if (! $alteradas) {
            abort_unless(DB::table('pre_matriculas')->where('id', $id)->where('tenant_id', $tenantId)->exists(), 404, 'Pré-matrícula não encontrada.');
            abort(409, 'Esta pré-matrícula já foi analisada.');
        }

        return ['message' => $decisao === 'aprovada' ? 'Pré-matrícula aprovada.' : 'Pré-matrícula reprovada. A aprovação não cria matrícula.'];
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
            ->filter(fn ($plano) => count(array_unique($diasPorModalidade[(int) $plano->modalidade_id] ?? [])) >= (int) $plano->checkins_semanais)
            ->values()
            ->map(fn ($plano) => [
                'id' => (int) $plano->id,
                'descricao' => $this->descricaoPlano($plano->descricao),
                'checkins_semanais' => (int) $plano->checkins_semanais,
                'modalidade_id' => (int) $plano->modalidade_id,
                'modalidade_nome' => (string) $plano->modalidade_nome,
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

    private function alunoDaAcademia(int $tenantId, int $userId): bool
    {
        return DB::table('usuarios as u')->join('tenant_usuario_papel as tup', 'tup.usuario_id', '=', 'u.id')
            ->where('u.id', $userId)->where('u.ativo', 1)->where('tup.tenant_id', $tenantId)
            ->where('tup.papel_id', Papel::ALUNO)->where('tup.ativo', 1)->exists();
    }

    private function temMatriculaAtiva(int $tenantId, int $userId): bool
    {
        return DB::table('matriculas as m')->join('alunos as a', 'a.id', '=', 'm.aluno_id')
            ->join('status_matricula as sm', 'sm.id', '=', 'm.status_id')
            ->where('m.tenant_id', $tenantId)->where('a.usuario_id', $userId)->where('sm.codigo', 'ativa')->exists();
    }

    private function publicoAlvo(string $publico, bool $temMatricula): bool
    {
        return $publico === 'ambos' || ($publico === 'com_matricula' && $temMatricula)
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
