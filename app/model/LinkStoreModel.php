<?php

/*
 * @description:
 * @Date: 2022-09-26 20:27:01
 * @LastEditTime: 2022-09-26 20:27:53
 */

namespace app\model;

use think\Model;

class LinkStoreModel extends Model
{
    protected $name = "linkstore";
    protected $pk = "id";
    protected $jsonAssoc = true;
    protected $json = ['custom'];

    function userInfo(): \think\model\relation\HasOne
    {
        return $this->hasOne(UserModel::class, 'id', 'user_id')->field('id,nickname');
    }
    function setGroupIdsAttr($val): string
    {
        // 字段可能为 null（例如前端未提交该字段），PHP 8 下 count(null) 会抛 TypeError，这里先做类型判断
        if (is_array($val) && count($val) > 0) {
            return join(',', $val);
        }
        return '0';
    }

    function getGroupIdsAttr($val): array
    {
        // 数据库字段可能为 null，PHP 8.1+ 下 strlen(null) 会触发弃用错误，这里统一转成字符串
        if (strlen((string)$val)) {
            return array_map('intval', explode(',', $val));
        }
        return [];
    }
}
