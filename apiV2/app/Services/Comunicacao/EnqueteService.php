<?php

namespace App\Services\Comunicacao;

use App\Support\AcademyDateTime;
use App\Support\Comunicacao;
use Illuminate\Support\Facades\DB;

/**
 * Respostas de enquete. A enquete só conta como respondida (destinatário.concluida_em) quando o envio
 * passa por inteiro na validação: marcar uma alternativa no app sem enviar não grava nada.
 *
 * Anônima: as respostas não guardam aluno nem horário; a participação fica só no destinatário. Por isso
 * não dá para trocar a resposta depois (permitir_alterar é desligado no cadastro).
 */
class EnqueteService
{
    public function __construct(
        private readonly ComunicacaoService $comunicacoes,
    ) {}

    /**
     * @param  mixed  $respostas  [{pergunta_id, alternativas?: int[], valor?: string|int|bool}]
     * @return array{status: int, body: array<string, mixed>}
     */
    public function registrarRespostaEnquete(int $tenantId, int $alunoId, int $usuarioId, int $comunicacaoId, mixed $respostas): array
    {
        $c = DB::table('comunicacoes as c');
        Comunicacao::whereVigente($c, AcademyDateTime::nowFormatted());
        $com = $c->where('c.id', $comunicacaoId)->where('c.tenant_id', $tenantId)->first();
        if (! $com || $com->tipo !== 'enquete') {
            return $this->erro('Enquete não encontrada ou encerrada.', 404);
        }
        $config = json_decode((string) $com->configuracoes, true)['enquete'] ?? [];
        $anonima = (bool) ($config['anonima'] ?? false);
        $permitirAlterar = ! $anonima && (bool) ($config['permitir_alterar'] ?? false);

        $perguntas = $this->comunicacoes->perguntas($tenantId, $comunicacaoId);
        [$linhas, $erro] = $this->validar($perguntas, $respostas);
        if ($erro) {
            return $this->erro($erro, 422);
        }

        return DB::transaction(function () use ($tenantId, $alunoId, $usuarioId, $comunicacaoId, $linhas, $anonima, $permitirAlterar) {
            $dest = DB::table('comunicacao_destinatarios')->where('tenant_id', $tenantId)
                ->where('comunicacao_id', $comunicacaoId)->where('aluno_id', $alunoId)->lockForUpdate()->first();
            if (! $dest) {
                return $this->erro('Enquete não encontrada ou encerrada.', 404);
            }
            if ($dest->concluida_em !== null && ! $permitirAlterar) {
                return ['status' => 200, 'body' => ['success' => true, 'ja_respondida' => true, 'message' => 'Você já respondeu esta enquete.']];
            }
            $agora = AcademyDateTime::nowFormatted();
            if ($dest->concluida_em !== null) {
                DB::table('enquete_respostas')->where('tenant_id', $tenantId)->where('comunicacao_id', $comunicacaoId)->where('aluno_id', $alunoId)->delete();
            }
            if ($anonima) {
                // Ordem embaralhada: a sequência dos ids não denuncia quem respondeu junto
                shuffle($linhas);
            }
            DB::table('enquete_respostas')->insert(array_map(fn ($l) => $l + [
                'tenant_id' => $tenantId, 'comunicacao_id' => $comunicacaoId,
                'aluno_id' => $anonima ? null : $alunoId, 'respondida_em' => $anonima ? null : $agora,
            ], $linhas));
            DB::table('comunicacao_destinatarios')->where('id', $dest->id)->update([
                'concluida_em' => $agora, 'visualizada_em' => $dest->visualizada_em ?? $agora,
                'confirmada_por_usuario_id' => $usuarioId, 'updated_at' => now(),
            ]);
            $this->comunicacoes->auditar($tenantId, $comunicacaoId, 'enquete_respondida', $usuarioId, $alunoId, $dest->concluida_em ? ['alterada' => true] : null);

            return ['status' => 200, 'body' => ['success' => true, 'ja_respondida' => false, 'message' => 'Respostas enviadas. Obrigado!']];
        });
    }

