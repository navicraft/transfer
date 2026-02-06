<?php

namespace App\Service;

use Predis\Client as RedisClient;
use Psr\Log\LoggerInterface;

/**
 * Redis-based idempotency service for fast duplicate detection
 */
final class IdempotencyService
{
    private const TTL_SECONDS = 86400; // 24 hours
    private const KEY_PREFIX = 'idempotency:';

    public function __construct(
        private readonly RedisClient $redis,
        private readonly LoggerInterface $logger
    ) {
    }

    public function exists(string $key): bool
    {
        try {
            return (bool) $this->redis->exists(self::KEY_PREFIX . $key);
        } catch (\Throwable $e) {
            $this->logger->error('Redis idempotency check failed', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function markAsProcessing(string $key): bool
    {
        try {
            $result = $this->redis->set(
                self::KEY_PREFIX . $key,
                json_encode([
                    'status' => 'processing',
                    'started_at' => time(),
                ]),
                'EX',
                self::TTL_SECONDS,
                'NX'
            );

            return $result !== null;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to mark idempotency key as processing', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function storeResult(string $key, array $result): void
    {
        try {
            $this->redis->setex(
                self::KEY_PREFIX . $key,
                self::TTL_SECONDS,
                json_encode([
                    'status' => 'completed',
                    'result' => $result,
                    'completed_at' => time(),
                ])
            );
        } catch (\Throwable $e) {
            $this->logger->error('Failed to cache idempotency result', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function storeFailure(string $key, string $reason): void
    {
        try {
            $this->redis->setex(
                self::KEY_PREFIX . $key,
                self::TTL_SECONDS,
                json_encode([
                    'status' => 'failed',
                    'reason' => $reason,
                    'failed_at' => time(),
                ])
            );
        } catch (\Throwable $e) {
            $this->logger->error('Failed to cache idempotency failure', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function getResult(string $key): ?array
    {
        try {
            $data = $this->redis->get(self::KEY_PREFIX . $key);
            if ($data === null) {
                return null;
            }
            return json_decode($data, true);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to get idempotency result', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
