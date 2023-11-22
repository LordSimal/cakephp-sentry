<?php
declare(strict_types=1);

/**
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace CakeSentry\Middleware;

use Cake\Datasource\ConnectionManager;
use CakeSentry\Database\Log\CakeSentryLog;
use CakeSentry\QuerySpanTrait;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sentry\SentrySdk;
use Sentry\Tracing\SpanContext;
use Sentry\Tracing\TransactionContext;
use Sentry\Tracing\TransactionSource;
use function Sentry\startTransaction;

/**
 * Middleware that sets the first span in sentry for performance monitoring
 */
class CakeSentryPerformanceMiddleware implements MiddlewareInterface
{
    use QuerySpanTrait;

    /**
     * Invoke the middleware.
     *
     * DebugKit will augment the response and add the toolbar if possible.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request.
     * @param \Psr\Http\Server\RequestHandlerInterface $handler The request handler.
     * @return \Psr\Http\Message\ResponseInterface A response.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // We don't want to trace OPTIONS and HEAD requests as they are not relevant for performance monitoring.
        if (in_array($request->getMethod(), ['OPTIONS', 'HEAD'], true)) {
            return $handler->handle($request);
        }

        $sentryTraceHeader = $request->getHeaderLine('sentry-trace');
        $baggageHeader = $request->getHeaderLine('baggage');

        $transactionContext = TransactionContext::fromHeaders($sentryTraceHeader, $baggageHeader);

        $requestStartTime = $request->getServerParams()['REQUEST_TIME_FLOAT'] ?? microtime(true);

        $transactionContext->setOp('http.server');
        $transactionContext->setName($request->getMethod() . ' ' . $request->getUri()->getPath());
        $transactionContext->setSource(TransactionSource::route());
        $transactionContext->setStartTimestamp($requestStartTime);

        $transaction = startTransaction($transactionContext);

        SentrySdk::getCurrentHub()->setSpan($transaction);

        $spanContext = new SpanContext();
        $spanContext->setOp('middleware.handle');
        $span = $transaction->startChild($spanContext);

        SentrySdk::getCurrentHub()->setSpan($span);

        $this->addQueryData();

        $response = $handler->handle($request);
        // We don't want to trace 404 responses as they are not relevant for performance monitoring.
        if ($response->getStatusCode() === 404) {
            $transaction->setSampled(false);
        }

        $span->setHttpStatus($response->getStatusCode());
        $span->finish();

        SentrySdk::getCurrentHub()->setSpan($transaction);

        $transaction->setHttpStatus($response->getStatusCode());
        $transaction->finish();

        return $response;
    }

    /**
     * @return void
     */
    protected function addQueryData(): void
    {
        $configs = ConnectionManager::configured();

        foreach ($configs as $name) {
            $connection = ConnectionManager::get($name);
            if ($connection->configName() === 'debug_kit') {
                continue;
            }
            $logger = null;
            $driver = $connection->getDriver();
            $driverConfig = $driver->config();
            if ($driverConfig['log']) {
                $logger = $driver->getLogger();
                if ($logger instanceof CakeSentryLog) {
                    $logger->setPerformanceMonitoring(true);
                }
            }
        }
    }
}
