<?php


namespace app\controller;


use app\BaseController;
use app\model\ConfigModel;
use app\model\SettingModel;
use think\facade\Cache;
use think\facade\Db;

class Setting extends BaseController
{
    function saveSetting(): \think\response\Json
    {
        $this->getAdmin();
        is_demo_mode(true);
        $list = $this->request->post('form');
        $tmp = [];
        $isAuth = false;
        foreach ($list as $key => $value) {
            $tmp[] = [
                'keys' => $key,
                'value' => $value
            ];
            if ($key === 'authCode') {
                $isAuth = true;
            }
        }
        Db::table('setting')->replace()->insertAll($tmp);
        Cache::delete('webConfig');
        if ($isAuth) {
            try {
                (new \app\controller\admin\Index(app()))->authorization();
            } catch (\Exception $e) {
                // throw $e;
            }
        }
        return $this->success('保存成功');
    }

    function refreshCache(): \think\response\Json
    {
        $this->getAdmin();
        Cache::delete('webConfig');
        return $this->success('刷新成功');
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

    function delRuntime(): \think\response\Json
    {
        $admin = $this->getAdmin();
        try {
            $dir = app()->getRuntimePath();
            $this->deleteDirectory($dir);
            return $this->success('删除成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    function getSetting(): \think\response\Json
    {
        $this->getAdmin();
        $role = $this->request->post('role', []);
        $info = SettingModel::Config();
        $tmp = [];
        $url = '';
        if (in_array('ext_name', $role)) {
            if (file_exists(public_path() . '/browserExt.zip')) {
                $url = '/browserExt.zip';
            }
        }
        if ($info) {
            if (count($role) > 0) {
                foreach ($info as $key => $val) {
                    if (in_array($key, $role)) {
                        $tmp[$key] = $val;
                    }
                }
            }
            return json(['msg' => "ok", "data" => $tmp, 'success' => $this->auth, 'code' => 1, "url" => $url]);
        }
        return json(['msg' => 'ok', 'data' => false, 'success' => $this->auth, 'code' => 0, 'url' => $url]);
    }

    function mailTest(): \think\response\Json
    {
        $admin = $this->getAdmin();
        $email = $this->request->post("email");
        $config = $this->request->post("smtp", []);
        try {
            \Mail::testMail($email, $config);
            return $this->success('发送成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    function delExt(): \think\response\Json
    {
        $this->getAdmin();
        if (file_exists(public_path() . '/browserExt.zip')) {
            unlink(public_path() . '/browserExt.zip');
        }
        return $this->success("ok");
    }
}
