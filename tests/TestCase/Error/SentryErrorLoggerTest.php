<?php
declare(strict_types=1);

namespace CakeSentry\Test\TestCase\Error;

use Cake\Core\Configure;
use Cake\Error\ErrorLogger;
use Cake\Error\PhpError;
use CakeSentry\Error\SentryErrorLogger;
use CakeSentry\Http\SentryClient;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use ReflectionProperty;
use RuntimeException;
use Stringable;

class SentryErrorLoggerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected SentryErrorLogger $logger;

    protected SentryClient $client;

    /**
     * @inheritDoc
     */
    public function setUp(): void
    {
        parent::setUp();

        Configure::write('Sentry.dsn', 'https://yourtoken@example.com/yourproject/1');
        $this->logger = new SentryErrorLogger([]);
        $this->client = Mockery::mock(SentryClient::class);
        $this->setProperty($this->logger, 'client', $this->client);
    }

    /**
     * Test for logException()
     */
    public function testLogException(): void
    {
        $exception = new RuntimeException('some error');
        $this->client->shouldReceive('captureException')->once()->with($exception, null);

        $this->logger->logException($exception);
        $this->assertTrue(true);
    }

    /**
     * Test for logError()
     */
    public function testLogError(): void
    {
        $phpError = new PhpError(E_USER_WARNING, 'some error');
        $this->client->shouldReceive('captureError')->once()->with($phpError, null);

        $this->logger->logError($phpError);
        $this->assertTrue(true);
    }

    public function testLogExceptionSkipsSentryWhenDsnMissing(): void
    {
        Configure::delete('Sentry');
        $logger = new SentryErrorLogger([]);
        $client = Mockery::mock(SentryClient::class);
        $client->shouldNotReceive('captureException');
        $this->setProperty($logger, 'client', $client);

        $logger->logException(new RuntimeException('some error'));
        $this->assertTrue(true);
    }

    public function testLogErrorSkipsSentryWhenDsnMissing(): void
    {
        Configure::delete('Sentry');
        $logger = new SentryErrorLogger([]);
        $client = Mockery::mock(SentryClient::class);
        $client->shouldNotReceive('captureError');
        $this->setProperty($logger, 'client', $client);

        $logger->logError(new PhpError(E_USER_WARNING, 'some error'));
        $this->assertTrue(true);
    }

    public function testPsrLogMethodsDelegateToCakeErrorLogger(): void
    {
        $cakeLogger = Mockery::mock(ErrorLogger::class);
        $this->setProperty($this->logger, 'logger', $cakeLogger);
        $context = ['userId' => 42];

        $levels = [
            LogLevel::EMERGENCY,
            LogLevel::ALERT,
            LogLevel::CRITICAL,
            LogLevel::ERROR,
            LogLevel::WARNING,
            LogLevel::NOTICE,
            LogLevel::INFO,
            LogLevel::DEBUG,
        ];
        foreach ($levels as $level) {
            $cakeLogger->shouldReceive('log')->once()->with($level, 'Message', $context);
            $this->logger->{$level}('Message', $context);
        }
    }

    public function testLogDelegatesStringableMessageAndContext(): void
    {
        $cakeLogger = Mockery::mock(ErrorLogger::class);
        $this->setProperty($this->logger, 'logger', $cakeLogger);
        $message = new class implements Stringable {
            public function __toString(): string
            {
                return 'Message';
            }
        };
        $context = ['userId' => 42];
        $cakeLogger->shouldReceive('log')->once()->with(LogLevel::ERROR, $message, $context);

        $this->logger->log(LogLevel::ERROR, $message, $context);
    }

    private function setProperty(object $subject, string $property, mixed $value): void
    {
        $reflection = new ReflectionProperty($subject, $property);
        $reflection->setValue($subject, $value);
    }
}
