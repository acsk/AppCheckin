<?php
namespace App\Controllers;

use App\Models\FeatureFlag;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class FeatureFlagController
{
    private FeatureFlag $featureFlagModel;

    public function __construct($db)
    {
        $this->featureFlagModel = new FeatureFlag($db);
    }

    public function list(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenantId');
        $db = require __DIR__ . '/../../config/database.php';
        $stmt = $db->prepare("SELECT * FROM feature_flags WHERE scope='global' OR (scope='tenant' AND tenant_id = :tenant_id)");
        $stmt->execute(['tenant_id' => $tenantId ?? 0]);
        $flags = $stmt->fetchAll();
        $response->getBody()->write(json_encode(['flags' => $flags]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function get(Request $request, Response $response, array $args): Response
    {
        $tenantId = $request->getAttribute('tenantId');
        $key = $args['key'] ?? '';
        $flag = $this->featureFlagModel->getFlag($key, $tenantId);
        $enabled = $this->featureFlagModel->isEnabled($key, $tenantId);
        $response->getBody()->write(json_encode([
            'key' => $key,
            'enabled' => $enabled,
            'flag' => $flag
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
