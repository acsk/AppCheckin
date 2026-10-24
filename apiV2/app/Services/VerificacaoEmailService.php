<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Confirma que o usuário recebe e-mail no endereço informado: código de 6 dígitos, 15 minutos,
 * até 5 tentativas. Reenvio só depois de 1 minuto e no máximo 5 códigos por hora.
 */
class VerificacaoEmailService
{
    public const EXPIRA_MINUTOS = 15;

    /** Assunto fixo: precisa estar em appcheckin.mail_allowed_subjects (EnforceAllowedOutboundMail). */
    public const ERRO_ENVIO = 'Não foi possível enviar o e-mail agora. Tente de novo.';

    public const ASSUNTO = '✉️ Código de confirmação de e-mail - App Check-in';

    private const MAX_TENTATIVAS = 5;

    private const MAX_POR_HORA = 5;

    public function __construct(
        private readonly EmailTemplateService $templates,
    ) {}

    /** @return string|null erro para o usuário, ou null se enviado */
    public function enviarCodigo(int $usuarioId, string $email, string $nome, ?int $atualizacaoId = null): ?string
    {
        $recentes = DB::table('verificacoes_email')->where('usuario_id', $usuarioId)
            ->where('created_at', '>=', now()->subHour())->orderByDesc('id')->get(['created_at']);
        if ($recentes->isNotEmpty() && now()->diffInSeconds($recentes->first()->created_at, true) < 60) {
            return 'Aguarde um minuto para pedir outro código.';
        }
        if ($recentes->count() >= self::MAX_POR_HORA) {
            return 'Muitos códigos pedidos. Tente de novo em uma hora.';
        }

        $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $id = DB::table('verificacoes_email')->insertGetId([
            'usuario_id' => $usuarioId,
            'atualizacao_id' => $atualizacaoId,
            'email' => $email,
            'codigo_hash' => password_hash($codigo, PASSWORD_BCRYPT),
            'expira_em' => now()->addMinutes(self::EXPIRA_MINUTOS),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            // false = envio cancelado (ex.: mail guard), sem exceção
            $enviado = TransactionalMailSender::send(
                $email,
                $nome,
                self::ASSUNTO,
                $this->templates->codigoVerificacaoEmail($nome, $codigo, self::EXPIRA_MINUTOS),
                $this->templates->codigoVerificacaoEmailPlainText($nome, $codigo, self::EXPIRA_MINUTOS),
            );
        } catch (\Throwable $e) {
            Log::error('Erro ao enviar código de verificação de e-mail: '.$e->getMessage());
            $enviado = false;
        }
        if (! $enviado) {
            // Código que não chegou não conta no limite de reenvio
            DB::table('verificacoes_email')->where('id', $id)->delete();
            Log::error('Código de verificação de e-mail não enviado', ['usuario_id' => $usuarioId]);

            return self::ERRO_ENVIO;
        }

        return null;
    }

    /** @return string|null erro para o usuário, ou null se o código confere */
    public function validarCodigo(int $usuarioId, string $email, string $codigo, ?int $atualizacaoId = null): ?string
    {
        $v = DB::table('verificacoes_email')->where('usuario_id', $usuarioId)->where('email', $email)
            ->when($atualizacaoId !== null, fn ($q) => $q->where('atualizacao_id', $atualizacaoId))
            ->whereNull('validado_em')->orderByDesc('id')->first();
        if (! $v || now()->greaterThan($v->expira_em)) {
            return 'Código expirado. Peça um novo.';
        }
        if ((int) $v->tentativas >= self::MAX_TENTATIVAS) {
            return 'Muitas tentativas. Peça um novo código.';
        }
        if (! password_verify(preg_replace('/\D/', '', $codigo) ?? '', (string) $v->codigo_hash)) {
            DB::table('verificacoes_email')->where('id', $v->id)->increment('tentativas');

            return 'Código incorreto.';
        }

        DB::table('verificacoes_email')->where('id', $v->id)->update(['validado_em' => now(), 'updated_at' => now()]);

        return null;
    }

    /** E-mail confirmado por código há pouco (vale para concluir a atualização cadastral). */
    public function confirmado(int $usuarioId, string $email, ?int $atualizacaoId = null): bool
    {
        return DB::table('verificacoes_email')->where('usuario_id', $usuarioId)->where('email', $email)
            ->when($atualizacaoId !== null, fn ($q) => $q->where('atualizacao_id', $atualizacaoId))
            ->where('validado_em', '>=', now()->subDay())->exists();
    }
}
