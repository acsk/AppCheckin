<?php

namespace App\Services;

/**
 * Serviço de Criptografia para dados sensíveis
 * 
 * Usa AES-256-GCM para criptografia autenticada
 */
class EncryptionService
{
    private string $key;
    private string $cipher = 'aes-256-gcm';
    
    public function __construct()
    {
        // Usar chave do ambiente ou derivar do JWT_SECRET
        $secret = $_ENV['ENCRYPTION_KEY'] ?? $_SERVER['ENCRYPTION_KEY'] ?? null;
        
        if (!$secret) {
            // Fallback: usar hash do JWT_SECRET
            $jwtSecret = $_ENV['JWT_SECRET'] ?? $_SERVER['JWT_SECRET'] ?? 'default_key';
            $secret = hash('sha256', $jwtSecret, true);
        } else {
            $secret = hash('sha256', $secret, true);
        }
        
        $this->key = $secret;
    }
    
    /**
     * Criptografar um valor
     * 
     * @param string $value Valor a ser criptografado
     * @return string Valor criptografado em base64
     */
    public function encrypt(string $value): string
    {
        $iv = random_bytes(openssl_cipher_iv_length($this->cipher));
        $tag = '';
        
        $encrypted = openssl_encrypt(
            $value,
            $this->cipher,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );
        
        if ($encrypted === false) {
            throw new \Exception('Falha ao criptografar dados');
        }
        
        // Combinar IV + tag + dados criptografados
        $combined = $iv . $tag . $encrypted;
        
        return base64_encode($combined);
    }
    
    /**
     * Descriptografar um valor
     * 
     * @param string $encryptedValue Valor criptografado em base64
     * @return string Valor descriptografado
     */
    public function decrypt(string $encryptedValue): string
    {
        $combined = base64_decode($encryptedValue);
        
        if ($combined === false) {
            throw new \Exception('Dados criptografados inválidos');
        }
        
        $ivLength = openssl_cipher_iv_length($this->cipher);
        $tagLength = 16; // GCM tag length
        
        $iv = substr($combined, 0, $ivLength);
        $tag = substr($combined, $ivLength, $tagLength);
        $encrypted = substr($combined, $ivLength + $tagLength);
        
        $decrypted = openssl_decrypt(
            $encrypted,
            $this->cipher,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );
        
        if ($decrypted === false) {
            throw new \Exception('Falha ao descriptografar dados');
        }
        
        return $decrypted;
    }
    
    /**
     * Verificar se um valor está criptografado
     * 
     * @param string $value Valor a verificar
     * @return bool
     */
    public function isEncrypted(string $value): bool
    {
        // Tentar decodificar base64 e verificar tamanho mínimo
        $decoded = base64_decode($value, true);
        if ($decoded === false) {
            return false;
        }
        
        // IV (12) + Tag (16) + pelo menos 1 byte de dados
        return strlen($decoded) > 28;
    }
}
