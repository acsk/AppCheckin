<?php

namespace Tests\Unit;

use App\Support\PlanoCicloFormatter;
use PHPUnit\Framework\TestCase;

class PlanoCicloFormatterTest extends TestCase
{
    public function test_formatar_duracao_mensal(): void
    {
        $this->assertSame('1 mês', PlanoCicloFormatter::formatarDuracao(30));
    }

    public function test_formatar_duracao_dias(): void
    {
        $this->assertSame('7 dias', PlanoCicloFormatter::formatarDuracao(7));
    }

    public function test_metodos_pagamento_recorrente_apenas_checkout(): void
    {
        $this->assertSame(['checkout'], PlanoCicloFormatter::metodosPagamento(true));
    }

    public function test_metodos_pagamento_avulso_apenas_pix_quando_cartao_desabilitado(): void
    {
        $this->assertSame(['pix'], PlanoCicloFormatter::metodosPagamento(false, false, true));
    }

    public function test_metodos_pagamento_recorrente_apenas_pix_quando_cartao_desabilitado(): void
    {
        $this->assertSame(['pix'], PlanoCicloFormatter::metodosPagamento(true, false, true));
    }

    public function test_metodos_pagamento_avulso_apenas_pix_com_cartao_habilitado(): void
    {
        $this->assertSame(['pix'], PlanoCicloFormatter::metodosPagamento(false, true, true));
    }
}
