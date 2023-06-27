<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\ODBC\SQLServer;

use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\ParameterType;
use Doctrine\Deprecations\Deprecation;
use RuntimeException;

use function str_replace;

class Connection extends AbstractConnectionMiddleware
{
    /** {@inheritDoc} */
    public function quote($value, $type = ParameterType::STRING): string
    {
        return "'" . str_replace("'", "''", (string) $value) . "'";
    }

    /**
     * {@inheritDoc}
     */
    public function lastInsertId($name = null)
    {
        if ($name !== null) {
            Deprecation::triggerIfCalledFromOutside(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/issues/4687',
                'The usage of Connection::lastInsertId() with a sequence name is deprecated.',
            );

            $statement = $this
                ->prepare('SELECT CONVERT(VARCHAR(MAX), current_value) FROM sys.sequences WHERE name = ?');
            $statement->bindValue(1, $name);
            $result = $statement->execute();
        } else {
            $result = $this->query('SELECT @@IDENTITY');
        }

        return $result->fetchOne();
    }

    public function getServerVersion(): string
    {
        $version = $this->query('SELECT SERVERPROPERTY(\'productversion\')')->fetchOne();
        if ($version === false) {
            throw new RuntimeException('Unable to determine server version.');
        }

        return $version;
    }
}
