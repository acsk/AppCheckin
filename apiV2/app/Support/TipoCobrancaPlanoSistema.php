<?php

namespace App\Support;

/**
 * Valores aceitos em planos_sistema.tipo_cobranca e cálculo da mensalidade do tenant.
 */
final class TipoCobrancaPlanoSistema
{
    public const GRATUITO = 'gratuito';

    public const FIXO = 'fixo';

    public const POR_ALUNO = 'por_aluno';

    public const ROTULOS = [
        self::GRATUITO => 'Gratuito',
        self::FIXO => 'Valor fixo',
        self::POR_ALUNO => 'Por aluno ativo',
    ];

    public const CODIGO_FREE = 'free';

    public static function valido(mixed $valor): bool
    {
        return is_string($valor) && isset(self::ROTULOS[$valor]);
    }

    /** Planos gravados antes da coluna existir são tratados como fixo (ou gratuito se valor 0). */
    public static function doPlano(array $plano): string
    {
        $tipo = $plano['tipo_cobranca'] ?? null;
        if (self::valido($tipo)) {
            return $tipo;
        }

        return (float) ($plano['valor'] ?? 0) <= 0 ? self::GRATUITO : self::FIXO;
    }

    public static function isGratuito(array $plano): bool
    {
        return self::doPlano($plano) === self::GRATUITO;
    }

    /** Planos por aluno cobram no mínimo 1 aluno para não gerar fatura zerada. */
    public static function calcularValor(array $plano, int $alunosAtivos): float
    {
        return match (self::doPlano($plano)) {
            self::GRATUITO => 0.0,
            self::POR_ALUNO => round(max(1, $alunosAtivos) * (float) ($plano['valor_por_aluno'] ?? 0), 2),
            default => round((float) ($plano['valor'] ?? 0), 2),
        };
    }

    public static function mensagemInvalido(): string
    {
        return 'tipo_cobranca inválido (use: '.implode(', ', array_keys(self::ROTULOS)).')';
    }
}
