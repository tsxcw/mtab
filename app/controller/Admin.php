<?php

namespace app\controller;

use app\BaseController;
use app\command\repair;
use app\model\ConfigModel;
use app\model\FileModel;
use app\model\HistoryModel;
use app\model\LinkModel;
use app\model\LinkStoreModel;
use app\model\NoteModel;
use app\model\SettingModel;
use app\model\TabbarModel;
use app\model\TokenModel;
use app\model\UserModel;
use app\model\UserSearchEngineModel;
use DateInterval;
use DatePeriod;
use DateTime;
use mysqli;
use think\facade\Cache;
use think\facade\Db;

class Admin extends BaseController
{
    public function UserList(): \think\response\Json
    {
        $this->getAdmin();
        $limit = $this->request->all('limit', 50);
        $search = $this->request->post('search');
        $group = $this->request->post('search.group_id', -1);
        $sql = [];
        if (isset($search['mail']) && mb_strlen($search['mail']) > 0) {
            $sql[] = ['mail', 'like', "%$search[mail]%"];
        }
        if (isset($search['nickname']) && mb_strlen($search['nickname']) > 0) {
            $sql[] = ["nickname", "like", "%$search[nickname]%"];
        }
        if (isset($search['status']) && $search['status'] >= 0) {
            $sql[] = ['status', '=', $search['status']];
        }
        if (is_numeric($group) && $group >= 0) {
            $sql['group_id'] = $search['group_id'];
        }
        $user = UserModel::where($sql)->withoutField('password')->order($this->request->post('sort.prop', 'id'), $this->request->post('sort.order', 'desc'))->paginate($limit);
        return $this->success('ok', $user);
    }

    function userUpdate(): \think\response\Json
    {
        $this->getAdmin();
        is_demo_mode(true);
        $id = $this->request->post('id');
        $user = UserModel::where('id', $id)->find();
        $data = $this->request->post();
        if (!$user) {
            $user = new UserModel();
        }
        //如果字段中的password有内容则md5加密后保存
        if (isset($data['password']) && mb_strlen($data['password']) > 0) {
            $data['password'] = md5($data['password']);
        } else {
            unset($data['password']);
        }
        $user->save($data);
        return $this->success('保存成功');
    }

    //用户删除函数
    function userDelete(): \think\response\Json
    {
        $this->getAdmin();
        is_demo_mode(true);
        $id = $this->request->post('id');
        $user = UserModel::where('id', $id)->find();
        if ($user) {//删除当前用户下的所有数据。
            LinkModel::where("user_id", $user['id'])->delete();//删除标签
            TabbarModel::where("user_id", $user['id'])->delete();//删除快捷图标
            HistoryModel::where('user_id', $user['id'])->delete();//删除历史图标
            ConfigModel::where('user_id', $user['id'])->delete();//删除配置信息
            NoteModel::where('user_id', $user['id'])->delete();//删除笔记
            UserSearchEngineModel::where('user_id', $user['id'])->delete();//删除自定义搜索引擎
            TokenModel::where('user_id', $user['id'])->delete();//删除所有Token
            $user->delete();//删除用户
        }
        return $this->success("删除完毕");
    }

    function export(): \think\response\Json
    {
        $this->getAdmin();
        is_demo_mode(true);
        $link = $this->request->post('link', []);
        if ($link) {
            $saveName = public_path() . 'static/exportsTabLink.json';
            $status = file_put_contents($saveName, json_encode($link, true, JSON_UNESCAPED_UNICODE));
            if ($status) {
                $setting = new SettingModel();
                if ($setting->find('defaultTab')) {
                    $setting->update(['value' => 'static/exportsTabLink.json'], ['keys' => 'defaultTab']);
                } else {
                    $setting->save(['keys' => 'defaultTab', 'value' => 'static/exportsTabLink.json']);
                }
                Cache::delete('webConfig');
                return $this->success('保存成功');
            }
        }
        return $this->error('保存失败');
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

    function xyCheck(): \think\response\Json
    {
        $this->getAdmin();
        if (is_file(root_path() . 'xy.pem')) {
            if (file_get_contents(root_path() . 'xy.pem') === file_get_contents(config_path() . 'LICENSE.html')) {
                return $this->success("ok");
            }
        }
        return $this->error("未找到证书文件", ['license' => file_get_contents(config_path() . 'LICENSE.html')]);
    }

    function xy(): \think\response\Json
    {
        $this->getAdmin();
        file_put_contents(root_path() . "xy.pem", file_get_contents(config_path() . 'LICENSE.html'));
        return $this->success("ok");
    }

    function getServicesStatus(): \think\response\Json
    {
        $this->getAdmin();
        $userNum = UserModel::count('id');
        $linkNum = LinkStoreModel::count('id');
        $redisNum = 0;
        $fileNum = FileModel::field('id')->count("id");
        $userWeekActive = 0;
        if (Cache::get('userWeekActive')) {
            $userWeekActive = Cache::get('userWeekActive');
        } else {
            $start = date('Y-m-d', strtotime('-7 days'));
            $userWeekActive = UserModel::where('active', ">", $start)->field("id,active")->count('id');
            Cache::set('userWeekActive', $userWeekActive, 60);
        }
        return $this->success('ok', ['userNum' => $userNum, 'linkNum' => $linkNum, 'redisNum' => $redisNum, 'fileNum' => $fileNum, "userWeekActive" => $userWeekActive]);
    }

    function getUserLine(): \think\response\Json
    {
        $this->getAdmin();
        $today = new DateTime();
        $firstDayOfMonth = new DateTime($today->format('Y-m-01'));
        $lastDayOfMonth = new DateTime($today->format('Y-m-t'));
        $formatDate = function ($date) {
            return $date->format('Y-m-d');
        };
        $defaultDate = [
            $formatDate($firstDayOfMonth),
            $formatDate($lastDayOfMonth)
        ];
        $dateSelect = $this->request->post('dateSelect', $defaultDate);
        $result = UserModel::whereBetweenTime('create_time', $dateSelect[0], $dateSelect[1]);  // 当前月
        $result = $result->field('DATE_FORMAT(create_time, "%Y-%m-%d") as time, count(id) as total');
        $result = $result->group('time')->select();
        return $this->success('ok', $this->render($result, $dateSelect[0], $dateSelect[1]));
    }

    function getHotTab(): \think\response\Json
    {
        $this->getAdmin();
        $list = LinkStoreModel::order('install_num', 'desc')->limit(30)->cache('hotTab', 60)->select()->toArray();
        return $this->success('ok', $list);
    }

    private function render($arr, $start, $end): array
    {
        $info = [];
        foreach ($arr as $key => $value) {
            $info[$value['time']] = $value['total'];
        }
        $time = [];
        $total = [];
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

    function userLoginRecord(): \think\response\Json
    {
        $this->getAdmin();
        $user_id = $this->request->post('user_id');
        if ($user_id && !is_demo_mode()) {
            $list = TokenModel::where("user_id", $user_id)->field('user_id,FROM_UNIXTIME(create_time) as create_time,user_agent,ip')->order('create_time', 'desc')->limit(100)->select()->toArray();
            return $this->success('', $list);
        }
        return $this->success('', []);
    }

    function repair(): \think\response\Json
    {
        $this->getAdmin();
        is_demo_mode(true);
        repair::repair();
        return $this->success("修复完毕");
    }
}
