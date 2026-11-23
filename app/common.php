<?php
//程序版本号，请勿修改
const app_version = '3.2.8';
//程序内部更新版本代码，请勿修改
const app_version_code = 328;
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

function joinPath($path1, $path2='')
{
    return preg_replace("#/+/#", "/", $path1 . $path2);
}

function getRealIp(): string
{
    $ip1 = request()->header('x-forwarded-for', false);
    if ($ip1) {
        $arr = explode(",", $ip1);
        if (count($arr) > 0) {
            return trim($arr[0]);
        }
    }
    return request()->ip();
}

function plugins_path($path = ''): string
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

function modifyImageUrls($htmlContent, $newBaseUrl): string
{
    // 内容可能来自数据库或接口，未设置时为 null，PHP 8.1+ 传给 mb_convert_encoding 会触发弃用错误
    if (!is_string($htmlContent)) {
        return '';
    }
    try {
        $dom = new DOMDocument();
        // HTML-ENTITIES 编码在 PHP 8.2+ 已弃用，改用等价的数值实体转换，兼容 PHP 7.4/8.x
        $htmlContent = mb_encode_numericentity($htmlContent, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8');
        libxml_use_internal_errors(true);
        $wrappedContent = '<div>' . $htmlContent . '</div>';
        $dom->loadHTML($wrappedContent, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $images = $dom->getElementsByTagName('img');
        foreach ($images as $img) {
            $oldSrc = $img->getAttribute('src');
            if (!preg_match('/^http/', $oldSrc)) {
                $newSrc = $newBaseUrl . joinPath('/',$oldSrc);
                $img->setAttribute('src', $newSrc);
            }
        }

        // 返回修改后的 HTML，去掉根节点
        $newHtmlContent = '';
        foreach ($dom->documentElement->childNodes as $child) {
            $newHtmlContent .= $dom->saveHTML($child);
        }
        return $newHtmlContent;
    } catch (Exception $e) {
        return $htmlContent;
    }
}

function removeImagesUrls($htmlContent, $newBaseUrl)
{
    // 内容可能来自数据库或接口，未设置时为 null，PHP 8.1+ 传给 mb_convert_encoding 会触发弃用错误
    if (!is_string($htmlContent)) {
        return '';
    }
    try {
        $dom = new DOMDocument();
        // HTML-ENTITIES 编码在 PHP 8.2+ 已弃用，改用等价的数值实体转换，兼容 PHP 7.4/8.x
        $htmlContent = mb_encode_numericentity($htmlContent, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8');
        libxml_use_internal_errors(true);
        $wrappedContent = '<div>' . $htmlContent . '</div>';
        $dom->loadHTML($wrappedContent, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $domain = $newBaseUrl;
        $images = $dom->getElementsByTagName('img');
        foreach ($images as $img) {
            $oldSrc = $img->getAttribute('src');
            $newSrc = str_replace($domain, '', $oldSrc);
            $img->setAttribute('src', $newSrc);
        }

        // 返回修改后的 HTML，去掉根节点
        $newHtmlContent = '';
        foreach ($dom->documentElement->childNodes as $child) {
            $newHtmlContent .= $dom->saveHTML($child);
        }
        return $newHtmlContent;
    } catch (Exception $e) {
        return $htmlContent;
    }
}

/**
 * 通过读取文件头 magic bytes 判断文件类型并返回对应扩展名
 * 纯 PHP 实现，不依赖 fileinfo 扩展
 *
 * @param string $path 文件路径
 * @return string 文件扩展名（如 png、jpg），无法识别则返回 'unknown'
 * @throws InvalidArgumentException 文件不存在或不可读时抛出
 */
function getFileExtByContent($path): string
{
    if (!file_exists($path) || !is_readable($path)) {
        throw new InvalidArgumentException("File does not exist or is not readable: {$path}");
    }

    // 读取文件前 8 字节用于识别文件头签名
    $handle = fopen($path, 'rb');
    $header = fread($handle, 8);
    fclose($handle);

    // PNG 文件头签名: \x89PNG\r\n\x1a\n
    if (str_starts_with($header, "\x89PNG\r\n\x1a\n")) {
        return 'png';
    }
    // JPEG 文件头签名: \xFF\xD8\xFF
    if (str_starts_with($header, "\xff\xd8\xff")) {
        return 'jpg';
    }
    // GIF 文件头签名: GIF87a 或 GIF89a
    if (str_starts_with($header, 'GIF87a') || str_starts_with($header, 'GIF89a')) {
        return 'gif';
    }
    // WebP 文件头: RIFF....WEBP（前4字节为 RIFF，偏移8-11为 WEBP）
    if (str_starts_with($header, 'RIFF') && strlen($header) >= 12 && substr($header, 8, 4) === 'WEBP') {
        return 'webp';
    }
    // PDF 文件头签名: %PDF
    if (str_starts_with($header, '%PDF')) {
        return 'pdf';
    }
    // ICO 文件头签名: \x00\x00\x01\x00（版本号 1）
    if (str_starts_with($header, "\x00\x00\x01\x00")) {
        return 'ico';
    }
    // MP4/M4V 等 ISOBMFF 容器，偏移4-7为 ftyp 标识
    if (str_starts_with($header, "\x00\x00\x00") && strlen($header) >= 12 && substr($header, 4, 4) === 'ftyp') {
        return 'mp4';
    }

    // SVG: 读取前 512 字节文本，匹配 <svg 标签
    $headText = @file_get_contents($path, false, null, 0, 512);
    if ($headText !== false && preg_match('/<svg[\s>]/i', $headText)) {
        return 'svg';
    }

    // 纯文本: 前 512 字节全部为可打印 ASCII 字符或常见控制字符（换行、回车、制表符）
    if ($headText !== false && preg_match('/^[\x20-\x7E\x09\x0A\x0D]*$/', $headText)) {
        return 'txt';
    }

    return 'unknown';
}
