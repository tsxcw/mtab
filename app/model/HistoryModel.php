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
    protected $json = ['link'];
}
