<?php
//docker环境下自动安装逻辑
$MYSQL_HOST = getenv("MYSQL_HOST");
$MYSQL_PORT = getenv("MYSQL_PORT");
$MYSQL_USER = getenv("MYSQL_USER");
$MYSQL_PASSWORD = getenv("MYSQL_PASSWORD");
$MYSQL_DATABASE = getenv("MYSQL_DATABASE");
$ADMIN_USER = getenv("ADMIN_USER");
$ADMIN_PASSWORD = getenv("ADMIN_PASSWORD");
if (empty($ADMIN_PASSWORD)) {
    $ADMIN_PASSWORD = '123456';
}
if (empty($ADMIN_USER)) {
    $ADMIN_USER = 'admin';
}
if (empty($MYSQL_PORT)) {
    $MYSQL_PORT = 3306;
}
$status = false;
if ($MYSQL_HOST && $MYSQL_PORT && $MYSQL_USER && $MYSQL_PASSWORD && $MYSQL_DATABASE && $ADMIN_USER && $ADMIN_PASSWORD) {
    print_r("开始安装\n");
    // PHP 8.1 起 mysqli 默认抛异常：重复执行 install.sql（如容器重建）会因字段已存在直接中断安装，
    // 这里关闭异常模式，恢复 PHP 7.4 下“查询失败返回 false 并继续”的行为
    mysqli_report(MYSQLI_REPORT_OFF);
    $conn = new mysqli($MYSQL_HOST, $MYSQL_USER, $MYSQL_PASSWORD, null, (int)$MYSQL_PORT);
    if ($conn->connect_error) {
        die('数据库连接失败');
    }
    $sql = "CREATE DATABASE IF NOT EXISTS $MYSQL_DATABASE";//创建新的
    if ($conn->query($sql) !== TRUE) {
        $error = '数据表创建失败';
    }
    print_r("数据库创建完毕\n");
    $conn = new mysqli($MYSQL_HOST, $MYSQL_USER, $MYSQL_PASSWORD, $MYSQL_DATABASE, (int)$MYSQL_PORT);
    $sql_file_content = file_get_contents('/app/install.sql');
    // 解析SQL文件内容并执行
    $sql_statements = explode(';', trim($sql_file_content));
    foreach ($sql_statements as $sql_statement) {
        if (!empty($sql_statement)) {
            $conn->query($sql_statement);
        }
    }
    //默认的一些基础数据
    $sql_file_content = file_get_contents('/app/defaultData.sql');
    // 解析SQL文件内容并执行
    $sql_statements = explode(';', trim($sql_file_content));
    foreach ($sql_statements as $sql_statement) {
        if (!empty($sql_statement)) {
            $conn->query($sql_statement);
        }
    }
    print_r("数据表创建完毕\n");
    $admin_password = md5($ADMIN_PASSWORD);
    //添加默认管理员
    $AdminSql = ("
                    INSERT INTO user (mail, password, create_time, login_ip, register_ip, manager, login_fail_count, login_time)
                    VALUES ('$ADMIN_USER', '$admin_password', null, null, null, 1, DEFAULT, null);
                 ");
    $conn->query($AdminSql);
    $conn->close();
    print_r("管理员账号创建完毕 账号$ADMIN_USER 密码$ADMIN_PASSWORD\n");
    file_put_contents('/app/public/installed.lock', 'installed');
    //写入安装标识
    $status = true;
}
//如果有环境变量则自动安装，没有则手动安装
if ($status) {
    $env = <<<EOF
APP_DEBUG = false

[APP]

[DATABASE]
TYPE = mysql
HOSTNAME = {$MYSQL_HOST}
DATABASE = {$MYSQL_DATABASE}
USERNAME = {$MYSQL_USER}
PASSWORD = {$MYSQL_PASSWORD}
HOSTPORT =  {$MYSQL_PORT}
CHARSET = utf8mb4
DEBUG = false

[CACHE]
DRIVER = file

EOF;
    file_put_contents('/app/.env', $env);
}

print_r("安装完毕\n");