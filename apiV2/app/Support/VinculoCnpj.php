<?php

namespace App\Support;

/** Papel e status de tenant_entidade_juridica. */
final class VinculoCnpj
{
    /** Único tenant que edita os dados jurídicos do CNPJ */
    public const GESTOR = 'gestor';

    /** Vê os dados jurídicos, não edita */
    public const VINCULADO = 'vinculado';

    public const ATIVO = 'ativo';

    public const ENCERRADO = 'encerrado';

    /** Campos jurídicos editáveis (pelo gestor ou pelo super admin). O CNPJ nunca entra aqui. */
    public const CAMPOS_JURIDICOS = [
        'razao_social', 'nome_fantasia', 'inscricao_estadual', 'inscricao_municipal',
        'cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'estado',
    ];
}
