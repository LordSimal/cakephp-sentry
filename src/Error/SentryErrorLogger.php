<?php
declare(strict_types=1);

namespace CakeSentry\Error;

use Cake\Core\Configure;
use Cake\Error\ErrorLogger;
use Cake\Error\ErrorLoggerInterface;
use Cake\Error\PhpError;
use Cake\Utility\Hash;
use CakeSentry\Http\SentryClient;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerTrait;
use Stringable;
use Throwable;

class SentryErrorLogger implements ErrorLoggerInterface
{
    use LoggerTrait;

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
        bool $includeTrace = false
    ): void {
        $this->logger->logException($exception, $request, $includeTrace);
        if (Hash::check($this->config, 'dsn')) {
            $this->client->captureException($exception, $request);
        }
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
        if (Hash::check($this->config, 'dsn')) {
            $this->client->captureError($error, $request);
        }
    }

    /**
     * @inheritDoc
     */
    public function log($level, Stringable|string $message, array $context = []): void
    {
        $this->logger->log($level, $message, $context);
    }
}
