<?php
declare(strict_types=1);

namespace CakeSentry;

use Cake\Core\BasePlugin;
use Cake\Core\Configure;
use Cake\Http\MiddlewareQueue;
use CakeSentry\Middleware\CakeSentryMiddleware;

class CakeSentryPlugin extends BasePlugin
{
    /**
     * @param \Cake\Http\MiddlewareQueue $middlewareQueue The current middleware queue object
     * @return \Cake\Http\MiddlewareQueue
     */
    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        if (Configure::read('CakeSentry.enableQueryLogging', false)) {
            $middlewareQueue = $middlewareQueue->add(new CakeSentryMiddleware());
        }

        return $middlewareQueue;
    }
}
