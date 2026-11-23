<?php
/*
 * @description: Web 端数据备份/恢复管理
 * @Date: 2026-08-12
 *
 * FPM 环境约束（max_execution_time=30 / memory_limit=128M）：
 * 备份/恢复均采用「主请求立即返回 + fastcgi_finish_request() 后台执行 + 前端轮询状态」模式，
 * 后台任务 set_time_limit(0) 不受 30s 限制；导出复用 MysqlDump 流式查询，导入采用流式读 SQL + 分句执行，内存占用可控。
 */

declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use DirectoryIterator;
use ZipArchive;

class Backup extends BaseController
{
    private array $ignore = [];

    // ==================== 备份安全认证 ====================

    /**
     * 确保 PHP Session 已启动（Session Cookie 默认浏览器关闭即失效）
     */
    private function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * 备份密码存储文件路径（存放在 backup 目录下，避免 runtime 被清理时丢失）
     */
    private function passwordFile(): string
    {
        $dir = root_path() . 'backup';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        return $dir . DIRECTORY_SEPARATOR . 'backup_auth.json';
    }

    /**
     * 校验备份密码强度：至少 8 位，必须包含字母和数字
     * @param string $password 待校验密码
     * @return string 错误信息，空串表示通过
     */
    private function validatePasswordStrength(string $password): string
    {
        if (strlen($password) < 8) {
            return '密码长度不能少于 8 位';
        }
        if (!preg_match('/[a-zA-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
            return '密码必须同时包含字母和数字';
        }
        return '';
    }

    /**
     * 校验备份操作权限：密码未设置则要求设置，未登录则要求登录
     * @return \think\response|null 已认证返回 null，未认证返回错误响应
     */
    private function checkAuth(): ?\think\response\Json
    {
        $this->ensureSession();

        $hasPassword = is_file($this->passwordFile());
        if (!$hasPassword) {
            return $this->error('请先设置备份安全密码', ['auth' => 'need_set_password']);
        }

        if (empty($_SESSION['backup_auth'])) {
            return $this->error('请输入备份安全密码', ['auth' => 'need_login']);
        }

        // 在后台任务前释放 session 锁，避免阻塞其他请求
        session_write_close();
        return null;
    }

    // ==================== 查询接口 ====================

    /**
     * 备份文件列表 + 当前任务状态
     */
    function list(): \think\response\Json
    {
        $this->getAdmin();
        $auth = $this->checkAuth();
        if ($auth) return $auth;
        $files = [];
        foreach ($this->getBackupFiles() as $file) {
            $basename = basename($file);
            $files[] = [
                'name'      => $basename,
                'type'      => preg_match('/\.sql$/i', $basename) ? 'database' : 'app',
                'size'      => filesize($file),
                'size_text' => $this->formatSize(filesize($file)),
                'time'      => date('Y-m-d H:i:s', filemtime($file)),
            ];
        }
        return $this->success('ok', [
            'files'      => $files,
            'status'     => $this->readStatus(),
            'backup_dir' => root_path() . 'backup',
        ]);
    }

    /**
     * 任务状态（前端轮询）
     * 注意：不校验登录态。恢复数据库过程中 user/token 表会被重建，此时校验会导致「请登录」误报。
     */
    function status(): \think\response\Json
    {
        return $this->success('ok', $this->readStatus());
    }

    /**
     * 查询认证状态：密码是否已设置、当前是否已登录
     */
    function authStatus(): \think\response\Json
    {
        $this->ensureSession();
        $hasPassword = is_file($this->passwordFile());
        $authenticated = !empty($_SESSION['backup_auth']) && $hasPassword;
        return $this->success('ok', [
            'password_set'  => $hasPassword,
            'authenticated' => $authenticated,
        ]);
    }

    /**
     * 设置/修改备份安全密码
     * 首次设置无需 old_password；修改时需验证旧密码
     */
    function setPassword(): \think\response\Json
    {
        $this->ensureSession();
        is_demo_mode(true);

        $password = (string)$this->request->post('password', '');
        $errMsg = $this->validatePasswordStrength($password);
        if ($errMsg !== '') {
            return $this->error($errMsg);
        }

        $file = $this->passwordFile();

        // 已有密码时，需验证旧密码
        if (is_file($file)) {
            $oldPassword = (string)$this->request->post('old_password', '');
            $data = json_decode((string)file_get_contents($file), true);
            if (!password_verify($oldPassword, $data['hash'] ?? '')) {
                return $this->error('原密码错误');
            }
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        @file_put_contents($file, json_encode(['hash' => $hash]), LOCK_EX);

        // 设置成功后自动登录
        $_SESSION['backup_auth'] = true;
        return $this->success('密码设置成功');
    }

    /**
     * 备份安全密码登录（Session Cookie 默认浏览器关闭即失效）
     */
    function login(): \think\response\Json
    {
        $this->ensureSession();

        $password = (string)$this->request->post('password', '');
        $file = $this->passwordFile();
        if (!is_file($file)) {
            return $this->error('请先设置备份安全密码', ['auth' => 'need_set_password']);
        }

        $data = json_decode((string)file_get_contents($file), true);
        if (!password_verify($password, $data['hash'] ?? '')) {
            return $this->error('密码错误');
        }

        $_SESSION['backup_auth'] = true;
        return $this->success('登录成功');
    }

    /**
     * 退出备份认证
     */
    function logout(): \think\response\Json
    {
        $this->ensureSession();
        unset($_SESSION['backup_auth']);
        session_destroy();
        return $this->success('已退出');
    }

    // ==================== 备份 ====================

    /**
     * 发起备份任务（后台执行，立即返回）
     * @param array $types 备份类型列表：database(数据库) / app(程序文件)，可多选
     */
    function start(): \think\response\Json
    {
        $this->getAdmin();
        is_demo_mode(true);
        $auth = $this->checkAuth();
        if ($auth) return $auth;

        $types = $this->request->post('types', []);
        if (!is_array($types)) {
            $types = $types ? [$types] : [];
        }
        $types = array_values(array_intersect($types, ['database', 'app']));
        if (empty($types)) {
            return $this->error('请至少选择一种备份类型');
        }

        $lockFp = $this->tryLock();
        if ($lockFp === false) {
            return $this->error('已有备份/恢复任务正在运行，请稍候');
        }

        $taskId = uuid();
        $this->writeStatus([
            'task_id'    => $taskId,
            'running'    => true,
            'type'       => 'backup',
            'types'      => $types,
            'step'       => 'init',
            'percent'    => 0,
            'message'    => '备份任务已启动',
            'file'       => '',
            'error'      => '',
            'start_time' => time(),
        ]);

        // 立即返回响应，后台继续执行
        $this->respondJson(['code' => 1, 'msg' => '备份任务已启动', 'data' => ['task_id' => $taskId]]);
        $this->prepareBackground();

        try {
            $this->doBackup($types);
        } catch (\Throwable $e) {
            $this->writeStatus([
                'running' => false,
                'step'    => 'error',
                'percent' => 0,
                'message' => '备份失败',
                'error'   => $e->getMessage(),
            ]);
        } finally {
            flock($lockFp, LOCK_UN);
            fclose($lockFp);
        }
        exit;
    }

    /**
     * 执行备份任务
     */
    private function doBackup(array $types): void
    {
        
        $this->initIgnore();
        $date = date('YmdHis');
        $backupDir = root_path() . 'backup';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0777, true);
        }

        if (in_array('database', $types)) {
            $sqlName = "mTabBackupMysql-{$date}.sql";
            $this->writeStatus(['step' => 'database', 'percent' => 1, 'message' => '正在导出数据库...', 'file' => $sqlName]);
            $this->mysqlDump($backupDir . '/' . $sqlName);
        }

        if (in_array('app', $types)) {
            $zipName = "mTabBackupApp-{$date}.zip";
            $this->writeStatus(['step' => 'app', 'percent' => 55, 'message' => '正在打包程序文件...', 'file' => $zipName]);
            $this->fileZip($backupDir . '/' . $zipName);
        }

        $this->writeStatus([
            'running' => false,
            'step'    => 'done',
            'percent' => 100,
            'message' => '备份完成',
            'file'    => '',
            'error'   => '',
        ]);
    }

