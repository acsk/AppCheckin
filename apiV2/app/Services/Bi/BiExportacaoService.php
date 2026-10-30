<?php

namespace App\Services\Bi;

use App\Support\AcademyDateTime;
use App\Support\Bi\Periodo;
use Throwable;

/**
 * Exportação do BI em CSV para Excel pt-BR (separador ";", vírgula decimal, BOM UTF-8).
 * Mesmos números das telas: as listas vêm de BiVisaoGeralService::itensDetalhe (sem o limite da tela)
 * e as séries mensais dos próprios services de cada tela.
 */
class BiExportacaoService
{
    /** Tela => [service, chave da lista no payload] */
    private const TELAS = [
        'financeiro' => [BiFinanceiroService::class, 'financeiro', 'mensal'],
        'alunos' => [BiAlunosService::class, 'alunos', 'mensal'],
        'retencao' => [BiRetencaoService::class, 'retencao', 'mensal'],
        'frequencia' => [BiFrequenciaService::class, 'frequencia', 'turmas'],
    ];

    /** Colunas na ordem de saída; só entram as que existem nos itens */
    private const COLUNAS = [
        'tenant_id' => 'ID', 'nome' => 'Academia', 'ativa' => 'Ativa',
        'mes' => 'Mês', 'parcela_id' => 'Parcela', 'aluno_nome' => 'Aluno', 'plano_nome' => 'Plano', 'modalidade_nome' => 'Modalidade',
        'matricula_id' => 'Matrícula', 'matricula_inicio' => 'Início da matrícula', 'valor' => 'Valor (R$)', 'data' => 'Data',
        'detalhe' => 'Situação', 'motivo' => 'Motivo', 'dias_sem_checkin' => 'Dias sem check-in', 'semana_recente' => 'Por semana (últimas 4)',
        'semana_antes' => 'Por semana (12 anteriores)', 'variacao' => 'Variação (%)',
        'modalidade' => 'Modalidade', 'horario' => 'Horário', 'professor' => 'Professor', 'aulas' => 'Aulas', 'checkins' => 'Check-ins',
        'media_por_aula' => 'Média por aula', 'limite' => 'Limite', 'ocupacao' => 'Ocupação (%)',
        'previsto' => 'Previsto (R$)', 'a_vencer' => 'A vencer (R$)', 'vencido' => 'Vencido (R$)', 'recebido' => 'Recebido (R$)',
        'estornado' => 'Estornado (R$)', 'pagantes' => 'Alunos que pagaram', 'inadimplencia' => 'Inadimplência (%)', 'ticket_medio' => 'Ticket médio (R$)',
        'ativos' => 'Alunos ativos', 'novos' => 'Novos', 'reativados' => 'Voltaram', 'saidas' => 'Saídas',
        'ativos_inicio' => 'Ativos no início', 'cancelaram' => 'Cancelaram', 'nao_renovaram' => 'Não renovaram', 'recuperados' => 'Recuperados',
        'evasao' => 'Evasão (%)',
        'alunos_ativos' => 'Alunos ativos', 'alunos_ativos_anterior' => 'Alunos ativos (período anterior)', 'novos_alunos' => 'Novos alunos',
        'receita_recebida' => 'Receita recebida (R$)', 'estornos' => 'Estornos (R$)', 'valor_vencido' => 'Vencido (R$)',
    ];

    private const DATAS = ['data', 'matricula_inicio'];

    public function __construct(
        private readonly BiVisaoGeralService $visaoGeral,
    ) {}

    /** @return array{status: int, body?: array<string, mixed>, csv?: string, arquivo?: string} */
    public function exportar(?int $tenantId, ?string $indicador, ?string $tela, ?string $preset, ?string $hoje = null): array
    {
        if (! $tenantId) {
            return ['status' => 400, 'body' => ['success' => false, 'error' => 'Academia não identificada']];
        }
        $hoje = $hoje ?: AcademyDateTime::today();
        $p = Periodo::resolver($preset, $hoje);

        try {
            if ($indicador !== null && $indicador !== '') {
                if (! in_array($indicador, BiVisaoGeralService::DETALHAVEIS, true)) {
                    return ['status' => 422, 'body' => ['success' => false, 'error' => 'Indicador sem exportação']];
                }
                $itens = $this->visaoGeral->itensDetalhe($tenantId, $indicador, $p, $hoje);
                $nome = $indicador;
            } elseif (isset(self::TELAS[$tela])) {
                [$classe, $metodo, $chave] = self::TELAS[$tela];
                $r = app($classe)->{$metodo}($tenantId, $p->preset, $hoje);
                if ($r['status'] !== 200) {
                    return $r;
                }
                $itens = $r['body']['data'][$chave];
                $nome = $tela;
            } else {
                return ['status' => 422, 'body' => ['success' => false, 'error' => 'Informe o indicador ou a tela']];
            }

            return ['status' => 200, 'csv' => self::csv($itens), 'arquivo' => "bi-{$nome}-{$p->inicio}-a-{$p->fim}.csv"];
        } catch (Throwable $e) {
            error_log('[BiExportacaoService::exportar] '.$e->getMessage());

            return ['status' => 500, 'body' => ['success' => false, 'error' => 'Não foi possível gerar o arquivo']];
        }
    }

    /**
     * @param  list<array<string, mixed>>  $itens
     * @param  list<string>|null  $ordem  colunas explícitas; sem ela, as de COLUNAS presentes nos itens
     */
    public static function csv(array $itens, ?array $ordem = null): string
    {
        $presentes = [];
        foreach ($itens as $i) {
            $presentes += array_fill_keys(array_keys($i), true);
        }
        $colunas = $ordem ?? array_values(array_filter(array_keys(self::COLUNAS), fn ($k) => isset($presentes[$k])));

        $f = fopen('php://temp', 'r+');
        fwrite($f, "\xEF\xBB\xBF");
        fputcsv($f, array_map(fn ($k) => self::COLUNAS[$k], $colunas), ';', '"', '');
        foreach ($itens as $i) {
            fputcsv($f, array_map(fn ($k) => self::celula($k, $i[$k] ?? null), $colunas), ';', '"', '');
        }
        rewind($f);
        $csv = stream_get_contents($f);
        fclose($f);

        return $csv;
    }

    private static function celula(string $coluna, mixed $v): string
    {
        if ($v === null) {
            return '';
        }
        if (is_bool($v)) {
            return $v ? 'Sim' : 'Não';
        }
        if (is_float($v) || is_int($v)) {
            return is_float($v) ? number_format($v, 2, ',', '') : (string) $v;
        }
        $v = (string) $v;
        if (in_array($coluna, self::DATAS, true) && preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $v, $m)) {
            return "{$m[3]}/{$m[2]}/{$m[1]}";
        }
        if ($coluna === 'mes' && preg_match('/^(\d{4})-(\d{2})$/', $v, $m)) {
            return "{$m[2]}/{$m[1]}";
        }

        // Texto começando com fórmula vira texto no Excel (injeção de fórmula)
        return preg_match('/^[=+\-@\t\r]/', $v) ? "'".$v : $v;
    }
}
