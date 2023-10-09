<?php

namespace app\controller;

use app\BaseController;
use app\model\HistoryModel;
use app\model\LinkModel;
use app\model\SettingModel;
use app\model\TabbarModel;

class Link extends BaseController
{
    public function update(): \think\response\Json
    {
        $user = $this->getUser(true);
        if ($user) {
            $link = $this->request->post("link", []);
            if ($link) {
                $is = LinkModel::where("user_id", $user['user_id'])->find();
                if ($is) {
                    $is->link = $link;
                    $is->save();
                } else {
                    LinkModel::create(["user_id" => $user['user_id'], "link" => $link]);
                }
                HistoryModel::create(["user_id" => $user['user_id'], "link" => $link]); //历史记录备份,用于用户误操作回复用途
                return $this->success('ok');
            }
        }
        return $this->error('保存失败');
    }

    public function get(): \think\response\Json
    {
        $user = $this->getUser();
        if ($user) {
            $data = LinkModel::find($user['user_id']);
            if ($data) {
                return $this->success('ok', $data['link']);
            }
        }
        $config = $this->Setting("defaultTab", '/static/defaultTab.json', true);
        if ($config) {
            $fp = joinPath(public_path(), $config);
            if (file_exists($fp)) {
                $file = file_get_contents($fp);
                $json = json_decode($file, true);
                return $this->success('ok', $json['link'] ?? []);
            }
        }
        return $this->success('ok', []);
    }

    public function reset(): \think\response\Json
    {
        $user = $this->getUser();
        if ($user) {
            $data = LinkModel::find($user['user_id']);
            if ($data) {
                $data->delete();
            }
            $data = TabbarModel::find($user['user_id']);
            if ($data) {
                $data->delete();
            }
        }
        return $this->success('ok');
    }
}
