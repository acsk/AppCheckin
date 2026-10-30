<?php

namespace App\Support;

/**
 * Funil e vocabulários do módulo Prospecção → Recuperação de Alunos.
 */
final class ProspeccaoStatus
{
    public const NAO_CONTATADO = 'nao_contatado';

    public const CONTATADO = 'contatado';

    public const INTERESSADO = 'interessado';

    public const EM_NEGOCIACAO = 'em_negociacao';

    public const RETORNO_AGENDADO = 'retorno_agendado';

    public const RECUPERADO = 'recuperado';

    public const SEM_INTERESSE = 'sem_interesse';

    public const NAO_CONTATAR = 'nao_contatar';

    /** @var array<string, string> */
    public const ROTULOS = [
        self::NAO_CONTATADO => 'Não contatado',
        self::CONTATADO => 'Contatado',
        self::INTERESSADO => 'Interessado',
        self::EM_NEGOCIACAO => 'Em negociação',
        self::RETORNO_AGENDADO => 'Retorno agendado',
        self::RECUPERADO => 'Recuperado',
        self::SEM_INTERESSE => 'Sem interesse',
        self::NAO_CONTATAR => 'Não contatar',
    ];

    /** @var list<string> */
    public const ABERTOS = [self::NAO_CONTATADO, self::CONTATADO, self::INTERESSADO, self::EM_NEGOCIACAO, self::RETORNO_AGENDADO];

    /** @var list<string> */
    public const ENCERRADOS = [self::RECUPERADO, self::SEM_INTERESSE, self::NAO_CONTATAR];

    /**
     * Transições manuais permitidas. RECUPERADO só pelo sistema (matrícula ativa);
     * sair de NAO_CONTATAR só removendo a restrição de contato (ação auditada própria).
     *
     * @var array<string, list<string>>
     */
    private const TRANSICOES_MANUAIS = [
        self::NAO_CONTATADO => [self::CONTATADO, self::INTERESSADO, self::EM_NEGOCIACAO, self::RETORNO_AGENDADO, self::SEM_INTERESSE, self::NAO_CONTATAR],
        self::CONTATADO => [self::NAO_CONTATADO, self::INTERESSADO, self::EM_NEGOCIACAO, self::RETORNO_AGENDADO, self::SEM_INTERESSE, self::NAO_CONTATAR],
        self::INTERESSADO => [self::CONTATADO, self::EM_NEGOCIACAO, self::RETORNO_AGENDADO, self::SEM_INTERESSE, self::NAO_CONTATAR],
        self::EM_NEGOCIACAO => [self::CONTATADO, self::INTERESSADO, self::RETORNO_AGENDADO, self::SEM_INTERESSE, self::NAO_CONTATAR],
        self::RETORNO_AGENDADO => [self::CONTATADO, self::INTERESSADO, self::EM_NEGOCIACAO, self::SEM_INTERESSE, self::NAO_CONTATAR],
        self::SEM_INTERESSE => [self::CONTATADO, self::INTERESSADO, self::EM_NEGOCIACAO, self::NAO_CONTATAR],
        self::RECUPERADO => [],
        self::NAO_CONTATAR => [],
    ];

    /** Categorias de motivo de cancelamento: as mesmas que o admin escolhe ao cancelar a matrícula. */
    public const MOTIVOS = MotivoCancelamento::ESCOLHIVEIS;

    public const CANAIS = ['whatsapp' => 'WhatsApp', 'telefone' => 'Ligação', 'presencial' => 'Presencial', 'email' => 'E-mail', 'outro' => 'Outro'];

    /** Resultado de uma interação. Abrir o WhatsApp não gera interação: o atendente registra o que houve. */
    public const RESULTADOS = [
        'mensagem_enviada' => 'Mensagem enviada',
        'sem_resposta' => 'Sem resposta',
        'conversou' => 'Conversou com o aluno',
        'interessado' => 'Demonstrou interesse',
        'sem_interesse' => 'Sem interesse',
        'pediu_nao_contatar' => 'Pediu para não ser contatado',
    ];

    public const ETAPAS_MODELO = ['primeiro_contato' => 'Primeiro contato', 'interessado' => 'Aluno interessado', 'negociacao' => 'Retomada de negociação', 'outro' => 'Outro'];

    public const TIPOS_CONTATO = ['whatsapp' => 'WhatsApp', 'telefone' => 'Ligação', 'presencial' => 'Presencial', 'email' => 'E-mail'];

    public const SITUACOES_AGENDAMENTO = ['pendente' => 'Pendente', 'concluido' => 'Concluído', 'reagendado' => 'Reagendado', 'cancelado' => 'Cancelado'];

    public static function aberto(string $status): bool
    {
        return in_array($status, self::ABERTOS, true);
    }

    public static function existe(string $status): bool
    {
        return isset(self::ROTULOS[$status]);
    }

    public static function podeTransicionar(string $de, string $para): bool
    {
        return in_array($para, self::TRANSICOES_MANUAIS[$de] ?? [], true);
    }

    /** @return list<string> */
    public static function transicoesManuais(string $de): array
    {
        return self::TRANSICOES_MANUAIS[$de] ?? [];
    }
}
