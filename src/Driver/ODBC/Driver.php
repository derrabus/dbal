<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\ODBC;

use Doctrine\DBAL\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\API\ODBC\ExceptionConverter;
use Doctrine\DBAL\Driver\ODBC\Exception\ConnectionFailed;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\Deprecations\Deprecation;
use LogicException;
use SensitiveParameter;

use function is_bool;
use function odbc_connect;
use function odbc_connection_string_quote;
use function odbc_connection_string_should_quote;
use function sprintf;
use function str_replace;

use const PHP_VERSION_ID;

final class Driver implements DriverInterface
{
    /** {@inheritDoc} */
    public function connect(
        #[SensitiveParameter]
        array $params
    ): Connection {
        $connection = @odbc_connect(
            $this->assembleDsn($params['driverOptions'] ?? []),
            $params['user'] ?? '',
            $params['password'] ?? '',
        );

        if ($connection === false) {
            throw ConnectionFailed::new();
        }

        return new Connection($connection);
    }

    public function getDatabasePlatform(): AbstractPlatform
    {
        throw new LogicException('The ODBC driver does not support platform detection.');
    }

    public function getSchemaManager(ConnectionInterface $conn, AbstractPlatform $platform): AbstractSchemaManager
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/5458',
            '%s() is deprecated. Use AbstractPlatform::createSchemaManager() instead.',
            __METHOD__,
        );

        return $platform->createSchemaManager($conn);
    }

    public function getExceptionConverter(): ExceptionConverter
    {
        return new ExceptionConverter();
    }

    /** @param array<string, mixed> $driverOptions */
    private function assembleDsn(array $driverOptions): string
    {
        $dsn = '';
        foreach ($driverOptions as $key => $value) {
            $dsn .= $key . '=' . $this->quoteForDsn($value) . ';';
        }

        return $dsn;
    }

    /** @param mixed $value */
    private function quoteForDsn($value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        $value = (string) $value;

        if (PHP_VERSION_ID < 80200) {
            return sprintf('{%s}', str_replace('}', '}}', $value));
        }

        return odbc_connection_string_should_quote($value)
            ? odbc_connection_string_quote($value)
            : $value;
    }
}
