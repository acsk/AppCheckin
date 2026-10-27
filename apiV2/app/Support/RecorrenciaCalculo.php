<?php

namespace App\Support;

/**
 * Regularização do desconto no cancelamento antecipado de um plano com fidelização (sem estado, sem banco).
 * Valores em centavos inteiros: nunca float.
 *
 * Regularização = (mensal de referência − mensal contratado) × mensalidades promocionais usufruídas,
 * só dentro da fidelização e só se a política aceita no contrato permitir. Mensalidades vencidas em
 * aberto aparecem à parte (não entram na regularização). Meses futuros nunca entram.
 */
final class RecorrenciaCalculo
{
    /** "120.00" | 120 | "120,5" → 12000. Parse por string, sem float. */
    public static function centavos(string|int|null $valor): int
    {
        if ($valor === null || $valor === '') {
            return 0;
        }
        if (is_int($valor)) {
            return $valor * 100;
        }
        $s = str_replace(',', '.', trim($valor));
        if (! preg_match('/^(-?)(\d+)(?:\.(\d{0,2})\d*)?$/', $s, $m)) {
            throw new \InvalidArgumentException("Valor monetário inválido: {$valor}");
        }
        $c = (int) $m[2] * 100 + (int) str_pad($m[3] ?? '', 2, '0');

        return $m[1] === '-' ? -$c : $c;
    }

    /** 12000 → "120.00" */
    public static function reais(int $centavos): string
    {
        $sinal = $centavos < 0 ? '-' : '';
        $c = abs($centavos);

        return $sinal.intdiv($c, 100).'.'.str_pad((string) ($c % 100), 2, '0', STR_PAD_LEFT);
    }