    /**
     * Distribuição por pergunta. $comAlunos só vale para enquete não anônima (respostas individuais).
     *
     * @return array{participantes: int, destinatarios: int, perguntas: list<array<string, mixed>>}
     */
    public function resultados(int $tenantId, int $comunicacaoId, bool $comAlunos = false): array
    {
        $perguntas = $this->comunicacoes->perguntas($tenantId, $comunicacaoId);
        $base = DB::table('comunicacao_destinatarios')->where('tenant_id', $tenantId)->where('comunicacao_id', $comunicacaoId);
        $participantes = (clone $base)->whereNotNull('concluida_em')->count();
        $destinatarios = (clone $base)->count();

        $respostas = DB::table('enquete_respostas as r')
            ->leftJoin('alunos as a', 'a.id', '=', 'r.aluno_id')
            ->leftJoin('usuarios as u', 'u.id', '=', 'a.usuario_id')
            ->where('r.tenant_id', $tenantId)->where('r.comunicacao_id', $comunicacaoId)
            ->get(['r.pergunta_id', 'r.alternativa_id', 'r.valor_texto', 'r.valor_numero', 'r.aluno_id', 'u.nome as aluno_nome'])
            ->groupBy('pergunta_id');

        $out = [];
        foreach ($perguntas as $p) {
            $rs = collect($respostas[$p['id']] ?? []);
            $item = ['id' => $p['id'], 'pergunta' => $p['pergunta'], 'tipo_resposta' => $p['tipo_resposta'], 'respostas' => $rs->count()];
            switch ($p['tipo_resposta']) {
                case 'unica':
                case 'multipla':
                    $cont = $rs->countBy('alternativa_id');
                    $item['opcoes'] = array_map(fn ($a) => ['id' => $a['id'], 'rotulo' => $a['texto'], 'total' => (int) ($cont[$a['id']] ?? 0)], $p['alternativas']);
                    // Múltipla: % sobre quem respondeu a pergunta, não sobre marcações
                    $item['respondentes'] = $p['tipo_resposta'] === 'multipla' ? $this->respondentes($rs, $participantes) : $rs->count();
                    break;
                case 'sim_nao':
                    $cont = $rs->countBy('valor_texto');
                    $item['opcoes'] = [['id' => 'sim', 'rotulo' => 'Sim', 'total' => (int) ($cont['sim'] ?? 0)], ['id' => 'nao', 'rotulo' => 'Não', 'total' => (int) ($cont['nao'] ?? 0)]];
                    $item['respondentes'] = $rs->count();
                    break;
                case 'avaliacao':
                    $cont = $rs->countBy('valor_numero');
                    $item['opcoes'] = array_map(fn ($n) => ['id' => $n, 'rotulo' => (string) $n, 'total' => (int) ($cont[$n] ?? 0)], [1, 2, 3, 4, 5]);
                    $item['respondentes'] = $rs->count();
                    $item['media'] = $rs->count() ? round((float) $rs->avg('valor_numero'), 2) : null;
                    break;
                default:
                    $item['textos'] = $rs->map(fn ($r) => array_filter([
                        'texto' => (string) $r->valor_texto,
                        'aluno' => $comAlunos && $r->aluno_id ? $r->aluno_nome : null,
                    ], fn ($v) => $v !== null))->values()->all();
                    $item['respondentes'] = $rs->count();
            }
            $out[] = $item;
        }

        return ['participantes' => $participantes, 'destinatarios' => $destinatarios, 'perguntas' => $out];
    }

