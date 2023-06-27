<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\ODBC\SQLServer;

use Doctrine\DBAL\Driver\AbstractSQLServerDriver;
use Doctrine\DBAL\Driver\API\ODBC\ExceptionConverter;
use Doctrine\DBAL\Driver\ODBC\Driver as ODBCDriver;
use SensitiveParameter;

final class Driver extends AbstractSQLServerDriver
{
    private ODBCDriver $odbcDriver;

    public function __construct()
    {
        $this->odbcDriver = new ODBCDriver();
    }

    /** {@inheritDoc} */
    public function connect(
        #[SensitiveParameter]
        array $params
    ): Connection {
        if (isset($params['host'])) {
            $params['driverOptions']['Server'] ??= 'tcp:' . $params['host']
                . (isset($params['port']) ? ',' . $params['port'] : '');
        }

        if (isset($params['dbname'])) {
            $params['driverOptions']['Database'] ??= $params['dbname'];
        }

        return new Connection($this->odbcDriver->connect($params));
    }

    public function getExceptionConverter(): ExceptionConverter
    {
        return $this->odbcDriver->getExceptionConverter();
    }
}
