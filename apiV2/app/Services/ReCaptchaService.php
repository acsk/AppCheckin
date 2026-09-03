<?php

namespace App\Services;

/**
 * Validação Google reCAPTCHA v3 (paridade Slim).
 */
class ReCaptchaService
{
    private string $verifyUrl = 'https://www.google.com/recaptcha/api/siteverify';

    public function __construct(
        private readonly string $secretKey,
        private readonly float $minimumScore = 0.5,
    ) {}

    /**
     * @return array{success: bool, score: float|null, error: string|null}
     */
    public function verify(?string $token, ?string $remoteIp = null): array
    {
        if (empty($token)) {
            return [
                'success' => false,
                'score' => null,
                'error' => 'Token reCAPTCHA não fornecido',
            ];
        }

        if ($this->secretKey === '') {
            return [
                'success' => false,
                'score' => null,
                'error' => 'reCAPTCHA não configurado',
            ];
        }

        $postData = [
            'secret' => $this->secretKey,
            'response' => $token,
        ];

        if ($remoteIp) {
            $postData['remoteip'] = $remoteIp;
        }

        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $this->verifyUrl,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($postData),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);

            $response = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($response === false || $httpCode !== 200) {
                return [
                    'success' => false,
                    'score' => null,
                    'error' => 'Erro ao comunicar com serviço reCAPTCHA',
                ];
            }

            $result = json_decode($response, true);
            if (! is_array($result) || ! isset($result['success'])) {
                return [
                    'success' => false,
                    'score' => null,
                    'error' => 'Resposta inválida do serviço reCAPTCHA',
                ];
            }

            $score = (float) ($result['score'] ?? 0.0);
            $success = ($result['success'] ?? false) && $score >= $this->minimumScore;

            return [
                'success' => $success,
                'score' => $score,
                'error' => $success ? null : 'Score reCAPTCHA muito baixo (possível bot)',
            ];
        } catch (\Throwable) {
            return [
                'success' => false,
                'score' => null,
                'error' => 'Erro ao validar reCAPTCHA',
            ];
        }
    }
}
