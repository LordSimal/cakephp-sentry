<?php
declare(strict_types=1);

namespace CakeSentry\Test\TestCase\Http;

use Cake\Core\Configure;
use Cake\Error\PhpError;
use Cake\Event\Event;
use Cake\Event\EventManager;
use Cake\TestSuite\TestCase;
use CakeSentry\CakeSentryInit;
use CakeSentry\Http\SentryClient;
use Exception;
use Mockery;
use RuntimeException;
use Sentry\ClientBuilder;
use Sentry\ClientInterface;
use Sentry\Event as SentryEvent;
use Sentry\EventHint;
use Sentry\EventId;
use Sentry\Options;
use Sentry\State\Hub;

class ClientTest extends TestCase
{
    /**
     * @inheritDoc
     */
    public function setUp(): void
    {
        parent::setUp();

        Configure::write('Sentry.dsn', 'https://yourtoken@example.com/yourproject/1');
    }

    /**
     * Check constructor sets Hub instance
     */
    public function testSetupClient(): void
    {
        $subject = new SentryClient();

        $this->assertInstanceOf(Hub::class, $subject->getHub());
    }

    /**
     * Check constructor does not throw exception if no DSN is set
     */
    public function testSetupClientNotHasDsn(): void
    {
        Configure::delete('Sentry.dsn');
        $client = new SentryClient();
        $this->assertInstanceOf(SentryClient::class, $client);
    }

    /**
     * Check constructor passes options to sentry client
     */
    public function testSetupClientSetOptions(): void
    {
        Configure::write('Sentry.server_name', 'test-server');

        CakeSentryInit::init();
        $subject = new SentryClient();
        $options = $subject->getHub()->getClient()->getOptions();

        $this->assertSame('test-server', $options->getServerName());
    }

    /**
     * Check constructor fill before_send option
     */
    public function testSetupClientSetSendCallback(): void
    {
        $callback = function (SentryEvent $event, ?EventHint $hint) {
            return 'this is user callback';
        };
        Configure::write('Sentry.before_send', $callback);

        CakeSentryInit::init();
        $subject = new SentryClient();
        $actual = $subject
            ->getHub()
            ->getClient()
            ->getOptions()
            ->getBeforeSendCallback();

        $this->assertSame(
            $callback(SentryEvent::createEvent(), null),
            $actual(SentryEvent::createEvent(), null)
        );

        restore_error_handler();
        restore_exception_handler();
    }

    /**
     * Check constructor dispatch event Client.afterSetup
     */
    public function testSetupClientDispatchAfterSetup(): void
    {
        $called = false;
        EventManager::instance()->on(
            'CakeSentry.Client.afterSetup',
            function () use (&$called) {
                $called = true;
            }
        );

        CakeSentryInit::init();
        new SentryClient();

        $this->assertTrue($called);
    }

    /**
     * Test capture exception
     */
    public function testCaptureException(): void
    {
        $subject = new SentryClient();
        $client = Mockery::mock(ClientInterface::class);
        $client->shouldReceive('captureException')->once()->andReturn(null);
        $subject->getHub()->bindClient($client);

        $exception = new RuntimeException('something wrong.');
        $subject->captureException($exception);
        $this->assertTrue(true);
    }

    /**
     * Test capture error
     */
    public function testCaptureError(): void
    {
        $subject = new SentryClient();
        $options = new Options();
        $clientBuilder = new ClientBuilder($options);
        $client = $clientBuilder->getClient();
        $subject->getHub()->bindClient($client);

        $error = new PhpError(E_USER_WARNING, 'something wrong.', '/my/app/path/test.php', 123);
        $subject->captureError($error);

        $result = $client->captureMessage($error->getMessage());
        $this->assertInstanceOf(EventId::class, $result);
    }

    /**
     * Test capture error with unknown lines '??'
     */
    public function testCaptureErrorWithUnknownLines(): void
    {
        $subject = new SentryClient();
        $options = new Options();
        $clientBuilder = new ClientBuilder($options);
        $client = $clientBuilder->getClient();
        $subject->getHub()->bindClient($client);

        $trace = [
            [
                'file' => '[internal]',
                'line' => '??',
            ],
        ];
        $error = new PhpError(E_USER_WARNING, 'something wrong.', '/my/app/path/test.php', 123, $trace);
        $subject->captureError($error);

        $result = $client->captureMessage($error->getMessage());
        $this->assertInstanceOf(EventId::class, $result);
    }

