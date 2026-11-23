<?php

use think\facade\Route;

Route::any('/index/all', "index/all");
Route::any('/manager', 'index/index');
Route::any("/privacy", "index/privacy");
Route::any('/noteApp', "index/index");
Route::any("/api/background$", "api/background");
Route::any("/qq_login$", "user/qq_login");
Route::any('/wx_login$', 'user/wx_login');
Route::any("/favicon", "index/favicon");
Route::get("/plugins/:dir/static/[:file]", "\PluginStaticSystem@index")->pattern(['dir' => '\w+', 'file' => '[\w||\s\-].*']); //插件静态资源路由文件
Route::any("/manifest.json", "index/manifest")->cache(60 * 10);
Route::any("/searchEngine/searchEngine", "searchEngine/searchEngine");
Route::get('/installApp', 'installApp/index');
Route::any('/installApp/testDb', 'installApp/testDb');
Route::any('/installApp/install', 'installApp/install');
Route::post("/ai/msg", "\app\controller\apps\ai\Ai@Index");
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
Route::any('/', 'index/index');
Route::options("[:s]", function () {
    return response('', 200);
})->cache(60 * 60);
