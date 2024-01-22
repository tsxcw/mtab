<?php

namespace app\controller;

use app\BaseController;
use app\model\ConfigModel;
use app\model\HistoryModel;
use app\model\LinkModel;
use app\model\NoteModel;
use app\model\SettingModel;
use app\model\TabbarModel;
use app\model\TokenModel;
use app\model\UserModel;
use app\model\UserSearchEngineModel;
use think\facade\Cache;

class Admin extends BaseController
{
    public function UserList(): \think\response\Json
    {
        $this->getAdmin();
        $limit = $this->request->all('limit', 50);
        $search = $this->request->post('search');
        $sql = [];
        if (isset($search['mail']) && mb_strlen($search['mail']) > 0) {
            $sql['mail'] = $search['mail'];
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
}
