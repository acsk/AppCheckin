<?php

namespace Tests\Unit;

use App\Support\PaymentSandboxAccess;
use Illuminate\Http\Request;
use Tests\TestCase;

class PaymentSandboxAccessTest extends TestCase
{
    public function test_aluno_nao_ve_sandbox(): void
    {
        $request = Request::create('/v2/mobile/pagamento/pix', 'POST');
        $request->attributes->set('usuario', ['papel_id' => 1]);
        $request->attributes->set('is_super_admin', false);

        $this->assertFalse(PaymentSandboxAccess::fromRequest($request));
    }

    public function test_admin_e_super_admin_veem_sandbox(): void
    {
        $admin = Request::create('/v2/mobile/pagamento/pix', 'POST');
        $admin->attributes->set('usuario', ['papel_id' => 3]);
        $admin->attributes->set('is_super_admin', false);

        $sa = Request::create('/v2/mobile/pagamento/pix', 'POST');
        $sa->attributes->set('usuario', ['papel_id' => 1]);
        $sa->attributes->set('is_super_admin', true);

        $this->assertTrue(PaymentSandboxAccess::fromRequest($admin));
        $this->assertTrue(PaymentSandboxAccess::fromRequest($sa));
    }

    public function test_admin_que_tambem_e_aluno_ve_sandbox(): void
    {
        $request = Request::create('/v2/mobile/pagamento/pix', 'POST');
        $request->attributes->set('usuario', ['papel_id' => 1]);
        $request->attributes->set('papel_id', 3);
        $request->attributes->set('is_super_admin', false);

        $this->assertTrue(PaymentSandboxAccess::fromRequest($request));
    }
}
