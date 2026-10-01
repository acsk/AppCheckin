<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * external_reference enviada ao gateway: "{PREFIXO}-{id}-{sufixo único}" (exatamente 3 segmentos).
 *
 * Contrato com os webhooks (explode('-') / ^(MAT|PAC)-(\d+)-):
 * - prefixo em [A-Z] e id numérico, sem "-";
 * - única exceção: o legado "MAT-ASSINATURA-…" (assinatura gerada sem matricula_id), que os
 *   webhooks já não resolvem para uma matrícula — mantido só por compatibilidade.
 *
 * time() sozinho colide com duas tentativas no mesmo segundo; o sufixo aleatório garante
 * unicidade e o timestamp mantém a ordem nos logs.
 */
final class ReferenciaExterna
{
    /** Id legado aceito apenas com prefixo MAT. */
    public const ID_LEGADO_ASSINATURA = 'ASSINATURA';

    public static function matricula(int|string $matriculaId): string
    {
        return self::gerar('MAT', $matriculaId);
    }

    public static function pacote(int|string $contratoId): string
    {
        return self::gerar('PAC', $contratoId);
    }

    /**
     * Normaliza um id vindo de payload externo (ex.: $data/metadata_extra do gateway), onde o
     * valor pode faltar, vir vazio, não-numérico ou nem sequer ser escalar.
     *
     * Use antes de matricula()/pacote() para decidir o fallback no chamador, em vez de deixar
     * gerar() lançar InvalidArgumentException e derrubar o fluxo de pagamento com 500.
     *
     * @return string|null id numérico positivo, ou null quando não há id utilizável
     */
    public static function normalizarId(mixed $id): ?string
    {
        if (is_int($id)) {
            return $id > 0 ? (string) $id : null;
        }

        if (! is_string($id)) {
            return null;
        }

        $id = trim($id);

        return $id !== '' && ctype_digit($id) && $id !== str_repeat('0', strlen($id)) ? $id : null;
    }

    /**
     * @throws \InvalidArgumentException prefixo fora de [A-Z] ou id não numérico (exceto o legado)
     */
    public static function gerar(string $prefixo, int|string $id): string
    {
        if (preg_match('/^[A-Z]+$/', $prefixo) !== 1) {
            throw new \InvalidArgumentException("Prefixo de external_reference inválido: {$prefixo}");
        }

        $id = (string) $id;
        $legado = $prefixo === 'MAT' && $id === self::ID_LEGADO_ASSINATURA;
        if (! $legado && ! ctype_digit($id)) {
            throw new \InvalidArgumentException("Id de external_reference deve ser numérico: {$id}");
        }

        return $prefixo.'-'.$id.'-'.time().self::sufixo();
    }

    /**
     * 8 caracteres hex. A referência não é segredo, só precisa ser única: se a fonte segura
     * falhar (random_bytes lança \Random\RandomException), cai para uniqid+mt_rand com log,
     * em vez de derrubar o fluxo de pagamento com 500.
     *
     * @param  (callable(int): string)|null  $bytes  fonte de bytes aleatórios
     */
    public static function sufixo(?callable $bytes = null): string
    {
        $bytes ??= random_bytes(...);

        try {
            $raw = $bytes(4);
            if (strlen($raw) !== 4) {
                throw new \UnexpectedValueException('Fonte de bytes deve retornar exatamente 4 bytes.');
            }

            return bin2hex($raw);
        } catch (\Throwable $e) {
            Log::warning('ReferenciaExterna: random_bytes indisponível, usando fallback', [
                'exception_class' => $e::class,
                'exception_message' => $e->getMessage(),
            ]);

            return substr(hash('sha256', uniqid('', true).mt_rand()), 0, 8);
        }
    }
}
