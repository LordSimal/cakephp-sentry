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
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;

/**
 * CakeSentry Query logger (originated from DebugKit Query logged)
 *
 * This logger decorates the existing logger if it exists,
 * and stores log messages internally so they can be displayed
 * or stored for future use.
 */
class CakeSentryLog extends AbstractLogger
{
    /**
     * Logs from the current request.
     */
    protected array $_queries = [];

    /**
     * Decorated logger.
     */
    protected ?LoggerInterface $_logger = null;

    /**
     * Name of the connection being logged.
     */
    protected string $_connectionName;

    /**
     * Total time (ms) of all queries
     */
    protected float $_totalTime = 0;

    /**
     * Total rows of all queries
     */
    protected float $_totalRows = 0;

    /**
     * Set to true to capture schema reflection queries
     * in the SQL log panel.
     *
     * @var bool
     */
    protected bool $_includeSchema = false;

    /**
     * Constructor
     *
     * @param \Psr\Log\LoggerInterface|null $logger The logger to decorate and spy on.
     * @param string $name The name of the connection being logged.
     * @param bool $includeSchema Whether or not schema reflection should be included.
     */
    public function __construct(?LoggerInterface $logger, string $name, bool $includeSchema = false)
    {
        $this->_logger = $logger;
        $this->_connectionName = $name;
        $this->_includeSchema = $includeSchema;
    }

    /**
     * Set the schema include flag.
     *
     * @param bool $value Set
     * @return $this
     */
    public function setIncludeSchema(bool $value)
    {
        $this->_includeSchema = $value;

        return $this;
    }

    /**
     * Get the connection name.
     *
     * @return string
     */
    public function name(): string
    {
        return $this->_connectionName;
    }

    /**
     * Get the stored logs.
     *
     * @return array
     */
    public function queries(): array
    {
        return $this->_queries;
    }

    /**
     * Get the total time
     *
     * @return float
     */
    public function totalTime(): float
    {
        return $this->_totalTime;
    }

    /**
     * Get the total rows
     *
     * @return float
     */
    public function totalRows(): float
    {
        return $this->_totalRows;
    }

    /**
     * @inheritDoc
     */
    public function log($level, $message, array $context = []): void
    {
        $query = $context['query'];

        $this->_logger?->log($level, $message, $context);

        if ($this->_includeSchema === false && $this->isSchemaQuery($query)) {
            return;
        }

        $context = $query->getContext();

        $this->_totalTime += $context['took'];
        $this->_totalRows += $context['numRows'];

        $this->_queries[] = [
            'query' => (string)$query,
            'took' => $context['took'],
            'rows' => $context['numRows'],
        ];
    }

    /**
     * Sniff SQL statements for things only found in schema reflection.
     *
     * @param \Cake\Database\Log\LoggedQuery $query The query to check.
     * @return bool
     */
    protected function isSchemaQuery(LoggedQuery $query): bool
    {
        /** @psalm-suppress InternalMethod */
        $context = $query->jsonSerialize();
        $querystring = $context['query'];

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
}
