<?php

namespace App\Services\EntidadeJuridica;

/**
 * Regra de CNPJ compartilhado violada. Carrega o status HTTP e um código estável para a tela.
 */
class EntidadeJuridicaException extends \RuntimeException
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(
        string $message,
        public readonly int $status = 422,
        public readonly string $codigo = 'CNPJ_INVALIDO',
        public readonly array $errors = [],
    ) {
        parent::__construct($message);
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function toResult(): array
    {
        $body = ['type' => 'error', 'code' => $this->codigo, 'message' => $this->getMessage()];
        if ($this->errors !== []) {
            $body['errors'] = $this->errors;
        }

        return ['status' => $this->status, 'body' => $body];
    }
}
