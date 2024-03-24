<?php

namespace app\controller;

use app\BaseController;
use app\model\SettingModel;
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
        $customHead = SettingModel::Config('customHead', '');
        if (SettingModel::Config('pwa', 0) == '1') {
            $customHead .= '<link rel="manifest" href="/manifest.json">';
        }
        View::assign("customHead", $customHead);
        View::assign("favicon", SettingModel::Config('logo', '/favicon.ico'));
        return View::fetch("dist/index.html");
    }
    function all()
    {
        $app = app();
        $ids = $this->request->post("ids", []);
        $dt = [];
        if (!in_array("link", $ids)) {
            $dt['link'] = (new Link($app))->get()->getData()['data'];
        }
        if (!in_array("tabbar", $ids)) {
            $dt['tabbar'] = (new Tabbar($app))->get()->getData()['data'];
        }
        if (!in_array("config", $ids)) {
            $dt['config'] = (new Config($app))->get()->getData()['data'];
        }
        $dt['site'] = (new Api($app))->site()->getData()['data'];
        return $this->success("ok", $dt);
    }
    function favicon()
    {
        //从配置中获取logo
        $favicon = $this->Setting('logo');
        $file = public_path() . $favicon;
        if (file_exists($file) && is_file($file)) {
            return download($file)->mimeType(\PluginStaticSystem::mimeType($file))->force(false)->expire(60 * 60 * 24);
        }
        return redirect("/static/mtab.png");
    }

    function manifest(): \think\response\Json
    {
        $manifest = [
            'name' => SettingModel::Config('title', 'Mtab书签'),
            'short_name' => SettingModel::Config('title', 'Mtab书签'),
            'description' => SettingModel::Config('description', 'Mtab书签'),
            'manifest_version' => 2,
            'version' => app_version,
            'theme_color' => SettingModel::Config('theme_color', '#141414'),
            'icons' => [
                [
                    'src' => SettingModel::Config('logo', '/favicon.ico'),
                    'sizes' => '144x144'
                ]
            ],
            'display' => 'standalone',
            'orientation' => 'portrait',
            'start_url' => '/',
            'scope' => '/',
            'permissions' => [
                'geolocation',
                'notifications'
            ]
        ];
        return json($manifest);
    }
}
