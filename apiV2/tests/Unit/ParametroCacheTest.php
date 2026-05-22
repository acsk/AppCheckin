<?php

namespace Tests\Unit;

use App\Models\Parametro;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

class ParametroCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Parametro::clearCache();
    }

    public function test_get_caches_missing_parameter_and_hits_db_once(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->willReturn(true);
        $stmt->expects($this->once())->method('fetch')->willReturn(false);

        $db = $this->createMock(PDO::class);
        $db->expects($this->once())->method('prepare')->willReturn($stmt);

        $parametro = new Parametro($db);

        $this->assertSame('fallback_a', $parametro->get(1, 'inexistente', 'fallback_a'));
        $this->assertSame('fallback_b', $parametro->get(1, 'inexistente', 'fallback_b'));
    }

    public function test_get_caches_falsy_boolean_without_requery(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn([
            'valor' => 'false',
            'valor_padrao' => null,
            'tipo_valor' => 'boolean',
        ]);

        $db = $this->createMock(PDO::class);
        $db->expects($this->once())->method('prepare')->willReturn($stmt);

        $parametro = new Parametro($db);

        $this->assertFalse($parametro->get(2, 'habilitar_x', true));
        $this->assertFalse($parametro->get(2, 'habilitar_x', true));
    }
}
