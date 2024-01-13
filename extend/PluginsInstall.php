<?php

ini_set('max_execution_time', 0);
ini_set('memory_limit', '500M');

class PluginsInstall
{
    protected string $archiveFile = '';//升级文件地址
    protected string $extractPath = '';//解压目录地址
    protected string $root_path = '';//程序根目录
    public string $download = '';//升级zip文件下载地址
    protected string $directory = '';//插件目录名称
    protected string $update_sql = '';//升级sql文件地址

    //构造方法初始化一些数据
    function __construct($info)
    {
        $this->archiveFile = runtime_path() . $info['name_en'] . '.zip';
        $this->extractPath = runtime_path();
        $this->root_path = root_path() . 'plugins/';
        $this->download = $info['download'];
        $this->directory = $info['name_en'];
        if (isset($info['update_sql']) && $info['update_sql']) {
            $this->update_sql = $info['update_sql'];
        }
    }

    //运行入口
    function run()
    {
        return $this->startUpgrade();
    }

    //新的进程启动升级
    private function startUpgrade()
    {
        //如果有程序代码的更新资源则更新程序代码
        if (strlen($this->download) > 1) {
            //如果有遗留的解压资源则删除
            $this->deleteDirectory("{$this->extractPath}{$this->directory}");
            //如果存在旧的升级包则删除
            $this->delZip();
            //下载远程更新包
            if (!$this->fileDownload()) {
                return '资源下载失败';
            }
            //解压升级包
            if (!$this->unzip($this->archiveFile, $this->extractPath)) {
                $this->delZip();
                return '升级资源包解压失败';
            }
            //拷贝覆盖
            $this->copy();
            //删除下载的更新包
            $this->delZip();
            //更新完后的一些操作
            if (mb_strlen($this->update_sql) > 0) {
                $this->updateSql();
            }
        }
        //退出
        return true;
    }

    private function fileDownload(): bool
    {
        try {
            $f = fopen($this->download, 'r');
            $w = fopen($this->archiveFile, 'wb+');
            do {
                $a = fread($f, 1024);
                fwrite($w, $a);
            } while ($a);
            fclose($w);
            fclose($f);
        } catch (ErrorException $e) {
            return false;
        }
        return true;
    }

    //删除升级包
    function delZip()
    {
        if (file_exists($this->archiveFile)) {
            unlink($this->archiveFile);
        }
    }

    //解压
    private function unzip($archiveFile, $extractPath): bool
    {
        $zip = new ZipArchive();
        if ($zip->open($archiveFile) === TRUE) {
            $zip->extractTo($extractPath, null);
            $zip->close();
        } else {
            return false;
        }
        return true;
    }

    //升级的数据库
    function updateSql()
    {
        $f = fopen($this->update_sql, 'r');
        $sql = '';
        do {
            $sqlTmp = fread($f, 1024);
            $sql = $sql . $sqlTmp;
        } while ($sqlTmp);
        fclose($f);
        // 解析SQL文件内容并执行
        $sql_statements = explode(';', trim($sql));
        foreach ($sql_statements as $sql_statement) {
            if (!empty($sql_statement)) {
                try {
                    \think\facade\Db::query($sql_statement);
                } catch (Exception $e) {

                }
            }
        }
    }

    //递归删除目录
    function deleteDirectory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file != '.' && $file != '..') {
                if (is_dir("$dir/$file")) {
                    $this->deleteDirectory("$dir/$file");
                } else {
                    unlink("$dir/$file");
                }
            }
        }
        rmdir($dir);
    }

    // 递归复制目录及其内容
    function copyDir($source, $dest)
    {
        if (!is_dir($dest)) {
            mkdir($dest, 0777, true);
        }
        $files = scandir($source);
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..') {
                $src = $source . '/' . $file;
                $dst = $dest . '/' . $file;
                if (is_dir($src)) {
                    $this->copyDir($src, $dst);
                } else {
                    copy($src, $dst);
                }
            }
        }
    }

    //覆盖原来的程序
    private function copy()
    {
        //移动覆盖
        $this->copyDir("{$this->extractPath}{$this->directory}/", "{$this->root_path}{$this->directory}");
        //删除解压目录
        $this->deleteDirectory("{$this->extractPath}{$this->directory}");
    }
}