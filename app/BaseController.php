<?php
/*
 * @description:
 * @Date: 2022-09-26 17:52:37
 * @LastEditTime: 2022-09-26 20:28:17
 */

declare(strict_types=1);

namespace app;

use app\model\SettingModel;
use app\model\TokenModel;
use app\model\UserModel;

use think\App;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\facade\Config;
use think\Model;

/**
 * 控制器基础类
 */
class BaseController
{
    /**
     * Request实例
     * @var \think\Request
     */
    protected $request;

    /**
     * 应用实例
     * @var \think\App
     */
    protected $app;

    /**
     * 是否批量验证
     * @var bool
     */
    protected $batchValidate = false;

    /**
     * 控制器中间件
     * @var array
     */
    protected $middleware = [];

    public $auth = false;

    public function __construct(App $app)
    {
        $this->app = $app;
        $this->request = $this->app->request;
        // 控制器初始化
        $this->initialize();
    }

    // 初始化
    protected function initialize()
    {
        if ($this->systemSetting('authCode', env('authCode', false), true)) {
            $this->auth = true;
        }
        if ($this->systemSetting("app_debug", '0') === '1') {
            $this->app->debug(true);
            Config::set([
                'show_error_msg' => true,
                'exception_tmpl' => app()->getThinkPath() . 'tpl/think_exception.tpl'
            ], 'app');
        }
    }

    //系统设置项
    protected function systemSetting($key = false, $def = false, $emptyReplace = false)
    {

        return SettingModel::systemSetting($key, $def, $emptyReplace);
    }

    /**
     * @description :用户信息获取
     * @param false $must 是否强制验证，true则强制验证程序退出
     * */
    protected function getUser(bool $must = false)
    {
        return UserModel::getUser($must);
    }

    //admin认证
    protected function getAdmin()
    {
        $user = $this->getUser(true);
        $info = UserModel::where('id', $user['user_id'])->where("manager", 1)->find();
        if ($info) {
            return $info;
        }
        $this->error('not permission')->send();
        exit();
    }

    protected function success($msg, $data = []): \think\response\Json
    {
        if (is_array($msg)) {
            return json(['msg' => "", "code" => 1, "data" => $msg]);
        }
        return json(['msg' => $msg, "code" => 1, "data" => $data]);
    }

    protected function error($msg, $data = []): \think\response\Json
    {
        if (is_array($msg)) {
            return json(['msg' => "", "code" => 0, "data" => $msg]);
        }
        return json(['msg' => $msg, "code" => 0, "data" => $data]);
    }
}
