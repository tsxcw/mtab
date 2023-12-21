<?php
function params($key, $default_value = '')
{
    return $_POST[$key] ?? $default_value;
}

if (file_exists('./installed.lock')) {//如果没有安装的就提示安装
    header('Location: /');
}
// 获取当前PHP版本
$phpVersion = phpversion();
// 检查是否大于7.3
$php_version = false;
if (version_compare($phpVersion, '7.4', '>')) {
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
// 连接数据库
$servername = 'localhost';
$db_username = params('db_username', false);
$db_password = params('db_password', false);
$db_host = params('db_host', '');
$db_port = params('db_port', 3306);
$table_name = params('table_name', '');
$admin_email = params('admin_email', '');
$admin_password = params('admin_password', '');
$database_type = params('database_type', 1);//1=全新安装，2=使用已存在数据库不安装数据库
$redis_host = params('redis_host', '127.0.0.1');
$redis_port = params('redis_port', 6379);
$redis_password = params('redis_password', '');
$error = false;
$conn = null;
$status = false;
if ($db_username && $php_version && $fileinfo_ext && $curl_ext && $zip_ext) {
    $conn = new mysqli($db_host, $db_username, $db_password, null, $db_port);
    if ($conn->connect_error) {
        $error = '数据库连接失败';
    } else {
        if ($database_type == 1) {//全新安装
            $sql = "DROP DATABASE $table_name";//删除原来的
            $conn->query($sql);
            $sql = "CREATE DATABASE $table_name";//创建新的
            if ($conn->query($sql) !== TRUE) {
                $error = '数据表创建失败';
            }
            $conn = new mysqli($db_host, $db_username, $db_password, $table_name, $db_port);
            $sql_file_content = file_get_contents('../install.sql');
            // 解析SQL文件内容并执行
            $sql_statements = explode(';', trim($sql_file_content));
            foreach ($sql_statements as $sql_statement) {
                if (!empty($sql_statement)) {
                    $conn->query($sql_statement);
                }
            }
            $admin_password = md5($admin_password);
            //添加默认管理员
            $AdminSql = ("
                    INSERT INTO user (mail, password, create_time, login_ip, register_ip, manager, login_fail_count, login_time)
                    VALUES ('$admin_email', '$admin_password', null, null, null, 1, DEFAULT, null);
                 ");
            $conn->query($AdminSql);
            $conn->close();
        }
        file_put_contents('./installed.lock', 'installed');
        $status = true;
    }
}
if ($status) {
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

[REDIS]
HOST = {$redis_host}
PORT = {$redis_port}
PASSWORD = {$redis_password}
SELECT = 0

[CACHE]
DRIVER = file

EOF;
    file_put_contents('../.env', $env);
}

?>

<?php if ($status === false) { ?>
    <!DOCTYPE html>
    <html lang="zh">
    <head>
        <title>Mtab安装页面</title>
        <meta name='viewport' content='width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no'>
        <style>
            body {
                font-family: Arial, sans-serif;
                background: url("static/background.jpeg") no-repeat center/cover;
            }

            form {
                max-width: 900px;
                margin: 0 auto 100px;
                background-color: #fff;
                padding: 20px 20px 30px 20px;
                border-radius: 12px;
                box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            }

            label {
                display: block;
                margin-bottom: 10px;
                font-weight: bold;
                margin-top: 15px;
            }

            input[type='text'], input[type='password'], input[type='number'] {
                text-indent: 15px;
                width: calc(100% - 8px);
                height: 45px;
                line-height: 30px;
                border: 2px solid transparent;
                border-radius: 10px;
                outline: none;
                background-color: #f3f3f3;
                color: #0d0c22;
                transition: .5s ease;
                font-size: 16px;
            }

            input[type='submit'] {
                width: 100%;
                background-color: rgb(255, 171, 84);
                color: #fff;
                border-radius: 0.9em;
                border: none;
                padding: 0.8em 1.2em 0.8em 1em;
                transition: all ease-in-out 0.2s;
                font-size: 16px;
            }

            .input:focus, input:hover {
                outline: none;
                border-color: rgba(255, 171, 84, 0.85);
                background-color: #fff;
                box-shadow: 0 0 0 5px rgba(253, 224, 99, 0.3);
            }

            input[type='submit']:hover {
                background-color: rgb(255, 171, 84);
            }

            #error-popup {
                position: fixed;
                left: 0;
                right: 0;
                top: 0;
                bottom: 0;
                margin: auto;
                width: 250px;
                height: fit-content;
                padding: 20px;
                background-color: rgb(211, 82, 0);
                color: #fff;
                border-radius: 12px;
                justify-content: center;
                z-index: 9999;
            }
        </style>
        <link rel='icon' href='favicon.png'>
    </head>
    <body>
    <?php if ($error) { ?>
        <div id='error-popup'>
            <div style='text-align: center'><b>遇到错误了！</b></div>
            <p style='text-align: center;font-size: 18px'><?php echo $error ?></p>
        </div>

        <script>
            setTimeout(function () {
                document.querySelector("#error-popup").style.display = "none";
            }, 3000);
        </script>
        }
    <?php } ?>
    <h1 style="text-align: center;color: #fff">MTAB书签安装程序</h1>
    <form method='post' action='install.php'>
        <div style="font-size: 25px;font-weight: bold;margin-bottom: 15px;">
            请优先授权程序可执行权限(755及以上的权限)，并检查并安装以下php扩展
        </div>
        <div style="margin-bottom: 30px;display: flex;flex-wrap: wrap;gap:15px 40px;">
            <b>
                php版本>7.4
                <?php if ($php_version) { ?>
                    <span style='color: limegreen'>✔</span>
                <?php } else { ?>
                    <span style='color: red'>✘</span>
                <?php } ?>
            </b>
            <b>
                fileinfo扩展
                <?php if ($fileinfo_ext) { ?>
                    <span style='color: limegreen'>✔</span>
                <?php } else { ?>
                    <span style='color: red'>✘</span>
                <?php } ?>
            </b>
            <b>
                zip扩展
                <?php if ($zip_ext) { ?>
                    <span style='color: limegreen'>✔</span>
                <?php } else { ?>
                    <span style='color: red'>✘</span>
                <?php } ?>
            </b>
            <b>
                curl扩展
                <?php if ($curl_ext) { ?>
                    <span style='color: limegreen'>✔</span>
                <?php } else { ?>
                    <span style='color: red'>✘</span>
                <?php } ?>
            </b>
            <b>
                mysqli扩展
                <?php if ($mysqli_ext) { ?>
                    <span style='color: limegreen'>✔</span>
                <?php } else { ?>
                    <span style='color: red'>✘</span>
                <?php } ?>
            </b>
        </div>
        <label for='db_host'>mysql数据库地址:</label>
        <input value="<?php echo $db_host; ?>" placeholder="本地一般是127.0.0.1" type='text' name='db_host' id='db_host'
               required><br>
        <label for='db_port'>mysql数据库端口号:</label>
        <input type='number' value="<?php echo $db_port; ?>" placeholder='默认 3306' name='db_port' id='db_port'
               required><br>
        <label for='db_username'>mysql数据库用户名:</label>
        <input type='text' placeholder="请输入数据库用户名" value="<?php echo $db_username; ?>" name='db_username'
               id='db_username' required><br>
        <label for='db_password'>mysql数据库密码:</label>
        <input type='text' name='db_password' value="<?php echo $db_password; ?>" placeholder="请输入数据库密码"
               id='db_password' required><br>
        <label for='table_name'>mysql数据库名称:</label>
        <input type='text' value="<?php echo $table_name; ?>" placeholder="请输入创建的数据库名称" name='table_name'
               id='table_name' required><br>

        <label for='redis_port'>管理员邮箱账号:</label>
        <input type='text' value="<?php echo $admin_email; ?>" placeholder='请输入邮箱,用于默认的管理员账号登录使用'
               name='admin_email'
               id='redis_port'
               required><br>
        <label for='redis_port'>管理员密码:</label>
        <input type='text' value="<?php echo $admin_password; ?>" placeholder='请设置管理员账号密码'
               name='admin_password'
               id='redis_port'
               required><br>
        <label for='redis_port'>数据库安装其他选项</label>
        <label for='install_other'></label>
        <label>
            <input type='radio' name='database_type' value='1' required>
            全新安装(如果数据库存在则删除原来的数据库，重新安装)
        </label>
        <label>
            <input type='radio' name='database_type' value='2' required>
            使用已存在数据库（不会覆盖数据库，仅安装代码，一般用于负载均衡部署）
        </label>
        <input type='submit' value='安装' style="margin-top: 50px"></form>
    </body>
    </html>
<?php } else { ?>
    <!DOCTYPE html>
    <html lang="zh">
    <head>
        <meta charset="UTF-8">
        <title>网站安装完毕</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                background-color: #fff;
                text-align: center;
                margin: 0;
                padding: 0;
            }

            .container {
                background-color: #fff;
                padding: 20px;
                border-radius: 10px;
                box-shadow: 0 0 10px rgba(0, 0, 0, 0.2);
                margin: 50px auto;
                max-width: 800px;
            }

            h1 {
                color: #333;
            }

            p {
                color: #666;
                font-size: 18px;
            }

            .btn-container {
                margin-top: 20px;
            }

            .btn {
                display: inline-block;
                padding: 10px 20px;
                margin-right: 10px;
                background-color: rgb(255, 171, 84);
                color: #fff;
                text-decoration: none;
                border-radius: 5px;
                transition: background-color 0.3s;
            }

            .btn:hover {
                background-color: rgb(255, 147, 38);
            }
        </style>
        <link rel='icon' href='favicon.png'>
    </head>
    <body>
    <div class='container'>
        <h1 style="align-content: center">网站安装完毕</h1>
        <div style="display: flex;justify-content: center">
            <svg style="width: 100px" t='1694607796889' class='icon' viewBox='0 0 1024 1024' version='1.1'
                 xmlns='http://www.w3.org/2000/svg'
                 p-id='40460' width='128' height='128'>
                <path d='M512 0C230.4 0 0 230.4 0 512c0 281.6 230.4 512 512 512 281.6 0 512-230.4 512-512C1024 230.4 793.6 0 512 0zM512 960c-249.6 0-448-204.8-448-448 0-249.6 204.8-448 448-448 249.6 0 448 198.4 448 448C960 761.6 761.6 960 512 960zM691.2 339.2 454.4 576 332.8 454.4c-19.2-19.2-51.2-19.2-76.8 0C243.2 480 243.2 512 262.4 531.2l153.6 153.6c19.2 19.2 51.2 19.2 70.4 0l51.2-51.2 224-224c19.2-19.2 25.6-51.2 0-70.4C742.4 320 710.4 320 691.2 339.2z'
                      fill='#54E283' p-id='40461'></path>
            </svg>
        </div>
        <p>欢迎使用Mtab书签，<br>点击下方按钮跳转到首页。</p>
        <div class='btn-container'>
            <a class='btn' href='/'>进入首页</a>
        </div>
        <p>后台进入方式，需要用管理员账户登录客户端<br/> <b>进入设置->个人中心->管理后台</b></p>
    </div>
    </body>
    </html>
<?php } ?>
