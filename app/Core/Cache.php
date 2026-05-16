<?php

namespace App\Core;

use Redis;

class Cache
{
    private static ?Cache $instance = null;
    private ?Redis $redis = null;
    private bool $connectionAttempted = false;
    private array $config;

    private function __construct(array $config)
    {
        $this->config = array_merge([
            'host' => '127.0.0.1',
            'port' => 6379,
            'password' => null,
            'db' => 0,
            'timeout' => 0.5,
        ], $config);
    }

    public static function init(array $config = []): void
    {
        self::$instance = new self($config);
    }

    public static function getInstance(): Cache
    {
        if (self::$instance === null) {
            self::$instance = new self([]);
        }
        return self::$instance;
    }

    private function getRedis(): ?Redis
    {
        if ($this->redis !== null) {
            return $this->redis;
        }
        if ($this->connectionAttempted) {
            return null;
        }
        $this->connectionAttempted = true;

        if (!extension_loaded('redis')) {
            return null;
        }

        try {
            $r = new Redis();
            if (!$r->connect($this->config['host'], (int)$this->config['port'], (float)$this->config['timeout'])) {
                return null;
            }
            if (!empty($this->config['password'])) {
                $r->auth($this->config['password']);
            }
            if (!empty($this->config['db'])) {
                $r->select((int)$this->config['db']);
            }
            $this->redis = $r;
            return $this->redis;
        } catch (\Throwable $e) {
            error_log("Redis connection failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Atomic check-and-set with TTL.
     * - true:  key was newly created (caller is the first hit within the window)
     * - false: key already existed (duplicate within window)
     * - null:  Redis unavailable — caller should proceed as if dedup is disabled
     */
    public function checkAndSet(string $key, int $ttl): ?bool
    {
        $r = $this->getRedis();
        if ($r === null) {
            return null;
        }

        try {
            $result = $r->set($key, 1, ['nx', 'ex' => $ttl]);
            return $result === true;
        } catch (\Throwable $e) {
            error_log("Redis checkAndSet failed for key {$key}: " . $e->getMessage());
            return null;
        }
    }
}
