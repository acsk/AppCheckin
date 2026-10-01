<?php

namespace Tests\Unit;

use App\Support\MobileResponse;
use Tests\TestCase;

class MobileResponseTest extends TestCase
{
    public function test_server_error_omite_detalhe_fora_de_debug(): void
    {
        config(['app.debug' => false]);

        $body = MobileResponse::serverError('Erro ao migrar plano', 'SQLSTATE[42S22]: Unknown column x')->getData(true);

        $this->assertSame(['success' => false, 'error' => 'Erro ao migrar plano'], $body);
    }

    public function test_server_error_inclui_detalhe_em_debug(): void
    {
        config(['app.debug' => true]);

        $body = MobileResponse::serverError('Erro ao migrar plano', 'detalhe')->getData(true);

        $this->assertSame('detalhe', $body['message']);
    }
}
