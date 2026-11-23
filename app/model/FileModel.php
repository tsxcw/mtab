<?php

namespace app\model;

use think\Model;

class FileModel extends Model
{
    protected $name = "file";
    protected $pk = "id";

    /**
     * 通过文件魔数字节检测 MIME 类型，无需 fileinfo 扩展
     */
    public static function detectMimeType($filepath): string
    {
        $fh = fopen($filepath, 'rb');
        if (!$fh) {
            return 'application/octet-stream';
        }
        // 读取 32 字节：既够判断各类魔数，也能完整覆盖 "<!DOCTYPE svg ..." 这类 svg 首行声明
        $bytes = fread($fh, 32);
        fclose($fh);

        if ($bytes === false || strlen($bytes) < 4) {
            return 'application/octet-stream';
        }

        if (strncmp($bytes, "\x89PNG\x0D\x0A\x1A\x0A", 8) === 0) {
            return 'image/png';
        }
        if (strncmp($bytes, "\xFF\xD8\xFF", 3) === 0) {
            return 'image/jpeg';
        }
        if (strncmp($bytes, "GIF8", 4) === 0) {
            return 'image/gif';
        }
        if (strncmp($bytes, "RIFF", 4) === 0 && isset($bytes[8]) && substr($bytes, 8, 4) === 'WEBP') {
            return 'image/webp';
        }
        if (strncmp($bytes, "\x00\x00\x01\x00", 4) === 0) {
            return 'image/x-icon';
        }
        // MP4 / MOV：偏移 4 字节处为 ftyp，品牌 "qt  " 表示 QuickTime
        if (substr($bytes, 4, 4) === 'ftyp') {
            return substr($bytes, 8, 4) === 'qt  ' ? 'video/quicktime' : 'video/mp4';
        }
        // WebM / Matroska：EBML 头
        if (strncmp($bytes, "\x1A\x45\xDF\xA3", 4) === 0) {
            return 'video/webm';
        }
        // OGG 容器（包含 ogg 视频）
        if (strncmp($bytes, "OggS", 4) === 0) {
            return 'video/ogg';
        }
        if (strncmp($bytes, "%PDF", 4) === 0) {
            return 'application/pdf';
        }
        if (strncmp($bytes, "PK\x03\x04", 4) === 0) {
            return 'application/zip';
        }

        $firstLine = strtolower(explode("\n", $bytes)[0]);
        // 常见 svg 开头：直接 <svg、带 xml 声明、或旧式 <!DOCTYPE svg ...>
        if (strpos($firstLine, '<svg') !== false || strpos($firstLine, '<?xml') !== false || strpos($firstLine, '<!doctype svg') !== false) {
            return 'image/svg+xml';
        }
        if (strpos($firstLine, '<!doctype html') !== false || strpos($firstLine, '<html') !== false) {
            return 'text/html';
        }

        return 'application/octet-stream';
    }

    function getPathAttr($value)
    {
        return joinPath("/", $value);
    }

    public static function addFile($file, $user_id = null)
    {
        $originPath = joinPath(public_path(), $file);
        $hash = hash_file('md5', $originPath);
        $find = self::where('hash', $hash)->find();
        if ($find) {
            //如果文件不存在，就删除记录
            if (!file_exists(joinPath(public_path(), $find['path']))) {
                $find->delete();
            } else {
                //存在就返回数据库的路径
                if ($find['path'] !== $file) {
                    unlink($originPath);
                }
                return joinPath('/', $find['path']);
            }
        }
        if (file_exists($originPath)) {
            clearstatcache(true, $originPath);
            $info = [];
            $info["path"] = $file;
            $info["user_id"] = $user_id;
            $info['create_time'] = date("Y-m-d H:i:s");
            $info['size'] = filesize($originPath);
            $info['hash'] = $hash;
            $info["mime_type"] = self::detectMimeType($originPath);
            self::insert($info);
            return joinPath('/', $info['path']);
        }
        return false;
    }

    static function delFile($path): bool
    {
        $path = joinPath(public_path(), $path);
        if (file_exists($path)) {
            $hash = hash_file("md5", $path);
            unlink($path);
            $find = self::where("hash", $hash)->find();
            if ($find) {
                $find->delete();
            }
        }
        return true;
    }

    static function moveFile($oldPath, $newPath): bool
    {
        $path = joinPath(public_path(), $oldPath);
        $Path2 = joinPath(public_path(), $newPath);
        if (file_exists($path)) {
            $find = self::where("hash", hash_file('md5', $path))->find();
            if ($find) {
                rename($path, $Path2);
                $find->path = joinPath('/', $newPath);
                $find->save();
            }
        }
        return true;
    }

    function user(): \think\model\relation\HasOne
    {
        return $this->hasOne(UserModel::class, "id", "user_id")->field("id,nickname,mail");
    }
}
