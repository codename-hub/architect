<?php

namespace codename\architect\deploy\task\client\cache;

use codename\architect\deploy\task\client\cache;
use codename\architect\deploy\taskresult;
use codename\core\exception;
use ReflectionException;

/**
 * class for flushing a cache
 */
class flush extends cache
{
    /**
     * flush configuration
     * @var array
     */
    protected array $flushConfig = [];

    /**
     * {@inheritDoc}
     * @return taskresult
     * @throws ReflectionException
     * @throws exception
     */
    public function run(): taskresult
    {
        $cacheInstance = $this->getCache();

        $results = [];

        foreach ($this->flushConfig as $flush) {
            if ($flush['all'] ?? false) {
                $success = $cacheInstance->flush();
                $results[] = chr(9) . "[" . ($success ? 'SUCCESS' : 'FAIL') . "]" . " Flush all cache items.";
            } else {
                $cacheGroup = $flush['cache_group'];
                $cacheKey = $flush['cache_key'] ?? null;
                if ($cacheKey) {
                    $success = $cacheInstance->clearKey($cacheGroup, $cacheKey);
                    $results[] = chr(9) . "[" . ($success ? 'SUCCESS' : 'FAIL') . "]" . " '{$cacheGroup}_$cacheKey' (specific key)";
                } else {
                    $success = $cacheInstance->clearGroup($cacheGroup);
                    $results[] = chr(9) . "[" . ($success ? 'SUCCESS' : 'FAIL') . "]" . " '{$cacheGroup}_*' (whole group)";
                }
            }
        }

        $resultsAsText = implode(chr(10), $results);

        return new taskresult\text([
          'text' => "Flushed cache '$this->cacheIdentifier':" . chr(10) . $resultsAsText,
        ]);
    }

    /**
     * {@inheritDoc}
     */
    protected function handleConfig(): void
    {
        parent::handleConfig();
        $this->flushConfig = $this->config->get('flush');
    }
}
