<?php

namespace App\Support;

/**
 * Qual permissão cada rota do painel exige (middleware `permissao.rota`, grupo /v2/admin e /v2/tenant).
 * A primeira regra que casar vale; por isso as específicas vêm antes.
 *
 * - código de App\Support\Permissao: exige a permissão na academia do token;
 * - self::LIVRE: qualquer pessoa da equipe (perfil, senha, permissões);
 * - sem regra: só Admin da academia, como antes da estrutura de permissões (nada fica aberto por esquecimento).
 */
final class PermissaoRotas
{
    public const LIVRE = '*';

    private const LEITURA = ['GET', 'HEAD'];

    /**
     * [métodos (null = todos), regex do caminho sem "v2/", permissão]
     *
     * @var list<array{0: ?list<string>, 1: string, 2: string}>
     */
    private const REGRAS = [
        // Conta da pessoa e da academia
        [null, '#^admin/minha-conta/(perfil|senha|permissoes)$#', self::LIVRE],
        [null, '#^admin/minha-conta/escola$#', Permissao::CONFIGURACOES_EDITAR],
        [null, '#^admin/minha-conta/(cnpj|acessos-suporte)$#', Permissao::CONTA_GERENCIAR],
        [null, '#^admin/meu-contrato(/|$)#', Permissao::CONTA_GERENCIAR],
        [null, '#^admin/equipe(/|$)#', Permissao::EQUIPE_GERENCIAR],

        // Ações financeiras avançadas (excluir/vincular matrícula, editar/excluir parcela)
        [['DELETE'], '#^admin/matriculas/\d+$#', Permissao::FINANCEIRO_AVANCADO],
        [['POST'], '#^admin/matriculas/\d+/vincular-atual$#', Permissao::FINANCEIRO_AVANCADO],
        [['PUT'], '#^admin/pagamentos-plano/\d+$#', Permissao::FINANCEIRO_AVANCADO],
        [['DELETE'], '#^admin/pagamentos-plano/\d+/excluir$#', Permissao::FINANCEIRO_AVANCADO],

        // Baixas e sincronização de cobranças feitas a partir da matrícula
        [['POST'], '#^admin/matriculas/(\d+/pagamentos/\d+/confirmar|contas/\d+/baixa|pacote-contrato/\d+/baixa|\d+/sincronizar-(asaas|assinatura))$#', Permissao::FINANCEIRO_BAIXAR],

        // Matrículas
        [self::LEITURA, '#^admin/(matriculas|matricula-descontos)(/|$)#', Permissao::MATRICULAS_VER],
        [null, '#^admin/(matriculas|matricula-descontos)(/|$)#', Permissao::MATRICULAS_GERENCIAR],
        [['POST'], '#^admin/pacote-contratos(/|$)#', Permissao::MATRICULAS_GERENCIAR],
        // Catálogos que a matrícula consulta
        [self::LEITURA, '#^admin/(modalidades|planos|assinatura-frequencias|pacotes|pacote-contratos|admins)(/|$)#', Permissao::MATRICULAS_VER],

        // Alunos e usuários (alunos/professores) da academia
        [self::LEITURA, '#^(admin/alunos|admin/usuarios|tenant/usuarios)(/|$)#', Permissao::ALUNOS_VER],
        [null, '#^(admin/alunos|tenant/usuarios)(/|$)#', Permissao::ALUNOS_EDITAR],

        // Financeiro
        [self::LEITURA, '#^admin/(dashboard|pagamentos-plano|contas-receber|creditos|financeiro|assinaturas|relatorios)(/|$)#', Permissao::FINANCEIRO_VER],
        [null, '#^admin/(pagamentos-plano|contas-receber|creditos|financeiro|assinaturas)(/|$)#', Permissao::FINANCEIRO_BAIXAR],

        // Central de Comunicação (específicas antes da leitura geral)
        [['GET'], '#^admin/comunicacoes/indicadores$#', Permissao::COMUNICACOES_RELATORIOS],
        [['GET'], '#^admin/comunicacoes/\d+/(destinatarios|resultados)$#', Permissao::COMUNICACOES_RELATORIOS],
        [['GET'], '#^admin/comunicacoes/\d+/respostas$#', Permissao::COMUNICACOES_RESPOSTAS],
        [['POST'], '#^admin/comunicacoes/\d+/(publicar|incluir-destinatarios)$#', Permissao::COMUNICACOES_PUBLICAR],
        [['POST'], '#^admin/comunicacoes/\d+/(encerrar|cancelar)$#', Permissao::COMUNICACOES_ENCERRAR],
        [['POST'], '#^admin/comunicacoes(/\d+/duplicar|/previa-publico|/anexos)?$#', Permissao::COMUNICACOES_CRIAR],
        [['PUT', 'DELETE'], '#^admin/comunicacoes/\d+$#', Permissao::COMUNICACOES_EDITAR],
        [self::LEITURA, '#^admin/comunicacoes(/|$)#', Permissao::COMUNICACOES_VER],

        // Aulas
        [null, '#^admin/(turmas|dias|wods|recordes|professores)(/|$)#', Permissao::AULAS_GERENCIAR],

        // Configurações (inclui catálogos de planos/modalidades/pacotes)
        [null, '#^admin/(modalidades|planos|pacotes|parametros|configuracoes|formas-pagamento-config|payment-credentials|tenant|auditoria|logs)(/|$)#', Permissao::CONFIGURACOES_EDITAR],
    ];

    /** Permissão exigida, self::LIVRE, ou null (sem regra: só Admin). */
    public static function exigida(string $metodo, string $caminho): ?string
    {
        $metodo = strtoupper($metodo);
        $caminho = preg_replace('#^v2/#', '', trim($caminho, '/'));
        foreach (self::REGRAS as [$metodos, $regex, $permissao]) {
            if (($metodos === null || in_array($metodo, $metodos, true)) && preg_match($regex, $caminho)) {
                return $permissao;
            }
        }

        return null;
    }
}
