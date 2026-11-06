<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Quem paga a cobrança do aluno. Menor de idade com responsável (ou dependente sem CPF): o responsável,
 * com nome, CPF, e-mail e telefone dele. Os gateways exigem CPF válido do pagador.
 */
final class Pagador
{
    /** @return array{nome: string, cpf: string, email: ?string, telefone: ?string}|null */
    public static function responsavelDoUsuario(int $usuarioId): ?array
    {
        if ($usuarioId <= 0 || ! Schema::hasColumn('usuarios', 'responsavel_cpf')) {
            return null;
        }
        $u = DB::table('usuarios')->where('id', $usuarioId)
            ->first(['cpf', 'data_nascimento', 'responsavel_nome', 'responsavel_cpf', 'responsavel_email', 'responsavel_telefone']);
        if (! $u || ! Documento::cpfValido((string) $u->responsavel_cpf)) {
            return null;
        }
        $semCpf = ! Documento::cpfValido((string) $u->cpf);
        if (! $semCpf && ! ResponsavelLegal::ehMenor($u->data_nascimento ? (string) $u->data_nascimento : null)) {
            return null;
        }

        return [
            'nome' => (string) $u->responsavel_nome,
            'cpf' => Documento::digitos((string) $u->responsavel_cpf),
            'email' => $u->responsavel_email,
            'telefone' => $u->responsavel_telefone,
        ];
    }

    /**
     * Troca aluno_nome/cpf/email/telefone pelos do responsável quando é ele quem paga ($data['usuario_id']).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function aplicar(array $data): array
    {
        $r = self::responsavelDoUsuario((int) ($data['usuario_id'] ?? 0));
        if ($r === null) {
            return $data;
        }

        return array_merge($data, [
            'aluno_nome' => $r['nome'],
            'aluno_cpf' => $r['cpf'],
            'aluno_email' => $r['email'] ?: ($data['aluno_email'] ?? null),
            'aluno_telefone' => $r['telefone'] ?: ($data['aluno_telefone'] ?? ''),
            'pagador_responsavel' => true,
        ]);
    }
}
