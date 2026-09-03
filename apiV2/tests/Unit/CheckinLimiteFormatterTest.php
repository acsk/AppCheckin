<?php

namespace Tests\Unit;

use App\Support\CheckinLimiteFormatter;
use Tests\TestCase;

class CheckinLimiteFormatterTest extends TestCase
{
    public function test_formatar_detalhes_admin_usa_o_aluno(): void
    {
        $result = CheckinLimiteFormatter::formatarDetalhesLimiteMensal([
            'plano' => 'Mensal 3x',
            'limite_mensal' => 17,
            'checkins_mes' => 17,
            'mes_referencia' => '01/09 a 30/09',
            'dias_checkin' => [],
        ], false);

        $this->assertSame(17, $result['direito']);
        $this->assertSame(17, $result['usados']);
        $this->assertSame(0, $result['excesso']);
        $this->assertStringContainsString('O aluno atingiu o limite', $result['mensagem']);
        $this->assertStringNotContainsString('Renove o plano', $result['mensagem']);
    }
}
