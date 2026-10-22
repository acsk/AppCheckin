<?php

namespace App\Support;

/**
 * Papéis de tenant_usuario_papel.
 *
 * 1–4 são hierárquicos (o maior id é o papel "efetivo" do usuário). COMERCIAL é funcional:
 * fica fora da hierarquia e é verificado explicitamente (ex.: módulo Prospecção), para não
 * herdar permissões de quem tem id menor (gerenciar turmas, funções de professor etc.).
 */
final class Papel
{
    public const ALUNO = 1;

    public const PROFESSOR = 2;

    public const ADMIN = 3;

    public const SUPER_ADMIN = 4;

    public const COMERCIAL = 5;

    /** Funcional como o Comercial: dados de alunos e cobranças (App\Support\Permissao) */
    public const FINANCEIRO = 6;

    /** @var list<int> */
    public const HIERARQUICOS = [self::ALUNO, self::PROFESSOR, self::ADMIN, self::SUPER_ADMIN];

    /** Fora da hierarquia: nunca viram o papel "efetivo" de quem também é admin/professor/aluno. */
    public const FUNCIONAIS = [self::COMERCIAL, self::FINANCEIRO];

    /** Papéis da equipe da academia (contam no limite de usuários do plano). */
    public const EQUIPE = [self::PROFESSOR, self::ADMIN, self::COMERCIAL, self::FINANCEIRO];

    /**
     * ORDER BY que põe os papéis hierárquicos na frente (maior primeiro) e os funcionais por último.
     */
    public static function ordemHierarquicaSql(string $coluna): string
    {
        return 'CASE WHEN '.$coluna.' IN ('.implode(', ', self::FUNCIONAIS).') THEN 0 ELSE '.$coluna.' END DESC';
    }
}
