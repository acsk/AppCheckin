<?php

namespace App\Services\Admin\Prospeccao;

use App\Repositories\ProspeccaoRepository;
use App\Support\AcademyDateTime;
use App\Support\ProspeccaoStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Oportunidades: listagem, ficha comercial, funil, interações e restrição de contato.
 *
 * $ctx = ['usuario_id' => int, 'admin' => bool]. Comercial não vê valores financeiros e não abre matrícula.
 */
class ProspeccaoOportunidadeService
{
    public const POR_PAGINA_PADRAO = 20;

    public const POR_PAGINA_MAX = 100;

    /** Contato de outra pessoa nesse intervalo exige confirmação (evita abordagem duplicada). */
    public const HORAS_CONTATO_RECENTE = 24;

    private const ORDENACOES = [
        'nome' => 'a.nome',
        'cancelamento' => 'o.data_cancelamento_referencia',
        'score' => 'o.score',
        'ultimo_contato' => 'o.ultimo_contato_em',
        'proximo_contato' => 'o.proximo_contato_em',
        'status' => 'o.status',
    ];

    public function __construct(
        private readonly ProspeccaoRepository $repo,
        private readonly ProspeccaoScoreService $score,
    ) {}

    /**
     * @param  array<string, mixed>  $q
     * @return array{status: int, body: array<string, mixed>}
     */
    public function listar(int $tenantId, array $q, array $ctx): array
    {
        $hoje = AcademyDateTime::todayStart();
        $f = ['status' => (string) ($q['status'] ?? 'abertas')];
        if (! in_array($f['status'], ['abertas', 'todas'], true) && ! ProspeccaoStatus::existe($f['status'])) {
            return $this->erro('Status inválido', 422);
        }

        $busca = trim((string) ($q['busca'] ?? ''));
        if ($busca !== '') {
            $digitos = preg_replace('/\D/', '', $busca);
            if (! preg_match('/\pL/u', $busca) && strlen($digitos) >= 3) {
                $f['busca_digitos'] = $digitos;
            } else {
                $f['busca_nome'] = mb_strtoupper(mb_substr($busca, 0, 100), 'UTF-8');
            }
        }
        foreach (['modalidade_id', 'plano_id'] as $chave) {
            $f[$chave] = isset($q[$chave]) && ctype_digit((string) $q[$chave]) ? (int) $q[$chave] : null;
        }
        $responsavel = $q['responsavel'] ?? null;
        $f['responsavel_id'] = $responsavel === 'meus' ? $ctx['usuario_id'] : (ctype_digit((string) $responsavel) ? (int) $responsavel : null);

        foreach (['cancelamento_de', 'cancelamento_ate'] as $chave) {
            $v = $q[$chave] ?? null;
            if ($v !== null && $v !== '') {
                if (! $this->dataValida((string) $v)) {
                    return $this->erro('Data inválida em '.$chave.' (use AAAA-MM-DD)', 422);
                }
                $f[$chave] = (string) $v;
            }
        }
        // Tempo de inatividade (dias desde o cancelamento) vira intervalo de datas
        if (isset($q['inatividade_min']) && ctype_digit((string) $q['inatividade_min'])) {
            $limite = (clone $hoje)->modify('-'.(int) $q['inatividade_min'].' days')->format('Y-m-d');
            $f['cancelamento_ate'] = min($f['cancelamento_ate'] ?? $limite, $limite);
        }
        if (isset($q['inatividade_max']) && ctype_digit((string) $q['inatividade_max'])) {
            $limite = (clone $hoje)->modify('-'.(int) $q['inatividade_max'].' days')->format('Y-m-d');
            $f['cancelamento_de'] = max($f['cancelamento_de'] ?? $limite, $limite);
        }
        $f['sem_contato'] = filter_var($q['sem_contato'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $f['com_retorno'] = filter_var($q['com_retorno'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $f['potencial'] = in_array($q['potencial'] ?? null, ['alto', 'medio', 'baixo'], true) ? $q['potencial'] : null;

        $ordem = self::ORDENACOES[$q['ordem'] ?? 'score'] ?? 'o.score';
        $direcao = strtolower((string) ($q['direcao'] ?? ($ordem === 'a.nome' ? 'asc' : 'desc'))) === 'asc' ? 'asc' : 'desc';
        $pagina = max(1, (int) ($q['pagina'] ?? 1));
        $porPagina = min(self::POR_PAGINA_MAX, max(1, (int) ($q['por_pagina'] ?? self::POR_PAGINA_PADRAO)));

        $r = $this->repo->listar($tenantId, $f, $pagina, $porPagina, $ordem, $direcao);

        return ['status' => 200, 'body' => [
            'oportunidades' => array_map(fn ($row) => $this->formatarLinha($row, $hoje), $r['rows']),
            'paginacao' => ['pagina' => $pagina, 'por_pagina' => $porPagina, 'total' => $r['total'], 'paginas' => (int) ceil($r['total'] / $porPagina)],
        ]];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function detalhar(int $tenantId, int $id, array $ctx): array
    {
        $o = $this->repo->buscar($tenantId, $id);
        if (! $o) {
            return $this->erro('Oportunidade não encontrada', 404);
        }
        $hoje = AcademyDateTime::todayStart();
        $alunoId = (int) $o['aluno_id'];
        $financeiro = $this->repo->resumoFinanceiro($tenantId, $alunoId, $hoje->format('Y-m-d'));
        if (! $ctx['admin']) {
            unset($financeiro['valor_pago'], $financeiro['valor_em_atraso']);
        }

        $oportunidade = $this->formatarLinha($o, $hoje) + [
            'interesse' => $o['interesse'],
            'condicoes_apresentadas' => $o['condicoes_apresentadas'],
            'observacoes' => $o['observacoes'],
            'score_detalhes' => $o['score_detalhes'] ? json_decode((string) $o['score_detalhes'], true) : null,
            'score_calculado_em' => $o['score_calculado_em'],
            'matricula_conversao_id' => $o['matricula_conversao_id'] ? (int) $o['matricula_conversao_id'] : null,
            'encerrada_em' => $o['encerrada_em'],
            'criada_em' => $o['created_at'],
            'ultimo_contato_por' => $o['ultimo_contato_por'] ? (int) $o['ultimo_contato_por'] : null,
            'ultimo_contato_por_nome' => $o['ultimo_contato_por_nome'],
            'restricao' => $o['nao_contatar'] ? ['motivo' => $o['restricao_motivo'], 'desde' => $o['restricao_desde']] : null,
            'transicoes' => ProspeccaoStatus::transicoesManuais((string) $o['status']),
        ];

        return ['status' => 200, 'body' => [
            'oportunidade' => $oportunidade,
            'aluno' => [
                'id' => $alunoId,
                'usuario_id' => (int) $o['usuario_id'],
                'nome' => $o['aluno_nome'],
                'email' => $o['email'],
                'telefone' => $o['telefone'],
                'whatsapp' => $o['whatsapp'],
                'data_nascimento' => $o['data_nascimento'],
                'foto_caminho' => $o['foto_caminho'],
                'ativo' => (bool) $o['aluno_ativo'],
            ],
            'matriculas' => $this->repo->matriculasDoAluno($tenantId, $alunoId),
            'checkins' => $this->repo->resumoCheckins($tenantId, $alunoId, (clone $hoje)->modify('first day of -11 months')->format('Y-m-d')),
            'financeiro' => $financeiro,
            'historico' => $this->repo->historicoStatus($tenantId, $id),
            'interacoes' => $this->repo->interacoes($tenantId, $id),
            'agendamentos' => $this->repo->agenda($tenantId, null, null, null, $id, 50),
            'ciclos' => $this->repo->ciclosDoAluno($tenantId, $alunoId, $id),
            'variaveis' => [
                'nome' => $o['aluno_nome'],
                'modalidade' => $o['modalidade_nome'],
                'plano_anterior' => $o['plano_nome'],
                'estabelecimento' => $this->repo->nomeTenant($tenantId),
            ],
        ]];
    }

    /**
     * Campos comerciais livres (não altera o cadastro do aluno).
     *
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function atualizar(int $tenantId, int $id, array $data, array $ctx): array
    {
        $o = $this->repo->buscarSimples($tenantId, $id);
        if (! $o) {
            return $this->erro('Oportunidade não encontrada', 404);
        }

        $campos = [];
        if (array_key_exists('responsavel_id', $data)) {
            $resp = $data['responsavel_id'];
            if ($resp !== null && $resp !== '' && ! $this->repo->usuarioDaEquipe($tenantId, (int) $resp)) {
                return $this->erro('Responsável precisa ser administrador ou comercial desta academia', 422);
            }
            $campos['responsavel_id'] = $resp === null || $resp === '' ? null : (int) $resp;
        }
        if (array_key_exists('motivo_categoria', $data)) {
            $m = $data['motivo_categoria'];
            if ($m !== null && $m !== '' && ! isset(ProspeccaoStatus::MOTIVOS[$m])) {
                return $this->erro('Motivo inválido', 422);
            }
            $campos['motivo_categoria'] = $m === '' ? null : $m;
        }
        foreach (['interesse' => 255, 'condicoes_apresentadas' => 5000, 'observacoes' => 5000] as $campo => $max) {
            if (array_key_exists($campo, $data)) {
                $v = $data[$campo] === null ? null : trim((string) $data[$campo]);
                if ($v !== null && mb_strlen($v) > $max) {
                    return $this->erro('Texto muito longo em '.$campo.' (máx. '.$max.')', 422);
                }
                $campos[$campo] = $v === '' ? null : $v;
            }
        }
        if ($campos === []) {
            return $this->erro('Nada para atualizar', 422);
        }

        $this->repo->atualizar($tenantId, $id, $campos);
        if (array_key_exists('motivo_categoria', $campos)) {
            $this->score->recalcular($tenantId, [$id]);
        }
        $this->auditar('atualizar', $tenantId, $id, $ctx, array_keys($campos));

        return $this->detalhar($tenantId, $id, $ctx);
    }

    /**
     * @param  array<string, mixed>  $data  {status, observacao?, motivo_restricao?}
     * @return array{status: int, body: array<string, mixed>}
     */
    public function alterarStatus(int $tenantId, int $id, array $data, array $ctx): array
    {
        $o = $this->repo->buscarSimples($tenantId, $id);
        if (! $o) {
            return $this->erro('Oportunidade não encontrada', 404);
        }
        $novo = (string) ($data['status'] ?? '');
        $obs = isset($data['observacao']) ? trim((string) $data['observacao']) : null;
        $erro = $this->aplicarStatus($tenantId, $o, $novo, $obs ?: null, $ctx, $data['motivo_restricao'] ?? null);
        if ($erro) {
            return $erro;
        }

        return $this->detalhar($tenantId, $id, $ctx);
    }

    /**
     * Valida e aplica a transição (sem transação própria: o chamador decide).
     *
     * @param  array<string, mixed>  $o
     * @return array{status: int, body: array<string, mixed>}|null erro ou null
     */
    private function aplicarStatus(int $tenantId, array $o, string $novo, ?string $obs, array $ctx, ?string $motivoRestricao): ?array
    {
        $atual = (string) $o['status'];
        if ($novo === ProspeccaoStatus::RECUPERADO) {
            return $this->erro('Recuperado é definido automaticamente quando o aluno tem uma nova matrícula ativa', 422);
        }
        if (! ProspeccaoStatus::existe($novo)) {
            return $this->erro('Status inválido', 422);
        }
        if ($novo === $atual) {
            return null;
        }
        if (! ProspeccaoStatus::podeTransicionar($atual, $novo)) {
            return $this->erro('Não é possível mudar de "'.ProspeccaoStatus::ROTULOS[$atual].'" para "'.ProspeccaoStatus::ROTULOS[$novo].'"', 422);
        }
        $reabrindo = ! ProspeccaoStatus::aberto($atual) && ProspeccaoStatus::aberto($novo);
        if ($reabrindo && $this->repo->existeOportunidadeAbertaDoAluno($tenantId, (int) $o['aluno_id'], (int) $o['id'])) {
            return $this->erro('O aluno já tem outra oportunidade aberta', 409);
        }

        $agora = AcademyDateTime::nowFormatted();
        DB::transaction(function () use ($tenantId, $o, $atual, $novo, $obs, $ctx, $motivoRestricao, $agora) {
            $id = (int) $o['id'];
            $campos = ['status' => $novo];
            if (! ProspeccaoStatus::aberto($novo)) {
                $campos['encerrada_em'] = $agora;
                $this->repo->cancelarAgendamentosPendentes($tenantId, $id, $ctx['usuario_id'], $agora);
            } elseif (! ProspeccaoStatus::aberto($atual)) {
                $campos['encerrada_em'] = null;
            }
            if ($novo === ProspeccaoStatus::NAO_CONTATAR && ! $this->repo->restricaoAtiva($tenantId, (int) $o['aluno_id'])) {
                $this->repo->inserirRestricao($tenantId, (int) $o['aluno_id'], $motivoRestricao ? mb_substr(trim($motivoRestricao), 0, 500) : $obs, $ctx['usuario_id'], $agora);
            }
            $this->repo->atualizar($tenantId, $id, $campos);
            $this->repo->registrarHistorico($tenantId, $id, $atual, $novo, $ctx['usuario_id'], $obs);
        });
        $this->auditar('status', $tenantId, (int) $o['id'], $ctx, ['de' => $atual, 'para' => $novo]);

        return null;
    }

    /**
     * Registra um contato feito pelo atendente. Abrir o WhatsApp sozinho não chama isto.
     *
     * @param  array<string, mixed>  $data  {canal, resultado, observacao?, mensagem?, modelo_mensagem_id?, ocorrido_em?, novo_status?, confirmar_duplicado?}
     * @return array{status: int, body: array<string, mixed>}
     */
    public function registrarInteracao(int $tenantId, int $id, array $data, array $ctx): array
    {
        $o = $this->repo->buscarSimples($tenantId, $id);
        if (! $o) {
            return $this->erro('Oportunidade não encontrada', 404);
        }
        $canal = (string) ($data['canal'] ?? '');
        $resultado = (string) ($data['resultado'] ?? '');
        if (! isset(ProspeccaoStatus::CANAIS[$canal])) {
            return $this->erro('Canal inválido', 422);
        }
        if (! isset(ProspeccaoStatus::RESULTADOS[$resultado])) {
            return $this->erro('Resultado inválido', 422);
        }
        if ($o['status'] === ProspeccaoStatus::NAO_CONTATAR || $this->repo->restricaoAtiva($tenantId, (int) $o['aluno_id'])) {
            return $this->erro('Aluno pediu para não ser contatado. Remova a restrição (com o motivo) antes de registrar contato.', 409);
        }
        if (! ProspeccaoStatus::aberto((string) $o['status']) && $resultado !== 'pediu_nao_contatar' && empty($data['novo_status'])) {
            return $this->erro('Oportunidade encerrada. Reabra-a (mude o status) para registrar contato.', 409);
        }

        $agora = AcademyDateTime::now();
        $ocorrido = $agora->format('Y-m-d H:i:s');
        if (! empty($data['ocorrido_em'])) {
            try {
                $dt = AcademyDateTime::parse((string) $data['ocorrido_em']);
            } catch (\Exception) {
                return $this->erro('Data do contato inválida', 422);
            }
            if ($dt > $agora) {
                return $this->erro('A data do contato não pode estar no futuro', 422);
            }
            $ocorrido = $dt->format('Y-m-d H:i:s');
        }

        // Evita abordagem duplicada: outra pessoa falou com o aluno há pouco
        $limite = (clone $agora)->modify('-'.self::HORAS_CONTATO_RECENTE.' hours')->format('Y-m-d H:i:s');
        if (! filter_var($data['confirmar_duplicado'] ?? false, FILTER_VALIDATE_BOOLEAN)
            && $o['ultimo_contato_em'] && $o['ultimo_contato_em'] >= $limite
            && (int) $o['ultimo_contato_por'] !== (int) $ctx['usuario_id']) {
            return ['status' => 409, 'body' => [
                'error' => 'Este aluno já foi contatado por outra pessoa da equipe nas últimas '.self::HORAS_CONTATO_RECENTE.' horas.',
                'code' => 'CONTATO_RECENTE',
                'ultimo_contato_em' => $o['ultimo_contato_em'],
            ]];
        }

        $modeloId = null;
        if (! empty($data['modelo_mensagem_id'])) {
            $modelo = $this->repo->buscarModelo($tenantId, (int) $data['modelo_mensagem_id']);
            if (! $modelo) {
                return $this->erro('Modelo de mensagem não encontrado', 422);
            }
            $modeloId = (int) $modelo['id'];
        }
        $mensagem = isset($data['mensagem']) ? mb_substr(trim((string) $data['mensagem']), 0, 5000) : null;
        $observacao = isset($data['observacao']) ? mb_substr(trim((string) $data['observacao']), 0, 5000) : null;

        // Status resultante: explícito, ou avanço natural do funil
        $novo = (string) ($data['novo_status'] ?? '');
        if ($novo === '') {
            $novo = match (true) {
                $resultado === 'pediu_nao_contatar' => ProspeccaoStatus::NAO_CONTATAR,
                $resultado === 'interessado' && in_array($o['status'], [ProspeccaoStatus::NAO_CONTATADO, ProspeccaoStatus::CONTATADO], true) => ProspeccaoStatus::INTERESSADO,
                $o['status'] === ProspeccaoStatus::NAO_CONTATADO => ProspeccaoStatus::CONTATADO,
                default => (string) $o['status'],
            };
        }

        $erro = null;
        try {
            $this->gravarInteracao($tenantId, $id, $o, $canal, $resultado, $modeloId, $mensagem, $observacao, $ocorrido, $novo, $ctx, $erro);
        } catch (TransicaoInvalida) {
            return $erro;
        }

        return $this->depoisDaInteracao($tenantId, $id, $ctx);
    }

    /**
     * Interação + último contato + status numa transação; transição inválida desfaz tudo.
     *
     * @param  array<string, mixed>  $o
     * @param  array{status: int, body: array<string, mixed>}|null  $erro
     */
    private function gravarInteracao(int $tenantId, int $id, array $o, string $canal, string $resultado, ?int $modeloId, ?string $mensagem, ?string $observacao, string $ocorrido, string $novo, array $ctx, ?array &$erro): void
    {
        DB::transaction(function () use ($tenantId, $id, $o, $canal, $resultado, $modeloId, $mensagem, $observacao, $ocorrido, $novo, $ctx, &$erro) {
            $this->repo->inserirInteracao([
                'tenant_id' => $tenantId,
                'oportunidade_id' => $id,
                'usuario_id' => $ctx['usuario_id'],
                'canal' => $canal,
                'resultado' => $resultado,
                'modelo_mensagem_id' => $modeloId,
                'mensagem' => $mensagem ?: null,
                'observacao' => $observacao ?: null,
                'ocorrido_em' => $ocorrido,
            ]);
            if (! $o['ultimo_contato_em'] || $ocorrido >= $o['ultimo_contato_em']) {
                $this->repo->atualizar($tenantId, $id, ['ultimo_contato_em' => $ocorrido, 'ultimo_contato_por' => $ctx['usuario_id']]);
            }
            $erro = $this->aplicarStatus($tenantId, $o, $novo, 'Contato: '.ProspeccaoStatus::RESULTADOS[$resultado], $ctx, $resultado === 'pediu_nao_contatar' ? $observacao : null);
            if ($erro) {
                throw new TransicaoInvalida;
            }
        });
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function depoisDaInteracao(int $tenantId, int $id, array $ctx): array
    {
        $this->score->recalcular($tenantId, [$id]);
        $this->auditar('interacao', $tenantId, $id, $ctx);
        $r = $this->detalhar($tenantId, $id, $ctx);
        $r['status'] = 201;

        return $r;
    }

    /**
     * Remove a restrição "Não contatar" (o aluno voltou a autorizar contato). Motivo obrigatório.
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function removerRestricao(int $tenantId, int $id, array $data, array $ctx): array
    {
        $o = $this->repo->buscarSimples($tenantId, $id);
        if (! $o) {
            return $this->erro('Oportunidade não encontrada', 404);
        }
        $motivo = trim((string) ($data['motivo'] ?? ''));
        if (mb_strlen($motivo) < 5) {
            return $this->erro('Informe como o aluno autorizou o novo contato (mín. 5 caracteres)', 422);
        }
        $alunoId = (int) $o['aluno_id'];
        if (! $this->repo->restricaoAtiva($tenantId, $alunoId)) {
            return $this->erro('Este aluno não tem restrição de contato ativa', 409);
        }

        $agora = AcademyDateTime::nowFormatted();
        DB::transaction(function () use ($tenantId, $alunoId, $motivo, $ctx, $agora) {
            $this->repo->removerRestricao($tenantId, $alunoId, mb_substr($motivo, 0, 500), $ctx['usuario_id'], $agora);
            // Reabre a oportunidade mais recente em Não contatar, se o aluno ainda estiver fora da academia
            $ultima = $this->repo->oportunidadesNaoContatar($tenantId, $alunoId)[0] ?? null;
            if ($ultima && ! $this->repo->existeOportunidadeAbertaDoAluno($tenantId, $alunoId, $ultima)
                && ! $this->repo->alunoTemMatriculaEmAndamento($tenantId, $alunoId)) {
                $this->repo->atualizar($tenantId, $ultima, ['status' => ProspeccaoStatus::NAO_CONTATADO, 'encerrada_em' => null]);
                $this->repo->registrarHistorico($tenantId, $ultima, ProspeccaoStatus::NAO_CONTATAR, ProspeccaoStatus::NAO_CONTATADO, $ctx['usuario_id'], 'Restrição removida: '.$motivo);
            }
        });
        $this->auditar('restricao_removida', $tenantId, $id, $ctx);

        return $this->detalhar($tenantId, $id, $ctx);
    }

    /**
     * Pré-checagem do botão "Recuperar aluno": a matrícula em si é feita no módulo de Matrículas.
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function verificarRecuperacao(int $tenantId, int $id, array $ctx): array
    {
        $o = $this->repo->buscar($tenantId, $id);
        if (! $o) {
            return $this->erro('Oportunidade não encontrada', 404);
        }
        $alunoId = (int) $o['aluno_id'];
        $pendencias = [];
        if (! $ctx['admin']) {
            $pendencias[] = 'A matrícula precisa ser feita por um administrador.';
        }
        if (! $o['aluno_ativo'] || ! $this->repo->vinculoAlunoAtivo($tenantId, (int) $o['usuario_id'])) {
            $pendencias[] = 'O cadastro do aluno está inativo nesta academia. Reative-o em Alunos antes de matricular.';
        }
        $emAndamento = array_values(array_filter(
            $this->repo->matriculasDoAluno($tenantId, $alunoId),
            fn ($m) => in_array($m['status_codigo'], ProspeccaoRepository::STATUS_MATRICULA_EM_ANDAMENTO, true),
        ));
        if ($emAndamento !== []) {
            $pendencias[] = 'O aluno já tem matrícula em andamento. Confira em Matrículas antes de criar outra.';
        }

        return ['status' => 200, 'body' => [
            'pode_matricular' => $pendencias === [],
            'pendencias' => $pendencias,
            'usuario_id' => (int) $o['usuario_id'],
            'matriculas_em_andamento' => $emAndamento,
        ]];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function formatarLinha(array $row, \DateTime $hoje): array
    {
        $score = $row['score'] !== null ? (int) $row['score'] : null;
        $cancel = (string) $row['data_cancelamento_referencia'];

        return [
            'id' => (int) $row['id'],
            'aluno_id' => (int) $row['aluno_id'],
            'aluno_nome' => $row['aluno_nome'],
            'foto_caminho' => $row['foto_caminho'],
            'telefone' => $row['telefone'],
            'whatsapp' => $row['whatsapp'],
            'modalidade_id' => $row['modalidade_id'] ? (int) $row['modalidade_id'] : null,
            'modalidade_nome' => $row['modalidade_nome'],
            'modalidade_cor' => $row['modalidade_cor'],
            'plano_id' => (int) $row['plano_id'],
            'plano_nome' => $row['plano_nome'],
            'matricula_referencia_id' => (int) $row['matricula_referencia_id'],
            'data_ultima_matricula' => $row['matricula_data_inicio'] ?? $row['data_matricula'],
            'data_cancelamento' => $cancel,
            'data_cancelamento_estimada' => (bool) $row['data_cancelamento_estimada'],
            'dias_inatividade' => max(0, (int) (new \DateTime($cancel))->diff($hoje)->format('%r%a')),
            'motivo_categoria' => $row['motivo_categoria'],
            'motivo_rotulo' => $row['motivo_categoria'] ? (ProspeccaoStatus::MOTIVOS[$row['motivo_categoria']] ?? null) : null,
            'motivo_cancelamento' => $row['motivo_cancelamento'],
            'status' => $row['status'],
            'status_rotulo' => ProspeccaoStatus::ROTULOS[$row['status']] ?? $row['status'],
            'ultimo_contato_em' => $row['ultimo_contato_em'],
            'proximo_contato_em' => $row['proximo_contato_em'],
            'score' => $score,
            'score_confianca' => $row['score_confianca'] !== null ? (int) $row['score_confianca'] : null,
            'potencial' => ProspeccaoScoreService::classificacao($score),
            'responsavel_id' => $row['responsavel_id'] ? (int) $row['responsavel_id'] : null,
            'responsavel_nome' => $row['responsavel_nome'],
            'nao_contatar' => (bool) $row['nao_contatar'],
            'convertida_em' => $row['convertida_em'],
        ];
    }

    private function dataValida(string $v): bool
    {
        $d = \DateTime::createFromFormat('!Y-m-d', $v);

        return $d !== false && $d->format('Y-m-d') === $v;
    }

    /** @param array<string, mixed> $extra */
    private function auditar(string $acao, int $tenantId, int $id, array $ctx, array $extra = []): void
    {
        Log::info('prospeccao.'.$acao, ['tenant_id' => $tenantId, 'oportunidade_id' => $id, 'usuario_id' => $ctx['usuario_id']] + ($extra ? ['dados' => $extra] : []));
    }

    /** @return array{status: int, body: array{error: string}} */
    private function erro(string $mensagem, int $status): array
    {
        return ['status' => $status, 'body' => ['error' => $mensagem]];
    }
}
