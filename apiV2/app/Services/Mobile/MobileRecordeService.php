<?php

namespace App\Services\Mobile;

use App\Repositories\RecordePessoalRepository;
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
     * @return array{id: int, nome: string, sexo: ?string}|null
     */
    private function aluno(int $userId): ?array
    {
        $cols = ['id', 'nome'];
        if (SexoAluno::colunaExiste()) {
            $cols[] = 'sexo';
        }
        $row = DB::table('alunos')->where('usuario_id', $userId)->orderBy('id')->first($cols);

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
            ->where('r.tenant_id', $tenantId)
            ->where('r.valido', 1)
            ->whereIn('r.definicao_id', array_keys($principal))
            ->orderBy('r.data_recorde')
            ->orderBy('r.id')
            ->get(array_merge(
                ['r.id', 'r.definicao_id', 'r.aluno_id', 'r.data_recorde', 'r.origem', 'rv.metrica_id', 'rv.valor_int', 'rv.valor_decimal', 'rv.valor_tempo_ms', 'a.nome as aluno_nome'],
                SexoAluno::colunaExiste() ? ['a.sexo'] : [],
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
