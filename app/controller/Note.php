<?php


namespace app\controller;


use app\BaseController;
use think\Exception;

class Note extends BaseController
{
    //获取列表
    public function get(): \think\response\Json
    {
        $user = $this->getUser();
        $sort = $this->request->get('sort', 'desc');
        $limit = $this->request->get('limit', 999999);
        if (!$user) {
            return $this->success('', []);
        }
        $data = (new \app\model\NoteModel)->where("user_id", $user['user_id'])->field('user_id,id,title,create_time,update_time')->order('id', $sort)->limit($limit)->select();
        return $this->success('ok', $data);
    }

    //获取文本
    public function getText(): \think\Response
    {
        $user = $this->getUser(true);
        $id = $this->request->get('id');
        $data = (new \app\model\NoteModel)->where("user_id", $user['user_id'])->field("text,id")->where('id', $id)->find();
        try {
            return response($data['text']);
        } catch (Exception $e) {
            return response('');
        }
    }

    //删除
    public function del(): \think\response\Json
    {
        $user = $this->getUser(true);
        $id = $this->request->get('id');
        $data = (new \app\model\NoteModel)->where("user_id", $user['user_id'])->where('id', $id)->delete();
        return $this->success('删除成功', $data);
    }

    //添加内容
    public function add(): \think\response\Json
    {
        $user = $this->getUser(true);
        $title = $this->request->post('title', '');
        $text = $this->request->post('text', '');
        $id = $this->request->post('id', false);
        if ($id != '') {
            return $this->update();
        }
        $data = array(
            "user_id" => $user['user_id'],
            "text" => $text,
            "title" => $title,
            "create_time" => date("Y-m-d H:i:s"),
            "update_time" => date("Y-m-d H:i:s"),
        );
        $status = (new \app\model\NoteModel)->insertGetId($data);
        if ($status) {
            $data['id'] = $status;
            return $this->success("创建成功", $data);
        }
        return $this->error('失败');
    }

    //更新内容
    public function update(): \think\response\Json
    {
        $user = $this->getUser(true);
        $id = $this->request->post('id', false);
        if (!$id) {
            return $this->error('no');
        }
        $title = $this->request->post('title', '');
        $text = $this->request->post('text', '');
        $data = array(
            "user_id" => $user['user_id'],
            "text" => $text,
            "title" => $title,
            "update_time" => date("Y-m-d H:i:s"),
        );
        $status = (new \app\model\NoteModel)->where("id", $id)->where('user_id', $user['user_id'])->update($data);
        if ($status) {
            $data['id'] = $id;
            return $this->success("修改", $data);
        }
        return $this->error('失败');
    }
}