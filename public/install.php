<?php
//设置永不超时
set_time_limit(0);
//设置内存可以使用512MB
ini_set('memory_limit', '512M');
function isAjaxRequest(): bool
{
    if (isset($_SERVER['HTTP_REFERER'])) {
        return true;
    } else {
        return false;
    }
}

if (file_exists("./installed.lock")) {
    if (isAjaxRequest()) {
        echo json_encode(['code' => 1000, 'msg' => '系统已安装，请勿重复安装！'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    //已安装则跳回首页（静态固定地址跳转，非用户可控）
    exit('<!DOCTYPE html><meta charset="utf-8"><script>location.replace("/")</script><meta http-equiv="refresh" content="0;url=/">');
}

//PHP 8.1 起 mysqli 默认抛异常，改为 PHP 7 的返回 false 模式，避免“删库时库不存在”这类正常情况中断安装
mysqli_report(MYSQLI_REPORT_OFF);
//允许后台运行
ini_set('max_execution_time', 0);
//取消超时限制
set_time_limit(0);
//内存允许更大的
ini_set('memory_limit', '512M');

class Install
{
    protected $defError = [
        1045 => [
            'reason' => '用户名或密码错误，或用户没有访问权限。'
        ],
        2002 => [
            'reason' => '数据库服务器未运行或无法连接到指定的主机和端口。'
        ],
        1049 => [
            'reason' => '指定的数据库不存在。'
        ],
        1044 => [
            'reason' => '用户没有访问指定数据库的权限。'
        ],
        2003 => [
            'reason' => '无法连接到 MySQL 服务器，可能是防火墙阻止了连接。'
        ]
    ];

    function __construct()
    {
    }

    //内容，是否换行
    function json($arr)
    {
        return json_encode($arr, JSON_UNESCAPED_UNICODE);
    }

    function index(): string
    {
        $var = [
            'title' => 'mTab新标签页安装程序',
            'customHead|raw' => '',
            'keywords' => '',
            'description' => '',
            'favicon' => '/static/mtab.png',
            'version' => time()
        ];
        //正则替换模板变量{$title},{$customHead|raw}
        $template = file_get_contents(__DIR__ . '/dist/index.html');
        return preg_replace_callback('/{\$([\w|]+)}/', function ($matches) use ($var) {
            $key = $matches[1];
            return $var[$key] ?? '';
        }, $template);
    }

    function connect($form, $tableName = null): mysqli
    {
        //将上面的值提取到数据库配置中
        $db_host = $form['db_host'];
        $db_username = $form['db_username'];
        $db_password = $form['db_password'];
        $db_port = $form['db_port'];
        $conn = @new mysqli($db_host, $db_username, $db_password, $tableName, $db_port);
        if ($conn->connect_error) {
            $errorCode = $conn->connect_errno; // 获取错误代码
            // 检查 defError 中是否存在该错误代码
            $errorMessage = isset($this->defError[$errorCode])
                ? $this->defError[$errorCode]['reason']
                : $conn->connect_error; // 如果不存在，返回原始错误信息
            throw new Exception($errorMessage, 500);
        }

        return $conn;
    }

    function ext()
    {
        $phpVersion = phpversion();
        $php_version = false;
        if (version_compare($phpVersion, '7.4', '>=')) {
            $php_version = true;
        }
        $fileinfo_ext = false;
        if (extension_loaded('fileinfo')) {
            $fileinfo_ext = true;
        }
        $zip_ext = false;
        if (extension_loaded('zip')) {
            $zip_ext = true;
        }
        $mysqli_ext = false;
        if (extension_loaded('mysqli')) {
            $mysqli_ext = true;
        }
        $curl_ext = false;
        if (extension_loaded('curl')) {
            $curl_ext = true;
        }
        return $this->json(['code' => 200, 'data' => [
            'php_version' => $php_version,
            'fileinfo_ext' => $fileinfo_ext,
            'zip_ext' => $zip_ext,
            'mysqli_ext' => $mysqli_ext,
            'curl_ext' => $curl_ext
        ]]);
    }

    function testDb()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        try {
            $conn = $this->connect($data);
            $version = $conn->server_info;
            $conn->close();
            return $this->json(['code' => 200, 'msg' => '连接成功', 'data' => ['mysql_version' => $version]]);
        } catch (Exception $e) {
            return $this->json(['code' => 500, 'msg' => $e->getMessage()]);
        }
    }

    function install()
    {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        //关闭nginx/fastcgi缓冲，让进度实时下发
        header('X-Accel-Buffering: no');
        //关闭PHP自身输出缓冲
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        $form = json_decode(file_get_contents('php://input'), true);
        $database_type = $form['database_type'];
        $table_name = $form['table_name'];
        $db_host = $form['db_host'];
        $db_username = $form['db_username'];
        $db_password = $form['db_password'];
        $db_port = $form['db_port'];
        $admin_password = $form['admin_password'];
        $admin_email = $form['admin_email'];
        try {
            $this->progress(5, '正在连接数据库...');
            $conn = $this->connect($form);
            $this->progress(8, '数据库连接成功');
            if ($database_type == 1) { //全新安装
                $this->progress(10, '正在重置数据库...');
                //首次安装时库还不存在，必须用 IF EXISTS，否则 PHP 8 下会因删除失败抛异常
                $conn->query("DROP DATABASE IF EXISTS `$table_name`"); //删除原来的
                $sql = "CREATE DATABASE `$table_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;";
                if ($conn->query($sql) !== TRUE) {
                    throw new Exception('数据表创建失败');
                }
                $conn->close();
                $conn = $this->connect($form, $table_name);
                $this->progress(15, '数据库创建成功');
                //数据库的格式内容数据（建表，进度20~50%）
                $this->progress(20, '正在导入建表SQL...');
                $this->execSqlFile($conn, '../install.sql', function ($done, $total) {
                    $this->progress(20 + intval($done / $total * 30), "正在导入建表SQL ({$done}/{$total})");
                });
                //默认的基础数据（进度50~80%）
                $this->progress(50, '正在导入默认数据...');
                $this->execSqlFile($conn, '../defaultData.sql', function ($done, $total) {
                    $this->progress(50 + intval($done / $total * 30), "正在导入默认数据 ({$done}/{$total})");
                });
                //添加默认管理员（85%）
                $this->progress(85, '正在创建管理员账号...');
                $admin_password = md5($admin_password);
                $AdminSql = ("INSERT INTO user (mail, password, create_time, login_ip, register_ip, manager, login_fail_count, login_time) VALUES ('$admin_email', '$admin_password', null, null, null, 1, DEFAULT, null);");
                if ($conn->query($AdminSql) === false) {
                    throw new Exception('创建管理员失败: ' . $conn->error);
                }
                $conn->close();
            }
            //写入配置文件（90%）
            $this->progress(90, '正在写入配置文件...');
            $env = <<<EOF
                APP_DEBUG = false
                
                [APP]
                
                [DATABASE]
                TYPE = mysql
                HOSTNAME = {$db_host}
                DATABASE = {$table_name}
                USERNAME = {$db_username}
                PASSWORD = {$db_password}
                HOSTPORT =  {$db_port}
                CHARSET = utf8mb4
                DEBUG = false
                
                [CACHE]
                DRIVER = file
                
                EOF;
            if (file_put_contents('../.env', $env) === false) {
                throw new Exception('配置文件写入失败，请检查目录权限');
            }
            //写入安装锁（95%）
            $this->progress(95, '正在写入安装锁...');
            if (file_put_contents('./installed.lock', 'installed') === false) {
                throw new Exception('安装锁写入失败，请检查目录权限');
            }
            //安装完成
            $this->progress(100, '安装完成');
        } catch (\Exception $e) {
            $this->fail($e->getMessage());
        }
    }

    //进度输出
    private function progress(int $percent, string $msg)
    {
        echo json_encode(['percent' => $percent, 'msg' => $msg], JSON_UNESCAPED_UNICODE) . "\n";
        while (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }

    //错误输出
    private function fail(string $msg)
    {
        echo json_encode(['error' => $msg], JSON_UNESCAPED_UNICODE) . "\n";
        while (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }

    //按分号切分SQL语句（忽略字符串内的分号），并过滤整行注释与空语句
    private function splitSql(string $sql): array
    {
        $statements = [];
        $len = strlen($sql);
        $start = 0;
        $inString = false;
        for ($i = 0; $i < $len; $i++) {
            $ch = $sql[$i];
            if ($inString) {
                if ($ch === '\\') {
                    $i++;
                    continue;
                }
                if ($ch === "'") {
                    $inString = false;
                }
                continue;
            }
            if ($ch === "'") {
                $inString = true;
                continue;
            }
            if ($ch === ';') {
                $next = ($i + 1 < $len) ? $sql[$i + 1] : '';
                if ($next === "\n" || $next === "\r" || $next === '') {
                    $stmt = $this->cleanStatement(substr($sql, $start, $i - $start + 1));
                    if ($stmt !== '') {
                        $statements[] = $stmt;
                    }
                    $start = $i + 1;
                }
            }
        }
        $tail = $this->cleanStatement(substr($sql, $start));
        if ($tail !== '') {
            $statements[] = $tail;
        }
        return $statements;
    }

    //移除整行注释（-- 或 #）与空行，纯注释语句返回空串
    private function cleanStatement(string $stmt): string
    {
        $lines = explode("\n", $stmt);
        $out = [];
        foreach ($lines as $line) {
            $line = rtrim($line);
            $trimmed = ltrim($line);
            if ($trimmed === '') {
                continue;
            }
            if (strpos($trimmed, '--') === 0 || $trimmed[0] === '#') {
                continue;
            }
            $out[] = $line;
        }
        return implode("\n", $out);
    }

    //分句执行SQL文件，每执行一条回调一次进度；兼容旧库的补充型语句（已存在/重复类错误跳过），其他错误中断
    private function execSqlFile(\mysqli $conn, string $filePath, callable $progressFn)
    {
        //已存在/重复类错误码：如重复建表、重复添加列/主键/索引、重复数据等
        $ignoreErrors = [
            1005, // Can't create table (errno)
            1050, // Table already exists
            1060, // Duplicate column name
            1061, // Duplicate key name
            1062, // Duplicate entry
            1068, // Multiple primary key defined
            1091, // Can't DROP ... check that column/key exists
            1022, // Duplicate key on write or update
        ];
        $sql = file_get_contents($filePath);
        if ($sql === false) {
            throw new Exception("无法读取SQL文件: {$filePath}");
        }
        //去除BOM
        if (substr($sql, 0, 3) === "\xEF\xBB\xBF") {
            $sql = substr($sql, 3);
        }
        $statements = $this->splitSql($sql);
        $total = count($statements);
        if ($total === 0) {
            throw new Exception("SQL文件为空: {$filePath}");
        }
        foreach ($statements as $i => $stmt) {
            if ($conn->query($stmt) === false) {
                if (!in_array($conn->errno, $ignoreErrors, true)) {
                    throw new Exception("SQL执行失败（第" . ($i + 1) . "条）: " . $conn->error);
                }
            }
            $progressFn($i + 1, $total);
        }
    }
}

$handle = new Install();
$path = "/";
if (isset($_GET['s'])) {
    $path = $_GET['s'];
}
switch ($path):
    case '/testDb':
        echo $handle->testDb();
        break;
    case '/install':
        $handle->install();
        break;
    case '/ext':
        echo $handle->ext();
        break;
    default:
        echo $handle->index();
        break;
endswitch;
exit;

