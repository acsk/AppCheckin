<?php

namespace App\Support;

use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dados pessoais da pessoa: a fonte é `usuarios` (fase 2 da consolidação).
 *
 * alunos/professores ainda têm cópias antigas desses campos. Enquanto a fase 3 não apaga as colunas,
 * a leitura usa COALESCE(usuarios, cópia) para não perder o que o `pessoas:consolidar --aplicar` ainda
 * não copiou. A gravação (UsuarioRepository::gravarDadosPessoais) escreve em usuarios e limpa a cópia,
 * então um campo apagado de propósito não volta pelo fallback. O nome continua espelhado (NOT NULL e
 * usado em muitas listagens); sai na fase 3.
 */
final class Pessoa
{
    /** Campos pessoais que moram em usuarios (o nome é tratado à parte). */
    public const CAMPOS = ['cpf', 'telefone', 'whatsapp', 'data_nascimento', 'sexo',
        'cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'estado', 'foto_caminho'];

    public const ENDERECO = ['cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'estado'];

    /** Cópias antigas que ainda podem ter o dado (tabela => campos). */
    private const COPIAS = [
        'alunos' => self::CAMPOS,
        'professores' => ['cpf', 'telefone', 'whatsapp', 'data_nascimento'],
    ];

    /** @var array<string, bool> */
    private static array $colunas = [];

    /**
     * Expressão de leitura: usuarios primeiro, cópia antiga se vazio. Sem $copia, só usuarios.
     */
    public static function col(string $campo, string $u = 'u', ?string $copia = 'a'): Expression
    {
        $tabela = $copia === 'p' ? 'professores' : 'alunos';
        $temUsuario = self::existe('usuarios', $campo);
        $temCopia = $copia !== null && self::copiasEm($tabela, [$campo]) !== [];

        // Coluna date: NULLIF(data, '') dá "Incorrect DATE value" no MySQL estrito
        $vazio = fn (string $c) => $campo === 'data_nascimento' ? $c : "NULLIF({$c}, '')";

        return DB::raw(match (true) {
            // Foto: o app só gravava em alunos e usuarios tem fotos antigas da Slim, então a cópia vem primeiro.
            // Todo upload novo vai para usuarios e limpa a cópia.
            $temUsuario && $temCopia && $campo === 'foto_caminho' => "COALESCE({$vazio("{$copia}.{$campo}")}, {$u}.{$campo})",
            $temUsuario && $temCopia => "COALESCE({$vazio("{$u}.{$campo}")}, {$copia}.{$campo})",
            $temUsuario => "{$u}.{$campo}",
            $temCopia => "{$copia}.{$campo}",
            default => 'NULL',
        });
    }

    /** Para select: a mesma expressão com o nome do campo como alias. */
    public static function select(string $campo, string $u = 'u', ?string $copia = 'a', ?string $alias = null): Expression
    {
        return DB::raw(self::col($campo, $u, $copia)->getValue(DB::connection()->getQueryGrammar()).' as '.($alias ?? $campo));
    }

    /**
     * @param  list<string>|null  $campos
     * @return list<Expression>
     */
    public static function selects(?array $campos = null, string $u = 'u', ?string $copia = 'a'): array
    {
        return array_map(fn ($c) => self::select($c, $u, $copia), $campos ?? self::CAMPOS);
    }

    /**
     * Só os campos pessoais (e o nome) presentes em $data, no formato gravado: documentos e telefones
     * com dígitos, endereço e nome em maiúsculas, sexo pela lista, vazio vira null.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizar(array $data): array
    {
        $out = [];
        foreach (['nome', ...self::CAMPOS] as $campo) {
            if (! array_key_exists($campo, $data)) {
                continue;
            }
            $v = is_string($data[$campo]) ? trim($data[$campo]) : $data[$campo];
            $v = ($v === '' || $v === null) ? null : $v;
            $out[$campo] = $v === null ? null : match ($campo) {
                'cpf', 'cep', 'telefone', 'whatsapp' => Documento::digitos((string) $v) ?: null,
                'nome', 'logradouro', 'complemento', 'bairro', 'cidade' => mb_strtoupper((string) $v, 'UTF-8'),
                'estado' => mb_substr(mb_strtoupper((string) $v, 'UTF-8'), 0, 2, 'UTF-8'),
                'sexo' => SexoAluno::normalizar($v),
                'data_nascimento' => substr((string) $v, 0, 10),
                default => (string) $v,
            };
        }
        if (array_key_exists('nome', $out) && $out['nome'] === null) {
            unset($out['nome']); // usuarios.nome é obrigatório
        }

        return $out;
    }

    /**
     * Campos de $campos que existem como cópia em $tabela (para limpar ao gravar em usuarios).
     *
     * @param  list<string>  $campos
     * @return list<string>
     */
    public static function copiasEm(string $tabela, array $campos): array
    {
        return array_values(array_filter(
            array_intersect($campos, self::COPIAS[$tabela] ?? []),
            fn ($c) => self::existe($tabela, $c),
        ));
    }

    public static function existe(string $tabela, string $coluna): bool
    {
        return self::$colunas["{$tabela}.{$coluna}"] ??= Schema::hasColumn($tabela, $coluna);
    }

    /** Testes recriam o schema: esquece o que já foi conferido. */
    public static function limparCache(): void
    {
        self::$colunas = [];
    }
}