    /**
     * Test capture exception pass cakephp-log's context as additional data
     */
    public function testCaptureExceptionWithAdditionalData(): void
    {
        $callback = function (SentryEvent $event, ?EventHint $hint) use (&$actualEvent) {
            $actualEvent = $event;
        };

        $userConfig = [
            'dsn' => false,
            'before_send' => $callback,
        ];

        Configure::write('Sentry', $userConfig);
        CakeSentryInit::init();
        $subject = new SentryClient();

        $extras = ['this is' => 'additional'];
        $exception = new RuntimeException('Some error');
        $subject->captureException($exception, null, $extras);

        $this->assertSame($extras, $actualEvent->getExtra());
    }

    /**
     * Test capture error pass cakephp-log's context as additional data
     */
    public function testCaptureErrorWithAdditionalData(): void
    {
        $callback = function (SentryEvent $event, ?EventHint $hint) use (&$actualEvent) {
            $actualEvent = $event;
        };

        $userConfig = [
            'dsn' => false,
            'before_send' => $callback,
        ];

        Configure::write('Sentry', $userConfig);
        CakeSentryInit::init();
        $subject = new SentryClient();

        $extras = ['this is' => 'additional'];
        $phpError = new PhpError(E_USER_WARNING, 'Some error', '/my/app/path/test.php', 123);
        $subject->captureError($phpError, null, $extras);

        $this->assertSame($extras, $actualEvent->getExtra());
    }

    /**
     * Check capture dispatch before exception capture
     */
    public function testCaptureDispatchBeforeExceptionCapture(): void
    {
        $subject = new SentryClient();
        $client = Mockery::mock(ClientInterface::class);
        $client->shouldReceive('captureException')->andReturn(null);
        $subject->getHub()->bindClient($client);

        $called = false;
        EventManager::instance()->on(
            'CakeSentry.Client.beforeCapture',
            function () use (&$called) {
                $called = true;
            }
        );

        $exception = new RuntimeException('Some error');
        $subject->captureException($exception, null, ['exception' => new Exception()]);

        $this->assertTrue($called);
    }

    /**
     * Check capture dispatch before error capture
     */
    public function testCaptureDispatchBeforeErrorCapture(): void
    {
        $subject = $this->getClient();

        $called = false;
        EventManager::instance()->on(
            'CakeSentry.Client.beforeCapture',
            function () use (&$called) {
                $called = true;
            }
        );

        $phpError = new PhpError(E_USER_WARNING, 'Some error', '/my/app/path/test.php', 123);
        $subject->captureError($phpError, null, ['exception' => new Exception()]);

        $this->assertTrue($called);
    }

    /**
     * Check capture dispatch after exception capture and receives lastEventId
     */
    public function testCaptureDispatchAfterExceptionCapture(): void
    {
        $subject = $this->getClient();

        $called = false;
        EventManager::instance()->on(
            'CakeSentry.Client.afterCapture',
            function (Event $event) use (&$called, &$actualLastEventId) {
                $called = true;
                $actualLastEventId = $event->getData('lastEventId');
            }
        );

        $phpError = new RuntimeException('Some error');
        $subject->captureException($phpError, null, ['exception' => new Exception()]);

        $this->assertTrue($called);
        $this->assertInstanceOf(EventId::class, $actualLastEventId);
    }

    /**
     * Check capture dispatch after error capture and receives lastEventId
     */
    public function testCaptureDispatchAfterErrorCapture(): void
    {
        $subject = $this->getClient();

        $called = false;
        EventManager::instance()->on(
            'CakeSentry.Client.afterCapture',
            function (Event $event) use (&$called, &$actualLastEventId) {
                $called = true;
                $actualLastEventId = $event->getData('lastEventId');
            }
        );

        $phpError = new PhpError(E_USER_WARNING, 'Some error', '/my/app/path/test.php', 123);
        $subject->captureError($phpError, null, ['exception' => new Exception()]);

        $this->assertTrue($called);
        $this->assertInstanceOf(EventId::class, $actualLastEventId);
    }

    private function getClient(): SentryClient
    {
        $subject = new SentryClient();
        $options = new Options();
        $clientBuilder = new ClientBuilder($options);
        $client = $clientBuilder->getClient();
        $subject->getHub()->bindClient($client);

        return $subject;
    }
}
