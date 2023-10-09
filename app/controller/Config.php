<?php


namespace app\controller;


use app\BaseController;
use app\model\ConfigModel;

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
        if ($user) {
            $data = ConfigModel::find($user['user_id']);
            if ($data) {
                return $this->success("ok", $data['config']);
            }
        }
        return $this->error('not Config');
    }
}