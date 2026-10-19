<?php

namespace App\Services\Site;

/** Validação recusada dentro da transação do checkout; desfaz o que já foi gravado. */
class CheckoutRecusado extends \RuntimeException
{
    /**
     * @param  list<string>  $erros
     */
    public function __construct(public readonly array $erros)
    {
        parent::__construct(implode('. ', $erros));
    }
}
