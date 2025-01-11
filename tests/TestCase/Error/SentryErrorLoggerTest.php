<?php
declare(strict_types=1);

namespace CakeSentry\Test\TestCase\Error;

use Cake\Core\Configure;
use Cake\Error\PhpError;
use CakeSentry\Error\SentryErrorLogger;
use CakeSentry\Http\SentryClient;
use Mockery;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class SentryErrorLoggerTest extends TestCase
{
    protected SentryErrorLogger $logger;

    protected SentryClient $client;

    /**
     * @inheritDoc
     */
    public function setUp(): void
    {
        parent::setUp();

        Configure::write('Sentry.dsn', 'https://yourtoken@example.com/yourproject/1');
        $logger = new SentryErrorLogger([]);
        $this->logger = $logger;
        $this->client = Mockery::mock(SentryClient::class);
    }

    /**
     * Test for logException()
     */
    public function testLogException()
    {
        $excpetion = new RuntimeException('some error');
        $this->client->shouldReceive('captureException')->with($excpetion, null);
        $this->assertNull($this->logger->logException($excpetion));
    }

    /**
     * Test for logError()
     */
    public function testLogError()
    {
        $phpError = new PhpError(E_USER_WARNING, 'some error');
        $this->client->shouldReceive('captureError')->with($phpError, null);
        $this->assertNull($this->logger->logError($phpError));
    }
}
