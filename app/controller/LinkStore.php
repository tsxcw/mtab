<?php

namespace app\controller;

use app\BaseController;
use app\model\FileModel;
use app\model\LinkFolderModel;
use app\model\LinkStoreModel;
use think\facade\Db;

class LinkStore extends BaseController
{

    public function list(): \think\response\Json
    {
        $user = $this->getUser();
        $limit = $this->request->post('limit', 12);
        $name = $this->request->post('name', false);
        $area = $this->request->post('area', false);
        $sql = [];
        if ($name) {
            $sql[] = ['name|tips|url', 'like', '%' . $name . '%'];
        }

        $list = LinkStoreModel::where($sql)->where('status', 1)->withoutField('user_id');

        // 使用 find_in_set 匹配 area
        if ($area && $area != 0) {
            $list = $list->whereRaw('find_in_set(?,area)', [$area]);
        }

        // 将两个 whereOrRaw 条件组合在一起
        $list = $list->where(function ($query) use ($user) {
            $query->whereRaw('find_in_set(0,group_ids)');
            if ($user) {
                $query->whereOrRaw('find_in_set(?,group_ids)', [$user['group_id']]);
            }
        });

        $list = $list->order(['hot' => 'desc', 'create_time' => 'desc'])->paginate($limit);

        return $this->success('ok', $list);
    }


    public function ListManager(): \think\response\Json
    {
        $admin = $this->getAdmin();
        $limit = $this->request->post('limit', 15);
        $name = $this->request->post('search.name', false);
        $area = $this->request->post('search.area', false);
        $group_id = $this->request->post('search.group_id',false);
        $sql = [];
        if ($name) {
            $sql[] = ['name|tips', 'like', '%' . $name . '%'];
        }
        $list = LinkStoreModel::with(['userInfo'])->where($sql);
        //area需要使用find_in_set来匹配
        if ($area && $area != '全部') {
            $list = $list->whereRaw("find_in_set(?,area)", [$area]);
        }
        if($group_id){
            $list = $list->whereRaw('find_in_set(?,group_ids)', [$group_id]);
        }
        $list = $list->order($this->request->post('sort.prop', 'id'), $this->request->post('sort.order', 'asc'))->paginate($limit);
        return json(["msg" => "ok", 'data' => $list, 'auth' => $this->auth]);
    }

    function getFolder(): \think\response\Json
    {
        $user = $this->getUser();
        $list = new LinkFolderModel();
        $list = $list->whereOrRaw("find_in_set(0,group_ids)");
        if ($user&&(int)$user['group_id'] != 0) {
            $list = $list->whereOrRaw('find_in_set(?,group_ids)', [$user['group_id']]);
        }
        return $this->success("ok", $list->order('sort', 'desc')->select());
    }

    function getFolderAdmin(): \think\response\Json
    {
        $user = $this->getAdmin();
        $list = new LinkFolderModel();
        return $this->success('ok', $list->order('sort', 'desc')->select());
    }

    private function update(): \think\response\Json
    {
        is_demo_mode(true);
        $admin = $this->getAdmin();
        $data = $this->request->post("form");
        try {
            unset($data['userInfo']);
        } catch (\Exception $exception) {

        }
        $info = LinkStoreModel::where("id", $data['id'])->withoutField(['userInfo'])->find();
        $info->update($data);
        return $this->success('修改成功', $info);
    }

    function addPublic(): \think\response\Json
    {
        $user = $this->getAdmin();
        $info = $this->request->post();
        $info['create_time'] = date("Y-m-d H:i:s");
        $info['domain'] = $this->getDomain($info['url']);
        $info['src'] = $this->downloadLogo($info['src']);
        FileModel::addFile($info['src'], $user['id']);
        if (isset($info['id'])) {
            unset($info['id']);
        }
        (new \app\model\LinkStoreModel)->allowField(["name", "src", "url", "domain", "create_time", "tips", "app"])->insert($info);
        return $this->success('添加成功', $info);
    }

    private function downloadLogo($src): string
    {
        $f = file_get_contents($src);
        $pathinfo = pathinfo($src);
        try {
            mkdir(public_path() . 'images/' . date("Y/m/d"), 0755, true);
        } catch (\Throwable $th) {
            //throw $th;
        }
        $filePath = '/images/' . date("Y/m/d") . '/' . md5($src) . '.' . $pathinfo['extension'];
        file_put_contents(joinPath(public_path(), $filePath), $f);
        return $filePath;
    }

