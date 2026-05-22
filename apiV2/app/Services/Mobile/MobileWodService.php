<?php

namespace App\Services\Mobile;

use App\Repositories\UsuarioRepository;
use App\Repositories\WodRepository;
use App\Support\AcademyDateTime;

class MobileWodService
{
    public function __construct(
        private readonly WodRepository $wods,
        private readonly UsuarioRepository $usuarios,
    ) {}

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function wodDoDia(
        int $userId,
        ?int $tenantId,
        ?string $dataParam,
        ?int $modalidadeParam,
        bool $modalidadeExplicita = false,
    ): array
    {
        if (! $tenantId) {
            return [
                'status' => 400,
                'body' => ['success' => false, 'error' => 'Nenhum tenant selecionado'],
            ];
        }

        $dataHoje = AcademyDateTime::today();
        if ($dataParam !== null && $dataParam !== '') {
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataParam)) {
                return [
                    'status' => 400,
                    'body' => [
                        'success' => false,
                        'error' => 'Formato de data inválido. Use YYYY-MM-DD',
                    ],
                ];
            }
            $dataHoje = $dataParam;
        }

        $usuario = $this->usuarios->findById($userId, $tenantId);
        if (! $usuario) {
            return [
                'status' => 404,
                'body' => ['success' => false, 'error' => 'Usuário não encontrado'],
            ];
        }

        $modalidadeId = $modalidadeExplicita
            ? $modalidadeParam
            : ($modalidadeParam ?? ($usuario['modalidade_id'] ?? null));

        $wod = $this->wods->findPublishedForDate(
            $tenantId,
            $dataHoje,
            $modalidadeId,
            ! $modalidadeExplicita,
        );

        if (! $wod) {
            return [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'data' => null,
                    'message' => 'Nenhum WOD agendado para esta data',
                ],
            ];
        }

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'data' => $this->formatarWod($wod),
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function wodsDoDia(?int $tenantId, ?string $dataParam): array
    {
        if (! $tenantId) {
            return [
                'status' => 400,
                'body' => ['success' => false, 'error' => 'Nenhum tenant selecionado'],
            ];
        }

        $dataHoje = AcademyDateTime::today();
        if ($dataParam !== null && $dataParam !== '') {
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataParam)) {
                return [
                    'status' => 400,
                    'body' => [
                        'success' => false,
                        'error' => 'Formato de data inválido. Use YYYY-MM-DD',
                    ],
                ];
            }
            $dataHoje = $dataParam;
        }

        $wods = $this->wods->listPublishedForDate($tenantId, $dataHoje);

        if ($wods === []) {
            return [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'data' => [],
                    'message' => 'Nenhum WOD agendado para esta data',
                ],
            ];
        }

        $formatados = array_map(fn (array $wod) => $this->formatarWodComModalidade($wod), $wods);

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'data' => $formatados,
                'total' => count($formatados),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $wod
     * @return array<string, mixed>
     */
    private function formatarWod(array $wod): array
    {
        $wodId = (int) $wod['id'];
        $blocos = $this->wods->listBlocosByWod($wodId);
        $variacoes = $this->wods->listVariacoesByWod($wodId);

        return [
            'id' => $wodId,
            'titulo' => $wod['titulo'],
            'descricao' => $wod['descricao'],
            'data' => $wod['data'],
            'status' => $wod['status'],
            'modalidade_id' => ! empty($wod['modalidade_id']) ? (int) $wod['modalidade_id'] : null,
            'blocos' => array_map(static fn (array $b) => [
                'id' => (int) $b['id'],
                'ordem' => (int) $b['ordem'],
                'tipo' => $b['tipo'],
                'titulo' => $b['titulo'],
                'conteudo' => $b['conteudo'],
                'tempo_cap' => $b['tempo_cap'],
            ], $blocos),
            'variacoes' => array_map(static fn (array $v) => [
                'id' => (int) $v['id'],
                'nome' => $v['nome'],
                'descricao' => $v['descricao'],
            ], $variacoes),
        ];
    }

    /**
     * @param  array<string, mixed>  $wod
     * @return array<string, mixed>
     */
    private function formatarWodComModalidade(array $wod): array
    {
        $base = $this->formatarWod($wod);
        unset($base['modalidade_id']);

        $base['modalidade'] = ! empty($wod['modalidade_id'])
            ? [
                'id' => (int) $wod['modalidade_id'],
                'nome' => $wod['modalidade_nome'] ?? null,
                'descricao' => $wod['modalidade_descricao'] ?? null,
                'cor' => $wod['modalidade_cor'] ?? null,
                'icone' => $wod['modalidade_icone'] ?? null,
                'ativo' => (bool) ($wod['modalidade_ativo'] ?? false),
            ]
            : null;

        return $base;
    }
}
