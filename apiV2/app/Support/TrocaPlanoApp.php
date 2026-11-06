<?php

namespace App\Support;

use App\Services\AppConfigService;

/**
 * Parâmetro troca_plano_app: com ele desligado (padrão), migrar de plano e cancelar o plano pelo app ficam
 * bloqueados. O aluno pede o cancelamento à academia, o admin cancela no painel (com ou sem crédito) e o
 * aluno contrata o plano novo pelo app, com o crédito abatido.
 */
final class TrocaPlanoApp
{
    public const CODIGO = 'TROCA_PLANO_PELA_ACADEMIA';

    public const MENSAGEM = 'A troca de plano é feita pela academia. Solicite o cancelamento do plano atual na recepção; '
        .'depois é só contratar o novo plano pelo app, com o crédito gerado (se houver) já abatido.';

    public static function permitida(?int $tenantId): bool
    {
        return app(AppConfigService::class)->parametro('troca_plano_app', $tenantId) === true;
    }

    /**
     * Bloqueio de um ciclo para o app exibir antes do aluno tentar pagar. Mesma regra da compra
     * (MobileCompraPlanoService) e da migração: com o parâmetro desligado e um plano ativo na modalidade,
     * só a renovação do mesmo plano e ciclo segue pelo app; trocar plano ou ciclo, em qualquer modo, é com a
     * academia. O app não decide nada: mostra titulo/mensagem, usa `rotulo` nas opções e `acao` no botão.
     *
     * @param  array{plano_id: int|string, plano_ciclo_id?: int|string|null}|null  $matriculaAtiva  ativa e vigente na modalidade
     * @return array{codigo: string, titulo: string, rotulo: string, mensagem: string, acao: string, modos: list<string>}|null
     */
    public static function bloqueioDoCiclo(?int $tenantId, ?array $matriculaAtiva, int $planoId, ?int $planoCicloId): ?array
    {
        if ($matriculaAtiva === null || self::permitida($tenantId)) {
            return null;
        }
        $mesmoPlanoCiclo = (int) $matriculaAtiva['plano_id'] === $planoId
            && (int) ($matriculaAtiva['plano_ciclo_id'] ?? 0) === (int) ($planoCicloId ?? 0);
        if ($mesmoPlanoCiclo) {
            return null;
        }

        return [
            'codigo' => self::CODIGO,
            'titulo' => 'Troca de plano pela academia',
            'rotulo' => 'Troca pela academia',
            'mensagem' => self::MENSAGEM,
            'acao' => 'Fale com a academia',
            'modos' => ['avista', 'assinatura'],
        ];
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public static function bloqueio(array $extra = []): array
    {
        return ['status' => 403, 'body' => ['success' => false, 'type' => 'info', 'code' => self::CODIGO, 'message' => self::MENSAGEM, 'error' => self::MENSAGEM] + $extra];
    }
}
