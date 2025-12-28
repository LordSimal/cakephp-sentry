<?php
declare(strict_types=1);

namespace CakeSentry\Event;

use Cake\Event\Event;
use Cake\Event\EventListenerInterface;
use Sentry\SentrySdk;
use Sentry\Tracing\SpanContext;
use Sentry\Tracing\SpanStatus;
use Sentry\Tracing\Transaction;
use function Sentry\continueTrace;
use function Sentry\startTransaction;

class QueueEventListener implements EventListenerInterface
{
    protected ?Transaction $consumerTransaction = null;

    /**
     * @inheritDoc
     */
    public function implementedEvents(): array
    {
        return [
            'CakeSentry.Queue.enqueue' => 'handleEnqueue',
            'CakeSentry.Queue.beforeExecute' => 'handleBeforeExecute',
            'CakeSentry.Queue.afterExecute' => 'handleAfterExecute',
        ];
    }

    /**
     * @param \Cake\Event\Event $event
     * @return void
     */
    public function handleEnqueue(Event $event): void
    {
        $parentSpan = SentrySdk::getCurrentHub()->getSpan();
        $jobData = $event->getData();
        $jobClass = $jobData['class'] ?? 'Unknown';

        if ($parentSpan === null) {
            return;
        }

        $context = SpanContext::make()->setOp('queue.publish');
        $span = $parentSpan->startChild($context);
        SentrySdk::getCurrentHub()->setSpan($span);

        $span
            ->setDescription(sprintf('queue.publish %s', $jobClass))
            ->setData([
                'messaging.message.id' => $jobData['id'] ?? null,
                'messaging.destination.name' => $jobData['queue'] ?? 'default',
                'messaging.message.body.size' => strlen(json_encode($jobData['data'] ?? []) ?: ''),
            ])
            ->finish();

        SentrySdk::getCurrentHub()->setSpan($parentSpan);
    }

    /**
     * @param \Cake\Event\Event $event
     * @return void
     */
    public function handleBeforeExecute(Event $event): void
    {
        $jobData = $event->getData();
        $jobClass = $jobData['class'] ?? 'Unknown';

        $context = continueTrace(
            $jobData['sentry_trace'] ?? '',
            $jobData['sentry_baggage'] ?? '',
        )
            ->setOp('queue.process')
            ->setName($jobClass);

        $this->consumerTransaction = startTransaction($context);
        SentrySdk::getCurrentHub()->setSpan($this->consumerTransaction);
    }

    /**
     * @param \Cake\Event\Event $event
     * @return void
     */
    public function handleAfterExecute(Event $event): void
    {
        $jobData = $event->getData();
        $result = $event->getResult();

        if ($this->consumerTransaction === null) {
            return;
        }

        $success = $result !== false && !isset($jobData['exception']);

        $this->consumerTransaction
            ->setData([
                'messaging.message.id' => $jobData['id'] ?? null,
                'messaging.destination.name' => $jobData['queue'] ?? 'default',
                'messaging.message.body.size' => strlen(json_encode($jobData['data'] ?? []) ?: ''),
                'messaging.message.receive.latency' => $jobData['execution_time'] ?? 0,
                'messaging.message.retry.count' => $jobData['retry_count'] ?? 0,
            ])
            ->setStatus($success ? SpanStatus::ok() : SpanStatus::internalError())
            ->finish();
    }
}
