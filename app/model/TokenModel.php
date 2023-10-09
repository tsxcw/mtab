<?php

namespace app\model;

use think\Model;

class TokenModel extends Model
{
    protected $name = 'token';
    protected $pk = 'id';
    protected $autoWriteTimestamp = false;
}