<?php

namespace App\Services\Mobile;

use App\Repositories\CheckinRepository;
use App\Support\AcademyDateTime;

class MobileHistoricoService
{
    public function __construct(
        private readonly CheckinRepository $checkins,
    ) {}

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function historicoCheckins(int $userId, int $limit, int $offset): array
    {
        $limit = min(max($limit, 1), 100);
        $offset = max($offset, 0);

        $result = $this->checkins->historicoCheckins($userId, $limit, $offset);

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'data' => [
                    'checkins' => $result['checkins'],
                    'total' => $result['total'],
                    'limit' => $limit,
                    'offset' => $offset,
                ],
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>, headers?: array<string, string>}
     */
    public function checkinsPorModalidade(
        int $userId,
        ?int $tenantId,
        ?string $dataReferencia,
        int $offsetSemanas,
    ): array {
        if (! $tenantId) {
            return [
                'status' => 400,
                'body' => [
                    'success' => false,
                    'error' => 'Nenhum tenant selecionado',
                ],
            ];
        }

        $ref = $dataReferencia
            ? AcademyDateTime::fromDateAndTime($dataReferencia, '12:00:00')
            : AcademyDateTime::now();

        if ($dataReferencia && ! $ref) {
            return [
                'status' => 400,
                'body' => [
                    'success' => false,
                    'error' => 'Formato de data inválido. Use YYYY-MM-DD',
                ],
            ];
        }

        $ref = $ref ?? AcademyDateTime::now();
        if ($offsetSemanas !== 0) {
            $ref->modify("{$offsetSemanas} weeks");
        }

        $diaSemana = (int) $ref->format('w');
        $domingo = clone $ref;
        $domingo->modify("-{$diaSemana} days");
        $sabado = clone $domingo;
        $sabado->modify('+6 days');

        $semanaInicio = $domingo->format('Y-m-d');
        $semanaFim = $sabado->format('Y-m-d');

        $rows = $this->checkins->checkinsSemanaPorModalidade(
            $userId,
            $tenantId,
            $semanaInicio,
            $semanaFim,
        );

        $dias = [];
        $modalidadesMap = [];

        foreach ($rows as $c) {
            $modId = (int) ($c['modalidade_id'] ?? 0);
            $modNome = $c['modalidade_nome'] ?? 'Outro';
            $modCor = $c['modalidade_cor'] ?? '#999999';
            $modIcone = $c['modalidade_icone'] ?? null;

            $dias[] = [
                'data' => $c['data'],
                'modalidade' => [
                    'id' => $modId,
                    'nome' => $modNome,
                    'cor' => $modCor,
                    'icone' => $modIcone,
                ],
            ];

            if (! isset($modalidadesMap[$modId])) {
                $modalidadesMap[$modId] = [
                    'id' => $modId,
                    'nome' => $modNome,
                    'cor' => $modCor,
                    'icone' => $modIcone,
                    'total' => 0,
                ];
            }
            $modalidadesMap[$modId]['total']++;
        }

        $modalidades = array_values($modalidadesMap);
        usort($modalidades, static fn ($a, $b) => $b['total'] <=> $a['total']);

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'data' => [
                    'semana_inicio' => $semanaInicio,
                    'semana_fim' => $semanaFim,
                    'total' => count($dias),
                    'dias' => $dias,
                    'modalidades' => $modalidades,
                ],
            ],
            'headers' => [
                'Cache-Control' => 'private, max-age=300',
                'Vary' => 'Authorization, X-Tenant-Id',
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>, headers?: array<string, string>}
     */
    public function rankingMensal(?int $tenantId, ?int $modalidadeId): array
    {
        if (! $tenantId) {
            return [
                'status' => 400,
                'body' => [
                    'success' => false,
                    'error' => 'Nenhum tenant selecionado',
                ],
            ];
        }

        $periodo = AcademyDateTime::currentMonthYear();
        $mes = $periodo['mes'];
        $ano = $periodo['ano'];

        $totalTenantMes = $this->checkins->contarCheckinsTenantMes($tenantId, $mes, $ano);
        $ranking = $this->checkins->rankingMesAtual($tenantId, 3, $modalidadeId, $mes, $ano);

        $rankingFormatado = [];
        foreach ($ranking as $index => $item) {
            $rankingFormatado[] = [
                'posicao' => $index + 1,
                'aluno' => [
                    'id' => (int) $item['aluno_id'],
                    'nome' => $item['nome'],
                    'email' => $item['email'],
                    'foto_caminho' => $item['foto_caminho'] ?? null,
                ],
                'total_checkins' => (int) $item['total_checkins'],
            ];
        }

        $mesAtualNome = self::nomeMes($mes);

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'data' => [
                    'periodo' => "{$mesAtualNome}/{$ano}",
                    'mes' => $mes,
                    'ano' => $ano,
                    'modalidade_id' => $modalidadeId,
                    'ranking' => $rankingFormatado,
                    'tenant_id_resolvido' => $tenantId,
                    'total_checkins_tenant_mes' => $totalTenantMes,
                ],
            ],
            'headers' => [
                'Cache-Control' => 'private, max-age=300',
                'Vary' => 'Authorization, X-Tenant-Id',
            ],
        ];
    }

    private static function nomeMes(int $mes): string
    {
        $meses = [
            1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
            5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
            9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
        ];

        return $meses[$mes] ?? AcademyDateTime::now()->format('F');
    }
}
