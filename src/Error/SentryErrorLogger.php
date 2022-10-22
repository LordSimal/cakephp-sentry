<?php
declare(strict_types=1);

namespace CakeSentry\Error;

use Cake\Error\ErrorLogger;
use Cake\Error\ErrorLoggerInterface;
use Cake\Error\PhpError;
use CakeSentry\Http\SentryClient;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Throwable;

class SentryErrorLogger implements ErrorLoggerInterface
{
    private ErrorLogger $logger;

    protected SentryClient $client;

    /**
     * @param array $config The config for the error logger and sentry client
     */
    public function __construct(array $config)
    {
        $this->logger = new ErrorLogger($config);
        $this->client = new SentryClient($config);
    }

    /**
     * @inheritDoc
     */
    public function log(Throwable $exception, ?ServerRequestInterface $request = null): bool
    {
        throw new RuntimeException('This method of error logging should not be used anymore');
    }

    /**
     * @inheritDoc
     */
    public function logMessage($level, string $message, array $context = []): bool
    {
        throw new RuntimeException('This method of error logging should not be used anymore');
    }

    /**
     * @inheritDoc
     */
    public function logException(
        Throwable $exception,
        ?ServerRequestInterface $request = null,
        bool $includeTrace = false
    ): void {
        $this->logger->logException($exception, $request, $includeTrace);
        $this->client->captureException($exception, $request);
    }

    /**
     * @inheritDoc
     */
    public function logError(
        PhpError $error,
        ?ServerRequestInterface $request = null,
        bool $includeTrace = false
    ): void {
        $this->logger->logError($error, $request, $includeTrace);
        $this->client->captureError($error, $request);
    }
}
