<?php

namespace Tests\Unit;

use App\Support\Pessoa;
use App\Support\Telefone;
use App\Support\TelefoneLegado;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TelefoneTest extends TestCase
{
    public static function numeros(): array
    {
        return [
            ['(82) 9 9999-9999', 'BR', '+5582999999999'],
            ['+49 162 3329708', 'BR', '+491623329708'],
            ['0162 3329708', 'DE', '+491623329708'],
            ['(415) 555-2671', 'US', '+14155552671'],
            ['912 345 678', 'PT', '+351912345678'],
            ['0055 82 99999-9999', null, '+5582999999999'],
            ['5582999999999', null, '+5582999999999'],
            ['(55) 99999-8888', 'BR', '+5555999998888'],
        ];
    }

    #[DataProvider('numeros')]
    public function test_normaliza_por_pais_e_preserva_e164(string $valor, ?string $pais, string $esperado): void
    {
        $this->assertSame($esperado, Telefone::normalizar($valor, 'telefone', $pais));
        $this->assertSame($esperado, Telefone::normalizar($esperado));
    }

    public function test_rejeita_invalidos_sem_extrair_ou_truncar(): void
    {
        foreach (['abc', '+49 12', '123', '(20) 99999-9999', '+999123456789', '+49 162 3329708 ramal 2', 'call +49 162 3329708'] as $valor) {
            $this->assertFalse(Telefone::valido($valor), $valor);
        }
        $this->expectException(ValidationException::class);
        Telefone::normalizar('+49 12');
    }

    public function test_pessoa_grava_telefone_e_whatsapp_em_e164(): void
    {
        $this->assertSame(['cpf' => '123', 'telefone' => '+491623329708', 'whatsapp' => '+351912345678'],
            Pessoa::normalizar(['telefone' => '+49 162 3329708', 'whatsapp' => '+351 912 345 678', 'cpf' => '1.2.3']));
    }

    public function test_legado_sem_ddi_exige_confirmacao_do_pais(): void
    {
        $this->assertSame('sem_ddi_revisar', TelefoneLegado::classificar('82999999999')['situacao']);
        $this->assertSame('sem_ddi_revisar', TelefoneLegado::classificar('01623329708')['situacao']);
        $this->assertSame('sem_ddi_revisar', TelefoneLegado::classificar('5582999999999')['situacao']);
        $this->assertSame('+5582999999999', TelefoneLegado::classificar('82999999999', 'BR')['normalizado']);
        $this->assertSame('+491623329708', TelefoneLegado::classificar('01623329708', 'DE')['normalizado']);
        $this->assertSame('invalido_revisar', TelefoneLegado::classificar('+49 12')['situacao']);
    }

    public function test_equivalentes_duplicados_e_links_internacionais(): void
    {
        $this->assertSame(Telefone::normalizar('(82) 99999-9999'), Telefone::normalizar('+55 82 99999 9999'));
        $this->assertContains('82999999999', Telefone::equivalentes('+5582999999999'));
        $this->assertSame('https://wa.me/491623329708', Telefone::whatsappUrl('+49 162 3329708'));
        $this->assertNull(Telefone::whatsappUrl('invalid'));
        $this->assertSame('82999999999', Telefone::nacionalBrasil('+5582999999999'));
        $this->assertNull(Telefone::nacionalBrasil('+491623329708'));
    }
}
