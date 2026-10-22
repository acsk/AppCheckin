<?php

namespace App\Support;

/** CPF, CNPJ, CREF, CEP e UF: só dígitos e validação. */
final class Documento
{
    public const UFS = ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];

    public static function digitos(?string $valor): string
    {
        return preg_replace('/\D/', '', (string) $valor) ?? '';
    }

    public static function cpfValido(string $cpf): bool
    {
        $cpf = self::digitos($cpf);
        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf) === 1) {
            return false;
        }
        for ($t = 9; $t < 11; $t++) {
            $soma = 0;
            for ($i = 0; $i < $t; $i++) {
                $soma += (int) $cpf[$i] * (($t + 1) - $i);
            }
            if ((int) $cpf[$t] !== ((10 * $soma) % 11) % 10) {
                return false;
            }
        }

        return true;
    }

    public static function cnpjValido(string $cnpj): bool
    {
        $cnpj = self::digitos($cnpj);
        if (strlen($cnpj) !== 14 || preg_match('/^(\d)\1{13}$/', $cnpj) === 1) {
            return false;
        }
        foreach ([12, 13] as $t) {
            $soma = 0;
            $peso = $t - 7;
            for ($i = 0; $i < $t; $i++) {
                $soma += (int) $cnpj[$i] * $peso;
                $peso = $peso === 2 ? 9 : $peso - 1;
            }
            $dv = $soma % 11 < 2 ? 0 : 11 - $soma % 11;
            if ((int) $cnpj[$t] !== $dv) {
                return false;
            }
        }

        return true;
    }

    public static function ufValida(?string $uf): bool
    {
        return in_array(strtoupper(trim((string) $uf)), self::UFS, true);
    }

    public static function cepValido(?string $cep): bool
    {
        return strlen(self::digitos($cep)) === 8;
    }

    /** CREF no formato 000000-G/UF (G = graduado, P = provisionado), com UF existente. */
    public static function crefValido(?string $cref): bool
    {
        $cref = self::normalizarCref($cref);

        return preg_match('/^\d{6}-[GP]\/([A-Z]{2})$/', $cref, $m) === 1 && self::ufValida($m[1]);
    }

    /** Aceita "123456g/al", "123456-G-AL", "123456 G AL"… e devolve 123456-G/AL. */
    public static function normalizarCref(?string $cref): string
    {
        $c = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', (string) $cref) ?? '');
        if (preg_match('/^(\d{6})([GP])([A-Z]{2})$/', $c, $m) === 1) {
            return "{$m[1]}-{$m[2]}/{$m[3]}";
        }

        return strtoupper(trim((string) $cref));
    }
}
