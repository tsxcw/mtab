<?php

namespace app\controller;

use app\BaseController;
use app\model\ConfigModel;
use app\model\HistoryModel;
use app\model\LinkModel;
use app\model\TabbarModel;
use app\model\UserSearchEngineModel;
use think\facade\Cache;
use think\facade\Db;

class Link extends BaseController
{
    public function update(): \think\response\Json
    {
        is_demo_mode(true);
        $user = $this->getUser(true);
        try {
            if ($user) {
                $link = $this->request->post("link", []);
                if (is_array($link)) {
                    $is = LinkModel::where("user_id", $user['user_id'])->find();
                    if ($is) {
                        try {
                            // 备份时同时记录当前的 tabbar 快照，恢复历史时可一并还原页脚
                            $tabbarRow = TabbarModel::where("user_id", $user['user_id'])->order("update_time", "desc")->find();
                            HistoryModel::create([
                                'user_id' => $user['user_id'],
                                'link' => $is['link'],
                                'tabbar' => $tabbarRow && is_array($tabbarRow['tabs']) ? $tabbarRow['tabs'] : [],
                                'create_time' => date("Y-m-d H:i:s")
                            ]); //历史记录备份,用于用户误操作恢复用途
                            $ids = HistoryModel::where("user_id", $user['user_id'])->order("id", 'desc')->limit(10)->select()->toArray();
                            $ids = array_column($ids, "id");
                            HistoryModel::where("user_id", $user['user_id'])->whereNotIn("id", $ids)->delete();
                        } catch (\Throwable $th) {
                            // 历史表结构尚未升级时忽略备份失败，不影响书签保存
                        }
                        $is->link = $link;
                        $is->save();
                    } else {
                        LinkModel::create(["user_id" => $user['user_id'], "link" => $link]);
                    }

                    // 清理已删除标签的备忘录：提取新 link 数组中所有标签 id
                    $this->cleanOrphanMemos($user['user_id'], $link);

                    Cache::delete("Link.{$user['user_id']}");
                    return $this->success('ok');
                }
            }
        } catch (\Throwable $th) {
            // 不向客户端泄露异常原始信息
        }
        return $this->error('保存失败');
    }

    /**
     * 清理孤儿备忘录：删除 link 数组中不存在的标签对应的备忘录记录
     * @param int $userId 用户 id
     * @param array $link 新的 link 数组
     */
    private function cleanOrphanMemos(int $userId, array $link): void
    {
        // 提取新 link 数组中所有标签 id
        $newIds = array_column($link, 'id');

        // 查询该用户所有备忘录记录，只取 id 和 link_id
        $memos = Db::table('link_memo')
            ->where('user_id', $userId)
            ->field('id, link_id')
            ->select()
            ->toArray();

        $orphanIds = [];
        foreach ($memos as $memo) {
            if (!in_array($memo['link_id'], $newIds)) {
                $orphanIds[] = $memo['id'];
            }
        }

        // 批量删除孤儿备忘录
        if (!empty($orphanIds)) {
            Db::table('link_memo')
                ->where('user_id', $userId)
                ->whereIn('id', $orphanIds)
                ->delete();
        }
    }

    public function updateOne(): \think\response\Json
    {
        $user = $this->getUser(true);
        $error = "";
        try {
            if ($user) {
                $id = $this->request->post("id", '');
                $appData = $this->request->post("appData", []);
                if (!$id) {
                    return $this->error('缺少 id 参数');
                }
                $is = LinkModel::where("user_id", $user['user_id'])->find();
                if ($is) {
                    $link = $is['link'];
                    if (is_array($link)) {
                        $found = false;
                        foreach ($link as &$item) {
                            if (isset($item['id']) && $item['id'] === $id) {
                                $item['appData'] = $appData;
                                $found = true;
                                break;
                            }
                        }
                        unset($item);
                        if ($found) {
                            $is->link = $link;
                            $is->save();
                            Cache::delete("Link.{$user['user_id']}");
                            return $this->success('ok');
                        }
                        return $this->error('未找到对应数据');
                    }
                }
            }
        } catch (\Throwable $th) {
            $error = $th->getMessage();
        }
        return $this->error('保存失败' . $error);
    }

    public function get(): \think\response\Json
    {
        $user = $this->getUser();
        return $this->success("ok", LinkModel::getLink($user));
    }

    function refreshWebAppCache(): \think\response\Json
    {
        $this->getAdmin();
        Cache::tag('linkCache')->clear();
        return $this->success('刷新完毕');
    }

    public function history(): \think\response\Json
    {
        $user = $this->getUser(true);
        $history = HistoryModel::where("user_id", $user['user_id'])->whereNotNull("create_time")->field('id,user_id,create_time')->limit(100)->order("id", "desc")->select();
        return $this->success('ok', $history);
    }

    public function delBack(): \think\response\Json
    {
        $user = $this->getUser(true);
        $id = $this->request->post('id');
        if ($id) {
            $res = HistoryModel::where('id', $id)->where('user_id', $user['user_id'])->delete();
            if ($res) {
                return $this->success('ok');
            }
        }
        return $this->error('备份节点不存在');
    }

