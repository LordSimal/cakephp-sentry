<?php
declare(strict_types=1);

namespace CakeSentry\Http;

use Cake\Core\Configure;
use Cake\Database\Driver;
use Cake\Datasource\ConnectionManager;
use Cake\Error\PhpError;
use Cake\Event\Event;
use Cake\Event\EventDispatcherInterface;
use Cake\Event\EventDispatcherTrait;
use CakeSentry\Database\Log\CakeSentryLog;
use Psr\Http\Message\ServerRequestInterface;
use Sentry\Breadcrumb;
use Sentry\EventHint;
use Sentry\SentrySdk;
use Sentry\Severity;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use Throwable;
use function Sentry\captureException;
use function Sentry\captureMessage;

/**
 * @implements \Cake\Event\EventDispatcherInterface<\CakeSentry\Http\SentryClient>
 */
class SentryClient implements EventDispatcherInterface
{
    /** @use \Cake\Event\EventDispatcherTrait<\CakeSentry\Http\SentryClient> */
    use EventDispatcherTrait;

    protected HubInterface $hub;

    /**
     * Loggers connected
     */
    protected array $_loggers = [];

    /**
     * Initialize the hub for this client
     */
    public function __construct()
    {
        $this->hub = SentrySdk::getCurrentHub();
    }

    /**
     * @return void
     */
    protected function getQueryLoggers(): void
    {
        $configs = ConnectionManager::configured();
        $includeSchemaReflection = (bool)Configure::read('CakeSentry.includeSchemaReflection');

        foreach ($configs as $name) {
            $logger = null;
            $connection = ConnectionManager::get($name);
            if ($connection->configName() === 'debug_kit') {
                continue;
            }
            /** @var \Cake\Database\Driver|object $driver */
            $driver = $connection->getDriver();

            if ($driver instanceof Driver) {
                $logger = $driver->getLogger();
            }

            if ($logger instanceof CakeSentryLog) {
                $logger->setIncludeSchema($includeSchemaReflection);
                $this->_loggers[] = $logger;
            }
        }
    }

    /**
     * Add an extra breadcrumb to the event foreach query executed in each logger
     *
     * @return void
     */
    protected function addQueryBreadcrumbs(): void
    {
        if ($this->_loggers) {
            foreach ($this->_loggers as $logger) {
                /** @var array<\Cake\Database\Log\LoggedQuery> $queries */
                $queries = $logger->queries();
                if (empty($queries)) {
                    continue;
                }

                foreach ($queries as $query) {
                    $context = $query->getContext();
                    $data = ['connectionName' => $logger->name()];
                    $data['executionTimeMs'] = $context['took'];
                    $data['rows'] = $context['numRows'];
                    $data['role'] = $context['role'];

                    $this->hub->addBreadcrumb(
                        new Breadcrumb(
                            level: Breadcrumb::LEVEL_INFO,
                            type: Breadcrumb::TYPE_DEFAULT,
                            category: 'sql.query',
                            message: $context['query'],
                            metadata: $data
                        )
                    );
                }
            }
        }
    }

    /**
     * Method responsible for passing on the exception to sentry
     *
     * @param \Throwable $exception The thrown exception
     * @param \Psr\Http\Message\ServerRequestInterface|null $request The associated request object if available
     * @param array|null $extras Extras passed down to the hub
     * @return void
     */
    public function captureException(
        Throwable $exception,
        ?ServerRequestInterface $request = null,
        ?array $extras = null
    ): void {
        $eventManager = $this->getEventManager();
        $event = new Event('CakeSentry.Client.beforeCapture', $this, compact('exception', 'request'));
        $eventManager->dispatch($event);

        if ($extras !== null) {
            $this->hub->configureScope(function (Scope $scope) use ($extras): void {
                $scope->setExtras($extras);
            });
        }

        $this->getQueryLoggers();
        $this->addQueryBreadcrumbs();

        $lastEventId = captureException($exception);
        $event = new Event('CakeSentry.Client.afterCapture', $this, compact('exception', 'request', 'lastEventId'));
        $eventManager->dispatch($event);
    }

    /**
     * Method responsible for passing on the error to sentry
     *
     * @param \Cake\Error\PhpError $error The error instance
     * @param \Psr\Http\Message\ServerRequestInterface|null $request The associated request object if available
     * @param array|null $extras Extras passed down to the hub
     * @return void
     */
    public function captureError(
        PhpError $error,
        ?ServerRequestInterface $request = null,
        ?array $extras = null
    ): void {
        $eventManager = $this->getEventManager();
        $event = new Event('CakeSentry.Client.beforeCapture', $this, compact('error', 'request'));
        $eventManager->dispatch($event);

        if ($extras !== null) {
            $this->hub->configureScope(function (Scope $scope) use ($extras): void {
                $scope->setExtras($extras);
            });
        }

        $this->getQueryLoggers();
        $this->addQueryBreadcrumbs();

        $client = $this->hub->getClient();
        if ($client) {
            /** @var list<array{function?: string, line?: int, file?: string, class?: class-string, type?: string, args?: array}> $trace */
            $trace = $this->cleanedTrace($error->getTrace());
            /** @psalm-suppress ArgumentTypeCoercion */
            $stacktrace = $client->getStacktraceBuilder()
                ->buildFromBacktrace($trace, $error->getFile() ?? 'unknown file', $error->getLine() ?? 0);
            $hint = EventHint::fromArray([
                'stacktrace' => $stacktrace,
            ]);
        }

        $lastEventId = captureMessage(
            $error->getMessage(),
            Severity::fromError($error->getCode()),
            $hint ?? null
        );
        $event = new Event('CakeSentry.Client.afterCapture', $this, compact('error', 'request', 'lastEventId'));
        $eventManager->dispatch($event);
    }

    /**
     * Accessor for current hub
     *
     * @return \Sentry\State\HubInterface
     */
    public function getHub(): HubInterface
    {
        return $this->hub;
    }

    /**
     * @param array<array<string, null|int|string|array>> $traces
     * @return array<array<string, null|int|string|array>>
     */
    protected function cleanedTrace(array $traces): array
    {
        foreach ($traces as $key => $trace) {
            if (isset($trace['line']) && ($trace['line'] === '??' || $trace['line'] === '')) {
                $traces[$key]['line'] = 0;
            }
        }

        return $traces;
    }
}
