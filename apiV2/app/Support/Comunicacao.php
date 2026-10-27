<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;

/**
 * Central de Comunicação: tipos, valores aceitos e as regras de vigência usadas pelo painel, pelo app
 * e pela trava do check-in. A vigência é sempre calculada na consulta (sem depender de agendador).
 */
final class Comunicacao
{
    public const TIPOS = ['notificacao' => 'Notificação', 'comunicado' => 'Comunicado', 'mensagem' => 'Mensagem', 'enquete' => 'Enquete'];

    public const CLASSIFICACOES = ['info', 'alerta', 'sucesso', 'urgente'];

    /** 0 baixa, 1 normal, 2 alta, 3 urgente */
    public const PRIORIDADE_MAX = 3;

    public const TIPOS_RESPOSTA = ['unica', 'multipla', 'sim_nao', 'avaliacao', 'aberta'];

    /** Status calculado: rascunho | agendada | ativa | encerrada | cancelada */
    public const STATUS = ['rascunho', 'agendada', 'ativa', 'encerrada', 'cancelada'];

    public const PUBLICOS = ['todos', 'ativos', 'segmento', 'alunos'];

    /** Janela padrão de "alunos que frequentam a turma" (check-in nos últimos N dias). */
    public const DIAS_CHECKIN_PADRAO = 30;

    public static function acaoExigida(string $tipo): string
    {
        return $tipo === 'enquete' ? 'responder' : 'confirmar_leitura';
    }

    /** Status exibido a partir do gravado e da vigência. */
    public static function statusEfetivo(string $status, ?string $inicio, ?string $termino, ?string $agora = null): string
    {
        if ($status !== 'publicada') {
            return $status;
        }
        $agora ??= AcademyDateTime::nowFormatted();
        if ($inicio !== null && $inicio > $agora) {
            return 'agendada';
        }
        if ($termino !== null && $termino <= $agora) {
            return 'encerrada';
        }

        return 'ativa';
    }

    /** Restringe a comunicações publicadas e dentro da vigência agora (alias da tabela: `c`). */
    public static function whereVigente(Builder $q, string $agora, string $alias = 'c'): Builder
    {
        return $q->where("{$alias}.status", 'publicada')
            ->whereNull("{$alias}.deleted_at")
            ->where(fn ($w) => $w->whereNull("{$alias}.inicio_em")->orWhere("{$alias}.inicio_em", '<=', $agora))
            ->where(fn ($w) => $w->whereNull("{$alias}.termino_em")->orWhere("{$alias}.termino_em", '>', $agora));
    }

    /** Filtro SQL equivalente a um status calculado. */
    public static function whereStatus(Builder $q, string $status, string $agora, string $alias = 'c'): Builder
    {
        return match ($status) {
            'rascunho', 'cancelada' => $q->where("{$alias}.status", $status),
            'agendada' => $q->where("{$alias}.status", 'publicada')->where("{$alias}.inicio_em", '>', $agora),
            'ativa' => self::whereVigente($q, $agora, $alias),
            'encerrada' => $q->where(fn ($w) => $w->where("{$alias}.status", 'encerrada')
                ->orWhere(fn ($p) => $p->where("{$alias}.status", 'publicada')->where("{$alias}.termino_em", '<=', $agora))),
            default => $q,
        };
    }
}
