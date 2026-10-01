<?php

namespace App\Support;

/**
 * Ids de status_pagamento usados em pagamentos_plano.status_pagamento_id.
 */
final class StatusPagamentoPlano
{
    public const AGUARDANDO = 1;

    public const PAGO = 2;

    public const ATRASADO = 3;

    public const CANCELADO = 4;

    /** Parcelas ainda em aberto (aguardando ou atrasada). */
    public const EM_ABERTO = [self::AGUARDANDO, self::ATRASADO];
}
