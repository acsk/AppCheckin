<?php

namespace App\Services\Comunicacao;

use App\Support\AcademyDateTime;
use App\Support\Comunicacao;
use Illuminate\Support\Facades\DB;

/**
 * Painel → Central de Comunicação: cadastro, publicação e encerramento.
 *
 * - Rascunho edita tudo. Depois de publicada, tipo, público e perguntas ficam fixos (já há destinatários
 *   e respostas); textos, prioridade, término, obrigatoriedade e anexos continuam editáveis.
 * - Publicar congela o público (uma linha por aluno). "Incluir novos alunos" recalcula e só acrescenta.
 * - Encerrar antecipa o término para agora (libera o check-in na hora); cancelar também tira do histórico.
 */
class ComunicacaoService
{
    private const EDITAVEIS_PUBLICADA = ['titulo', 'descricao', 'conteudo', 'icone', 'classificacao', 'prioridade', 'obrigatoria', 'exibir_modal', 'manter_historico', 'termino_em', 'configuracoes'];

    public function __construct(
        private readonly ComunicacaoPublicoService $publicos,
    ) {}

    /**
     * @param  array<string, mixed>  $f  tipo, status, busca, obrigatoria, ordem, direcao, pagina, por_pagina
     * @return array{status: int, body: array<string, mixed>}
     */
    public function listarComunicacoes(int $tenantId, array $f): array
    {
        $agora = AcademyDateTime::nowFormatted();
        $q = DB::table('comunicacoes as c')->where('c.tenant_id', $tenantId)->whereNull('c.deleted_at');
        if (! empty($f['tipo']) && isset(Comunicacao::TIPOS[$f['tipo']])) {
            $q->where('c.tipo', $f['tipo']);
        }
        if (! empty($f['status']) && in_array($f['status'], Comunicacao::STATUS, true)) {
            Comunicacao::whereStatus($q, (string) $f['status'], $agora);
        }
        if (isset($f['obrigatoria']) && $f['obrigatoria'] !== '') {
            $q->where('c.obrigatoria', filter_var($f['obrigatoria'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0);
        }
        if (($busca = trim((string) ($f['busca'] ?? ''))) !== '') {
            $q->where(fn ($w) => $w->where('c.titulo', 'like', "%{$busca}%")->orWhere('c.descricao', 'like', "%{$busca}%"));
        }

        $total = (clone $q)->count();
        $ordem = in_array($f['ordem'] ?? '', ['titulo', 'inicio_em', 'termino_em', 'created_at', 'prioridade'], true) ? $f['ordem'] : 'created_at';
        $direcao = strtolower((string) ($f['direcao'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $porPagina = max(5, min(100, (int) ($f['por_pagina'] ?? 20)));
        $pagina = max(1, (int) ($f['pagina'] ?? 1));

        $linhas = $q->orderBy("c.{$ordem}", $direcao)->orderByDesc('c.id')
            ->offset(($pagina - 1) * $porPagina)->limit($porPagina)->get()->map(fn ($r) => (array) $r)->all();
        $contagens = $this->contagens($tenantId, array_column($linhas, 'id'));

        return $this->ok([
            'comunicacoes' => array_map(fn ($c) => $this->formatar($c, $contagens[(int) $c['id']] ?? null, $agora), $linhas),
            'paginacao' => ['pagina' => $pagina, 'por_pagina' => $porPagina, 'total' => $total, 'paginas' => (int) ceil($total / $porPagina)],
        ]);
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function obter(int $tenantId, int $id): array
    {
        $c = $this->buscar($tenantId, $id);
        if (! $c) {
            return $this->erro('Comunicação não encontrada', 404);
        }

        return $this->ok(['comunicacao' => $this->formatar($c, $this->contagens($tenantId, [$id])[$id] ?? null) + ['perguntas' => $this->perguntas($tenantId, $id)]]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function criarComunicacao(int $tenantId, array $data, ?int $userId): array
    {
        [$campos, $perguntas, $erro] = $this->validar($tenantId, $data, null);
        if ($erro) {
            return $this->erro($erro, 422);
        }

        $id = DB::transaction(function () use ($tenantId, $campos, $perguntas, $userId) {
            $id = (int) DB::table('comunicacoes')->insertGetId($campos + [
                'tenant_id' => $tenantId, 'status' => 'rascunho', 'criado_por' => $userId, 'atualizado_por' => $userId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->salvarPerguntas($tenantId, $id, $perguntas);
            $this->auditar($tenantId, $id, 'criada', $userId);

            return $id;
        });

        if (! empty($data['publicar'])) {
            $pub = $this->publicarComunicacao($tenantId, $id, $userId);
            if ($pub['status'] !== 200) {
                return ['status' => $pub['status'], 'body' => $pub['body'] + ['comunicacao_id' => $id, 'salva_como_rascunho' => true]];
            }

            return ['status' => 201, 'body' => $pub['body']];
        }

        return ['status' => 201, 'body' => $this->obter($tenantId, $id)['body'] + ['message' => 'Rascunho salvo']];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function atualizarComunicacao(int $tenantId, int $id, array $data, ?int $userId): array
    {
        $atual = $this->buscar($tenantId, $id);
        if (! $atual) {
            return $this->erro('Comunicação não encontrada', 404);
        }
        if (in_array($atual['status'], ['cancelada'], true)) {
            return $this->erro('Comunicação cancelada não pode ser editada. Duplique para reaproveitar.', 409);
        }
        $rascunho = $atual['status'] === 'rascunho';
        if (! $rascunho) {
            // Campos fixos depois de publicada: mantém os gravados
            $data = array_intersect_key($data, array_flip(self::EDITAVEIS_PUBLICADA)) + [
                'tipo' => $atual['tipo'], 'inicio_em' => $atual['inicio_em'], 'publico' => json_decode((string) $atual['publico'], true),
            ];
            $data += array_intersect_key($atual, array_flip(self::EDITAVEIS_PUBLICADA));
            if (is_string($data['configuracoes'] ?? null)) {
                $data['configuracoes'] = json_decode($data['configuracoes'], true);
            }
        }

        [$campos, $perguntas, $erro] = $this->validar($tenantId, $data, $atual);
        if ($erro) {
            return $this->erro($erro, 422);
        }

        DB::transaction(function () use ($tenantId, $id, $campos, $perguntas, $rascunho, $userId, $atual) {
            DB::table('comunicacoes')->where('id', $id)->where('tenant_id', $tenantId)
                ->update($campos + ['atualizado_por' => $userId, 'updated_at' => now()]);
            if ($rascunho) {
                $this->salvarPerguntas($tenantId, $id, $perguntas);
            }
            $mudou = array_keys(array_filter($campos, fn ($v, $k) => (string) ($atual[$k] ?? '') !== (string) $v, ARRAY_FILTER_USE_BOTH));
            $this->auditar($tenantId, $id, 'editada', $userId, null, ['campos' => $mudou]);
        });

        return $this->ok($this->obter($tenantId, $id)['body'] + ['message' => 'Comunicação atualizada']);
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function publicarComunicacao(int $tenantId, int $id, ?int $userId): array
    {
        $c = $this->buscar($tenantId, $id);
        if (! $c) {
            return $this->erro('Comunicação não encontrada', 404);
        }
        if ($c['status'] !== 'rascunho') {
            return $this->erro('Só rascunhos podem ser publicados.', 409);
        }
        if ($c['tipo'] === 'enquete' && $this->perguntas($tenantId, $id) === []) {
            return $this->erro('A enquete precisa de ao menos uma pergunta.', 422);
        }
        $agora = AcademyDateTime::nowFormatted();
        if ($c['termino_em'] !== null && $c['termino_em'] <= $agora) {
            return $this->erro('A data de término já passou. Ajuste antes de publicar.', 422);
        }
        $alunos = $this->publicos->resolver($tenantId, json_decode((string) $c['publico'], true) ?: ['tipo' => 'todos']);
        if ($alunos === []) {
            return $this->erro('Nenhum aluno corresponde ao público escolhido.', 422);
        }

        DB::transaction(function () use ($tenantId, $id, $alunos, $userId, $agora) {
            $this->inserirDestinatarios($tenantId, $id, $alunos);
            DB::table('comunicacoes')->where('id', $id)->where('tenant_id', $tenantId)->update([
                'status' => 'publicada', 'publicada_em' => $agora, 'publicada_por' => $userId, 'updated_at' => now(),
            ]);
            $this->auditar($tenantId, $id, 'publicada', $userId, null, ['destinatarios' => count($alunos)]);
        });

        return $this->ok($this->obter($tenantId, $id)['body'] + ['message' => 'Comunicação publicada para '.count($alunos).' aluno(s)']);
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function incluirNovosDestinatarios(int $tenantId, int $id, ?int $userId): array
    {
        $c = $this->buscar($tenantId, $id);
        if (! $c) {
            return $this->erro('Comunicação não encontrada', 404);
        }
        if (! in_array(Comunicacao::statusEfetivo($c['status'], $c['inicio_em'], $c['termino_em']), ['agendada', 'ativa'], true)) {
            return $this->erro('Só comunicações agendadas ou ativas recebem novos alunos.', 409);
        }
        $alunos = $this->publicos->resolver($tenantId, json_decode((string) $c['publico'], true) ?: ['tipo' => 'todos']);
        $novos = DB::transaction(function () use ($tenantId, $id, $alunos, $userId) {
            $novos = $this->inserirDestinatarios($tenantId, $id, $alunos);
            $this->auditar($tenantId, $id, 'destinatarios_incluidos', $userId, null, ['novos' => $novos]);

            return $novos;
        });

        return $this->ok(['novos' => $novos, 'message' => $novos ? "{$novos} aluno(s) incluído(s)" : 'Nenhum aluno novo no público']);
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function encerrarComunicacao(int $tenantId, int $id, ?int $userId): array
    {
        return $this->finalizar($tenantId, $id, $userId, 'encerrada');
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function cancelarComunicacao(int $tenantId, int $id, ?int $userId): array
    {
        return $this->finalizar($tenantId, $id, $userId, 'cancelada');
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function excluir(int $tenantId, int $id, ?int $userId): array
    {
        $c = $this->buscar($tenantId, $id);
        if (! $c) {
            return $this->erro('Comunicação não encontrada', 404);
        }
        if ($c['status'] !== 'rascunho') {
            return $this->erro('Só rascunhos podem ser excluídos. Encerre ou cancele a comunicação publicada.', 409);
        }
        DB::table('comunicacoes')->where('id', $id)->where('tenant_id', $tenantId)->update(['deleted_at' => now(), 'atualizado_por' => $userId]);
        $this->auditar($tenantId, $id, 'excluida', $userId);

        return $this->ok(['message' => 'Rascunho excluído']);
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function duplicar(int $tenantId, int $id, ?int $userId): array
    {
        $c = $this->buscar($tenantId, $id);
        if (! $c) {
            return $this->erro('Comunicação não encontrada', 404);
        }
        $novoId = DB::transaction(function () use ($tenantId, $id, $c, $userId) {
            $copia = array_intersect_key($c, array_flip(['tipo', 'descricao', 'conteudo', 'icone', 'classificacao', 'prioridade', 'obrigatoria', 'exibir_modal', 'manter_historico', 'publico', 'configuracoes']));
            $novoId = (int) DB::table('comunicacoes')->insertGetId($copia + [
                'tenant_id' => $tenantId, 'titulo' => mb_substr($c['titulo'].' (cópia)', 0, 160), 'status' => 'rascunho',
                'criado_por' => $userId, 'atualizado_por' => $userId, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->salvarPerguntas($tenantId, $novoId, $this->perguntas($tenantId, $id));
            $this->auditar($tenantId, $novoId, 'criada', $userId, null, ['duplicada_de' => $id]);

            return $novoId;
        });

        return ['status' => 201, 'body' => $this->obter($tenantId, $novoId)['body'] + ['message' => 'Cópia criada como rascunho']];
    }

    /**
     * Quantos alunos o público alcança (pré-visualização do formulário).
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function previaPublico(int $tenantId, mixed $publico): array
    {
        [$p, $erro] = $this->publicos->normalizar($publico);
        if ($erro) {
            return $this->erro($erro, 422);
        }
        $ids = $this->publicos->resolver($tenantId, $p);
        $amostra = $ids === [] ? [] : DB::table('alunos as a')->join('usuarios as u', 'u.id', '=', 'a.usuario_id')
            ->whereIn('a.id', array_slice($ids, 0, 8))->orderBy('u.nome')->pluck('u.nome')->all();

        return $this->ok(['total' => count($ids), 'amostra' => $amostra]);
    }

    /**
     * Listas do formulário: modalidades, planos e turmas (modalidade + horário).
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function opcoes(int $tenantId): array
    {
        $modalidades = DB::table('modalidades')->where('tenant_id', $tenantId)->orderBy('nome')->get(['id', 'nome'])->map(fn ($r) => (array) $r)->all();
        $planos = DB::table('planos as p')->leftJoin('modalidades as m', 'm.id', '=', 'p.modalidade_id')
            ->where('p.tenant_id', $tenantId)->where('p.ativo', 1)->orderBy('m.nome')->orderBy('p.nome')
            ->get(['p.id', 'p.nome', 'p.modalidade_id', 'm.nome as modalidade_nome'])->map(fn ($r) => (array) $r)->all();
        $horarios = DB::table('turmas as t')->join('modalidades as m', 'm.id', '=', 't.modalidade_id')
            ->where('t.tenant_id', $tenantId)->where('t.ativo', 1)
            ->selectRaw("t.modalidade_id, m.nome as modalidade_nome, LEFT(CAST(t.horario_inicio AS CHAR), 5) as horario")
            ->groupBy('t.modalidade_id', 'm.nome', 'horario')->orderBy('m.nome')->orderBy('horario')
            ->get()->map(fn ($r) => ['modalidade_id' => (int) $r->modalidade_id, 'modalidade_nome' => $r->modalidade_nome, 'horario' => (string) $r->horario])->all();

        return $this->ok(['modalidades' => $modalidades, 'planos' => $planos, 'horarios' => $horarios, 'tipos' => Comunicacao::TIPOS]);
    }

    /* ---------------------------------------------------------------- internos */

    /** @return array{status: int, body: array<string, mixed>} */
    private function finalizar(int $tenantId, int $id, ?int $userId, string $status): array
    {
        $c = $this->buscar($tenantId, $id);
        if (! $c) {
            return $this->erro('Comunicação não encontrada', 404);
        }
        if (in_array($c['status'], ['encerrada', 'cancelada'], true) && ($status === 'encerrada' || $c['status'] === 'cancelada')) {
            return $this->ok($this->obter($tenantId, $id)['body'] + ['message' => 'Comunicação já estava '.$c['status']]);
        }
        if ($c['status'] === 'rascunho') {
            return $this->erro('Rascunho não precisa ser encerrado: exclua ou publique.', 409);
        }
        $agora = AcademyDateTime::nowFormatted();
        $termino = $c['termino_em'] !== null && $c['termino_em'] < $agora ? $c['termino_em'] : $agora;
        DB::table('comunicacoes')->where('id', $id)->where('tenant_id', $tenantId)->update([
            'status' => $status, 'encerrada_em' => $agora, 'termino_em' => $termino, 'atualizado_por' => $userId, 'updated_at' => now(),
        ]);
        $this->auditar($tenantId, $id, $status, $userId);

        return $this->ok($this->obter($tenantId, $id)['body'] + ['message' => $status === 'cancelada' ? 'Comunicação cancelada' : 'Comunicação encerrada']);
    }

    /**
     * @param  array<string, mixed>  $d
     * @param  array<string, mixed>|null  $atual
     * @return array{0: array<string, mixed>, 1: list<array<string, mixed>>, 2: string|null}
     */
    private function validar(int $tenantId, array $d, ?array $atual): array
    {
        $tipo = (string) ($d['tipo'] ?? '');
        if (! isset(Comunicacao::TIPOS[$tipo])) {
            return [[], [], 'Tipo inválido.'];
        }
        $titulo = trim((string) ($d['titulo'] ?? ''));
        if ($titulo === '' || mb_strlen($titulo) > 160) {
            return [[], [], 'Informe um título de até 160 caracteres.'];
        }
        $descricao = trim((string) ($d['descricao'] ?? '')) ?: null;
        if ($descricao !== null && mb_strlen($descricao) > 500) {
            return [[], [], 'A descrição aceita até 500 caracteres.'];
        }
        $conteudo = trim((string) ($d['conteudo'] ?? '')) ?: null;
        if ($conteudo !== null && mb_strlen($conteudo) > 20000) {
            return [[], [], 'O conteúdo aceita até 20.000 caracteres.'];
        }
        if ($tipo !== 'enquete' && $tipo !== 'notificacao' && $conteudo === null && $descricao === null) {
            return [[], [], 'Escreva o conteúdo da comunicação.'];
        }
        $classificacao = in_array($d['classificacao'] ?? 'info', Comunicacao::CLASSIFICACOES, true) ? $d['classificacao'] ?? 'info' : 'info';
        $prioridade = max(0, min(Comunicacao::PRIORIDADE_MAX, (int) ($d['prioridade'] ?? 1)));

        $inicio = $this->dataHora($d['inicio_em'] ?? null);
        $termino = $this->dataHora($d['termino_em'] ?? null);
        if ($inicio === false || $termino === false) {
            return [[], [], 'Data inválida. Use dia e hora.'];
        }
        if ($inicio !== null && $termino !== null && $termino <= $inicio) {
            return [[], [], 'O término precisa ser depois do início.'];
        }
        $obrigatoria = filter_var($d['obrigatoria'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($obrigatoria && $termino === null) {
            return [[], [], 'Comunicação obrigatória precisa de data de término (ela bloqueia o check-in até lá).'];
        }

        [$publico, $erroPublico] = $this->publicos->normalizar($d['publico'] ?? ['tipo' => 'todos']);
        if ($erroPublico) {
            return [[], [], $erroPublico];
        }
        [$config, $erroConfig] = $this->configuracoes($d['configuracoes'] ?? [], $tipo);
        if ($erroConfig) {
            return [[], [], $erroConfig];
        }

        $perguntas = [];
        if ($tipo === 'enquete' && ($atual === null || $atual['status'] === 'rascunho')) {
            [$perguntas, $erroPerguntas] = $this->validarPerguntas($d['perguntas'] ?? []);
            if ($erroPerguntas) {
                return [[], [], $erroPerguntas];
            }
        }

        $icone = preg_replace('/[^a-z0-9\-]/', '', strtolower((string) ($d['icone'] ?? ''))) ?: null;

        return [[
            'tipo' => $tipo, 'titulo' => $titulo, 'descricao' => $descricao, 'conteudo' => $conteudo,
            'icone' => $icone ? substr($icone, 0, 40) : null, 'classificacao' => $classificacao, 'prioridade' => $prioridade,
            'obrigatoria' => $obrigatoria ? 1 : 0,
            // Obrigatória sempre aparece em destaque no app
            'exibir_modal' => ($obrigatoria || filter_var($d['exibir_modal'] ?? false, FILTER_VALIDATE_BOOLEAN)) ? 1 : 0,
            'manter_historico' => filter_var($d['manter_historico'] ?? true, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
            'inicio_em' => $inicio, 'termino_em' => $termino,
            'publico' => json_encode($publico, JSON_UNESCAPED_UNICODE),
            'configuracoes' => json_encode($config, JSON_UNESCAPED_UNICODE),
        ], $perguntas, null];
    }

    /**
     * @return array{0: array<string, mixed>, 1: string|null}
     */
    private function configuracoes(mixed $c, string $tipo): array
    {
        $c = is_array($c) ? $c : [];
        $url = fn ($u) => is_string($u) && preg_match('#^https?://#i', trim($u)) && mb_strlen(trim($u)) <= 1000 ? trim($u) : null;
        $links = [];
        foreach (array_slice((array) ($c['links'] ?? []), 0, 10) as $l) {
            $u = $url($l['url'] ?? null);
            if (! $u) {
                return [[], 'Link inválido: use um endereço começando com http:// ou https://.'];
            }
            $links[] = ['rotulo' => mb_substr(trim((string) ($l['rotulo'] ?? '')) ?: $u, 0, 80), 'url' => $u];
        }
        $anexos = [];
        foreach (array_slice((array) ($c['anexos'] ?? []), 0, 10) as $a) {
            $u = $url($a['url'] ?? null);
            if (! $u) {
                return [[], 'Anexo inválido.'];
            }
            $anexos[] = ['nome' => mb_substr(trim((string) ($a['nome'] ?? '')) ?: 'Anexo', 0, 120), 'url' => $u, 'mime' => mb_substr((string) ($a['mime'] ?? ''), 0, 80) ?: null];
        }
        $imagem = isset($c['imagem_url']) && $c['imagem_url'] !== '' ? $url($c['imagem_url']) : null;
        if (isset($c['imagem_url']) && $c['imagem_url'] !== '' && ! $imagem) {
            return [[], 'Imagem inválida.'];
        }
        $out = ['links' => $links, 'anexos' => $anexos, 'imagem_url' => $imagem, 'canais' => ['app']];
        if ($tipo === 'enquete') {
            $e = (array) ($c['enquete'] ?? []);
            $anonima = filter_var($e['anonima'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $out['enquete'] = [
                'anonima' => $anonima,
                // Sem vínculo com o aluno não há como achar a resposta anterior para trocar
                'permitir_alterar' => ! $anonima && filter_var($e['permitir_alterar'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'exibir_resultados' => filter_var($e['exibir_resultados'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'exibir_participantes' => filter_var($e['exibir_participantes'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ];
        }

        return [$out, null];
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: string|null}
     */
    private function validarPerguntas(mixed $lista): array
    {
        $lista = array_values(array_filter((array) $lista, 'is_array'));
        if ($lista === []) {
            return [[], 'Adicione ao menos uma pergunta à enquete.'];
        }
        if (count($lista) > 30) {
            return [[], 'A enquete aceita até 30 perguntas.'];
        }
        $out = [];
        foreach ($lista as $i => $p) {
            $n = $i + 1;
            $texto = trim((string) ($p['pergunta'] ?? ''));
            if ($texto === '' || mb_strlen($texto) > 300) {
                return [[], "Pergunta {$n}: escreva o texto (até 300 caracteres)."];
            }
            $tipo = (string) ($p['tipo_resposta'] ?? '');
            if (! in_array($tipo, Comunicacao::TIPOS_RESPOSTA, true)) {
                return [[], "Pergunta {$n}: tipo de resposta inválido."];
            }
            $alternativas = [];
            if (in_array($tipo, ['unica', 'multipla'], true)) {
                foreach ((array) ($p['alternativas'] ?? []) as $a) {
                    $t = trim((string) (is_array($a) ? ($a['texto'] ?? '') : $a));
                    if ($t !== '') {
                        $alternativas[] = mb_substr($t, 0, 200);
                    }
                }
                if (count($alternativas) < 2 || count($alternativas) > 20) {
                    return [[], "Pergunta {$n}: informe de 2 a 20 alternativas."];
                }
            }
            $out[] = ['pergunta' => $texto, 'tipo_resposta' => $tipo, 'obrigatoria' => filter_var($p['obrigatoria'] ?? true, FILTER_VALIDATE_BOOLEAN), 'alternativas' => $alternativas];
        }

        return [$out, null];
    }

    /** @param  list<array<string, mixed>>  $perguntas */
    private function salvarPerguntas(int $tenantId, int $comunicacaoId, array $perguntas): void
    {
        DB::table('enquete_perguntas')->where('tenant_id', $tenantId)->where('comunicacao_id', $comunicacaoId)->delete();
        foreach (array_values($perguntas) as $ordem => $p) {
            $pid = (int) DB::table('enquete_perguntas')->insertGetId([
                'tenant_id' => $tenantId, 'comunicacao_id' => $comunicacaoId, 'pergunta' => $p['pergunta'],
                'tipo_resposta' => $p['tipo_resposta'], 'obrigatoria' => $p['obrigatoria'] ? 1 : 0, 'ordem' => $ordem,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach (array_values($p['alternativas'] ?? []) as $i => $a) {
                DB::table('enquete_alternativas')->insert(['tenant_id' => $tenantId, 'pergunta_id' => $pid, 'texto' => is_array($a) ? $a['texto'] : $a, 'ordem' => $i]);
            }
        }
    }

    /** @return list<array<string, mixed>> */
    public function perguntas(int $tenantId, int $comunicacaoId): array
    {
        $perguntas = DB::table('enquete_perguntas')->where('tenant_id', $tenantId)->where('comunicacao_id', $comunicacaoId)
            ->orderBy('ordem')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
        if ($perguntas === []) {
            return [];
        }
        $alts = DB::table('enquete_alternativas')->where('tenant_id', $tenantId)->whereIn('pergunta_id', array_column($perguntas, 'id'))
            ->orderBy('ordem')->orderBy('id')->get()->groupBy('pergunta_id');

        return array_map(fn ($p) => [
            'id' => (int) $p['id'], 'pergunta' => $p['pergunta'], 'tipo_resposta' => $p['tipo_resposta'], 'obrigatoria' => (bool) $p['obrigatoria'], 'ordem' => (int) $p['ordem'],
            'alternativas' => collect($alts[$p['id']] ?? [])->map(fn ($a) => ['id' => (int) $a->id, 'texto' => $a->texto])->values()->all(),
        ], $perguntas);
    }

    /**
     * @param  list<int>  $alunos
     * @return int novas linhas
     */
    private function inserirDestinatarios(int $tenantId, int $comunicacaoId, array $alunos): int
    {
        $agora = AcademyDateTime::nowFormatted();
        $novos = 0;
        foreach (array_chunk($alunos, 500) as $lote) {
            $novos += DB::table('comunicacao_destinatarios')->insertOrIgnore(array_map(fn ($a) => [
                'tenant_id' => $tenantId, 'comunicacao_id' => $comunicacaoId, 'aluno_id' => $a,
                'disponibilizada_em' => $agora, 'created_at' => now(), 'updated_at' => now(),
            ], $lote));
        }

        return $novos;
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array{destinatarios: int, concluidas: int, visualizadas: int}>
     */
    private function contagens(int $tenantId, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return DB::table('comunicacao_destinatarios as d')->join('comunicacoes as c', 'c.id', '=', 'd.comunicacao_id')
            ->where('d.tenant_id', $tenantId)->whereIn('d.comunicacao_id', $ids)
            ->groupBy('d.comunicacao_id')
            ->selectRaw("d.comunicacao_id, COUNT(*) as destinatarios,
                SUM(CASE WHEN (c.tipo = 'enquete' AND d.concluida_em IS NOT NULL) OR (c.tipo <> 'enquete' AND d.confirmada_em IS NOT NULL) THEN 1 ELSE 0 END) as concluidas,
                SUM(CASE WHEN d.visualizada_em IS NOT NULL THEN 1 ELSE 0 END) as visualizadas")
            ->get()->mapWithKeys(fn ($r) => [(int) $r->comunicacao_id => [
                'destinatarios' => (int) $r->destinatarios, 'concluidas' => (int) $r->concluidas, 'visualizadas' => (int) $r->visualizadas,
            ]])->all();
    }

    /**
     * @param  array<string, mixed>  $c
     * @param  array{destinatarios: int, concluidas: int, visualizadas: int}|null  $n
     * @return array<string, mixed>
     */
    private function formatar(array $c, ?array $n, ?string $agora = null): array
    {
        $n ??= ['destinatarios' => 0, 'concluidas' => 0, 'visualizadas' => 0];

        return [
            'id' => (int) $c['id'], 'tipo' => $c['tipo'], 'tipo_nome' => Comunicacao::TIPOS[$c['tipo']] ?? $c['tipo'],
            'titulo' => $c['titulo'], 'descricao' => $c['descricao'], 'conteudo' => $c['conteudo'], 'icone' => $c['icone'],
            'classificacao' => $c['classificacao'], 'prioridade' => (int) $c['prioridade'],
            'obrigatoria' => (bool) $c['obrigatoria'], 'exibir_modal' => (bool) $c['exibir_modal'], 'manter_historico' => (bool) $c['manter_historico'],
            'inicio_em' => $c['inicio_em'], 'termino_em' => $c['termino_em'],
            'status' => Comunicacao::statusEfetivo($c['status'], $c['inicio_em'], $c['termino_em'], $agora), 'status_gravado' => $c['status'],
            'publico' => json_decode((string) $c['publico'], true) ?: ['tipo' => 'todos'],
            'configuracoes' => json_decode((string) $c['configuracoes'], true) ?: [],
            'acao_exigida' => Comunicacao::acaoExigida($c['tipo']),
            'publicada_em' => $c['publicada_em'], 'encerrada_em' => $c['encerrada_em'], 'created_at' => $c['created_at'],
            'destinatarios' => $n['destinatarios'], 'leituras' => $n['concluidas'], 'visualizadas' => $n['visualizadas'],
            'pendentes' => max(0, $n['destinatarios'] - $n['concluidas']),
        ];
    }

    /** @return array<string, mixed>|null */
    private function buscar(int $tenantId, int $id): ?array
    {
        $r = DB::table('comunicacoes')->where('id', $id)->where('tenant_id', $tenantId)->whereNull('deleted_at')->first();

        return $r ? (array) $r : null;
    }

    /** "Y-m-d H:i[:s]" ou "Y-m-dTH:i" no fuso da academia; null vazio; false inválido. */
    private function dataHora(mixed $v): string|false|null
    {
        if ($v === null || $v === '') {
            return null;
        }
        $s = str_replace('T', ' ', trim((string) $v));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
            $s .= ' 00:00';
        }
        foreach (['Y-m-d H:i:s', 'Y-m-d H:i'] as $fmt) {
            $d = \DateTime::createFromFormat('!'.$fmt, $s, AcademyDateTime::tz());
            if ($d && $d->format($fmt) === $s) {
                return $d->format('Y-m-d H:i:s');
            }
        }

        return false;
    }

    /** @param  array<string, mixed>|null  $dados */
    public function auditar(int $tenantId, int $comunicacaoId, string $acao, ?int $usuarioId, ?int $alunoId = null, ?array $dados = null): void
    {
        DB::table('comunicacao_auditoria')->insert([
            'tenant_id' => $tenantId, 'comunicacao_id' => $comunicacaoId, 'acao' => $acao, 'usuario_id' => $usuarioId,
            'aluno_id' => $alunoId, 'dados' => $dados ? json_encode($dados, JSON_UNESCAPED_UNICODE) : null, 'created_at' => now(),
        ]);
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function ok(array $body): array
    {
        return ['status' => 200, 'body' => ['success' => true] + $body];
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function erro(string $mensagem, int $status): array
    {
        return ['status' => $status, 'body' => ['success' => false, 'error' => $mensagem, 'message' => $mensagem]];
    }
}
