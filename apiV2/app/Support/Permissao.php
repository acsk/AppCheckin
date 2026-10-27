<?php

namespace App\Support;

/**
 * Catálogo de permissões da academia (código `modulo.acao`) e o padrão de cada papel.
 *
 * Permissão efetiva (App\Services\PermissaoService) = padrão dos papéis ativos do usuário na academia
 * + concedidas − retiradas (tenant_usuario_permissoes). O admin principal tem todas as não sensíveis.
 * Sensíveis só o super admin concede e nunca vêm de papel.
 *
 * Nova permissão: constante + entrada em CATALOGO (+ papéis em PADRAO) + `->middleware('permissao:codigo')`.
 */
final class Permissao
{
    public const ALUNOS_VER = 'alunos.ver';

    public const ALUNOS_EDITAR = 'alunos.editar';

    public const MATRICULAS_VER = 'matriculas.ver';

    public const MATRICULAS_GERENCIAR = 'matriculas.gerenciar';

    public const FINANCEIRO_VER = 'financeiro.ver';

    public const FINANCEIRO_BAIXAR = 'financeiro.baixar';

    /** Excluir/vincular matrícula e editar/excluir parcela (código anterior ao catálogo, mantido) */
    public const FINANCEIRO_AVANCADO = 'financeiro_avancado';

    public const PROSPECCAO_USAR = 'prospeccao.usar';

    public const PROSPECCAO_GERENCIAR = 'prospeccao.gerenciar';

    public const AULAS_GERENCIAR = 'aulas.gerenciar';

    public const EQUIPE_GERENCIAR = 'equipe.gerenciar';

    public const CONFIGURACOES_EDITAR = 'configuracoes.editar';

    /** Plano do Check-in e dados do CNPJ */
    public const CONTA_GERENCIAR = 'conta.gerenciar';

    public const COMUNICACOES_VER = 'comunicacoes.ver';

    public const COMUNICACOES_CRIAR = 'comunicacoes.criar';

    public const COMUNICACOES_EDITAR = 'comunicacoes.editar';

    public const COMUNICACOES_PUBLICAR = 'comunicacoes.publicar';

    public const COMUNICACOES_ENCERRAR = 'comunicacoes.encerrar';

    public const COMUNICACOES_RELATORIOS = 'comunicacoes.relatorios';

    /** Respostas identificadas das enquetes (nunca as anônimas) */
    public const COMUNICACOES_RESPOSTAS = 'comunicacoes.respostas';

