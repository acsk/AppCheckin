<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Validation\ValidationException;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

final class Telefone
{
    public const CAMPOS = ['telefone', 'whatsapp', 'emergencia_telefone', 'responsavel_telefone'];

    public const ERRO = 'Telefone inválido. Selecione o país e informe o número completo com DDI.';

    public static function analisar(mixed $valor, ?string $pais = 'BR'): ?PhoneNumber
    {
        if (! is_string($valor) && ! is_int($valor)) {
            return null;
        }
        $valor = preg_replace('/^0xx(?=[\d\s().-]+$)/i', '', trim((string) $valor));
        if ($valor === '' || ! preg_match('/^[+\d\s().-]+$/', $valor)) {
            return null;
        }
        $valor = preg_replace('/^00/', '+', $valor);
        $digitos = Documento::digitos($valor);
        if (($pais === null || $pais === 'BR') && ! str_starts_with($valor, '+') && preg_match('/^55\d{10,11}$/', $digitos)) {
            $valor = '+'.$digitos;
        }
        try {
            $util = PhoneNumberUtil::getInstance();
            $telefone = $util->parse($valor, $pais);

            return ! $telefone->hasExtension() && $util->isValidNumber($telefone) ? $telefone : null;
        } catch (NumberParseException) {
            return null;
        }
    }

    public static function valido(mixed $valor, ?string $pais = 'BR'): bool
    {
        return self::analisar($valor, $pais) !== null;
    }

    public static function normalizar(mixed $valor, string $campo = 'telefone', ?string $pais = 'BR'): ?string
    {
        if ($valor === null || (is_string($valor) && trim($valor) === '')) {
            return null;
        }
        $telefone = self::analisar($valor, $pais);
        if (! $telefone) {
            throw ValidationException::withMessages([$campo => self::ERRO]);
        }

        return PhoneNumberUtil::getInstance()->format($telefone, PhoneNumberFormat::E164);
    }

    /** Provedores brasileiros recebem número nacional; internacional nunca perde o DDI no cadastro. */
    public static function nacionalBrasil(mixed $valor): ?string
    {
        $telefone = self::analisar($valor);
        if (! $telefone || $telefone->getCountryCode() !== 55) {
            return null;
        }

        return PhoneNumberUtil::getInstance()->getNationalSignificantNumber($telefone);
    }

    public static function whatsappUrl(mixed $valor, string $texto = ''): ?string
    {
        if (! self::valido($valor)) {
            return null;
        }

        return 'https://wa.me/'.substr(self::normalizar($valor), 1)
            .($texto !== '' ? '?text='.rawurlencode($texto) : '');
    }

    /** Valores equivalentes para buscas durante a transição, sem comparar os últimos N dígitos. */
    public static function equivalentes(string $valor): array
    {
        if (! self::valido($valor)) {
            return [$valor];
        }
        $e164 = self::normalizar($valor);
        $valores = [$e164, substr($e164, 1)];
        if (($nacional = self::nacionalBrasil($e164)) !== null) {
            $valores[] = $nacional;
        }

        return array_values(array_unique($valores));
    }

    public static function buscar(Builder $query, string|Expression $coluna, string $termo, string $boolean = 'or'): void
    {
        $digitos = Documento::digitos($termo);
        if (strlen($digitos) < 3) {
            return;
        }
        $grammar = $query->getGrammar();
        $sql = $coluna instanceof Expression ? $coluna->getValue($grammar) : $grammar->wrap($coluna);
        foreach (['+', ' ', '(', ')', '-', '.'] as $separador) {
            $sql = "REPLACE({$sql}, '{$separador}', '')";
        }
        $query->where(function (Builder $q) use ($sql, $digitos, $termo) {
            $q->whereRaw("{$sql} LIKE ?", ['%'.$digitos.'%']);
            if (self::valido($termo)) {
                $valores = array_map(fn ($v) => Documento::digitos($v), self::equivalentes($termo));
                $q->orWhereIn(new Expression($sql), $valores);
            }
        }, null, null, $boolean);
    }
}
