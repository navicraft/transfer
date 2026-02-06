<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HealthController extends AbstractController
{
    public function __construct(
        private readonly Connection $connection
    ) {
    }

    #[Route('/api/health', name: 'api_health', methods: ['GET'])]
    public function check(): JsonResponse
    {
        $health = [
            'status' => 'healthy',
            'timestamp' => time(),
            'checks' => [],
        ];

        // Check server
        $health['checks']['server'] = [
            'status' => 'ok',
            'message' => 'Server is running',
        ];

        // Check database connectivity
        try {
            $this->connection->executeQuery('SELECT 1');
            $health['checks']['database'] = [
                'status' => 'ok',
                'message' => 'Database connection successful',
            ];
        } catch (\Throwable $e) {
            $health['status'] = 'unhealthy';
            $health['checks']['database'] = [
                'status' => 'error',
                'message' => 'Database connection failed: ' . $e->getMessage(),
            ];

            return $this->json($health, Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return $this->json($health, Response::HTTP_OK);
    }
}
