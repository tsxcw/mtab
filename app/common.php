<?php
// 应用公共文件
$error_custom= '111';
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

function getRealIp()
{
    $ip1 = request()->header('x-forwarded-for', false);
    if ($ip1) {
        return $ip1;
    }
    return request()->ip();
}