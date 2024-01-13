<?php

namespace app;

use app\model\CardModel;
use think\App;
use think\View;

class PluginsBase
{
    public ?View $view = null;
    public ?\think\Request $request = null;

    function __construct(App $app)
    {
        $this->request = $app->request;
        // 视图对象
        $this->view = new View($app);
        $this->_initialize();
    }
    function _initialize(){

    }
    function assign($key, $view)
    {
        $this->view->assign($key, $view);
    }

    function fetch($view, $opt = []): string
    {
        $view = plugins_path("view/" . $view);
        return $this->view->fetch($view, $opt);
    }

    public function success($msg, $data = []): \think\response\Json
    {
        if (is_array($msg)) {
            return json(['msg' => '', 'code' => 1, 'data' => $msg]);
        }
        return json(['msg' => $msg, 'code' => 1, 'data' => $data]);
    }

    public function error($msg, $data = []): \think\response\Json
    {
        if (is_array($msg)) {
            return json(['msg' => '', 'code' => 0, 'data' => $msg]);
        }
        return json(['msg' => $msg, 'code' => 0, 'data' => $data]);
    }
}