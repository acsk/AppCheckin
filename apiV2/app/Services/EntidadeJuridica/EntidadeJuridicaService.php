<?php

namespace App\Services\EntidadeJuridica;

use App\Repositories\EntidadeJuridicaRepository;
use App\Support\Documento;
use App\Support\PoliticaCnpj;
use App\Support\VinculoCnpj;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * CNPJ compartilhado entre tenants. Único ponto que cria/encerra vínculos, troca o gestor cadastral,
 * edita dados jurídicos e mexe no espelho tenants.cnpj.
 *
 * Regras:
 * - Um CNPJ = uma entidade jurídica (UNIQUE). Um tenant tem no máximo um vínculo ativo.
 * - O primeiro tenant de um CNPJ sem vínculos ativos vira gestor; os demais entram como vinculados.
 *   Quando o gestor sai, o CNPJ fica sem gestor até o super admin definir outro (ninguém assume sozinho).
 * - Compartilhar (2º tenant em diante) depende da política global; em "exceção", de uma exceção vigente.
 * - Revogar a exceção impede novos vínculos, mas preserva os existentes.
 * - Vínculo e troca de gestor travam a entidade (lockForUpdate) e o UNIQUE de gestor_ativo garante
 *   no banco que não existem dois gestores ativos.
 */
class EntidadeJuridicaService
{
    /** Tentativas em deadlock (dois cadastros simultâneos do mesmo CNPJ novo disputam o gap lock) */
    private const TENTATIVAS = 3;

    public function __construct(
        private readonly EntidadeJuridicaRepository $repo,
    ) {}

    public static function normalizar(?string $cnpj): string
    {
        return Documento::digitos($cnpj);
    }

    public static function formatar(string $cnpj): string
    {
        return strlen($cnpj) === 14
            ? preg_replace('/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/', '$1.$2.$3/$4-$5', $cnpj)
            : $cnpj;
    }

    /** Mensagem de erro, ou null quando o CNPJ (já normalizado) é válido. */
    public static function erroCnpj(string $cnpj): ?string
    {
        if (strlen($cnpj) !== 14) {
            return 'CNPJ inválido. Deve conter 14 dígitos';
        }

        return Documento::cnpjValido($cnpj) ? null : 'CNPJ inválido: os dígitos verificadores não conferem';
    }

    // ------------------------------------------------------------------ política

    public function politica(): string
    {
        return $this->repo->politica();
    }

    public function definirPolitica(string $politica, ?int $usuarioId, ?string $ip = null): string
    {
        if (! PoliticaCnpj::valida($politica)) {
            throw new EntidadeJuridicaException('Política inválida. Use: '.implode(', ', PoliticaCnpj::TODAS), 422, 'POLITICA_INVALIDA');
        }

        $anterior = $this->repo->politica();
        if ($anterior !== $politica) {
            DB::transaction(function () use ($politica, $anterior, $usuarioId, $ip) {
                $this->repo->salvarPolitica($politica, $usuarioId);
                $this->repo->registrarAuditoria([
                    'usuario_id' => $usuarioId, 'acao' => 'politica_alterada', 'ip' => $ip,
                    'dados_anteriores' => ['politica' => $anterior], 'dados_novos' => ['politica' => $politica],
                ]);
            });
        }

        return $politica;
    }

    /**
     * Pré-checagem sem travas (para devolver 422 antes de criar a academia). A decisão final é
     * refeita com a entidade travada em vincular().
     *
     * @return array{code: string, message: string}|null
     */
    public function avaliarCompartilhamento(string $cnpj, ?int $tenantId = null): ?array
    {
        $entidade = $this->repo->buscarPorCnpj($cnpj);
        if (! $entidade || $this->repo->contarVinculosAtivos((int) $entidade['id'], $tenantId) === 0) {
            return null;
        }

        try {
            $this->exigirCompartilhamentoPermitido($entidade);

            return null;
        } catch (EntidadeJuridicaException $e) {
            return ['code' => $e->codigo, 'message' => $e->getMessage()];
        }
    }

