<?php

namespace app\model;

use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\facade\Cache;
use think\Model;

class CardModel extends Model
{
    protected $name = "card";
    protected $pk = "id";
    static array $stopCard = [];

    public static function cardStatus($name_en = ''): bool
    {
        $config = self::$stopCard;
        if (count($config) == 0) {
            $config = self::cache('cardList', 60 * 60)->select()->toArray();
            self::$stopCard = $config;
        }
        foreach ($config as $item) {
            if ($item['name_en'] == $name_en) {
                if ($item['status'] === 1) {
                    return true;
                }
            }
        }
        return false;
    }

    public static function cardInfo($name_en = '')
    {
        $config = self::$stopCard;
        if (count($config) == 0) {
            $config = self::cache('cardList', 60 * 60)->select()->toArray();
            self::$stopCard = $config;
        }
        foreach ($config as $item) {
            if ($item['name_en'] == $name_en) {
                return $item;
            }
        }
        return false;
    }

    /**
     * 获取单个配置信息
     * @param string $cardName
     * @param string $key
     * @param string $default
     * @return mixed|string|null
     */
    public static function config(string $cardName = '', string $key = '', string $default = '')
    {
        $card = self::where('name_en', $cardName)->value('dict_option');
        if ($card) {
            try {
                $json = json_decode($card, true);
                if (isset($json[$key])) {
                    return $json[$key];
                }
            } catch (\Exception $e) {
            }

            if ($default) {
                return $default;
            }
            return null;
        }
        return null;
    }

    /**
     * 获取整个配置信息 返回数组对象
     * @param string $cardName
     * @return mixed|string|null
     */
    public static function configs(string $cardName = '')
    {
        $card = self::where('name_en', $cardName)->value('dict_option');
        if ($card) {
            try {
                return json_decode($card, true);
            } catch (\Exception $e) {
            }
            return [];
        }
        return [];
    }

    /**
     * 保存配置信息，完整的配置数组
     * @param string $cardName
     * @param array $option
     * @return bool
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public static function saveConfigs(string $cardName = '', array $option = []): bool
    {
        $card = self::where('name_en', $cardName)->find();
        if ($card) {
            try {
                $json = json_encode($option);
                $card->save(['dict_option' => $json]);
                return true;
            } catch (\Exception $e) {
            }
        }
        return false;
    }


    /**
     * 保存单个配置信息，不存在则创建
     * @param string $cardName
     * @param string $key
     * @param $value
     * @return bool
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public static function saveConfig(string $cardName = '', string $key = '', $value=''): bool
    {
        $card = self::where('name_en', $cardName)->find();
        if ($card) {
            $config = self::configs($cardName);
            $config[$key] = $value;
            $card->save(['dict_option' => json_encode($config)]);
            return true;
        }
        return false;
    }
}