    /**
     * @var array<string, array{modulo: string, nome: string, descricao: string, sensivel?: bool}>
     */
    public const CATALOGO = [
        self::ALUNOS_VER => ['modulo' => 'Alunos', 'nome' => 'Ver alunos', 'descricao' => 'Lista, ficha e contatos dos alunos.'],
        self::ALUNOS_EDITAR => ['modulo' => 'Alunos', 'nome' => 'Cadastrar e editar alunos', 'descricao' => 'Criar, editar e desativar alunos.'],
        self::MATRICULAS_VER => ['modulo' => 'Matrículas', 'nome' => 'Ver matrículas', 'descricao' => 'Matrículas, planos e vencimentos.'],
        self::MATRICULAS_GERENCIAR => ['modulo' => 'Matrículas', 'nome' => 'Gerenciar matrículas', 'descricao' => 'Matricular, trocar plano, cancelar, bloquear.'],
        self::FINANCEIRO_VER => ['modulo' => 'Financeiro', 'nome' => 'Ver financeiro', 'descricao' => 'Cobranças, recebimentos e relatórios.'],
        self::FINANCEIRO_BAIXAR => ['modulo' => 'Financeiro', 'nome' => 'Dar baixa em cobranças', 'descricao' => 'Registrar pagamentos e créditos.'],
        self::FINANCEIRO_AVANCADO => ['modulo' => 'Financeiro', 'nome' => 'Ações financeiras avançadas', 'descricao' => 'Excluir ou vincular matrículas e editar ou excluir parcelas.', 'sensivel' => true],
        self::PROSPECCAO_USAR => ['modulo' => 'Prospecção', 'nome' => 'Usar a Prospecção', 'descricao' => 'Recuperação de alunos, contatos e mensagens.'],
        self::PROSPECCAO_GERENCIAR => ['modulo' => 'Prospecção', 'nome' => 'Configurar a Prospecção', 'descricao' => 'Equipe comercial e pesos do score.'],
        self::AULAS_GERENCIAR => ['modulo' => 'Aulas', 'nome' => 'Gerenciar aulas', 'descricao' => 'Turmas, horários, WOD e recordes.'],
        self::EQUIPE_GERENCIAR => ['modulo' => 'Equipe', 'nome' => 'Gerenciar equipe', 'descricao' => 'Cadastrar usuários da equipe, papéis e permissões.'],
        self::CONFIGURACOES_EDITAR => ['modulo' => 'Configurações', 'nome' => 'Editar configurações', 'descricao' => 'Parâmetros, formas de pagamento e grupos.'],
        self::CONTA_GERENCIAR => ['modulo' => 'Conta', 'nome' => 'Gerenciar a conta', 'descricao' => 'Plano do Check-in e dados do CNPJ.'],
        self::COMUNICACOES_VER => ['modulo' => 'Comunicação', 'nome' => 'Ver comunicações', 'descricao' => 'Central de Comunicação: lista e detalhes.'],
        self::COMUNICACOES_CRIAR => ['modulo' => 'Comunicação', 'nome' => 'Criar comunicações', 'descricao' => 'Rascunhos, cópias e anexos.'],
        self::COMUNICACOES_EDITAR => ['modulo' => 'Comunicação', 'nome' => 'Editar comunicações', 'descricao' => 'Alterar e excluir rascunhos; ajustar as publicadas.'],
        self::COMUNICACOES_PUBLICAR => ['modulo' => 'Comunicação', 'nome' => 'Publicar comunicações', 'descricao' => 'Publicar para os alunos e incluir novos alunos no público.'],
        self::COMUNICACOES_ENCERRAR => ['modulo' => 'Comunicação', 'nome' => 'Encerrar comunicações', 'descricao' => 'Encerrar antes do prazo ou cancelar (libera o check-in).'],
        self::COMUNICACOES_RELATORIOS => ['modulo' => 'Comunicação', 'nome' => 'Ver relatórios de comunicação', 'descricao' => 'Leituras, pendências e resultados das enquetes.'],
        self::COMUNICACOES_RESPOSTAS => ['modulo' => 'Comunicação', 'nome' => 'Ver respostas das enquetes', 'descricao' => 'Quem respondeu o quê (enquetes não anônimas).'],
    ];

    /**
     * Padrão por papel (decisões: Financeiro vê alunos e cobranças; Comercial sem valores financeiros;
     * Professor só no app; equipe e conta só com o admin principal, que recebe tudo o que não é sensível).
     *
     * @var array<int, list<string>>
     */
    public const PADRAO = [
        Papel::ADMIN => [
            self::ALUNOS_VER, self::ALUNOS_EDITAR, self::MATRICULAS_VER, self::MATRICULAS_GERENCIAR,
            self::FINANCEIRO_VER, self::FINANCEIRO_BAIXAR, self::PROSPECCAO_USAR, self::PROSPECCAO_GERENCIAR,
            self::AULAS_GERENCIAR, self::CONFIGURACOES_EDITAR,
            self::COMUNICACOES_VER, self::COMUNICACOES_CRIAR, self::COMUNICACOES_EDITAR, self::COMUNICACOES_PUBLICAR,
            self::COMUNICACOES_ENCERRAR, self::COMUNICACOES_RELATORIOS, self::COMUNICACOES_RESPOSTAS,
        ],
        Papel::FINANCEIRO => [self::ALUNOS_VER, self::MATRICULAS_VER, self::FINANCEIRO_VER, self::FINANCEIRO_BAIXAR],
        Papel::COMERCIAL => [self::PROSPECCAO_USAR],
        Papel::PROFESSOR => [],
        Papel::ALUNO => [],
    ];

    public static function valida(string $codigo): bool
    {
        return isset(self::CATALOGO[$codigo]);
    }

    public static function sensivel(string $codigo): bool
    {
        return (bool) (self::CATALOGO[$codigo]['sensivel'] ?? false);
    }

    /** @return list<string> */
    public static function todas(): array
    {
        return array_keys(self::CATALOGO);
    }

    /** @return list<string> */
    public static function naoSensiveis(): array
    {
        return array_values(array_filter(self::todas(), fn ($c) => ! self::sensivel($c)));
    }

    /**
     * @param  list<int>  $papeis
     * @return list<string>
     */
    public static function padraoDosPapeis(array $papeis): array
    {
        $out = [];
        foreach ($papeis as $papel) {
            $out = array_merge($out, self::PADRAO[$papel] ?? []);
        }

        return array_values(array_unique($out));
    }
}
