<?php

namespace app\controller\admin;

use app\BaseController;
use app\model\CardModel;
use app\model\LinkStoreModel;
use app\model\SettingModel;
use app\model\UserModel;
use DateInterval;
use DatePeriod;
use DateTime;
use think\facade\Cache;
use think\facade\Db;

//use Upgrade;

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
        $authCode = $this->Setting('authCode', '', true);
        if (strlen($authCode) == 0) {
            $authCode = env('authCode', '');
        }
        $this->authCode = $authCode;
        $this->authService = $this->Setting('authServer', 'https://auth.mtab.cc', true);
    }

    function updateApp(): \think\response\Json
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
            if ($json['code'] === 1) {
                $upgradePhp = runtime_path() . 'update.php';
                $f = "";
                $upGrade = null;
                if (!empty($json['info']['update_php'])) {
                    try {//用远程脚本更新,一般用不到，除非上一个版本发生一些问题需要额外脚本处理
                        $f = file_get_contents($json['info']['update_php']);
                        file_put_contents(runtime_path() . 'update.php', $f);
                        require_once $upgradePhp;
                        $upGrade = new \Upgrade();
                    } catch (\Exception $e) {

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
                $status = $upGrade->run();//启动任务
                try {
                    unlink($upgradePhp);
                } catch (\Exception $e) {

                }
                if ($status === true) {
                    return $this->success('更新完毕');
                } else {
                    return $this->error($status);
                }
            } else {
                return $this->error($json['msg']);
            }
        }
        return $this->error("没有更新的版本");
    }

    function authorization(): \think\response\Json
    {
        $this->getAdmin();
        $this->initAuth();
        $result = \Axios::http()->post($this->authService . '/checkAuth', [
            'timeout' => 5,
            'form_params' => [
                'authorization_code' => $this->authCode,
                'version_code' => app_version_code,
            ]
        ]);
        $info = [];
        $info['version'] = app_version;
        $info['version_code'] = app_version_code;
        $info['php_version'] = phpversion();
        if ($result->getStatusCode() == 200) {
            $jsonStr = $result->getBody()->getContents();
            $json = json_decode($jsonStr, true);
            $info['remote'] = $json;
            return $this->success($info);
        } else {
            return $this->error('授权服务器连接失败', $info);
        }
    }

    private function countFilesInDirectory($directory): int
    {
        $fileCount = 0;

        // 获取目录中的文件和子目录
        $files = scandir($directory);

        foreach ($files as $file) {
            // 排除"."和".."
            if ($file != '.' && $file != '..') {
                $filePath = $directory . '/' . $file;

                // 如果是目录，则递归调用函数
                if (is_dir($filePath)) {
                    $fileCount += $this->countFilesInDirectory($filePath);
                } else {
                    // 如果是文件，则增加文件数量
                    $fileCount++;
                }
            }
        }

        return $fileCount;
    }

    function getServicesStatus(): \think\response\Json
    {
        $this->getAdmin();
        $userNum = UserModel::count("id");
        $linkNum = LinkStoreModel::count("id");
        $redisNum = 0;
        $fileNum = Cache::get("fileNum");
        if (!$fileNum) {
            if (is_dir(public_path() . 'images')) {
                $fileNum = $this->countFilesInDirectory(public_path() . 'images');
                Cache::set('fileNum', $fileNum, 300);
            }
        }
        return $this->success("ok", ["userNum" => $userNum, "linkNum" => $linkNum, "redisNum" => $redisNum, "fileNum" => $fileNum]);
    }

    function getUserLine(): \think\response\Json
    {
        $this->getAdmin();
        $result = UserModel::whereMonth('create_time');
        $result = $result->field('DATE_FORMAT(create_time, "%Y-%m-%d") as time, count(id) as total');
        $result = $result->group('time')->select();
        return $this->success('ok', $this->render($result));
    }

    function getHotTab(): \think\response\Json
    {
        $this->getAdmin();
        $list = LinkStoreModel::order('install_num', 'desc')->limit(30)->cache('hotTab', 60)->select()->toArray();
        return $this->success('ok', $list);
    }

    private function render($arr): array
    {
        $info = [];
        foreach ($arr as $key => $value) {
            $info[$value['time']] = $value['total'];
        }
        $time = [];
        $total = [];
        //当月的第一天
        $start = date('Y-m-01', strtotime(date('Y-m-d')));
        //当月的最后一天
        $end = date('Y-m-d', strtotime(date('Y-m-01') . ' +1 month -1 day'));
        $start_date = new DateTime($start);
        $end_date = new DateTime($end);
        $interval = new DateInterval('P1D');
        $dateRange = new DatePeriod($start_date, $interval, $end_date);
        $ts = null;
        foreach ($dateRange as $date) {
            $ts = $date->format('Y-m-d');
            $time[] = $ts;
            if (isset($info[$ts])) {
                $total[] = $info[$ts];
            } else {
                $total[] = 0;
            }
        }
        // 判断是否需要添加最后一天的数据
        if ($end_date->format('Y-m-d') != $ts) {
            $time[] = $end_date->format('Y-m-d');
            $total[] = isset($info[$end_date->format('Y-m-d')]) ? $info[$end_date->format('Y-m-d')] : 0;
        }
        return ['time' => $time, 'total' => $total, 'sum' => array_sum($total)];
    }

    function cardList(): \think\response\Json
    {
        $this->getAdmin();
        $this->initAuth();
        $result = \Axios::http()->post($this->authService . '/card', [
            'timeout' => 15,
            'form_params' => [
                'authorization_code' => $this->authCode
            ]
        ]);
        try {
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
                    'version' => $version
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
            }

        }
        return $this->error("没有需要安装的卡片插件！");
    }

    function uninstallCard(): \think\response\Json
    {
        $this->getAdmin();
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
        return $this->error('新版本没有提供下载地址！');
    }
}