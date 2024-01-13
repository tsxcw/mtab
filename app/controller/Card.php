<?php

namespace app\controller;

use app\BaseController;
use app\model\CardModel;
use app\model\SettingModel;

class Card extends BaseController
{
    function index(): \think\response\Json
    {
        $apps = CardModel::where('status', 1)->select();
        return $this->success('ok', $apps);
    }

    function install_num(): \think\response\Json
    {
        $id = $this->request->post('id', 0);
        if ($id) {
            $find = CardModel::where("id", $id)->find();
            if ($find) {
                $find->install_num += 1;
                $find->save();
            }
        }
        return $this->success('ok');
    }
}