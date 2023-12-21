<?php


namespace app\controller;


use app\BaseController;
use app\model\TabbarModel;

class Tabbar extends BaseController
{
    public function update(): \think\response\Json
    {
        $user = $this->getUser(true);
        if ($user) {
            $tabbar = $this->request->post("tabbar", []);
            if (is_array($tabbar)) {
                $is = TabbarModel::where("user_id", $user['user_id'])->find();
                if ($is) {
                    $is->tabs = $tabbar;
                    $is->save();
                } else {
                    TabbarModel::create(["user_id" => $user['user_id'], "tabs" => $tabbar]);
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
            $data = TabbarModel::find($user['user_id']);
            if ($data) {
                return $this->success('ok', $data['tabs']);
            }
        }
        $config = $this->Setting('defaultTab', '/static/defaultTab.json', true);
        if ($config) {
            $fp = joinPath(public_path(), $config);
            if (file_exists($fp)) {
                $file = file_get_contents($fp);
                $json = json_decode($file, true);
                return $this->success('ok', $json['tabbar'] ?? []);
            }
        }
        return $this->success('ok', []);
    }
}
