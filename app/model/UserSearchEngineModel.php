<?php

namespace app\model;

use think\Model;

class UserSearchEngineModel extends Model
{
    protected $name = 'user_search_engine';
    protected $pk = 'user_id';
    protected $jsonAssoc = true;
    protected $json = ['list'];
}