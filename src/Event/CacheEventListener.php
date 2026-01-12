<?php
declare(strict_types=1);

namespace CakeSentry\Event;

use Cake\Cache\Event\CacheAfterAddEvent;
use Cake\Cache\Event\CacheAfterDeleteEvent;
use Cake\Cache\Event\CacheAfterGetEvent;
use Cake\Cache\Event\CacheAfterIncrementEvent;
use Cake\Cache\Event\CacheAfterSetEvent;
use Cake\Cache\Event\CacheClearedEvent;
use Cake\Event\EventInterface;
use Cake\Event\EventListenerInterface;
use Cake\I18n\DateTime;
use DateInterval;
use Sentry\SentrySdk;
use Sentry\Tracing\SpanContext;

class CacheEventListener implements EventListenerInterface
{
    /**
     * @inheritDoc
     */
    public function implementedEvents(): array
    {
        return [
            CacheAfterGetEvent::NAME => 'onCacheAfterGet',
            CacheAfterSetEvent::NAME => 'onCacheAfterSet',
            CacheAfterAddEvent::NAME => 'onCacheAfterAdd',
            CacheAfterIncrementEvent::NAME => 'onCacheAfterIncrement',
            CacheAfterDeleteEvent::NAME => 'onCacheAfterDelete',
            CacheClearedEvent::NAME => 'onCacheCleared',
        ];
    }

    /**
     * @param \Cake\Event\EventInterface $event
     * @return void
     */
    public function onCacheAfterGet(EventInterface $event): void
    {
        if (!$event instanceof CacheAfterGetEvent) {
            return;
        }
        $value = $this->serializedEventValue($event->getValue());
        $this->addGetSpan($event->getKey(), $value);
    }

    /**
     * @param \Cake\Event\EventInterface $event
     * @return void
     */
    public function onCacheAfterSet(EventInterface $event): void
    {
        if (!$event instanceof CacheAfterSetEvent) {
            return;
        }
        $ttl = $event->getTtl();
        if ($ttl instanceof DateInterval) {
            $now = new DateTime();
            $expiry = $now->add($ttl);
            $ttl = $expiry->getTimestamp() - $now->getTimestamp();
        }
        $value = $this->serializedEventValue($event->getValue());
        $this->addPutSpan($event->getKey(), $value, $ttl);
    }

    /**
     * @param \Cake\Event\EventInterface $event
     * @return void
     */
    public function onCacheAfterAdd(EventInterface $event): void
    {
        if (!$event instanceof CacheAfterAddEvent) {
            return;
        }
        $ttl = $event->getTtl();
        if ($ttl instanceof DateInterval) {
            $now = new DateTime();
            $expiry = $now->add($ttl);
            $ttl = $expiry->getTimestamp() - $now->getTimestamp();
        }
        $value = $this->serializedEventValue($event->getValue());
        $this->addPutSpan($event->getKey(), $value, $ttl);
    }

    /**
     * @param \Cake\Event\EventInterface $event
     * @return void
     */
    public function onCacheAfterIncrement(EventInterface $event): void
    {
        if (!$event instanceof CacheAfterIncrementEvent) {
            return;
        }
        $value = $this->serializedEventValue($event->getValue());
        $this->addPutSpan($event->getKey(), $value);
    }

    /**
     * @param \Cake\Event\EventInterface $event
     * @return void
     */
    public function onCacheAfterDelete(EventInterface $event): void
    {
        if (!$event instanceof CacheAfterDeleteEvent) {
            return;
        }
        $this->addRemoveSpan($event->getKey());
    }

    /**
     * @param \Cake\Event\EventInterface $event
     * @return void
     */
    public function onCacheCleared(EventInterface $event): void
    {
        if (!$event instanceof CacheClearedEvent) {
            return;
        }
        $this->addClearSpan();
    }

    /**
     * @param mixed $value
     * @return string
     */
    private function serializedEventValue(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            return serialize($value);
        }

        return (string)$value;
    }

    /**
     * @param string $key
     * @param string|null $value
     * @return void
     */
    private function addGetSpan(string $key, ?string $value): void
    {
        $parentSpan = SentrySdk::getCurrentHub()->getSpan();
        if ($parentSpan === null) {
            return;
        }
        $context = SpanContext::make()
            ->setDescription($key)
            ->setOp('cache.get');
        $span = $parentSpan->startChild($context);
        SentrySdk::getCurrentHub()->setSpan($span);

        $span->setData([
            'cache.key' => $key,
        ]);
        if ($value !== null) {
            $span->setData([
                'cache.hit' => true,
                'cache.item_size' => strlen($value),
            ]);
        } else {
            $span->setData([
                'cache.hit' => false,
            ]);
        }
        $span->finish();
        SentrySdk::getCurrentHub()->setSpan($parentSpan);
    }

    /**
     * @param string $key
     * @param string $value
     * @param int|null $ttl
     * @return void
     */
    private function addPutSpan(string $key, string $value, ?int $ttl = null): void
    {
        $parentSpan = SentrySdk::getCurrentHub()->getSpan();
        if ($parentSpan === null) {
            return;
        }
        $context = SpanContext::make()
            ->setDescription($key)
            ->setOp('cache.put');
        $span = $parentSpan->startChild($context);
        SentrySdk::getCurrentHub()->setSpan($span);

        $span->setData([
            'cache.key' => $key,
            'cache.item_size' => strlen($value),
        ]);
        if ($ttl !== null) {
            $span->setData([
                'cache.ttl' => $ttl,
            ]);
        }
        $span->finish();
        SentrySdk::getCurrentHub()->setSpan($parentSpan);
    }

    /**
     * @param string $key
     * @return void
     */
    private function addRemoveSpan(string $key): void
    {
        $parentSpan = SentrySdk::getCurrentHub()->getSpan();
        if ($parentSpan === null) {
            return;
        }
        $context = SpanContext::make()
            ->setDescription($key)
            ->setOp('cache.remove');
        $span = $parentSpan->startChild($context);
        SentrySdk::getCurrentHub()->setSpan($span);

        $span
            ->setData([
                'cache.key' => $key,
            ])
            ->finish();
        SentrySdk::getCurrentHub()->setSpan($parentSpan);
    }

    /**
     * @return void
     */
    private function addClearSpan(): void
    {
        $parentSpan = SentrySdk::getCurrentHub()->getSpan();
        if ($parentSpan === null) {
            return;
        }
        $context = SpanContext::make()
            ->setDescription('Cache flushed')
            ->setOp('cache.flush');
        $span = $parentSpan->startChild($context);
        SentrySdk::getCurrentHub()->setSpan($span);

        $span->finish();
        SentrySdk::getCurrentHub()->setSpan($parentSpan);
    }
}
