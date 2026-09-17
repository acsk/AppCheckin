<?php

namespace App\Support;

use App\Models\Parametro;
use Illuminate\Support\Facades\DB;

/**
 * Teto (por tenant) de quantos minutos antes do início da aula o check-in pode abrir.
 */
final class CheckinToleranciaAntes
{
    public const PARAM_CODIGO = 'max_tolerancia_checkin_antes_minutos';

    public function __construct(
        private readonly ?Parametro $parametro = null,
    ) {}

    private function parametroModel(): Parametro
    {
        return $this->parametro ?? new Parametro(DB::connection()->getPdo());
    }

    /** 0 = sem teto (usa só o valor configurado na turma). */
    public function maxMinutosTenant(int $tenantId): int
    {
        $max = $this->parametroModel()->getInt($tenantId, self::PARAM_CODIGO, 0);

        return max(0, $max);
    }

    public function effectiveAntesMinutos(int $tenantId, int $turmaAntesMinutos): int
    {
        $turma = max(0, $turmaAntesMinutos);
        $max = $this->maxMinutosTenant($tenantId);
        if ($max <= 0) {
            return $turma;
        }

        return min($turma, $max);
    }

    /** Aplica teto ao salvar turma (quando o parâmetro está ativo). */
    public function clampParaTurma(int $tenantId, int $valorInformado): int
    {
        return $this->effectiveAntesMinutos($tenantId, $valorInformado);
    }
}
