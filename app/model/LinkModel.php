<?php
/*
 * @description:
 * @Date: 2022-09-26 20:27:01
 * @LastEditTime: 2022-09-26 20:27:53
 */

namespace app\model;

use think\facade\Cache;
use think\Model;

class LinkModel extends Model
{
    protected $name = "link";
    protected $pk = "user_id";
    protected $autoWriteTimestamp = "datetime";
    protected $updateTime = "update_time";
    protected $jsonAssoc = true;
    protected $json = ['link'];
    protected $WebApp = [];
    protected $card = [];

    public function __construct(array $data = [])
    {
        parent::__construct($data);
        $list = LinkStoreModel::where("app", 1)->select()->toArray();
        foreach ($list as $k => $v) {
            $this->WebApp[$v['id']] = $v;
        }
    }

    function getLinkAttr($value): array
    {
        // 字段为空时可能返回 null，PHP 8 下直接遍历会触发错误，这里统一返回空数组
        if (!is_array($value)) {
            return [];
        }
        foreach ($value as $k => &$v) {
            if (isset($v['app']) && $v['app'] == 1) {
                //如果存在app，并且id>0,且type为icon，则从app中获取数据
                if (isset($v['origin_id']) && $v['origin_id'] > 0 && $v['type'] === 'icon') {
                    $origin_id = (int)$v['origin_id'];
                    if (isset($this->WebApp[$origin_id])) {
                        $webApp = $this->WebApp[$origin_id];
                        //替换掉app数据
                        $v['custom'] = $webApp['custom'];
                        if (isset($v['custom']['height'])) {
                            $v['custom']['height'] = (int)$v['custom']['height'];
                        }
                        if (isset($v['custom']['width'])) {
                            $v['custom']['width'] = (int)$v['custom']['width'];
                        }
                        $v['url'] = $webApp['url'];
                        $v['src'] = $webApp['src'];
                        $v['name'] = $webApp['name'];
                        $v['bgColor'] = $webApp['bgColor'];
                    }
                }
            }
        }
        return (array)$value;
    }

    static function getLink($user)
    {
        if ($user) {
            $c = Cache::get("Link.{$user['user_id']}");
            if ($c) {
                return $c;
            }
            $data = LinkModel::where('user_id', $user['user_id'])->find();
            if ($data) {
                $c = $data['link'];
                Cache::tag("linkCache")->set("Link.{$user['user_id']}", $c, 60 * 60);
                return $c;
            }
        }
        $config = SettingModel::systemSetting("defaultTab", 'static/defaultTab.json', true);
        if ($config) {
            $fp = public_path() . $config;
            if (!file_exists($fp)) {
                $fp = public_path() . "static/defaultTab.json";
            }
            if (file_exists($fp)) {
                $file = file_get_contents($fp);
                $json = json_decode($file, true);
                return $json['link'] ?? [];
            }
        }
        return [];
    }
}
