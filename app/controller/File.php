<?php

namespace app\controller;
ini_set('max_execution_time', 300);

use app\BaseController;
use app\model\FileModel;
use think\facade\Db;

class File extends BaseController
{
    private $files = [];

    public function list(): \think\response\Json
    {
        $this->getAdmin();
        $limit = $this->request->post('limit', 15);
        $name = $this->request->post('search.path', false);
        $user_id = $this->request->post("search.user_id",false);
        $sql = [];
        if ($name) {
            $sql[] = ['mime_type|path', 'like', '%' . $name . '%'];
        }
        if($user_id){
            $sql["user_id"] = $user_id;
        }
        $list = FileModel::with("user")->where($sql);
        $list = $list->order('id', 'desc')->paginate($limit);
        return $this->success('ok', $list);
    }

    function del(): \think\response\Json
    {
        is_demo_mode(true);
        $this->getAdmin();
        $ids = $this->request->post('ids', []);
        $res = FileModel::where('id', 'in', $ids)->select()->toArray();
        foreach ($res as $k => $v) {
            $p = joinPath(public_path(), $v['path']);
            if (file_exists($p)) {
                @unlink($p);
            }
            FileModel::where('id', $v['id'])->delete();
        }
        return $this->success('删除成功');
    }

    private function countFilesInDirectory($directory): int
    {
        $fileCount = 0;
        // 获取目录中的文件和子目录
        $files = scandir($directory);
        foreach ($files as $file) {
            // 排除"."和".."
            if ($file != '.' && $file != '..') {
                $filePath = $directory . '/' . $file;
                // 如果是目录，则递归调用函数
                if (is_dir($filePath)) {
                    $fileCount += $this->countFilesInDirectory($filePath);
                } else {
                    // 如果是文件，则增加文件数量
                    $this->files[] = joinPath("/", $filePath);
                    $fileCount++;
                }
            }
        }

        return $fileCount;
    }

    /**
     * 清理重复入库记录：同一 path 只保留一条，优先保留带 user_id 的，
     * 同优先级保留 id 最小的。只删数据库记录，不动磁盘文件。
     * 单条 SQL 实现：内层 GROUP BY 算出每组保留 id，外层包装一层绕开
     * MySQL 1093（DELETE 目标表不能直接出现在子查询中）的限制。
     */
    private function cleanDuplicateRecords(): void
    {
        $table = FileModel::getTable();
        // 归一化 path：反斜杠转正斜杠、去掉前导斜杠，兼容历史数据里的多种写法
        $norm = "TRIM(LEADING '/' FROM REPLACE(path, '\\\\', '/'))";
        Db::execute("DELETE FROM {$table} WHERE id NOT IN (
            SELECT keep_id FROM (
                SELECT IFNULL(MIN(CASE WHEN user_id IS NOT NULL THEN id END), MIN(id)) AS keep_id
                FROM {$table}
                GROUP BY {$norm}
            ) tmp
        )");
    }

    function scanLocal(): \think\response\Json
    {
        is_demo_mode(true);
        $this->getAdmin();
        if (!is_dir(public_path("images"))) {
            return $this->success('扫描完成');
        }
        $this->countFilesInDirectory("images");
        $this->cleanDuplicateRecords();
        // 路径转为哈希表，O(1) 查找，代替 array_search 的 O(n) 逐条查找
        $fileMap = array_flip($this->files);
        // 分批读取数据库记录，不限制数量，避免内存溢出
        // 统一成 "/images/x" 格式再匹配，兼容库中 images、/images、//images、反斜杠等写法
        FileModel::chunk(5000, function ($list) use (&$fileMap) {
            foreach ($list as $v) {
                $path = '/' . ltrim(str_replace('\\', '/', $v['path']), '/');
                unset($fileMap[$path]);
            }
        });
        $this->files = array_keys($fileMap);
        // 分批计算元数据并批量入库，兼顾内存与插入速度
        $all = [];
        foreach ($this->files as $v) {
            $p = joinPath(public_path(), $v);
            $all[] = [
                'path'        => $v,
                'user_id'     => null,
                'create_time' => date('Y-m-d H:i:s'),
                'size'        => filesize($p) ?: 0,
                'hash'        => hash_file('md5', $p) ?: '',
                'mime_type'   => FileModel::detectMimeType($p),
            ];
            if (count($all) >= 2000) {
                FileModel::insertAll($all);
                $all = [];
            }
        }
        if ($all) {
            FileModel::insertAll($all);
        }
        return $this->success("扫描完成");
    }
}
