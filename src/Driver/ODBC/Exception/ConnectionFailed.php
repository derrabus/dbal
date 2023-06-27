<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\ODBC\Exception;

use Doctrine\DBAL\Driver\AbstractException;

use function odbc_error;
use function odbc_errormsg;

/** @psalm-immutable */
final class ConnectionFailed extends AbstractException
{
    public static function new(): self
    {
        return new self(odbc_errormsg(), odbc_error());
    }
}
