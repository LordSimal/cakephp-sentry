<?php
declare(strict_types=1);

namespace CakeSentry\Event;

use Cake\Event\EventListenerInterface;
use Cake\Http\Client\ClientEvent;
use CakeSentry\SpanStackTrait;
use Sentry\SentrySdk;
use Sentry\Tracing\SpanContext;

class HttpEventListener implements EventListenerInterface
{
    use SpanStackTrait;

    /**
     * @inheritDoc
     */
    public function implementedEvents(): array
    {
        return [
            'HttpClient.beforeSend' => 'handleBeforeSend',
            'HttpClient.afterSend' => 'handleAfterSend',
        ];
    }

    /**
     * @param \Cake\Http\Client\ClientEvent $event
     * @return void
     */
    public function handlebeforeSend(ClientEvent $event): void
    {
        /** @var \Cake\Http\Client\Request $request */
        $request = $event->getRequest();
        $parentSpan = SentrySdk::getCurrentHub()->getSpan();

        if ($parentSpan !== null) {
            $span = SpanContext::make()
                ->setOp('http.client');

            $uri = $request->getUri();
            $fullUrl = sprintf('%s://%s%s/', $uri->getScheme(), $uri->getHost(), $uri->getPath());
            $span
                ->setDescription(sprintf('%s %s', $request->getMethod(), $fullUrl))
                ->setData([
                    'url' => $fullUrl,
                    'http.query' => $uri->getQuery(),
                    'http.fragment' => $uri->getFragment(),
                    'http.request.method' => $request->getMethod(),
                    'http.request.body.size' => $request->getBody()->getSize(),
                ]);

            $this->pushSpan($parentSpan->startChild($span));
        }
    }

    /**
     * @param \Cake\Http\Client\ClientEvent $event
     * @return void
     */
    public function handleAfterSend(ClientEvent $event): void
    {
        $response = $event->getResult();
        $span = $this->popSpan();

        if ($span !== null) {
            $span
                ->setHttpStatus($response?->getStatusCode() ?? 0)
                ->setData([
                    'http.response.body.size' => $response?->getBody()?->getSize(),
                    'http.response.status_code' => $response?->getStatusCode() ?? 0,
                ])
                ->finish();
        }
    }
}
