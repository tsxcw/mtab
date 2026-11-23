<?php


namespace app\controller;


use app\BaseController;
use think\facade\View;

class Note extends BaseController
{
    //获取列表
    public function get(): \think\response\Json
    {
        $user = $this->getUser();
        $limit = $this->request->get('limit', 999999);
        if (!$user) {
            return $this->success('', []);
        }
        $data = (new \app\model\NoteModel)->where("user_id", $user['user_id'])->field('user_id,id,title,create_time,update_time,weight,sort')->order(['sort' => 'asc', 'create_time' => 'desc'])->limit($limit)->select();
        return $this->success('ok', $data);
    }

    function sort(): \think\response\Json
    {
        $user = $this->getUser(true);
        $ids = $this->request->post('ids', []);
        $data = (new \app\model\NoteModel)->field("id,user_id,sort")->where("user_id", $user['user_id'])->whereIn('id', $ids)->select()->toArray();
        //查询到和id做个比对重新设置sort入库，批量入库，
        $data_map = [];
        foreach ($data as $k => $v) {
            $data_map[$v['id']] = $v;
        }
        $update_data = [];
        foreach ($ids as $k => $v) {
            if (isset($data_map[$v])) {
                $update_data[] = [
                    "id" => $v,
                    "sort" => $k
                ];
            }
        }
        try {
            (new \app\model\NoteModel)->saveAll($update_data);
        } catch (\Exception $e) {
            return $this->error('排序失败');
        }
        return $this->success('ok');

    }

    //获取文本
    public function getText(): \think\Response
    {
        $user = $this->getUser(true);
        $id = $this->request->get('id');
        $data = (new \app\model\NoteModel)->where("user_id", $user['user_id'])->field("text,id")->where('id', $id)->value('text');
        return response($data);
    }

    function setWeight(): \think\response\Json
    {
        $user = $this->getUser(true);
        $weight = $this->request->post('weight', 0);
        $id = $this->request->post('id', false);
        if ($id) {
            $data = array(
                'weight' => $weight,
                'update_time' => date('Y-m-d H:i:s'),
            );
            (new \app\model\NoteModel)->where('id', $id)->where('user_id', $user['user_id'])->update($data);
        }
        return $this->success("ok");
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
            'weight' => $this->request->post("weight", 0),
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
            "text" => $text,
            "title" => $title,
            'weight' => $this->request->post('weight', 0),
            "update_time" => date("Y-m-d H:i:s"),
        );
        $status = (new \app\model\NoteModel)->where("id", $id)->where('user_id', $user['user_id'])->find()->save($data);
        if ($status) {
            $data['id'] = $id;
            return $this->success("修改", $data);
        }
        return $this->error('失败');
    }
}