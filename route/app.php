<?php

use think\facade\Route;

Route::any('/manager', 'index/index');
Route::any('/noteApp', "index/index");
Route::any("/", 'index/index');
Route::any("/favicon", "index/favicon");
Route::get("/plugins/:dir/static/[:file]", "\PluginStaticSystem@index")->pattern(['dir' => '\w+', 'file' => '[\w||\s\-].*']);//插件静态资源路由文件

Route::group("/plugins", function () {
    $pluginsDir = root_path() . "plugins/";
    if (is_dir($pluginsDir)) {
        $url = request()->baseUrl();
        $urlArr = explode('/', $url);
        $pluginsDirName = '';
        if (isset($urlArr[2])) {
            $pluginsDirName = $urlArr[2];
        }
        foreach (scandir($pluginsDir) as $item) {
            if (mb_strtolower($item) == mb_strtolower($pluginsDirName)) {
                $router = $pluginsDir . $item . '/route.php';
                if (file_exists($router)) {
                    $_ENV['plugins_dir_name'] = $pluginsDir . $item;
                    include_once $router;
                    break;
                }
            }
        }
    }
    Route::miss(function () {
        return view(app_path() . "view/cardNotFound.html")->code(200);
    });
});

Route::options("[:s]", function () {
    return response('', 200);
});