<?php
declare(strict_types=1);

namespace CakeSentry;

use Sentry\SentrySdk;
use Sentry\Tracing\SpanContext;

trait EventSpanTrait
{
    /**
     * @param string $name The name which should be displayed in the span
     * @param string $sentryOp The sentry valid op string. See https://develop.sentry.dev/sdk/performance/span-operations/#list-of-operations
     * @param float|null $startTime Timestamp of the starting time for the span
     * @param float|null $endTime Timestamp of the ending time for the span
     * @return void
     */
    public function addEventSpan(string $name, string $sentryOp, ?float $startTime = null, ?float $endTime = null): void
    {
        $parentSpan = SentrySdk::getCurrentHub()->getSpan();
        if ($parentSpan === null) {
            return;
        }

        if ($startTime == 0) {
            $startTime = 0.00000000000001;
        }

        $spanContext = new SpanContext();
        $spanContext->setDescription($name);
        $spanContext->setOp($sentryOp);
        //$spanContext->setData();
        if ($startTime !== null && $endTime !== null) {
            $spanContext->setStartTimestamp(DebugTimer::requestStartTime() + $startTime);
            $spanContext->setEndTimestamp(DebugTimer::requestStartTime() + $endTime);
        }
        $parentSpan->startChild($spanContext);
    }
}
