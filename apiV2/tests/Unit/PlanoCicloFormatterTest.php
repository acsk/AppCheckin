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
        $this->assertSame(['cartao', 'checkout'], PlanoCicloFormatter::metodosPagamento(true, true, false, false));
    }

    public function test_metodos_pagamento_avulso_apenas_pix_quando_cartao_desabilitado(): void
    {
        $this->assertSame(['pix'], PlanoCicloFormatter::metodosPagamento(false, false, true, false));
    }

    public function test_metodos_pagamento_recorrente_apenas_pix_quando_cartao_desabilitado(): void
    {
        $this->assertSame(['pix'], PlanoCicloFormatter::metodosPagamento(true, false, true, false));
    }

    public function test_metodos_pagamento_avulso_lista_pix_cartao_e_checkout(): void
    {
        $this->assertSame(['pix', 'cartao', 'checkout'], PlanoCicloFormatter::metodosPagamento(false, true, true, false));
    }

    public function test_metodos_pagamento_avulso_lista_pix_boleto_cartao(): void
    {
        $this->assertSame(
            ['pix', 'boleto', 'cartao', 'checkout'],
            PlanoCicloFormatter::metodosPagamento(false, true, true, true),
        );
    }
}
