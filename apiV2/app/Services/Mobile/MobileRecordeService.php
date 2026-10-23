<?php

namespace App\Services\Mobile;

use App\Repositories\RecordePessoalRepository;
use App\Support\Pessoa;
use App\Support\SexoAluno;
use Illuminate\Support\Facades\DB;

/**
 * Recordes no app do aluno: melhores marcas dele, recordista de cada prova e ranking.
 * Cada definição é comparada pela métrica principal (menor ordem_comparacao), na direção configurada.
 * Conta recordes válidos de qualquer origem (aluno ou registrado pela escola).
 */
class MobileRecordeService
{
    private const SEXOS = [SexoAluno::MASCULINO, SexoAluno::FEMININO];

    public function __construct(
        private readonly RecordePessoalRepository $recordes,
    ) {}

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function resumo(int $userId, ?int $tenantId): array
    {
        if (! $tenantId) {
            return ['status' => 400, 'body' => ['success' => false, 'error' => 'Nenhum tenant selecionado']];
        }

        $aluno = $this->aluno($userId);
        $definicoes = $this->definicoes($tenantId);
        $marcas = $this->marcas($tenantId, array_map(fn ($d) => $d['metrica_id'], $definicoes));

        $meus = [];
        $escola = ['geral' => [], SexoAluno::MASCULINO => [], SexoAluno::FEMININO => []];
        foreach ($definicoes as $defId => $def) {
            $lista = $marcas[$defId] ?? [];
            $ranking = $this->melhoresPorAluno($lista, $def['direcao']);

            $lider = $this->lider($lista, $def['direcao']);
            if ($lider) {
                $escola['geral'][] = $this->recordista($defId, $lider, $aluno);
            }
            foreach (self::SEXOS as $sexo) {
                $top = array_values(array_filter($ranking, fn ($m) => $m['sexo'] === $sexo))[0] ?? null;
                if ($top) {
                    $escola[$sexo][] = $this->recordista($defId, $top, $aluno);
                }
            }

            if ($aluno) {
                $minhas = array_values(array_filter($lista, fn ($m) => (int) $m['aluno_id'] === $aluno['id']));
                $meus[] = $this->minhaProva($defId, $minhas, $ranking, $aluno['id'], $def['direcao']);
            }
        }

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'aluno' => $aluno,
                'definicoes' => array_values(array_map(fn ($d) => array_diff_key($d, ['metrica_id' => 1]), $definicoes)),
                'meus' => $meus,
                'escola' => $escola,
            ],
        ];
    }

    /**
     * Melhor marca de cada aluno numa definição, da melhor para a pior.
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function ranking(int $userId, ?int $tenantId, int $definicaoId, ?string $sexo, int $limite = 50): array
    {
        if (! $tenantId) {
            return ['status' => 400, 'body' => ['success' => false, 'error' => 'Nenhum tenant selecionado']];
        }

        $definicoes = $this->definicoes($tenantId);
        $def = $definicoes[$definicaoId] ?? null;
        if (! $def) {
            return ['status' => 404, 'body' => ['success' => false, 'error' => 'Prova não encontrada']];
        }

        $sexo = in_array($sexo, self::SEXOS, true) ? $sexo : null;
        $aluno = $this->aluno($userId);
        $ranking = $this->melhoresPorAluno($this->marcas($tenantId, [$definicaoId => $def['metrica_id']])[$definicaoId] ?? [], $def['direcao']);
        if ($sexo) {
            $ranking = array_values(array_filter($ranking, fn ($m) => $m['sexo'] === $sexo));
        }

        $lista = [];
        foreach ($ranking as $i => $m) {
            $self = $aluno && (int) $m['aluno_id'] === $aluno['id'];
            if ($i >= $limite && ! $self) {
                continue;
            }
            $lista[] = [
                'posicao' => $i + 1,
                'aluno_id' => (int) $m['aluno_id'],
                'nome' => $m['nome'],
                'foto_caminho' => $m['foto_caminho'],
                'valor' => $m['valor'],
                'data' => $m['data'],
                'self' => $self,
            ];
        }

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'definicao' => array_diff_key($def, ['metrica_id' => 1]),
                'sexo' => $sexo,
                'total' => count($ranking),
                'ranking' => $lista,
            ],
        ];
    }

    /**
     * Uma prova do aluno: métricas da definição e todas as marcas dele, da mais recente para a mais antiga.
     * Só as marcas que ele mesmo registrou (origem aluno) são editáveis; as da escola aparecem como leitura.
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function prova(int $userId, ?int $tenantId, int $definicaoId): array
    {
        if (! $tenantId) {
            return $this->erro('Nenhum tenant selecionado', 400);
        }
        $def = $this->definicoes($tenantId)[$definicaoId] ?? null;
        $full = $def ? $this->recordes->buscarDefinicao($definicaoId, $tenantId) : null;
        if (! $def || ! $full) {
            return $this->erro('Prova não encontrada', 404);
        }

        $aluno = $this->aluno($userId);
        $marcas = [];
        if ($aluno) {
            foreach ($this->recordes->listarRecordes($tenantId, $definicaoId, null, null, $aluno['id']) as $r) {
                $valores = [];
                foreach ($r['valores'] ?? [] as $v) {
                    $valor = $v['valor_tempo_ms'] ?? $v['valor_decimal'] ?? $v['valor_int'];
                    if ($valor !== null && is_numeric($valor)) {
                        $valores[(string) $v['metrica_id']] = (float) $valor;
                    }
                }
                $principal = $valores[(string) $def['metrica_id']] ?? null;
                if ($principal === null) {
                    continue;
                }
                $marcas[] = [
                    'id' => (int) $r['id'],
                    'data' => substr((string) $r['data_recorde'], 0, 10),
                    'valor' => $principal,
                    'valores' => $valores,
                    'observacoes' => $r['observacoes'] ?? null,
                    'origem' => (string) $r['origem'],
                    'editavel' => $r['origem'] === 'aluno',
                ];
            }
        }
        usort($marcas, fn ($a, $b) => [$b['data'], $b['id']] <=> [$a['data'], $a['id']]);

        // Empate: vale a mais antiga (a lista está da mais recente para a mais antiga).
        $melhor = null;
        foreach ($marcas as $m) {
            if (! $melhor || ! $this->melhor($melhor['valor'], $m['valor'], $def['direcao'])) {
                $melhor = $m;
            }
        }

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'definicao' => array_diff_key($def, ['metrica_id' => 1]) + ['metrica_principal_id' => $def['metrica_id']],
                'metricas' => $this->metricasPublicas($full),
                'pode_registrar' => $aluno !== null,
                'marcas' => $marcas,
                'melhor_id' => $melhor['id'] ?? null,
            ],
        ];
    }

    /**
     * Registra uma marca do próprio aluno. Diz se bateu o melhor anterior dele na prova.
     *
     * @param  array<string, mixed>  $data  definicao_id, data_recorde (Y-m-d), observacoes?, valores [{metrica_id, valor}]
     * @return array{status: int, body: array<string, mixed>}
     */
    public function criar(int $userId, ?int $tenantId, array $data): array
    {
        if (! $tenantId) {
            return $this->erro('Nenhum tenant selecionado', 400);
        }
        $aluno = $this->aluno($userId);
        if (! $aluno) {
            return $this->erro('Seu cadastro de aluno não foi encontrado', 404);
        }
        $definicaoId = filter_var($data['definicao_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($definicaoId === false) {
            return $this->erro('Selecione uma prova válida', 422);
        }
        $def = $this->definicoes($tenantId)[$definicaoId] ?? null;
        $full = $def ? $this->recordes->buscarDefinicao($definicaoId, $tenantId) : null;
        if (! $def || ! $full) {
            return $this->erro('Prova não encontrada', 404);
        }

        $validado = $this->validar($full, $data);
        if (isset($validado['erro'])) {
            return $this->erro($validado['erro'], 422);
        }

        $anterior = $this->melhorDoAluno($tenantId, $def, $aluno['id']);
        $id = $this->recordes->criar([
            'tenant_id' => $tenantId,
            'aluno_id' => $aluno['id'],
            'definicao_id' => $definicaoId,
            'origem' => 'aluno',
            'data_recorde' => $validado['data'],
            'observacoes' => $validado['observacoes'],
            'registrado_por' => $userId,
            'valores' => $validado['valores'],
        ]);
        $valor = $validado['principal'][$def['metrica_id']] ?? null;

        return [
            'status' => 201,
            'body' => [
                'success' => true,
                'message' => 'Marca registrada',
                'id' => $id,
                'valor' => $valor,
                'melhor_anterior' => $anterior,
                'novo_recorde' => $valor !== null && ($anterior === null || $this->melhor($valor, $anterior, $def['direcao'])),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data  data_recorde, observacoes?, valores
     * @return array{status: int, body: array<string, mixed>}
     */
    public function atualizar(int $userId, ?int $tenantId, int $id, array $data): array
    {
        $alvo = $this->minhaMarca($userId, $tenantId, $id);
        if (isset($alvo['status'])) {
            return $alvo;
        }
        $full = $this->recordes->buscarDefinicao((int) $alvo['definicao_id'], (int) $tenantId);
        if (! $full) {
            return $this->erro('Prova não encontrada', 404);
        }

        $validado = $this->validar($full, $data);
        if (isset($validado['erro'])) {
            return $this->erro($validado['erro'], 422);
        }

        $this->recordes->atualizar($id, (int) $tenantId, [
            'definicao_id' => (int) $alvo['definicao_id'],
            'data_recorde' => $validado['data'],
            'observacoes' => $validado['observacoes'],
            'valores' => $validado['valores'],
        ]);

        return ['status' => 200, 'body' => ['success' => true, 'message' => 'Marca atualizada', 'id' => $id]];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function excluir(int $userId, ?int $tenantId, int $id): array
    {
        $alvo = $this->minhaMarca($userId, $tenantId, $id);
        if (isset($alvo['status'])) {
            return $alvo;
        }
        if (! $this->recordes->excluir($id, (int) $tenantId)) {
            return $this->erro('Marca não encontrada', 404);
        }

        return ['status' => 200, 'body' => ['success' => true, 'message' => 'Marca excluída']];
    }

    /**
     * Marca registrada pelo próprio aluno nesta academia; senão, a resposta de erro.
     *
     * @return array<string, mixed>
     */
    private function minhaMarca(int $userId, ?int $tenantId, int $id): array
    {
        if (! $tenantId) {
            return $this->erro('Nenhum tenant selecionado', 400);
        }
        $aluno = $this->aluno($userId);
        $r = $aluno ? $this->recordes->buscar($id, $tenantId) : null;
        if (! $r || ! $r['valido'] || (int) $r['aluno_id'] !== $aluno['id']) {
            return $this->erro('Marca não encontrada', 404);
        }
        if ($r['origem'] !== 'aluno') {
            return $this->erro('Marcas registradas pela escola só podem ser alteradas por ela', 403);
        }

        return $r;
    }

    /**
     * Valida data, observação e valores contra as métricas da definição.
     *
     * @param  array<string, mixed>  $def  definição com metricas
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function validar(array $def, array $data): array
    {
        if (! is_string($data['data_recorde'] ?? null)) {
            return ['erro' => 'Informe a data no formato AAAA-MM-DD'];
        }
        $data_ = $data['data_recorde'];
        $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $data_);
        if (! $dt || $dt->format('Y-m-d') !== $data_) {
            return ['erro' => 'Informe a data no formato AAAA-MM-DD'];
        }
        if ($data_ > now()->format('Y-m-d')) {
            return ['erro' => 'A data não pode ser no futuro'];
        }

        if (isset($data['observacoes']) && ! is_string($data['observacoes'])) {
            return ['erro' => 'A observação deve ser um texto'];
        }
        $obs = trim($data['observacoes'] ?? '');
        if (mb_strlen($obs) > 500) {
            return ['erro' => 'A observação pode ter até 500 caracteres'];
        }

        if (! is_array($data['valores'] ?? null) || ! array_is_list($data['valores'])) {
            return ['erro' => 'Informe os valores das métricas'];
        }
        $metricas = $def['metricas'] ?? [];
        usort($metricas, fn ($a, $b) => [(int) $a['ordem_comparacao'], (int) $a['id']] <=> [(int) $b['ordem_comparacao'], (int) $b['id']]);
        $ids = array_column($metricas, 'id');
        $enviados = [];
        foreach ($data['valores'] as $v) {
            $mid = is_array($v) ? filter_var($v['metrica_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
            if ($mid === false || ! in_array($mid, $ids, false) || array_key_exists($mid, $enviados)) {
                return ['erro' => 'As métricas devem pertencer à prova e não podem se repetir'];
            }
            $enviados[$mid] = $v['valor'] ?? null;
        }

        $valores = [];
        $principal = [];
        foreach ($metricas as $i => $m) {
            $mid = (int) $m['id'];
            $bruto = $enviados[$mid] ?? null;
            if ($bruto === null || $bruto === '') {
                if ($i === 0 || (int) ($m['obrigatoria'] ?? 1) === 1) {
                    return ['erro' => 'Informe '.mb_strtolower((string) $m['nome'])];
                }

                continue;
            }
            if (! is_numeric($bruto) || ! is_finite((float) $bruto) || (float) $bruto <= 0) {
                return ['erro' => 'Valor inválido em '.mb_strtolower((string) $m['nome'])];
            }
            $n = (float) $bruto;
            $inteiro = in_array((string) $m['tipo_valor'], ['tempo_ms', 'inteiro'], true);
            if (($inteiro && (floor($n) !== $n || $n > 9007199254740991))
                || (! $inteiro && ($n > 999999999.999 || round($n, 3) !== $n))) {
                return ['erro' => 'Valor fora do limite ou precisão inválida em '.mb_strtolower((string) $m['nome'])];
            }
            $linha = ['metrica_id' => $mid];
            match ((string) $m['tipo_valor']) {
                'tempo_ms' => $linha['valor_tempo_ms'] = (int) round($n),
                'inteiro' => $linha['valor_int'] = (int) round($n),
                default => $linha['valor_decimal'] = round($n, 3),
            };
            $valores[] = $linha;
            $principal[$mid] = (float) ($linha['valor_tempo_ms'] ?? $linha['valor_int'] ?? $linha['valor_decimal']);
        }
        if ($valores === []) {
            return ['erro' => 'Informe pelo menos um valor'];
        }

        return ['data' => $data_, 'observacoes' => $obs !== '' ? $obs : null, 'valores' => $valores, 'principal' => $principal];
    }

    /**
     * @param  array<string, mixed>  $def  definição resumida (com metrica_id)
     */
    private function melhorDoAluno(int $tenantId, array $def, int $alunoId): ?float
    {
        $minhas = array_values(array_filter(
            $this->marcas($tenantId, [$def['id'] => $def['metrica_id']])[$def['id']] ?? [],
            fn ($m) => $m['aluno_id'] === $alunoId,
        ));

        return $this->lider($minhas, $def['direcao'])['valor'] ?? null;
    }

    /**
     * @param  array<string, mixed>  $full
     * @return list<array<string, mixed>>
     */
    private function metricasPublicas(array $full): array
    {
        $metricas = $full['metricas'] ?? [];
        usort($metricas, fn ($a, $b) => [(int) $a['ordem_comparacao'], (int) $a['id']] <=> [(int) $b['ordem_comparacao'], (int) $b['id']]);

        return array_map(fn ($m) => [
            'id' => (int) $m['id'],
            'nome' => (string) $m['nome'],
            'tipo_valor' => (string) $m['tipo_valor'],
            'unidade' => $m['unidade'] ?? null,
            'direcao' => $m['direcao'] === 'menor_melhor' ? 'menor_melhor' : 'maior_melhor',
            'obrigatoria' => (int) ($m['obrigatoria'] ?? 1) === 1,
        ], $metricas);
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    private function erro(string $mensagem, int $status): array
    {
        return ['status' => $status, 'body' => ['success' => false, 'error' => $mensagem]];
    }

    /**
     * @return array{id: int, nome: string, sexo: ?string}|null
     */
    private function aluno(int $userId): ?array
    {
        $row = DB::table('alunos as a')->leftJoin('usuarios as u', 'u.id', '=', 'a.usuario_id')
            ->where('a.usuario_id', $userId)->orderBy('a.id')->first(['a.id', 'a.nome', Pessoa::select('sexo')]);

        return $row ? ['id' => (int) $row->id, 'nome' => (string) $row->nome, 'sexo' => SexoAluno::normalizar($row->sexo ?? null)] : null;
    }

    /**
     * Definições ativas com a métrica principal. Sem métrica não há o que comparar: fica de fora.
     *
     * @return array<int, array<string, mixed>>
     */
    private function definicoes(int $tenantId): array
    {
        $out = [];
        foreach ($this->recordes->listarDefinicoes($tenantId, true) as $d) {
            $metricas = $d['metricas'] ?? [];
            usort($metricas, fn ($a, $b) => [(int) $a['ordem_comparacao'], (int) $a['id']] <=> [(int) $b['ordem_comparacao'], (int) $b['id']]);
            $m = $metricas[0] ?? null;
            if (! $m) {
                continue;
            }
            $out[(int) $d['id']] = [
                'id' => (int) $d['id'],
                'nome' => (string) $d['nome'],
                'categoria' => (string) ($d['categoria'] ?? ''),
                'modalidade_id' => $d['modalidade_id'] !== null ? (int) $d['modalidade_id'] : null,
                'modalidade_nome' => $d['modalidade_nome'] ?? null,
                'ordem' => (int) ($d['ordem'] ?? 0),
                'metrica_id' => (int) $m['id'],
                'metrica_nome' => (string) $m['nome'],
                'tipo_valor' => (string) $m['tipo_valor'],
                'unidade' => $m['unidade'] ?? null,
                'direcao' => $m['direcao'] === 'menor_melhor' ? 'menor_melhor' : 'maior_melhor',
            ];
        }

        return $out;
    }

    /**
     * Marcas válidas (valor da métrica principal) por definição, em ordem de data.
     *
     * @param  array<int, int>  $principal  definicao_id => id da métrica principal
     * @return array<int, list<array<string, mixed>>>
     */
    private function marcas(int $tenantId, array $principal): array
    {
        if ($principal === []) {
            return [];
        }

        $rows = DB::table('recordes as r')
            ->join('recorde_valores as rv', 'rv.recorde_id', '=', 'r.id')
            ->join('recorde_definicao_metricas as rdm', function ($j) {
                $j->on('rdm.id', '=', 'rv.metrica_id')->on('rdm.definicao_id', '=', 'r.definicao_id');
            })
            ->leftJoin('alunos as a', 'a.id', '=', 'r.aluno_id')
            ->leftJoin('usuarios as u', 'u.id', '=', 'a.usuario_id')
            ->where('r.tenant_id', $tenantId)
            ->where('r.valido', 1)
            ->whereIn('r.definicao_id', array_keys($principal))
            ->orderBy('r.data_recorde')
            ->orderBy('r.id')
            ->get(array_merge(
                ['r.id', 'r.definicao_id', 'r.aluno_id', 'r.data_recorde', 'r.origem', 'rv.metrica_id', 'rv.valor_int', 'rv.valor_decimal', 'rv.valor_tempo_ms', 'a.nome as aluno_nome', Pessoa::select('foto_caminho'), Pessoa::select('sexo')],
            ));

        $out = [];
        foreach ($rows as $r) {
            $defId = (int) $r->definicao_id;
            if (($principal[$defId] ?? null) !== (int) $r->metrica_id) {
                continue;
            }
            $valor = $r->valor_tempo_ms ?? $r->valor_decimal ?? $r->valor_int;
            if ($valor === null || ! is_numeric($valor)) {
                continue;
            }
            $out[$defId][] = [
                'id' => (int) $r->id,
                'aluno_id' => $r->aluno_id !== null ? (int) $r->aluno_id : null,
                'nome' => $r->aluno_id !== null ? (string) ($r->aluno_nome ?? 'Aluno #'.$r->aluno_id) : 'Marca da academia',
                'sexo' => SexoAluno::normalizar($r->sexo ?? null),
                'foto_caminho' => $r->foto_caminho,
                'valor' => (float) $valor,
                'data' => substr((string) $r->data_recorde, 0, 10),
            ];
        }

        return $out;
    }

    private function melhor(float $a, float $b, string $direcao): bool
    {
        return $direcao === 'menor_melhor' ? $a < $b : $a > $b;
    }

    /**
     * Melhor marca de qualquer origem (inclui a marca da academia, sem aluno). Empate: a mais antiga.
     *
     * @param  list<array<string, mixed>>  $lista
     * @return array<string, mixed>|null
     */
    private function lider(array $lista, string $direcao): ?array
    {
        $best = null;
        foreach ($lista as $m) {
            if (! $best || $this->melhor($m['valor'], $best['valor'], $direcao)) {
                $best = $m;
            }
        }

        return $best;
    }

    /**
     * @param  list<array<string, mixed>>  $lista
     * @return list<array<string, mixed>>
     */
    private function melhoresPorAluno(array $lista, string $direcao): array
    {
        $best = [];
        foreach ($lista as $m) {
            if ($m['aluno_id'] === null) {
                continue;
            }
            $k = $m['aluno_id'];
            if (! isset($best[$k]) || $this->melhor($m['valor'], $best[$k]['valor'], $direcao)) {
                $best[$k] = $m;
            }
        }
        $best = array_values($best);
        usort($best, function ($a, $b) use ($direcao) {
            if ($a['valor'] === $b['valor']) {
                return [$a['data'], $a['id']] <=> [$b['data'], $b['id']];
            }

            return $this->melhor($a['valor'], $b['valor'], $direcao) ? -1 : 1;
        });

        return $best;
    }

    /**
     * @param  array<string, mixed>  $m
     * @param  array<string, mixed>|null  $aluno
     * @return array<string, mixed>
     */
    private function recordista(int $defId, array $m, ?array $aluno): array
    {
        return [
            'definicao_id' => $defId,
            'aluno_id' => $m['aluno_id'],
            'nome' => $m['nome'],
            'valor' => $m['valor'],
            'data' => $m['data'],
            'self' => $aluno !== null && $m['aluno_id'] === $aluno['id'],
        ];
    }

    /**
     * Melhor marca do aluno, a melhor anterior a ela (para "x s mais rápido") e o histórico.
     *
     * @param  list<array<string, mixed>>  $minhas  em ordem de data
     * @param  list<array<string, mixed>>  $ranking
     * @return array<string, mixed>
     */
    private function minhaProva(int $defId, array $minhas, array $ranking, int $alunoId, string $direcao): array
    {
        $melhor = $this->lider($minhas, $direcao);
        if (! $melhor) {
            return ['definicao_id' => $defId, 'valor' => null, 'data' => null, 'anterior' => null, 'historico' => [], 'posicao' => null, 'total' => count($ranking)];
        }

        $antes = array_values(array_filter($minhas, fn ($m) => [$m['data'], $m['id']] < [$melhor['data'], $melhor['id']]));
        $anterior = $this->lider($antes, $direcao);
        $posicao = null;
        foreach ($ranking as $i => $m) {
            if ($m['aluno_id'] === $alunoId) {
                $posicao = $i + 1;
                break;
            }
        }

        return [
            'definicao_id' => $defId,
            'valor' => $melhor['valor'],
            'data' => $melhor['data'],
            'anterior' => $anterior['valor'] ?? null,
            'historico' => array_slice(array_map(fn ($m) => $m['valor'], $minhas), -12),
            'posicao' => $posicao,
            'total' => count($ranking),
        ];
    }
}
