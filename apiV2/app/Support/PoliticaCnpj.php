<?php

namespace App\Support;

/**
 * Política global (super admin) para mais de um tenant usar o mesmo CNPJ.
 * Guardada em configuracoes_sistema; regras em App\Services\EntidadeJuridica\EntidadeJuridicaService.
 */
final class PoliticaCnpj
{
    public const CHAVE = 'cnpj_politica_duplicidade';

    /** Nunca compartilha: cada CNPJ em um único tenant ativo */
    public const BLOQUEAR = 'bloquear';

    /** Compartilha livremente */
    public const PERMITIR = 'permitir';

    /** Bloqueia, salvo CNPJ com exceção vigente autorizada pelo super admin */
    public const EXCECAO = 'excecao';

    public const PADRAO = self::EXCECAO;

    /** @var list<string> */
    public const TODAS = [self::BLOQUEAR, self::PERMITIR, self::EXCECAO];

    public static function valida(?string $politica): bool
    {
        return in_array($politica, self::TODAS, true);
    }
}
