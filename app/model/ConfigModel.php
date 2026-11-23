<?php
/*
 * @description:
 * @Date: 2022-09-26 20:27:01
 * @LastEditTime: 2022-09-26 20:27:53
 */

namespace app\model;

use stdClass;
use think\Model;

class ConfigModel extends Model
{
    protected $name = "config";
    protected $pk = "user_id";
    protected $jsonAssoc = true;
    protected $json = ['config'];
    public static function getConfigs($user)
    {
        if ($user) {
            $data = self::find($user['user_id']);
            if ($data) {
                return $data['config'];
            }
        }
        $config = SettingModel::systemSetting('defaultTab', 'static/defaultTab.json', true);
        if ($config) {
            $fp = public_path() . $config;
            if (!file_exists($fp)) {
                $fp = public_path() . 'static/defaultTab.json';
            }
            if (file_exists($fp)) {
                $file = file_get_contents($fp);
                $json = json_decode($file, true);
                if (isset($json['config'])) {
                    return $json['config'];
                }
            }
        }
        return new stdClass();
    }
}
