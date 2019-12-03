<?php

namespace Doctrine\DBAL\Cache;

use BadMethodCallException;
use Doctrine\Common\Cache\Cache;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\DoctrineAdapter;
use Symfony\Component\Cache\DoctrineProvider;
use TypeError;
use const E_USER_DEPRECATED;
use function get_class;
use function hash;
use function serialize;
use function sha1;
use function sprintf;
use function trigger_error;

/**
 * Query Cache Profile handles the data relevant for query caching.
 *
 * It is a value object, setter methods return NEW instances.
 */
class QueryCacheProfile
{
    /** @var CacheItemPoolInterface|null */
    private $resultCache;

    /** @var Cache|null */
    private $resultCacheDriver;

    /** @var int */
    private $lifetime = 0;

    /** @var string|null */
    private $cacheKey;

    /**
     * @param int                    $lifetime
     * @param CacheItemPoolInterface $resultCache
     */
    public function __construct($lifetime = 0, ?string $cacheKey = null, ?object $resultCache = null)
    {
        $this->lifetime = $lifetime;
        $this->cacheKey = $cacheKey;

        if ($resultCache instanceof Cache) {
            @trigger_error(sprintf('Using an instance of %s as result cache is deprecated. Please provide a PSR-6 cache instead.', Cache::class), E_USER_DEPRECATED);

            $resultCache = new DoctrineAdapter($resultCache);
            $this->resultCacheDriver = $resultCache;
        } elseif ($resultCache !== null && ! $resultCache instanceof CacheItemPoolInterface) {
            throw new TypeError(sprintf('Expected $resultCache to be an instance of %s or null, got %s.', CacheItemPoolInterface::class, get_class($resultCache)));
        } else {
            $this->resultCacheDriver = null;
        }

        $this->resultCache = $resultCache;
    }

    /**
     * @return Cache|null
     */
    public function getResultCacheDriver()
    {
        @trigger_error(sprintf('%s is deprecated. Use getResultCache() instead.', __METHOD__), E_USER_DEPRECATED);

        if ($this->resultCacheDriver !== null) {
            return $this->resultCacheDriver;
        }

        if ($this->resultCache === null) {
            return null;
        }

        return new DoctrineProvider($this->resultCache);
    }

    public function getResultCache() : ?CacheItemPoolInterface
    {
        return $this->resultCache;
    }

    /**
     * @return int
     */
    public function getLifetime()
    {
        return $this->lifetime;
    }

    /**
     * @return string
     *
     * @throws CacheException
     */
    public function getCacheKey()
    {
        if ($this->cacheKey === null) {
            throw CacheException::noCacheKey();
        }

        return $this->cacheKey;
    }

    /**
     * Generates the real cache key from query, params, types and connection parameters.
     *
     * @param string         $query
     * @param mixed[]        $params
     * @param int[]|string[] $types
     * @param mixed[]        $connectionParams
     *
     * @return string[]
     */
    public function generateCacheKeys($query, $params, $types, array $connectionParams = [])
    {
        $realCacheKey = 'query=' . $query .
            '&params=' . serialize($params) .
            '&types=' . serialize($types) .
            '&connectionParams=' . hash('sha256', serialize($connectionParams));

        // should the key be automatically generated using the inputs or is the cache key set?
        if ($this->cacheKey === null) {
            $cacheKey = sha1($realCacheKey);
        } else {
            $cacheKey = $this->cacheKey;
        }

        return [$cacheKey, $realCacheKey];
    }

    /**
     * @return \Doctrine\DBAL\Cache\QueryCacheProfile
     */
    public function setResultCacheDriver(Cache $cache)
    {
        @trigger_error(sprintf('%s is deprecated. Use setResultCache() instead.', __METHOD__), E_USER_DEPRECATED);

        return new QueryCacheProfile($this->lifetime, $this->cacheKey, $cache);
    }

    public function setResultCache(CacheItemPoolInterface $cache) : self
    {
        return new QueryCacheProfile($this->lifetime, $this->cacheKey, $cache);
    }

    /**
     * @param string|null $cacheKey
     *
     * @return \Doctrine\DBAL\Cache\QueryCacheProfile
     */
    public function setCacheKey($cacheKey)
    {
        return new QueryCacheProfile($this->lifetime, $cacheKey, $this->resultCache);
    }

    /**
     * @param int $lifetime
     *
     * @return \Doctrine\DBAL\Cache\QueryCacheProfile
     */
    public function setLifetime($lifetime)
    {
        return new QueryCacheProfile($lifetime, $this->cacheKey, $this->resultCache);
    }
}
