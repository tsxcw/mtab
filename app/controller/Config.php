<?php


namespace app\controller;


use app\BaseController;
use app\model\ConfigModel;
use app\model\SearchEngineModel;
use app\model\UserSearchEngineModel;
use stdClass;

class Config extends BaseController
{
    public function update(): \think\response\Json
    {
        $user = $this->getUser(true);
        if ($user) {
            $config = $this->request->post("config", []);
            if ($config) {
                $is = ConfigModel::where("user_id", $user['user_id'])->find();
                if ($is) {
                    $is->config = $config;
                    $is->save();
                } else {
                    ConfigModel::create(["user_id" => $user['user_id'], "config" => $config]);
                }
                return $this->success('ok');
            }
        }
        return $this->error('保存失败');
    }

    public function get(): \think\response\Json
    {
        $user = $this->getUser();
        return $this->success('ok', ConfigModel::getConfigs($user));
    }
}
