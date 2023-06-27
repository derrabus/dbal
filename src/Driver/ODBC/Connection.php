<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\ODBC;

use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\ODBC\Exception\Error;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\SQL\Parser;
use LogicException;

use function odbc_autocommit;
use function odbc_close;
use function odbc_commit;
use function odbc_exec;
use function odbc_rollback;

use const PHP_VERSION_ID;

class Connection implements ConnectionInterface
{
    /** @var resource */
    private $connection;

    private Parser $parser;

    /** @param resource $connection */
    public function __construct($connection)
    {
        $this->connection = $connection;
        $this->parser     = new Parser(false);
    }

    public function __destruct()
    {
        if (! isset($this->connection)) {
            return;
        }

        @odbc_rollback($this->connection);
        @odbc_close($this->connection);
    }

    public function prepare(string $sql): Statement
    {
        $visitor = new ConvertParameters();
        $this->parser->parse($sql, $visitor);

        return new Statement($this->connection, $visitor->getSQL(), $visitor->getParameterMap());
    }

    public function query(string $sql): Result
    {
        $result = @odbc_exec($this->connection, $sql);
        if ($result === false) {
            throw Error::new($this->connection);
        }

        return new Result($result);
    }

    /**
     * {@inheritDoc}
     *
     * @psalm-return never
     */
    public function quote($value, $type = ParameterType::STRING): string
    {
        throw new LogicException('The ODBC driver does not support quoting values.');
    }

    public function exec(string $sql): int
    {
        return $this->query($sql)->rowCount();
    }

    /**
     * {@inheritDoc}
     *
     * @psalm-return never
     */
    public function lastInsertId($name = null)
    {
        throw new LogicException('The ODBC driver does not support retrieving the last inserted ID.');
    }

    public function beginTransaction(): bool
    {
        if (PHP_VERSION_ID < 80000) {
            return (bool) odbc_autocommit($this->connection, 0);
        }

        return (bool) odbc_autocommit($this->connection, false);
    }

    public function commit(): bool
    {
        $result = odbc_commit($this->connection);
        if (PHP_VERSION_ID < 80000) {
            odbc_autocommit($this->connection, 1);
        } else {
            odbc_autocommit($this->connection, true);
        }

        return $result;
    }

    public function rollBack(): bool
    {
        $result = odbc_rollback($this->connection);
        if (PHP_VERSION_ID < 80000) {
            odbc_autocommit($this->connection, 1);
        } else {
            odbc_autocommit($this->connection, true);
        }

        return $result;
    }

    /** @return resource */
    public function getNativeConnection()
    {
        return $this->connection;
    }
}
