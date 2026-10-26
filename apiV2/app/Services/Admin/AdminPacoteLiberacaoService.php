<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\DB;

/**
 * Alunos liberados para contratar um pacote pelo app (venda_app = liberados), por exemplo depois de
 * uma negociação. A liberação pode ter validade; depois dela o pacote some do app para o aluno.
 */
class AdminPacoteLiberacaoService
{
    /** @return array{status: int, body: array<string, mixed>} */
    public function listar(int $tenantId, int $pacoteId): array
    {
        if (! $this->pacoteExiste($tenantId, $pacoteId)) {
            return $this->erro(404, 'Pacote não encontrado');
        }
        $liberacoes = DB::table('pacote_liberacoes as l')
            ->join('alunos as a', 'a.id', '=', 'l.aluno_id')
            ->join('usuarios as u', 'u.id', '=', 'a.usuario_id')
            ->where('l.tenant_id', $tenantId)
            ->where('l.pacote_id', $pacoteId)
            ->orderBy('u.nome')
            ->get(['l.aluno_id', 'u.nome', 'u.email', 'l.valida_ate', 'l.observacao', 'l.created_at'])
            ->map(fn ($r) => [
                'aluno_id' => (int) $r->aluno_id,
                'nome' => (string) $r->nome,
                'email' => $r->email,
                'valida_ate' => $r->valida_ate,
                'vencida' => $r->valida_ate !== null && $r->valida_ate < now()->toDateString(),
                'observacao' => $r->observacao,
                'created_at' => $r->created_at,
            ])->all();

        return ['status' => 200, 'body' => ['success' => true, 'liberacoes' => $liberacoes]];
    }

    /**
     * @param  array<string, mixed>  $data  aluno_id, valida_ate (Y-m-d, opcional), observacao
     * @return array{status: int, body: array<string, mixed>}
     */
    public function liberar(int $tenantId, int $pacoteId, array $data, ?int $adminId): array
    {
        if (! $this->pacoteExiste($tenantId, $pacoteId)) {
            return $this->erro(404, 'Pacote não encontrado');
        }
        $alunoId = (int) ($data['aluno_id'] ?? 0);
        $alunoNoTenant = $alunoId > 0 && DB::table('alunos as a')
            ->join('tenant_usuario_papel as tup', 'tup.usuario_id', '=', 'a.usuario_id')
            ->where('a.id', $alunoId)->where('tup.tenant_id', $tenantId)->where('tup.papel_id', 1)->where('tup.ativo', 1)
            ->exists();
        if (! $alunoNoTenant) {
            return $this->erro(404, 'Aluno não encontrado nesta academia');
        }
        $validaAte = trim((string) ($data['valida_ate'] ?? ''));
        if ($validaAte !== '' && (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $validaAte) || strtotime($validaAte) === false)) {
            return $this->erro(422, 'Data de validade inválida');
        }
        if ($validaAte !== '' && $validaAte < now()->toDateString()) {
            return $this->erro(422, 'A validade não pode estar no passado');
        }

        DB::table('pacote_liberacoes')->updateOrInsert(
            ['pacote_id' => $pacoteId, 'aluno_id' => $alunoId],
            [
                'tenant_id' => $tenantId,
                'valida_ate' => $validaAte !== '' ? $validaAte : null,
                'observacao' => mb_substr(trim((string) ($data['observacao'] ?? '')), 0, 255) ?: null,
                'criado_por' => $adminId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        return $this->listar($tenantId, $pacoteId);
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function revogar(int $tenantId, int $pacoteId, int $alunoId): array
    {
        if (! $this->pacoteExiste($tenantId, $pacoteId)) {
            return $this->erro(404, 'Pacote não encontrado');
        }
        DB::table('pacote_liberacoes')->where('tenant_id', $tenantId)->where('pacote_id', $pacoteId)->where('aluno_id', $alunoId)->delete();

        return $this->listar($tenantId, $pacoteId);
    }

    private function pacoteExiste(int $tenantId, int $pacoteId): bool
    {
        return DB::table('pacotes')->where('tenant_id', $tenantId)->where('id', $pacoteId)->exists();
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function erro(int $status, string $mensagem): array
    {
        return ['status' => $status, 'body' => ['success' => false, 'message' => $mensagem]];
    }
}
