<?php
declare(strict_types=1);

namespace CakeSentry;

use Cake\Core\BasePlugin;
use Cake\Core\Configure;
use Cake\Http\MiddlewareQueue;
use CakeSentry\Middleware\CakeSentryPerformanceMiddleware;
use CakeSentry\Middleware\CakeSentryQueryMiddleware;

class CakeSentryPlugin extends BasePlugin
{
    /**
     * @param \Cake\Http\MiddlewareQueue $middlewareQueue The current middleware queue object
     * @return \Cake\Http\MiddlewareQueue
     */
    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        $enableQueryLogging = Configure::read('CakeSentry.enableQueryLogging', false);
        if ($enableQueryLogging) {
            $middlewareQueue = $middlewareQueue->add(new CakeSentryQueryMiddleware());
        }

        $enablePerformanceLogging = Configure::read('CakeSentry.enablePerformanceMonitoring', false);
        if ($enableQueryLogging && $enablePerformanceLogging) {
            $middlewareQueue = $middlewareQueue->add(new CakeSentryPerformanceMiddleware());
        }

        return $middlewareQueue;
    }
}
