<?php

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class TransferApiTest extends WebTestCase
{
    private const API_KEY = 'dev_secret_key_12345';

    protected function setUp(): void
    {
        parent::setUp();

        // Boot kernel to get container for DB cleanup
        self::bootKernel();
        $container = self::getContainer();

        /** @var \Doctrine\ORM\EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();

        // Load Fixtures
        $fixture = new \App\DataFixtures\AppFixtures();
        $loader = new \Doctrine\Common\DataFixtures\Loader();
        $loader->addFixture($fixture);

        $purger = new \Doctrine\Common\DataFixtures\Purger\ORMPurger();
        $executor = new \Doctrine\Common\DataFixtures\Executor\ORMExecutor($em, $purger);
        $executor->execute($loader->getFixtures());

        // Clear Redis idempotency keys (using raw client to avoid double prefixing)
        try {
            $redisUrl = $_SERVER['REDIS_URL'] ?? 'redis://redis:6379';
            $redis = new \Predis\Client($redisUrl);
            $keys = $redis->keys('transfer_api:idempotency:*');
            // fwrite(STDERR, "Found " . count($keys) . " keys to delete.\n");
            if (!empty($keys)) {
                $redis->del($keys);
            }
        } catch (\Throwable $e) {
            fwrite(STDERR, "Redis cleanup failed: " . $e->getMessage() . "\n");
        }

        // Shutdown kernel so tests can create their own client
        self::ensureKernelShutdown();
    }

    public function testHealthEndpoint(): void
    {
        $client = static::createClient([], ['CONTENT_TYPE' => 'application/json']);

        $client->request('GET', '/api/health');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertSame('healthy', $data['status']);
        $this->assertArrayHasKey('checks', $data);
        $this->assertSame('ok', $data['checks']['server']['status']);
        $this->assertSame('ok', $data['checks']['database']['status']);
    }

    public function testTransferWithoutApiKey(): void
    {
        $client = static::createClient([], ['CONTENT_TYPE' => 'application/json']);

        $client->request('POST', '/api/v1/transfers', [], [], [], json_encode([
            'source_account_uuid' => '123e4567-e89b-12d3-a456-426614174000',
            'destination_account_uuid' => '123e4567-e89b-12d3-a456-426614174001',
            'amount' => 1000,
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('error', $data['status']);
        $this->assertStringContainsString('Unauthorized', $data['message']);
    }

    public function testTransferWithoutIdempotencyKey(): void
    {
        $client = static::createClient([], ['CONTENT_TYPE' => 'application/json']);

        $client->request('POST', '/api/v1/transfers', [], [], [
            'HTTP_X-API-Key' => self::API_KEY,
        ], json_encode([
            'source_account_uuid' => '123e4567-e89b-12d3-a456-426614174000',
            'destination_account_uuid' => '123e4567-e89b-12d3-a456-426614174001',
            'amount' => 1000,
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertStringContainsString('Idempotency-Key', $data['message']);
    }

    public function testTransferWithInvalidJson(): void
    {
        $client = static::createClient([], ['CONTENT_TYPE' => 'application/json']);

        $client->request('POST', '/api/v1/transfers', [], [], [
            'HTTP_X-API-Key' => self::API_KEY,
            'HTTP_X-Idempotency-Key' => 'test-key-001',
        ], 'invalid json');

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('error', $data['status']);
        $this->assertStringContainsString('Request payload contains invalid', $data['message']);
    }

    public function testTransferWithMissingFields(): void
    {
        $client = static::createClient([], ['CONTENT_TYPE' => 'application/json']);

        $client->request('POST', '/api/v1/transfers', [], [], [
            'HTTP_X-API-Key' => self::API_KEY,
            'HTTP_X-Idempotency-Key' => 'test-key-002',
        ], json_encode([
            'source_account_uuid' => '123e4567-e89b-12d3-a456-426614174000',
            // Missing destination_account_uuid and amount
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('error', $data['status']);
        $this->assertStringContainsString('should be of type', $data['message']);
    }

    public function testTransferWithInvalidUuid(): void
    {
        $client = static::createClient([], ['CONTENT_TYPE' => 'application/json']);

        $client->request('POST', '/api/v1/transfers', [], [], [
            'HTTP_X-API-Key' => self::API_KEY,
            'HTTP_X-Idempotency-Key' => 'test-key-003',
        ], json_encode([
            'source_account_uuid' => 'invalid-uuid',
            'destination_account_uuid' => '123e4567-e89b-12d3-a456-426614174001',
            'amount' => 1000,
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('error', $data['status']);
    }

    public function testTransferWithNegativeAmount(): void
    {
        $client = static::createClient([], ['CONTENT_TYPE' => 'application/json']);

        $client->request('POST', '/api/v1/transfers', [], [], [
            'HTTP_X-API-Key' => self::API_KEY,
            'HTTP_X-Idempotency-Key' => 'test-key-004',
        ], json_encode([
            'source_account_uuid' => '123e4567-e89b-12d3-a456-426614174000',
            'destination_account_uuid' => '123e4567-e89b-12d3-a456-426614174001',
            'amount' => -1000,
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testSuccessfulTransfer(): void
    {
        $client = static::createClient([], ['CONTENT_TYPE' => 'application/json']);
        $container = $client->getContainer();

        // Create test accounts
        $em = $container->get('doctrine')->getManager();

        $sourceAccount = new \App\Entity\Account('Alice', \App\Enum\Currency::USD);
        $sourceAccount->credit(10000); // $100.00

        $destAccount = new \App\Entity\Account('Bob', \App\Enum\Currency::USD);

        $em->persist($sourceAccount);
        $em->persist($destAccount);
        $em->flush();

        $sourceUuid = $sourceAccount->getUuid();
        $destUuid = $destAccount->getUuid();

        // Make transfer request
        $client->request('POST', '/api/v1/transfers', [], [], [
            'HTTP_X-API-Key' => self::API_KEY,
            'HTTP_X-Idempotency-Key' => 'test-key-success-001',
        ], json_encode([
            'source_account_uuid' => $sourceUuid,
            'destination_account_uuid' => $destUuid,
            'amount' => 5000, // $50.00
            'description' => 'Test transfer',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('success', $data['status']);
        $this->assertSame('Transfer processed', $data['message']);

        // Verify balances
        $em->clear();
        $sourceAccount = $em->getRepository(\App\Entity\Account::class)->findByUuid($sourceUuid);
        $destAccount = $em->getRepository(\App\Entity\Account::class)->findByUuid($destUuid);

        $this->assertSame(5000, $sourceAccount->getBalance());
        $this->assertSame(5000, $destAccount->getBalance());
    }

    public function testTransferWithInsufficientFunds(): void
    {
        $client = static::createClient([], ['CONTENT_TYPE' => 'application/json']);
        $container = $client->getContainer();

        $em = $container->get('doctrine')->getManager();

        $sourceAccount = new \App\Entity\Account('Alice', \App\Enum\Currency::USD);
        $sourceAccount->credit(1000); // $10.00

        $destAccount = new \App\Entity\Account('Bob', \App\Enum\Currency::USD);

        $em->persist($sourceAccount);
        $em->persist($destAccount);
        $em->flush();

        $client->request('POST', '/api/v1/transfers', [], [], [
            'HTTP_X-API-Key' => self::API_KEY,
            'HTTP_X-Idempotency-Key' => 'test-key-insufficient-001',
        ], json_encode([
            'source_account_uuid' => $sourceAccount->getUuid(),
            'destination_account_uuid' => $destAccount->getUuid(),
            'amount' => 5000, // $50.00 - more than available
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('error', $data['status']);
        $this->assertStringContainsString('Insufficient funds', $data['message']);
    }

    public function testSelfTransferNotAllowed(): void
    {
        $client = static::createClient([], ['CONTENT_TYPE' => 'application/json']);
        $container = $client->getContainer();

        $em = $container->get('doctrine')->getManager();

        $account = new \App\Entity\Account('Alice', \App\Enum\Currency::USD);
        $account->credit(10000);

        $em->persist($account);
        $em->flush();

        $client->request('POST', '/api/v1/transfers', [], [], [
            'HTTP_X-API-Key' => self::API_KEY,
            'HTTP_X-Idempotency-Key' => 'test-key-self-001',
        ], json_encode([
            'source_account_uuid' => $account->getUuid(),
            'destination_account_uuid' => $account->getUuid(),
            'amount' => 1000,
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertStringContainsString('Self-transfer', $data['message']);
    }

    public function testTransferWithNonExistentAccount(): void
    {
        $client = static::createClient([], ['CONTENT_TYPE' => 'application/json']);
        $container = $client->getContainer();

        $em = $container->get('doctrine')->getManager();

        $sourceAccount = new \App\Entity\Account('Alice', \App\Enum\Currency::USD);
        $sourceAccount->credit(10000);

        $em->persist($sourceAccount);
        $em->flush();

        $client->request('POST', '/api/v1/transfers', [], [], [
            'HTTP_X-API-Key' => self::API_KEY,
            'HTTP_X-Idempotency-Key' => 'test-key-nonexistent-001',
        ], json_encode([
            'source_account_uuid' => $sourceAccount->getUuid(),
            'destination_account_uuid' => '123e4567-e89b-12d3-a456-426614174999',
            'amount' => 1000,
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertStringContainsString('not found', $data['message']);
    }

    public function testDuplicateIdempotencyKey(): void
    {
        $client = static::createClient([], ['CONTENT_TYPE' => 'application/json']);
        $container = $client->getContainer();

        $em = $container->get('doctrine')->getManager();

        $sourceAccount = new \App\Entity\Account('Alice', \App\Enum\Currency::USD);
        $sourceAccount->credit(10000);

        $destAccount = new \App\Entity\Account('Bob', \App\Enum\Currency::USD);

        $em->persist($sourceAccount);
        $em->persist($destAccount);
        $em->flush();

        $payload = json_encode([
            'source_account_uuid' => $sourceAccount->getUuid(),
            'destination_account_uuid' => $destAccount->getUuid(),
            'amount' => 1000,
        ]);

        // First request
        $client->request('POST', '/api/v1/transfers', [], [], [
            'HTTP_X-API-Key' => self::API_KEY,
            'HTTP_X-Idempotency-Key' => 'test-key-duplicate-001',
        ], $payload);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        // Second request with same idempotency key
        $client->request('POST', '/api/v1/transfers', [], [], [
            'HTTP_X-API-Key' => self::API_KEY,
            'HTTP_X-Idempotency-Key' => 'test-key-duplicate-001',
        ], $payload);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('success', $data['status']);
        $this->assertSame('Transfer processed', $data['message']);
    }

    public function testTransferBetweenDifferentCurrencies(): void
    {
        $client = static::createClient([], ['CONTENT_TYPE' => 'application/json']);
        $container = $client->getContainer();

        $em = $container->get('doctrine')->getManager();

        $sourceAccount = new \App\Entity\Account('Alice', \App\Enum\Currency::USD);
        $sourceAccount->credit(10000);

        $destAccount = new \App\Entity\Account('Bob', \App\Enum\Currency::EUR);

        $em->persist($sourceAccount);
        $em->persist($destAccount);
        $em->flush();

        $client->request('POST', '/api/v1/transfers', [], [], [
            'HTTP_X-API-Key' => self::API_KEY,
            'HTTP_X-Idempotency-Key' => 'test-key-currency-001',
        ], json_encode([
            'source_account_uuid' => $sourceAccount->getUuid(),
            'destination_account_uuid' => $destAccount->getUuid(),
            'amount' => 1000,
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertStringContainsString('Currency mismatch', $data['message']);
    }

    public function testInactiveAccountCannotTransfer(): void
    {
        $client = static::createClient([], ['CONTENT_TYPE' => 'application/json']);
        $container = $client->getContainer();

        $em = $container->get('doctrine')->getManager();

        $sourceAccount = new \App\Entity\Account('Alice', \App\Enum\Currency::USD, \App\Enum\AccountStatus::INACTIVE);
        $sourceAccount->credit(10000);

        $destAccount = new \App\Entity\Account('Bob', \App\Enum\Currency::USD);

        $em->persist($sourceAccount);
        $em->persist($destAccount);
        $em->flush();

        $client->request('POST', '/api/v1/transfers', [], [], [
            'HTTP_X-API-Key' => self::API_KEY,
            'HTTP_X-Idempotency-Key' => 'test-key-inactive-001',
        ], json_encode([
            'source_account_uuid' => $sourceAccount->getUuid(),
            'destination_account_uuid' => $destAccount->getUuid(),
            'amount' => 1000,
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertStringContainsString('not active', $data['message']);
    }
}
