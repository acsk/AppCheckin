<?php

namespace Tests\Unit;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Mockery;
use PHPUnit\Framework\SkippedWithMessageException;
use Tests\Concerns\RequiresSqlite;
use Tests\TestCase;

class RequiresSqliteTest extends TestCase
{
    use RequiresSqlite;

    public function test_registrar_funcoes_pula_quando_driver_nao_e_sqlite(): void
    {
        $conexao = Mockery::mock(Connection::class);
        $conexao->shouldReceive('getDriverName')->andReturn('mysql');
        $conexao->shouldNotReceive('getPdo');
        DB::shouldReceive('connection')->andReturn($conexao);

        $this->expectException(SkippedWithMessageException::class);

        try {
            $this->registrarFuncoesSqlite(['NOW' => [fn () => 'x', 0]]);
        } finally {
            DB::clearResolvedInstance('db');
        }
    }

    /**
     * @return array<string, array{array<mixed>}>
     */
    public static function definicoesInvalidas(): array
    {
        return [
            'sem aridade' => [['F' => [fn () => 1]]],
            'aridade string' => [['F' => [fn () => 1, '0']]],
            'callback inválido' => [['F' => ['nao_existe_essa_funcao', 0]]],
            'chave numérica' => [[[fn () => 1, 0]]],
            'posições extras' => [['F' => [fn () => 1, 0, 'x']]],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('definicoesInvalidas')]
    public function test_registrar_funcoes_rejeita_formato_invalido_com_mensagem_clara(array $funcoes): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('deve ser [callable, int aridade]');

        $this->registrarFuncoesSqlite($funcoes);
    }

    public function test_registrar_funcoes_funciona_em_sqlite(): void
    {
        $this->registrarFuncoesSqlite(['DOBRO' => [fn ($x) => $x * 2, 1]]);

        $this->assertEquals(42, DB::selectOne('SELECT DOBRO(21) AS v')->v);
    }
}
