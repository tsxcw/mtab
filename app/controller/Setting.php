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
        $list = $this->request->post('form');
        $tmp = [];
        foreach ($list as $key => $value) {
            $tmp[] = [
                'keys' => $key,
                'value' => $value
            ];
        }
        Db::table('setting')->replace()->insertAll($tmp);
        $config = array_column($tmp, 'value', 'keys');
        Cache::set('webConfig', $config, 300);
        return $this->success('ok');
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