<?php

namespace app;


class Request extends \think\Request
{

    function __construct()
    {
        parent::__construct();
        header('Access-Control-Allow-Origin:*');
        header('Access-Control-Allow-Headers:*');
        header('Access-Control-Allow-Methods:*');
    }
}
