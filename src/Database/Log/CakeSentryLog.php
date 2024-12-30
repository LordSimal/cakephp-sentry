<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * This file is heavily copied from cakephp/debug_kit
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace CakeSentry\Database\Log;

use Cake\Database\Log\LoggedQuery;
use CakeSentry\QuerySpanTrait;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Sentry\State\HubInterface;
use Stringable;

/**
 * CakeSentry Query logger (originated from DebugKit Query logged)
 *
 * This logger decorates the existing logger if it exists,
 * and stores log messages internally so they can be displayed
 * or stored for future use.
 */
class CakeSentryLog extends AbstractLogger
{
    use QuerySpanTrait;

    /**
     * Logs from the current request.
     */
    protected array $queries = [];

    /**
     * Decorated logger.
     */
    protected ?LoggerInterface $logger = null;

    /**
     * Name of the connection being logged.
     */
    protected string $connectionName;

    /**
     * Total time (ms) of all queries
     */
    protected float $totalTime = 0;

    /**
     * Total rows of all queries
     */
    protected float $totalRows = 0;

    /**
     * The connection role the driver behind this query has
     */
    protected string $role = 'write';

    /**
     * Set to true to capture schema reflection queries
     * in the SQL log panel.
     *
     * @var bool
     */
    protected bool $includeSchema = false;

    /**
     * @var \Sentry\State\HubInterface|null
     */
    protected ?HubInterface $hub = null;

    /**
     * @var bool
     */
    protected bool $enablePerformanceMonitoring = false;

    /**
     * Constructor
     *
     * @param \Psr\Log\LoggerInterface|null $logger The logger to decorate and spy on.
     * @param string $name The name of the connection being logged.
     * @param bool $includeSchema Whether or not schema reflection should be included.
     */
    public function __construct(?LoggerInterface $logger, string $name, bool $includeSchema = false)
    {
        $this->logger = $logger;
        $this->connectionName = $name;
        $this->includeSchema = $includeSchema;
    }

    /**
     * Set the schema include flag.
     *
     * @param bool $value Set
     * @return $this
     */
    public function setIncludeSchema(bool $value)
    {
        $this->includeSchema = $value;

        return $this;
    }

    /**
     * Get the connection name.
     *
     * @return string
     */
    public function name(): string
    {
        return $this->connectionName;
    }

    /**
     * Get the stored logs.
     *
     * @return array<\Cake\Database\Log\LoggedQuery>
     */
    public function queries(): array
    {
        return $this->queries;
    }

    /**
     * Get the total time
     *
     * @return float
     */
    public function totalTime(): float
    {
        return $this->totalTime;
    }

    /**
     * Get the total rows
     *
     * @return float
     */
    public function totalRows(): float
    {
        return $this->totalRows;
    }

    /**
     * The connection role the driver behind this query has
     *
     * @return string
     */
    public function role(): string
    {
        return $this->role;
    }

    /**
     * @inheritDoc
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        // Return early when elastic search provides no query
        if (empty($context['query'])){
            return;
        }
        /** @var \Cake\Database\Log\LoggedQuery $query */
        $query = $context['query'];

        $this->logger?->log($level, $message, $context);

        if ($this->includeSchema === false && $this->isSchemaQuery($query)) {
            return;
        }

        if ($this->enablePerformanceMonitoring) {
            $this->addTransactionSpan($query, $this->connectionName);
        }

        $context = $query->getContext();

        $this->totalTime += $context['took'];
        $this->totalRows += $context['numRows'];
        $this->role = $context['role'];

        $this->queries[] = $query;
    }

    /**
     * Sniff SQL statements for things only found in schema reflection.
     *
     * @param \Cake\Database\Log\LoggedQuery $query The query to check.
     * @return bool
     */
    protected function isSchemaQuery(LoggedQuery $query): bool
    {
        $context = $query->getContext();
        $querystring = $context['query'] ?? '';

        if ($querystring === '') {
            $querystring = $query->jsonSerialize()['query'] ?? '';
        }

        return // Multiple engines
            str_contains($querystring, 'FROM information_schema') ||
            // Postgres
            str_contains($querystring, 'FROM pg_catalog') ||
            // MySQL
            str_starts_with($querystring, 'SHOW TABLE') ||
            str_starts_with($querystring, 'SHOW FULL COLUMNS') ||
            str_starts_with($querystring, 'SHOW INDEXES') ||
            // Sqlite
            str_contains($querystring, 'FROM sqlite_master') ||
            str_starts_with($querystring, 'PRAGMA') ||
            // Sqlserver
            str_contains($querystring, 'FROM INFORMATION_SCHEMA') ||
            str_contains($querystring, 'FROM sys.');
    }

    /**
     * @return bool
     */
    public function isPerformanceMonitoringEnabled(): bool
    {
        return $this->enablePerformanceMonitoring;
    }

    /**
     * @param bool $enablePerformanceMonitoring
     * @return void
     */
    public function setPerformanceMonitoring(bool $enablePerformanceMonitoring): void
    {
        $this->enablePerformanceMonitoring = $enablePerformanceMonitoring;
    }
}
