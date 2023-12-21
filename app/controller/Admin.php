<?php

namespace app\controller;

use app\BaseController;
use app\model\UserModel;

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
        $user = UserModel::where($sql)->withoutField('password')->order("id", 'desc')->paginate($limit);
        return $this->success('ok', $user);
    }

    function userUpdate(): \think\response\Json
    {
        $this->getAdmin();
        $id = $this->request->post('id');
        $user = UserModel::where('id', $id)->find();
        $data = $this->request->post();
        if (!$user) {
            return $this->error('用户不存在');
        }
        //如果字段中的password有内容则md5加密后保存
        if (isset($data['password']) && mb_strlen($data['password']) > 0) {
            $data['password'] = md5($data['password']);
        } else {
            unset($data['password']);
        }
        $user->save($data);
        return $this->success('ok');
    }
}
