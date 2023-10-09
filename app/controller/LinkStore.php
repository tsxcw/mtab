<?php

namespace app\controller;

use app\BaseController;
use app\model\LinkStoreModel;
use think\facade\Db;

class LinkStore extends BaseController
{
    public function list(): \think\response\Json
    {
        $limit = $this->request->post('limit', 15);
        $name = $this->request->post('name', false);
        $area = $this->request->post('area', false);
        $sql = [];
        if ($name) {
            $sql[] = ['name', 'like', "%" . $name . "%"];
        }
        $list = LinkStoreModel::where($sql);
        //area需要使用find_in_set来匹配
        if ($area) {
            $list = $list->whereRaw("find_in_set('$area',area)");
        }
        $list = $list->order("hot", 'desc')->paginate($limit);
        return $this->success('ok', $list);
    }

    private function update(): \think\response\Json
    {
        $data = $this->request->post("form");
        $info = LinkStoreModel::where("id", $data['id'])->update($data);
        return $this->success('修改成功', $info);
    }

    public function add(): \think\response\Json
    {
        $admin = $this->getAdmin();
        $data = $this->request->post('form');
        if ($data) {
            if (isset($data['id']) && $data['id']) { //更新
                return $this->update();
            } else {
                $data['create_time'] = date("Y-m-d H:i:s");
                $info = (new \app\model\LinkStoreModel)->insert($data);
                return $this->success('添加成功', $info);
            }
        }
        return $this->error('缺少数据');
    }

    public function getIcon(): \think\response\Json
    {
        $url = $this->request->post('url', false);

        if ($url) {
            if (mb_substr($url, 0, 4) == 'tab:') {
            } else {
                if (mb_substr($url, 0, 4) != 'http') {
                    $url = 'https://' . $url;
                }
                $url = parse_url($url);
                $url = $url['host'];
            }
            $data = LinkStoreModel::whereRaw("FIND_IN_SET('$url',domain)")->find();
            if ($data) {
                return $this->success('ok', $data);
            }
        }
        return $this->error('no', '未查询到相关信息');
    }

    function install_num(): \think\response\Json
    {
        $id = $this->request->post('id', false);
        //给标签+=1
        $res = Db::table("linkstore")->where('id', $id)->inc('install_num')->update();
        if ($res) {
            return $this->success('ok');
        }
        return $this->error('fail');
    }

    public function del(): \think\response\Json
    {
        $this->getAdmin();
        $ids = $this->request->post('ids', []);
        LinkStoreModel::where("id", 'in', $ids)->delete();
        return $this->success('删除成功');
    }
}
