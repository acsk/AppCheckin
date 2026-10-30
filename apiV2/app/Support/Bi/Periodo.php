<?php

namespace App\Support\Bi;

use DateTimeImmutable;

/**
 * Período de análise do BI e o período anterior equivalente, para comparar sem um lado sair perdendo.
 *
 * - mes: do dia 1 até hoje; anterior = mês passado do dia 1 até o mesmo dia (limitado ao último dia dele)
 * - mes_anterior: mês passado inteiro; anterior = o mês antes dele inteiro
 * - 3m / 12m: últimos 90 / 365 dias até hoje; anterior = os 90 / 365 dias imediatamente antes
 */
final class Periodo
{
    public const PRESETS = ['mes', 'mes_anterior', '3m', '12m'];

    private function __construct(
        public readonly string $preset,
        public readonly string $inicio,
        public readonly string $fim,
        public readonly string $anteriorInicio,
        public readonly string $anteriorFim,
    ) {}

    public static function resolver(?string $preset, string $hoje): self
    {
        $preset = in_array($preset, self::PRESETS, true) ? $preset : 'mes';
        $h = new DateTimeImmutable($hoje);

        switch ($preset) {
            case 'mes_anterior':
                $ini = $h->modify('first day of last month');
                $antIni = $ini->modify('first day of last month');

                return new self($preset, $ini->format('Y-m-d'), $ini->format('Y-m-t'), $antIni->format('Y-m-d'), $antIni->format('Y-m-t'));
            case '3m':
            case '12m':
                $dias = $preset === '3m' ? 90 : 365;
                $ini = $h->modify('-'.($dias - 1).' days');
                $antFim = $ini->modify('-1 day');

                return new self($preset, $ini->format('Y-m-d'), $h->format('Y-m-d'), $antFim->modify('-'.($dias - 1).' days')->format('Y-m-d'), $antFim->format('Y-m-d'));
            default:
                $antIni = $h->modify('first day of last month');
                $dia = min((int) $h->format('j'), (int) $antIni->format('t'));

                return new self($preset, $h->format('Y-m-01'), $h->format('Y-m-d'), $antIni->format('Y-m-d'), $antIni->format('Y-m-').str_pad((string) $dia, 2, '0', STR_PAD_LEFT));
        }
    }

    public function dias(): int
    {
        return (int) (new DateTimeImmutable($this->inicio))->diff(new DateTimeImmutable($this->fim))->days + 1;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'preset' => $this->preset,
            'inicio' => $this->inicio,
            'fim' => $this->fim,
            'dias' => $this->dias(),
            'anterior' => ['inicio' => $this->anteriorInicio, 'fim' => $this->anteriorFim],
        ];
    }
}
