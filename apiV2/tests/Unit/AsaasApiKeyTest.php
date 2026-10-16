<?php

namespace Tests\Unit;

use App\Support\AsaasApiKey;
use Tests\TestCase;

class AsaasApiKeyTest extends TestCase
{
    public function test_sandbox_prefix_is_required(): void
    {
        $this->assertTrue(AsaasApiKey::valida('$aact_hmlg_abc', 'sandbox'));
        $this->assertFalse(AsaasApiKey::valida('00000000-0000-0000-0000-000000000000', 'sandbox'));
        $this->assertFalse(AsaasApiKey::valida('$aact_prod_abc', 'sandbox'));
    }

    public function test_production_prefix_is_required(): void
    {
        $this->assertTrue(AsaasApiKey::valida('$aact_prod_abc', 'production'));
        $this->assertFalse(AsaasApiKey::valida('$aact_hmlg_abc', 'production'));
    }
}
