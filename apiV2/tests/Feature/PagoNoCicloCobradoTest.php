<?php

namespace Tests\Feature;

use App\Services\PagamentoPlanoService;
use Tests\Concerns\BiSchema;
use Tests\TestCase;

/**
 * Pix da renovação: mensalidades de ciclos anteriores não podem contar como pagamento do ciclo cobrado (JA_QUITADO).
 */
class PagoNoCicloCobradoTest extends TestCase
{
    use BiSchema;

    private const MAT = 900;

    protected function setUp(): void
    {
        parent::setUp();
        $this->criarCenarioBi();
        // Mensal de 70 desde março: abril pago, maio em aberto
        $this->parcela(self::MAT, 1, 70, '2026-03-27', 2, '2026-03-27');
        $this->parcela(self::MAT, 1, 70, '2026-04-27', 2, '2026-04-25');
        $this->parcela(self::MAT, 1, 70, '2026-05-27', 1);
    }

    private function pago(?string $status, string $vencimento): float
    {
        return app(PagamentoPlanoService::class)->somarPagoNoCicloCobrado(self::T1, self::MAT, $status, '2026-03-27', $vencimento);
    }

    public function test_renovacao_ignora_ciclos_anteriores(): void
    {
        $this->assertSame(0.0, $this->pago('ativa', '2026-05-27'));
        $this->assertSame(0.0, $this->pago('vencida', '2026-05-27'));
    }

    public function test_renovacao_ja_paga_antecipada_conta(): void
    {
        $this->parcela(self::MAT, 1, 70, '2026-05-27', 2, '2026-05-20');

        $this->assertSame(70.0, $this->pago('ativa', '2026-05-27'));
    }

    public function test_primeira_cobranca_segue_desde_o_inicio(): void
    {
        $this->assertSame(140.0, $this->pago('pendente', '2026-05-27'));
    }
}
