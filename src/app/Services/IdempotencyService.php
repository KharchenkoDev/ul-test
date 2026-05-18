<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\Cache;

class IdempotencyService
{
    private const TTL = 86400; // 24h
    private const LOCK_TTL = 10;
    private const LOCK_WAIT = 5;

    public function remember(string $key, Closure $callback): mixed
    {
        $cacheKey = "idempotency:{$key}";

        // Fast path: cache hit без блокировки
        if (($cached = Cache::get($cacheKey)) !== null) {
            return $cached;
        }

        // Медленный путь: acquire lock + double-check (защита от race condition)
        return Cache::lock("idempotency_lock:{$key}", self::LOCK_TTL)
            ->block(self::LOCK_WAIT, function () use ($cacheKey, $callback) {
                if (($cached = Cache::get($cacheKey)) !== null) {
                    return $cached;
                }

                $result = $callback();
                Cache::put($cacheKey, $result, self::TTL);

                return $result;
            });
    }
}
