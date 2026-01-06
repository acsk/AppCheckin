<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * CepController
 * 
 * Controller para consulta de CEP usando a API ViaCEP
 * Permite buscar informações de endereço a partir do CEP brasileiro
 * 
 * Rotas disponíveis:
 * - GET /cep/{cep} - Buscar dados do CEP
 * 
 * @package App\Controllers
 * @author App Checkin Team
 * @version 1.0.0
 */
class CepController
{
    /**
     * Buscar dados de endereço pelo CEP
     * 
     * @param Request $request Requisição HTTP
     * @param Response $response Resposta HTTP
     * @param array $args Argumentos da rota (cep)
     * @return Response JSON com dados do endereço
     * 
     * @api GET /cep/{cep}
     * @apiGroup CEP
     * @apiDescription Busca informações de endereço usando a API ViaCEP
     * 
     * @apiParam {String} cep CEP brasileiro (8 dígitos, com ou sem formatação)
     * 
     * @apiSuccess {String} cep CEP formatado
     * @apiSuccess {String} logradouro Rua/Avenida
     * @apiSuccess {String} complemento Complemento (quando disponível)
     * @apiSuccess {String} bairro Nome do bairro
     * @apiSuccess {String} localidade Cidade
     * @apiSuccess {String} uf Estado (sigla)
     * @apiSuccess {String} ibge Código IBGE
     * @apiSuccess {String} gia Código GIA
     * @apiSuccess {String} ddd DDD da região
     * @apiSuccess {String} siafi Código SIAFI
     * 
     * @apiError (400) CepInvalido CEP deve conter 8 dígitos
     * @apiError (404) CepNaoEncontrado CEP não encontrado na base de dados
     * @apiError (500) ErroConsulta Erro ao consultar API ViaCEP
     * 
     * @apiExample {curl} Exemplo de uso:
     *     curl -X GET http://api/cep/01310100
     *     curl -X GET http://api/cep/01310-100
     */
    public function buscar(Request $request, Response $response, array $args): Response
    {
        $cep = $args['cep'] ?? '';
        
        // Remover caracteres não numéricos
        $cepLimpo = preg_replace('/[^0-9]/', '', $cep);
        
        // Validar CEP (deve ter 8 dígitos)
        if (strlen($cepLimpo) !== 8) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'CEP inválido. Deve conter 8 dígitos.'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        try {
            // Consultar API ViaCEP
            $url = "https://viacep.com.br/ws/{$cepLimpo}/json/";
            
            // Usar cURL para fazer a requisição
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Para evitar problemas de SSL em ambiente de desenvolvimento
            
            $resultado = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $erro = curl_error($ch);
            curl_close($ch);
            
            if ($erro) {
                throw new \Exception("Erro na requisição: {$erro}");
            }
            
            if ($httpCode !== 200) {
                throw new \Exception("API ViaCEP retornou status {$httpCode}");
            }
            
            $dados = json_decode($resultado, true);
            
            // Verificar se o CEP foi encontrado
            if (isset($dados['erro']) && $dados['erro'] === true) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'CEP não encontrado'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }
            
            // Verificar se os dados estão vazios (CEP válido mas sem informações)
            $dadosVazios = empty($dados['logradouro']) && 
                          empty($dados['bairro']) && 
                          empty($dados['localidade']);
            
            if ($dadosVazios) {
                $response->getBody()->write(json_encode([
                    'type' => 'warning',
                    'message' => 'CEP válido, mas não há dados disponíveis. Tente outro CEP.',
                    'data' => [
                        'cep' => $dados['cep'] ?? $cepLimpo,
                        'logradouro' => '',
                        'complemento' => '',
                        'bairro' => '',
                        'cidade' => '',
                        'estado' => '',
                        'ibge' => '',
                        'gia' => '',
                        'ddd' => '',
                        'siafi' => ''
                    ]
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json');
            }
            
            // Retornar dados do endereço
            $response->getBody()->write(json_encode([
                'type' => 'success',
                'data' => [
                    'cep' => $dados['cep'] ?? '',
                    'logradouro' => $dados['logradouro'] ?? '',
                    'complemento' => $dados['complemento'] ?? '',
                    'bairro' => $dados['bairro'] ?? '',
                    'cidade' => $dados['localidade'] ?? '',
                    'estado' => $dados['uf'] ?? '',
                    'ibge' => $dados['ibge'] ?? '',
                    'gia' => $dados['gia'] ?? '',
                    'ddd' => $dados['ddd'] ?? '',
                    'siafi' => $dados['siafi'] ?? ''
                ]
            ], JSON_UNESCAPED_UNICODE));
            
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            error_log("Erro ao consultar CEP: " . $e->getMessage());
            
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao consultar CEP. Tente novamente mais tarde.'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
}