    /** @param  array<string, mixed>  $entidade */
    private function exigirCompartilhamentoPermitido(array $entidade): void
    {
        $politica = $this->repo->politica();
        if ($politica === PoliticaCnpj::PERMITIR) {
            return;
        }
        if ($politica === PoliticaCnpj::BLOQUEAR) {
            throw new EntidadeJuridicaException(
                'Este CNPJ já está em uso por outra academia e o compartilhamento de CNPJ está bloqueado.',
                409, 'CNPJ_DUPLICADO_BLOQUEADO',
            );
        }
        if (! $this->repo->excecaoVigente((int) $entidade['id'])) {
            throw new EntidadeJuridicaException(
                'Este CNPJ já está vinculado a outra academia. O compartilhamento precisa ser autorizado pelo Super Admin.',
                409, 'CNPJ_DUPLICADO_SEM_EXCECAO',
            );
        }
    }

    // ------------------------------------------------------------------ vínculos

    /**
     * Define o CNPJ do tenant: vazio encerra o vínculo atual; outro CNPJ troca de entidade.
     *
     * @return array<string, mixed>|null vínculo ativo resultante
     */
    public function definirCnpjDoTenant(int $tenantId, ?string $cnpj, ?int $usuarioId, string $origem, ?string $ip = null): ?array
    {
        $cnpj = self::normalizar($cnpj);
        if ($cnpj === '') {
            $this->desvincular($tenantId, $usuarioId, $origem, $ip);

            return null;
        }

        return $this->vincular($tenantId, $cnpj, $usuarioId, $origem, $ip);
    }

    /**
     * @param  bool  $somenteSemCnpj  true quando quem pede é o próprio tenant: ele pode informar o primeiro
     *                                CNPJ, mas trocar um já vinculado é só pelo super admin
     * @return array<string, mixed> vínculo ativo (id, papel, entidade_juridica_id, cnpj)
     *
     * @throws EntidadeJuridicaException
     */
    public function vincular(int $tenantId, string $cnpj, ?int $usuarioId, string $origem, ?string $ip = null, bool $somenteSemCnpj = false): array
    {
        $cnpj = self::normalizar($cnpj);
        if (($erro = self::erroCnpj($cnpj)) !== null) {
            throw new EntidadeJuridicaException($erro, 422, 'CNPJ_INVALIDO');
        }

        return DB::transaction(function () use ($tenantId, $cnpj, $usuarioId, $origem, $ip, $somenteSemCnpj) {
            if (! $this->repo->travarTenant($tenantId)) {
                throw new EntidadeJuridicaException('Academia não encontrada', 404, 'TENANT_NAO_ENCONTRADO');
            }

            $atual = $this->repo->vinculoAtivoDoTenant($tenantId, true);
            if ($atual && $atual['cnpj'] === $cnpj) {
                return $atual;
            }
            if ($atual && $somenteSemCnpj) {
                throw new EntidadeJuridicaException(
                    'A academia já tem CNPJ cadastrado. Para trocar, fale com o suporte do Check-in.',
                    403, 'CNPJ_IMUTAVEL',
                );
            }

            $entidade = $this->entidadeTravada($cnpj, $usuarioId);
            $entidadeId = (int) $entidade['id'];
            $outros = $this->repo->contarVinculosAtivos($entidadeId, $tenantId);
            if ($outros > 0) {
                $this->exigirCompartilhamentoPermitido($entidade);
            }

            if ($atual) {
                $this->encerrar($atual, $usuarioId, $origem, $ip);
            }

            $papel = $outros === 0 ? VinculoCnpj::GESTOR : VinculoCnpj::VINCULADO;
            $this->repo->criarVinculo($tenantId, $entidadeId, $papel);
            $this->repo->atualizarEspelhoTenant($tenantId, $cnpj);
            $this->repo->registrarAuditoria([
                'entidade_juridica_id' => $entidadeId, 'cnpj' => $cnpj, 'tenant_id' => $tenantId,
                'usuario_id' => $usuarioId, 'acao' => 'vinculo_criado', 'ip' => $ip,
                'dados_novos' => ['papel' => $papel, 'origem' => $origem, 'compartilhado' => $outros > 0],
            ]);

            return $this->repo->vinculoAtivoDoTenant($tenantId);
        }, self::TENTATIVAS);
    }

