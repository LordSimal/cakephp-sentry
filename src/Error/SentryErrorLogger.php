<?php
declare(strict_types=1);

namespace CakeSentry\Error;

use Cake\Core\Configure;
use Cake\Error\ErrorLogger;
use Cake\Error\ErrorLoggerInterface;
use Cake\Error\PhpError;
use Cake\Utility\Hash;
use CakeSentry\Http\SentryClient;
use ErrorException;
use Exception;
use Psr\Http\Message\ServerRequestInterface;
use Sentry\Logs\Logs;
use Sentry\Severity;
use Throwable;

class SentryErrorLogger implements ErrorLoggerInterface
{
    private ErrorLogger $logger;

    protected SentryClient $client;

    protected array $config;

    /**
     * @param array $config The config for the error logger and sentry client
     */
    public function __construct(array $config)
    {
        $this->logger = new ErrorLogger($config);
        $this->client = new SentryClient();
        $this->config = Configure::read('Sentry');
    }

    /**
     * @inheritDoc
     */
    public function logException(
        Throwable $exception,
        ?ServerRequestInterface $request = null,
        bool $includeTrace = false,
    ): void {
        $this->logger->logException($exception, $request, $includeTrace);
        if (Hash::check($this->config, 'dsn')) {
            $this->client->captureException($exception, $request);
        }
        if (Hash::get($this->config, 'enable_logs')) {
            $this->logToSentry($exception, $request);
        }
    }

    /**
     * @inheritDoc
     */
    public function logError(
        PhpError $error,
        ?ServerRequestInterface $request = null,
        bool $includeTrace = false,
    ): void {
        $this->logger->logError($error, $request, $includeTrace);
        if (Hash::check($this->config, 'dsn')) {
            $this->client->captureError($error, $request);
        }
        if (Hash::get($this->config, 'enable_logs')) {
            $this->logToSentry($error, $request);
        }
    }

    /**
     * @param \Throwable|\Cake\Error\PhpError $error
     * @param \Psr\Http\Message\ServerRequestInterface|null $request
     * @return void
     */
    protected function logToSentry(Throwable|PhpError $error, ?ServerRequestInterface $request = null): void
    {
        $sentryLogger = Logs::getInstance();
        $msg = $error->getMessage();

        $attributes = ['exception' => $error];
        if ($request !== null) {
            $attributes['request'] = $request;
        }

        if ($error instanceof ErrorException) {
            $severity = Severity::fromError($error->getSeverity());
            if ($severity->isEqualTo(Severity::fatal())) {
                $sentryLogger->fatal($msg, [], $attributes);
            } else {
                $sentryLogger->error($msg, [], $attributes);
            }
        } elseif ($error instanceof Exception) {
            $sentryLogger->error($msg, [], $attributes);
        }

        if ($error instanceof PhpError) {
            $attributes['error'] = $error;
            unset($attributes['exception']);

            match ($error->getCode()) {
                E_PARSE, E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR =>
                    $sentryLogger->error($msg, [], $attributes),
                E_WARNING, E_USER_WARNING, E_COMPILE_WARNING, E_RECOVERABLE_ERROR, E_CORE_WARNING =>
                    $sentryLogger->warn($msg, [], $attributes),
                E_NOTICE, E_USER_NOTICE, E_STRICT =>
                    $sentryLogger->info($msg, [], $attributes),
                default => $sentryLogger->debug($msg, [], $attributes),
            };
        }
    }
}