    public function rollBack(): \think\response\Json
    {
        $user = $this->getUser(true);
        $id = $this->request->post("id");
        if ($id) {
            $res = HistoryModel::where('id', $id)->where("user_id", $user['user_id'])->find();
            if ($res) {
                $link = $res['link'];
                Cache::delete("Link.{$user['user_id']}");
                LinkModel::update(["user_id" => $user['user_id'], "link" => $link]);
                // 备份中包含 tabbar 快照时一并恢复，保证标签与页脚数据保持一致
                $tabbar = $res['tabbar'];
                if (is_array($tabbar)) {
                    $tab = TabbarModel::where("user_id", $user['user_id'])->order("update_time", "desc")->find();
                    if ($tab) {
                        $tab->tabs = $tabbar;
                        $tab->update_time = date("Y-m-d H:i:s");
                        $tab->save();
                    } else {
                        TabbarModel::create([
                            "user_id" => $user['user_id'],
                            "tabs" => $tabbar,
                            "update_time" => date("Y-m-d H:i:s")
                        ]);
                    }
                }
                return $this->success('ok');
            }
        }
        return $this->error("备份节点不存在");
    }

    public function reset(): \think\response\Json
    {
        $user = $this->getUser();
        if ($user) {
            $data = LinkModel::find($user['user_id']);
            if ($data) {
                Cache::delete("Link.{$user['user_id']}");
                $data->delete();
            }
            // 删除页脚数据：按 user_id 批量删除，顺带清理历史遗留的重复记录
            TabbarModel::where("user_id", $user['user_id'])->delete();
            $data = ConfigModel::find($user['user_id']);
            if ($data) {
                $data->delete();
            }
            $data = UserSearchEngineModel::find($user['user_id']);
            if ($data) {
                $data->delete();
            }
        }
        return $this->success('ok');
    }

    /**
     * 获取标签备忘录内容
     * @return \think\response\Json
     */
    public function getMemo(): \think\response\Json
    {
        $user = $this->getUser(true);
        try {
            if ($user) {
                $linkId = $this->request->post("id", '');
                if (!$linkId) {
                    return $this->error('缺少 id 参数');
                }

                // 查询用户的 link 数据，校验该标签 id 是否属于当前用户，防止越权
                $linkRecord = LinkModel::where("user_id", $user['user_id'])->find();
                if (!$linkRecord) {
                    return $this->error('用户数据不存在');
                }

                $link = $linkRecord->link ?? [];
                $found = false;
                if (is_array($link)) {
                    foreach ($link as $item) {
                        if (isset($item['id']) && $item['id'] === $linkId) {
                            $found = true;
                            break;
                        }
                    }
                }

                if (!$found) {
                    return $this->error('标签不存在或无权操作');
                }

                $memo = Db::table('link_memo')
                    ->where('user_id', $user['user_id'])
                    ->where('link_id', $linkId)
                    ->find();

                return $this->success('ok', $memo ? $memo['content'] : '');
            }
        } catch (\Throwable $th) {
            // 不向客户端泄露异常原始信息
        }
        return $this->error('获取失败');
    }

    /**
     * 保存/更新/删除标签备忘录
     * 提交内容为空且存在记录时删除，否则新增或修改
     * @return \think\response\Json
     */
    public function saveMemo(): \think\response\Json
    {
        $user = $this->getUser(true);
        try {
            if ($user) {
                $linkId = $this->request->post("id", '');
                $content = $this->request->post("content", '');

                if (!$linkId) {
                    return $this->error('缺少 id 参数');
                }

                // 服务端校验内容长度，防止绕过前端限制
                if (mb_strlen($content) > 2000) {
                    return $this->error('备忘录内容不能超过2000字');
                }

                // 查询用户的 link 数据，校验该标签 id 是否属于当前用户，防止越权
                $linkRecord = LinkModel::where("user_id", $user['user_id'])->find();
                if (!$linkRecord) {
                    return $this->error('用户数据不存在');
                }

                $link = $linkRecord->link ?? [];
                $found = false;
                if (is_array($link)) {
                    foreach ($link as $item) {
                        if (isset($item['id']) && $item['id'] === $linkId) {
                            $found = true;
                            break;
                        }
                    }
                }

                if (!$found) {
                    return $this->error('标签不存在或无权操作');
                }

                $userId = $user['user_id'];
                $existing = Db::table('link_memo')
                    ->where('user_id', $userId)
                    ->where('link_id', $linkId)
                    ->find();

                // 提交内容为空且已有记录，直接删除
                if ($content === '' && $existing) {
                    Db::table('link_memo')
                        ->where('id', $existing['id'])
                        ->delete();
                    return $this->success('ok');
                }

                // 提交内容不为空，新增或更新
                if ($existing) {
                    Db::table('link_memo')
                        ->where('id', $existing['id'])
                        ->update([
                            'content' => $content,
                            'update_time' => date("Y-m-d H:i:s"),
                        ]);
                } else {
                    Db::table('link_memo')->insert([
                        'user_id' => $userId,
                        'link_id' => $linkId,
                        'content' => $content,
                        'create_time' => date("Y-m-d H:i:s"),
                        'update_time' => date("Y-m-d H:i:s"),
                    ]);
                }

                return $this->success('ok');
            }
        } catch (\Throwable $th) {
            // 不向客户端泄露异常原始信息
        }
        return $this->error('保存失败');
    }
}