    function push(): \think\response\Json
    {
        $user = $this->getUser(true);
        $data = $this->request->post();
        $info = [];
        if ($data) {
            if (isset($data['name'])) {
                $info['name'] = $data['name'];
            }
            if (isset($data['src'])) {
                $info['src'] = $data['src'];
            }
            if (isset($data['url']) && mb_strlen($data['url']) > 2) {
                $info['url'] = $data['url'];
            } else {
                return $this->error('推送失败');
            }
            if (isset($data['bgColor'])) {
                $info['bgColor'] = $data['bgColor'];
            }
            if (isset($data['app'])) {
                $info['app'] = $data['app'];
            }
            if (isset($data['tips'])) {
                $info['tips'] = $data['tips'];
            }
            $info['domain'] = $this->getDomain($info['url']);
            $info['user_id'] = $user['user_id'];
            $info['status'] = 0;
            $info['create_time'] = date('Y-m-d H:i:s');
            if (!LinkStoreModel::where("url", $info['url'])->find()) {
                LinkStoreModel::create($info);
                return $this->success('推送完毕');
            }
        }
        return $this->error('推送失败');
    }

    private function getDomain($url)
    {
        $domain = $url;
        $p = parse_url($domain);
        if (isset($p['host'])) {
            return $p['host'];
        }
        if (isset($p['path'])) {
            return $p['path'];
        }
        return '';
    }

    public function add(): \think\response\Json
    {
        $admin = $this->getAdmin();
        is_demo_mode(true);
        $data = $this->request->post('form', []);
        if ($data) {
            try {
                unset($data['userInfo']);
            } catch (\Exception $exception) {

            }
            if (isset($data['id']) && $data['id']) { //更新
                return $this->update();
            } else {
                $data['create_time'] = date("Y-m-d H:i:s");
                $info = (new \app\model\LinkStoreModel)->create($data);
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
            $data = LinkStoreModel::whereRaw("FIND_IN_SET(?,domain)", [$url])->find();
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

    function createFolder(): \think\response\Json
    {
        is_demo_mode(true);
        $type = $this->request->post('type', false);
        $this->getAdmin();
        if ($type === 'edit') {
            $form = $this->request->post('info');
            $id = $this->request->post('info.id', false);
            if ($id && $id > 0) {
                $model = LinkFolderModel::find($id);
                $model->update($form);
            } else {
                $model = new LinkFolderModel();
                $model->create($form);
            }
        } else if ($type === 'del') {
            $id = $this->request->post('id');
            $result = LinkFolderModel::where("id", $id)->find();
            if ($result) {
                $result->delete();
                Db::query(
                    "UPDATE linkstore
                     SET area = TRIM(BOTH ',' FROM REPLACE(CONCAT(',', area, ','), ',$id,', ','))
                     WHERE FIND_IN_SET(?, area) > 0;"
                    , [$id]);
            }
        }
        return $this->success('处理完毕！');
    }
    function moveGroup(): \think\response\Json
    {
        is_demo_mode(true);
        $this->getAdmin();
        $ids = $this->request->post('link', []);
        $group_ids = $this->request->post('group_ids', '');
        LinkStoreModel::where('id', 'in', $ids)->update(['group_ids' => $group_ids]);
        return $this->success('处理完毕！');
    }
    function moveFolder(): \think\response\Json
    {
        is_demo_mode(true);
        $this->getAdmin();
        $ids = $this->request->post('link', []);
        $area = $this->request->post('area', '');
        LinkStoreModel::where('id', 'in', $ids)->update(['area' => $area]);
        return $this->success('处理完毕！');
    }

    function sortFolder(): \think\response\Json
    {
        $sort = (array)$this->request->post();
        foreach ($sort as $key => $value) {
            LinkFolderModel::where("id", $value['id'])->update(['sort' => $value['sort']]);
        }
        return $this->success("ok");
    }

    public function del(): \think\response\Json
    {
        is_demo_mode(true);
        $this->getAdmin();
        $ids = $this->request->post('ids', []);
        LinkStoreModel::where("id", 'in', $ids)->delete();
        return $this->success('删除成功');
    }

    function domains(): \think\response\Json
    {
        $domains = $this->request->post('domains', []);
        $tmp = [];
        foreach (LinkStoreModel::where('status', 1)->cursor() as $value) {
            $d = $this->getDomain($value['url']);
            if (in_array($d, $domains)) {
                $tmp[$d] = ["domain" => $d, "name" => $value['name'], "src" => $value['src'], "bgColor" => $value['bgColor'], 'tips' => $value['tips']];
            } else if ($value['domain']) {
                $r = explode(",", $value['domain']);
                foreach ($r as $v) {
                    if (in_array($v, $domains)) {
                        $tmp[$v] = ['domain' => $v, 'name' => $value['name'], 'src' => $value['src'], 'bgColor' => $value['bgColor'], 'tips' => $value['tips']];
                        break;
                    }
                }
            }
        }
        return $this->success('ok', $tmp);
    }
}
