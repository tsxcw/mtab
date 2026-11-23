<?php
namespace app\controller\apps\announcement;

use app\model\PluginsAnnouncementModel;
use app\PluginsBase;

class Index extends PluginsBase
{
    function list(): \think\response\Json
    {
        $keyword = $this->request->get("keyword", "");
        $date = $this->request->get("date", "");
        $page = $this->request->get("page", 1);
        $pageSize = $this->request->get("pageSize", 10);
        $status = $this->request->get("status", -1);

        $query = PluginsAnnouncementModel::order('top_time', 'desc')->order('create_time', 'desc');

        if ($keyword) {
            $query->where('title', 'like', "%{$keyword}%");
        }
        if ($date) {
            $query->whereDay('create_time', $date);
        }
        if ($status >= 0) {
            $query->where('status', $status);
        }
        $total = $query->count();
        $list = $query->page($page, $pageSize)->field('id,title,content,create_time,status,top_time')->select()->toArray();
        foreach ($list as &$item) {
            $item['content_summary'] = $item['content'] ? mb_substr(strip_tags($item['content']), 0, 80) : '';
            $item['is_top'] = !empty($item['top_time']);
            unset($item['content']);
        }
        return $this->success("ok", [
            'list' => $list,
            'total' => $total,
            'page' => (int)$page,
            'pageSize' => (int)$pageSize,
        ]);
    }

    function detail(): \think\response\Json
    {
        $id = $this->request->get("id", 0);
        $item = PluginsAnnouncementModel::find($id);
        if ($item) {
            if ($item['content']) {
                $item['content'] = modifyImageUrls($item['content'], request()->root(true));
            }
            $item['is_top'] = !empty($item['top_time']);
            return $this->success("ok", $item);
        }
        return $this->error("公告不存在");
    }

    function save(): \think\response\Json
    {
        $admin = $this->getAdmin();
        $id = $this->request->post("id", 0);
        $title = $this->request->post("title", "");
        $content = $this->request->post("content", "");
        $status = $this->request->post("status", 0);

        if (!$title) {
            return $this->error("标题不能为空");
        }

        if ($id) {
            $item = PluginsAnnouncementModel::find($id);
            if ($item) {
                $item->title = $title;
                $item->content = $content;
                $item->status = $status;
                $item->save();
                return $this->success('更新成功');
            }
            return $this->error("公告不存在");
        }

        PluginsAnnouncementModel::insertGetId([
            'title' => $title,
            'content' => $content,
            'status' => $status,
            'create_time' => date('Y-m-d H:i:s'),
        ]);
        return $this->success('添加成功');
    }

    function delete(): \think\response\Json
    {
        $admin = $this->getAdmin();
        $id = $this->request->post("id", 0);

        $item = PluginsAnnouncementModel::find($id);
        if ($item) {
            $item->delete();
            return $this->success('删除成功');
        }
        return $this->error("公告不存在");
    }

    function toggleStatus(): \think\response\Json
    {
        $admin = $this->getAdmin();
        $id = $this->request->post("id", 0);

        $item = PluginsAnnouncementModel::find($id);
        if ($item) {
            $item->status = $item->status ? 0 : 1;
            $item->save();
            return $this->success('状态已更新');
        }
        return $this->error("公告不存在");
    }

    function top(): \think\response\Json
    {
        $admin = $this->getAdmin();
        $id = $this->request->post("id", 0);

        $item = PluginsAnnouncementModel::find($id);
        if ($item) {
            $item->top_time = date('Y-m-d H:i:s');
            $item->save();
            return $this->success('已置顶');
        }
        return $this->error("公告不存在");
    }

    function cancelTop(): \think\response\Json
    {
        $admin = $this->getAdmin();
        $id = $this->request->post("id", 0);

        $item = PluginsAnnouncementModel::find($id);
        if ($item) {
            $item->top_time = null;
            $item->save();
            return $this->success('已取消置顶');
        }
        return $this->error("公告不存在");
    }
}
