<?php
declare(strict_types=1);

namespace CakeSentry;

use Cake\Database\Log\LoggedQuery;
use Cake\Database\Schema\MysqlSchemaDialect;
use Cake\Database\Schema\PostgresSchemaDialect;
use Cake\Database\Schema\SqliteSchemaDialect;
use Cake\Database\Schema\SqlserverSchemaDialect;
use Cake\Datasource\ConnectionManager;
use Sentry\SentrySdk;
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
        $parentSpan = SentrySdk::getCurrentHub()->getSpan();
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

        $connection = ConnectionManager::get($connectionName);
        $dialect = $connection->getDriver()->schemaDialect();
        $type = match (true) {
            $dialect instanceof MysqlSchemaDialect => 'mysql',
            $dialect instanceof PostgresSchemaDialect => 'postgresql',
            $dialect instanceof SqliteSchemaDialect => 'sqlite',
            $dialect instanceof SqlserverSchemaDialect => 'mssql',
        };

        $spanContext = new SpanContext();
        $spanContext->setOp('db.sql.query');
        $spanContext->setData([
            'db.system' => $type,
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
