<?php

namespace app\controller\admin;

use app\BaseController;
use app\model\CardModel;
use app\model\LinkStoreModel;
use app\model\SettingModel;
use think\facade\Cache;
use think\facade\Db;


class Index extends BaseController
{
    public $authService = "https://auth.mtab.cc";
    public $authCode = '';


    function setSubscription(): \think\response\Json
    {
        $this->getAdmin();
        $code = $this->request->post("code", "");
        if (trim($code)) {
            Db::table('setting')->replace()->insert(['keys' => 'authCode', 'value' => $code]);
            SettingModel::refreshSetting();
        }
        return $this->success("ok");
    }

    private function initAuth()
    {
        // 数据库中未配置授权码时 value() 返回 null，PHP 8.1+ 下传给 strlen 会触发弃用错误，这里强制转为字符串
        $authCode = (string) SettingModel::where("keys", 'authCode')->value("value");
        if (strlen($authCode) == 0) {
            $authCode = env('authCode', '');
        }
        $this->authCode = $authCode;
        $this->authService = SettingModel::where("keys", 'authServer')->value("value");
        if (!$this->authService) {
            $this->authService = "https://auth.mtab.cc";
        }
    }


    function updateApp($n = 0): \think\response\Json
    {
        $this->getAdmin();
        $this->initAuth();
        $result = \Axios::http()->post($this->authService . '/getUpGrade', [
            'timeout' => 10,
            'form_params' => [
                'authorization_code' => $this->authCode,
                'version_code' => app_version_code,
            ]
        ]);
        if ($result->getStatusCode() == 200) {
            $json = json_decode($result->getBody()->getContents(), true);
            // 授权服务器返回非 JSON（如网关错误页）时，PHP 8 下访问 null 的数组下标会被错误处理器转成异常
            if (!is_array($json)) {
                return $this->error('授权服务器返回数据异常');
            }
            if (($json['code'] ?? null) === 1) {
                $upgradePhp = runtime_path() . 'update.php';
                $f = "";
                $upGrade = null;
                if (!empty($json['info']['update_php'])) {
                    try { //用远程脚本更新,一般用不到，除非上一个版本发生一些问题需要额外脚本处理
                        $f = file_get_contents($json['info']['update_php']);
                        file_put_contents(runtime_path() . 'update.php', $f);
                        require_once $upgradePhp;
                        $upGrade = new \Upgrade();
                    } catch (\Exception $e) {
                        return $this->error($e->getMessage());
                    }
                }
                if ($upGrade === null) {
                    $upGrade = new \Upgrade2();
                }
                if (!empty($json['info']['update_zip'])) {
                    $upGrade->update_download_url = $json['info']['update_zip'];
                }
                if (!empty($json['info']['update_sql'])) {
                    $upGrade->update_sql_url = $json['info']['update_sql'];
                }
                try {
                    // docker 环境下先请求 root 守护进程强刷一遍权限，保证覆盖/删除对用户改过的文件也可执行
                    // docker 环境判断：根目录锁文件由 start.sh 首次启动时创建
                    // 宝塔等面板开启 open_basedir 后探测 /move.lock 会抛 warning，故用 @ 抑制并 try/catch 兜底
                    try {
                        $isDocker = @is_file('/move.lock');
                    } catch (\Throwable $e) {
                        $isDocker = false;
                    }
                    if ($isDocker) {
                        $permReq = runtime_path() . 'permfix.request';
                        $permDone = runtime_path() . 'permfix.done';
                        @unlink($permDone);
                        file_put_contents($permReq, (string)time());
                        $waited = 0;
                        while (!file_exists($permDone) && $waited < 60) {
                            usleep(500000);
                            $waited += 0.5;
                        }
                        @unlink($permDone);
                    }
                    $upGrade->run(); //启动任务
                    if (file_exists($upgradePhp)) {
                        unlink($upgradePhp);
                    }
                    // 清除全部 OPcache 缓存（影响所有请求）
                    try {
                        if (function_exists('opcache_reset')) {
                            opcache_reset();
                        }
                    } catch (\Throwable $th) {
                        //throw $th;
                    }
                    return $this->success('更新完毕');
                } catch (\Exception $e) {
                    return $this->error($e->getMessage());
                }
            } else {
                return $this->error($json['msg'] ?? '远程返回异常');
            }
        }
        return $this->error("没有更新的版本");
    }

