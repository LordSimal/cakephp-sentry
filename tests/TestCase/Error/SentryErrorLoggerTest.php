<?php
declare(strict_types=1);

namespace CakeSentry\Test\TestCase\Error;

use Cake\Core\Configure;
use Cake\Error\PhpError;
use CakeSentry\Error\SentryErrorLogger;
use CakeSentry\Http\SentryClient;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;

final class SentryErrorLoggerTest extends TestCase
{
    private SentryErrorLogger $subject;

    /**
     * @inheritDoc
     */
    public function setUp(): void
    {
        parent::setUp();

        Configure::write('Sentry.dsn', 'https://yourtoken@example.com/yourproject/1');
        $subject = new SentryErrorLogger([]);

        $clientMock = $this->createMock(SentryClient::class);
        $this->subject = $subject;

        $clientProp = $this->getClientProp();
        $clientProp->setValue($this->subject, $clientMock);
    }

    /**
     * Test for logException()
     */
    public function testLogException()
    {
        $excpetion = new RuntimeException('some error');

        $client = $this->getClientProp()->getValue($this->subject);
        $client->expects($this->once())
            ->method('captureException')
            ->with($excpetion);

        $this->subject->logException($excpetion);
    }

    /**
     * Test for logError()
     */
    public function testLogError()
    {
        $phpError = new PhpError(E_USER_WARNING, 'some error');

        $client = $this->getClientProp()->getValue($this->subject);
        $client->expects($this->once())
            ->method('captureError')
            ->with($phpError);

        $this->subject->logError($phpError);
    }

    /**
     * Helper access subject::$client(reflection)
     *
     * @return ReflectionProperty Client reflection
     */
    private function getClientProp()
    {
        $clientProp = new ReflectionProperty($this->subject, 'client');
        $clientProp->setAccessible(true);

        return $clientProp;
    }
}
