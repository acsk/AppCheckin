<?php

namespace App\Support;

/**
 * Responsável legal do aluno menor de idade (menos de 18 anos na data de hoje).
 *
 * Os dados ficam em `usuarios` junto dos demais dados pessoais (Pessoa::CAMPOS), para que contratos,
 * cobranças e cadastros futuros (ex.: um irmão) usem o mesmo responsável.
 */
final class ResponsavelLegal
{
    public const MAIORIDADE = 18;

    public const CAMPOS = ['responsavel_nome', 'responsavel_cpf', 'responsavel_email', 'responsavel_telefone', 'responsavel_parentesco'];

    public const PARENTESCOS = [
        'mae' => 'Mãe',
        'pai' => 'Pai',
        'avo' => 'Avó ou avô',
        'tio' => 'Tia ou tio',
        'irmao' => 'Irmã ou irmão',
        'tutor' => 'Tutor legal',
        'outro' => 'Outro',
    ];

    /** Idade completa em $hoje (Y-m-d); null se a data é inválida ou futura. */
    public static function idade(?string $dataNascimento, ?string $hoje = null): ?int
    {
        $data = substr(trim((string) $dataNascimento), 0, 10);
        $nasc = \DateTimeImmutable::createFromFormat('!Y-m-d', $data);
        if (! $nasc || $nasc->format('Y-m-d') !== $data) {
            return null;
        }
        $ref = $hoje !== null ? \DateTimeImmutable::createFromFormat('!Y-m-d', $hoje) : new \DateTimeImmutable('today');
        if (! $ref || $nasc > $ref) {
            return null;
        }

        return $nasc->diff($ref)->y;
    }

    public static function ehMenor(?string $dataNascimento, ?string $hoje = null): bool
    {
        $idade = self::idade($dataNascimento, $hoje);

        return $idade !== null && $idade < self::MAIORIDADE;
    }

    /** Aceita a chave ("mae") ou o rótulo ("Mãe"); devolve a chave ou null. */
    public static function normalizarParentesco(mixed $valor): ?string
    {
        if (! is_string($valor) || trim($valor) === '') {
            return null;
        }
        $v = mb_strtolower(trim($valor), 'UTF-8');
        if (isset(self::PARENTESCOS[$v])) {
            return $v;
        }
        foreach (self::PARENTESCOS as $chave => $rotulo) {
            if (mb_strtolower($rotulo, 'UTF-8') === $v) {
                return $chave;
            }
        }

        return null;
    }

    /**
     * Erros do responsável de um aluno menor. Nome, CPF e e-mail são obrigatórios; telefone e parentesco,
     * se vierem, precisam ser válidos. O CPF não pode ser o do próprio aluno.
     *
     * @param  array<string, mixed>  $data  campos responsavel_*
     * @return list<string>
     */
    public static function validar(array $data, ?string $cpfAluno = null): array
    {
        $erros = [];
        $nome = trim((string) ($data['responsavel_nome'] ?? ''));
        $cpf = Documento::digitos((string) ($data['responsavel_cpf'] ?? ''));
        $email = trim((string) ($data['responsavel_email'] ?? ''));
        $telefone = $data['responsavel_telefone'] ?? null;
        $parentesco = $data['responsavel_parentesco'] ?? null;

        if ($nome === '') {
            $erros[] = 'Aluno menor de idade: informe o nome do responsável';
        } elseif (count(preg_split('/\s+/', $nome) ?: []) < 2) {
            $erros[] = 'Nome do responsável: escreva nome e sobrenome';
        }

        if ($cpf === '') {
            $erros[] = 'Aluno menor de idade: informe o CPF do responsável';
        } elseif (! Documento::cpfValido($cpf)) {
            $erros[] = 'CPF do responsável inválido';
        } elseif ($cpfAluno !== null && $cpf === Documento::digitos($cpfAluno)) {
            $erros[] = 'O CPF do responsável precisa ser diferente do CPF do aluno';
        }

        if ($email === '') {
            $erros[] = 'Aluno menor de idade: informe o e-mail do responsável';
        } elseif (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erros[] = 'E-mail do responsável inválido';
        }

        if ($telefone !== null && $telefone !== '' && ! Telefone::valido($telefone)) {
            $erros[] = 'Telefone do responsável inválido';
        }

        if ($parentesco !== null && $parentesco !== '' && self::normalizarParentesco($parentesco) === null) {
            $erros[] = 'Parentesco do responsável inválido';
        }

        return $erros;
    }
}
