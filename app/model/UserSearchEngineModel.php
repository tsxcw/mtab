<?php

namespace app\model;

use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\Model;

class UserSearchEngineModel extends Model
{
    protected $name = 'user_search_engine';
    protected $pk = 'user_id';
    protected $jsonAssoc = true;
    protected $json = ['list'];

    /**
     * 获取列表属性
     * 该函数用于将给定数组与从SearchEngineModel中获取的列表进行合并
     * 主要目的是为了丰富给定数组中的每个项目的信息，通过匹配ID来进行数据合并
     *
     * @param array $value 一个包含多项数据的数组，每项数据中可能包含一个'id'键
     * @return array 返回合并了SearchEngineModel中相关信息后的数组
     */
    function getListAttr($value)
    {
        // 字段为空时可能返回 null，PHP 8 下直接遍历会触发错误，这里统一返回空数组
        if (!is_array($value)) {
            return [];
        }
        // 从SearchEngineModel中选择所有记录并转换为数组
        $list = SearchEngineModel::select()->toArray();
        // 遍历传入的数组，对每个项目进行处理
        foreach ($value as &$item) {
            // 检查项目中是否存在'id'键
            if (isset($item['id'])) {
                // 遍历从数据库中获取的列表，寻找匹配的记录
                foreach ($list as $v) {
                    // 如果找到匹配的记录，将记录的信息合并到项目中
                    if ($v['id'] == $item['id']) {
                        $item = $v;
                    }
                }
            }
        }
        // 返回合并后的数组
        return $value;
    }
}