    public function desvincular(int $tenantId, ?int $usuarioId, string $origem, ?string $ip = null): void
    {
        DB::transaction(function () use ($tenantId, $usuarioId, $origem, $ip) {
            $this->repo->travarTenant($tenantId);
            $atual = $this->repo->vinculoAtivoDoTenant($tenantId, true);
            if (! $atual) {
                return;
            }
            $this->repo->buscarPorId((int) $atual['entidade_juridica_id'], true);
            $this->encerrar($atual, $usuarioId, $origem, $ip);
            $this->repo->atualizarEspelhoTenant($tenantId, null);
        }, self::TENTATIVAS);
    }

    /** @param  array<string, mixed>  $vinculo */
    private function encerrar(array $vinculo, ?int $usuarioId, string $origem, ?string $ip): void
    {
        $entidadeId = (int) $vinculo['entidade_juridica_id'];
        $this->repo->encerrarVinculo((int) $vinculo['id']);
        $restantes = $this->repo->contarVinculosAtivos($entidadeId);
        $this->repo->registrarAuditoria([
            'entidade_juridica_id' => $entidadeId, 'cnpj' => $vinculo['cnpj'] ?? null, 'tenant_id' => (int) $vinculo['tenant_id'],
            'usuario_id' => $usuarioId, 'acao' => 'vinculo_encerrado', 'ip' => $ip,
            'dados_anteriores' => ['papel' => $vinculo['papel']],
            'dados_novos' => ['origem' => $origem, 'gestor_vago' => $vinculo['papel'] === VinculoCnpj::GESTOR && $restantes > 0],
        ]);
    }

    /**
     * Entidade do CNPJ travada para a transação; cria se não existir. Se outra requisição criar no
     * meio do caminho, o UNIQUE de cnpj recusa o insert e a entidade dela é usada.
     *
     * @return array<string, mixed>
     */
    private function entidadeTravada(string $cnpj, ?int $usuarioId): array
    {
        $entidade = $this->repo->buscarPorCnpj($cnpj, true);
        if ($entidade) {
            return $entidade;
        }

        try {
            DB::transaction(fn () => $this->repo->criar($cnpj, [], $usuarioId));
        } catch (UniqueConstraintViolationException) {
            // criada em paralelo: segue com a existente
        }

        return $this->repo->buscarPorCnpj($cnpj, true);
    }

    // ------------------------------------------------------------------ visão do tenant

    /**
     * Aba "Dados do CNPJ". Não expõe quais são as outras academias (isolamento entre tenants).
     *
     * @return array<string, mixed>
     */
    public function dadosDoTenant(int $tenantId): array
    {
        $vinculo = $this->repo->vinculoAtivoDoTenant($tenantId);
        if (! $vinculo) {
            return ['vinculado' => false];
        }

        $entidadeId = (int) $vinculo['entidade_juridica_id'];
        $entidade = $this->repo->buscarPorId($entidadeId);
        $outros = $this->repo->contarVinculosAtivos($entidadeId, $tenantId);
        $gestor = $vinculo['papel'] === VinculoCnpj::GESTOR;

        return [
            'vinculado' => true,
            'cnpj' => $entidade['cnpj'],
            'cnpj_formatado' => self::formatar($entidade['cnpj']),
            'dados' => array_intersect_key($entidade, array_flip(VinculoCnpj::CAMPOS_JURIDICOS)),
            'atualizado_em' => $entidade['updated_at'],
            'papel' => $vinculo['papel'],
            'vinculado_em' => $vinculo['vinculado_em'],
            'compartilhado' => $outros > 0,
            'outras_contas' => $outros,
            'tem_gestor' => $gestor || $this->repo->gestorAtivo($entidadeId) !== null,
            'pode_editar' => $gestor,
        ];
    }

