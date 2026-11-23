<?php


namespace app\controller;


use app\BaseController;
use app\model\HistoryModel;
use app\model\LinkModel;
use app\model\TabbarModel;
use think\facade\Db;

class Tabbar extends BaseController
{
    public function update(): \think\response\Json
    {
        $user = $this->getUser(true);
        if (!$user) {
            return $this->error('保存失败');
        }
        // 请求中缺少 tabbar 字段时直接拒绝，避免参数丢失被当成“清空页脚”
        if (!$this->request->has("tabbar")) {
            return $this->error('保存失败');
        }
        $tabbar = $this->request->post("tabbar", []);
        // 防护：只接受数组，且过滤缺失 id 的脏项后再入库
        if (!is_array($tabbar)) {
            return $this->error('保存失败');
        }
        $tabs = TabbarModel::normalizeTabs($tabbar);
        // 原始数据非空但全部非法时拒绝写入，避免把用户的页脚误清空
        if ($tabs === null || (count($tabbar) > 0 && count($tabs) === 0)) {
            return $this->error('保存失败');
        }
        try {
            Db::startTrans();
            // 按更新时间取最新一条，兼容历史上产生的重复记录
            $is = TabbarModel::where("user_id", $user['user_id'])->order("update_time", "desc")->find();
            $now = date("Y-m-d H:i:s");
            $keepId = null; // 本次写入对应的记录 id，用于清理重复数据
            if ($is) {
                try {
                    // 写入历史备份（同时保存 link 与旧 tabbar 快照），供误操作恢复使用
                    $linkRow = LinkModel::where("user_id", $user['user_id'])->find();
                    HistoryModel::create([
                        'user_id' => $user['user_id'],
                        'link' => $linkRow ? $linkRow['link'] : [],
                        'tabbar' => is_array($is['tabs']) ? $is['tabs'] : [],
                        'create_time' => $now
                    ]);
                    // 仅保留最近 10 条历史，避免无限增长
                    $ids = HistoryModel::where("user_id", $user['user_id'])->order("id", "desc")->limit(10)->select()->toArray();
                    $ids = array_column($ids, "id");
                    HistoryModel::where("user_id", $user['user_id'])->whereNotIn("id", $ids)->delete();
                } catch (\Throwable $th) {
                    // 历史表结构尚未升级时忽略备份失败，不影响 tabbar 保存
                }

                $is->tabs = $tabs;
                $is->update_time = $now;
                $is->save();
                $keepId = $is['id'] ?? null;
            } else {
                // 首次写入只创建一条记录，不再走清理逻辑
                TabbarModel::create(["user_id" => $user['user_id'], "tabs" => $tabs, "update_time" => $now]);
            }
            Db::commit();

            // 清理同一账号下的历史重复记录，保证一个账号只保留一条 tabbar
            try {
                if ($keepId) {
                    TabbarModel::where("user_id", $user['user_id'])->where("id", "<>", $keepId)->delete();
                }
            } catch (\Throwable $th) {
                // 旧版本表结构没有 id 列时忽略，安装/升级后会自动补齐
            }
            return $this->success('ok');
        } catch (\Throwable $th) {
            Db::rollback();
            return $this->error('保存失败');
        }
    }

    public function get(): \think\response\Json
    {
        $user = $this->getUser();
        return $this->success('ok', TabbarModel::getTabbar($user));
    }
}
