<?php
declare(strict_types=1);

namespace CakeSentry;

use Sentry\SentrySdk;
use Sentry\Tracing\Span;

trait SpanStackTrait
{
    /**
     * @var array
     */
    protected array $parentSpanStack = [];

    /**
     * @var array
     */
    protected array $currentSpanStack = [];

    /**
     * @param \Sentry\Tracing\Span $span The span.
     * @return void
     */
    protected function pushSpan(Span $span): void
    {
        $this->parentSpanStack[] = SentrySdk::getCurrentHub()->getSpan();
        SentrySdk::getCurrentHub()->setSpan($span);
        $this->currentSpanStack[] = $span;
    }

    /**
     * @return \Sentry\Tracing\Span|null
     */
    protected function popSpan(): ?Span
    {
        if (count($this->currentSpanStack) === 0) {
            return null;
        }

        $parent = array_pop($this->parentSpanStack);
        SentrySdk::getCurrentHub()->setSpan($parent);

        return array_pop($this->currentSpanStack);
    }
}
