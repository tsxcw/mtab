<?php
ini_set('max_execution_time', 0);
ini_set('memory_limit', '500M');

class Upgrade2
{
    protected $archiveFile = ""; //升级文件地址
    protected $extractPath = ""; //解压目录地址
    protected $root_path = ""; //程序根目录
    public $update_download_url = ""; //升级zip文件下载地址
    public $update_sql_url = ""; //升级sql脚本文件地址
    public $update_script = ""; //升级后执行的脚本地址
    protected $isLog = false;
    protected $noMove = [];

    //构造方法初始化一些数据
    function __construct($update_download_url = null, $update_sql_url = null, $update_script = null)
    {
        $this->archiveFile = runtime_path() . 'mtab.zip';
        $this->extractPath = runtime_path();
        $this->root_path = root_path();
        if ($update_download_url !== null) {
            $this->update_download_url = $update_download_url;
        }
        if ($update_sql_url !== null) {
            $this->update_sql_url = $update_sql_url;
        }
        if ($update_script !== null) {
            $this->update_script = $update_script;
        }
    }

    //运行入口
    function run($cli = false): bool
    {
        if ($cli) {
            $this->isLog = true;
        }
        return $this->startUpgrade();
    }

    public function log($msg)
    {
        if ($this->isLog) {
            print_r($msg . "\n");
        }
    }

