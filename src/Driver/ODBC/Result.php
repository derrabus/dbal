<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\ODBC;

use Doctrine\DBAL\Driver\FetchUtils;
use Doctrine\DBAL\Driver\Result as ResultInterface;
use TypeError;

use function assert;
use function get_class;
use function gettype;
use function is_object;
use function is_resource;
use function ob_end_clean;
use function ob_get_contents;
use function ob_start;
use function odbc_binmode;
use function odbc_fetch_row;
use function odbc_field_name;
use function odbc_free_result;
use function odbc_longreadlen;
use function odbc_num_fields;
use function odbc_num_rows;
use function odbc_result;
use function sprintf;

use const ODBC_BINMODE_PASSTHRU;

class Result implements ResultInterface
{
    /** @var resource|null */
    private $result;

    /** @param resource $result */
    public function __construct($result)
    {
        if (! is_resource($result)) {
            throw new TypeError(sprintf(
                'Expected result to be a resource, got %s.',
                is_object($result) ? get_class($result) : gettype($result),
            ));
        }

        // Binary data and large strings are sent to the output buffer
        odbc_binmode($result, ODBC_BINMODE_PASSTHRU);
        odbc_longreadlen($result, 0);

        $this->result = $result;
    }

    public function __destruct()
    {
        if (! isset($this->result)) {
            return;
        }

        $this->free();
    }

    /** {@inheritDoc} */
    public function fetchNumeric()
    {
        if ($this->result === null || ! odbc_fetch_row($this->result)) {
            return false;
        }

        $row = [];
        for ($i = 1; $i <= $this->columnCount(); $i++) {
            $row[] = $this->readResultColumn($this->result, $i);
        }

        return $row;
    }

    /** {@inheritDoc} */
    public function fetchAssociative()
    {
        if ($this->result === null || ! odbc_fetch_row($this->result)) {
            return false;
        }

        $row = [];
        for ($i = 1; $i <= $this->columnCount(); $i++) {
            $fieldName = odbc_field_name($this->result, $i);
            assert($fieldName !== false);

            $row[$fieldName] = $this->readResultColumn($this->result, $i);
        }

        return $row;
    }

    /** {@inheritDoc} */
    public function fetchOne()
    {
        if ($this->result === null || ! odbc_fetch_row($this->result)) {
            return false;
        }

        return $this->readResultColumn($this->result, 1);
    }

    /** {@inheritDoc} */
    public function fetchAllNumeric(): array
    {
        return FetchUtils::fetchAllNumeric($this);
    }

    /** {@inheritDoc} */
    public function fetchAllAssociative(): array
    {
        return FetchUtils::fetchAllAssociative($this);
    }

    /** {@inheritDoc} */
    public function fetchFirstColumn(): array
    {
        return FetchUtils::fetchFirstColumn($this);
    }

    public function rowCount(): int
    {
        if ($this->result === null) {
            return 0;
        }

        return odbc_num_rows($this->result);
    }

    public function columnCount(): int
    {
        if ($this->result === null) {
            return 0;
        }

        return odbc_num_fields($this->result);
    }

    public function free(): void
    {
        if ($this->result === null) {
            return;
        }

        @odbc_free_result($this->result);
        $this->result = null;
    }

    /**
     * @param resource $result
     *
     * @return mixed
     */
    private function readResultColumn($result, int $i)
    {
        ob_start();
        try {
            $data = odbc_result($result, $i);
            if ($data === true) {
                $data = ob_get_contents();
            }
        } finally {
            ob_end_clean();
        }

        return $data;
    }
}
