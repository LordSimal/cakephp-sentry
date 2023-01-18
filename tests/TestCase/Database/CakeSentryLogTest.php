<?php
declare(strict_types=1);

namespace CakeSentry\Test\Database;

use Cake\Database\Log\LoggedQuery;
use Cake\TestSuite\TestCase;
use CakeSentry\Database\Log\CakeSentryLog;
use Psr\Log\LogLevel;

final class CakeSentryLogTest extends TestCase
{
    protected CakeSentryLog $logger;

    /**
     * setup
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->logger = new CakeSentryLog(null, 'test');
    }

    /**
     * Test logs being stored.
     *
     * @return void
     */
    public function testLog()
    {
        $query = new LoggedQuery();
        $query->setContext([
          'query' => 'SELECT * FROM posts',
          'took' => 10,
          'numRows' => 5,
        ]);

        $this->assertCount(0, $this->logger->queries());

        $this->logger->log(LogLevel::DEBUG, (string)$query, ['query' => $query]);
        $this->assertCount(1, $this->logger->queries());
        $this->assertSame(10.0, $this->logger->totalTime());
        $this->assertSame(5.0, $this->logger->totalRows());

        $this->logger->log(LogLevel::DEBUG, (string)$query, ['query' => $query]);
        $this->assertCount(2, $this->logger->queries());
        $this->assertSame(20.0, $this->logger->totalTime());
        $this->assertSame(10.0, $this->logger->totalRows());
    }

    /**
     * Test log ignores schema reflection
     *
     * @dataProvider schemaQueryProvider
     * @return void
     */
    public function testLogIgnoreReflection($sql)
    {
        $query = new LoggedQuery();
        $query->setContext([
          'query' => $sql ,
          'took' => 10,
          'numRows' => 5,
        ]);

        $this->assertCount(0, $this->logger->queries());

        $this->logger->log(LogLevel::DEBUG, (string)$query, ['query' => $query]);
        $this->assertCount(0, $this->logger->queries());
    }

    /**
     * Test config setting turns off schema ignores
     *
     * @dataProvider schemaQueryProvider
     * @return void
     */
    public function testLogIgnoreReflectionDisabled($sql)
    {
        $query = new LoggedQuery();
        $query->setContext([
          'query' => $sql,
          'took' => 10,
          'numRows' => 5,
        ]);

        $logger = new CakeSentryLog(null, 'test', true);
        $this->assertCount(0, $logger->queries());

        $logger->log(LogLevel::DEBUG, (string)$query, ['query' => $query]);
        $this->assertCount(1, $logger->queries());
    }

    public function schemaQueryProvider()
    {
        return [
            // MySQL
            ['SHOW TABLES FROM database'],
            ['SHOW FULL COLUMNS FROM database.articles'],
            // general
            ['SELECT * FROM information_schema'],
            // sqlserver
            ['SELECT I.[name] FROM sys.[tables]'],
            ['SELECT [name] FROM sys.foreign_keys'],
            ['SELECT [name] FROM INFORMATION_SCHEMA.TABLES'],
            // sqlite
            ['PRAGMA index_info()'],
        ];
    }
}