    /**
     * Respostas do aluno (para reabrir e alterar, quando permitido). Vazio em enquete anônima.
     *
     * @return array<int, array{alternativas: list<int>, valor: mixed}>
     */
    public function respostasDoAluno(int $tenantId, int $comunicacaoId, int $alunoId): array
    {
        $out = [];
        foreach (DB::table('enquete_respostas')->where('tenant_id', $tenantId)->where('comunicacao_id', $comunicacaoId)->where('aluno_id', $alunoId)->get() as $r) {
            $pid = (int) $r->pergunta_id;
            $out[$pid] ??= ['alternativas' => [], 'valor' => null];
            if ($r->alternativa_id !== null) {
                $out[$pid]['alternativas'][] = (int) $r->alternativa_id;
            } else {
                $out[$pid]['valor'] = $r->valor_numero !== null ? (int) $r->valor_numero : $r->valor_texto;
            }
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $perguntas
     * @return array{0: list<array<string, mixed>>, 1: string|null}
     */
    private function validar(array $perguntas, mixed $respostas): array
    {
        $porPergunta = [];
        foreach ((array) $respostas as $r) {
            if (is_array($r) && isset($r['pergunta_id'])) {
                $porPergunta[(int) $r['pergunta_id']] = $r;
            }
        }
        $ids = array_column($perguntas, 'id');
        if (array_diff(array_keys($porPergunta), $ids) !== []) {
            return [[], 'Resposta para pergunta que não é desta enquete.'];
        }

        $linhas = [];
        foreach ($perguntas as $i => $p) {
            $n = $i + 1;
            $r = $porPergunta[$p['id']] ?? null;
            $alts = array_values(array_unique(array_map('intval', (array) ($r['alternativas'] ?? (isset($r['alternativa_id']) ? [$r['alternativa_id']] : [])))));
            $valor = $r['valor'] ?? null;
            $vazia = $r === null || (in_array($p['tipo_resposta'], ['unica', 'multipla'], true) ? $alts === [] : ($valor === null || (is_string($valor) && trim($valor) === '')));
            if ($vazia) {
                if ($p['obrigatoria']) {
                    return [[], "Responda a pergunta {$n}: {$p['pergunta']}"];
                }

                continue;
            }
            $base = ['pergunta_id' => $p['id'], 'alternativa_id' => null, 'valor_texto' => null, 'valor_numero' => null];
            switch ($p['tipo_resposta']) {
                case 'unica':
                case 'multipla':
                    $validas = array_column($p['alternativas'], 'id');
                    if (array_diff($alts, $validas) !== []) {
                        return [[], "Pergunta {$n}: alternativa inválida."];
                    }
                    if ($p['tipo_resposta'] === 'unica' && count($alts) !== 1) {
                        return [[], "Pergunta {$n}: escolha só uma alternativa."];
                    }
                    foreach ($alts as $a) {
                        $linhas[] = ['alternativa_id' => $a] + $base;
                    }
                    break;
                case 'sim_nao':
                    $v = is_bool($valor) ? ($valor ? 'sim' : 'nao') : strtolower(str_replace('ã', 'a', trim((string) $valor)));
                    if (! in_array($v, ['sim', 'nao'], true)) {
                        return [[], "Pergunta {$n}: responda sim ou não."];
                    }
                    $linhas[] = ['valor_texto' => $v] + $base;
                    break;
                case 'avaliacao':
                    if (! is_numeric($valor) || (int) $valor != $valor || $valor < 1 || $valor > 5) {
                        return [[], "Pergunta {$n}: dê uma nota de 1 a 5."];
                    }
                    $linhas[] = ['valor_numero' => (int) $valor] + $base;
                    break;
                default:
                    $t = trim((string) $valor);
                    if (mb_strlen($t) > 2000) {
                        return [[], "Pergunta {$n}: a resposta aceita até 2.000 caracteres."];
                    }
                    $linhas[] = ['valor_texto' => $t] + $base;
            }
        }
        if ($linhas === []) {
            return [[], 'Responda ao menos uma pergunta.'];
        }

        return [$linhas, null];
    }

    /** Respondentes de uma múltipla escolha; na anônima (sem como agrupar) usa o total de participantes. */
    private function respondentes(\Illuminate\Support\Collection $rs, int $participantes): int
    {
        $comAluno = $rs->whereNotNull('aluno_id');

        return $comAluno->isNotEmpty() ? $comAluno->pluck('aluno_id')->unique()->count() : min($participantes, $rs->count());
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function erro(string $mensagem, int $status): array
    {
        return ['status' => $status, 'body' => ['success' => false, 'error' => $mensagem, 'message' => $mensagem]];
    }
}
