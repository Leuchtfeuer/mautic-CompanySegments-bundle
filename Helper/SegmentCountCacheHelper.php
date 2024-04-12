<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Helper;

use Mautic\CacheBundle\Cache\CacheProvider;
use Symfony\Component\Cache\Psr16Cache;

class SegmentCountCacheHelper
{
    private Psr16Cache $cache;

    public function __construct(CacheProvider $cacheStorageHelper)
    {
        $this->cache = $cacheStorageHelper->getSimpleCache();
    }

    /**
     * @throws \Exception
     */
    public function getSegmentCompanyCount(int $segmentId): int
    {
        $segmentCompanyCount = $this->cache->get($this->generateCacheKey($segmentId));

        if (!is_numeric($segmentCompanyCount)) {
            return 0;
        }

        return (int) $segmentCompanyCount;
    }

    /**
     * @throws \Exception
     */
    public function setSegmentCompanyCount(int $segmentId, int $count): void
    {
        $this->cache->set($this->generateCacheKey($segmentId), $count);
    }

    public function hasSegmentCompanyCount(int $segmentId): bool
    {
        return $this->cache->has($this->generateCacheKey($segmentId));
    }

    public function invalidateSegmentCompanyCount(int $segmentId): void
    {
        if ($this->hasSegmentCompanyCount($segmentId)) {
            $this->cache->delete($this->generateCacheKey($segmentId));
        }
    }

    /**
     * @throws \Exception
     */
    public function incrementSegmentCompanyCount(int $segmentId): void
    {
        $count = $this->hasSegmentCompanyCount($segmentId) ? $this->getSegmentCompanyCount($segmentId) : 0;
        $this->setSegmentCompanyCount($segmentId, ++$count);
    }

    /**
     * @throws \Exception
     */
    public function decrementSegmentCompanyCount(int $segmentId): void
    {
        if ($this->hasSegmentCompanyCount($segmentId)) {
            $count = $this->getSegmentCompanyCount($segmentId);

            if ($count <= 0) {
                $count = 1;
            }

            $this->setSegmentCompanyCount($segmentId, --$count);
        }
    }

    private function generateCacheKey(int $segmentId): string
    {
        return sprintf('%s.%s.%s', 'segment', $segmentId, 'company');
    }
}
