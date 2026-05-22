<?php

namespace Tests\Unit;

use App\Support\MetodoPagamentoResolver;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MetodoPagamentoResolverTest extends TestCase
{
    public function test_resolve_returns_fallback_when_row_missing(): void
    {
        DB::shouldReceive('table')->once()->with('metodos_pagamento')->andReturnSelf();
        DB::shouldReceive('where')->once()->with('codigo', 'credit_card')->andReturnSelf();
        DB::shouldReceive('value')->once()->with('id')->andReturn(null);

        $this->assertSame(1, MetodoPagamentoResolver::resolve('credit_card', 1));
    }

    public function test_resolve_returns_int_when_row_exists(): void
    {
        DB::shouldReceive('table')->once()->with('metodos_pagamento')->andReturnSelf();
        DB::shouldReceive('where')->once()->with('codigo', 'pix')->andReturnSelf();
        DB::shouldReceive('value')->once()->with('id')->andReturn('3');

        $this->assertSame(3, MetodoPagamentoResolver::resolve('pix'));
    }

    public function test_resolve_returns_null_without_fallback(): void
    {
        DB::shouldReceive('table')->once()->with('metodos_pagamento')->andReturnSelf();
        DB::shouldReceive('where')->once()->with('codigo', 'pix')->andReturnSelf();
        DB::shouldReceive('value')->once()->with('id')->andReturn(null);

        $this->assertNull(MetodoPagamentoResolver::resolve('pix'));
    }
}