    function authorization(): \think\response\Json
    {
        $this->getAdmin();
        $this->initAuth();
        $info = [];
        $info['version'] = app_version;
        $info['version_code'] = app_version_code;
        $info['php_version'] = phpversion();
        try {
            $result = \Axios::http()->post($this->authService . '/checkAuth', [
                'timeout' => 10,
                'form_params' => [
                    'authorization_code' => $this->authCode,
                    'version_code' => app_version_code,
                    'domain' => request()->domain()
                ]
            ]);
            if ($result->getStatusCode() == 200) {
                $jsonStr = $result->getBody()->getContents();
                $json = json_decode($jsonStr, true);
                $info['remote'] = $json;
                if (!isset($json['auth'])) {
                    $f = SettingModel::where('keys', 'authCode')->find();
                    if ($f) {
                        $f->value = '';
                        $f->save();
                    }
                    Cache::delete('webConfig');
                }
                return $this->success($info);
            }
        } catch (\Exception $e) {
            // 连接失败时不再把异常对象打印到响应体（会破坏 JSON 格式），统一走下方的兜底返回
        }
        $info['remote'] = [
            "auth" => (bool)$this->authCode
        ];
        return $this->success('授权服务器连接失败', $info);
    }


    function cardList(): \think\response\Json
    {
        $this->getAdmin();
        $this->initAuth();
        try {
            $result = \Axios::http()->post($this->authService . '/card', [
                'timeout' => 15,
                'form_params' => [
                    'authorization_code' => $this->authCode
                ]
            ]);
            $json = $result->getBody()->getContents();
            $json = json_decode($json, true);
            if ($json['code'] === 1) {
                return $this->success('ok', $json['data']);
            }
        } catch (\Exception $e) {
        }
        return $this->error('远程卡片获取失败');
    }

    //获取本地应用
    function localCard(): \think\response\Json
    {
        $this->getAdmin();
        $apps = CardModel::select();
        return $this->success('ok', $apps);
    }

    function stopCard(): \think\response\Json
    {
        $this->getAdmin();
        is_demo_mode(true);
        $name_en = $this->request->post('name_en', '');
        CardModel::where('name_en', $name_en)->update(['status' => 0]);
        Cache::delete('cardList');
        return $this->success('设置成功');
    }

    function startCard(): \think\response\Json
    {
        $this->getAdmin();
        $name_en = $this->request->post('name_en', '');
        CardModel::where('name_en', $name_en)->update(['status' => 1]);
        Cache::delete('cardList');
        return $this->success('设置成功');
    }

    function installCard(): \think\response\Json
    {
        $this->getAdmin();
        $this->initAuth();
        $name_en = $this->request->post("name_en", '');
        $version = 0;
        $type = $this->request->post('type', 'install');
        if (mb_strlen($name_en) > 0) {
            $card = CardModel::where('name_en', $name_en)->find();
            if ($card) {
                if ($type == 'install') {
                    return $this->error('您已安装当前卡片组件');
                }
                if ($type == 'update') {
                    $version = $card['version'];
                }
            }
            $result = \Axios::http()->post($this->authService . '/installCard', [
                'timeout' => 15,
                'form_params' => [
                    'authorization_code' => $this->authCode,
                    'name_en' => $name_en,
                    'version' => $version,
                    'version_code' => app_version_code,
                ]
            ]);
            try {
                $json = $result->getBody()->getContents();
                $json = json_decode($json, true, JSON_UNESCAPED_UNICODE);
                if ($json['code'] == 0) {
                    return $this->error($json['msg']);
                }
                return $this->installCardTask($json['data']);
            } catch (\Exception $e) {
                return $this->error($e->getMessage());
            }
        }
        return $this->error("没有需要安装的卡片插件！");
    }

    function uninstallCard(): \think\response\Json
    {
        $this->getAdmin();
        is_demo_mode(true);
        $name_en = $this->request->post("name_en");
        if ($name_en) {
            $this->deleteDirectory(root_path() . 'plugins/' . $name_en);
            CardModel::where('name_en', $name_en)->delete();
            Cache::delete('cardList');
        }
        return $this->success('卸载完毕！');
    }

