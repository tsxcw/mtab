<?php

namespace app\controller\apps\todo;

use app\PluginsBase;
use app\model\PluginsToDoFolderModel;
use app\model\PluginsToDoModel;

class Index extends PluginsBase
{

    function getFolderAndToDo(): \think\response\Json
    {
        $user = $this->getUser(true);
        $list = PluginsToDoFolderModel::where('user_id', $user['user_id'])->order("create_time")->select()->toArray();
        $toDoList = PluginsToDoModel::where('user_id', $user['user_id'])->orderRaw('status = 1 ASC, create_time ASC')->select()->toArray();
        return $this->success("ok", ['folder' => $list, 'todo' => $toDoList]);
    }

    function createFolder(): \think\response\Json
    {
        $user = $this->getUser(true);
        $name = $this->request->post("name", '待办事项');
        $id = $this->request->post("id");
        if ($name && $id) {
            //修改模式
            $folder = PluginsToDoFolderModel::where('user_id', $user['user_id'])->find($id);
            if ($folder) {
                $folder->name = $name;
                $folder->save();
                return $this->success("修改成功", $folder);
            }
            //返回失败
            return $this->error("修改失败");
        }
        if (PluginsToDoFolderModel::where('user_id', $user['user_id'])->count('id') > 20) {
            return $this->error("最多可以创建20个列表");
        }
        $insertId = PluginsToDoFolderModel::insertGetId(["name" => $name, 'create_time' => date('Y-m-d H:i:s'), 'user_id' => $user['user_id']]);
        return $this->success("ok", PluginsToDoFolderModel::where('user_id', $user['user_id'])->find($insertId));
    }

    function createToDo(): \think\response\Json
    {
        $user = $this->getUser(true);
        $todo = $this->request->post("todo");
        $id = $this->request->post("id");
        $folder = $this->request->post("folder",0);
        $expire_time = $this->request->post("expire_time");
        if(!$expire_time){
            $expire_time = null;
        }
        $weight = $this->request->post("weight", 0);
        $status = $this->request->post("status", 0);
        if ($id) {
            if ($todo && mb_strlen($todo) > 500) {
                return $this->error("待办内容不能超过500字，请分割待办事项");
            }
            PluginsToDoModel::where("id", $id)->where("user_id", $user['user_id'])->update(['todo' => $todo, 'folder' => $folder, 'expire_time' => $expire_time, 'weight' => $weight, 'status' => $status]);
            return $this->success("ok", PluginsToDoModel::where("id", $id)->where("user_id", $user['user_id'])->find($id));
        }
        if (PluginsToDoModel::where('user_id', $user['user_id'])->field('id,user_id')->count("id") > 300) {
            return $this->error("最多可以创建300条待办");
        }
        $td = PluginsToDoModel::insertGetId(["todo" => $todo, "user_id" => $user['user_id'], 'status' => $status, 'weight' => $weight, 'create_time' => date('Y-m-d H:i:s'), 'folder' => $folder, 'expire_time' => $expire_time]);
        return $this->success("ok", PluginsToDoModel::where("user_id", $user['user_id'])->find($td));
    }

    function delFolder(): \think\response\Json
    {
        $user = $this->getUser();
        $id = $this->request->post('id');
        if ($id) {
            $folder = PluginsToDoFolderModel::where('user_id', $user['user_id'])->where('id', $id)->find();
            if ($folder) {
                PluginsToDoModel::where("user_id", $user['user_id'])->where("folder", $id)->delete();
                $folder->delete();
                return $this->success('删除完毕');
            }
        }
        return $this->success('删除失败');
    }

    function delToDo(): \think\response\Json
    {
        $user = $this->getUser();
        $id = $this->request->post("id");
        if ($id) {
            $toDo = PluginsToDoModel::where('user_id', $user['user_id'])->find($id);
            $toDo->delete();
        }
        return $this->success('删除完毕');
    }
}
