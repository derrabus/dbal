<?php

namespace Doctrine\DBAL\Cache;

use ArrayIterator;
use Cache\Adapter\Doctrine\DoctrineCachePool;
use Doctrine\Common\Cache\Cache;
use Doctrine\DBAL\Driver\ResultStatement;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\FetchMode;
use InvalidArgumentException;
use IteratorAggregate;
use PDO;
use Psr\Cache\CacheItemPoolInterface;
use TypeError;
use const E_USER_DEPRECATED;
use function array_merge;
use function array_values;
use function assert;
use function get_class;
use function reset;
use function sprintf;
use function trigger_error;

/**
 * Cache statement for SQL results.
 *
 * A result is saved in multiple cache keys, there is the originally specified
 * cache key which is just pointing to result rows by key. The following things
 * have to be ensured:
 *
 * 1. lifetime of the original key has to be longer than that of all the individual rows keys
 * 2. if any one row key is missing the query has to be re-executed.
 *
 * Also you have to realize that the cache will load the whole result into memory at once to ensure 2.
 * This means that the memory usage for cached results might increase by using this feature.
 */
class ResultCacheStatement implements IteratorAggregate, ResultStatement
{
    /** @var CacheItemPoolInterface */
    private $resultCache;

    /** @var string */
    private $cacheKey;

    /** @var string */
    private $realKey;

    /** @var int */
    private $lifetime;

    /** @var ResultStatement */
    private $statement;

    /**
     * Did we reach the end of the statement?
     *
     * @var bool
     */
    private $emptied = false;

    /** @var mixed[] */
    private $data;

    /** @var int */
    private $defaultFetchMode = FetchMode::MIXED;

    /**
     * @param CacheItemPoolInterface $resultCache
     * @param string                 $cacheKey
     * @param string                 $realKey
     * @param int                    $lifetime
     */
    public function __construct(ResultStatement $stmt, object $resultCache, $cacheKey, $realKey, $lifetime)
    {
        if ($resultCache instanceof Cache) {
            @trigger_error(sprintf('Using an instance of %s as result cache is deprecated. Please provide a PSR-6 cache instead.', Cache::class), E_USER_DEPRECATED);

            $resultCache = new DoctrineCachePool($resultCache);
        } elseif (! $resultCache instanceof CacheItemPoolInterface) {
            throw new TypeError(sprintf('Expected $resultCache to be an instance of %s, got %s.', CacheItemPoolInterface::class, get_class($resultCache)));
        }

        $this->statement   = $stmt;
        $this->resultCache = $resultCache;
        $this->cacheKey    = $cacheKey;
        $this->realKey     = $realKey;
        $this->lifetime    = $lifetime;
    }

    /**
     * {@inheritdoc}
     */
    public function closeCursor()
    {
        $this->statement->closeCursor();
        if (! $this->emptied || $this->data === null) {
            return true;
        }

        $item                 = $this->resultCache->getItem($this->cacheKey);
        $data                 = $item->isHit() ? $item->get() : [];
        $data[$this->realKey] = $this->data;

        $item->set($data);
        $item->expiresAfter($this->lifetime);

        $this->resultCache->save($item);
        unset($this->data);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function columnCount()
    {
        return $this->statement->columnCount();
    }

    /**
     * {@inheritdoc}
     */
    public function setFetchMode($fetchMode, $arg2 = null, $arg3 = null)
    {
        $this->defaultFetchMode = $fetchMode;

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        $data = $this->fetchAll();

        return new ArrayIterator($data);
    }

    /**
     * {@inheritdoc}
     */
    public function fetch($fetchMode = null, $cursorOrientation = PDO::FETCH_ORI_NEXT, $cursorOffset = 0)
    {
        if ($this->data === null) {
            $this->data = [];
        }

        $row = $this->statement->fetch(FetchMode::ASSOCIATIVE);

        if ($row) {
            $this->data[] = $row;

            $fetchMode = $fetchMode ?: $this->defaultFetchMode;

            if ($fetchMode === FetchMode::ASSOCIATIVE) {
                return $row;
            }

            if ($fetchMode === FetchMode::NUMERIC) {
                return array_values($row);
            }

            if ($fetchMode === FetchMode::MIXED) {
                return array_merge($row, array_values($row));
            }

            if ($fetchMode === FetchMode::COLUMN) {
                return reset($row);
            }

            throw new InvalidArgumentException('Invalid fetch-style given for caching result.');
        }

        $this->emptied = true;

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll($fetchMode = null, $fetchArgument = null, $ctorArgs = null)
    {
        $data = $this->statement->fetchAll($fetchMode, $fetchArgument, $ctorArgs);

        if ($fetchMode === FetchMode::COLUMN) {
            $realData = [];
            foreach ($data as $key => $value) {
                $realData[$key] = [$value];
            }
            $this->data = $realData;
        } else {
            $this->data = $data;
        }

        $this->emptied = true;

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchColumn($columnIndex = 0)
    {
        $row = $this->fetch(FetchMode::NUMERIC);

        // TODO: verify that return false is the correct behavior
        return $row[$columnIndex] ?? false;
    }

    /**
     * Returns the number of rows affected by the last DELETE, INSERT, or UPDATE statement
     * executed by the corresponding object.
     *
     * If the last SQL statement executed by the associated Statement object was a SELECT statement,
     * some databases may return the number of rows returned by that statement. However,
     * this behaviour is not guaranteed for all databases and should not be
     * relied on for portable applications.
     *
     * @return int The number of rows.
     */
    public function rowCount()
    {
        assert($this->statement instanceof Statement);

        return $this->statement->rowCount();
    }
}
