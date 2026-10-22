<?php

namespace App\Services\SuperAdmin;

use App\Support\Documento;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 1 da consolidação de dados pessoais: `usuarios` vira a fonte da verdade.
 *
 * Compara usuarios com as cópias em alunos e professores e classifica cada campo:
 * - preencher: usuarios está vazio e as outras tabelas concordam num valor (é o único caso que --aplicar grava);
 * - conflito: os dois lados têm valor e eles diferem, ou alunos e professores discordam entre si;
 * - bloqueado: o valor existe mas não pode ir para usuarios (CPF inválido ou já de outro usuário).
 * Nada é sobrescrito nem apagado: conflitos e bloqueios ficam para decisão manual.
 */
class ConsolidacaoPessoasService
{
    public const PREENCHER = 'preencher';

    public const CONFLITO = 'conflito';

    public const BLOQUEADO = 'bloqueado';

    /** campo => [tipo de comparação, tabelas de origem] */
    private const CAMPOS = [
        'nome' => ['texto', ['alunos', 'professores']],
        'cpf' => ['digitos', ['alunos', 'professores']],
        'email' => ['email', ['professores']],
        'telefone' => ['digitos', ['alunos', 'professores']],
        'whatsapp' => ['digitos', ['alunos', 'professores']],
        'data_nascimento' => ['data', ['alunos', 'professores']],
        'sexo' => ['minusculo', ['alunos']],
        'foto_caminho' => ['caminho', ['alunos']],
    ];