    /**
     * Gestor edita os dados jurídicos (refletem em todos os tenants do CNPJ). O papel é conferido
     * com o vínculo travado: não basta a tela esconder o botão.
     *
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    public function atualizarPeloTenant(int $tenantId, array $dados, ?int $usuarioId, ?string $ip = null): array
    {
        DB::transaction(function () use ($tenantId, $dados, $usuarioId, $ip) {
            $vinculo = $this->repo->vinculoAtivoDoTenant($tenantId, true);
            if (! $vinculo) {
                throw new EntidadeJuridicaException('Esta academia não tem CNPJ vinculado.', 404, 'SEM_CNPJ');
            }
            $entidade = $this->repo->buscarPorId((int) $vinculo['entidade_juridica_id'], true);
            if ($vinculo['papel'] !== VinculoCnpj::GESTOR) {
                throw new EntidadeJuridicaException(
                    'Este CNPJ é compartilhado com outra conta. Os dados jurídicos são administrados pela conta responsável.',
                    403, 'CNPJ_SOMENTE_LEITURA',
                );
            }
            $this->recusarTrocaDeCnpj($dados, $entidade['cnpj']);
            $this->aplicarDados($entidade, $dados, $tenantId, $usuarioId, $ip);
        }, self::TENTATIVAS);

        return $this->dadosDoTenant($tenantId);
    }

    /** @param  array<string, mixed>  $dados */
    private function recusarTrocaDeCnpj(array $dados, string $cnpjAtual): void
    {
        if (array_key_exists('cnpj', $dados) && self::normalizar((string) $dados['cnpj']) !== $cnpjAtual) {
            throw new EntidadeJuridicaException('O CNPJ não pode ser alterado por aqui. Fale com o suporte do Check-in.', 403, 'CNPJ_IMUTAVEL');
        }
    }

    /**
     * @param  array<string, mixed>  $entidade  travada
     * @param  array<string, mixed>  $dados
     */
    private function aplicarDados(array $entidade, array $dados, ?int $tenantId, ?int $usuarioId, ?string $ip): void
    {
        $limpos = $this->validarDados($dados);
        $antes = [];
        $depois = [];
        foreach ($limpos as $campo => $valor) {
            if ((string) ($entidade[$campo] ?? '') !== (string) ($valor ?? '')) {
                $antes[$campo] = $entidade[$campo] ?? null;
                $depois[$campo] = $valor;
            }
        }
        if ($depois === []) {
            return;
        }

        $this->repo->atualizarDados((int) $entidade['id'], $depois, $usuarioId);
        $this->repo->registrarAuditoria([
            'entidade_juridica_id' => (int) $entidade['id'], 'cnpj' => $entidade['cnpj'], 'tenant_id' => $tenantId,
            'usuario_id' => $usuarioId, 'acao' => 'dados_juridicos_atualizados', 'ip' => $ip,
            'dados_anteriores' => $antes, 'dados_novos' => $depois,
        ]);
    }

    /**
     * Só os campos jurídicos enviados; o resto do payload é ignorado.
     *
     * @param  array<string, mixed>  $dados
     * @return array<string, ?string>
     */
    private function validarDados(array $dados): array
    {
        $limpos = [];
        foreach (VinculoCnpj::CAMPOS_JURIDICOS as $campo) {
            if (array_key_exists($campo, $dados)) {
                $v = trim((string) ($dados[$campo] ?? ''));
                $limpos[$campo] = $v === '' ? null : $v;
            }
        }

        $errors = [];
        if (array_key_exists('razao_social', $limpos) && $limpos['razao_social'] === null) {
            $errors[] = 'Razão social é obrigatória';
        }
        foreach (['razao_social' => 255, 'nome_fantasia' => 255, 'inscricao_estadual' => 30, 'inscricao_municipal' => 30,
            'logradouro' => 255, 'numero' => 20, 'complemento' => 100, 'bairro' => 100, 'cidade' => 100] as $campo => $max) {
            if (isset($limpos[$campo]) && mb_strlen($limpos[$campo]) > $max) {
                $errors[] = "O campo {$campo} aceita até {$max} caracteres";
            }
        }
        if (isset($limpos['cep'])) {
            $limpos['cep'] = Documento::digitos($limpos['cep']);
            if (strlen($limpos['cep']) !== 8) {
                $errors[] = 'CEP deve ter 8 dígitos';
            }
        }
        if (isset($limpos['estado'])) {
            $limpos['estado'] = mb_strtoupper($limpos['estado']);
            if (! preg_match('/^[A-Z]{2}$/', $limpos['estado'])) {
                $errors[] = 'UF deve ter 2 letras';
            }
        }

        if ($errors !== []) {
            throw new EntidadeJuridicaException(implode(', ', $errors), 422, 'DADOS_INVALIDOS', $errors);
        }

        return $limpos;
    }

