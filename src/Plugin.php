<?php
declare(strict_types=1);

namespace CakeSentry;

use Cake\Core\BasePlugin;
use Cake\Core\Configure;
use Cake\Error\Middleware\ErrorHandlerMiddleware;
use Cake\Http\MiddlewareQueue;
use CakeSentry\Middleware\CakeSentryMiddleware;

class Plugin extends BasePlugin
{
    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        if (Configure::read('CakeSentry.enableQueryLogging', false)) {
            $middlewareQueue = $middlewareQueue->insertAfter(ErrorHandlerMiddleware::class, new CakeSentryMiddleware());
        }
        return $middlewareQueue;
    }
}
