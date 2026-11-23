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
        if (!file_exists(public_path() . '/installed.lock')) {//如果没有安装的就提示安装
            //如果是/installApp开头的路径一律放行（静态固定地址跳转，非用户可控）
            exit('<!DOCTYPE html><meta charset="utf-8"><script>location.replace("/install.php")</script><meta http-equiv="refresh" content="0;url=/install.php">');
        }
    }

    public function boot()
    {

    }
}
