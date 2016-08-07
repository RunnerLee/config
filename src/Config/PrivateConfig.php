<?php
/**
 * @author: RunnerLee
 * @email: runnerleer@gmail.com
 * @time: 16-8-7 下午10:03
 */

namespace FastD\Config;

class PrivateConfig
{

    private static $config = [];


    public static function set($name, $value)
    {
        self::$config[$name] = $value;

        return true;
    }


    public static function get($name, $default = '')
    {
        return self::$config[$name] ?? $default;
    }

}