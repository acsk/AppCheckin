<?php

namespace App\Support;

/**
 * Campos do cadastro que o super admin pode pedir na atualização cadastral (todos moram em usuarios).
 * tipo orienta o app: texto, email, cpf, telefone, data, sexo, cep, uf.
 */
final class CamposCadastro
{
    public const CATALOGO = [
        'nome' => ['label' => 'Nome completo', 'tipo' => 'texto'],
        'email' => ['label' => 'E-mail', 'tipo' => 'email'],
        'cpf' => ['label' => 'CPF', 'tipo' => 'cpf'],
        'telefone' => ['label' => 'Telefone', 'tipo' => 'telefone'],
        'whatsapp' => ['label' => 'WhatsApp', 'tipo' => 'telefone'],
        'data_nascimento' => ['label' => 'Data de nascimento', 'tipo' => 'data'],
        'sexo' => ['label' => 'Sexo', 'tipo' => 'sexo'],
        'cep' => ['label' => 'CEP', 'tipo' => 'cep'],
        'logradouro' => ['label' => 'Rua', 'tipo' => 'texto'],
        'numero' => ['label' => 'Número', 'tipo' => 'texto'],
        'complemento' => ['label' => 'Complemento', 'tipo' => 'texto'],
        'bairro' => ['label' => 'Bairro', 'tipo' => 'texto'],
        'cidade' => ['label' => 'Cidade', 'tipo' => 'texto'],
        'estado' => ['label' => 'UF', 'tipo' => 'uf'],
    ];

    /** @return list<array{campo: string, label: string, tipo: string}> */
    public static function lista(): array
    {
        return array_map(fn ($k) => ['campo' => $k] + self::CATALOGO[$k], array_keys(self::CATALOGO));
    }

    /**
     * Só campos conhecidos, sem repetição, na ordem do catálogo.
     *
     * @param  array<mixed>  $campos
     * @return list<string>
     */
    public static function filtrar(array $campos): array
    {
        $pedidos = array_map('strval', $campos);

        return array_values(array_filter(array_keys(self::CATALOGO), fn ($k) => in_array($k, $pedidos, true)));
    }

    /** Mensagem de erro do valor, ou null se válido. Vazio é tratado fora (obrigatoriedade). */
    public static function erro(string $campo, string $valor): ?string
    {
        $digitos = Documento::digitos($valor);

        return match (self::CATALOGO[$campo]['tipo'] ?? 'texto') {
            'email' => filter_var($valor, FILTER_VALIDATE_EMAIL) ? null : 'E-mail inválido.',
            'cpf' => strlen($digitos) === 11 ? null : 'CPF deve ter 11 dígitos.',
            'telefone' => strlen($digitos) >= 10 && strlen($digitos) <= 11 ? null : 'Informe DDD e número.',
            'cep' => strlen($digitos) === 8 ? null : 'CEP deve ter 8 dígitos.',
            'uf' => preg_match('/^[A-Za-z]{2}$/', $valor) ? null : 'Use a sigla do estado (ex.: AL).',
            'sexo' => SexoAluno::normalizar($valor) ? null : 'Escolha uma opção de sexo.',
            'data' => self::dataValida($valor) ? null : 'Data inválida.',
            default => mb_strlen($valor) <= 255 ? null : 'Texto muito longo.',
        } ?? ($campo === 'nome' && count(preg_split('/\s+/', trim($valor)) ?: []) < 2 ? 'Escreva nome e sobrenome.' : null);
    }

    private static function dataValida(string $valor): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $valor);

        return $d && $d->format('Y-m-d') === $valor && $d <= new \DateTime && (int) $d->format('Y') >= 1900;
    }
}
