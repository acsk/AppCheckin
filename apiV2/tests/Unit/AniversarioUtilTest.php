<?php

namespace Tests\Unit;

use App\Support\AniversarioUtil;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

class AniversarioUtilTest extends TestCase
{
    public function test_detecta_aniversario_no_dia_correto(): void
    {
        $ref = new DateTimeImmutable('2026-03-05', new DateTimeZone('America/Sao_Paulo'));

        $this->assertTrue(AniversarioUtil::ehAniversarioHoje('2000-03-05', $ref));
        $this->assertFalse(AniversarioUtil::ehAniversarioHoje('2000-03-06', $ref));
    }

    public function test_payload_com_idade_no_aniversario(): void
    {
        $ref = new DateTimeImmutable('2026-06-08', new DateTimeZone('America/Sao_Paulo'));
        $payload = AniversarioUtil::payload('1990-06-08', $ref);

        $this->assertTrue($payload['aniversario_hoje']);
        $this->assertSame(36, $payload['idade']);
    }

    public function test_payload_idade_dia_apos_aniversario(): void
    {
        $ref = new DateTimeImmutable('2026-06-09', new DateTimeZone('America/Sao_Paulo'));
        $payload = AniversarioUtil::payload('1990-06-08', $ref);

        $this->assertFalse($payload['aniversario_hoje']);
        $this->assertSame(36, $payload['idade']);
    }

    public function test_payload_idade_dia_antes_do_aniversario(): void
    {
        $ref = new DateTimeImmutable('2026-06-07', new DateTimeZone('America/Sao_Paulo'));
        $payload = AniversarioUtil::payload('1990-06-08', $ref);

        $this->assertFalse($payload['aniversario_hoje']);
        $this->assertSame(35, $payload['idade']);
    }

    public function test_payload_idade_nascimento_setembro_referencia_novembro(): void
    {
        $ref = new DateTimeImmutable('2026-11-01', new DateTimeZone('America/Sao_Paulo'));
        $payload = AniversarioUtil::payload('1990-09-12', $ref);

        $this->assertSame(36, $payload['idade']);
    }

    public function test_payload_idade_nascimento_dezembro_referencia_outubro(): void
    {
        $ref = new DateTimeImmutable('2026-10-15', new DateTimeZone('America/Sao_Paulo'));
        $payload = AniversarioUtil::payload('1990-12-01', $ref);

        $this->assertSame(35, $payload['idade']);
    }

    public function test_payload_sem_data_nascimento(): void
    {
        $payload = AniversarioUtil::payload(null);

        $this->assertFalse($payload['aniversario_hoje']);
        $this->assertNull($payload['idade']);
    }
}
