<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class PlanoSistemaRepository
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listarTodos(bool $apenasAtivos = false): array
    {
        $query = DB::table('planos_sistema');

        if ($apenasAtivos) {
            $query->where('ativo', 1);
        }

        return $query->orderBy('ordem')->orderBy('valor')->get()->map(fn ($row) => (array) $row)->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarDisponiveis(): array
    {
        return DB::table('planos_sistema')
            ->where('atual', 1)
            ->where('ativo', 1)
            ->orderBy('ordem')
            ->orderBy('valor')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function buscarPorId(int $id): ?array
    {
        $row = DB::table('planos_sistema')->where('id', $id)->first();

        return $row ? (array) $row : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function criar(array $data): int
    {
        return (int) DB::table('planos_sistema')->insertGetId([
            'nome' => $data['nome'],
            'descricao' => $data['descricao'] ?? null,
            'valor' => $data['valor'],
            'duracao_dias' => $data['duracao_dias'] ?? 30,
            'max_alunos' => $data['max_alunos'] ?? null,
            'max_admins' => $data['max_admins'] ?? 1,
            'features' => isset($data['features']) ? json_encode($data['features']) : null,
            'ativo' => $data['ativo'] ?? true,
            'atual' => $data['atual'] ?? true,
            'ordem' => $data['ordem'] ?? 0,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function atualizar(int $id, array $data): bool
    {
        $planoAtual = $this->buscarPorId($id);
        if (! $planoAtual) {
            throw new \RuntimeException('Plano não encontrado');
        }

        if ($this->possuiContratos($id)) {
            $camposMudados = [];
            foreach ($data as $campo => $valor) {
                if (! array_key_exists($campo, $planoAtual)) {
                    continue;
                }
                $valorAtual = $planoAtual[$campo];
                $valorNovo = $valor;
                if (is_bool($valorNovo)) {
                    $valorNovo = $valorNovo ? 1 : 0;
                }
                if (is_bool($valorAtual)) {
                    $valorAtual = $valorAtual ? 1 : 0;
                }
                if (is_numeric($valorAtual) && is_numeric($valorNovo)) {
                    $valorAtual = (float) $valorAtual;
                    $valorNovo = (float) $valorNovo;
                }
                if ($valorAtual !== $valorNovo) {
                    $camposMudados[] = $campo;
                }
            }

            $camposProibidos = array_diff($camposMudados, ['atual']);
            if ($camposProibidos !== []) {
                throw new \RuntimeException(
                    'Não é possível modificar este plano pois existem contratos vinculados a ele. Apenas o campo "Plano Atual" pode ser alterado. Para outras alterações, marque como histórico e crie um novo plano.',
                );
            }
        }

        $update = [];
        foreach (['nome', 'descricao', 'valor', 'duracao_dias', 'max_alunos', 'max_admins', 'ordem'] as $campo) {
            if (isset($data[$campo])) {
                $update[$campo] = $data[$campo];
            }
        }
        if (isset($data['features'])) {
            $update['features'] = json_encode($data['features']);
        }
        if (isset($data['ativo'])) {
            $update['ativo'] = is_bool($data['ativo']) ? ($data['ativo'] ? 1 : 0) : $data['ativo'];
        }
        if (isset($data['atual'])) {
            $update['atual'] = is_bool($data['atual']) ? ($data['atual'] ? 1 : 0) : $data['atual'];
        }

        if ($update === []) {
            return false;
        }

        return DB::table('planos_sistema')->where('id', $id)->update($update) >= 0;
    }

    public function possuiContratos(int $id): bool
    {
        return DB::table('tenant_planos_sistema')
            ->where('plano_sistema_id', $id)
            ->where('status_id', '!=', 3)
            ->count() > 0;
    }

    public function marcarComoHistorico(int $id): bool
    {
        return DB::table('planos_sistema')->where('id', $id)->update(['atual' => 0]) >= 0;
    }

    public function desativar(int $id): bool
    {
        $ativos = DB::table('tenant_planos_sistema')
            ->where('plano_sistema_id', $id)
            ->where('status_id', 1)
            ->count();

        if ($ativos > 0) {
            throw new \RuntimeException(
                'Não é possível desativar este plano pois existem contratos ativos vinculados a ele.',
            );
        }

        return DB::table('planos_sistema')->where('id', $id)->update(['ativo' => 0]) >= 0;
    }

    public function contarContratosAtivos(int $id): int
    {
        return DB::table('tenant_planos_sistema')
            ->where('plano_sistema_id', $id)
            ->where('status_id', 1)
            ->count();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarAcademias(int $id): array
    {
        return DB::table('tenants as t')
            ->join('tenant_planos_sistema as tp', 't.id', '=', 'tp.tenant_id')
            ->join('status_contrato as sc', 'tp.status_id', '=', 'sc.id')
            ->where('tp.plano_sistema_id', $id)
            ->orderBy('tp.status_id')
            ->orderBy('t.nome')
            ->get([
                't.id',
                't.nome',
                't.cnpj',
                't.email',
                't.telefone',
                't.ativo',
                'sc.nome as status_contrato',
                'tp.data_inicio',
                'tp.status_id',
                'tp.created_at as contrato_criado_em',
            ])
            ->map(fn ($row) => (array) $row)
            ->all();
    }
}
