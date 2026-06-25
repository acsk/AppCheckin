<?php

namespace App\Support;

/**
 * Verificação de aniversário (dia/mês em America/Sao_Paulo).
 */
class AniversarioUtil
{
    private const TZ = 'America/Sao_Paulo';

    public static function ehAniversarioHoje(?string $dataNascimento, ?\DateTimeInterface $referencia = null): bool
    {
        $nasc = self::parseData($dataNascimento);
        if (! $nasc) {
            return false;
        }

        $ref = self::referencia($referencia);

        return (int) $nasc->format('m') === (int) $ref->format('m')
            && (int) $nasc->format('d') === (int) $ref->format('d');
    }

    public static function calcularIdade(?string $dataNascimento, ?\DateTimeInterface $referencia = null): ?int
    {
        $nasc = self::parseData($dataNascimento);
        if (! $nasc) {
            return null;
        }

        $ref = self::referencia($referencia);

        return (int) $nasc->diff($ref)->y;
    }

    /**
     * @return array{aniversario_hoje: bool, idade: int|null}
     */
    public static function payload(?string $dataNascimento, ?\DateTimeInterface $referencia = null): array
    {
        $aniversarioHoje = self::ehAniversarioHoje($dataNascimento, $referencia);

        return [
            'aniversario_hoje' => $aniversarioHoje,
            'idade' => self::calcularIdade($dataNascimento, $referencia),
        ];
    }

    private static function parseData(?string $dataNascimento): ?\DateTimeImmutable
    {
        if ($dataNascimento === null || trim($dataNascimento) === '') {
            return null;
        }

        $data = substr(trim($dataNascimento), 0, 10);
        $nasc = \DateTimeImmutable::createFromFormat('Y-m-d', $data, new \DateTimeZone(self::TZ));

        return $nasc ?: null;
    }

    private static function referencia(?\DateTimeInterface $referencia): \DateTimeImmutable
    {
        if ($referencia instanceof \DateTimeImmutable) {
            return $referencia->setTimezone(new \DateTimeZone(self::TZ));
        }
        if ($referencia instanceof \DateTime) {
            return \DateTimeImmutable::createFromMutable($referencia)
                ->setTimezone(new \DateTimeZone(self::TZ));
        }

        return new \DateTimeImmutable('now', new \DateTimeZone(self::TZ));
    }
}
