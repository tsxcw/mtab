<?php
/*
 * @description: 
 * @Date: 2022-09-26 20:27:01
 * @LastEditTime: 2022-09-26 20:27:53
 */

namespace app\model;

use think\facade\Cache;
use think\Model;

class SettingModel extends Model
{
    protected $name = "setting";
    protected $pk = "keys";
    static $CacheConfig = false;

    public static function Config($key = false, $default = '##')
    {
        $config = self::$CacheConfig;
        if (!$config) {
            $config = Cache::get('webConfig');
            if (!$config) {
                $config = self::select()->toArray();
                $config = array_column($config, 'value', 'keys');
                Cache::set('webConfig', $config, 300);
            }
        }
        if ($key) {
            if (isset($config[$key])) {
                return $config[$key];
            }
            if ($default !== '##') {
                return $default;
            }
        }
        return $config;
    }

    public static function refreshSetting()
    {
        Cache::delete('webConfig');
    }
}
