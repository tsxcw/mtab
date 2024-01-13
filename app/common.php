<?php
// 应用公共文件
function validateEmail($email): bool
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    } else {
        return true;
    }
}

function uuid(): string
{
    $chars = md5(uniqid(mt_rand(), true));
    return substr($chars, 0, 8) . '-'
        . substr($chars, 8, 4) . '-'
        . substr($chars, 12, 4) . '-'
        . substr($chars, 16, 4) . '-'
        . substr($chars, 20, 12);
}

function renderToken($t = 'tab'): string
{
    $s = uuid() . strval(time()) . $t;
    return md5($s);
}

function joinPath($path1, $path2)
{
    return preg_replace("#//#", "/", $path1 . $path2);
}

function getRealIp(): string
{
    $ip1 = request()->header('x-forwarded-for', false);
    if ($ip1) {
        $arr = explode(",", $ip1);
        if(count($arr)>0){
            return trim($arr[0]);
        }
    }
    return request()->ip();
}

function plugins_path($path=''): string
{
    if (mb_strlen($path) > 0) {
        if (strpos($path, "/") == 0) {
            return $_ENV['plugins_dir_name'] . $path;
        }
        return $_ENV['plugins_dir_name'] . '/' . $path;
    }
    return $_ENV['plugins_dir_name'] . "/";
}

function is_demo_mode($is_exit = false)
{
    if (env('demo_mode')) {
        if ($is_exit) {
            json(["msg" => "演示模式，部分功能受限,禁止更新或删除！", "code" => 0])->send();
            exit();
        }
        return true;
    }
    return false;
}