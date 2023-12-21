<?php
/*
 * @description:
 * @Date: 2022-09-26 20:27:01
 * @LastEditTime: 2022-09-26 20:27:53
 */

namespace app\model;

use think\Model;

class LinkModel extends Model
{
    protected $name = "link";
    protected $pk = "user_id";
    protected $autoWriteTimestamp = "datetime";
    protected $updateTime = "update_time";
    protected $jsonAssoc = true;
    protected $json = ['link'];
}
