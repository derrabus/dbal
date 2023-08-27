<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\API\ODBC;

use Doctrine\DBAL\Driver\API\ExceptionConverter as ExceptionConverterInterface;
use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\DBAL\Exception\ConnectionLost;
use Doctrine\DBAL\Exception\ConstraintViolationException;
use Doctrine\DBAL\Exception\DatabaseObjectNotFoundException;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\SyntaxErrorException;
use Doctrine\DBAL\Exception\TableExistsException;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\DBAL\Query;

final class ExceptionConverter implements ExceptionConverterInterface
{
    public function convert(Exception $exception, ?Query $query): DriverException
    {
        switch ($exception->getSQLState()) {
            case '23000':
                return new ConstraintViolationException($exception, $query);
            case '28000':
            case 'S1T00':
                return new ConnectionException($exception, $query);
            case '37000':
                return new SyntaxErrorException($exception, $query);
            case '08S01':
                return new ConnectionLost($exception, $query);
            case 'S0001':
                return new TableExistsException($exception, $query);
            case 'S0002':
                return new TableNotFoundException($exception, $query);
            case 'S0022':
                return new DatabaseObjectNotFoundException($exception, $query);
        }

        return new DriverException($exception, $query);
    }
}
