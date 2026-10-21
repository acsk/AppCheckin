<?php

namespace App\Services\Admin\Prospeccao;

use App\Repositories\ProspeccaoRepository;
use App\Support\ProspeccaoStatus;
use Illuminate\Support\Facades\Log;

/**
 * Modelos de abordagem com variáveis {nome}, {modalidade}, {plano_anterior}, {estabelecimento}.
 * A substituição é feita no painel, na hora de abrir o WhatsApp ou copiar o texto.
 */
class ProspeccaoModeloMensagemService
{
    public const VARIAVEIS = ['nome', 'modalidade', 'plano_anterior', 'estabelecimento'];

    /** Criados na primeira vez que o tenant abre os modelos. */
    public const PADRAO = [
        [
            'nome' => 'Primeiro contato',
            'etapa' => 'primeiro_contato',
            'conteudo' => "Olá, {nome}! Tudo bem? 😊\n\nAqui é da equipe do {estabelecimento}.\n\nSentimos sua falta nas nossas aulas! Estamos com novidades e gostaríamos de saber se você tem interesse em retornar.\n\nPodemos apresentar as opções disponíveis?",
        ],
        [
            'nome' => 'Aluno interessado',
            'etapa' => 'interessado',
            'conteudo' => 'Olá, {nome}! Temos algumas opções para você voltar às aulas de {modalidade}. Gostaria de conhecer os horários e planos disponíveis?',
        ],
        [
            'nome' => 'Retomada de negociação',
            'etapa' => 'negociacao',
            'conteudo' => 'Olá, {nome}! Passando para saber se conseguiu avaliar as opções que conversamos. Caso tenha alguma dúvida, estamos à disposição!',
        ],
    ];

    public function __construct(
        private readonly ProspeccaoRepository $repo,
    ) {}

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function listar(int $tenantId, bool $apenasAtivos): array
    {
        if ($this->repo->contarModelos($tenantId) === 0) {
            $this->repo->inserirModelosPadrao($tenantId, self::PADRAO);
        }

        return ['status' => 200, 'body' => ['modelos' => $this->repo->modelos($tenantId, $apenasAtivos), 'variaveis' => self::VARIAVEIS]];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function salvar(int $tenantId, ?int $id, array $data, array $ctx): array
    {
        if ($id && ! $this->repo->buscarModelo($tenantId, $id)) {
            return $this->erro('Modelo não encontrado', 404);
        }
        $nome = trim((string) ($data['nome'] ?? ''));
        $conteudo = trim((string) ($data['conteudo'] ?? ''));
        $etapa = (string) ($data['etapa'] ?? 'outro');
        if ($nome === '' || mb_strlen($nome) > 100) {
            return $this->erro('Informe um nome de até 100 caracteres', 422);
        }
        if ($conteudo === '' || mb_strlen($conteudo) > 2000) {
            return $this->erro('Informe o texto da mensagem (até 2000 caracteres)', 422);
        }
        if (! isset(ProspeccaoStatus::ETAPAS_MODELO[$etapa])) {
            return $this->erro('Etapa inválida', 422);
        }
        preg_match_all('/\{([a-z_]+)\}/', $conteudo, $m);
        $desconhecidas = array_diff(array_unique($m[1]), self::VARIAVEIS);
        if ($desconhecidas !== []) {
            return $this->erro('Variável desconhecida: {'.implode('}, {', $desconhecidas).'}. Use {'.implode('}, {', self::VARIAVEIS).'}', 422);
        }
        if ($this->repo->nomeModeloEmUso($tenantId, $nome, $id)) {
            return $this->erro('Já existe um modelo com esse nome', 409);
        }

        $dados = ['nome' => $nome, 'etapa' => $etapa, 'conteudo' => $conteudo, 'ativo' => filter_var($data['ativo'] ?? true, FILTER_VALIDATE_BOOLEAN), 'atualizado_por' => $ctx['usuario_id']];
        if (! $id) {
            $dados['criado_por'] = $ctx['usuario_id'];
        }
        $salvoId = $this->repo->salvarModelo($tenantId, $id, $dados);
        Log::info('prospeccao.modelo', ['tenant_id' => $tenantId, 'modelo_id' => $salvoId, 'usuario_id' => $ctx['usuario_id'], 'acao' => $id ? 'editar' : 'criar']);

        return ['status' => $id ? 200 : 201, 'body' => ['modelo' => $this->repo->buscarModelo($tenantId, $salvoId)]];
    }

    /**
     * Modelo já usado em contato é desativado em vez de excluído (preserva o histórico).
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function excluir(int $tenantId, int $id, array $ctx): array
    {
        if (! $this->repo->buscarModelo($tenantId, $id)) {
            return $this->erro('Modelo não encontrado', 404);
        }
        if ($this->repo->modeloUsado($tenantId, $id)) {
            $this->repo->salvarModelo($tenantId, $id, ['ativo' => false, 'atualizado_por' => $ctx['usuario_id']]);

            return ['status' => 200, 'body' => ['message' => 'Modelo já usado em contatos: foi desativado para preservar o histórico', 'desativado' => true]];
        }
        $this->repo->excluirModelo($tenantId, $id);
        Log::info('prospeccao.modelo', ['tenant_id' => $tenantId, 'modelo_id' => $id, 'usuario_id' => $ctx['usuario_id'], 'acao' => 'excluir']);

        return ['status' => 200, 'body' => ['message' => 'Modelo excluído', 'desativado' => false]];
    }

    /** @return array{status: int, body: array{error: string}} */
    private function erro(string $mensagem, int $status): array
    {
        return ['status' => $status, 'body' => ['error' => $mensagem]];
    }
}
