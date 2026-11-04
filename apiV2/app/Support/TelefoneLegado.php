<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

final class TelefoneLegado
{
    public const TABELAS = ['usuarios', 'alunos', 'professores', 'tenants'];

    /** Somente tabelas operacionais; backups, auditorias e payloads não são reescritos. */
    public static function colunas(): array
    {
        $colunas = [];
        foreach (self::TABELAS as $tabela) {
            if (! Schema::hasTable($tabela)) {
                continue;
            }
            $presentes = array_intersect(Schema::getColumnListing($tabela), Telefone::CAMPOS);
            if ($presentes !== []) {
                $colunas[$tabela] = array_values($presentes);
            }
        }

        return $colunas;
    }

    /** País para números sem DDI só é aplicado depois de revisão explícita do operador. */
    public static function classificar(?string $valor, ?string $paisConfirmado = null): array
    {
        if ($valor === null || trim($valor) === '') {
            return ['situacao' => 'vazio', 'normalizado' => null];
        }
        $bruto = trim($valor);
        $explicito = str_starts_with($bruto, '+') || str_starts_with($bruto, '00');
        if (! $explicito && $paisConfirmado === null) {
            return ['situacao' => 'sem_ddi_revisar', 'normalizado' => null];
        }
        if (! Telefone::valido($bruto, $explicito ? null : $paisConfirmado)) {
            return ['situacao' => 'invalido_revisar', 'normalizado' => null];
        }
        $normalizado = Telefone::normalizar($bruto, 'telefone', $explicito ? null : $paisConfirmado);

        return ['situacao' => $normalizado === $valor ? 'normalizado' : 'converter', 'normalizado' => $normalizado];
    }
}
