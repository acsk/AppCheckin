<?php

namespace Tests\Unit;

use App\Support\Pessoa;
use App\Support\ResponsavelLegal;
use PHPUnit\Framework\TestCase;

class ResponsavelLegalTest extends TestCase
{
    private const VALIDO = [
        'responsavel_nome' => 'Maria da Silva',
        'responsavel_cpf' => '529.982.247-25',
        'responsavel_email' => 'maria@exemplo.com',
    ];

    public function test_idade_e_maioridade(): void
    {
        $this->assertSame(17, ResponsavelLegal::idade('2008-11-07', '2026-11-06'));
        $this->assertSame(18, ResponsavelLegal::idade('2008-11-06', '2026-11-06'));
        $this->assertTrue(ResponsavelLegal::ehMenor('2008-11-07', '2026-11-06'));
        $this->assertFalse(ResponsavelLegal::ehMenor('2008-11-06', '2026-11-06'));
        $this->assertFalse(ResponsavelLegal::ehMenor(null));
        $this->assertFalse(ResponsavelLegal::ehMenor('2030-01-01', '2026-11-06'));
        $this->assertNull(ResponsavelLegal::idade('2010-02-30'));
        $this->assertSame(10, ResponsavelLegal::idade('2016-01-01 00:00:00', '2026-11-06'));
    }

    public function test_responsavel_completo_passa(): void
    {
        $this->assertSame([], ResponsavelLegal::validar(self::VALIDO, '11144477735'));
        $this->assertSame([], ResponsavelLegal::validar(self::VALIDO + ['responsavel_parentesco' => 'Mãe']));
    }

    public function test_exige_nome_cpf_e_email(): void
    {
        $erros = ResponsavelLegal::validar([]);

        $this->assertCount(3, $erros);
        $this->assertStringContainsString('nome do responsável', $erros[0]);
        $this->assertStringContainsString('CPF do responsável', $erros[1]);
        $this->assertStringContainsString('e-mail do responsável', $erros[2]);
    }

    public function test_cpf_do_responsavel_diferente_do_aluno_e_valido(): void
    {
        $this->assertSame(
            ['O CPF do responsável precisa ser diferente do CPF do aluno'],
            ResponsavelLegal::validar(self::VALIDO, '52998224725'),
        );
        $this->assertSame(
            ['CPF do responsável inválido'],
            ResponsavelLegal::validar(['responsavel_cpf' => '11111111111'] + self::VALIDO),
        );
    }

    public function test_parentesco_invalido(): void
    {
        $this->assertSame(
            ['Parentesco do responsável inválido'],
            ResponsavelLegal::validar(self::VALIDO + ['responsavel_parentesco' => 'vizinho']),
        );
    }

    public function test_pessoa_normaliza_campos_do_responsavel(): void
    {
        $out = Pessoa::normalizar([
            'responsavel_nome' => ' Maria da Silva ',
            'responsavel_cpf' => '529.982.247-25',
            'responsavel_email' => 'Maria@Exemplo.com',
            'responsavel_parentesco' => 'Mãe',
            'responsavel_telefone' => '',
        ]);

        $this->assertSame('MARIA DA SILVA', $out['responsavel_nome']);
        $this->assertSame('52998224725', $out['responsavel_cpf']);
        $this->assertSame('maria@exemplo.com', $out['responsavel_email']);
        $this->assertSame('mae', $out['responsavel_parentesco']);
        $this->assertNull($out['responsavel_telefone']);
    }
}
