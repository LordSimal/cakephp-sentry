<?php
declare(strict_types=1);

namespace CakeSentry\Http;

use Cake\Core\Configure;
use Cake\Core\InstanceConfigTrait;
use Cake\Datasource\ConnectionInterface;
use Cake\Datasource\ConnectionManager;
use Cake\Error\PhpError;
use Cake\Event\Event;
use Cake\Event\EventDispatcherTrait;
use Cake\Utility\Hash;
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
use function Sentry\init;

class SentryClient
{
    use EventDispatcherTrait;
    use InstanceConfigTrait;

    protected array $_defaultConfig = [
        'sentry' => [
            'prefixes' => [
                APP,
            ],
            'in_app_exclude' => [
                ROOT . DS . 'vendor' . DS,
            ],
        ],
    ];

    protected ?HubInterface $hub = null;

    /**
     * Loggers connected
     *
     * @var array
     */
    protected array $_loggers = [];

    /**
     * Client constructor.
     *
     * @param array $config config for uses Sentry
     */
    public function __construct(array $config)
    {
        $userConfig = Configure::read('Sentry');
        if ($userConfig) {
            $this->_defaultConfig['sentry'] = array_merge($this->_defaultConfig['sentry'], $userConfig);
        }
        $this->setConfig($config);
        $this->setupClient();
    }

    /**
     * Init sentry client
     *
     * @return void
     */
    protected function setupClient(): void
    {
        $config = $this->getConfig('sentry');
        if (Hash::check($config, 'dsn')) {
            init($config);
            $this->hub = SentrySdk::getCurrentHub();
            $event = new Event('CakeSentry.Client.afterSetup', $this);
            $this->getEventManager()->dispatch($event);
        }
    }

    /**
     * @return void
     */
    protected function getQueryLoggers(): void
    {
        $configs = ConnectionManager::configured();
        $includeSchemaReflection = (bool)Configure::read('CakeSentry.includeSchemaReflection');

        foreach ($configs as $name) {
            $connection = ConnectionManager::get($name);
            if (
                $connection->configName() === 'debug_kit'
                || !$connection instanceof ConnectionInterface
            ) {
                continue;
            }
            $logger = $connection->getLogger();

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
                $queries = $logger->queries();
                if (empty($queries)) {
                    continue;
                }

                foreach ($queries as $query) {
                    $data = ['connectionName' => $logger->name()];
                    $data['executionTimeMs'] = $query['took'];
                    $data['rows'] = $query['rows'];

                    if ($this->hub) {
                        $this->hub->addBreadcrumb(new Breadcrumb(
                            Breadcrumb::LEVEL_INFO,
                            Breadcrumb::TYPE_DEFAULT,
                            'sql.query',
                            $query['query'],
                            $data
                        ));
                    }
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

        if ($extras && $this->hub) {
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

        if ($extras && $this->hub) {
            $this->hub->configureScope(function (Scope $scope) use ($extras): void {
                $scope->setExtras($extras);
            });
        }

        $this->getQueryLoggers();
        $this->addQueryBreadcrumbs();

        if ($this->hub) {
            $client = $this->hub->getClient();
            if ($client) {
                $trace = $this->cleanedTrace($error->getTrace());
                /** @psalm-suppress ArgumentTypeCoercion */
                $stacktrace = $client->getStacktraceBuilder()
                ->buildFromBacktrace($trace, $error->getFile() ?? 'unknown file', $error->getLine() ?? 0);
                $hint = EventHint::fromArray([
                'stacktrace' => $stacktrace,
                ]);
            }
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
     * @return \Sentry\State\HubInterface|null
     */
    public function getHub(): ?HubInterface
    {
        return $this->hub;
    }

    /**
     * @param array<array<string, int|string>> $traces
     * @return array<array<string, int>>
     */
    private function cleanedTrace(array $traces): array
    {
        foreach ($traces as $key => $trace) {
            if (isset($trace['line']) && is_string($trace['line'])) {
                $traces[$key]['line'] = 0;
            }
        }

        return $traces;
    }
}
