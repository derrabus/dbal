<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\ODBC\Exception;

use Doctrine\DBAL\Driver\AbstractException;

use function odbc_error;
use function odbc_errormsg;

/** @psalm-immutable */
class Error extends AbstractException
{
    /** @param resource $connection */
    public static function new($connection): self
    {
        return new self(odbc_errormsg($connection), odbc_error($connection));
    }
}
