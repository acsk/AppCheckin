<?php

namespace App\Services\Admin;

use App\Repositories\AdminPlanoRepository;

class AdminPlanoService
{
    /** Campos que mudam a regra de quem já está no plano: travados enquanto houver matrículas. */
    public const CAMPOS_TRAVADOS = [
        'valor' => 'o valor',
        'duracao_dias' => 'a duração',
        'checkins_semanais' => 'o número de aulas por semana',
        'modalidade_id' => 'a modalidade',
    ];

    public function __construct(
        private readonly AdminPlanoRepository $planos,
    ) {}

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function index(int $tenantId, mixed $ativosParam): array
    {
        $apenasAtivos = false;
        if ($ativosParam !== null && $ativosParam !== '') {
            $apenasAtivos = filter_var($ativosParam, FILTER_VALIDATE_BOOLEAN);
        }

        $planos = $this->planos->listar($tenantId, $apenasAtivos);

        return [
            'status' => 200,
            'body' => [
                'planos' => $planos,
                'total' => count($planos),
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function show(int $id, int $tenantId): array
    {
        try {
            $plano = $this->planos->findById($id, $tenantId);
            if (! $plano) {
                return ['status' => 404, 'body' => ['error' => 'Plano não encontrado']];
            }

            // Com matrículas, o painel trava os campos que mudam a regra de quem já está no plano
            $comMatriculas = $this->planos->possuiContratos($id, $tenantId);

            return ['status' => 200, 'body' => $plano + [
                'possui_contratos' => $comMatriculas,
                'campos_travados' => $comMatriculas ? array_keys(self::CAMPOS_TRAVADOS) : [],
            ]];
        } catch (\Throwable $e) {
            return [
                'status' => 500,
                'body' => ['error' => 'Erro ao buscar plano: '.$e->getMessage()],
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function create(int $tenantId, array $data): array
    {
        $errors = [];
        if (empty($data['modalidade_id'])) {
            $errors[] = 'Modalidade é obrigatória';
        }
        if (empty($data['nome'])) {
            $errors[] = 'Nome é obrigatório';
        }
        if (! isset($data['valor']) || $data['valor'] < 0) {
            $errors[] = 'Valor deve ser maior ou igual a zero';
        }
        if (empty($data['checkins_semanais']) || $data['checkins_semanais'] < 1) {
            $errors[] = 'Checkins semanais deve ser maior que zero';
        }
        if ($errors !== []) {
            return ['status' => 422, 'body' => ['errors' => $errors]];
        }

        if ($this->planos->existeDuplicado(
            $tenantId,
            (int) $data['modalidade_id'],
            (string) $data['nome'],
            $data['valor'],
            (int) $data['checkins_semanais'],
            (int) ($data['duracao_dias'] ?? 30),
        )) {
            return [
                'status' => 422,
                'body' => ['error' => 'Já existe um plano com essas características nesta modalidade'],
            ];
        }

        $id = $this->planos->create($tenantId, $data);
        $plano = $this->planos->findById($id, $tenantId);

        return [
            'status' => 201,
            'body' => [
                'message' => 'Plano criado com sucesso',
                'plano' => $plano,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function update(int $id, int $tenantId, array $data): array
    {
        $plano = $this->planos->findById($id, $tenantId);
        if (! $plano) {
            return ['status' => 404, 'body' => ['error' => 'Plano não encontrado']];
        }

        // Com matrículas (não canceladas) só travam valor, duração, aulas por semana e modalidade, e só se
        // mudarem: nome, descrição, ativo e "aceita novas matrículas" continuam editáveis.
        $mudaram = [];
        foreach (array_keys(self::CAMPOS_TRAVADOS) as $k) {
            if (! array_key_exists($k, $data)) {
                continue;
            }
            $novo = $k === 'valor' ? round((float) $data[$k], 2) : (int) $data[$k];
            $atual = $k === 'valor' ? round((float) $plano[$k], 2) : (int) $plano[$k];
            if ($novo !== $atual) {
                $mudaram[] = $k;
            }
        }
        if ($mudaram !== [] && $this->planos->possuiContratos($id, $tenantId)) {
            $nomes = implode(', ', array_map(fn ($k) => self::CAMPOS_TRAVADOS[$k], $mudaram));

            return [
                'status' => 409,
                'body' => [
                    'error' => "Este plano tem matrículas, então {$nomes} não pode mudar (mudaria a regra de quem já está nele). Para mudar, crie um novo plano e desmarque \"Aceita novas matrículas\" neste.",
                    'code' => 'PLANO_COM_MATRICULAS',
                    'campos_travados' => $mudaram,
                ],
            ];
        }

        if (isset($data['modalidade_id'], $data['nome'], $data['valor'], $data['checkins_semanais'])) {
            if ($this->planos->existeDuplicado(
                $tenantId,
                (int) $data['modalidade_id'],
                (string) $data['nome'],
                $data['valor'],
                (int) $data['checkins_semanais'],
                (int) ($data['duracao_dias'] ?? 30),
                $id,
            )) {
                return [
                    'status' => 422,
                    'body' => ['error' => 'Já existe um plano com essas características nesta modalidade'],
                ];
            }
        }

        if (! $this->planos->update($id, $tenantId, $data)) {
            return ['status' => 400, 'body' => ['error' => 'Nenhum dado foi atualizado']];
        }

        return [
            'status' => 200,
            'body' => [
                'message' => 'Plano atualizado com sucesso',
                'plano' => $this->planos->findById($id, $tenantId),
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function delete(int $id, int $tenantId): array
    {
        $total = $this->planos->countUsuariosAtivos($id, $tenantId);
        if ($total > 0) {
            return [
                'status' => 400,
                'body' => [
                    'error' => "Não é possível desativar. {$total} usuário(s) estão usando este plano.",
                ],
            ];
        }

        if (! $this->planos->softDelete($id, $tenantId)) {
            return ['status' => 400, 'body' => ['error' => 'Erro ao desativar plano']];
        }

        return [
            'status' => 200,
            'body' => ['message' => 'Plano desativado com sucesso'],
        ];
    }
}
