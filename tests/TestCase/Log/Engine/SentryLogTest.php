<?php
declare(strict_types=1);

namespace CakeSentry\Test\TestCase\Log\Engine;

use Cake\Log\Formatter\DefaultFormatter;
use Cake\TestSuite\TestCase;
use CakeSentry\Log\Engine\SentryLog;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LogLevel;
use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\Options;
use Sentry\SentrySdk;
use Sentry\State\Hub;

class SentryLogTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected Hub $originalHub;

    public function setUp(): void
    {
        parent::setUp();
        $this->skipIf(!method_exists('Sentry\Logs\Log', 'getPsrLevel'), 'Sentry SDK too low');

        $this->originalHub = SentrySdk::getCurrentHub();
    }

    public function tearDown(): void
    {
        SentrySdk::setCurrentHub($this->originalHub);

        parent::tearDown();
    }

    #[DataProvider('logLevelProvider')]
    public function testLogSendsFormattedLogsToSentry(
        string $level,
        string $expectedPsrLevel,
        bool $expectsContextAttributes,
    ): void {
        $client = $this->createClientMock(function (Event $event) use ($level, $expectedPsrLevel, $expectsContextAttributes): void {
            $logs = $event->getLogs();

            $this->assertCount(1, $logs);
            $this->assertSame(sprintf('%s: Message 42', $level), $logs[0]->getBody());
            $this->assertSame($expectedPsrLevel, $logs[0]->getPsrLevel());

            $attributes = $logs[0]->attributes()->toSimpleArray();
            if ($expectsContextAttributes) {
                $this->assertSame(42, $attributes['userId']);
                $this->assertSame('test', $attributes['scope']);
            } else {
                $this->assertArrayNotHasKey('userId', $attributes);
                $this->assertArrayNotHasKey('scope', $attributes);
            }
        });

        SentrySdk::setCurrentHub(new Hub($client));

        $logger = new SentryLog([
            'formatter' => [
                'className' => DefaultFormatter::class,
                'includeDate' => false,
            ],
        ]);

        $logger->log($level, 'Message {userId}', ['userId' => 42, 'scope' => 'test']);
    }

    public static function logLevelProvider(): array
    {
        return [
            'warning' => [LogLevel::WARNING, LogLevel::WARNING, true],
            'error' => [LogLevel::ERROR, LogLevel::ERROR, false],
            'notice' => [LogLevel::NOTICE, LogLevel::INFO, true],
            'debug' => [LogLevel::DEBUG, LogLevel::DEBUG, true],
            'emergency' => [LogLevel::EMERGENCY, LogLevel::CRITICAL, true],
            'custom defaults to trace' => ['custom', LogLevel::DEBUG, true],
        ];
    }

    public function testLogSkipsImmediateFlushWhenDeferred(): void
    {
        $client = Mockery::mock(ClientInterface::class);
        $client->shouldReceive('getOptions')->andReturn(new Options([
            'dsn' => 'https://public@example.com/1',
            'enable_logs' => true,
        ]));
        $client->shouldReceive('captureEvent')
            ->once()
            ->withArgs(function (Event $event): bool {
                $logs = $event->getLogs();

                $this->assertCount(1, $logs);
                $this->assertSame('info: Deferred message', $logs[0]->getBody());
                $this->assertSame('test', $logs[0]->attributes()->toSimpleArray()['scope']);

                return true;
            })
            ->andReturnNull();

        SentrySdk::setCurrentHub(new Hub($client));

        $logger = new SentryLog([
            'formatter' => [
                'className' => DefaultFormatter::class,
                'includeDate' => false,
            ],
        ]);
        $logger->logsWillBeFlushed = true;

        $logger->log(LogLevel::INFO, 'Deferred message', ['scope' => 'test']);

        $logs = SentrySdk::getCurrentRuntimeContext()->getLogsAggregator()->all();
        $this->assertCount(1, $logs);
        $this->assertSame('info: Deferred message', $logs[0]->getBody());
        $this->assertSame('test', $logs[0]->attributes()->toSimpleArray()['scope']);

        SentrySdk::getCurrentRuntimeContext()->getLogsAggregator()->flush();
    }

    public function testLegacyNamespaceAliasesToNewClass(): void
    {
        $this->expectDeprecationMessageMatches(
            '/Use `CakeSentry\\\\Log\\\\Engine\\\\SentryLog` instead of `CakeSentry\\\\Log\\\\Engines\\\\SentryLog`\./',
            function (): void {
                require dirname(__DIR__, 4) . '/src/Log/Engines/SentryLog.php';
            },
        );

        $legacyClass = 'CakeSentry\\Log\\Engines\\SentryLog';

        $this->assertTrue(class_exists($legacyClass, false));
        $this->assertSame(SentryLog::class, get_class(new $legacyClass([])));
    }

    protected function createClientMock(callable $captureEventAssertion): ClientInterface
    {
        $client = Mockery::mock(ClientInterface::class);
        $client->shouldReceive('getOptions')->andReturn(new Options([
            'dsn' => 'https://public@example.com/1',
            'enable_logs' => true,
        ]));
        $client->shouldReceive('captureEvent')
            ->once()
            ->withArgs(function (Event $event) use ($captureEventAssertion): bool {
                $captureEventAssertion($event);

                return true;
            })
            ->andReturnNull();

        return $client;
    }
}
