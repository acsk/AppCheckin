<?php

namespace App\Support;

/**
 * Permissões pontuais por usuário/academia (tabela tenant_usuario_permissoes), além do papel.
 * Para criar uma nova: constante aqui + rótulo em ROTULOS + `->middleware('permissao:codigo')` na rota.
 */
final class Permissao
{
    /** Excluir/vincular matrícula e editar/excluir parcela */
    public const FINANCEIRO_AVANCADO = 'financeiro_avancado';

    /** @var array<string, array{nome: string, descricao: string}> */
    public const ROTULOS = [
        self::FINANCEIRO_AVANCADO => [
            'nome' => 'Ações financeiras avançadas',
            'descricao' => 'Excluir ou vincular matrículas e editar ou excluir parcelas.',
        ],
    ];

    public static function valida(string $codigo): bool
    {
        return isset(self::ROTULOS[$codigo]);
    }
}
