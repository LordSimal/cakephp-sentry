<?php
declare(strict_types=1);

namespace CakeSentry;

use Cake\Core\BasePlugin;
use Cake\Core\Configure;
use Cake\Http\MiddlewareQueue;
use CakeSentry\Middleware\CakeSentryMiddleware;

class Plugin extends BasePlugin
{
    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        if (Configure::read('CakeSentry.enableQueryLogging', false)) {
            $middlewareQueue = $middlewareQueue->add(new CakeSentryMiddleware());
        }
        return $middlewareQueue;
    }
}
