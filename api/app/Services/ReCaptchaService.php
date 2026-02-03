<?php

namespace App\Services;

/**
 * Serviço para validação do Google reCAPTCHA v3
 */
class ReCaptchaService
{
    private string $secretKey;
    private float $minimumScore;
    private string $verifyUrl = 'https://www.google.com/recaptcha/api/siteverify';

    /**
     * @param string $secretKey Chave secreta do reCAPTCHA
     * @param float $minimumScore Score mínimo aceito (0.0 a 1.0, recomendado: 0.5)
     */
    public function __construct(string $secretKey, float $minimumScore = 0.5)
    {
        $this->secretKey = $secretKey;
        $this->minimumScore = $minimumScore;
    }

    /**
     * Valida o token do reCAPTCHA v3
     * 
     * @param string|null $token Token enviado pelo cliente
     * @param string|null $remoteIp IP do cliente (opcional)
     * @return array ['success' => bool, 'score' => float|null, 'error' => string|null]
     */
    public function verify(?string $token, ?string $remoteIp = null): array
    {
        if (empty($token)) {
            return [
                'success' => false,
                'score' => null,
                'error' => 'Token reCAPTCHA não fornecido'
            ];
        }

        $postData = [
            'secret' => $this->secretKey,
            'response' => $token
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
                CURLOPT_SSL_VERIFYPEER => true
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($response === false || $httpCode !== 200) {
                error_log("ReCaptcha API error: HTTP $httpCode, CURL error: $curlError");
                return [
                    'success' => false,
                    'score' => null,
                    'error' => 'Erro ao comunicar com serviço reCAPTCHA'
                ];
            }

            $result = json_decode($response, true);

            if (!isset($result['success'])) {
                error_log("ReCaptcha invalid response: " . $response);
                return [
                    'success' => false,
                    'score' => null,
                    'error' => 'Resposta inválida do serviço reCAPTCHA'
                ];
            }

            // reCAPTCHA v3 retorna um score de 0.0 a 1.0
            $score = $result['score'] ?? 0.0;
            $success = $result['success'] && $score >= $this->minimumScore;

            if (!$success) {
                error_log("ReCaptcha verification failed. Score: $score, Minimum: {$this->minimumScore}");
            }

            return [
                'success' => $success,
                'score' => $score,
                'error' => $success ? null : 'Score reCAPTCHA muito baixo (possível bot)',
                'action' => $result['action'] ?? null,
                'challenge_ts' => $result['challenge_ts'] ?? null
            ];

        } catch (\Throwable $e) {
            error_log("ReCaptcha exception: " . $e->getMessage());
            return [
                'success' => false,
                'score' => null,
                'error' => 'Erro ao validar reCAPTCHA'
            ];
        }
    }

    /**
     * Verifica se o reCAPTCHA é válido (atalho)
     */
    public function isValid(?string $token, ?string $remoteIp = null): bool
    {
        $result = $this->verify($token, $remoteIp);
        return $result['success'];
    }

    /**
     * Define o score mínimo aceito
     */
    public function setMinimumScore(float $score): void
    {
        $this->minimumScore = max(0.0, min(1.0, $score));
    }
}
