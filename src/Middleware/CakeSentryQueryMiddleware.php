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

use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use CakeSentry\Database\Log\CakeSentryLog;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware that enables query logging which can be used in error & exception events
 * as well as performance monitoring
 */
class CakeSentryQueryMiddleware implements MiddlewareInterface
{
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
        $this->enableQueryLogging();

        return $handler->handle($request);
    }

    /**
     * @return void
     */
    protected function enableQueryLogging(): void
    {
        $configs = ConnectionManager::configured();
        $includeSchemaReflection = (bool)Configure::read('CakeSentry.includeSchemaReflection');

        foreach ($configs as $name) {
            $connection = ConnectionManager::get($name);
            if ($connection->configName() === 'debug_kit') {
                continue;
            }
            $logger = null;
            /** @var \Cake\Database\Driver|object $driver */
            $driver = $connection->getDriver();
            $driverConfig = $driver->config();
            if ($driverConfig['sentryLog'] ?? false) {
                $logger = $driver->getLogger();
            }

            $logger = new CakeSentryLog($logger, $name, $includeSchemaReflection);
            if (method_exists($driver, 'setLogger')) {
                $driver->setLogger($logger);
            }
        }
    }
}