    //新的进程启动升级
    private function startUpgrade(): bool
    {
        //如果有程序代码的更新资源则更新程序代码
        if (strlen($this->update_download_url) > 1) {
            //如果有遗留的解压资源则删除
            $this->log("正在检查是否有旧版本的安装包，并删除。");
            $this->deleteDirectory("{$this->extractPath}mtab");
            //如果存在旧的升级包则删除
            $this->delZip();
            //下载远程更新包
            $this->log("正在下载升级包...");
            if (!$this->fileDownload()) {
                $this->log('资源下载失败');
                abort(0, '资源下载失败');
            }
            //解压升级包
            $this->log("正在解压升级包...");
            if (!$this->unzip($this->archiveFile, $this->extractPath)) {
                $this->delZip();
                abort(0, '升级资源包解压失败');
            }
            $this->log("正在更新程序...");
            $this->deleteDirectory(public_path() . 'dist/'); //删除旧的网站文件
            $exclude = root_path() . 'exclude.txt';
            if (file_exists($exclude)) {
                try {
                    $noMove = array_map('trim', file($exclude, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
                    //逐行读取，并且去除前后多余空格
                    foreach ($noMove as $item) {
                        $this->noMove[] = joinPath(root_path(), $item);
                    }
                } catch (\Throwable $th) {
                    $this->log($th->getMessage());
                }
            }
            //拷贝覆盖
            $this->log("正在覆盖源文件");
            $this->copy();
            //删除下载的更新包
            $this->log("正在删除升级包...");
            $this->delZip();
            //更新完后的一些操作
        }
        //如果有数据库的更新资源则更新程序代码
        if (strlen($this->update_sql_url) > 1) {
            $this->log("正在更新数据库...");
            $this->updateSql();
        }
        if (file_exists("{$this->root_path}install.sql")) {
            $this->log("正在更新数据库...");
            $this->updateSql("{$this->root_path}install.sql");
        }
        if (!file_exists(root_path() . "exclude.txt")) {
            file_put_contents(root_path() . "exclude.txt", '#从程序根目录开始填写您的更新忽略的文件地址，每行一个，例如/exclude.txt');
        }

        if (function_exists('opcache_reset')) {
            //如果存在opcache_reset函数，则调用该函数清除缓存。
            opcache_reset();
        }
        return true;
    }

    private function fileDownload(): bool
    {
        $length = 0;
        try {
            $f = fopen($this->update_download_url, 'r');
            $w = fopen($this->archiveFile, 'wb+');
            $fileSize = $this->getFileSize($this->update_download_url);
            do {
                $a = fread($f, 1024 * 64);
                $length += strlen($a);
                fwrite($w, $a);
                // 计算下载进度
                $progress = ($fileSize > 0) ? round($length / $fileSize * 100, 2) : 0;
                // 打印进度条，在一行内更新
                if ($this->isLog) {
                    if ($progress <= 100) {
                        $this->printProgress((int)$progress);
                    }
                }
            } while ($a);
            fclose($w);
            fclose($f);
            $this->log("\n下载完成");
        } catch (\Throwable $e) {
            // PHP 8 下文件/网络操作的错误类型比 PHP 7 更多（ValueError、TypeError 等），统一按下载失败处理
            return false;
        }
        return true;
    }

    private function printProgress(float $progress)
    {
        try {
            $barLength = 50; // 进度条的总长度
            $completed = round($progress / 100 * $barLength); // 完成的部分
            $bar = str_repeat('=', $completed) . str_repeat(' ', max($barLength - $completed, 0)); // 拼接进度条
            echo "\r[" . $bar . "] " . $progress . "%";
        } catch (Exception $e) {
        }
    }

    private function getFileSize(string $url): int
    {
        // 使用 HEAD 请求获取文件大小
        $headers = get_headers($url, 1);
        return isset($headers['Content-Length']) ? (int)$headers['Content-Length'] : 0;
    }

    //删除升级包
    function delZip()
    {
        if (file_exists($this->archiveFile)) {
            // 删除失败不影响升级主流程，PHP 8 下未抑制的告警会被转成异常
            @unlink($this->archiveFile);
        }
    }

    //解压
    private function unzip($archiveFile, $extractPath): bool
    {
        if (class_exists("ZipArchive")) {
            $zip = new ZipArchive();
            if ($zip->open($archiveFile) === TRUE) {
                // 解压失败时不能继续覆盖程序，否则会出现“旧文件已删、新文件没到”的半更新状态
                $result = $zip->extractTo($extractPath, null);
                $zip->close();
                return $result;
            }
            return false;
        }
        //不再回退到 shell 解压：避免命令注入风险，缺少 ZipArchive 时提示安装扩展
        $this->log('未检测到 ZipArchive 扩展，无法解压升级包，请安装 php-zip 扩展后重试');
        return false;
    }

    //升级的数据库
    function updateSql($path = null)
    {
        try {
            $f = fopen($path ?: $this->update_sql_url, 'r');
        } catch (\Throwable $e) {
            // PHP 8 下 fopen 失败会抛异常（PHP 7 返回 false），统一按读取失败处理
            $this->log('SQL 文件读取失败：' . $e->getMessage());
            return;
        }
        if (!$f) {
            $this->log('SQL 文件读取失败');
            return;
        }
        $sql = "";
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
                    \think\facade\Db::execute($sql_statement);
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
        // 目录不可读时 scandir 会告警，PHP 8 下会被错误处理器转成异常，这里直接放弃删除
        $files = @scandir($dir);
        if (!is_array($files)) {
            return;
        }
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
            @mkdir($dest, 0755, true);
        }
        // 升级包里缺少 mtab 目录或目录不可读时，PHP 8 下 scandir 会抛异常并中断整个升级
        $files = @scandir($source);
        if (!is_array($files)) {
            $this->log('目录读取失败=>' . $source);
            return;
        }
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..') {
                $src = joinPath($source, '/' . $file);
                $dst = joinPath($dest, '/' . $file);
                // 检查目标路径是否在 noMove 中，并且是文件
                if (is_dir($src)) {
                    $this->copyDir($src, $dst);
                } else {
                    if (in_array($dst, $this->noMove)) {
                        $this->log('跳过=>' . $dst);
                        continue; // 跳过复制
                    }
                    // 单个文件复制失败（权限/占用）不应中断整个升级，PHP 8 下未抑制的告警会直接抛异常
                    if (!@copy($src, $dst)) {
                        $this->log('复制失败=>' . $dst);
                    }
                }
            }
        }
    }

    //覆盖原来的程序
    private function copy()
    {
        //移动覆盖
        $this->copyDir("{$this->extractPath}mtab/", "{$this->root_path}");
        //删除解压目录
        $this->deleteDirectory("{$this->extractPath}mtab");
    }
}
