<?php

namespace App\Services\Site;

use App\Repositories\PagamentoContratoRepository;
use App\Repositories\PlanoSistemaRepository;
use App\Repositories\TenantPlanoRepository;
use App\Repositories\UsuarioRepository;
use App\Services\RateLimiter;
use App\Services\SuperAdmin\CobrancaContratoService;
use App\Services\SuperAdmin\SuperAdminAcademiaService;
use App\Support\Documento;
use App\Support\TipoCobrancaPlanoSistema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Assinatura pelo site: cria academia, administrador e contrato e, nos planos pagos,
 * a primeira cobrança no Asaas da conta do sistema.
 */
class CheckoutService
{
    public const FORMAS = ['PIX', 'BOLETO', 'CREDIT_CARD'];

    private const FORMA_PAGAMENTO_NOME = ['PIX' => 'Pix', 'BOLETO' => 'Boleto', 'CREDIT_CARD' => 'Cartão'];

    public function __construct(
        private readonly PlanoSistemaRepository $planos,
        private readonly UsuarioRepository $usuarios,
        private readonly SuperAdminAcademiaService $academias,
        private readonly TenantPlanoRepository $contratos,
        private readonly PagamentoContratoRepository $pagamentos,
        private readonly CobrancaContratoService $cobrancas,
        private readonly ?RateLimiter $rateLimiter = null,
    ) {}

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

        try {
            [$academia, $contrato, $pagamentoId] = DB::transaction(function () use ($f, $plano, $pago) {
                $res = $this->academias->criarAcademia($this->dadosAcademia($f, $plano));
                if ($res['status'] !== 201) {
                    throw new CheckoutRecusado($res['body']['errors'] ?? [$res['body']['error'] ?? 'Não foi possível criar a conta']);
                }
                $academia = $res['body']['academia'];
                $contrato = $res['body']['contrato'] ?? null;
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

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizar(array $data): array
    {
        $txt = static fn (string $k) => trim((string) ($data[$k] ?? ''));

        return [
            'nome' => $txt('academia_nome'),
            'documento' => Documento::digitos($txt('documento')),
            'cidade' => $txt('cidade'),
            'responsavel' => $txt('responsavel_nome'),
            'email' => mb_strtolower($txt('email'), 'UTF-8'),
            'telefone' => Documento::digitos($txt('telefone')),
            'senha' => (string) ($data['senha'] ?? ''),
            'plano_id' => (int) ($data['plano_id'] ?? 0),
            'forma' => strtoupper($txt('forma_pagamento')),
            'alunos' => max(0, (int) ($data['alunos'] ?? 0)),
            'aceite' => filter_var($data['aceite_termos'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
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
        if (mb_strlen($f['nome']) < 2) {
            $e['academia_nome'] = 'Informe o nome da academia.';
        }
        $doc = $f['documento'];
        if (strlen($doc) === 11 ? ! Documento::cpfValido($doc) : (strlen($doc) !== 14 || ! Documento::cnpjValido($doc))) {
            $e['documento'] = 'Confira o CNPJ ou CPF.';
        }
        if (mb_strlen($f['responsavel']) < 3) {
            $e['responsavel_nome'] = 'Informe o nome de quem vai administrar o painel.';
        }
        if (! filter_var($f['email'], FILTER_VALIDATE_EMAIL)) {
            $e['email'] = 'Confira o e-mail.';
        }
        if (strlen($f['telefone']) < 10 || strlen($f['telefone']) > 11) {
            $e['telefone'] = 'Informe o WhatsApp com DDD.';
        }
        if (strlen($f['senha']) < 6) {
            $e['senha'] = 'A senha precisa de pelo menos 6 caracteres.';
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
        [$cidade, $uf] = $this->cidadeUf($f['cidade']);
        $cnpj = strlen($f['documento']) === 14 ? $f['documento'] : null;

        return [
            'nome' => $f['nome'],
            'email' => $f['email'],
            'cnpj' => $cnpj,
            'telefone' => $f['telefone'],
            'cidade' => $cidade,
            'estado' => $uf,
            'responsavel_nome' => $f['responsavel'],
            'responsavel_email' => $f['email'],
            'responsavel_telefone' => $f['telefone'],
            'responsavel_cpf' => $cnpj ? null : $f['documento'],
            'senha_admin' => $f['senha'],
            'plano_sistema_id' => (int) $plano['id'],
            'data_fim' => ! empty($plano['prazo_dias']) ? date('Y-m-d', strtotime('+'.(int) $plano['prazo_dias'].' days')) : null,
        ];
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
        $id = DB::table('formas_pagamento')->where('nome', self::FORMA_PAGAMENTO_NOME[$forma] ?? '')->value('id');

        return $id ? (int) $id : null;
    }
}