    /** Endereço vai em bloco: só preenche se usuarios não tem nenhuma parte dele. */
    private const ENDERECO = ['cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'estado'];

    /** Campos que nunca são gravados (nome é obrigatório em usuarios; e-mail é o login). */
    private const SO_RELATORIO = ['nome', 'email'];

    /**
     * @return list<array{usuario_id: int, nome: string, campo: string, situacao: string, origem: string, atual: ?string, novo: ?string, motivo: ?string}>
     */
    public function analisar(): array
    {
        $alunos = $this->porUsuario('alunos', array_merge(array_keys(self::CAMPOS), self::ENDERECO), ['email']);
        $professores = $this->porUsuario('professores', ['nome', 'cpf', 'email', 'telefone', 'whatsapp', 'data_nascimento']);
        $ids = array_values(array_unique(array_merge(array_keys($alunos), array_keys($professores))));
        if ($ids === []) {
            return [];
        }

        $usuarios = [];
        foreach (array_chunk($ids, 1000) as $lote) {
            foreach (DB::table('usuarios')->whereIn('id', $lote)->get() as $u) {
                $usuarios[(int) $u->id] = (array) $u;
            }
        }
        ksort($usuarios);

        // CPFs já usados em usuarios (usuarios.cpf é UNIQUE): só dígitos => id
        $cpfsEmUso = [];
        DB::table('usuarios')->whereNotNull('cpf')->where('cpf', '<>', '')->orderBy('id')->get(['id', 'cpf'])
            ->each(function ($u) use (&$cpfsEmUso) {
                $cpfsEmUso[Documento::digitos($u->cpf)] ??= (int) $u->id;
            });

        $itens = [];
        foreach ($usuarios as $id => $u) {
            $fontes = ['alunos' => $alunos[$id] ?? null, 'professores' => $professores[$id] ?? null];
            foreach (self::CAMPOS as $campo => [$tipo, $origens]) {
                $item = $this->compararCampo($id, $u, $campo, $tipo, $origens, $fontes);
                if ($item === null) {
                    continue;
                }
                if ($item['situacao'] === self::PREENCHER && $campo === 'cpf') {
                    $item = $this->conferirCpf($item, $cpfsEmUso);
                }
                if ($item['situacao'] === self::PREENCHER && in_array($campo, ['telefone', 'whatsapp'], true) && ! in_array(strlen((string) $item['novo']), [10, 11, 12, 13], true)) {
                    $item['situacao'] = self::BLOQUEADO;
                    $item['motivo'] = 'Telefone com quantidade de dígitos inválida';
                }
                $itens[] = $item;
            }
            if ($fontes['alunos'] && ($item = $this->compararEndereco($id, $u, $fontes['alunos']))) {
                $itens[] = $item;
            }
        }

        return $itens;
    }

    /**
     * Grava só os itens "preencher", sem tocar em valor já existente em usuarios.
     *
     * @param  list<array<string, mixed>>  $itens
     * @return int usuários atualizados
     */
    public function aplicar(array $itens): int
    {
        $porUsuario = [];
        foreach ($itens as $item) {
            if ($item['situacao'] !== self::PREENCHER || in_array($item['campo'], self::SO_RELATORIO, true)) {
                continue;
            }
            if ($item['campo'] === 'endereco') {
                $porUsuario[$item['usuario_id']] = ($porUsuario[$item['usuario_id']] ?? []) + $item['valores'];
            } else {
                $porUsuario[$item['usuario_id']][$item['campo']] = $item['novo'];
            }
        }

        DB::transaction(function () use ($porUsuario) {
            foreach ($porUsuario as $id => $campos) {
                $query = DB::table('usuarios')->where('id', $id);
                // Garante o "nunca sobrescrever" mesmo se algo mudou entre o relatório e a gravação
                foreach (array_keys($campos) as $campo) {
                    $query->where(fn ($q) => $q->whereNull($campo)->orWhere($campo, ''));
                }
                $query->update($campos + ['updated_at' => now()]);
            }
        });

        return count($porUsuario);
    }

    /**
     * @param  list<string>  $origens
     * @param  array<string, ?array<string, mixed>>  $fontes
     * @return array<string, mixed>|null
     */
    private function compararCampo(int $id, array $u, string $campo, string $tipo, array $origens, array $fontes): ?array
    {
        $atual = $this->normalizar($u[$campo] ?? null, $tipo);
        $candidatos = [];
        foreach ($origens as $origem) {
            $valor = isset($fontes[$origem]) ? $this->normalizar($fontes[$origem][$campo] ?? null, $tipo) : null;
            if ($valor !== null) {
                $candidatos[$origem] = $valor;
            }
        }
        if ($candidatos === []) {
            return null;
        }

        $base = ['usuario_id' => $id, 'nome' => (string) $u['nome'], 'campo' => $campo, 'atual' => $atual, 'motivo' => null];
        if ($atual !== null) {
            $diferentes = array_filter($candidatos, fn ($v) => $this->chave($v, $tipo) !== $this->chave($atual, $tipo));
            if ($diferentes === []) {
                return null;
            }

            return $base + ['situacao' => self::CONFLITO, 'origem' => implode('+', array_keys($diferentes)), 'novo' => implode(' | ', array_unique($diferentes))];
        }

        $distintos = array_unique(array_map(fn ($v) => $this->chave($v, $tipo), $candidatos));
        if (count($distintos) > 1) {
            return array_merge($base, ['situacao' => self::CONFLITO, 'origem' => implode('+', array_keys($candidatos)), 'novo' => implode(' | ', $candidatos),
                'motivo' => 'alunos e professores discordam']);
        }

        return $base + ['situacao' => self::PREENCHER, 'origem' => implode('+', array_keys($candidatos)), 'novo' => reset($candidatos)];
    }

    /**
     * @param  array<string, mixed>  $aluno
     * @return array<string, mixed>|null
     */
    private function compararEndereco(int $id, array $u, array $aluno): ?array
    {
        $de = fn (array $r) => array_combine(self::ENDERECO, array_map(
            fn ($c) => $this->normalizar($r[$c] ?? null, $c === 'cep' ? 'digitos' : 'texto'), self::ENDERECO));
        $atual = $de($u);
        $novo = $de($aluno);
        if (array_filter($novo) === []) {
            return null;
        }
        $resumo = fn (array $e) => implode(', ', array_filter([$e['logradouro'], $e['numero'], $e['bairro'], $e['cidade'], $e['estado'], $e['cep']]));
        $base = ['usuario_id' => $id, 'nome' => (string) $u['nome'], 'campo' => 'endereco', 'origem' => 'alunos', 'novo' => $resumo($novo), 'motivo' => null];

        if (array_filter($atual) === []) {
            return $base + ['situacao' => self::PREENCHER, 'atual' => null,
                'valores' => array_map(fn ($v) => $v === null ? null : mb_strtoupper($v, 'UTF-8'), $novo)];
        }
        $iguais = array_map(fn ($v) => $v === null ? null : mb_strtoupper($v, 'UTF-8'), $atual) === array_map(fn ($v) => $v === null ? null : mb_strtoupper($v, 'UTF-8'), $novo);

        return $iguais ? null : $base + ['situacao' => self::CONFLITO, 'atual' => $resumo($atual)];
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, int>  $cpfsEmUso
     * @return array<string, mixed>
     */
    private function conferirCpf(array $item, array &$cpfsEmUso): array
    {
        $cpf = (string) $item['novo'];
        if (! Documento::cpfValido($cpf)) {
            return ['situacao' => self::BLOQUEADO, 'motivo' => 'CPF inválido'] + $item;
        }
        if (isset($cpfsEmUso[$cpf]) && $cpfsEmUso[$cpf] !== $item['usuario_id']) {
            return ['situacao' => self::BLOQUEADO, 'motivo' => "CPF já é do usuário #{$cpfsEmUso[$cpf]} (caso para unificação)"] + $item;
        }
        $cpfsEmUso[$cpf] = $item['usuario_id'];

        return $item;
    }

    private function normalizar(mixed $valor, string $tipo): ?string
    {
        $v = trim(preg_replace('/\s+/u', ' ', (string) $valor) ?? '');
        if ($v === '') {
            return null;
        }

        $v = match ($tipo) {
            'digitos' => Documento::digitos($v),
            'data' => preg_match('/^\d{4}-\d{2}-\d{2}/', $v) && ! str_starts_with($v, '0000') ? substr($v, 0, 10) : '',
            'email', 'minusculo' => mb_strtolower($v, 'UTF-8'),
            default => $v,
        };

        return $v === '' ? null : $v;
    }

    /** Valor usado na comparação: texto sem diferença de maiúsculas. */
    private function chave(string $valor, string $tipo): string
    {
        return $tipo === 'texto' ? mb_strtoupper($valor, 'UTF-8') : $valor;
    }

    /**
     * @param  list<string>  $colunas
     * @param  list<string>  $ignorar
     * @return array<int, array<string, mixed>>
     */
    private function porUsuario(string $tabela, array $colunas, array $ignorar = []): array
    {
        $existentes = array_values(array_filter(
            array_diff($colunas, $ignorar),
            fn ($c) => Schema::hasColumn($tabela, $c),
        ));
        $linhas = [];
        DB::table($tabela)->whereNotNull('usuario_id')->orderBy('id')->get(['usuario_id', ...$existentes])
            ->each(function ($r) use (&$linhas) {
                $linhas[(int) $r->usuario_id] ??= (array) $r;
            });

        return $linhas;
    }
}
