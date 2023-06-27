<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\ODBC;

use Doctrine\DBAL\Driver\ODBC\Exception\Error;
use Doctrine\DBAL\Driver\ODBC\Exception\UnknownParameter;
use Doctrine\DBAL\Driver\Statement as StatementInterface;
use Doctrine\DBAL\ParameterType;
use Doctrine\Deprecations\Deprecation;

use function array_map;
use function func_num_args;
use function is_int;
use function is_resource;
use function ksort;
use function odbc_execute;
use function odbc_prepare;
use function stream_get_contents;

class Statement implements StatementInterface
{
    /** @var resource */
    private $connection;

    private string $sql;

    /** @var array<array-key, int> */
    private array $parameterMap;

    /** @var array<int, mixed> */
    private array $parameters = [];

    /**
     * @param resource              $connection
     * @param array<array-key, int> $parameterMap
     */
    public function __construct($connection, string $sql, array $parameterMap)
    {
        $this->connection   = $connection;
        $this->sql          = $sql;
        $this->parameterMap = $parameterMap;
    }

    /** {@inheritDoc} */
    public function bindValue($param, $value, $type = ParameterType::STRING): bool
    {
        if (! isset($this->parameterMap[$param])) {
            throw UnknownParameter::new((string) $param);
        }

        $this->parameters[$this->parameterMap[$param]] = $value;

        return true;
    }

    /** {@inheritDoc} */
    public function bindParam($param, &$variable, $type = ParameterType::STRING, $length = null): bool
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/5563',
            '%s is deprecated. Use bindValue() instead.',
            __METHOD__,
        );

        if (func_num_args() < 3) {
            Deprecation::trigger(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/pull/5558',
                'Not passing $type to Statement::bindParam() is deprecated.'
                . ' Pass the type corresponding to the parameter being bound.',
            );
        }

        if (func_num_args() > 4) {
            Deprecation::triggerIfCalledFromOutside(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/issues/4533',
                'The $length argument of Statement::bindParam() is deprecated.',
            );
        }

        if (! isset($this->parameterMap[$param])) {
            throw UnknownParameter::new((string) $param);
        }

        $this->parameters[$this->parameterMap[$param]] = &$variable;

        return true;
    }

    /** {@inheritDoc} */
    public function execute($params = null): Result
    {
        if ($params !== null) {
            Deprecation::trigger(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/pull/5556',
                'Passing $params to Statement::execute() is deprecated. Bind parameters using'
                . ' Statement::bindParam() or Statement::bindValue() instead.',
            );

            foreach ($params as $param => $value) {
                if (is_int($param)) {
                    $this->bindValue($param + 1, $value, ParameterType::STRING);
                } else {
                    $this->bindValue($param, $value, ParameterType::STRING);
                }
            }
        }

        ksort($this->parameters);

        $parameters = array_map(
            static fn ($value) => is_resource($value)
                ? stream_get_contents($value)
                : $value,
            $this->parameters,
        );

        $statement = @odbc_prepare($this->connection, $this->sql);
        if ($statement === false) {
            throw Error::new($this->connection);
        }

        if (@odbc_execute($statement, $parameters) === false) {
            throw Error::new($this->connection);
        }

        return new Result($statement);
    }
}
