<?php


namespace app\controller;

use app\BaseController;
use app\model\WallpaperModel;

class Wallpaper extends BaseController
{

    function editFolder(): \think\response\Json
    {
        $this->getAdmin();
        is_demo_mode(true);
        $data = $this->request->post();
        if (isset($data['id']) && strlen($data['id']) > 0) {
            $mode = WallpaperModel::find($data['id']);
        } else {
            $mode = new WallpaperModel();
        }
        $mode->name = $data['name'];
        $mode->type = 1;
        $mode->save();
        $list = WallpaperModel::where("type", 1)->field("id,name,type,sort,create_time")->order("sort")->select();
        return $this->success("处理完毕", $list);
    }

    function DelFolder(): \think\response\Json
    {
        $this->getAdmin();
        $id = $this->request->post("id");
        if ($id) {
            $find = WallpaperModel::where("id", $id)->find();
            if (!$find) {
                return $this->error("分类不存在！");
            }
        }
        $find->delete();
        $list = WallpaperModel::where("type", 0)->where("folder", $id)->select()->toArray();
        foreach ($list as $key => $value) {
            //删除资源
            $url = joinPath(public_path(), $value['url']);
            if (file_exists($url)) {
                try {
                    unlink($url);
                } catch (\Throwable $th) {
                }
            }
            $cover = joinPath(public_path(), $value['cover']);
            if (file_exists($cover)) {
                try {
                    unlink($cover);
                } catch (\Throwable $th) {
                }
            }
        }
        WallpaperModel::where("type", 0)->where("folder", $id)->delete(); //删除数据库数据;
        $list = WallpaperModel::where("type", 1)->field("id,name,type,sort,create_time")->order("sort")->select();
        return $this->success("ok", $list);
    }

    function getFolder(): \think\response\Json
    {
        $this->getAdmin();
        $list = WallpaperModel::where("type", 1)->field("id,name,type,sort,create_time")->order("sort")->select();
        return $this->success("ok", $list);
    }

    function getFolderClient(): \think\response\Json
    {
        $list = WallpaperModel::where("type", 1)->field("id,name,type,sort,create_time")->order("sort")->select();
        return $this->success("ok", $list);
    }

    function getFolderWallpaper()
    {
        $this->getAdmin();
        $folder_id = $this->request->post("id");
        if ($folder_id) {
            $list = WallpaperModel::where('type', 0)->where("folder", $folder_id)->order("create_time", 'desc')->paginate($this->request->post("limit", 19));
            return $this->success("ok", $list);
        }
    }

    function getFolderWallpaperClient()
    {
        $folder_id = $this->request->post("id");
        $offset = $this->request->post("offset", 0);
        if ($folder_id) {
            $list = WallpaperModel::where("type", 0)->where("folder", $folder_id)->field("create_time,id,folder,cover,type,mime,url")->order("id", 'desc')->limit($offset * 30, 30)->select();
            return $this->success("ok", $list);
        }
    }

    function deleteWallpaper()
    {
        $this->getAdmin();
        $id = $this->request->post("id");
        if ($id) {
            $find = WallpaperModel::where("id", $id)->find();
            if ($find) {
                $find->delete();
                //删除资源
                $url = joinPath(public_path(), $find['url']);
                if (file_exists($url)) {
                    try {
                        unlink($url);
                    } catch (\Throwable $th) {
                    }
                }
                $cover = joinPath(public_path(), $find['cover']);
                if (file_exists($cover)) {
                    try {
                        unlink($cover);
                    } catch (\Throwable $th) {
                    }
                }
            }
            return $this->success("ok");
        }
    }

    function addWallpaper(): \think\response\Json
    {
        $this->getAdmin();
        $data = [];
        $data['cover'] = $this->request->post("cover");
        $data['url'] = $this->request->post("url");
        $data['type'] = $this->request->post("type");
        $data['mime'] = $this->request->post("mime");
        $data['folder'] = $this->request->post("folder");
        $id = $this->request->post("id");
        if ($id) {
            $res = WallpaperModel::where("id", $id)->find();
            if ($res) {
                $res->save($data);
            }
        } else {
            $maxId = WallpaperModel::max('id');
            $data['id'] = $maxId ? $maxId + 1 : 1;
            $res = WallpaperModel::create($data);
        }
        return $this->success("ok", $res);
    }

    function randomWallpaper(): \think\response\Json
    {
        $list = WallpaperModel::where("type",0)->field('id, mime, url')
            ->orderRaw('RAND()')
            ->find();
        return $this->success("ok", $list);
    }

    function sortFolder(): \think\response\Json
    {
        $this->getAdmin();
        $sort = (array)$this->request->post();
        foreach ($sort as $key => $value) {
            WallpaperModel::where("id", $value['id'])->update(['sort' => $value['sort']]);
        }
        return $this->success("ok");
    }
}
