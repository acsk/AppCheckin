<?php

namespace App\Services\SuperAdmin;

use App\Repositories\PlanoSistemaRepository;
use App\Support\TipoCobrancaPlanoSistema;

class SuperAdminPlanoSistemaService
{
    public function __construct(
        private readonly PlanoSistemaRepository $planos,
    ) {}

    /**
     * @param  array<string, mixed>  $query
     * @return array{status: int, body: array<string, mixed>}
     */
    public function index(array $query): array
    {
        $apenasAtivos = isset($query['ativos']) && $query['ativos'] === 'true';
        $apenasAtuais = isset($query['apenas_atuais']) && $query['apenas_atuais'] === 'true';

        $planos = $this->planos->listarTodos($apenasAtivos);

        if ($apenasAtuais) {
            $planos = array_values(array_filter(
                $planos,
                fn (array $plano) => ($plano['atual'] ?? 0) == 1,
            ));
        }

        return [
            'status' => 200,
            'body' => ['planos' => $planos, 'total' => count($planos)],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function disponiveis(): array
    {
        $planos = $this->planos->listarDisponiveis();

        return [
            'status' => 200,
            'body' => ['planos' => $planos, 'total' => count($planos)],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function show(int $id): array
    {
        $plano = $this->planos->buscarPorId($id);
        if (! $plano) {
            return ['status' => 404, 'body' => ['error' => 'Plano não encontrado']];
        }

        $plano['contratos_ativos'] = $this->planos->contarContratosAtivos($id);

        return ['status' => 200, 'body' => $plano];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function academias(int $id): array
    {
        $plano = $this->planos->buscarPorId($id);
        if (! $plano) {
            return ['status' => 404, 'body' => ['error' => 'Plano não encontrado']];
        }

        $academias = $this->planos->listarAcademias($id);

        return [
            'status' => 200,
            'body' => ['academias' => $academias, 'total' => count($academias)],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function create(array $data): array
    {
        $data['tipo_cobranca'] ??= TipoCobrancaPlanoSistema::FIXO;
        $data = $this->normalizarCobranca($data);

        $errors = [];
        if (empty($data['nome'])) {
            $errors[] = 'Nome é obrigatório';
        }
        $errors = array_merge($errors, $this->validarCobranca($data));
        if (! empty($data['codigo']) && $this->planos->codigoEmUso((string) $data['codigo'])) {
            $errors[] = 'Já existe um plano com este código';
        }
        if ($errors !== []) {
            return ['status' => 422, 'body' => ['errors' => $errors]];
        }

        try {
            $planoId = $this->planos->criar($data);

            return [
                'status' => 201,
                'body' => [
                    'message' => 'Plano criado com sucesso',
                    'plano' => $this->planos->buscarPorId($planoId),
                ],
            ];
        } catch (\Throwable $e) {
            return ['status' => 500, 'body' => ['error' => 'Erro ao criar plano: '.$e->getMessage()]];
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function update(int $id, array $data): array
    {
        $plano = $this->planos->buscarPorId($id);
        if (! $plano) {
            return [
                'status' => 404,
                'body' => ['type' => 'error', 'message' => 'Plano não encontrado'],
            ];
        }

        $data = $this->normalizarCobranca($data);
        $errors = $this->validarCobranca(array_merge($plano, $data));
        if (! empty($data['codigo']) && $this->planos->codigoEmUso((string) $data['codigo'], $id)) {
            $errors[] = 'Já existe um plano com este código';
        }
        if ($errors !== []) {
            return [
                'status' => 422,
                'body' => ['type' => 'error', 'message' => implode('. ', $errors), 'errors' => $errors],
            ];
        }

        try {
            $this->planos->atualizar($id, $data);

            return [
                'status' => 200,
                'body' => [
                    'type' => 'success',
                    'message' => 'Plano atualizado com sucesso',
                    'plano' => $this->planos->buscarPorId($id),
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 400,
                'body' => ['type' => 'error', 'message' => $e->getMessage()],
            ];
        }
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function marcarHistorico(int $id): array
    {
        $plano = $this->planos->buscarPorId($id);
        if (! $plano) {
            return ['status' => 404, 'body' => ['error' => 'Plano não encontrado']];
        }

        $this->planos->marcarComoHistorico($id);

        return [
            'status' => 200,
            'body' => ['message' => 'Plano marcado como histórico. Não estará mais disponível para novos contratos.'],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function delete(int $id): array
    {
        $plano = $this->planos->buscarPorId($id);
        if (! $plano) {
            return ['status' => 404, 'body' => ['error' => 'Plano não encontrado']];
        }

        try {
            $this->planos->desativar($id);

            return ['status' => 200, 'body' => ['message' => 'Plano desativado com sucesso']];
        } catch (\Throwable $e) {
            return ['status' => 400, 'body' => ['error' => $e->getMessage()]];
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizarCobranca(array $data): array
    {
        foreach (['valor_por_aluno', 'max_alunos', 'max_admins', 'prazo_dias', 'codigo'] as $campo) {
            if (array_key_exists($campo, $data) && ($data[$campo] === '' || $data[$campo] === null)) {
                $data[$campo] = null;
            }
        }

        $tipo = $data['tipo_cobranca'] ?? null;
        if ($tipo === TipoCobrancaPlanoSistema::GRATUITO) {
            $data['valor'] = 0;
            $data['valor_por_aluno'] = null;
        } elseif ($tipo === TipoCobrancaPlanoSistema::POR_ALUNO) {
            $data['valor'] = 0;
        } elseif ($tipo === TipoCobrancaPlanoSistema::FIXO) {
            $data['valor_por_aluno'] = null;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $plano  Plano completo (atual + alterações) ou payload de criação
     * @return list<string>
     */
    private function validarCobranca(array $plano): array
    {
        $tipo = $plano['tipo_cobranca'] ?? TipoCobrancaPlanoSistema::FIXO;
        if (! TipoCobrancaPlanoSistema::valido($tipo)) {
            return [TipoCobrancaPlanoSistema::mensagemInvalido()];
        }

        $errors = [];
        foreach (['max_alunos' => 'Limite de alunos', 'max_admins' => 'Limite de administradores', 'prazo_dias' => 'Prazo do contrato'] as $campo => $rotulo) {
            $v = $plano[$campo] ?? null;
            if ($v !== null && (! is_numeric($v) || (int) $v < 1 || (float) $v != (int) $v)) {
                $errors[] = $rotulo.' deve ser um número inteiro maior que zero, ou vazio para sem limite';
            }
        }
        if (isset($plano['duracao_dias']) && (! is_numeric($plano['duracao_dias']) || (int) $plano['duracao_dias'] < 1)) {
            $errors[] = 'Ciclo de cobrança deve ter pelo menos 1 dia';
        }
        if (! empty($plano['codigo']) && preg_match('/^[a-z0-9_-]{2,30}$/', (string) $plano['codigo']) !== 1) {
            $errors[] = 'Código deve ter de 2 a 30 letras minúsculas, números, - ou _';
        }
        if ($tipo === TipoCobrancaPlanoSistema::FIXO
            && (! isset($plano['valor']) || ! is_numeric($plano['valor']) || $plano['valor'] < 0)) {
            $errors[] = 'Valor deve ser maior ou igual a zero';
        }
        if ($tipo === TipoCobrancaPlanoSistema::POR_ALUNO
            && (! isset($plano['valor_por_aluno']) || ! is_numeric($plano['valor_por_aluno']) || $plano['valor_por_aluno'] <= 0)) {
            $errors[] = 'Valor por aluno deve ser maior que zero';
        }

        return $errors;
    }
}
