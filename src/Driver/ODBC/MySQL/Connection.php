<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\ODBC\MySQL;

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
        return "'" . str_replace(["'", '\\'], ["''", '\\\\'], (string) $value) . "'";
    }

    /** {@inheritDoc} */
    public function lastInsertId($name = null)
    {
        if ($name !== null) {
            Deprecation::triggerIfCalledFromOutside(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/issues/4687',
                'The usage of Connection::lastInsertId() with a sequence name is deprecated.',
            );
        }

        return $this->query('SELECT LAST_INSERT_ID()')->fetchOne();
    }

    public function getServerVersion(): string
    {
        $version = $this->query('SELECT VERSION()')->fetchOne();
        if ($version === false) {
            throw new RuntimeException('Unable to determine server version.');
        }

        return $version;
    }
}
