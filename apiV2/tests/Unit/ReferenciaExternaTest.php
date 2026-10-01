<?php

namespace Tests\Unit;

use App\Support\ReferenciaExterna;
use Tests\TestCase;

class ReferenciaExternaTest extends TestCase
{
    public function test_formato_compativel_com_os_webhooks(): void
    {
        $ref = ReferenciaExterna::matricula(158);

        // Webhooks: explode('-') usa [0] e [1]; regex ^MAT-(\d+)-
        // timestamp de tamanho variável + 8 hex (não assume 10 dígitos de time()).
        $this->assertMatchesRegularExpression('/^MAT-158-\d+[0-9a-f]{8}$/', $ref);
        $partes = explode('-', $ref);
        $this->assertCount(3, $partes);
        $this->assertSame(['MAT', '158'], [$partes[0], $partes[1]]);
        $this->assertSame(1, preg_match('/^(MAT|PAC)-(\d+)-/', $ref, $m));
        $this->assertSame('158', $m[2]);
    }

    public function test_falha_de_random_bytes_usa_fallback_sem_lancar(): void
    {
        $falha = static function (int $n): string {
            throw new \Random\RandomException('sem entropia');
        };

        \Illuminate\Support\Facades\Log::spy();

        $valor = ReferenciaExterna::sufixo($falha);

        \Illuminate\Support\Facades\Log::shouldHaveReceived('warning')->withArgs(
            fn (string $msg, array $ctx) => str_contains($msg, 'random_bytes')
                && $ctx === ['exception_class' => \Random\RandomException::class, 'exception_message' => 'sem entropia'],
        );

        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $valor);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('bytesDeTamanhoInvalido')]
    public function test_fonte_com_tamanho_invalido_usa_fallback_com_sufixo_valido(string $raw): void
    {
        \Illuminate\Support\Facades\Log::spy();

        $valor = ReferenciaExterna::sufixo(static fn (int $n): string => $raw);

        \Illuminate\Support\Facades\Log::shouldHaveReceived('warning')->withArgs(
            fn (string $msg, array $ctx) => str_contains($msg, 'random_bytes')
                && $ctx['exception_class'] === \UnexpectedValueException::class
                && $ctx['exception_message'] === 'Fonte de bytes deve retornar exatamente 4 bytes.',
        );
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $valor);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function bytesDeTamanhoInvalido(): array
    {
        return [
            'curto' => ["\xde\xad\xbe"],
            'longo' => ["\xde\xad\xbe\xef\x01"],
        ];
    }

    public function test_sufixo_e_o_hex_dos_bytes_da_fonte(): void
    {
        $this->assertSame('deadbeef', ReferenciaExterna::sufixo(static fn (int $n): string => "\xde\xad\xbe\xef"));
    }

    public function test_sufixo_aceita_invokable_como_fonte_de_bytes(): void
    {
        $fonte = new class
        {
            public function __invoke(int $n): string
            {
                return "\xca\xfe\xba\xbe";
            }
        };

        $this->assertSame('cafebabe', ReferenciaExterna::sufixo($fonte));
    }

    public function test_sufixo_pede_4_bytes_e_fontes_distintas_geram_sufixos_distintos(): void
    {
        // Fonte determinística: contador. Unicidade do sufixo = unicidade da fonte (sem depender de sorte).
        $contador = 0;
        $pedidos = [];
        $fonte = static function (int $n) use (&$contador, &$pedidos): string {
            $pedidos[] = $n;

            return pack('N', $contador++);
        };

        $sufixos = [];
        for ($i = 0; $i < 1000; $i++) {
            $sufixos[] = ReferenciaExterna::sufixo($fonte);
        }

        $this->assertSame(array_fill(0, 1000, 4), $pedidos);
        $this->assertCount(1000, array_unique($sufixos));
        $this->assertSame(['00000000', '00000001', '000003e7'], [$sufixos[0], $sufixos[1], $sufixos[999]]);
    }

    public function test_sufixo_padrao_tem_8_hex(): void
    {
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', ReferenciaExterna::sufixo());
    }

    public function test_prefixo_de_pacote(): void
    {
        $this->assertStringStartsWith('PAC-7-', ReferenciaExterna::pacote(7));
    }

    public function test_fallback_legado_assinatura_continua_aceito_so_com_mat(): void
    {
        $ref = ReferenciaExterna::matricula(ReferenciaExterna::ID_LEGADO_ASSINATURA);

        $this->assertStringStartsWith('MAT-ASSINATURA-', $ref);
        $this->assertCount(3, explode('-', $ref));
    }

    public function test_todo_id_numerico_aceito_e_parseavel_pelo_webhook(): void
    {
        foreach ([1, '42', 158, '9999999'] as $id) {
            foreach (['MAT' => ReferenciaExterna::matricula($id), 'PAC' => ReferenciaExterna::pacote($id)] as $tipo => $ref) {
                $this->assertSame(1, preg_match('/^(MAT|PAC)-(\d+)-/', $ref, $m), $ref);
                $this->assertSame([$tipo, (string) $id], [$m[1], $m[2]]);
            }
        }
    }

    /**
     * @return array<string, array{string, int|string}>
     */
    public static function entradasInvalidas(): array
    {
        return [
            'id com hífen' => ['MAT', '12-34'],
            'id alfanumérico' => ['MAT', 'ABC123'],
            'id negativo' => ['MAT', -5],
            'legado com outro prefixo' => ['PAC', 'ASSINATURA'],
            'id vazio' => ['MAT', ''],
            'id com espaço' => ['MAT', '12 34'],
            'prefixo com hífen' => ['MA-T', 1],
            'prefixo minúsculo' => ['mat', 1],
            'prefixo vazio' => ['', 1],
        ];
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function idsNaoUtilizaveis(): array
    {
        return [
            'null' => [null],
            'string vazia' => [''],
            'só espaços' => ['   '],
            'não numérico' => ['ABC123'],
            'com hífen' => ['12-34'],
            'decimal' => ['12.5'],
            'negativo string' => ['-5'],
            'negativo int' => [-5],
            'zero int' => [0],
            'zero string' => ['0'],
            'zeros à esquerda só' => ['000'],
            'array' => [['pacote_contrato_id' => 7]],
            'bool' => [true],
            'float' => [12.5],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('idsNaoUtilizaveis')]
    public function test_normalizar_id_rejeita_entradas_nao_utilizaveis(mixed $id): void
    {
        $this->assertNull(ReferenciaExterna::normalizarId($id));
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function idsUtilizaveis(): array
    {
        return [
            'int' => [158, '158'],
            'string numérica' => ['42', '42'],
            'com espaços em volta' => ['  42  ', '42'],
            'zeros à esquerda' => ['007', '007'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('idsUtilizaveis')]
    public function test_normalizar_id_aceita_ids_numericos_positivos(mixed $id, string $esperado): void
    {
        $normalizado = ReferenciaExterna::normalizarId($id);

        $this->assertSame($esperado, $normalizado);
        // O que passa pelo normalizador nunca pode fazer gerar() lançar.
        $this->assertStringStartsWith("MAT-{$esperado}-", ReferenciaExterna::matricula($normalizado));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('entradasInvalidas')]
    public function test_rejeita_segmentos_que_quebrariam_os_webhooks(string $prefixo, int|string $id): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ReferenciaExterna::gerar($prefixo, $id);
    }
}
