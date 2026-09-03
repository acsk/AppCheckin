<?php

namespace Tests\Unit;

use App\Services\Admin\AdminCreditoAlunoService;
use App\Services\Admin\AdminMatriculaDescontoService;
use Tests\TestCase;

class AdminPagamentoCreditoServiceTest extends TestCase
{
    public function test_calcular_saldo_credito(): void
    {
        $this->assertSame(50.0, AdminCreditoAlunoService::calcularSaldo(100, 50));
        $this->assertSame(0.0, AdminCreditoAlunoService::calcularSaldo(100, 100));
    }

    public function test_calcular_desconto_valor_fixo(): void
    {
        $result = AdminMatriculaDescontoService::calcularDesconto(200.0, [
            ['id' => 1, 'tipo' => 'recorrente', 'valor' => 30, 'percentual' => null, 'motivo' => 'Fixo'],
        ]);

        $this->assertSame(30.0, $result['desconto_total']);
        $this->assertSame('Fixo', $result['motivos']);
    }

    public function test_calcular_desconto_percentual(): void
    {
        $result = AdminMatriculaDescontoService::calcularDesconto(200.0, [
            ['id' => 2, 'tipo' => 'recorrente', 'valor' => null, 'percentual' => 10, 'motivo' => 'Pct'],
        ]);

        $this->assertSame(20.0, $result['desconto_total']);
        $this->assertSame('Pct', $result['motivos']);
    }

    public function test_validar_atualizacao_rejeita_valor_zero(): void
    {
        $errors = AdminMatriculaDescontoService::validarAtualizacao(['valor' => 0]);

        $this->assertContains('Valor deve ser numérico e positivo', $errors);
    }

    public function test_validar_atualizacao_rejeita_percentual_acima_de_cem(): void
    {
        $errors = AdminMatriculaDescontoService::validarAtualizacao(['percentual' => 150]);

        $this->assertContains('Percentual deve estar entre 0.01 e 100', $errors);
    }

    public function test_validar_atualizacao_rejeita_valor_e_percentual_juntos(): void
    {
        $errors = AdminMatriculaDescontoService::validarAtualizacao([
            'valor' => 50,
            'percentual' => 10,
        ]);

        $this->assertContains('Informe apenas valor OU percentual, não ambos', $errors);
    }
}
