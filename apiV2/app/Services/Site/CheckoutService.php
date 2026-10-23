<?php

namespace App\Services\Site;

use App\Repositories\PagamentoContratoRepository;
use App\Repositories\PlanoSistemaRepository;
use App\Repositories\TenantPlanoRepository;
use App\Repositories\UsuarioRepository;
use App\Services\EntidadeJuridica\EntidadeJuridicaService;
use App\Services\RateLimiter;
use App\Services\SuperAdmin\CobrancaContratoService;
use App\Services\SuperAdmin\SuperAdminAcademiaService;
use App\Support\Documento;
use App\Support\TipoCobrancaPlanoSistema;
use App\Support\VinculoCnpj;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Assinatura pelo site: cria academia, administrador e contrato e, nos planos pagos,
 * a primeira cobrança no Asaas da conta do sistema.
 */
class CheckoutService
{
    public const FORMAS = ['PIX', 'BOLETO', 'CREDIT_CARD'];

    public function __construct(
        private readonly PlanoSistemaRepository $planos,
        private readonly UsuarioRepository $usuarios,
        private readonly SuperAdminAcademiaService $academias,
        private readonly TenantPlanoRepository $contratos,
        private readonly PagamentoContratoRepository $pagamentos,
        private readonly CobrancaContratoService $cobrancas,
        private readonly ?RateLimiter $rateLimiter = null,
        ?EntidadeJuridicaService $entidades = null,
    ) {
        $this->entidades = $entidades ?? app(EntidadeJuridicaService::class);
    }

    private EntidadeJuridicaService $entidades;

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function assinar(array $data, string $ip): array
    {
        $limite = ($this->rateLimiter ?? new RateLimiter(5, 60))->attempt($ip, 'site-assinar');
        if (! $limite['allowed']) {
            return ['status' => 429, 'body' => ['message' => 'Muitas tentativas. Tente de novo em alguns minutos.']];
        }

        $f = $this->normalizar($data);
        $plano = $this->planoPublico($f['plano_id']);
        $pago = $plano && ! TipoCobrancaPlanoSistema::isGratuito($plano);
        $formas = $pago ? $this->cobrancas->formasHabilitadas() : [];

        if ($pago && $formas === []) {
            return ['status' => 503, 'body' => ['message' => 'A assinatura paga está indisponível no momento. Comece pelo plano grátis ou fale com a gente.']];
        }

        $errors = $this->validar($f, $plano, $formas);
        if ($errors !== []) {
            return ['status' => 422, 'body' => ['message' => 'Confira os campos destacados.', 'errors' => $errors]];
        }

        if ($this->usuarios->emailExists($f['email'], null, null)) {
            return ['status' => 409, 'body' => [
                'message' => 'Já existe uma conta com este e-mail.',
                'errors' => ['email' => 'Já existe uma conta com este e-mail. Entre no painel ou use outro.'],
            ]];
        }

        if ($f['cpf'] !== '' && $this->usuarios->cpfExists($f['cpf'])) {
            return ['status' => 409, 'body' => [
                'message' => 'Já existe uma conta com este CPF.',
                'errors' => [$f['legado'] ? 'documento' : 'responsavel_cpf' => 'Já existe uma conta com este CPF. Entre no painel ou fale com o suporte.'],
            ]];
        }

        try {
            [$academia, $contrato, $pagamentoId] = DB::transaction(function () use ($f, $plano, $pago, $ip) {
                $res = $this->academias->criarAcademia($this->dadosAcademia($f, $plano));
                if ($res['status'] !== 201) {
                    throw new CheckoutRecusado($res['body']['errors'] ?? [$res['body']['error'] ?? 'Não foi possível criar a conta']);
                }
                $academia = $res['body']['academia'];
                $contrato = $res['body']['contrato'] ?? null;
                if ($adminId = (int) ($res['body']['admin']['id'] ?? 0)) {
                    $this->completarCadastro($f, (int) $academia['id'], $adminId, $ip);
                }
                if (! $contrato) {
                    throw new \RuntimeException('Contrato inicial não foi criado');
                }

                $pagamentoId = null;
                if ($pago) {
                    $pagamentoId = $this->pagamentos->criar([
                        'tenant_plano_id' => (int) $contrato['id'],
                        'valor' => $this->valorInicial($plano, $f['alunos']),
                        'data_vencimento' => date('Y-m-d'),
                        'status_pagamento_id' => 1,
                        'forma_pagamento_id' => $this->formaPagamentoId($f['forma']),
                        'observacoes' => 'Primeiro pagamento, assinatura pelo site',
                    ]);
                }

                return [$academia, $contrato, $pagamentoId];
            });
        } catch (CheckoutRecusado $e) {
            return ['status' => 422, 'body' => ['message' => implode('. ', $e->erros), 'errors' => ['geral' => implode('. ', $e->erros)]]];
        } catch (\Throwable $e) {
            Log::error('Checkout do site falhou', ['error' => $e->getMessage()]);

            return ['status' => 500, 'body' => ['message' => 'Não foi possível criar a conta agora. Tente de novo em instantes.']];
        }

        $cobranca = null;
        $aviso = null;
        if ($pagamentoId) {
            try {
                $cobranca = $this->cobrancas->gerar($pagamentoId, $f['forma']);
            } catch (\Throwable $e) {
                Log::error('Checkout do site: cobrança não gerada', ['pagamento_id' => $pagamentoId, 'error' => $e->getMessage()]);
                $aviso = 'Sua conta foi criada, mas não conseguimos gerar a cobrança agora. Vamos enviá-la por e-mail.';
            }
        }

        return ['status' => 201, 'body' => [
            'message' => $pago ? 'Assinatura criada' : 'Conta grátis criada',
            'academia' => ['id' => (int) $academia['id'], 'nome' => $academia['nome']],
            'email' => $f['email'],
            'plano' => ['id' => (int) $plano['id'], 'nome' => $plano['nome'], 'tipo_cobranca' => $plano['tipo_cobranca']],
            'contrato' => [
                'id' => (int) $contrato['id'],
                'status' => $pago ? 'pendente' : 'ativo',
                'data_fim' => $contrato['data_fim'] ?? null,
            ],
            'valor' => $pagamentoId ? $this->valorInicial($plano, $f['alunos']) : 0,
            'cobranca' => $cobranca ? array_intersect_key($cobranca, array_flip(['billing_type', 'vencimento', 'url', 'boleto_url', 'pix_payload', 'pix_qr_code'])) : null,
            'aviso' => $aviso,
        ]];
    }

