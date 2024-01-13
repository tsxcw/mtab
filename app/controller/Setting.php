<?php


namespace app\controller;


use app\BaseController;
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
        foreach ($list as $key => $value) {
            $tmp[] = [
                'keys' => $key,
                'value' => $value
            ];
        }
        Db::table('setting')->replace()->insertAll($tmp);
        Cache::delete('webConfig');
        return $this->success('保存成功');
    }

    function getSetting(): \think\response\Json
    {
        $admin = $this->getAdmin();
        $info = SettingModel::Config();
        if ($info) {
            return $this->success('ok', $info);
        }
        return $this->error('empty');
    }
}