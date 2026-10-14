<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

/**
 * Valores aceitos em alunos.sexo (rótulos em pt-BR para telas e relatórios).
 */
final class SexoAluno
{
    public const FEMININO = 'feminino';

    public const MASCULINO = 'masculino';

    public const OUTRO = 'outro';

    public const NAO_INFORMAR = 'prefiro_nao_informar';

    public const ROTULOS = [
        self::FEMININO => 'Feminino',
        self::MASCULINO => 'Masculino',
        self::OUTRO => 'Outro',
        self::NAO_INFORMAR => 'Prefiro não informar',
    ];

    private static ?bool $colunaExiste = null;

    /** Aceita o valor ("feminino") ou o rótulo ("Feminino"); devolve o valor ou null se inválido. */
    public static function normalizar(mixed $valor): ?string
    {
        if (! is_string($valor)) {
            return null;
        }

        $valor = mb_strtolower(trim($valor), 'UTF-8');
        if (isset(self::ROTULOS[$valor])) {
            return $valor;
        }

        foreach (self::ROTULOS as $codigo => $rotulo) {
            if (mb_strtolower($rotulo, 'UTF-8') === $valor) {
                return $codigo;
            }
        }

        return null;
    }

    /** Mensagem de erro para valor fora da lista. */
    public static function mensagemInvalido(): string
    {
        return 'sexo inválido (use: '.implode(', ', array_keys(self::ROTULOS)).')';
    }

    /**
     * A coluna vem da migration 2026_10_13_120000; enquanto ela não roda, o código segue sem gravar o campo.
     */
    public static function colunaExiste(): bool
    {
        return self::$colunaExiste ??= Schema::hasColumn('alunos', 'sexo');
    }

    /** Só para testes. */
    public static function resetCache(): void
    {
        self::$colunaExiste = null;
    }
}