    /** Endereço: campo => tamanho máximo. */
    private const ENDERECO = ['cep' => 8, 'logradouro' => 255, 'numero' => 20, 'complemento' => 100, 'bairro' => 100, 'cidade' => 100, 'estado' => 2];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizar(array $data): array
    {
        $txt = static fn (string $k) => trim(preg_replace('/\s+/u', ' ', (string) ($data[$k] ?? '')) ?? '');
        $endereco = function (string $prefixo) use ($txt): array {
            $e = [];
            foreach (array_keys(self::ENDERECO) as $c) {
                $e[$c] = $txt($prefixo.$c);
            }
            $e['cep'] = Documento::digitos($e['cep']);
            $e['estado'] = mb_strtoupper($e['estado'], 'UTF-8');

            return $e;
        };

        $f = [
            // Formulário antigo (antes do cadastro completo): documento único e cidade em texto livre
            'legado' => ! array_key_exists('tipo_pessoa', $data),
            'tipo' => $txt('tipo_pessoa') === 'pf' ? 'pf' : 'pj',
            'nome' => $txt('academia_nome'),
            'cnpj' => Documento::digitos($txt('cnpj')),
            'razao_social' => $txt('razao_social'),
            'nome_fantasia' => $txt('nome_fantasia'),
            'endereco' => $endereco(''),
            'responsavel' => $txt('responsavel_nome'),
            'cpf' => Documento::digitos($txt('responsavel_cpf')),
            'nascimento' => $txt('responsavel_nascimento'),
            'endereco_igual' => ! array_key_exists('endereco_responsavel_igual', $data) || filter_var($data['endereco_responsavel_igual'], FILTER_VALIDATE_BOOLEAN),
            'endereco_responsavel' => $endereco('responsavel_'),
            'email' => mb_strtolower($txt('email'), 'UTF-8'),
            'telefone' => Documento::digitos($txt('telefone')),
            'senha' => (string) ($data['senha'] ?? ''),
            'senha_confirmacao' => (string) ($data['senha_confirmacao'] ?? ''),
            'plano_id' => (int) ($data['plano_id'] ?? 0),
            'forma' => strtoupper($txt('forma_pagamento')),
            'alunos' => max(0, (int) ($data['alunos'] ?? 0)),
            'aceite' => filter_var($data['aceite_termos'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];

        if ($f['legado']) {
            $doc = Documento::digitos($txt('documento'));
            $f['tipo'] = strlen($doc) === 11 ? 'pf' : 'pj';
            $f[$f['tipo'] === 'pf' ? 'cpf' : 'cnpj'] = $doc;
            [$f['endereco']['cidade'], $f['endereco']['estado']] = array_map(fn ($v) => (string) $v, $this->cidadeUf($txt('cidade')));
        }
        if ($f['tipo'] === 'pf') {
            $f['cnpj'] = '';
            $f['razao_social'] = '';
            $f['nome_fantasia'] = '';
        }

        return $f;
    }

    /**
     * @param  array<string, mixed>  $f
     * @param  array<string, mixed>|null  $plano
     * @param  list<string>  $formas
     * @return array<string, string>
     */
    private function validar(array $f, ?array $plano, array $formas): array
    {
        $e = [];
        if (mb_strlen($f['nome']) < 2 || mb_strlen($f['nome']) > 255) {
            $e['academia_nome'] = 'Informe o nome da academia.';
        }

        if ($f['legado']) {
            // Formulário antigo: só o documento (CNPJ ou CPF), como antes
            $doc = $f['tipo'] === 'pf' ? $f['cpf'] : $f['cnpj'];
            if (strlen($doc) === 11 ? ! Documento::cpfValido($doc) : (strlen($doc) !== 14 || ! Documento::cnpjValido($doc))) {
                $e['documento'] = 'Confira o CNPJ ou CPF.';
            }
        } else {
            if ($f['tipo'] === 'pj') {
                if (! Documento::cnpjValido($f['cnpj'])) {
                    $e['cnpj'] = strlen($f['cnpj']) !== 14 ? 'O CNPJ tem 14 números.' : 'CNPJ inválido. Confira os números.';
                }
                if (mb_strlen($f['razao_social']) < 3 || mb_strlen($f['razao_social']) > 255) {
                    $e['razao_social'] = 'Informe a razão social, como está no cartão do CNPJ.';
                }
                if (mb_strlen($f['nome_fantasia']) > 255) {
                    $e['nome_fantasia'] = 'Use até 255 caracteres.';
                }
            }
            $e += $this->validarEndereco($f['endereco'], '');
            if (! $f['endereco_igual']) {
                $e += $this->validarEndereco($f['endereco_responsavel'], 'responsavel_');
            }
            if (! Documento::cpfValido($f['cpf'])) {
                $e['responsavel_cpf'] = strlen($f['cpf']) !== 11 ? 'O CPF tem 11 números.' : 'CPF inválido. Confira os números.';
            }
            $nascimento = \DateTimeImmutable::createFromFormat('!Y-m-d', $f['nascimento']);
            $idade = $nascimento ? $nascimento->diff(new \DateTimeImmutable('today'))->y : null;
            if (! $nascimento || $nascimento->format('Y-m-d') !== $f['nascimento'] || $nascimento > new \DateTimeImmutable('today') || $idade > 110) {
                $e['responsavel_nascimento'] = 'Informe a data de nascimento (DD/MM/AAAA).';
            } elseif ($idade < 18) {
                $e['responsavel_nascimento'] = 'O responsável precisa ter 18 anos ou mais.';
            }
        }

        if (mb_strlen($f['responsavel']) < 3 || ! preg_match('/^\p{L}[\p{L}\'. -]*\s+\p{L}/u', $f['responsavel']) || mb_strlen($f['responsavel']) > 255) {
            $e['responsavel_nome'] = 'Informe nome e sobrenome de quem vai administrar o painel.';
        }
        if (! filter_var($f['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($f['email']) > 255) {
            $e['email'] = 'Confira o e-mail.';
        }
        if ($f['legado'] ? (strlen($f['telefone']) < 10 || strlen($f['telefone']) > 11) : ! preg_match('/^\d{2}9\d{8}$/', $f['telefone'])) {
            $e['telefone'] = 'Informe o WhatsApp com DDD: (00) 90000-0000.';
        }
        if ($f['legado']) {
            if (strlen($f['senha']) < 6) {
                $e['senha'] = 'A senha precisa de pelo menos 6 caracteres.';
            }
        } elseif (mb_strlen($f['senha']) < 8 || ! preg_match('/\pL/u', $f['senha']) || ! preg_match('/\d/', $f['senha'])) {
            $e['senha'] = 'Use pelo menos 8 caracteres, com letras e números.';
        } elseif ($f['senha'] !== $f['senha_confirmacao']) {
            $e['senha_confirmacao'] = 'As senhas não são iguais.';
        }

        if (! $plano) {
            $e['plano_id'] = 'Escolha um plano.';
        } elseif (! TipoCobrancaPlanoSistema::isGratuito($plano) && ! in_array($f['forma'], $formas, true)) {
            $e['forma_pagamento'] = 'Escolha como pagar.';
        }
        if (! $f['aceite']) {
            $e['aceite_termos'] = 'Aceite os termos para continuar.';
        }

        return $e;
    }

    /**
     * @param  array<string, string>  $end
     * @return array<string, string>
     */
    private function validarEndereco(array $end, string $prefixo): array
    {
        $e = [];
        if (! Documento::cepValido($end['cep'])) {
            $e[$prefixo.'cep'] = 'O CEP tem 8 números.';
        }
        foreach (['logradouro' => [3, 'Informe a rua.'], 'numero' => [1, 'Informe o número (ou s/n).'], 'bairro' => [2, 'Informe o bairro.'], 'cidade' => [2, 'Informe a cidade.']] as $c => [$min, $msg]) {
            if (mb_strlen($end[$c]) < $min) {
                $e[$prefixo.$c] = $msg;
            }
        }
        if (! Documento::ufValida($end['estado'])) {
            $e[$prefixo.'estado'] = 'Escolha o estado.';
        }
        foreach (self::ENDERECO as $c => $max) {
            if (! isset($e[$prefixo.$c]) && mb_strlen($end[$c]) > $max) {
                $e[$prefixo.$c] = "Use até {$max} caracteres.";
            }
        }

        return $e;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function planoPublico(int $id): ?array
    {
        foreach ($this->planos->listarPublicos() as $plano) {
            if ((int) $plano['id'] === $id) {
                return $plano;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $f
     * @param  array<string, mixed>  $plano
     * @return array<string, mixed>
     */
    private function dadosAcademia(array $f, array $plano): array
    {
        $end = array_map(fn ($v) => $v === '' ? null : $v, $f['endereco']);

        return [
            'nome' => $f['nome'],
            'email' => $f['email'],
            'cnpj' => $f['tipo'] === 'pj' ? $f['cnpj'] : null,
            'telefone' => $f['telefone'],
            'responsavel_nome' => $f['responsavel'],
            'responsavel_email' => $f['email'],
            'responsavel_telefone' => $f['telefone'],
            'responsavel_cpf' => $f['cpf'] ?: null,
            'senha_admin' => $f['senha'],
            'plano_sistema_id' => (int) $plano['id'],
            'data_fim' => ! empty($plano['prazo_dias']) ? date('Y-m-d', strtotime('+'.(int) $plano['prazo_dias'].' days')) : null,
        ] + $end;
    }

    /**
     * Depois de criar academia e administrador: dados pessoais do responsável (usuarios) e, se o CNPJ é
     * novo no Check-in, razão social e nome fantasia. CNPJ já de outra conta mantém os dados de lá.
     *
     * @param  array<string, mixed>  $f
     */
    private function completarCadastro(array $f, int $tenantId, int $adminId, string $ip): void
    {
        if ($f['legado']) {
            return;
        }
        // Endereço do responsável: o dele ou o da academia (criarUsuarioCompleto não grava endereço)
        $pessoais = ['whatsapp' => $f['telefone'], 'data_nascimento' => $f['nascimento']]
            + ($f['endereco_igual'] ? $f['endereco'] : $f['endereco_responsavel']);
        $this->usuarios->gravarDadosPessoais($adminId, $pessoais);

        if ($f['tipo'] === 'pj') {
            $vinculo = DB::table('tenant_entidade_juridica')->where('tenant_id', $tenantId)->whereNull('encerrado_em')->first(['papel']);
            if ($vinculo && $vinculo->papel === VinculoCnpj::GESTOR) {
                $this->entidades->atualizarPeloTenant($tenantId, array_filter(
                    ['razao_social' => $f['razao_social'], 'nome_fantasia' => $f['nome_fantasia'] ?: $f['nome']] + $f['endereco'],
                    fn ($v) => $v !== '',
                ), $adminId, $ip);
            }
        }
    }

    /**
     * "Maceió – AL", "Maceió/AL" ou "Maceió, AL" viram ['Maceió', 'AL'].
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function cidadeUf(string $valor): array
    {
        if ($valor === '') {
            return [null, null];
        }
        if (preg_match('/^(.+?)\s*[-–\/,]\s*([A-Za-z]{2})$/u', $valor, $m) === 1) {
            return [trim($m[1]), strtoupper($m[2])];
        }

        return [$valor, null];
    }

    /**
     * @param  array<string, mixed>  $plano
     */
    private function valorInicial(array $plano, int $alunos): float
    {
        if (TipoCobrancaPlanoSistema::doPlano($plano) === TipoCobrancaPlanoSistema::POR_ALUNO) {
            $max = ! empty($plano['max_alunos']) ? (int) $plano['max_alunos'] : PHP_INT_MAX;

            return round(max(1, min($alunos, $max)) * (float) $plano['valor_por_aluno'], 2);
        }

        return round((float) $plano['valor'], 2);
    }

    private function formaPagamentoId(string $forma): ?int
    {
        return CobrancaContratoService::formaPagamentoId($forma);
    }
}
