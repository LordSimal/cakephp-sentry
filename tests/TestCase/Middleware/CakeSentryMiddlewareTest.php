<?php
declare(strict_types=1);

namespace CakeSentry\Test\Middleware;

use Cake\Datasource\ConnectionManager;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\TestSuite\TestCase;
use CakeSentry\Database\Log\CakeSentryLog;
use CakeSentry\Middleware\CakeSentryQueryMiddleware;
use Mockery;
use Psr\Http\Server\RequestHandlerInterface;

class CakeSentryMiddlewareTest extends TestCase
{
    public function testQueryLoggingEnabled(): void
    {
        $request = new ServerRequest([
            'url' => '/articles',
            'environment' => ['REQUEST_METHOD' => 'GET'],
        ]);
        $response = new Response([
            'statusCode' => 200,
            'type' => 'text/html',
            'body' => '<html><title>test</title><body><p>some text</p></body>',
        ]);

        $handler = Mockery::mock(RequestHandlerInterface::class);
        $handler->shouldReceive('handle')
            ->once()
            ->andReturn($response);

        $middleware = new CakeSentryQueryMiddleware();
        $response = $middleware->process($request, $handler);
        $this->assertInstanceOf(Response::class, $response, 'Should return the response');

        $configs = ConnectionManager::configured();
        foreach ($configs as $name) {
            $connection = ConnectionManager::get($name);
            $driver = $connection->getDriver();
            $this->assertSame(CakeSentryLog::class, get_class($driver->getLogger()));
        }
    }
}
