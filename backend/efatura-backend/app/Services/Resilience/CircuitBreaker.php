<?php

namespace App\Services\Resilience;

use Illuminate\Support\Facades\Cache;

class CircuitBreaker
{
    public function allow(string $key): bool
    {
        $state = Cache::get($this->stateKey($key), 'closed');
        if ($state === 'open') {
            $openedAt = (int) Cache::get($this->openedAtKey($key), 0);
            $openSeconds = (int) config('resilience.open_seconds', 300);
            if ($openedAt > 0 && (time() - $openedAt) >= $openSeconds) {
                // Half-open dene
                Cache::put($this->stateKey($key), 'half_open', $openSeconds);
                Cache::put($this->halfOpenTrialsKey($key), 0, $openSeconds);
                return true;
            }
            return false;
        }
        return true; // closed veya half_open
    }

    public function recordSuccess(string $key): void
    {
        Cache::forget($this->failureCountKey($key));
        Cache::forget($this->openedAtKey($key));
        Cache::put($this->stateKey($key), 'closed', config('resilience.open_seconds', 300));
    }

    public function recordFailure(string $key): void
    {
        $state = Cache::get($this->stateKey($key), 'closed');
        $failures = (int) Cache::increment($this->failureCountKey($key));
        $threshold = (int) config('resilience.failure_threshold', 5);
        if ($state === 'half_open') {
            $trials = (int) Cache::increment($this->halfOpenTrialsKey($key));
            $maxTrials = (int) config('resilience.half_open_max_trials', 3);
            if ($trials >= $maxTrials) {
                $this->open($key);
            }
            return;
        }
        if ($failures >= $threshold) {
            $this->open($key);
        }
    }

    private function open(string $key): void
    {
        Cache::put($this->stateKey($key), 'open', config('resilience.open_seconds', 300));
        Cache::put($this->openedAtKey($key), time(), config('resilience.open_seconds', 300));
    }

    private function stateKey(string $key): string { return 'cb:'.$key.':state'; }
    private function failureCountKey(string $key): string { return 'cb:'.$key.':failures'; }
    private function openedAtKey(string $key): string { return 'cb:'.$key.':opened_at'; }
    private function halfOpenTrialsKey(string $key): string { return 'cb:'.$key.':half_trials'; }
}


