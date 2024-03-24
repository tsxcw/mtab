<?php

class PluginStaticSystem
{
    function index($dir, $file)
    {
        $file = preg_replace("#\.\.#", "", $file);
        $file = plugins_path("static/" . $file);
        if (file_exists($file)) {
            return download($file)->force(false)->mimeType(self::mimeType($file))->expire(60*60*24*7);
        }
        return response('', 404);
    }

    static function mimeType($ext): string
    {
        $ext = pathinfo($ext);
        if ($ext['extension']) {
            $ext = $ext["extension"];
            $type = array(
                'css' => 'text/css',
                'js' => 'text/javascript',
                'woff' => 'font/woff',
                'ttf' => 'font/truetype',
                'ico' => 'image/x-icon',
                'jpg' => 'image/jpeg',
                'png' => 'image/png',
                'webp' => 'image/webp',
                'gif' => 'image/gif',
                'svg' => 'image/svg+xml',
                'json'=> 'application/json',
                'pdf' => 'application/pdf',
                'doc' => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'xls' => 'application/vnd.ms-excel',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'zip' => 'application/zip',
                'rar' => 'application/x-rar-compressed',
                'txt' => 'text/plain',
                'html' => 'text/html',
            );
            if (isset($type[$ext])) {
                return $type[$ext];
            }
        }
        return '';
    }
}