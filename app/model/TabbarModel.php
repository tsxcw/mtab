<?php
/*
 * @description:
 * @Date: 2022-09-26 20:27:01
 * @LastEditTime: 2022-09-26 20:27:53
 */

namespace app\model;

use think\Model;

class TabbarModel extends Model
{
    protected $name = "tabbar";
    protected $pk = "user_id";
    protected $jsonAssoc = true;
    protected $json = ['tabs'];

    static function getTabbar($user)
    {
        if ($user) {
            // 按更新时间倒序取最新一条，避免历史遗留的重复记录读到旧数据
            $data = self::where("user_id", $user['user_id'])->order("update_time", "desc")->find();
            if ($data) {
                $tabs = self::normalizeTabs($data['tabs']);
                // 记录存在但数据库中的 tabs 已损坏（非数组）时回退到站点默认布局，避免页脚直接消失
                if ($tabs !== null) {
                    return $tabs;
                }
            }
        }
        return self::getDefaultTabbar();
    }

    /**
     * 规范化 tabbar 数据：过滤非对象项、缺失 id 项，并按 id 去重
     * @param mixed $tabs 待校验的 tabbar 数据
     * @return array|null 合法数组返回过滤后的结果；不是数组时返回 null 表示数据非法
     */
    public static function normalizeTabs($tabs): ?array
    {
        if (!is_array($tabs)) {
            return null;
        }
        $result = []; // 过滤后的合法 tabbar 项
        $seen = [];   // 已出现的 id，用于去重
        foreach ($tabs as $item) {
            if (!is_array($item) || !isset($item['id']) || $item['id'] === '') {
                continue;
            }
            $key = (string)$item['id'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $result[] = $item;
        }
        return $result;
    }

    /**
     * 读取站点默认的 tabbar 布局
     * @return array 默认 tabbar 数组
     */
    private static function getDefaultTabbar(): array
    {
        $config = SettingModel::systemSetting('defaultTab', '/static/defaultTab.json', true);
        if ($config) {
            $fp = joinPath(public_path(), $config);
            if (!file_exists($fp)) {
                $fp = public_path() . 'static/defaultTab.json';
            }
            if (file_exists($fp)) {
                $file = file_get_contents($fp);
                $json = json_decode($file, true);
                // 配置文件解析失败时返回空数组，避免直接访问 null 报错
                $tabs = is_array($json) ? self::normalizeTabs($json['tabbar'] ?? []) : null;
                return $tabs ?? [];
            }
        }
        return [];
    }
}
