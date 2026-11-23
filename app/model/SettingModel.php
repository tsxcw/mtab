<?php
/*
 * @description: 
 * @Date: 2022-09-26 20:27:01
 * @LastEditTime: 2022-09-26 20:27:53
 */

namespace app\model;

use think\facade\Cache;
use think\Model;
use think\response\Json;

class SettingModel extends Model
{
    protected $name = "setting";
    protected $pk = "keys";
    /**
     * @var array|mixed|string
     */
    static $SettingConfig = false;


    public static function Config($key = false, $default = '##')
    {
        $config = Cache::get('webConfig');
        if (!$config) {
            $config = self::select()->toArray();
            $config = array_column($config, 'value', 'keys');
            Cache::set('webConfig', $config, 300);
        }
        if ($key) {
            if (isset($config[$key]) && $config[$key] != '') {
                return $config[$key];
            }
            if ($default !== '##') {
                return $default;
            }
        }
        return $config;
    }

    static function systemSetting($key = false, $def = false, $emptyReplace = false)
    {
        if (self::$SettingConfig === false) {
            self::$SettingConfig = SettingModel::Config();
        }
        if ($key) {
            if (isset(self::$SettingConfig[$key])) {
                if ($emptyReplace && empty(self::$SettingConfig[$key])) {
                    return $def;
                }
                return self::$SettingConfig[$key];
            }
            return $def;
        }
        return self::$SettingConfig;
    }

    public static function refreshSetting()
    {
        Cache::delete('webConfig');
        self::$SettingConfig = false;
    }

    protected static function getTranslations($JsonString): array
    {
        if (!is_string($JsonString)) {
            return [];
        }
        try {
            // 判断是否是 json 字符串，如果是返回数组，不是返回 []
            $data = json_decode($JsonString, true);
            return is_array($data) ? $data : [];
        } catch (\Exception $e) {
            return [];
        }
    }

    static function siteConfig(): array
    {
        $auth = false;
        if (self::systemSetting('authCode', env('authCode', false), true)) {
            $auth = true;
        }
        return [
            'email' => self::systemSetting('email', ''),
            'qqGroup' => self::systemSetting("qqGroup", ''),
            'beianMps' => self::systemSetting("beianMps", ''),
            'copyright' => self::systemSetting("copyright", ''),
            "recordNumber" => self::systemSetting("recordNumber", ''),
            "mobileRecordNumber" => self::systemSetting('mobileRecordNumber', '0'),
            "auth" => $auth,
            "def_user_avatar" => self::systemSetting('def_user_avatar', ''),
            "logo" => self::systemSetting('logo', ''),
            "qq_login" => self::systemSetting('qq_login', '0'),
            "wx_login" => self::systemSetting('wx_login', '0'),
            "loginCloseRecordNumber" => self::systemSetting('loginCloseRecordNumber', '0'),
            "is_push_link_store" => self::systemSetting('is_push_link_store', '0'),
            "is_push_link_store_tips" => self::systemSetting('is_push_link_store_tips', '0'),
            "is_push_link_status" => self::systemSetting("is_push_link_status", '0'),
            'google_ext_link' => self::systemSetting("google_ext_link", ''),
            'edge_ext_link' => self::systemSetting("edge_ext_link", ''),
            'local_ext_link' => self::systemSetting("local_ext_link", ''),
            "customAbout" => self::systemSetting("customAbout", ''),
            "user_register" => self::systemSetting("user_register", '0', true),
            "force_bind_email" => self::systemSetting("force_bind_email", '0', true),
            "auth_check" => self::systemSetting("auth_check", '0', true),
            "captcha_check" => self::systemSetting("captcha_check", '0', true),
            "force_latest_data" => self::systemSetting("force_latest_data", '0', true),
            "tip" => [
                "ds_status" => self::systemSetting('ds_status', '0', true),
                "ds_template" => self::systemSetting('ds_template', 'org', true),
                "ds_alipay_img" => self::systemSetting('ds_alipay_img', '', true),
                "ds_wx_img" => self::systemSetting('ds_wx_img', '', true),
                "ds_custom_url" => self::systemSetting("ds_custom_url", '', true),
                'ds_title' => self::systemSetting('ds_title', '', true),
                'ds_tips' => self::systemSetting('ds_tips', '', true)
            ],
            "translations" => self::getTranslations(self::systemSetting("translations", '[]', true)),
            "timeBold" => self::systemSetting("timeBold", '0')
        ];
    }
}
