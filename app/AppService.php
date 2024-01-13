<?php
declare (strict_types=1);
namespace app;

use think\Service;

/**
 * 应用服务类
 */
class AppService extends Service
{
    public function register()
    {
        // 服务注册
        if (!file_exists(public_path() . 'installed.lock')) {//如果没有安装的就提示安装
            header("Location:/install.php");
            exit();
        }
    }

    public function boot()
    {
        // 服务启动
    }
}