    // ------------------------------------------------------------------ super admin

    /** @return list<array<string, mixed>> */
    public function listar(string $busca = '', bool $somenteCompartilhados = false): array
    {
        return array_map(
            fn (array $e) => $e + ['cnpj_formatado' => self::formatar($e['cnpj'])],
            $this->repo->listar($busca, $somenteCompartilhados),
        );
    }

    /** @return array<string, mixed> */
    public function detalhe(string $cnpj): array
    {
        $entidade = $this->entidadeOu404($cnpj);
        $id = (int) $entidade['id'];

        return [
            'entidade' => $entidade + ['cnpj_formatado' => self::formatar($entidade['cnpj'])],
            'vinculos' => $this->repo->vinculos($id),
            'excecoes' => $this->repo->excecoes($id),
            'auditoria' => $this->repo->auditoria($id),
            'politica' => $this->repo->politica(),
        ];
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    public function atualizarPeloSuperAdmin(string $cnpj, array $dados, ?int $usuarioId, ?string $ip = null): array
    {
        DB::transaction(function () use ($cnpj, $dados, $usuarioId, $ip) {
            $entidade = $this->repo->buscarPorCnpj(self::normalizar($cnpj), true)
                ?? throw new EntidadeJuridicaException('CNPJ não encontrado', 404, 'CNPJ_NAO_ENCONTRADO');
            $this->recusarTrocaDeCnpj($dados, $entidade['cnpj']);
            $this->aplicarDados($entidade, $dados, null, $usuarioId, $ip);
        }, self::TENTATIVAS);

        return $this->detalhe($cnpj);
    }

    /** @return array<string, mixed> */
    public function autorizarExcecao(string $cnpj, ?string $justificativa, ?int $usuarioId, ?string $ip = null): array
    {
        $justificativa = $this->exigirJustificativa($justificativa);

        DB::transaction(function () use ($cnpj, $justificativa, $usuarioId, $ip) {
            $entidade = $this->repo->buscarPorCnpj(self::normalizar($cnpj), true)
                ?? throw new EntidadeJuridicaException('CNPJ não encontrado', 404, 'CNPJ_NAO_ENCONTRADO');
            if ($this->repo->excecaoVigente((int) $entidade['id'])) {
                throw new EntidadeJuridicaException('Este CNPJ já tem uma exceção vigente.', 409, 'EXCECAO_JA_VIGENTE');
            }
            $excecaoId = $this->repo->criarExcecao((int) $entidade['id'], $justificativa, $usuarioId);
            $this->repo->registrarAuditoria([
                'entidade_juridica_id' => (int) $entidade['id'], 'cnpj' => $entidade['cnpj'], 'usuario_id' => $usuarioId,
                'acao' => 'excecao_autorizada', 'justificativa' => $justificativa, 'ip' => $ip,
                'dados_novos' => ['excecao_id' => $excecaoId],
            ]);
        }, self::TENTATIVAS);

        return $this->detalhe($cnpj);
    }

    /**
     * Revoga a exceção: novos tenants deixam de poder entrar no CNPJ; os vínculos atuais continuam.
     *
     * @return array<string, mixed>
     */
    public function revogarExcecao(int $excecaoId, ?string $motivo, ?int $usuarioId, ?string $ip = null): array
    {
        $motivo = $this->exigirJustificativa($motivo, 'Informe o motivo da revogação (mínimo 10 caracteres)');

        $cnpj = DB::transaction(function () use ($excecaoId, $motivo, $usuarioId, $ip) {
            $excecao = $this->repo->buscarExcecao($excecaoId, true)
                ?? throw new EntidadeJuridicaException('Exceção não encontrada', 404, 'EXCECAO_NAO_ENCONTRADA');
            if ($excecao['revogado_em'] !== null) {
                throw new EntidadeJuridicaException('Esta exceção já foi revogada.', 409, 'EXCECAO_JA_REVOGADA');
            }
            $entidade = $this->repo->buscarPorId((int) $excecao['entidade_juridica_id'], true);
            $this->repo->revogarExcecao($excecaoId, $motivo, $usuarioId);
            $this->repo->registrarAuditoria([
                'entidade_juridica_id' => (int) $entidade['id'], 'cnpj' => $entidade['cnpj'], 'usuario_id' => $usuarioId,
                'acao' => 'excecao_revogada', 'justificativa' => $motivo, 'ip' => $ip,
                'dados_novos' => ['excecao_id' => $excecaoId, 'vinculos_preservados' => $this->repo->contarVinculosAtivos((int) $entidade['id'])],
            ]);

            return $entidade['cnpj'];
        }, self::TENTATIVAS);

        return $this->detalhe($cnpj);
    }

    /**
     * Define ou transfere o gestor cadastral. O alvo precisa ter vínculo ativo com o CNPJ.
     *
     * @return array<string, mixed>
     */
    public function definirGestor(string $cnpj, int $tenantId, ?string $justificativa, ?int $usuarioId, ?string $ip = null): array
    {
        $justificativa = $this->exigirJustificativa($justificativa);

        DB::transaction(function () use ($cnpj, $tenantId, $justificativa, $usuarioId, $ip) {
            $entidade = $this->repo->buscarPorCnpj(self::normalizar($cnpj), true)
                ?? throw new EntidadeJuridicaException('CNPJ não encontrado', 404, 'CNPJ_NAO_ENCONTRADO');
            $entidadeId = (int) $entidade['id'];

            $alvo = $this->repo->vinculoAtivoDoTenant($tenantId, true);
            if (! $alvo || (int) $alvo['entidade_juridica_id'] !== $entidadeId) {
                throw new EntidadeJuridicaException('Esta academia não está vinculada a este CNPJ.', 422, 'TENANT_NAO_VINCULADO');
            }
            if ($alvo['papel'] === VinculoCnpj::GESTOR) {
                throw new EntidadeJuridicaException('Esta academia já é a gestora do CNPJ.', 409, 'JA_GESTOR');
            }

            $anterior = $this->repo->gestorAtivo($entidadeId);
            if ($anterior) {
                // Rebaixa antes de promover: o UNIQUE de gestor_ativo não aceita dois ao mesmo tempo
                $this->repo->definirPapel((int) $anterior['id'], $entidadeId, VinculoCnpj::VINCULADO);
            }
            $this->repo->definirPapel((int) $alvo['id'], $entidadeId, VinculoCnpj::GESTOR);
            $this->repo->registrarAuditoria([
                'entidade_juridica_id' => $entidadeId, 'cnpj' => $entidade['cnpj'], 'tenant_id' => $tenantId,
                'usuario_id' => $usuarioId, 'acao' => 'gestor_transferido', 'justificativa' => $justificativa, 'ip' => $ip,
                'dados_anteriores' => ['gestor_tenant_id' => $anterior ? (int) $anterior['tenant_id'] : null],
                'dados_novos' => ['gestor_tenant_id' => $tenantId],
            ]);
        }, self::TENTATIVAS);

        return $this->detalhe($cnpj);
    }

    /** @return array<string, mixed> */
    private function entidadeOu404(string $cnpj): array
    {
        return $this->repo->buscarPorCnpj(self::normalizar($cnpj))
            ?? throw new EntidadeJuridicaException('CNPJ não encontrado', 404, 'CNPJ_NAO_ENCONTRADO');
    }

    private function exigirJustificativa(?string $texto, string $mensagem = 'Informe a justificativa (mínimo 10 caracteres)'): string
    {
        $texto = trim((string) $texto);
        if (mb_strlen($texto) < 10) {
            throw new EntidadeJuridicaException($mensagem, 422, 'JUSTIFICATIVA_OBRIGATORIA');
        }

        return mb_substr($texto, 0, 2000);
    }
}
