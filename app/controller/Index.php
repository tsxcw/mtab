<?php

namespace app\controller;

use app\BaseController;
use app\model\CardModel;
use app\model\ConfigModel;
use app\model\LinkModel;
use app\model\SettingModel;
use app\model\TabbarModel;
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
        //加载首页html的时候一并加载基础配置文件。
        $SiteConfig = SettingModel::siteConfig();
        $json = json_encode($SiteConfig);
        $customHead .= "<script>window.siteConfig={$json}</script>";
        $userConfig = (new Config($this->app))->get()->getData()['data'];
        if ($userConfig) {
            $userConfig = json_encode($userConfig);
            $customHead .= "<script>window.userConfig={$userConfig}</script>";
        }
        View::assign("customHead", $customHead);
        View::assign("favicon", SettingModel::Config('favicon', SettingModel::Config('logo', '/favicon.ico')));
        return View::fetch("dist/index.html");
    }

    function all(): \think\response\Json
    {
        $app = app();
        $user = $this->getUser();
        $ids = $this->request->post("ids", []);
        $dt = [];
        if (!in_array("link", $ids)) {
            $dt['link'] = LinkModel::getLink($user);
        }
        if (!in_array("tabbar", $ids)) {
            $dt['tabbar'] = TabbarModel::getTabbar($user);
        }
        if (!in_array("config", $ids)) {
            $dt['config'] = ConfigModel::getConfigs($user);
        }
        $card = CardModel::where("status", 1)->field('name_en,status')->select()->toArray();
        $dt['card'] = $card;
        $dt['site'] = (new Api($app))->site()->getData()['data'];
        $isChina = $this->ip();
        if ($isChina) {
            header("X-CountryName:China");
        } else {
            header('X-CountryName:Other');
        }
        return $this->success("ok", $dt);
    }

    function privacy(): string
    {
        $content = $this->systemSetting("privacy", "");
        return View::fetch('/privacy', ['content' => $content, 'title' => $this->systemSetting("title", ''), 'logo' => $this->systemSetting('logo', '')]);
    }

    function favicon()
    {
        //从配置中获取logo
        $favicon = $this->systemSetting('logo');
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
                    'src' => SettingModel::Config('favicon', SettingModel::Config('logo', '/favicon.ico')),
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

    protected function ip(): bool
    {
        //如果在审核模式开启，则返回false；如果关闭则返回true;默认关闭；
        $preview = SettingModel::systemSetting("ext_review", 'off');
        if ($preview == 'off') {
            return true;
        }
        return false;
    }

    function classFolderIcons(): \think\response\Json
    {
        $iconList = [
            ["src" => "/static/pageGroup/home.svg", "name" => "主页"],
            ["src" => "/static/pageGroup/game.svg", "name" => "游戏"],
            ["src" => "/static/pageGroup/music.svg", "name" => "音乐"],
            ["src" => "/static/pageGroup/work.svg", "name" => "办公"],
            ["src" => "/static/pageGroup/chat.svg", "name" => "社交"],
            ["src" => "/static/pageGroup/shop.svg", "name" => "购物"],
            ["src" => "/static/pageGroup/travel.svg", "name" => "出行"],
            ["src" => "/static/pageGroup/all.svg", "name" => "综合"],
            ["src" => "/static/pageGroup/read.svg", "name" => "阅读"],
            ["src" => "/static/pageGroup/astronomy.svg", "name" => "天文"],
            ["src" => "/static/pageGroup/safe.svg", "name" => "安全"],
            ["src" => "/static/pageGroup/crown.svg", "name" => "王冠"],
            ["src" => "/static/pageGroup/shanzi.svg", "name" => "扇子"],
            ["src" => "/static/pageGroup/photo.svg", "name" => "图片"],
            ["src" => "/static/pageGroup/star.svg", "name" => "星星"],
            ["src" => "/static/pageGroup/liwu.svg", "name" => "礼物"],
            ["src" => "/static/pageGroup/code.svg", "name" => "代码"],
            ["src" => "/static/pageGroup/movie.svg", "name" => "电影"],
            ["src" => "/static/pageGroup/hiuzhang.svg", "name" => "徽章"],
            ["src" => "/static/pageGroup/study.svg", "name" => "学习"],
            ["src" => "/static/pageGroup/kongjian.svg", "name" => "空间"],
            ["src" => "/static/pageGroup/faxian.svg", "name" => "发现"],
            ["src" => "/static/pageGroup/computer.svg", "name" => "计算机"],
            ["src" => "/static/pageGroup/xiuxian.svg", "name" => "休闲"],
            ["src" => "/static/pageGroup/geren.svg", "name" => "个人空间"],
        ];
        //不存在配置文件则使用默认的
        $configIcon = $this->systemSetting("classFolderIcons", false);
        if ($configIcon) {
            $configIcon = json_decode($configIcon, true);
        } else {
            $configIcon = $iconList;
        }
        return $this->success("ok", $configIcon);
    }
}
