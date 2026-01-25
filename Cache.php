<?php

namespace Imy\Core;

class Cache
{

    private static   $connection_name;
    protected static $engine;
    protected static $connected;


    public static function init($connection_name = 'default')
    {
        self::$connection_name = $connection_name;

        if (!$config = Config::get("cache.$connection_name")) {
            throw new Exception\System("config for cache worker: $connection_name not found");
        }

        try {
            if (class_exists('Memcached')) {
                self::$engine = new \Memcached();
            } elseif (class_exists('Memcache')) {
                self::$engine = new \Memcache();
            } else {
                return true;
            }

            self::$connected = self::$engine->addServer($config['server'], $config['port']);
        } catch (Exception $e) {
            throw new Exception\System($e->getMessage());
        }
    }

    /**
     * @param $key
     * @param $value
     * @param int $expire - time in seconds
     * @return bool
     */
    public function set($key, $value, $expire = 0)
    {
        if (self::$connected) {
            return self::$engine->set($key, $value, $expire);
        } else {
            return false;
        }
    }

    public function get($key)
    {
        if (self::$connected) {
            return self::$engine->get($key);
        } else {
            return false;
        }
    }

    public function delete($key)
    {
        if (self::$connected) {
            return self::$engine->delete($key);
        }
    }

    public function invalidate($alias)
    {
        if (self::$connected) {
            $versionKey = 'version:' . $alias;
            $currentVersion = self::$engine->get($versionKey) ?: 1;
            self::$engine->set($versionKey, (int)$currentVersion + 1, 0);
        }

    }
}
