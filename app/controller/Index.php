<?php

namespace app\controller;

use app\BaseController;
use app\model\SettingModel;
use DateTime;
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
        View::assign("customHead", SettingModel::Config('customHead', ''));
        View::assign("favicon", SettingModel::Config('logo', '/favicon.ico'));
        return View::fetch("dist/index.html");
    }

    function favicon(): \think\response\File
    {
        //从配置中获取logo
        $favicon = $this->Setting('logo');
        $file = public_path() . $favicon;
        return download($file)->mimeType(\PluginStaticSystem::mimeType($file))->header(['Cache-Control' => 'max-age=68400']);
    }
}
