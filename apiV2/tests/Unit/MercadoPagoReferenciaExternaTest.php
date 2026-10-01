<?php

namespace Tests\Unit;

use App\Services\MercadoPagoService;
use App\Support\ReferenciaExterna;
use Illuminate\Support\Facades\Log;
use ReflectionMethod;
use Tests\TestCase;

/**
 * matricula_id/pacote_contrato_id chegam de $data/metadata_extra: um valor ruim não pode
 * derrubar a criação do pagamento com 500 (ReferenciaExterna lança InvalidArgumentException).
 */
class MercadoPagoReferenciaExternaTest extends TestCase
{
    private function referencia(array $data): string
    {
        $metodo = new ReflectionMethod(MercadoPagoService::class, 'referenciaMatricula');
        $metodo->setAccessible(true);

        return $metodo->invoke(new MercadoPagoService, $data, 'teste');
    }

    public function test_usa_matricula_id_valido(): void
    {
        $this->assertStringStartsWith('MAT-158-', $this->referencia(['matricula_id' => 158]));
        $this->assertStringStartsWith('MAT-42-', $this->referencia(['matricula_id' => '42']));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function matriculaIdInvalido(): array
    {
        return [
            'ausente' => [null],
            'string vazia' => [''],
            'não numérico' => ['abc'],
            'zero' => [0],
            'array' => [['id' => 1]],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('matriculaIdInvalido')]
    public function test_id_invalido_cai_no_legado_sem_lancar(mixed $matriculaId): void
    {
        Log::spy();

        $data = $matriculaId === null ? [] : ['matricula_id' => $matriculaId];
        $ref = $this->referencia($data + ['tenant_id' => 9]);

        $this->assertStringStartsWith('MAT-'.ReferenciaExterna::ID_LEGADO_ASSINATURA.'-', $ref);
        $this->assertCount(3, explode('-', $ref));

        Log::shouldHaveReceived('warning')->withArgs(
            fn (string $msg, array $ctx) => str_contains($msg, 'matricula_id inválido')
                && $ctx['origem'] === 'teste'
                && $ctx['tenant_id'] === 9,
        );
    }
}