    /** 12000 → "R$ 120,00" (memória de cálculo) */
    public static function brl(int $centavos): string
    {
        $c = abs($centavos);

        return ($centavos < 0 ? '-' : '').'R$ '.number_format(intdiv($c, 100), 0, ',', '.').','.str_pad((string) ($c % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array{valor_mensal_referencia: string, valor_mensal_contratado: string, fidelizacao_meses: int, em_fidelizacao: bool,
     *               fim_fidelizacao_em: string, aceite_em: string, politica: array{regularizacao_ativa?: bool, proporcional?: bool, arrependimento_dias?: int}}  $contrato
     * @param  list<array{numero: int, vencimento: string, valor: string, status: string}>  $cobrancas
     * @param  string  $dataEfetiva  último dia de acesso (Y-m-d)
     * @param  string  $hoje  Y-m-d
     * @param  string|null  $tipo  impossibilidade força isenção; null = deduzido
     * @param  int  $isencaoCentavos  isenção administrativa
     * @return array<string, mixed>
     */
    public static function regularizacao(array $contrato, array $cobrancas, string $dataEfetiva, string $hoje, ?string $tipo = null, int $isencaoCentavos = 0): array
    {
        $ref = self::centavos($contrato['valor_mensal_referencia']);
        $contratado = self::centavos($contrato['valor_mensal_contratado']);
        $diferenca = max(0, $ref - $contratado);
        $fidelizacao = max(0, (int) $contrato['fidelizacao_meses']);
        $politica = $contrato['politica'] ?? [];
        $ativa = (bool) ($politica['regularizacao_ativa'] ?? false);
        $proporcional = (bool) ($politica['proporcional'] ?? false);
        $arrependimento = (int) ($politica['arrependimento_dias'] ?? 7);
        $linhas = [];

        // Mensalidades da fidelização efetivamente pagas e já iniciadas até a data efetiva
        $pagas = array_values(array_filter($cobrancas, fn ($c) => $c['status'] === 'paga' && (int) $c['numero'] <= $fidelizacao && $c['vencimento'] <= $dataEfetiva));
        usort($pagas, fn ($a, $b) => (int) $a['numero'] <=> (int) $b['numero']);
        $meses = count($pagas);
        $fracaoMil = $meses * 1000; // meses em milésimos (proporcional)

        if ($proporcional && $meses > 0) {
            // A última paga pode estar em curso na data efetiva: conta só os dias usados
            $ultima = end($pagas)['vencimento'];
            $fimPeriodo = self::maisUmMes($ultima);
            if ($dataEfetiva < $fimPeriodo) {
                $total = self::dias($ultima, $fimPeriodo);
                $usados = min($total, self::dias($ultima, $dataEfetiva) + 1);
                $fracaoMil = ($meses - 1) * 1000 + intdiv($usados * 1000, max(1, $total));
                $linhas[] = "Último mês proporcional: {$usados} de {$total} dias.";
            }
        }

        $emAberto = array_values(array_filter($cobrancas, fn ($c) => in_array($c['status'], ['pendente', 'vencida'], true) && $c['vencimento'] <= $hoje));
        $emAbertoCentavos = array_sum(array_map(fn ($c) => self::centavos($c['valor']), $emAberto));

        $desconto = intdiv($diferenca * $fracaoMil, 1000);
        $linhas[] = 'Mensal de referência '.self::brl($ref).' − contratado '.self::brl($contratado).' = '.self::brl($diferenca).' de desconto por mês.';
        $linhas[] = "Mensalidades promocionais usufruídas na fidelização: {$meses} de {$fidelizacao}.";
        $linhas[] = 'Desconto concedido até a data efetiva: '.self::brl($desconto).'.';

        $aceite = substr($contrato['aceite_em'], 0, 10);
        $limiteArrependimento = date('Y-m-d', strtotime($aceite." +{$arrependimento} days"));
        $cumprida = ! $contrato['em_fidelizacao'] || $meses >= $fidelizacao || $hoje >= $contrato['fim_fidelizacao_em'];

        $motivoZero = null;
        $tipoFinal = $tipo;
        if ($tipo === 'impossibilidade') {
            $motivoZero = 'Cancelamento por impossibilidade de prestação do serviço: sem regularização.';
        } elseif ($hoje <= $limiteArrependimento) {
            $tipoFinal = 'arrependimento';
            $motivoZero = "Dentro do prazo de arrependimento ({$arrependimento} dias a partir do aceite, até ".self::dataBr($limiteArrependimento).'): sem regularização.';
        } elseif ($cumprida) {
            $tipoFinal = 'fim_prazo';
            $motivoZero = 'Fidelização cumprida: nenhuma diferença é devida.';
        } elseif (! $ativa) {
            $motivoZero = 'A política deste plano não cobra regularização (valor só informativo).';
        } elseif ($diferenca === 0) {
            $motivoZero = 'Sem desconto em relação ao mensal: nada a regularizar.';
        }
        $tipoFinal ??= $cumprida ? 'fim_prazo' : 'antecipado';

        $calculado = $motivoZero === null ? $desconto : 0;
        $isencao = min(max(0, $isencaoCentavos), $calculado);
        $total = $calculado - $isencao;

        if ($motivoZero !== null) {
            $linhas[] = $motivoZero;
        } else {
            $linhas[] = 'Regularização = '.self::brl($diferenca).' × '.self::mesesTexto($fracaoMil).' = '.self::brl($calculado).'.';
        }
        if ($isencao > 0) {
            $linhas[] = 'Isenção administrativa: '.self::brl($isencao).'.';
        }
        $linhas[] = 'Meses futuros não entram no cálculo.';
        if ($emAberto) {
            $linhas[] = 'Mensalidades vencidas em aberto (cobradas à parte): '.count($emAberto).' · '.self::brl($emAbertoCentavos).'.';
        }

        return [
            'tipo' => $tipoFinal,
            'meses_usufruidos' => $meses,
            'desconto_mensal' => self::reais($diferenca),
            'desconto_acumulado' => self::reais($desconto),
            'regularizacao_calculada' => self::reais($calculado),
            'valor_isencao' => self::reais($isencao),
            'valor_regularizacao' => self::reais($total),
            'valor_em_aberto' => self::reais($emAbertoCentavos),
            'mensalidades_em_aberto' => array_map(fn ($c) => ['numero' => (int) $c['numero'], 'vencimento' => $c['vencimento'], 'valor' => self::reais(self::centavos($c['valor']))], $emAberto),
            'total_a_pagar' => self::reais($total + $emAbertoCentavos),
            'politica_cobra' => $ativa,
            'fidelizacao_cumprida' => $cumprida,
            'dentro_arrependimento' => $tipoFinal === 'arrependimento',
            'arrependimento_ate' => $limiteArrependimento,
            'data_efetiva' => $dataEfetiva,
            'memoria' => $linhas,
        ];
    }

    public static function maisUmMes(string $data): string
    {
        return self::maisMeses($data, 1);
    }

    /** Mesmo dia N meses depois; sem esse dia no mês, o último (31/01 + 1 = 28/02; + 2 = 31/03). */
    public static function maisMeses(string $data, int $meses): string
    {
        $d = new \DateTimeImmutable(substr($data, 0, 10));
        $alvo = $d->modify('first day of this month')->modify("+{$meses} months");
        $dia = min((int) $d->format('j'), (int) $alvo->format('t'));

        return $alvo->setDate((int) $alvo->format('Y'), (int) $alvo->format('n'), $dia)->format('Y-m-d');
    }

    private static function dias(string $de, string $ate): int
    {
        return (int) (new \DateTimeImmutable($de))->diff(new \DateTimeImmutable($ate))->days;
    }

    private static function mesesTexto(int $mil): string
    {
        $inteiro = intdiv($mil, 1000);
        $resto = $mil % 1000;

        return $resto ? str_replace('.', ',', rtrim(rtrim($inteiro.'.'.str_pad((string) $resto, 3, '0', STR_PAD_LEFT), '0'), '.')).' meses' : ($inteiro === 1 ? '1 mês' : "{$inteiro} meses");
    }

    private static function dataBr(string $d): string
    {
        return implode('/', array_reverse(explode('-', $d)));
    }
}
