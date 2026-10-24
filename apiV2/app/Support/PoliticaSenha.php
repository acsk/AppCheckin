<?php

namespace App\Support;

/**
 * Senha segura da atualização cadastral. Níveis: basica (só tamanho), media (letras e números),
 * forte (maiúscula, minúscula, número e símbolo). Nunca aceita o CPF, que era a senha inicial do app.
 */
final class PoliticaSenha
{
    public const NIVEIS = ['basica', 'media', 'forte'];

    public const MIN = 6;

    public const MAX = 64;

    /** @return list<string> regras para mostrar ao usuário */
    public static function regras(string $nivel, int $min): array
    {
        $regras = ["Pelo menos {$min} caracteres"];
        if ($nivel === 'media') {
            $regras[] = 'Letras e números';
        }
        if ($nivel === 'forte') {
            array_push($regras, 'Uma letra maiúscula', 'Uma letra minúscula', 'Um número', 'Um símbolo (ex.: ! @ # $)');
        }
        $regras[] = 'Diferente do seu CPF';

        return $regras;
    }

    /** @return list<string> o que falta na senha (vazio = válida) */
    public static function validar(string $senha, string $nivel, int $min, ?string $cpf = null): array
    {
        $erros = [];
        if (mb_strlen($senha) < $min) {
            $erros[] = "A senha precisa ter pelo menos {$min} caracteres.";
        }
        if (mb_strlen($senha) > self::MAX) {
            $erros[] = 'A senha pode ter no máximo '.self::MAX.' caracteres.';
        }
        $letra = preg_match('/\pL/u', $senha);
        $numero = preg_match('/\d/', $senha);
        if ($nivel === 'media' && (! $letra || ! $numero)) {
            $erros[] = 'Use letras e números.';
        }
        if ($nivel === 'forte') {
            if (! preg_match('/\p{Lu}/u', $senha)) {
                $erros[] = 'Inclua uma letra maiúscula.';
            }
            if (! preg_match('/\p{Ll}/u', $senha)) {
                $erros[] = 'Inclua uma letra minúscula.';
            }
            if (! $numero) {
                $erros[] = 'Inclua um número.';
            }
            if (! preg_match('/[^\pL\d\s]/u', $senha)) {
                $erros[] = 'Inclua um símbolo (ex.: ! @ # $).';
            }
        }
        $cpf = Documento::digitos((string) $cpf);
        if ($cpf !== '' && Documento::digitos($senha) === $cpf) {
            $erros[] = 'A senha não pode ser o seu CPF.';
        }

        return $erros;
    }
}
