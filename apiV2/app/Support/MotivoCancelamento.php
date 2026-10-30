<?php

namespace App\Support;

/**
 * Motivo de cancelamento da matrícula (matriculas.motivo_cancelamento_codigo).
 * ESCOLHIVEIS: o admin escolhe ao cancelar e a Prospecção usa como categoria da oportunidade.
 * AUTOMATICOS: gravados pelo sistema; não aparecem na escolha do admin.
 */
final class MotivoCancelamento
{
    public const ESCOLHIVEIS = [
        'financeiro' => 'Financeiro',
        'horario' => 'Horário incompatível',
        'tempo' => 'Falta de tempo',
        'mudanca' => 'Mudança de cidade/bairro',
        'saude' => 'Saúde ou lesão',
        'temporario' => 'Pausa temporária (viagem, férias)',
        'insatisfacao' => 'Insatisfação com o serviço',
        'concorrencia' => 'Foi para outra academia',
        'outro' => 'Outro',
    ];

    public const TROCA_PLANO = 'troca_plano';

    public const CHECKOUT_NAO_PAGO = 'checkout_nao_pago';

    public const PELO_APP = 'pelo_app';

    public const ESTORNO = 'estorno';

    public const AUTOMATICOS = [
        self::TROCA_PLANO => 'Troca de plano',
        self::CHECKOUT_NAO_PAGO => 'Checkout não pago',
        self::PELO_APP => 'Cancelado pelo aluno no app',
        self::ESTORNO => 'Pagamento estornado',
    ];

    public static function rotulo(?string $codigo): string
    {
        if ($codigo === null || $codigo === '') {
            return 'Não informado';
        }

        return self::ESCOLHIVEIS[$codigo] ?? self::AUTOMATICOS[$codigo] ?? $codigo;
    }

    public static function escolhivel(?string $codigo): bool
    {
        return $codigo !== null && isset(self::ESCOLHIVEIS[$codigo]);
    }
}
