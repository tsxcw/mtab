<?php

namespace app\model;

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
}