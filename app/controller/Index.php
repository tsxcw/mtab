<?php

namespace app\controller;

use app\BaseController;
use app\model\SettingModel;
use GuzzleHttp\Exception\GuzzleException;
use think\exception\ErrorException;
use think\facade\Cache;
use think\facade\View;
use think\Request;

class Index extends BaseController
{
    function index(Request $request, $s = ''): string
    {
        $title = SettingModel::Config('title', 'Mtab书签');
        View::assign("title", $title);
        View::assign("keywords", SettingModel::Config('keywords', 'Mtab书签'));
        View::assign("description", SettingModel::Config('description', 'Mtab书签'));
        View::assign("version", app_version);
        View::assign("customHead",SettingModel::Config('customHead',''));
        return View::fetch("dist/index.html");
    }

    function favicon(): \think\response\File
    {
        //从配置中获取logo
        $favicon = $this->Setting('logo');
        return download(public_path() . $favicon);
    }
}