    /**
     * 使用 MySQLDumpUtil 库导出数据库
     * 库内部使用 MYSQLI_USE_RESULT 流式读取，内存占用可控
     * @param string $savePath SQL 文件保存路径
     */
    private function mysqlDump(string $savePath): void
    {
        $host     = env("DATABASE.HOSTNAME");
        $user     = env("DATABASE.USERNAME");
        $password = env("DATABASE.PASSWORD");
        $database = env("DATABASE.DATABASE");
        $port     = (int)env("DATABASE.HOSTPORT");

        $conn = new \mysqli($host, $user, $password, $database, $port);
        if ($conn->connect_error) {
            throw new \Exception("数据库连接失败: " . $conn->connect_error);
        }

        $dump = new \MysqlDumper($conn);
        $this->writeStatus(['percent' => 5, 'message' => '正在导出数据库...']);
        $dump->save($savePath);
        $conn->close();
        $this->writeStatus(['percent' => 55, 'message' => '数据库导出完成']);
    }

    private function fileZip(string $zipPath): void
    {
        if (!extension_loaded('zip')) {
            throw new \Exception('系统未安装或开启zip扩展，无法备份程序文件');
        }
        $files = $this->scanFiles(root_path());
        $total = count($files);
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \Exception("无法创建备份文件: {$zipPath}");
        }
        foreach ($files as $i => $file) {
            $zip->addFile($file, $this->getRelativePath($file));
            $done = $i + 1;
            if ($done % 50 === 0 || $done === $total) {
                // 程序打包占整体进度 55~100%
                $this->writeStatus([
                    'percent' => 55 + intval($done / $total * 45),
                    'message' => "正在打包程序文件 ({$done}/{$total})",
                ]);
            }
        }
        $zip->close();
    }

    // ==================== 恢复 ====================

    /**
     * 发起恢复任务（后台执行，立即返回）
     * @param string $file 备份文件名（自动按扩展名识别 .sql 数据库 / .zip 程序文件）
     */
    function restore(): \think\response\Json
    {
        $this->getAdmin();
        is_demo_mode(true);
        $auth = $this->checkAuth();
        if ($auth) return $auth;

        $name = basename((string)$this->request->post('file', ''));
        if (!preg_match('/^[\w\-\.\(\)]+\.(sql|zip)$/iu', $name)) {
            return $this->error('备份文件不合法');
        }
        $filePath = root_path() . 'backup/' . $name;
        if (!is_file($filePath)) {
            return $this->error('备份文件不存在');
        }

        $lockFp = $this->tryLock();
        if ($lockFp === false) {
            return $this->error('已有备份/恢复任务正在运行，请稍候');
        }

        $taskId = uuid();
        $this->writeStatus([
            'task_id'    => $taskId,
            'running'    => true,
            'type'       => 'restore',
            'step'       => 'init',
            'percent'    => 0,
            'message'    => '恢复任务已启动',
            'file'       => $name,
            'error'      => '',
            'start_time' => time(),
        ]);

        $this->respondJson(['code' => 1, 'msg' => '恢复任务已启动', 'data' => ['task_id' => $taskId]]);
        $this->prepareBackground();

        try {
            if (preg_match('/\.sql$/i', $name)) {
                $this->restoreDatabase($filePath);
            } else {
                $this->restoreFiles($filePath);
            }
            $this->writeStatus([
                'running' => false,
                'step'    => 'done',
                'percent' => 100,
                'message' => '恢复完成',
                'file'    => $name,
                'error'   => '',
            ]);
        } catch (\Throwable $e) {
            $this->writeStatus([
                'running' => false,
                'step'    => 'error',
                'percent' => 0,
                'message' => '恢复失败',
                'error'   => $e->getMessage(),
            ]);
        } finally {
            flock($lockFp, LOCK_UN);
            fclose($lockFp);
        }
        exit;
    }

    /**
     * 使用 MySQLImport 库导入 SQL 文件
     * 库内部逐行读取并按分号切分执行，内存占用可控
     * @param string $filePath SQL 文件路径
     */
    private function restoreDatabase(string $filePath): void
    {
        set_time_limit(0);

        $host     = env("DATABASE.HOSTNAME");
        $user     = env("DATABASE.USERNAME");
        $password = env("DATABASE.PASSWORD");
        $database = env("DATABASE.DATABASE");
        $port     = (int)env("DATABASE.HOSTPORT");

        $conn = new \mysqli($host, $user, $password, $database, $port);
        if ($conn->connect_error) {
            throw new \Exception("数据库连接失败: " . $conn->connect_error);
        }
        $conn->set_charset('utf8mb4');

        $import = new \MysqlImporter($conn);
        // 进度回调：通过已执行语句数和文件百分比更新状态
        $import->onProgress = function (int $count, ?float $percent): void {
            $this->writeStatus([
                'percent' => $percent !== null ? min(99, (int)$percent) : 50,
                'message' => "正在导入数据... ({$count} 条语句)",
            ]);
        };
        $import->load($filePath);
        $conn->close();
    }

    /**
     * 恢复程序文件（跳过 runtime/backup/web/.git 等目录，与 CLI 一致）
     */
    private function restoreFiles(string $filePath): void
    {
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new \Exception('无法打开 zip 备份文件');
        }
        $excludeDirs = ['runtime', 'backup', '.git', '.idea', 'web', '.env', 'public/installed.lock'];
        $total = $zip->numFiles;
        $restored = 0;

        for ($i = 0; $i < $total; $i++) {
            $name = $zip->getNameIndex($i);
            $done = $i + 1;

            // 跳过排除目录下的文件
            $skip = false;
            foreach ($excludeDirs as $dir) {
                if ($name === $dir || strpos($name, $dir . '/') === 0 || strpos($name, $dir . '\\') === 0) {
                    $skip = true;
                    break;
                }
            }
            if ($skip || substr($name, -1) === '/') {
                continue;
            }

            $targetPath = root_path() . $name;
            $dir = dirname($targetPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            $content = $zip->getFromIndex($i);
            if ($content !== false) {
                file_put_contents($targetPath, $content);
                $restored++;
            }

            $this->writeStatus([
                'percent' => intval($done / $total * 100),
                'message' => "正在恢复文件 ({$done}/{$total})",
            ]);
        }

        $zip->close();
    }

    // ==================== 下载 / 删除 ====================

    /**
     * 下载备份文件（backup/ 目录不在 web 可访问范围内，需流式输出）
     */
    function download()
    {
        set_time_limit(0); // 大文件下载不受执行时间限制
        $this->getAdmin();
        is_demo_mode(true);
        $auth = $this->checkAuth();
        if ($auth) return $auth;
        $name = basename((string)$this->request->get('name', ''));
        if (!preg_match('/^[\w\-\.\(\)]+\.(sql|zip)$/iu', $name)) {
            return $this->error('备份文件不合法');
        }
        $filePath = root_path() . 'backup/' . $name;
        if (!is_file($filePath)) {
            return $this->error('备份文件不存在');
        }

        $size = filesize($filePath);
        $mime = preg_match('/\.sql$/i', $name) ? 'application/sql' : 'application/zip';
        $isHead = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD';

        // 清空输出缓冲，确保分块内容能实时刷给客户端
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Accept-Ranges: bytes');

        // 解析 Range 请求头，支持断点续传
        $range = $_SERVER['HTTP_RANGE'] ?? '';
        if (preg_match('/bytes=(\d*)-(\d*)/', $range, $m)) {
            $start = $m[1] === '' ? 0 : (int)$m[1];
            $end   = $m[2] === '' ? $size - 1 : (int)$m[2];
            $end   = min($end, $size - 1);

            // Range 越界，返回 416
            if ($start < 0 || $start > $end || $start >= $size) {
                header('HTTP/1.1 416 Range Not Satisfiable');
                header("Content-Range: bytes */{$size}");
                exit;
            }

            $length = $end - $start + 1;
            header('HTTP/1.1 206 Partial Content');
            header("Content-Range: bytes {$start}-{$end}/{$size}");
            header('Content-Length: ' . $length);

            // HEAD 请求只返回响应头，不读文件内容
            if ($isHead) {
                exit;
            }

            $fp = fopen($filePath, 'rb');
            if ($fp === false) {
                exit;
            }
            fseek($fp, $start);
            $sent = 0;
            while ($sent < $length && !feof($fp)) {
                $chunk = fread($fp, min(1048576, $length - $sent)); // 每次读 1MB
                if ($chunk === false) {
                    break;
                }
                echo $chunk;
                flush();
                $sent += strlen($chunk);
            }
            fclose($fp);
            exit;
        }

        // 无 Range 时走完整下载
        header('Content-Length: ' . $size);

        // HEAD 请求只返回响应头，不读文件内容
        if ($isHead) {
            exit;
        }

        $fp = fopen($filePath, 'rb');
        if ($fp === false) {
            exit;
        }
        while (!feof($fp)) {
            $chunk = fread($fp, 1048576); // 每次读 1MB
            if ($chunk === false) {
                break;
            }
            echo $chunk;
            flush();
        }
        fclose($fp);
        exit;
    }

    /**
     * 删除备份文件
     * @param array|string $names 文件名或文件名数组
     */
    function delete(): \think\response\Json
    {
        $this->getAdmin();
        is_demo_mode(true);
        $auth = $this->checkAuth();
        if ($auth) return $auth;

        $names = $this->request->post('names', []);
        if (!is_array($names)) {
            $names = $names ? [$names] : [];
        }
        $deleted = 0;
        foreach ($names as $name) {
            $name = basename((string)$name);
            if (!preg_match('/^[\w\-\.\(\)]+\.(sql|zip)$/iu', $name)) {
                continue;
            }
            $filePath = root_path() . 'backup/' . $name;
            if (is_file($filePath) && @unlink($filePath)) {
                $deleted++;
            }
        }
        return $this->success("已删除 {$deleted} 个备份文件");
    }

    // ==================== 上传备份（分片） ====================

    /**
     * 上传备份文件分片（每片 ≤5MB，规避 FPM post_max_size=20M 限制）
     * 分片暂存 runtime/upload_tmp/{upload_id}/{index}，全部传完后由 uploadMerge 合并
     * @param string $upload_id 前端生成的本次上传唯一标识
     * @param int $index 分片序号（0 起）
     * @param int $total 总分片数
     * @param string $name 原始文件名（仅 .sql / .zip）
     */
    function uploadChunk(): \think\response\Json
    {
        $this->getAdmin();
        is_demo_mode(true);
        $auth = $this->checkAuth();
        if ($auth) return $auth;

        $uploadId = (string)$this->request->post('upload_id', '');
        $index = (int)$this->request->post('index', -1);
        $total = (int)$this->request->post('total', 0);
        $name = basename((string)$this->request->post('name', ''));

        if (!preg_match('/^[0-9a-zA-Z\-_]{6,80}$/', $uploadId)) {
            return $this->error('上传参数不合法');
        }
        if ($index < 0 || $total <= 0 || $index >= $total) {
            return $this->error('分片参数不合法');
        }
        if (!preg_match('/^[\w\-\.\(\)]+\.(sql|zip)$/iu', $name)) {
            return $this->error('仅支持 .sql / .zip 备份文件');
        }

        $file = $this->request->file('chunk');
        if (!$file) {
            return $this->error('未收到文件分片');
        }

        $dir = runtime_path() . 'upload_tmp/' . $uploadId . '/';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $ok = $file->move($dir, (string)$index, true);
        if (!$ok) {
            return $this->error('分片保存失败');
        }
        return $this->success('ok', ['index' => $index, 'total' => $total]);
    }

    /**
     * 合并上传的分片到 backup/ 目录，清理临时分片
     * @param string $upload_id 与 uploadChunk 一致
     * @param int $total 总分片数
     * @param string $name 原始文件名
     */
    function uploadMerge(): \think\response\Json
    {
        $this->getAdmin();
        is_demo_mode(true);
        $auth = $this->checkAuth();
        if ($auth) return $auth;

        $uploadId = (string)$this->request->post('upload_id', '');
        $total = (int)$this->request->post('total', 0);
        $name = basename((string)$this->request->post('name', ''));

        if (!preg_match('/^[0-9a-zA-Z\-_]{6,80}$/', $uploadId)) {
            return $this->error('上传参数不合法');
        }
        if ($total <= 0 || $total > 10000) {
            return $this->error('分片总数不合法');
        }
        if (!preg_match('/^[\w\-\.\(\)]+\.(sql|zip)$/iu', $name)) {
            return $this->error('仅支持 .sql / .zip 备份文件');
        }

        $tmpDir = runtime_path() . 'upload_tmp/' . $uploadId . '/';
        // 校验分片完整
        for ($i = 0; $i < $total; $i++) {
            if (!is_file($tmpDir . $i)) {
                return $this->error("分片 {$i} 缺失，请重新上传");
            }
        }

        $backupDir = root_path() . 'backup';
        if (!is_dir($backupDir)) {
            @mkdir($backupDir, 0777, true);
        }
        // 目标重名时自动加序号，避免覆盖已有备份
        $destName = $name;
        $count = 1;
        while (is_file($backupDir . '/' . $destName)) {
            $info = pathinfo($name);
            $destName = $info['filename'] . "({$count})." . $info['extension'];
            $count++;
        }
        $destPath = $backupDir . '/' . $destName;

        // 流式合并（逐片追加，内存占用仅单片大小）
        $out = @fopen($destPath, 'wb');
        if (!$out) {
            return $this->error('无法创建备份文件');
        }
        for ($i = 0; $i < $total; $i++) {
            $in = @fopen($tmpDir . $i, 'rb');
            if (!$in) {
                fclose($out);
                @unlink($destPath);
                $this->removeDir($tmpDir);
                return $this->error('分片读取失败，请重新上传');
            }
            stream_copy_to_stream($in, $out);
            fclose($in);
        }
        fclose($out);
        $this->removeDir($tmpDir);

        // zip 完整性校验
        if (preg_match('/\.zip$/i', $destName)) {
            $zip = new ZipArchive();
            if ($zip->open($destPath) !== true) {
                @unlink($destPath);
                return $this->error('zip 文件校验失败，请确认上传文件完整');
            }
            $zip->close();
        }

        return $this->success('上传成功', ['name' => $destName]);
    }

    /**
     * 递归删除目录
     */
    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $it = new DirectoryIterator($dir);
        foreach ($it as $item) {
            if ($item->isDot()) {
                continue;
            }
            if ($item->isDir()) {
                $this->removeDir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
        @rmdir($dir);
    }

    // ==================== 工具方法 ====================

    private function statusFile(): string
    {
        return runtime_path() . 'backup_status.json';
    }

    private function lockFile(): string
    {
        return runtime_path() . 'backup.lock';
    }

    /**
     * 获取任务锁（flock 非阻塞）。FPM 下进程若被杀，锁自动释放，无需人工处理。
     * @return resource|false
     */
    private function tryLock()
    {
        $status = $this->readStatus();
        // 超过 1 小时的运行状态视为已中断的残留记录，重置后允许重新开始
        if (!empty($status['running']) && (time() - intval($status['start_time'])) > 3600) {
            $this->writeStatus(['running' => false, 'message' => '上次任务已中断']);
        }

        $lockFp = @fopen($this->lockFile(), 'c');
        if (!$lockFp) {
            return false;
        }
        if (!flock($lockFp, LOCK_EX | LOCK_NB)) {
            fclose($lockFp);
            return false;
        }
        return $lockFp;
    }

    private function writeStatus(array $data): void
    {
        $status = array_merge($this->readStatus(), $data);
        $status['update_time'] = time();
        @file_put_contents($this->statusFile(), json_encode($status, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    private function readStatus(): array
    {
        $file = $this->statusFile();
        if (!is_file($file)) {
            return [
                'task_id'     => '',
                'running'     => false,
                'type'        => '',
                'step'        => '',
                'percent'     => 0,
                'message'     => '',
                'file'        => '',
                'error'       => '',
                'start_time'  => 0,
                'update_time' => 0,
            ];
        }
        $data = json_decode((string)file_get_contents($file), true);
        return is_array($data) ? $data : [];
    }

    /**
     * 输出 JSON 并立即结束请求（配合 fastcgi_finish_request 实现后台任务）
     */
    private function respondJson(array $data): void
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Length: ' . strlen($json));
        echo $json;
        flush();
    }

    /**
     * 进入后台执行模式：断开客户端连接，解除超时限制
     */
    private function prepareBackground(): void
    {
        set_time_limit(0);
        ini_set('memory_limit', '-1');
        ignore_user_abort(true);
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
    }

    private function initIgnore(): void
    {
        set_time_limit(0);
        ini_set('memory_limit', '-1');
        $this->ignore = [];
        foreach (["/web", '.git', '.idea', '/runtime', '/backup', '/.env', '/public/installed.lock'] as $item) {
            $this->ignore[] = joinPath(root_path(), $item);
        }
    }

    private function scanFiles(string $path): array
    {
        $files = [];
        if (!is_dir($path)) {
            return $files;
        }
        $dir = new DirectoryIterator($path);
        foreach ($dir as $item) {
            if ($item->isDot()) {
                continue;
            }
            $currentPath = $item->getPathname();
            foreach ($this->ignore as $ignorePath) {
                if (strpos($currentPath, $ignorePath) === 0) {
                    continue 2;
                }
            }
            if ($item->isDir()) {
                $files = array_merge($files, $this->scanFiles($currentPath));
            } else {
                $files[] = $currentPath;
            }
        }
        return $files;
    }

    private function getRelativePath(string $basePath): string
    {
        $absolutePath = root_path();
        return preg_replace('/^' . preg_quote($absolutePath, '/') . '/', '', $basePath);
    }

    private function getBackupFiles(): array
    {
        $dir = root_path() . 'backup';
        if (!is_dir($dir)) {
            return [];
        }
        $files = [];
        $iterator = new DirectoryIterator($dir);
        foreach ($iterator as $item) {
            if ($item->isDot() || $item->isDir()) {
                continue;
            }
            if (preg_match('/\.(sql|zip)$/i', $item->getFilename())) {
                $files[] = $item->getPathname();
            }
        }
        usort($files, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        return $files;
    }

    private function formatSize(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2) . ' GB';
        }
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }
}
