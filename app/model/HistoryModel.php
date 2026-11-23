<?php
/*
 * @description:
 * @Date: 2022-09-26 20:27:01
 * @LastEditTime: 2022-09-26 20:27:53
 */

namespace app\model;

use think\Model;

class HistoryModel extends Model
{
    protected $name = "history";
    protected $pk = "id";
    protected $jsonAssoc = true;
    // link 与 tabbar 均以 JSON 存储，备份时同时记录两类数据，恢复时可一并还原
    protected $json = ['link', 'tabbar'];
}