    private function deleteDirectory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file != '.' && $file != '..') {
                if (is_dir("$dir/$file")) {
                    $this->deleteDirectory("$dir/$file");
                } else {
                    unlink("$dir/$file");
                }
            }
        }
        rmdir($dir);
    }

    private function readCardInfo($name_en)
    {
        $file = root_path() . 'plugins/' . $name_en . '/info.json';
        $info = file_get_contents($file);
        try {
            return json_decode($info, true);
        } catch (\Exception $e) {
        }
        return false;
    }

    private function installCardTask($info): \think\response\Json
    {
        if ($info['download']) {
            $task = new \PluginsInstall($info);
            $state = $task->run();
            if ($state === true) {
                $config = $this->readCardInfo($info['name_en']);
                $data = [
                    'name' => $config['name'],
                    'name_en' => $config['name_en'],
                    'version' => $config['version'],
                    'tips' => $config['tips'],
                    'src' => $config['src'],
                    'url' => $config['url'],
                    'window' => $config['window'],
                ];
                if (isset($config['setting'])) {
                    $data['setting'] = $config['setting'];
                }
                $find = CardModel::where('name_en', $info['name_en'])->find();
                if ($find) {
                    $find->force()->save($data);
                } else {
                    CardModel::create($data);
                }
                Cache::delete('cardList');
                return $this->success("安装成功");
            }
            return $this->error($state);
        }
        abort(0, "新版本没有提供下载地址！");
    }

    //打包扩展
    function build(): \think\response\Json
    {
        $this->getAdmin();
        is_demo_mode(true);
        if (!extension_loaded('zip')) {
            return $this->error("系统未安装或开启zip扩展，请安装后重试！");
        }
        if (!$this->auth) {
            return $this->error("请获取授权后进行操作");
        }
        $ExtInfo = $this->request->post("extInfo", []);
        $build = new \BrowserExtBuild($ExtInfo);
        try {
            $status = $build->runBuild();
            if ($status) {
                return $this->success('打包完毕', ['url' => '/browserExt.zip']);
            }
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
        return $this->success('打包失败');
    }


    function folders(): \think\response\Json
    {
        $this->getAdmin();
        $this->initAuth();
        $result = \Axios::http()->post($this->authService . '/client/folders', [
            'timeout' => 15,
            'form_params' => [
                'authorization_code' => $this->authCode
            ]
        ]);
        $json = $result->getBody()->getContents();
        $json = json_decode($json, true);
        if ($json['code'] === 1) {
            return $this->success('ok', $json['data']);
        }
        return $this->success('获取失败');
    }

    function links(): \think\response\Json
    {
        $this->getAdmin();
        $this->initAuth();
        $folders = $this->request->get("folders");
        $page = $this->request->get("page", 1);
        $limit = $this->request->get("limit", 18);
        $result = \Axios::http()->post($this->authService . '/client/links', [
            'timeout' => 15,
            'form_params' => [
                'folders' => $folders,
                'limit' => $limit,
                'page' => $page,
                'authorization_code' => $this->authCode
            ]
        ]);
        $json = $result->getBody()->getContents();
        $json = json_decode($json, true);
        if ($json['code'] === 1) {
            $arrName = [];
            $arrUrl = [];
            foreach ($json['data']['data'] as $key => $value) {
                $arrName[] = $value['name'];
                $arrUrl[] = $value['url'];
            }
            $res = LinkStoreModel::whereOr([["name", 'in', $arrName], ['url', 'in', $arrUrl]])->select();
            return json(['code' => 1, 'msg' => 'ok', 'data' => $json['data'], 'local' => $res]);
        }
        return $this->success('获取失败');
    }

    function getBindList()
    {
        is_demo_mode(true);
        $this->getAdmin();
        $this->initAuth();
        $result = \Axios::http()->post($this->authService . '/registerIp', [
            'timeout' => 15,
            'form_params' => [
                'authorization_code' => $this->authCode
            ]
        ]);
        if ($result->getStatusCode() == 200) {
            $result = $result->getBody()->getContents();
            return json_decode($result);
        }
        return $this->success('获取失败');
    }
}
