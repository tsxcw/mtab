<?php

namespace app\controller;

use app\BaseController;
use app\model\LinkStoreModel;
use app\model\SearchEngineModel;
use app\model\UserSearchEngineModel;

class SearchEngine extends BaseController
{
    function index(): \think\response\Json
    {
        $list = SearchEngineModel::where("status", 1)->order('sort', 'desc')->select();
        return $this->success("ok", $list);
    }

    public function list(): \think\response\Json
    {
        $this->getAdmin();
        $name = $this->request->post('search.name', false);
        $sql = [];
        if ($name) {
            $sql[] = ['name|tips', 'like', '%' . $name . '%'];
        }
        $list = SearchEngineModel::where($sql);
        $list = $list->order('sort', 'desc')->select();
        return $this->success('ok', $list);
    }

    function add(): \think\response\Json
    {
        is_demo_mode(true);
        $this->getAdmin();
        $data = $this->request->post('form');
        if ($data) {
            $model = new SearchEngineModel();
            if (isset($data['id']) && $data['id']) { //更新
                $model = $model->find($data['id']);
            }
            $model->save($data);
            return $this->success("保存成功！");
        }
        return $this->error('缺少数据');
    }

    function del(): \think\response\Json
    {
        is_demo_mode(true);
        $this->getAdmin();
        $ids = $this->request->post('ids', []);
        SearchEngineModel::where('id', 'in', $ids)->delete();
        return $this->success('删除成功');
    }

    function searchEngine(): \think\response\Json
    {
        $user = $this->getUser();
        if ($user) {
            $data = UserSearchEngineModel::find($user['user_id']);
            if ($data) {
                return $this->success('ok', $data['list']);
            }
        }
        $list = SearchEngineModel::where('status', 1)->order('sort', 'desc')->limit(10)->select()->toArray();
        return $this->success('ok', $list);
    }

    function saveSearchEngine(): \think\response\Json
    {
        $user = $this->getUser(true);
        if ($user) {
            $config = $this->request->post('searchEngine', []);
            if ($config) {
                $is = UserSearchEngineModel::where('user_id', $user['user_id'])->find();
                if ($is) {
                    $is->list = $config;
                    $is->force()->save();
                } else {
                    UserSearchEngineModel::create(['user_id' => $user['user_id'], 'list' => $config]);
                }
                return $this->success('ok');
            }
        }
        return $this->error('保存失败');
    }
}