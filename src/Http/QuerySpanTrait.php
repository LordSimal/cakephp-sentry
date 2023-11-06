<?php
declare(strict_types=1);

namespace CakeSentry\Http;

use Cake\Database\Log\LoggedQuery;
use Sentry\Tracing\Span;
use Sentry\Tracing\SpanContext;
use Sentry\Tracing\SpanStatus;

trait QuerySpanTrait
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
     * @param \Cake\Database\Log\LoggedQuery $query
     * @param string|null $connectionName
     * @return void
     */
    public function addTransactionSpan(LoggedQuery $query, ?string $connectionName = null): void
    {
        $parentSpan = $this->hub->getSpan();
        if ($parentSpan === null) {
            return;
        }

        $context = $query->getContext();

        if ($context['query'] === 'BEGIN') {
            $spanContext = new SpanContext();
            $spanContext->setOp('db.transaction');
            $this->pushSpan($parentSpan->startChild($spanContext));

            return;
        }

        if ($context['query'] === 'COMMIT') {
            $span = $this->popSpan();

            if ($span !== null) {
                $span->finish();
                $span->setStatus(SpanStatus::ok());
            }

            return;
        }

        $spanContext = new SpanContext();
        $spanContext->setOp('db.sql.query');
        $spanContext->setData([
            'db.connectionName' => $connectionName,
            'db.role' => $context['role'],
            'db.numRows' => $context['numRows'],
        ]);
        $spanContext->setDescription($context['query']);
        $spanContext->setStartTimestamp(microtime(true) - $context['took'] / 1000);
        $spanContext->setEndTimestamp($spanContext->getStartTimestamp() + $context['took'] / 1000);
        $parentSpan->startChild($spanContext);
    }

    /**
     * @param \Sentry\Tracing\Span $span The span.
     * @return void
     */
    protected function pushSpan(Span $span): void
    {
        $this->parentSpanStack[] = $this->hub->getSpan();
        $this->hub->setSpan($span);
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
        $this->hub->setSpan($parent);

        return array_pop($this->currentSpanStack);
    }
}
